<?php

class Rweather extends Provider
{
    private const RAW_DATA_TABLE_PATTERN = "rweather_raw_data";

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PUBLIC SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function __construct()
    {
    }

    public static function supportParameter($parameter): bool
    {
        switch ($parameter) {
            case GlobalParameters::ETP:
                return true;
            default:
                return false;
        }
    }

    public static function canProvidesOneData(array $missingDates): bool
    {
        return true;
    }

    public function getData(WeatherParameters $weatherParameters, WeatherData $weatherData, &$error_msg): bool
    {
        /// PARAMETER
        $parameter = $weatherParameters->getParameter(WeatherParameters::PARAMETER);
        if(!Rweather::supportParameter($parameter)){
            $error_msg="Can't support parameter " . $parameter;
            GlobalLogger::$logger->error($error_msg);
            return false;
        }

        // no modification of input
        $parameter = $weatherParameters->getParameter(WeatherParameters::PARAMETER);
        $location = $weatherParameters->getParameter(WeatherParameters::LOCATION);

        // cached data table
        $rawdatatable = new RawDataTable(self::RAW_DATA_TABLE_PATTERN."_".$parameter, true);

        ////////////////////////
        // GET DATA FROM DB
        ///////////////////////
        $dataCleanFromDb=array();
        $rawdatatable->getDataFromDBEasy($weatherParameters->getParameter(WeatherParameters::STARTDATE),$weatherParameters->getParameter(WeatherParameters::ENDDATE),
            $location,$parameter,$unit_param,$dataCleanFromDb);

        $weatherData->addMetadata($unit_param,get_class($this), WeatherData::METADATA_UNITFROMDATABASE);

        ////////////////////////
        // complete output array with database input
        ////////////////////////
        GlobalLogger::$logger->info("[RWEATHER] For each date");
        $datePeriodes = $weatherParameters->getParameter(WeatherParameters::DATESPERIODE);
        $error_conversion = 0;
        $warn_date_ignored=0;
        $dates_not_found = array();
        $nbdates=0;

        // Don't get data after max date
        // SOLAR
        $datetime_max_date = getDatimeWithLatency(NasapowerParameters::getLatency("ALLSKY_SFC_SW_DWN"));

        foreach ($datePeriodes as $date) {
            $nbdates++;
            if( $date <= $datetime_max_date) {
                $formateddate = $date->format(FORMAT_DATE_EN);
                if (array_key_exists($formateddate, $dataCleanFromDb)) {
                    $value_from_db = $dataCleanFromDb[$formateddate];
                    if($value_from_db < 0){
                        $value_from_db = 0;
                    }
                    $weatherData->addData(round($value_from_db, 2), $formateddate);
                } else {
                    $dates_not_found[] = $formateddate;
                }
            }
        }

        $count_dates_not_found = count($dates_not_found);
        if($count_dates_not_found !== 0){
            GlobalLogger::$logger->warn("[RWEATHER] ".$count_dates_not_found." dates not found in database");
        }

        ////////////////////////
        // search missing data with R package
        ///////////////////////
        $query="";
        if(!empty($dates_not_found)) {

            GlobalLogger::$logger->info("[RWEATHER] start ETP computation");

            //  get all data
            if (!$this->getAllDataFromNasa($weatherParameters, $weatherData, $output, $error_msg)) {
                return false;
            }

            //////
            // PREPARE R options
            //////
            $hash = substr(hash('sha256', microtime(true)), 0, 30);
            $time = date("Y-m-d-h-i-s");
            $input_data_file = GlobalEnv::$env_rweather_mode_locale_input . "/etp_" . $time . "_" . $hash . ".csv";
            $output_data_file = GlobalEnv::$env_rweather_mode_locale_output . "/etp_" . $time . "_" . $hash . ".csv";
            if ($weatherParameters->parameterExist(WeatherParameters::DEBUG)) {
                $debug = $weatherParameters->getParameter(WeatherParameters::DEBUG);
            } else {
                $debug = "false";
            }

            //////
            // put header in input for R
            $csvfile = fopen($input_data_file, 'wb') or die("Can't open $input_data_file");
            $headercsv = "LOCATION,LON,LAT,ALT,DATE,YEAR,MONTH,DAY,DOY,T2M,T2M_MAX,T2M_MIN,PRECTOTCORR,WS2M,RH2M,T2MDEW,ALLSKY_SFC_SW_DWN";
            fwrite($csvfile, $headercsv . PHP_EOL);
            foreach ($output as $date_formated => $data) {
                if(!isset($data["ALLSKY_SFC_SW_DWN"])){
                    continue;
                }
                $body = array();
                $body[] = "";
                $body[] = $weatherParameters->getParameter(WeatherParameters::LONGITUDE);
                $body[] = $weatherParameters->getParameter(WeatherParameters::LATITUDE);
                $body[] = $weatherParameters->getParameter(WeatherParameters::ALTITUDE);
                $datetime = DateTime::createFromFormat(FORMAT_DATE_EN, $date_formated);
                $body[] = $datetime->format(FORMAT_DATE_METEOBLUE_EN);
                $body[] = $datetime->format(FORMAT_DATE_YEAR);
                $body[] = $datetime->format(FORMAT_DATE_MONTH);
                $body[] = $datetime->format(FORMAT_DATE_DAY);
                $body[] = (int)$datetime->format(FORMAT_DATE_DAY_OF_YEAR) + 1;
                $body[] = $data["T2M"];
                $body[] = $data["T2M_MAX"];
                $body[] = $data["T2M_MIN"];
                $body[] = $data["PRECTOTCORR"];
                $body[] = $data["WS2M"];
                $body[] = $data["RH2M"];
                $body[] = $data["T2MDEW"];
                $body[] = $data["ALLSKY_SFC_SW_DWN"];
                fputcsv($csvfile, $body);
            }
            fclose($csvfile);

            //////
            // compute
            //////
            $timer_start__ = microtime(true);
            $cmd = "Rscript --vanilla common/api/providers/rweather/scripts/etp.R $input_data_file $output_data_file $debug";
            GlobalLogger::$logger->info("Rscript options :$cmd");
            $stdout = shell_exec($cmd);
            GlobalLogger::$logger->info("RWeather response in " . ((microtime(true) - $timer_start__) * 1000) . " ms");
            GlobalLogger::$logger->info("stdout = ".print_r($stdout,true));

            //////
            /// SEARCH MISSING DATA
            //////
            $all_data_from_rweather = array();
            // open output file
            if (file_exists($output_data_file)) {
                if (($handle = fopen($output_data_file, 'rb')) !== FALSE) {

                    // search column
                    $header = true;
                    $num_etp_montey = -1;
                    $num_etp_date = -1;
                    while (($data = fgetcsv($handle, 1000, CSV_DELIMETER)) !== FALSE) {

                        // HEADER SECTION
                        if ($header) {
                            $found_etp = false;
                            $found_date = false;
                            foreach ($data as $c => $cValue) {
                                if ($cValue === "ETP_PM") {
                                    $num_etp_montey = $c;
                                    $found_etp = true;
                                }
                                if ($cValue === "DATE") {
                                    $num_etp_date = $c;
                                    $found_date = true;
                                }
                                if($found_etp && $found_date){
                                    break;
                                }
                            }
                            if (!$found_etp || !$found_date) {
                                $error_msg = "[RWEATHER] Not found ETP Penman-Monteith equation in header";
                                GlobalLogger::$logger->error($error_msg);
                                return false;
                            }
                            $header = false;
                        } else {
                            // store data
                            $date_tmp=DateTime::createFromFormat(FORMAT_DATE_METEOBLUE_EN,$data[$num_etp_date]);
                            $formateddated = $date_tmp->format(FORMAT_DATE_EN);
                            $all_data_from_rweather[$formateddated]=$data[$num_etp_montey];
                        }
                    }
                    fclose($handle);

                    // add missing data into
                    foreach ($dates_not_found as $date_not_found){

                        if(array_key_exists($date_not_found,$all_data_from_rweather)) {

                            $value_from_r = $all_data_from_rweather[$date_not_found];
                            // negative value set to 0
                            if($value_from_r < 0){
                                $value_from_r = 0;
                            }
                            $weatherData->addData(round($value_from_r, 2), $date_not_found);

                            // prepare insert
                            // store all queries into variable
                            $rawdatatable->appendInsertQuery($date_not_found, $value_from_r, "mm", "ETP_PM", $location->getLatitude(), $location->getLongitude(), $query);

                        }else{
                            GlobalLogger::$logger->warn("[RWEATHER] Date not found " . $date_not_found);
                        }
                    }
                }
            }else{
                // error
                GlobalLogger::$logger->error("[RWEATHER]  Can't open output file");
                return false;
            }
        }

        //////////////////////
        // Insert in database
        //////////////////////
        if(!empty($query)){
            GlobalLogger::$logger->info("[RWEATHER] Insert into database");
            $rawdatatable->executeQuery($query);
        }

        // stat
        $count_dates_found=count($weatherData->getData());
        $count_dates_not_found = count($dates_not_found);
        GlobalLogger::$logger->info("[RWEATHER] [STAT] nb date : $nbdates");
        GlobalLogger::$logger->info("[RWEATHER] [STAT] date found : $count_dates_found");
        GlobalLogger::$logger->info("[RWEATHER] [STAT] [ERROR] dates not found: $count_dates_not_found");
        GlobalLogger::$logger->info("[RWEATHER] [STAT] [ERROR] conversion: $error_conversion");
        GlobalLogger::$logger->info("[RWEATHER] [STAT] [WARN] ignored: $warn_date_ignored");

        if ($count_dates_found === 0) {
            GlobalLogger::$logger->debug("[RWEATHER] no result");
            return false;
        }

        if($count_dates_found === $nbdates) {
            $weatherData->addMetadata(WeatherData::DATA_COMPLETE,get_class($this), WeatherData::METADATA_CONTENTS);
        } else{
            $weatherData->addMetadata(WeatherData::DATA_PARTIAL,get_class($this), WeatherData::METADATA_CONTENTS);
        }

        return true;
    }

