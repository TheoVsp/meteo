<?php

/**
 * Database class
 */
class Location
{
    private string|null $name;
    private string|null $latitude;
    private string|null  $longitude;
    private string|null $altitude;

    public function __construct(string|null $name = null, string|null $latitude= null, string|null $longitude= null , string|null $altitude = null){
        if(isset($name)){
            $this->name = str_replace("'", "\'", $name); // TODO HACK
        }
        $this->latitude=$latitude;
        $this->longitude=$longitude;
        $this->altitude=$altitude;
    }

    public function __toString(): string
    {
        $string ="[";
        if(isset($this->name)){
            $string.="[name: ".$this->name."]";
        }
        if(isset($this->latitude)){
            $string.="[latitude: ".$this->latitude."]";
        }
        if(isset($this->longitude)){
            $string.="[longitude: ".$this->longitude."]";
        }
        if(isset($this->altitude)){
            $string.="[altitude: ".$this->altitude."]";
        }
        $string.="]";

        return $string;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * @param string $latitude
     */
    public function setLatitude(string $latitude): void
    {
        $this->latitude = $latitude;
    }

    /**
     * @return string
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * @param string $longitude
     */
    public function setLongitude(string $longitude): void
    {
        $this->longitude = $longitude;
    }

    /**
     * @return string
     */
    public function getAltitude()
    {
        return $this->altitude;
    }

    /**
     * @param string $altitude
     */
    public function setAltitude(string $altitude): void
    {
        $this->altitude = $altitude;
    }

    public function get_nearest_timezone() {
        return TimeZone::get_nearest_timezone($this->getLatitude(),$this->getLongitude());
    }
}