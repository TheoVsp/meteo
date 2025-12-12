<?php

class Ecmwf extends Provider{

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PUBLIC SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function supportParameter($parameter): bool
    {
       return EcmwfParameters::supportParameter($parameter);
    }

    public static function canProvidesOneData(array $missingDates): bool
    {
        GlobalLogger::$logger->debug("[ECMWF] Check provides one data capabilities on " . count($missingDates)." dates");
        $datetime_max_date = getDatimeWithLatency(EcmwfParameters::getLatency());
        GlobalLogger::$logger->debug("[ECMWF] Max dates " . $datetime_max_date->format(FORMAT_DATE_METEOBLUE_EN));
        foreach ($missingDates as $date){
           $datetime = DateTime::createFromFormat(FORMAT_DATE_EN,$date);
           if($datetime < $datetime_max_date) {
               return true;
           }
        }
        return false;
    }

    public function getData(WeatherParameters $weatherParameters, WeatherData $weatherData, &$error_msg): bool
    {
        GlobalLogger::$logger->info("[ECMWF] Get data");

        ////////////////////////
        // CONVERSION
        ///////////////////////

        /// PARAMETER
        $parameter = $weatherParameters->getParameter(WeatherParameters::PARAMETER);
        if(EcmwfParameters::globalToEcmwf($parameter, $variable, $statistic)) {
            $isGlobal = true;
            // test if global parameter is supported
            if(!Ecmwf::supportParameter($parameter)) {
                $error_msg = "Global parameter ".$parameter." not supported";
                return false;
            }
        }else{
            $isGlobal = false;
            GlobalLogger::$logger->info("Can't convert " . $parameter . " to ECMWF parameter");

            // check mandatory parameters
            if(!$weatherParameters->parameterExist(WeatherParameters::VARIABLE)){
                $error_msg = "variable parameter not provided";
                return false;
            }
            if(!$weatherParameters->parameterExist(WeatherParameters::STATISTIC)){
                $error_msg = "statistic parameter not provided";
                return false;
            }

            // get parameters
            $variable=$weatherParameters->getParameter(WeatherParameters::VARIABLE);
            $statistic=$weatherParameters->getParameter(WeatherParameters::STATISTIC);
        }
        $weatherData->addMetadata($isGlobal,get_class($this), WeatherData::METADATA_UNITCONVERSION);

        $weatherData->addMetadata($variable,get_class($this), WeatherData::METADATA_VARIABLE);
        $weatherData->addMetadata($statistic ?? 'null',get_class($this), WeatherData::METADATA_STATISTIC);

        /////////////////////////
        /// GET DATA INTO FILE
        /////////////////////////
        if(!$this->getDataFromFile($weatherParameters->getParameter(WeatherParameters::LOCATION),$variable,$statistic,$weatherData,$dataFromFile,$error_msg)){
            GlobalLogger::$logger->error("Can't get data from file");
            return false;
        }

        GlobalLogger::$logger->info("[ECMWF] For each date");
        $datePeriodes = $weatherParameters->getParameter(WeatherParameters::DATESPERIODE);
        $error_conversion=0;
        $error_notfound=0;
        $warn_date_ignored=0;
        $nbdates=0;

        // Optimise section
        // Don't get data after max date
        $datetime_max_date = getDatimeWithLatency(EcmwfParameters::getLatency());
        $minyear=(int)GlobalEnv::$env_ecmwf_min_year;

        foreach ($datePeriodes as $date) {
            $nbdates++;
            if($date <= $datetime_max_date && (int)$date->format(FORMAT_DATE_YEAR) >= $minyear ) {
                $formated_date = $date->format(FORMAT_DATE_EN);
                if (array_key_exists($formated_date, $dataFromFile)) {

                    // convert value in global mode
                    if ($isGlobal) {
                        if (EcmwfUnits::ecmwfToGlobal($variable, $statistic, $dataFromFile[$formated_date], $value)) {
                            // save value
                            $weatherData->addData(round($value, 2), $formated_date);
                        } else {
                            GlobalLogger::$logger->error("[ECMWF] Can't convert $formated_date $variable $statistic : " . print_r($dataFromFile[$formated_date], true));
                            $error_conversion++;
                        }
                    } else {
                        // raw value
                        // save value
                        $weatherData->addData(round($dataFromFile[$formated_date], 2), $formated_date);
                    }
                } else {
                    GlobalLogger::$logger->warn("[ECMWF] Date $formated_date not found");
                    $error_notfound++;
                }
            }else{
                // date ignored
                $warn_date_ignored++;
            }
        }
        // stat
        $count_dates_found=count($weatherData->getData());
        GlobalLogger::$logger->info("[ECMWF] [STAT] nb dates : $nbdates");
        GlobalLogger::$logger->info("[ECMWF] [STAT] dates found : $count_dates_found");
        GlobalLogger::$logger->info("[ECMWF] [STAT] [ERROR] dates not found: $error_notfound");
        GlobalLogger::$logger->info("[ECMWF] [STAT] [ERROR] conversion: $error_conversion");
        GlobalLogger::$logger->info("[ECMWF] [STAT] [WARN] ignored: $warn_date_ignored");

        if ($count_dates_found === 0) {
            GlobalLogger::$logger->debug("[ECMWF] no result");
            return false;
        }

        if($count_dates_found === $nbdates) {
            $weatherData->addMetadata(WeatherData::DATA_COMPLETE,get_class($this), WeatherData::METADATA_CONTENTS);
        } else{
            $weatherData->addMetadata(WeatherData::DATA_PARTIAL,get_class($this), WeatherData::METADATA_CONTENTS);
        }
        return true;
    }

