<?php

class NasapowerUnits
{
    public static function nasaToGlobal(string $param_name, float $value, &$global_value): bool
    {
        // FILL VALUES
        if(self::isFillValue($value)){
            return false;
        }

        if(NasapowerParameters::isMeteorologicalParameter($param_name)){
            $global_value = $value;
            return true;
        }

        // TODO others solar parameter not been converted
        switch ($param_name) {
            case 'ALLSKY_SFC_SW_DWN': // MJ/m2/day
            case 'ALLSKY_SFC_LW_UP': // MJ/m2/day
            case 'ALLSKY_SFC_SW_UP': // MJ/m2/day
            case 'TOA_SW_DWN':
                $global_value = ($value * 1000000.0) / 86400.0;
                break;
            case 'ALLSKY_SFC_LW_DWN': // W/m2/day
                $global_value = ($value) / 86400.0;
                break;
            default:
                return false;
        }
        return true;
    }

    public static function isFillValue($value): bool
    {
        return $value == -99.0 || $value == -999.0  || $value == "-999";
    }

    public static function get(string $nasa_param_name, &$unit): bool
    {
        switch ($nasa_param_name) {
            case 'PRECTOTCORR':
                $unit = "mm";
                break;
            case 'T2M_MAX':
            case 'T2M_MIN':
            case 'T2M':
            case 'T2MDEW':
                $unit = "°C";
                break;
            case 'WS2M':
            case 'WS2M_MAX':
            case 'WS10M':
            case 'WS10M_MAX':
                $unit = "m/s";
                break;
            case 'RH2M':
                $unit = "%";
                break;
            case 'ALLSKY_SFC_SW_DWN':
            case 'ALLSKY_SFC_LW_UP':
            case 'ALLSKY_SFC_SW_UP':
            case 'TOA_SW_DWN':
                $unit = "MJ/m²/day";
                break;
            case 'ALLSKY_SFC_LW_DWN':
                $unit = "W/m²/day";
                break;
            default:
                return false;
        }
        return true;
    }

    public static function DDToNasaDD(Location $location, string $param_name, Location $nasa_location): bool{

        if(NasapowerParameters::isMeteorologicalParameter($param_name)){
            // That resolution is ½° latitude by ⅝° longitude for the meteorological data sets
            $nasa_location->setLatitude(round($location->getLatitude()*2)/2);// 2/1
            $nasa_location->setLongitude(round($location->getLongitude()*1.6)/1.6);// 8/5
            return true;
        }
        // That resolution is 1.0° latitude by 1.0° longitude for the radiation data sets
        // and use 1.0° grid for other case (worse)
        $nasa_location->setLatitude(round($location->getLatitude()));
        $nasa_location->setLongitude(round($location->getLongitude()));
        return true;
    }
}