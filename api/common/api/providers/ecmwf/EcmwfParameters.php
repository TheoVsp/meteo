<?php

class EcmwfParameters
{
    public static function supportParameter($parameter): bool
    {
        switch ($parameter) {
            case GlobalParameters::RR:
            case GlobalParameters::TEMPMAX:
            case GlobalParameters::TEMPMIN:
            case GlobalParameters::TEMPAVG:
            case GlobalParameters::WINDSPEED:
            case GlobalParameters::SOLARIRRADIANCE:
                //case GlobalParameters::HUMR: // agregate
            case GlobalParameters::DEWPOINT:
            case GlobalParameters::VAPOURPRESSURE:
                return true;
            default:
                return false;
        }
    }

    public static function getLatency(): int{
        return (int)GlobalEnv::$env_ecmwf_default_latency;
    }

    public static function globalToEcmwf(string $global_param_name, &$variable, &$statistic): bool{
        switch($global_param_name)
        {
            case GlobalParameters::TEMPAVG:
                $variable="2m_temperature";
                $statistic="24_hour_mean";
                break;
            case GlobalParameters::TEMPMIN:
                $variable="2m_temperature";
                $statistic="24_hour_minimum";
                break;
            case GlobalParameters::TEMPMAX:
                $variable="2m_temperature";
                $statistic="24_hour_maximum";
                break;
            case GlobalParameters::WINDSPEED:
            case 'FF10':
                $variable="10m_wind_speed";
                $statistic="24_hour_mean";
                break;
            case GlobalParameters::RR:
                $variable="precipitation_flux";
                $statistic=null;
                break;
            case GlobalParameters::SOLARIRRADIANCE:
                $variable="solar_radiation_flux";
                $statistic=null;
                break;
            case GlobalParameters::DEWPOINT:
                $variable="2m_dewpoint_temperature";
                $statistic="24_hour_mean";
                break;
            case GlobalParameters::VAPOURPRESSURE:
                $variable="vapour_pressure";
                $statistic="24_hour_mean";
                break;
            default:
                return false;
        }
        return true;
    }

    public static function ecmwfToGlobal(string $variable, string | null $statistic, &$global_param_name): bool {

        switch($variable)
        {
            case '2m_dewpoint_temperature':
                switch($statistic)
                {
                    case '24_hour_mean':
                        $global_param_name=GlobalParameters::DEWPOINT;
                        break;
                    default;
                        return false;
                }
                break;
            case 'vapour_pressure':
                switch($statistic)
                {
                    case '24_hour_mean':
                        $global_param_name=GlobalParameters::VAPOURPRESSURE;
                        break;
                    default;
                        return false;
                }
                break;
            case '2m_temperature':

                switch($statistic)
                {
                    case '24_hour_mean':
                        $global_param_name=GlobalParameters::TEMPAVG;
                        break;
                    case '24_hour_maximum':
                        $global_param_name=GlobalParameters::TEMPMAX;
                        break;
                    case '24_hour_minimum':
                        $global_param_name=GlobalParameters::TEMPMIN;
                        break;
                    default;
                        return false;
                }
                break;
            case '10m_wind_speed':
                switch($statistic)
                {
                    case '24_hour_mean':
                        $global_param_name=GlobalParameters::WINDSPEED;
                        break;
                    default;
                        return false;
                }
                break;
            case 'precipitation_flux':
                $global_param_name=GlobalParameters::RR;
                break;
            case 'solar_radiation_flux':
                $global_param_name=GlobalParameters::SOLARIRRADIANCE;
                break;
            default;
                return false;
        }
        return true;
    }

    public static function ecwmfVariableToFilename(string $variable, $statistic, string| null $date, &$filename): bool {

        switch($variable)
        {
            case '2m_dewpoint_temperature':
                switch($statistic)
                {
                    case '24_hour_mean':
                        $filename="Dew-Point-Temperature-2m-Mean";
                        break;
                    default;
                        return false;
                }
                break;
            case 'vapour_pressure':
                switch($statistic)
                {
                    case '24_hour_mean':
                        $filename="Vapour-Pressure-Mean";
                        break;
                    default;
                        return false;
                }
                break;
            case '2m_temperature':
                switch($statistic)
                {
                    case '24_hour_mean':
                        $filename="Temperature-Air-2m-Mean-24h";
                        break;
                    case '24_hour_maximum':
                        $filename="Temperature-Air-2m-Max-24h";
                        break;
                    case '24_hour_minimum':
                        $filename="Temperature-Air-2m-Min-24h";
                        break;
                    default;
                        return false;
                }
                break;
            case '10m_wind_speed':
                switch($statistic)
                {
                    case '24_hour_mean':
                        $filename="Wind-Speed-10m-Mean";
                        break;
                    default;
                        return false;
                }
                break;
            case 'precipitation_flux':
                $filename="Precipitation-Flux";
                break;
            case 'solar_radiation_flux':
                $filename="Solar-Radiation-Flux";
                break;
            default;
                return false;
        }

        if(isset($date)){
            $filename.= "_C3S-glob-agric_AgERA5_" . $date. "_final-v1.0.nc";
        }

        return true;
    }
}