    public function executeDaily(&$error_msg): bool
    {
        // TODO change
        return $this->runQuotidien();
    }

    public function executeRangeDaily(string $start_date, string $end_date,&$error_msg): bool
    {
        return $this->runQuotidien($start_date,$end_date);
    }

    public function importInCache(string $start_date, string $end_date, Location $location, string $parameter_name,&$error_msg): bool
    {
        $error_msg=NOT_IMPLEMENTED;
        return false;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PRIVATE SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function runQuotidien(string $start_date = "",string $end_date = ""): bool{

        // send command
        $timer_start = microtime(true);
        $stdout = shell_exec("cd /ecmwf-import ; export CDSAPI_RC=/ecmwf-import/.cdsapirc; ./script_quotidien.sh $start_date $end_date");
        GlobalLogger::$logger->info("Quotidien finish in " . ((microtime(true) - $timer_start) * 1000 ) . " ms");
        if($stdout === false){
            return false;
        }
        GlobalLogger::$logger->info("stdout = ".print_r($stdout,true));
        return true;
    }

    private function  getDataFromFile(Location $location,$variable,$statistic,WeatherData $weatherData, &$dataFromFile, &$error_msg): bool{

        if(!EcmwfParameters::ecwmfVariableToFilename($variable, $statistic, null , $filename)) {
            $error_msg = "Can't create filename with variable";
            return false;
        }

        if(!EcmwfUnits::DDToEcmwfNc($location->getLatitude(),$location->getLongitude(),
            $round_lat,$round_long,$converted_latitude,$converted_longitude,$msg)){
            $error_msg = "Can't convert DD to Ecmwf";
            return false;
        }

        GlobalLogger::$logger->debug("[ECMWF] - converted_DMS_latitude=$converted_latitude");
        GlobalLogger::$logger->debug("[ECMWF] - converted_DMS_longitude=$converted_longitude");

        $weatherData->addMetadata($round_lat,get_class($this), WeatherData::METADATA_LATITUDE);
        $weatherData->addMetadata($round_long,get_class($this), WeatherData::METADATA_LONGITUDE);
        $weatherData->addMetadata(Geo::distance($location->getLatitude(),$location->getLongitude(),$round_lat,$round_long,1) . "km",get_class($this), WeatherData::METADATA_DISTANCE);
        $weatherData->addMetadata($converted_latitude,get_class($this), WeatherData::METADATA_CONVERTEDLATITUDE);
        $weatherData->addMetadata($converted_longitude,get_class($this), WeatherData::METADATA_CONVERTEDLONGITUDE);

        $fileinram =  GlobalEnv::$env_ecmwf_ram_dir."/".$converted_latitude."/".$converted_longitude."/".$filename;
        $fileinpersis= GlobalEnv::$env_ecmwf_persistence_dir."/".$converted_latitude."/".$converted_longitude."/".$filename;
        // open files
        $filespath=array(
            $fileinram . ".csv",
            $fileinpersis . ".csv",
            $fileinram . ".csv.gz",
            $fileinpersis . ".csv.gz"
        );
        $file_is_found=false;
        $file_path="";
        foreach ($filespath as $path) {
            if(isFileExists($path)){
                $file_is_found=true;
                $file_path=$path;
                GlobalLogger::$logger->info("[ECMWF] Found a weather file");
                break;
            }
        }

        if(!$file_is_found){
            $error_msg="[ECMWF] Can't found a weather file";
            GlobalLogger::$logger->error($error_msg);
            return false;
        }

        if(!str_contains($file_path, "gz"))
        {
            $csv_file_path=$file_path;
        }else{

            GlobalLogger::$logger->info("[ECMWF] Decompress gzip file");

            // Raising this value may increase performance
            $buffer_size = 16777216; // read 16Mb at a time

            // Open our files (in binary mode)
            $gzip_file_handler = gzopen($file_path, 'rb');

            // create tempory file
            $temp_file_path = tempnam(sys_get_temp_dir(), 'ecmwf_');
            $temp_file_handler = fopen($temp_file_path, 'wb');

            // Keep repeating until the end of the input file
            while (!gzeof($gzip_file_handler)) {
                // Read buffer-size bytes
                // Both fwrite and gzread and binary-safe
                fwrite($temp_file_handler, gzread($gzip_file_handler, $buffer_size));
            }

            // Files are done, close files
            fclose($temp_file_handler);
            gzclose($gzip_file_handler);

            $csv_file_path=$temp_file_path;
        }

        $row = 1;
        $dataFromFile = array();
        GlobalLogger::$logger->info("[ECMWF] Open csv file");
        if (($handle = fopen($csv_file_path, "rb")) !== FALSE) {
            while (($line = fgetcsv($handle, 1000)) !== FALSE) {
                $num = count($line);
                if($num != 2){
                    GlobalLogger::$logger->error("Not 2 columns in line $row but $line");
                }else{
                    $dataFromFile[$line[0]] = $line[1];
                }
                $row++;
            }
            fclose($handle);
        }
        // remove temp file if needed
        if(isset($temp_file_path)){
            unlink($temp_file_path);
        }
        return true;
    }
}