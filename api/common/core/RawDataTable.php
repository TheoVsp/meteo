<?php

class RawDataTable
{
    private string $table;

    ////////////////////////////////////////////////////////////
    /// PUBLIC FUNCTION SECTION
    ////////////////////////////////////////////////////////////
    public function __construct(string $table,bool $create = false)
    {
        $this->table = strtolower($table);
        if($create){
            $this->createRawDataTable($this->table);
        }
    }

    public function exists(): bool{
        return $this->tableExists($this->table);
    }

    public function createRawDataTable(string $name){

        if(!$this->tableExists($name)){

            $query ="CREATE TABLE IF NOT EXISTS ".GlobalEnv::$env_db_schema.".".$name."(
                    id SERIAL ,
                    date timestamp without time zone NOT NULL,
                    type  varchar(50) NOT NULL,
                    value varchar(100) NOT NULL,
                    unit varchar(20) NOT NULL,
                    name varchar(50) NOT NULL,
                    latitude float NOT NULL,
                    longitude float NOT NULL,
                    ctime timestamp without time zone NOT NULL DEFAULT current_timestamp,
                    utime timestamp without time zone NOT NULL DEFAULT current_timestamp,
                    PRIMARY KEY (date, name,unit,type,latitude,longitude)
                );";
            $query .="CREATE MATERIALIZED VIEW ".GlobalEnv::$env_db_schema.".mv_locations_".$name."
                    AS SELECT DISTINCT rawtab.longitude AS longitude,
                                       rawtab.latitude AS latitude
                    FROM ".GlobalEnv::$env_db_schema.".".$name." rawtab;";

            GlobalDatabase::$database->execute($query,false);
        }
    }

    public function getFirstDataFromDB(string $latitude, string $longitude,string $parameter)
    {
        $query = "SELECT * FROM ".GlobalEnv::$env_db_schema.".".$this->table." d WHERE";
        $query .= "  d.longitude = '" . $longitude . "'";
        $query .= " AND d.latitude = '" . $latitude . "'";
        $query .= " AND d.name = '" . $parameter . "'";
        $query .= " ORDER BY d.date asc LIMIT 1";

        return GlobalDatabase::$database->get($query);
    }

    public function isExistingLocation(Location $targetLocation)
    {
        $query = "SELECT DISTINCT d.latitude ,d.longitude  FROM " . GlobalEnv::$env_db_schema . ".mv_locations_" . $this->table . " d";
        $locations = GlobalDatabase::$database->list($query);
        foreach ($locations as $location) {
            $lat = $location->latitude;
            $long = $location->longitude;
            if ($lat == $targetLocation->getLatitude() && $long == $targetLocation->getLongitude())
                return true;
        }
        return false;
    }

    public function refreshMV(){
        $request ="REFRESH MATERIALIZED VIEW " . GlobalEnv::$env_db_schema . ".mv_locations_" . $this->table;
        GlobalDatabase::$database->execute($request);
    }

    public function yearExisting($year,string  $latitude, string $longitude):bool
    {
        $startdate = new DateTime();
        $startdate->setDate($year,1,1)->setTime(0, 0, 0);

        $enddate = new DateTime();
        $enddate->setDate($year,12,31)->setTime(23, 59, 59);

        $query = "SELECT count(*)  FROM ".GlobalEnv::$env_db_schema.".".$this->table." d WHERE ";
        $query .= " d.date >= '".$startdate->format(FORMAT_DATE_POSTGRESDB_WITH_S)."'";
        $query .= " AND d.date <= '".$enddate->format(FORMAT_DATE_POSTGRESDB_WITH_S)."'";
        $query .= " AND d.longitude = '" . $longitude . "'";
        $query .= " AND d.latitude = '" . $latitude . "'";
        return GlobalDatabase::$database->get($query)->count != 0;
    }

    public function getDataFromDB(DateTime $startdate, DateTime $enddate,
                                   string  $latitude, string $longitude,
                                   string  $parameter,
                                           &$data): void
    {

        $query = "SELECT * FROM ".GlobalEnv::$env_db_schema.".".$this->table." d WHERE ";
        $query .= " d.date >= '".$startdate->setTime(0, 0, 0)->format(FORMAT_DATE_POSTGRESDB_WITH_S)."'";
        $query .= " AND d.date <= '".$enddate->setTime(23, 59, 59)->format(FORMAT_DATE_POSTGRESDB_WITH_S)."'";
        $query .= " AND d.longitude = '" . $longitude . "'";
        $query .= " AND d.latitude = '" . $latitude . "'";
        $query .= " AND d.name = '" . $parameter . "'";

        $data = GlobalDatabase::$database->list($query);
    }

    public function getLocationDataFromDB( string  $latitude, string $longitude,
                                  string  $parameter,
                                           &$data): void
    {

        $query = "SELECT * FROM ".GlobalEnv::$env_db_schema.".".$this->table." d WHERE ";
        $query .= " d.longitude = '" . $longitude . "'";
        $query .= " AND d.latitude = '" . $latitude . "'";
        $query .= " AND d.name = '" . $parameter . "'";

        $data = GlobalDatabase::$database->list($query);
    }

    public function generateInsert(string $date, $value, $unit_param,string $name_param, $latitude, $longitude, $type = ADJUSTED): string
    {
        $datedateime = DateTime::createFromFormat(FORMAT_DATE_EN, $date);

        // import data

        $insert_date = $datedateime->setTime(12, 0, 0);
        $insert_type = $type;
        $insert_value = $value;
        $insert_unit = $unit_param;
        $insert_name = $name_param;
        $insert_latitude = $latitude;
        $insert_longitude = $longitude;

        $query = "INSERT INTO ".GlobalEnv::$env_db_schema.".".$this->table." (date,type,value,unit,name,latitude,longitude) ";
        $query .= "VALUES ('".$insert_date->format(FORMAT_DATE_POSTGRESDB_WITH_S)."','" . $insert_type . "','" . $insert_value . "','" . $insert_unit . "','" . $insert_name . "','" . $insert_latitude . "','" . $insert_longitude . "');";
        return $query;
    }
    public  function generateUpdate(string $en_date, $value,string $parameter, $latitude, $longitude){

        $datedateime = DateTime::createFromFormat(FORMAT_DATE_EN, $en_date);
        $insert_date = $datedateime->setTime(12, 0, 0);

        $query = "UPDATE ".GlobalEnv::$env_db_schema.".".$this->table;
        $query .= " SET value='".$value."', utime=current_timestamp";
        $query .= " WHERE ";
        $query .= " date = '" . $insert_date->format(FORMAT_DATE_POSTGRESDB_WITH_S) . "'";
        $query .= " AND longitude = '" . $longitude . "'";
        $query .= " AND latitude = '" . $latitude . "'";
        $query .= " AND name = '" . $parameter . "';";
        return $query;
    }

    public function appendInsertQuery($date, $value, $unitparam, $nameparam, $latitude, $longitude, &$query, string $type = ADJUSTED): void
    {
        $query .= $this->generateInsert($date, $value, $unitparam, $nameparam, $latitude, $longitude, $type);
        $query .= "COMMIT;" . PHP_EOL;
    }
    public function appendUpdateQuery($en_date, $value, $parameter, $latitude, $longitude, &$query): void
    {
        $query .= $this->generateUpdate($en_date, $value, $parameter, $latitude, $longitude);
        $query .= "COMMIT;" . PHP_EOL;
    }

    public function executeQuery(string $query): bool
    {
        try {
            // execute request
            GlobalDatabase::$database->execute($query,false);
            GlobalLogger::$logger->debug("Execute [OK]");
        } catch (PDOException $e) {

            // Exception
            GlobalLogger::$logger->error('Caught exception: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    public function getDataFromDBEasy(string $start_date, string $end_date, Location $location, string $parameter_name, &$unit, &$dataCleanFromDb): bool
    {
        $dataCleanFromDb = array();
        if(!empty($start_date) && !empty($end_date)){
            // create Datetime Object
            $datetime_start = DateTime::createFromFormat(FORMAT_DATE_EN, $start_date);
            $datetime_end = DateTime::createFromFormat(FORMAT_DATE_EN, $end_date);

            // get all data from db
            $this->getDataFromDB($datetime_start,$datetime_end,
                $location->getLatitude(),$location->getLongitude(),
                $parameter_name,$dataFromDb);
        }else{
            $this->getLocationDataFromDB($location->getLatitude(),$location->getLongitude(),
                $parameter_name,$dataFromDb);
        }

        $unit="";
        // Add each data in output array
        $error = false;
        foreach ($dataFromDb as $dataDbLine){

            if(empty($unit)){
                $unit = $dataDbLine->unit;
            }else{
                if($dataDbLine->unit != $unit){
                    GlobalLogger::$logger->error("Unit consistency failed : [DB] ($unit) != [INSERT] (" . $dataDbLine->unit . ")");
                    $error=true;
                }
            }
            // change date format to Ymd
            $datetime = DateTime::createFromFormat(FORMAT_DATE_POSTGRESDB_WITH_S, $dataDbLine->date);
            $date_formated = $datetime->format(FORMAT_DATE_EN);
            // add value
            $dataCleanFromDb[$date_formated] = $dataDbLine->value;
        }
        return !$error;
    }

    ////////////////////////////////////////////////////////////
    /// PRIVATE FUNCTION SECTION
    ////////////////////////////////////////////////////////////

    private function tableExists($tablename){
        $query="SELECT to_regclass('".GlobalEnv::$env_db_schema.".".$tablename."')";
        $return = GlobalDatabase::$database->get($query);

        if($return->to_regclass == $tablename){
            return true;
        }
        return false;
    }
}