    public function executeDaily(&$error_msg): bool{


        return false;
    }

    public function executeRangeDaily(string $start_date, string $end_date, &$error_msg): bool{

        $error_msg = NOT_IMPLEMENTED;
        return false;
    }

    public function importInCache(string $start_date, string $end_date, Location $location, string $parameter_name, &$error_msg): bool{

        $error_msg = NOT_IMPLEMENTED;
        return false;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PRIVATE SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function searchAllLocations(&$locations){

        $error = false;

        // get all location
        $this->searchAllLocations($locations);

        foreach ($locations as $location){
            // load histo + forecast
            if(!$this->importOneLocationIntoDB(location: $location, load_forecast: true,load_histo: true, load_only_current_year: false)){
                $error_msg="Error when import location :" . $location;
                GlobalLogger::$logger->error($error_msg);
                $error = true;
            }
        }

        // check consistency unit

        // check consistency date

        return !$error;
    }

    private function getAllParameterArray(){
        $list_parameters = array();
        $list_parameters[] = GlobalParameters::TEMPAVG;
        $list_parameters[] = GlobalParameters::TEMPMIN;
        $list_parameters[] = GlobalParameters::TEMPMAX;
        $list_parameters[] = GlobalParameters::RR;
        $list_parameters[] = "WS2M";
        $list_parameters[] = GlobalParameters::HUMR;
        $list_parameters[] = GlobalParameters::DEWPOINT;
        $list_parameters[] = "ALLSKY_SFC_SW_DWN";
        return $list_parameters;
    }

    private function getAllDataFromNasa(WeatherParameters $weatherParameters, WeatherData $weatherData, &$output, &$error_msg):bool
    {
        $serviceProvider = new ServiceProvider();

        // get all parameters
        $list_parameters = $this->getAllParameterArray();

        $weatherParameters_clone = clone $weatherParameters;
        // HACK force to nasa provider
        $weatherParameters_clone->setParameter(WeatherParameters::PROVIDER,"nasapower");
        $output=array();
        foreach ($list_parameters as $parameter_name) {

            $weatherParameters_clone->setParameter(WeatherParameters::PARAMETER, $parameter_name);
            $weatherData_tmp = new WeatherData();

            if (!$serviceProvider->getData($weatherParameters_clone, $weatherData_tmp, $error_msg)) {
                return false;
            }

            foreach ($weatherData_tmp->getData() as $date_formated => $data){
                if(!array_key_exists($date_formated,$output)){
                    $output[$date_formated]=array();
                }
                if(GlobalParameters::isGlobalParameter($parameter_name))
                {
                     if(!NasapowerParameters::globalToNasa($parameter_name,$nasa_parameter_name)){
                         return false;
                     }
                }else{
                    $nasa_parameter_name = $parameter_name;
                }
                $output[$date_formated][$nasa_parameter_name] = $data;
            }
        }
        return true;
    }
}