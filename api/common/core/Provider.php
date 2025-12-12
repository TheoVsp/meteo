<?php

abstract class Provider
{
    /////////////////////
    // OTHERS PART
    /////////////////////
    abstract public static function supportParameter(string $parameter): bool;
    abstract public static function canProvidesOneData(Array $missingDates): bool;

    /////////////////////
    // QUERY PART
    /////////////////////
    abstract public function getData(WeatherParameters $weatherParameters, WeatherData $weatherData, &$error_msg): bool;

    /////////////////////
    // BATCH PART
    /////////////////////
    abstract public function executeDaily(&$error_msg): bool;
    abstract public function executeRangeDaily(string $start_date, string $end_date,&$error_msg): bool;
    abstract public function importInCache(string $start_date, string $end_date, Location $location, string $parameter_name,&$error_msg): bool;
}