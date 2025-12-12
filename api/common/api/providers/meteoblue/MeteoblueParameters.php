<?php

class MeteoblueParameters
{
    public static function supportParameter($parameter): bool
    {
        switch ($parameter) {
            case GlobalParameters::RR:
            case GlobalParameters::TEMPMAX:
            case GlobalParameters::TEMPMIN:
            case GlobalParameters::TEMPAVG:
            case GlobalParameters::DEWPOINT:
            case GlobalParameters::WINDSPEED:
            case GlobalParameters::WINDGUSTS:
            case GlobalParameters::SOLARIRRADIANCE:
            case GlobalParameters::HUMR:
            case GlobalParameters::ETP:
                return true;
            default:
                return false;
        }
    }

    public static function getLatency(): int{
        return (int)GlobalEnv::$env_meteoblue_default_latency;
    }

    public static function globalToMeteoblue(string $global_param_name, &$param_name,&$param_unit_name,&$param_unit): bool
    {
        switch ($global_param_name) {
            case GlobalParameters::RR:
                $param_name = "precipitation";
                $param_unit_name="precipitation";
                $param_unit = "mm";
                break;
            case GlobalParameters::TEMPMAX:
                $param_name = "temperature_max";
                $param_unit_name="temperature";
                $param_unit  = "C";
                break;
            case GlobalParameters::TEMPMIN:
                $param_name = "temperature_min";
                $param_unit_name="temperature";
                $param_unit  = "C";
                break;
            case GlobalParameters::TEMPAVG:
                $param_name = "temperature_mean";
                $param_unit_name="temperature";
                $param_unit  = "C";
                break;
            case GlobalParameters::WINDSPEED:
                $param_name = "windspeed_mean";
                $param_unit_name="windspeed";
                $param_unit = "ms-1";
                break;
            case GlobalParameters::WINDGUSTS:
                $param_name = "windspeed_max";
                $param_unit_name="windspeed";
                $param_unit = "ms-1";
                break;
            case GlobalParameters::HUMR:
                $param_name = "relativehumidity_mean";
                $param_unit_name="relativehumidity";
                $param_unit = "percent";
                break;
            case GlobalParameters::ETP:
                $param_name = "referenceevapotranspiration_fao";
                $param_unit_name="transpiration";
                $param_unit = "mm";
                break;
            case GlobalParameters::DEWPOINT:
                $param_name = "dewpointtemperature_mean";
                $param_unit_name="temperature";
                $param_unit  = "C";
                break;
            case GlobalParameters::SOLARIRRADIANCE:
                $param_name = "ghi_total";
                $param_unit_name  = "radiation";
                $param_unit  = "Wm-2";
                break;
            default:
                return false;
        }
        return true;
    }

    public static function meteoblueToGlobal(string $param_name, &$global_param_name): bool
    {
        switch ($param_name) {
            case 'precipitation':
                $global_param_name = GlobalParameters::RR;
                break;
            case 'temperature_max':
                $global_param_name = GlobalParameters::TEMPMAX;
                break;
            case 'temperature_min':
                $global_param_name = GlobalParameters::TEMPMIN;
                break;
            case 'temperature_mean':
                $global_param_name = GlobalParameters::TEMPAVG;
                break;
            case 'windspeed_mean':
                $global_param_name = GlobalParameters::WINDSPEED;
                break;
            case 'windspeed_max':
                $global_param_name = GlobalParameters::WINDGUSTS;
                break;
            case 'relativehumidity_mean':
                $global_param_name = GlobalParameters::HUMR;
                break;
            case 'potentialevapotranspiration':
                $global_param_name = GlobalParameters::ETP;
                break;
            case 'dewpointtemperature_mean':
                $global_param_name = GlobalParameters::DEWPOINT;
                break;
            case 'ghi_total':
                $global_param_name = GlobalParameters::SOLARIRRADIANCE;
                break;            default:
                return false;
        }
        return true;
    }
}