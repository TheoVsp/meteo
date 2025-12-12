<?php

class WeatherData
{
    public const METADATA='metadata';
    public const DATA='data';

    public const METADATA_INPUTS="inputs";
    public const METADATA_OUTPUT="outputs";
    public const METADATA_VARIABLE="variable";
    public const METADATA_STATISTIC="statistic";
    public const METADATA_CONTENTS="contents";
    public const METADATA_LATITUDE="latitude";
    public const METADATA_LONGITUDE="longitude";
    public const METADATA_DISTANCE="distance";
    public const METADATA_UNITCONVERSION="unitconversion";
    public const METADATA_PARAMETER="parameter";
    public const METADATA_UNIT="unit";
    public const METADATA_UNITCONSISTENCY="unitconsistency";
    public const METADATA_UNITFROMAPI="unitfromapi";
    public const METADATA_UNITFROMDATABASE="unitfromdatabase";
    public const METADATA_CONVERTEDLATITUDE="convertedlatitude";
    public const METADATA_CONVERTEDLONGITUDE="convertedlongitude";

    public const DATA_PARTIAL='partial';
    public const DATA_COMPLETE='complete';

    private array $data;
    private array $metadata;

    public function __construct()
    {
        $this->data = array();
        $this->metadata  = array();
    }
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function removeMetadataWithKeys(string $key ="",string $secondkey ="", string $thirdkey = ""):void{
        if (!empty($thirdkey)) {

            unset($this->metadata[$key][$secondkey][$thirdkey]);
            return;
        }

        if(!empty($secondkey)) {
            unset( $this->metadata[$key][$secondkey]);
            return;
        }

        unset(  $this->metadata[$key]);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getDataAndMetadata(): array{
        $output = array();
        $output[self::METADATA]=$this->metadata;
        $output[self::DATA]=$this->data;
        return $output;
    }
    public function getMetadataWithKeys(string $key ="",string $secondkey ="", string $thirdkey = ""): mixed{
        if (!empty($thirdkey)) {
            return  $this->metadata[$key][$secondkey][$thirdkey];
        }

        if(!empty($secondkey)) {
            return  $this->metadata[$key][$secondkey];
        }

        return  $this->metadata[$key];
    }

    public function addMetadataIfNotExist(mixed $metadata,string $key ="",string $secondkey ="", string $thirdkey = ""): void {
        if (!empty($thirdkey)) {
            if(!array_key_exists($thirdkey,$this->metadata[$key][$secondkey])){
                $this->addMetadata($metadata,$key,$secondkey, $thirdkey);
                return;
            }
        }

        if(!empty($secondkey)) {
            if(!array_key_exists($secondkey,$this->metadata[$key])){
                $this->addMetadata($metadata,$key,$secondkey);
                return;
            }
        }

        if(!array_key_exists($key,$this->metadata)){
            $this->addMetadata($metadata,$key);
        }
    }

    public function addMetadata(mixed $metadata,string $key ="",string $secondkey ="", string $thirdkey = ""):void{
        if(is_array($metadata)){
            if(!empty($metadata)){
                $this->metadata += $metadata;
            }
        }else{
            if(!empty($metadata) && !empty($key) && !empty($secondkey)){
                if(!array_key_exists($key,$this->metadata)){
                    $this->metadata[$key] = array();
                }

                if(!empty($thirdkey)){
                    if(!array_key_exists($secondkey,$this->metadata[$key])){
                        $this->metadata[$key][$secondkey] = array();
                    }
                    $this->metadata[$key][$secondkey][$thirdkey] = $metadata;
                }else{
                    $this->metadata[$key][$secondkey] = $metadata;
                }
            }
        }
    }

    public function addData(mixed $data,string $key =""){
        if(is_array($data)){
            if(!empty($data)) {
                $this->data += $data;
            }
        }else{
            $this->data[$key] = $data;
        }
    }

    public function logAll(){
        GlobalLogger::$logger->info("Metadata:");
        GlobalLogger::$logger->info(print_r($this->metadata,true));
        GlobalLogger::$logger->info("Data:");
        GlobalLogger::$logger->info(print_r($this->data,true));
    }
}