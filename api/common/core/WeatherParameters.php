<?php

class WeatherParameters
{
    public const STARTDATE="startdate";
    public const ENDDATE="enddate";
    public const STARTDAY="startday";
    public const ENDDAY="endday";
    public const STARTMONTH="startmonth";
    public const ENDMONTH="endmonth";
    public const STARTYEAR="startyear";
    public const ENDYEAR="endyear";
    public const LOCATION="location";
    public const PARAMETER="parameter";
    public const LATITUDE="latitude";
    public const LONGITUDE="longitude";
    public const ALTITUDE="altitude";
    public const DATERANGE="daterange";

    public const DATESPERIODE="datesperiode";
    public const PROVIDER="provider";

    public const VARIABLE="variable";
    public const STATISTIC="statistic";

    public const DEBUG="debug";

    private array $parameters;

    public function __construct()
    {
        $this->parameters = array();
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function addSecuredStringsParameters(array $inputs)
    {
        foreach ($inputs as $key => $value){
            $this->parameters[$key] = htmlspecialchars($value);
        }
    }

    public function setDefault(string $parameter, mixed $default){
        if(!$this->parameterExist($parameter)){
            $this->addParameter($parameter,$default);
        }
    }

    public function addParameter($key, $value)
    {
        $this->parameters[$key] = $value;
    }

    public function getSecureParameter($key, &$value):bool
    {
        if($this->parameterExist($key)) {
            $value = $this->parameters[$key];
            return true;
        }
        return false;
    }
    public function getParameter($key):mixed
    {
        if($this->parameterExist($key)) {
            return $this->parameters[$key];
        }
        return false;
    }
    public function removeParameter($key)
    {
        if($this->parameterExist($key)) {
            unset($this->parameters[$key]);
        }
    }

    public function setParameter($key,$value):void
    {
        $this->parameters[$key] = $value;
    }

    public function setSecureParameter($key,$value):bool
    {
        if($this->parameterExist($key)) {
            $this->parameters[$key] = $value;
            return true;
        }
        return false;
    }

    public function parameterExist(string $parameter):bool{
        return array_key_exists($parameter,$this->parameters);
    }

    public function logAllParameters(): void
    {
        GlobalLogger::$logger->info("Parameters:");
        foreach ($this->parameters as $key => $value){
            GlobalLogger::$logger->info("  - $key : $value");
        }
    }

    public function parameterExistAndIsDate(string $parameter):bool{
        if(!$this->getSecureParameter($parameter,$value)) {
            return false;
        }

        if (preg_match("/^[0-9]{4}(0[1-9]|1[0-2])(0[1-9]|[1-2][0-9]|3[0-1])$/",$value)) {
            return true;
        }
        return false;
    }

    public function parameterExistAndIsInt(string $parameter):bool{
        if(!$this->getSecureParameter($parameter,$value)) {
            return false;
        }
        return is_int((int)$value);
    }

    public function parameterExistAndIsNumeric(string $parameter):bool{
        if(!$this->getSecureParameter($parameter,$value)) {
            return false;
        }
        return is_numeric($value);
    }
}