<?php

class MeteoblueUnits
{
    public static function meteoblueToGlobal(string $param_name, $value, &$global_value): bool
    {
        switch ($param_name) {
            case 'precipitation':
            case 'temperature_max':
            case 'temperature_min':
            case 'temperature_mean':
            case 'windspeed_mean':
            case 'windspeed_max':
            case 'relativehumidity_mean':
            case 'potentialevapotranspiration':
            case 'dewpointtemperature_mean':
                $global_value = (float) $value;
                break;
            case 'ghi_total':
                $global_value = (float) $value / 24.0;
                break;
            default:
                return false;
        }
        return true;
    }

    public static function isFillValue($value): bool
    {
        return $value == ""; // don't use === because not work
    }

    public static function get(string $param_name, &$unit): bool
    {
        switch ($param_name) {
            case 'temperature_max':
            case 'temperature_min':
            case 'temperature_mean':
            case 'dewpointtemperature_mean':
                $unit = "°C";
                break;
            case 'windspeed_mean':
            case 'windspeed_max':
                $unit = "m/s";
                break;
            case 'relativehumidity_mean':
                $unit = "%";
                break;
            case 'potentialevapotranspiration':
            case 'precipitation':
                $unit = "mm";
                break;
            case 'ghi_total':
                $unit = "Whm-2";
                break;
            default:
                return false;
        }
        return true;
    }

    public static function DDToMeteoblueDD(Location $location, Location $mb_location): bool{

        // accurancy 2km (1/50 100km)
        $mb_location->setLatitude(round($location->getLatitude()*50)/50);
        $mb_location->setLongitude(round($location->getLongitude()*50)/50);
        return true;
    }
}