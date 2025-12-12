<?php

class Nasapower extends Provider
{

    private const RAW_DATA_TABLE_PATTERN = "nasapower_raw_data";

    private $rawdatatable;

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PUBLIC SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function __construct()
    {
        $this->rawdatatable = new RawDataTable(self::RAW_DATA_TABLE_PATTERN,true);
    }

    public static function supportParameter($parameter): bool
    {
        return NasapowerParameters::supportParameter($parameter);
    }

    public static function canProvidesOneData(array $missingDates): bool
    {
        GlobalLogger::$logger->debug("[NASA] Check provides one data capabilities on " . count($missingDates)." dates");
        $datetime_max_date = getDatimeWithLatency(NasapowerParameters::getLatency(""));
        GlobalLogger::$logger->debug("[NASA] Max dates " . $datetime_max_date->format(FORMAT_DATE_METEOBLUE_EN));
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
        GlobalLogger::$logger->info("[NASA] Get data");

        ////////////////////////
        // CONVERSION
        ///////////////////////

        /// PARAMETER
        $parameter = $weatherParameters->getParameter(WeatherParameters::PARAMETER);
        if(NasapowerParameters::globalToNasa($parameter,$nasa_parameter_name)) {
            $isGlobal = true;
            // test if global is supported
            if(!Nasapower::supportParameter($parameter)){
                $error_msg="Can't support global parameter " . $parameter;
                GlobalLogger::$logger->error($error_msg);
                return false;
            }
        }else{
            $isGlobal = false;
            GlobalLogger::$logger->info("Can't convert " . $weatherParameters->getParameter(WeatherParameters::PARAMETER) . " to NASA parameter");
            $nasa_parameter_name = $weatherParameters->getParameter(WeatherParameters::PARAMETER);
        }
        $weatherData->addMetadata($isGlobal,get_class($this), WeatherData::METADATA_UNITCONVERSION);
        GlobalLogger::$logger->debug("[NASA] NASA parameter : " . $nasa_parameter_name);
        $weatherData->addMetadata($nasa_parameter_name,get_class($this),  WeatherData::METADATA_PARAMETER);

        /// LOCATION
        $nasa_location=new Location();
        if(!NasapowerUnits::DDToNasaDD($weatherParameters->getParameter(WeatherParameters::LOCATION),$nasa_parameter_name,$nasa_location)){
            $error_msg = "Can't convert Decimal Degrees to Nasa Decimal Degrees";
            GlobalLogger::$logger->error($error_msg);
            return false;
        }
        $weatherData->addMetadata($nasa_location->getLatitude(),get_class($this), WeatherData::METADATA_LATITUDE);
        $weatherData->addMetadata($nasa_location->getLongitude(),get_class($this), WeatherData::METADATA_LONGITUDE);
        $latitude= $weatherParameters->getParameter(WeatherParameters::LOCATION)->getLatitude();
        $longitude= $weatherParameters->getParameter(WeatherParameters::LOCATION)->getLongitude();
        // calcul distance
        $weatherData->addMetadata(Geo::distance($latitude,$longitude,$nasa_location->getLatitude(),$nasa_location->getLongitude(),1) . "km",get_class($this), WeatherData::METADATA_DISTANCE);

        ////////////////////////
        // get data from DB
        ///////////////////////
        $this->rawdatatable->getDataFromDBEasy($weatherParameters->getParameter(WeatherParameters::STARTDATE),$weatherParameters->getParameter(WeatherParameters::ENDDATE),
            $nasa_location,$nasa_parameter_name,$nasa_unit_param,$dataCleanFromDb);

        // TODO HACK save unit database (don't check consistency between data)
        $weatherData->addMetadata($nasa_unit_param,get_class($this),  WeatherData::METADATA_UNITFROMDATABASE);
        $nasa_unit_param_db = $nasa_unit_param;
        if($isGlobal && !NasapowerUnits::get($nasa_parameter_name, $nasa_unit_param)) {
            GlobalLogger::$logger->error("[NASA] Can't get unit for $nasa_parameter_name");
            return false;
        }

        // complete output array with database input
        GlobalLogger::$logger->info("[NASA] For each date");
        $datePeriodes = $weatherParameters->getParameter(WeatherParameters::DATESPERIODE);
        $error_conversion=0;
        $dates_not_found=array();
        $nbdates=0;
        $warn_date_ignored=0;

        // Optimise section
        // Don't get data after max date
        $datetime_max_date = getDatimeWithLatency(NasapowerParameters::getLatency($nasa_parameter_name));

        foreach ($datePeriodes as $date) {
            $nbdates++;
            if( $date <= $datetime_max_date) {
                $formateddate = $date->format(FORMAT_DATE_EN);
                if (array_key_exists($formateddate, $dataCleanFromDb)) {

                    // convert units if need
                    if ($isGlobal) {
                        if (NasapowerUnits::nasaToGlobal($nasa_parameter_name, $dataCleanFromDb[$formateddate], $global_value)) {
                            // save value
                            $weatherData->addData(round($global_value, 2), $formateddate);

                        } else {
                            GlobalLogger::$logger->error("[NASA] Can't convert $formateddate $nasa_parameter_name : $dataCleanFromDb[$formateddate]");
                            $error_conversion++;
                        }
                    } else {
                        // RAW save value
                        $weatherData->addData(round($dataCleanFromDb[$formateddate], 2), $formateddate);
                    }
                } else {
                    $dates_not_found[] = $formateddate;
                }
            }else{
                $warn_date_ignored++;
            }
        }

        $count_dates_not_found = count($dates_not_found);
        if($count_dates_not_found !== 0){
            GlobalLogger::$logger->warn("[NASA] ".$count_dates_not_found." dates not found in database");
        }

        ////////////////////////
        // search missing data with API
        ///////////////////////
        $query="";
        if(!empty($dates_not_found)){

            GlobalLogger::$logger->info("[NASA] Get missing data with API");

            // first date
            $first_date_not_found = $dates_not_found[0] ;
            // Not the best way because we get all dates between this two dates
            $last_date_not_found = $dates_not_found[ $count_dates_not_found - 1 ];

            // get raw data
            $dataFromApi="";
            $error_msg_api="";
            if (!$this->getAllDataFromApi($first_date_not_found, $last_date_not_found, $nasa_location, $nasa_parameter_name, $dataFromApi, $error_msg_api)) {
                GlobalLogger::$logger->error("[NASA] Can't get data from API ($error_msg_api)");
                return false;
            }

            // get unit
            $nasa_unit_param = $this->getOnlyUnitFromRawData($dataFromApi, $nasa_parameter_name);
            $weatherData->addMetadata($nasa_unit_param, get_class($this),  WeatherData::METADATA_UNITFROMAPI);
            if ($nasa_unit_param_db != $nasa_unit_param) {
                GlobalLogger::$logger->error("[NASA] Not consistency unit between database and api ($nasa_unit_param_db != $nasa_unit_param)");
                $weatherData->addMetadata("false", get_class($this), WeatherData::METADATA_UNITCONSISTENCY);
            } else {
                $weatherData->addMetadata("true", get_class($this), WeatherData::METADATA_UNITCONSISTENCY);
            }
            // convert unit from databse if global mode enable
            if ($isGlobal) {
                if (!NasapowerUnits::get($nasa_parameter_name, $nasa_unit_param)) {
                    GlobalLogger::$logger->error("[NASA] Can't get unit for $nasa_parameter_name");
                    return false;
                }
            }

            // get all data from api response
            $all_data_from_api = $this->getOnlyDataFromRawData($dataFromApi, $nasa_parameter_name);

            foreach ($dates_not_found as $date_not_found){

                if(array_key_exists($date_not_found,$all_data_from_api)) {
                    $nasa_value = $all_data_from_api[$date_not_found];

                    if($isGlobal) {
                        if (!NasapowerUnits::nasaToGlobal($nasa_parameter_name, $nasa_value, $value)) {
                            GlobalLogger::$logger->error("[NASA] Can't convert $nasa_parameter_name ");
                            $error_conversion++;
                        } else {
                            $weatherData->addData(round($value, 2), $date_not_found);
                        }
                    }else{
                        $weatherData->addData(round($nasa_value, 2), $date_not_found);
                    }
                    // prepare insert
                    // store all queries into variable
                    $this->rawdatatable->appendInsertQuery($date_not_found, $nasa_value, $nasa_unit_param, $nasa_parameter_name, $nasa_location->getLatitude(), $nasa_location->getLongitude(), $query);

                }else{
                   GlobalLogger::$logger->error("Date not found " . $date_not_found);
               }
            }
        }

        //////////////////////
        // Insert in database
        //////////////////////
        if(!empty($query)){
            GlobalLogger::$logger->info("[NASA] Insert into database");
            $this->rawdatatable->executeQuery($query);
        }

        // stat
        $count_dates_found=count($weatherData->getData());
        $count_dates_not_found = count($dates_not_found);
        GlobalLogger::$logger->info("[NASA] [STAT] nb date : $nbdates");
        GlobalLogger::$logger->info("[NASA] [STAT] date found : $count_dates_found");
        GlobalLogger::$logger->info("[NASA] [STAT] [ERROR] dates not found: $count_dates_not_found");
        GlobalLogger::$logger->info("[NASA] [STAT] [ERROR] conversion: $error_conversion");
        GlobalLogger::$logger->info("[NASA] [STAT] [WARN] ignored: $warn_date_ignored");

        if ($count_dates_found === 0) {
            GlobalLogger::$logger->debug("[NASA] no result");
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
        $now = new DateTime();
        $this->getAllLocationsAndParameters($all_data);

        // TEST COMMIT
        GlobalLogger::$logger->info("[NASA] TOTO");

        // get all data
        $error_msg=array();
        $error=false;
        foreach ($all_data as $nasa_parameter_name => $nasa_location){

            // TODO HACK : start first line for repair missing data automatically
            $startdate_fromdb=$this->rawdatatable->getFirstDataFromDB($nasa_location->getLatitude(),$nasa_location->getLongitude(),$nasa_parameter_name)->date;
            $startdate_datetime=DateTime::createFromFormat(FORMAT_DATE_POSTGRESDB_WITH_S,$startdate_fromdb);
            $startdate =$startdate_datetime->format(FORMAT_DATE_EN);

            // Get end date with latency
            $nbday = NasapowerParameters::getLatency($nasa_parameter_name);
            $datenow_cloned = clone $now;
            $enddate = $datenow_cloned->sub(new DateInterval("P".($nbday+1) ."D"))->format(FORMAT_DATE_EN);

            if(!$this->importData($startdate, $enddate, $nasa_location,  $nasa_parameter_name,false,$errors)){
                GlobalLogger::$logger->error($errors);
                $error_msg[]=$errors;
                $error=true;
            }

            // Update Solar iradiation
            // Use utime and date for detect it
            if (NasapowerParameters::isEnergyFluxesParameter($nasa_parameter_name)) {

                GlobalLogger::$logger->info("[NASA] Parameter is Solar parameter. We need to check updates.");

                // get data older 4 month -1day (for not unclude it)
                $datenow_cloned = clone $now;
                $enddate = $datenow_cloned->sub(new DateInterval("P".GlobalEnv::$env_nasa_solar_latency_ceres_syn."D"))->sub(new DateInterval('P1D'));

                GlobalLogger::$logger->info("[NASA] Get all data before " . $datenow_cloned->format(FORMAT_DATE_EN));

                // get old data
                $this->getSolarIDataFromDB($enddate,$nasa_location->getLatitude(),$nasa_location->getLongitude(),$nasa_parameter_name,$dataFromDb);

                GlobalLogger::$logger->info("[NASA] Number of old data to check : " . count($dataFromDb));

                // for each old data
                foreach ($dataFromDb as $data)
                {
                    // get uptime from data
                    $date_FromDb = DateTime::createFromFormat(FORMAT_DATE_POSTGRESDB_WITH_S, $data->date);
                    $utime_FromDb = DateTime::createFromFormat(FORMAT_DATE_POSTGRESDB_WITH_US, $data->utime);

                    if($utime_FromDb === false){
                        $utime_FromDb = DateTime::createFromFormat(FORMAT_DATE_POSTGRESDB_WITH_MS, $data->utime);
                    }

                    // if update time is older than date minus ceres latency
                    if(! (
                        abs($utime_FromDb->diff($date_FromDb)->days) > (int)GlobalEnv::$env_nasa_solar_latency_ceres_syn ||
                        abs($utime_FromDb->diff($now)->days) > (int)GlobalEnv::$env_nasa_solar_latency_ceres_syn) ){

                        // force update
                        if(!$this->importData($date_FromDb->format(FORMAT_DATE_EN), $date_FromDb->format(FORMAT_DATE_EN), $nasa_location,  $nasa_parameter_name,true,$errors)){
                            GlobalLogger::$logger->error($errors);
                            $error_msg[]=$errors;
                            $error=true;
                        }
                    }
                }
            }
        }

        return !$error;
    }

    public function executeRangeDaily(string $start_date, string $end_date,&$error_msg): bool
    {
        // get all locations and parameters
        $this->getAllLocationsAndParameters($all_data);

        $error_msg=array();
        $error=false;
        foreach ($all_data as $nasa_parameter_name => $nasa_location){
            if(!$this->importData($start_date, $end_date, $nasa_location,  $nasa_parameter_name,false,$errors)){
                GlobalLogger::$logger->error($errors);
                $error_msg[]=$errors;
                $error=true;
            }
        }

        return !$error;
    }

    public function importInCache(string $start_date, string $end_date, Location $location, string $parameter_name,&$error_msg): bool
    {
        return $this->importData( $start_date,  $end_date,  $location,  $parameter_name, false, $error_msg);
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PRIVATE SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @param string $start_date
     * @param string $end_date
     * @param Location $location support global or nasa parameter
     * @param string $parameter_name support global or nasa parameter
     * @param bool $toUpdateIfExist
     * @param $error_msg
     * @return bool
     */
    private function importData(string $start_date, string $end_date, Location $location, string $parameter_name,
                                bool $toUpdateIfExist,
                                &$error_msg): bool
    {
        $query ="";
        $error = false;
        $error_msg=array();
        $nasa_location=new Location();

        ////////////////////////
        // CONVERSION
        ///////////////////////
        /// PARAMETER
        if(GlobalParameters::isGlobalParameter($parameter_name)){
            if(!NasapowerParameters::globalToNasa($parameter_name,$nasa_parameter_name)){
                GlobalLogger::$logger->error("Can't convert ($parameter_name) to nasa parameter");
                return false;
            }
            $isGlobal = true;
        }else{
            $isGlobal = false;
            $nasa_parameter_name = $parameter_name;
        }

        /// LOCATION
        if(!NasapowerUnits::DDToNasaDD($location,$nasa_parameter_name,$nasa_location)){
            $error_msg = "Can't convert Decimal Degrees to Nasa Decimal Degrees";
            GlobalLogger::$logger->error($error_msg);
            return false;
        }

        ////////////////////////
        // GET DATA FROM DB
        ///////////////////////
        if (!$this->getAllDataFromApi($start_date, $end_date, $nasa_location, $nasa_parameter_name , $dataFromApi, $error_msg_api)) {
            $error_msg ="[NASA] Can't get data from API ($error_msg_api)";
            GlobalLogger::$logger->error($error_msg);
            return false;
        }

        // save value
        $all_data_from_api = $this->getOnlyDataFromRawData($dataFromApi,$nasa_parameter_name);
        GlobalLogger::$logger->info("[NASA] Number data from API : " . count($all_data_from_api));

        // save unit from api
        $nasa_unit_param_from_api = $this->getOnlyUnitFromRawData($dataFromApi, $nasa_parameter_name);

        // get data from DB
        $dataCleanFromDb="";
        $this->rawdatatable->getDataFromDBEasy($start_date,$end_date, $nasa_location,$nasa_parameter_name,$nasa_parameter_unit,$dataCleanFromDb);
        GlobalLogger::$logger->info("[NASA] Number data from database : " . count($dataCleanFromDb));

        foreach ($all_data_from_api as $api_date => $api_data){

            $data_exist = array_key_exists($api_date,$dataCleanFromDb);

            if (!NasapowerUnits::isFillValue($api_data)) {
                $roundedValue = round($api_data, 2);
                // prepare insert
                // store all queries into variable
                if ($isGlobal && !NasapowerUnits::get($nasa_parameter_name, $nasa_unit_param)) {
                    $msg = "[NASA] $nasa_location Can't get unit for $nasa_parameter_name";
                    GlobalLogger::$logger->error($msg);
                    $error_msg[] = $msg;
                    $error = true;
                } else {
                    if(!$isGlobal){
                        $nasa_unit_param = $nasa_unit_param_from_api;
                    }
                    // if get unit is ok
                    // use appended string to optimise insert
                    if($data_exist && $toUpdateIfExist){
                        // update value and ctime
                        $this->rawdatatable->appendUpdateQuery($api_date,$roundedValue,$nasa_parameter_name,
                            $nasa_location->getLatitude(), $nasa_location->getLongitude(), $query);
                    }elseif (!$data_exist){
                       // insert
                        $this->rawdatatable->appendInsertQuery($api_date, $roundedValue,
                            $nasa_unit_param, $nasa_parameter_name,
                            $nasa_location->getLatitude(), $nasa_location->getLongitude(), $query);
                    }
                }
            } else {
                // error only if need to update or inset
                if(($data_exist && $toUpdateIfExist) || !$data_exist){
                    $msg = "$nasa_location $nasa_parameter_name $api_date => Fill Value";
                    GlobalLogger::$logger->error($msg);
                    $error_msg[] = $msg;
                    // $error = true;
                }
            }
        }

        // Insert in database
        if(!empty($query)){
            GlobalLogger::$logger->info("[NASA] Execute query");
            $this->rawdatatable->executeQuery($query);

            // refresh MV
            $this->rawdatatable->refreshMV();
        }

        return !$error;
    }

    private function getDataFromApi(string $startdate, string $enddate,
                                    string $latitude, string $longitude,string|null $altitude,
                                    string $parameters,
                                           &$result, string &$msg = ""): bool
    {
        // documentation
        // Collectively the POWER Data Archive consists of
        //   - a 2-3 day latency for meteorological parameters
        //   - a 5-7 days for latency for solar parameters.
        // https://power.larc.nasa.gov/docs/services/api/v1/temporal/daily/
        $url = "https://power.larc.nasa.gov/api/temporal/daily/point";

        // basic arg
        $url .= "?user=DOCUMENTATION";

        // AG : Agrimatologie : https://power.larc.nasa.gov/docs/methodology/communities/ag/
        // SB : for building : https://power.larc.nasa.gov/docs/methodology/communities/sb/
        // SSE : https://power.larc.nasa.gov/docs/methodology/communities/sse/
        $url .= "&community=ag";

        // output
        $url .= "&outputList=JSON";

        // geo
        $url .= "&longitude=$longitude&latitude=$latitude";
        if($altitude !== null){
            $url .= "&site-elevation=$altitude";
        }

        // parameters : https://power.larc.nasa.gov/#resources
        $url .= "&parameters=$parameters";

        // format YYYYMMDD
        $url .= "&start=$startdate&end=$enddate";

        $response="";
        $header="";
        $cpt_retry=0;
        do {
            $httpCode="";
            GlobalCurl::$curl->httpGet($url, NULL, $httpCode, $response, $header, false);

            if ($httpCode === 200) {
                GlobalLogger::$logger->debug("Get ".__FUNCTION__." ok");
                $respdata = json_decode($response, true);
                $result = $respdata;
                return true;
            } elseif ($httpCode === 429) { // RETRY part
                GlobalLogger::$logger->warn("Limit rate attempted. Wait " .GlobalEnv::$env_nasa_retry_time. " seconds.");
                $cpt_retry++;
                sleep(GlobalEnv::$env_nasa_retry_time);
            }else{
                $msg = $response;
                GlobalLogger::$logger->error("Get ".__FUNCTION__." nok ($msg)");
                break;
            }
        }while($cpt_retry < GlobalEnv::$env_nasa_max_retry);
        return false;
    }

    private function getOnlyDataFromRawData($data, $nasa_parameter_name){
        return $data["properties"]["parameter"][$nasa_parameter_name];
    }

    private function getOnlyUnitFromRawData($data, $nasa_parameter_name){
        return $data["parameters"][$nasa_parameter_name]["units"];
    }

    private function getAllDataFromApi(string $start_date, string $end_date, Location $location, string $parameter_name, &$data, &$error_msg): bool
    {
        $error_msg ="";
        $nasa_location=new Location();
        if(!NasapowerUnits::DDToNasaDD($location,$parameter_name,$nasa_location)){
            $error_msg = "Can't convert Decimal Degrees to Nasa Decimal Degrees";
            GlobalLogger::$logger->error($error_msg);
            return false;
        }

        return $this->getDataFromApi(
            $start_date,
            $end_date,
            $nasa_location->getLatitude(),
            $nasa_location->getLongitude(),
            $nasa_location->getAltitude(),
            $parameter_name,
            $data,
            $error_msg);
    }

    private function getSolarIDataFromDB(DateTime $enddate,
                                         string   $latitude, string $longitude,
                                         string   $nasa_parameter,
                                                  &$data): void
    {

        $query = "SELECT * FROM ".GlobalEnv::$env_db_schema.".".self::RAW_DATA_TABLE_PATTERN." d WHERE ";
        $query .= " d.date <= '".$enddate->setTime(23, 59, 59)->format(FORMAT_DATE_POSTGRESDB_WITH_S)."'";
        $query .= " AND d.longitude = '" . $longitude . "'";
        $query .= " AND d.latitude = '" . $latitude . "'";
        $query .= " AND d.name = '" . $nasa_parameter . "'";

        $data = GlobalDatabase::$database->list($query);
    }

    private function getAllLocationsAndParameters(&$output): void
    {
        $query = "SELECT DISTINCT nrd.latitude,
                   nrd.longitude,
                   nrd.name
                 FROM ".GlobalEnv::$env_db_schema.".".self::RAW_DATA_TABLE_PATTERN." nrd";
        $data = GlobalDatabase::$database->list($query);

        $output = array();
        foreach ($data as $line){
            $output[$line->name]= new Location(latitude: $line->latitude,longitude: $line->longitude);
        }
    }
}
