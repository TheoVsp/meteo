<?php

class GlobalUnits
{
    public static function get(string $parametername, &$unit): bool
    {
        switch ($parametername) {
            case GlobalParameters::ETP:
            case GlobalParameters::RR:
                $unit = "mm";
                break;
            case GlobalParameters::TEMPMAX:
            case GlobalParameters::TEMPMIN:
            case GlobalParameters::TEMPAVG:
            case GlobalParameters::GDD_0_30:
            case GlobalParameters::GDD_6_30:
            case GlobalParameters::GDD_6_34:
            case GlobalParameters::DEWPOINT:
                $unit = "°C";
                break;
            case GlobalParameters::WINDSPEED:
            case GlobalParameters::WINDGUSTS:
                $unit = "m/s";
                break;
            case GlobalParameters::SOLARIRRADIANCE:
                $unit = "W/m²";
                break;
            case GlobalParameters::HUMR:
                $unit = "%";
                break;
            case GlobalParameters::VAPOURPRESSURE:
                $unit="hPa";
                break;
            default:
                return false;
        }
        return true;
    }

}