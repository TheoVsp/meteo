<?php

class Meteoblue extends Provider{

    private const RAW_DATA_TABLE_PATTERN = "meteoblue_raw_data_";

    private string $api_key ;
    private string $host ;
    private bool $authorized_create_location ;

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PUBLIC SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function __construct(){
        // Not best way
        $this->api_key = GlobalEnv::$env_meteoblue_api_key;
        $this->host = GlobalEnv::$env_meteoblue_host;
        $this->authorized_create_location = GlobalVar::$var_authorized_create_location;
    }

    public static function supportParameter($parameter): bool{
        return MeteoblueParameters::supportParameter($parameter);
    }

    public static function canProvidesOneData(array $missingDates): bool
    {
        return true;
    }

    public function getData(WeatherParameters $weatherParameters, WeatherData $weatherData, &$error_msg): bool{

        GlobalLogger::$logger->info("[METEOBLUE] Get data");

        ////////////////////////
        // CONVERSION
        ///////////////////////

        /// PARAMETER
        $parameter = $weatherParameters->getParameter(WeatherParameters::PARAMETER);
        if(MeteoblueParameters::globalToMeteoblue($parameter,$mb_parameter_name, $param_unit_name,$param_unit)) {
            $isGlobal = true;
            // test if global is supported
            if(!Meteoblue::supportParameter($parameter)){
                $error_msg="Can't support parameter " . $parameter;
                GlobalLogger::$logger->error($error_msg);
                return false;
            }
        }else{
            $isGlobal = false;
            GlobalLogger::$logger->info("Can't convert " . $parameter . " to Meteoblue parameter");
            $mb_parameter_name = $weatherParameters->getParameter(WeatherParameters::PARAMETER);
        }
        $weatherData->addMetadata($isGlobal,get_class($this), WeatherData::METADATA_UNITCONVERSION);
        GlobalLogger::$logger->debug("[METEOBLUE] Meteoblue parameter : " . $mb_parameter_name);
        $weatherData->addMetadata($mb_parameter_name,get_class($this), WeatherData::METADATA_PARAMETER);

        /// LOCATION
        $mb_location= new Location();
        if(!MeteoblueUnits::DDToMeteoblueDD($weatherParameters->getParameter(WeatherParameters::LOCATION),$mb_location)){
            $error_msg = "Can't convert Decimal Degrees to Meteoblue Decimal Degrees";
            GlobalLogger::$logger->error($error_msg);
            return false;
        }
        $weatherData->addMetadata($mb_location->getLatitude(),get_class($this), WeatherData::METADATA_LATITUDE);
        $weatherData->addMetadata($mb_location->getLongitude(),get_class($this), WeatherData::METADATA_LONGITUDE);
        $latitude= $weatherParameters->getParameter(WeatherParameters::LOCATION)->getLatitude();
        $longitude= $weatherParameters->getParameter(WeatherParameters::LOCATION)->getLongitude();
        $weatherData->addMetadata(Geo::distance($latitude,$longitude,$mb_location->getLatitude(),$mb_location->getLongitude(),1) . "km",get_class($this), WeatherData::METADATA_DISTANCE);


        // if one table exist already loaded => parameter must be exist
        // otherwise we need to load data
        if($this->oneTableExists()){
            $rawdatatable = new RawDataTable(self::RAW_DATA_TABLE_PATTERN . $mb_parameter_name,false);
            // check if table exist
            if(!$rawdatatable->exists()){
                $error_msg = "Parameter $mb_parameter_name not exist";
                GlobalLogger::$logger->error($error_msg);
                return false;
            }
        }else{
            // first call
            // no table
            $rawdatatable = new RawDataTable(self::RAW_DATA_TABLE_PATTERN . $mb_parameter_name, true);
        }

        ////////////////////////
        // search if new location
        ///////////////////////
        if($this->authorized_create_location && !$rawdatatable->isExistingLocation($mb_location)){

            // check limit
            $this->searchAllLocations($locations);
            $count_location = count($locations);
            if($count_location <= (int) GlobalEnv::$env_meteoblue_max_locations){

                // import DB
                if(!$this->importOneLocationIntoDB(location:$mb_location,load_only_current_year: true)){
                    $error_msg="Error when import location :" . $mb_location;
                    GlobalLogger::$logger->error($error_msg);
                    // TODO HACK try to get data
                }
            }else{
                GlobalMail::$mail->sendEmail("no_reply_dia@ragt.fr", "Digital Innovation and Analytics", GlobalEnv::$env_mail_admins, "[RWS] Max locations reached", "Count : $count_location / " . (int)GlobalEnv::$env_meteoblue_max_locations);
            }
        }

        ////////////////////////
        // get data from DB
        ///////////////////////
        $dataCleanFromDb=array();
        $rawdatatable->getDataFromDBEasy($weatherParameters->getParameter(WeatherParameters::STARTDATE),$weatherParameters->getParameter(WeatherParameters::ENDDATE),
            $mb_location,$mb_parameter_name,$mb_unit_param,$dataCleanFromDb);

        $weatherData->addMetadata($mb_unit_param,get_class($this), WeatherData::METADATA_UNITFROMDATABASE);

        // complete output array with database input
        GlobalLogger::$logger->info("[METEOBLUE] For each date");
        $datePeriodes = $weatherParameters->getParameter(WeatherParameters::DATESPERIODE);
        $error_conversion = 0;
        $warn_date_ignored=0;
        $dates_not_found = array();
        $nbdates=0;

        // Optimise section
        // Don't get data after max date
        $datetime_max_date = getDatimeWithLatency(MeteoblueParameters::getLatency());
        $minyear=(int)GlobalEnv::$env_meteoblue_min_year;

        foreach ($datePeriodes as $date) {
            $nbdates++;
            if($date <= $datetime_max_date  && (int)$date->format(FORMAT_DATE_YEAR) >= $minyear ) {

                $formateddate = $date->format(FORMAT_DATE_EN);
                if (array_key_exists($formateddate, $dataCleanFromDb)) {

                    // convert units if need
                    if ($isGlobal) {
                        if (MeteoblueUnits::meteoblueToGlobal($mb_parameter_name, $dataCleanFromDb[$formateddate], $global_value)) {
                            // save value
                            $weatherData->addData( round($global_value, 2), $formateddate);
                        } else {
                            GlobalLogger::$logger->error("[METEOBLUE] Can't convert $formateddate $mb_parameter_name :" . print_r($dataCleanFromDb[$formateddate],true));
                            $error_conversion++;
                        }
                    } else {
                        // RAW save value
                        $weatherData->addData(round($dataCleanFromDb[$formateddate], 2), $formateddate);
                    }
                } else {
                    GlobalLogger::$logger->warn("[METEOBLUE] Date $formateddate not found");
                    $dates_not_found[] = $formateddate;
                }
            }else{
                $warn_date_ignored++;
            }
        }

        // stat
        $count_dates_not_found = count($dates_not_found);
        $count_dates_found=count($weatherData->getData());
        GlobalLogger::$logger->info("[METEOBLUE] [STAT] nb date : $nbdates");
        GlobalLogger::$logger->info("[METEOBLUE] [STAT] date found : $count_dates_found");
        GlobalLogger::$logger->info("[METEOBLUE] [STAT] [ERROR] dates not found: $count_dates_not_found");
        GlobalLogger::$logger->info("[METEOBLUE] [STAT] [ERROR] conversion: $error_conversion");
        GlobalLogger::$logger->info("[METEOBLUE] [STAT] [WARN] ignored: $warn_date_ignored");

        if ($count_dates_found === 0) {
            GlobalLogger::$logger->debug("[METEOBLUE] no result");
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

    public function executeRangeDaily(string $start_date, string $end_date,&$error_msg): bool
    {
        $error_msg=NOT_IMPLEMENTED;
        return false;
    }

    public function importInCache(string $start_date, string $end_date, Location $location, string $parameter_name,&$error_msg): bool
    {
        if(!$this->importOneLocationIntoDB($location)){
            $error_msg="Error when import location :" . $location;
            GlobalLogger::$logger->error($error_msg);
            return false;
        }
        return true;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PRIVATE SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function importOneLocationIntoDB(Location $location, bool $load_forecast = true, bool $load_histo = true, bool $load_only_current_year=false): bool{

        GlobalLogger::$logger->debug("Import one location");

        // send email
        if($load_only_current_year) {
            GlobalMail::$mail->sendEmail("no_reply_dia@ragt.fr", "Digital Innovation and Analytics", GlobalEnv::$env_mail_admins, "[RWS]  New location imported to Meteoblue", $location);
        }

        $meteoblue_location=new Location();
        if(!MeteoblueUnits::DDToMeteoblueDD($location,$meteoblue_location)){
            $error_msg = "Can't convert Decimal Degrees to Meteoblue Decimal Degrees";
            GlobalLogger::$logger->error($error_msg);
            return false;
        }

        //// //////////
        /// HISTO
        /// Load history before forecast to laod current year
        /// If  one data exists we don't import history for this year
        /// ///////////
        if($load_histo) {
            // get current year

            $now = new DateTime();
            $current_year = (int)$now->format(FORMAT_DATE_YEAR);
            if($load_only_current_year){
                $min = $current_year;
            }else{
                $min = ($current_year - (int)GlobalEnv::$env_meteoblue_max_number_year_history);
            }
            GlobalLogger::$logger->info("MIN : $min");

            for ($year = $min; $year <= $current_year; $year++) {

                // check if data year is present
                // HACK We suppose that each location have temperature_mean
                $rawdatatable = new RawDataTable(self::RAW_DATA_TABLE_PATTERN . "temperature_mean");

                if (!$rawdatatable->exists() || ($rawdatatable->exists() && !$rawdatatable->yearExisting($year,$meteoblue_location->getLatitude(),$meteoblue_location->getLongitude()))) {
                    GlobalLogger::$logger->info("IMPORT");

                    // if not try to load
                    $url = $this->constructUrl($year . "-01-01", $year . "-12-31", GlobalEnv::$env_meteoblue_packages_histo, $meteoblue_location->getLatitude(), $meteoblue_location->getLongitude());
                    $error_msg="";
                    if (!$this->getDataFromApi($url, $data, $error_msg)) {
                        GlobalLogger::$logger->error("Can't get data from api : $error_msg");
                        return false;
                    }
                    if (!$this->importDataInDb(false, $meteoblue_location, $data, $error_msg)) {
                        GlobalLogger::$logger->error("Can't import data into database : $error_msg");
                        return false;
                    }
                }
            }
        }

        //// //////////
        /// FORECAST
        /// ///////////
        if($load_forecast) {
            // 1 request forecast current
            $url = $this->constructUrl(null, null, GlobalEnv::$env_meteoblue_packages_forecast, $meteoblue_location->getLatitude(), $meteoblue_location->getLongitude());
            $error_msg = "";
            if (!$this->getDataFromApi($url, $data, $error_msg)) {
                GlobalLogger::$logger->error("Can't get data from api : $error_msg");
                return false;
            }

            if (!$this->importDataInDb(true, $meteoblue_location, $data, $error_msg)) {
                GlobalLogger::$logger->error("Can't import data into database : $error_msg");
                return false;
            }
        }

        return true;
    }

    private function importDataInDb(bool $isforecast,Location $mb_location,$data,&$error_msg): bool{

        // print metadata
        if(!array_key_exists("metadata",$data)){
            $error_msg = "Data not contains metadata array";
            return false;
        }
        GlobalLogger::$logger->info(print_r($data["metadata"],true));

        // store units
        if(!array_key_exists("units",$data)){
            $error_msg = "Data not contains units array";
            return false;
        }
        $units = $data["units"];

        // store data
        if($isforecast){
            if(!array_key_exists("data_day",$data)){
                $error_msg = "Data not contains data_day array";
                return false;
            }
            $datafromapi = $data["data_day"];
        }else{
            if(!array_key_exists("history_day",$data)){
                $error_msg = "Data not contains history_day array";
                return false;
            }
            $datafromapi = $data["history_day"];
        }

        // store time
        if(!array_key_exists("time",$datafromapi)){
            $error_msg = "Data not contains time array";
            return false;
        }
        $time = $datafromapi["time"];

        $lat_insert = $mb_location->getLatitude();
        $long_insert = $mb_location->getLongitude();

        // for each data
        foreach ($datafromapi as $param_name => $param_array_data){
            if($param_name !== "time"){

                // TODO HACK histo solar => meteoblue issue
                $hack_history_solar = false;
                if(!$isforecast) {
                    switch ($param_name){
                        case "ghi_sum":
                            $param_name = "ghi_total";
                            $hack_history_solar=true;
                            break;
                        case "dif_sum":
                            $param_name = "dif_total";
                            $hack_history_solar=true;
                            break;
                        case "dni_sum":
                            $param_name = "dni_total";
                            $hack_history_solar=true;
                            break;
                        case "gni_sum":
                            $param_name = "gni_total";
                            $hack_history_solar=true;
                            break;
                        case "extraterrestrialradiation_sum":
                            $param_name = "extraterrestrialradiation_total";
                            $hack_history_solar=true;
                            break;
                    }
                }

                // create table if needed
                $raw_data_table = new RawDataTable(self::RAW_DATA_TABLE_PATTERN . $param_name,true);

                // search the best unit
                // except for solar history hack (unit not present in response)
                if($hack_history_solar){
                    $unit = "Whm-2"; // 12/2021
                }else {
                    $unit = $this->searchUnit($param_name, $units);
                }

                // load old data for know if update is necessary or insert
                $dataCleanFromDb="";
                $raw_data_table->getDataFromDBEasy("", "", $mb_location, $param_name, $unitfromdb, $dataCleanFromDb);

                $cpt=0;
                $query="";
                foreach ($param_array_data as $param_data){

                    // dont insert fill value
                    if(!MeteoblueUnits::isFillValue($param_data)) {

                        if ($isforecast) {
                            $date_insert = $time[$cpt];
                        } else {
                            $tempdate = DateTime::createFromFormat(FORMAT_DATE_METEOBLUE_EN, $time[$cpt]);
                            $date_insert = $tempdate->format(FORMAT_DATE_EN);
                        }

                        if (isset($dataCleanFromDb[$date_insert])) {

                            $raw_data_table->appendUpdateQuery($date_insert, $param_data, $param_name, $lat_insert, $long_insert, $query);
                        } else {
                            $raw_data_table->appendInsertQuery($date_insert, $param_data,
                                $unit, $param_name,
                                $lat_insert, $long_insert, $query);
                        }

                        $cpt++;
                    }
                }

                $raw_data_table->executeQuery($query);

                // refresh MV
                $raw_data_table->refreshMV();
            }
        }

        return true;
    }

    private function searchUnit(string $param,array $units):string|null{

        // try same name
        foreach ($units as $unit_name => $unit){
            if($unit_name == $param){
                return $unit;
            }
        }

        // try reggex
        foreach ($units as $unit_name => $unit){
            if(strlen($unit_name) > 3) { // otherwise match everytime
                if (preg_match('/' . $unit_name . '/', $param)) {
                    return $unit;
                }
            }
        }

        // nothing else null
        return null;
    }

    private function oneTableExists(): bool{
         return count($this->getAllTables()) > 0;
    }

    private function getAllTables(){
        // search table schema
        $query="SELECT t.table_name FROM information_schema.tables t WHERE t.table_schema = '".GlobalEnv::$env_db_schema."' AND t.table_type  = 'BASE TABLE' AND t.table_name LIKE '%".self::RAW_DATA_TABLE_PATTERN."%'";
        return GlobalDatabase::$database->list($query);
    }

    private function searchAllLocations(&$locations){

        // search table schema
        $all_tables=$this->getAllTables();

        // for each parameter search location
        $array_loc = array();
        $locations = array();
        foreach ($all_tables as $table) {
            $tablename = $table->table_name;

            // search all locations in this table
            $query = "SELECT DISTINCT t.latitude,t.longitude  FROM ".GlobalEnv::$env_db_schema.".".$tablename." t";
            $all_locations = GlobalDatabase::$database->list($query);

            // for each location store new location
            foreach ($all_locations as $location){

                $lat = $location->latitude;
                $long = $location->longitude;
                $key = $lat . "_" . $long;
                if(!isset($array_loc[$key])){
                    // new location
                    $array_loc[$key] ="exists";
                    $locations[]= new Location(latitude: $lat,longitude: $long);
                }
            }
        }
        GlobalLogger::$logger->debug("Found " . count($all_tables). " tables");
        GlobalLogger::$logger->debug("Found " . count($locations). " locations");
    }

    private function getDataFromApi(string $url, &$result, string &$msg = ""): bool
    {
        $response="";
        $header="";
        $httpCode="";
        GlobalCurl::$curl->httpGet($url, NULL, $httpCode, $response, $header, false);

        if ($httpCode === 200) {
            GlobalLogger::$logger->debug("Get ".__FUNCTION__." ok");
            $respdata = json_decode($response, true);
            $result = $respdata;
            return true;
        }

        $msg = $response;
        GlobalLogger::$logger->error("Get ".__FUNCTION__." nok ($msg)");
        return false;
    }

    private function constructUrl(string|null $startdate,string|null $enddate,string $packages,string $latitude,string $longitude): string{

        // adress
        $url = $this->host  . "/packages/";

        // package
        $url .= $packages;

        // basic arg
        $url .= "?";

        // geo
        // format geo : 7.57
        $url .= "lat=$latitude&lon=$longitude";

        // date
        if($startdate !== null && $enddate !== null) {
            // HACK history package
            // /!\ format date: 2020-01-01
            $url .= "&startdate=$startdate&enddate=" . $enddate;
        }else{
            // time format
            $url .= "&timeformat=YMD";
            // history J-1 to fix issue
            $url .= "&history_days=1";
        }

        // timezone
        $tz = TimeZone::get_nearest_timezone($latitude,$longitude);
        if(isset($tz)){
            $url.= "&tz=".urlencode($tz);
        }

        // units
        $url .= "&temperature=C&windspeed=ms-1&winddirection=degree&precipitationamount=mm&relativehumidity=percent&precipitation=mm";

        // output format
        $url .= "&format=json";

        // API key
        $url .= "&apikey=". $this->api_key;

        return $url;
    }
}