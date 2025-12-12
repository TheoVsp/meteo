<?php

class NasapowerParameters
{

    public static function supportParameter($parameter): bool
    {
        switch ($parameter) {
            case GlobalParameters::RR:
            case GlobalParameters::TEMPMAX:
            case GlobalParameters::TEMPMIN:
            case GlobalParameters::TEMPAVG:
            case GlobalParameters::WINDSPEED:
            case GlobalParameters::WINDGUSTS:
            case GlobalParameters::SOLARIRRADIANCE:
            case GlobalParameters::HUMR:
            case GlobalParameters::DEWPOINT:
                return true;
            default:
                return false;
        }
    }

    public static function nasaToGlobal(string $param_name, &$global_param_name): bool
    {
        switch ($param_name) {
            case 'PRECTOTCORR':
                $global_param_name = GlobalParameters::RR;
                break;
            case 'T2M_MAX':
                $global_param_name = GlobalParameters::TEMPMAX;
                break;
            case 'T2M_MIN':
                $global_param_name = GlobalParameters::TEMPMIN;
                break;
            case 'T2M':
                $global_param_name = GlobalParameters::TEMPAVG;
                break;
            case 'WS10M':
                $global_param_name = GlobalParameters::WINDSPEED;
                break;
            case 'WS10M_MAX':
                $global_param_name = GlobalParameters::WINDGUSTS;
                break;
            case 'RH2M':
                $global_param_name = GlobalParameters::HUMR;
                break;
            case 'T2MDEW':
                $global_param_name = GlobalParameters::DEWPOINT;
                break;
            case 'ALLSKY_SFC_SW_DWN':
                $global_param_name = GlobalParameters::SOLARIRRADIANCE;
                break;
            default:
                return false;
        }
        return true;
    }

    public static function globalToNasa(string $global_param_name, &$nasa_param_name): bool
    {
        switch ($global_param_name) {
            case GlobalParameters::RR:
                $nasa_param_name = 'PRECTOTCORR';
                break;
            case GlobalParameters::TEMPMAX:
                $nasa_param_name = 'T2M_MAX';
                break;
            case GlobalParameters::TEMPMIN:
                $nasa_param_name = 'T2M_MIN';
                break;
            case GlobalParameters::TEMPAVG:
                $nasa_param_name = 'T2M';
                break;
            case GlobalParameters::WINDSPEED:
                $nasa_param_name = 'WS10M';
                break;
            case GlobalParameters::WINDGUSTS:
                $nasa_param_name = 'WS10M_MAX';
                break;
            case GlobalParameters::HUMR:
                $nasa_param_name = 'RH2M';
                break;
            case GlobalParameters::DEWPOINT:
                $nasa_param_name = 'T2MDEW';
                break;
            case GlobalParameters::SOLARIRRADIANCE:
                $nasa_param_name = 'ALLSKY_SFC_SW_DWN';
                break;
            default:
                return false;
        }
        return true;
    }

    public static function getLatency($nasa_param_name): int{
        // see https://power.larc.nasa.gov/docs/methodology/data/sources/
        if(self::isMeteorologicalParameter($nasa_param_name)) {
            return GlobalEnv::$env_nasa_meteo_latency_nrt; // + 1 (batch marge)
        }
        if(self::isEnergyFluxesParameter($nasa_param_name)) {
            return (int)GlobalEnv::$env_nasa_solar_latency_nrt; // + 1 (batch marge)
        }

        // TODO HACK worse case for unknown parameters
        return GlobalEnv::$env_nasa_default_latency_nrt;
    }

    public static function isEnergyFluxesParameter($nasa_param_name): bool
    {
        switch ($nasa_param_name) {
            case (bool)preg_match('/ALLSKY/', $nasa_param_name) :
            case (bool)preg_match('/CLRSKY/', $nasa_param_name) :
            case (bool)preg_match('/TOA/', $nasa_param_name) :
                return true;
            default:
                return false;
        }
    }

    public static function isMeteorologicalParameter($nasa_param_name):bool {
        switch ($nasa_param_name) {
            case (bool)preg_match('/PREC/', $nasa_param_name) :
            case (bool)preg_match('/T2M/', $nasa_param_name) :
            case (bool)preg_match('/WS/', $nasa_param_name) :
            case (bool)preg_match('/RH2/', $nasa_param_name) :
                return true;
            default:
                return false;
        }
    }
}