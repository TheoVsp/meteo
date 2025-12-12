<?php

class GlobalParameters
{
    public const RR = 'RR';
    public const TEMPMAX = 'TEMPMAX';
    public const TEMPMIN = 'TEMPMIN';
    public const TEMPAVG = 'TEMPAVG';
    public const WINDSPEED = 'WINDSPEED';
    public const WINDGUSTS = 'WINDGUSTS';
    public const SOLARIRRADIANCE = 'SOLARIRRADIANCE';
    public const HUMR = 'HUMR';
    public const DEWPOINT = 'DEWPOINT';
    public const VAPOURPRESSURE = 'VAPOURPRESSURE';
    public const ETP = 'ETP';
    public const GDD_0_30 = 'GDD_0_30';
    public const GDD_6_30 = 'GDD_6_30';
    public const GDD_6_34 = 'GDD_6_34';

    public static function isGlobalParameter(string $parametername){
        switch ($parametername) {
            case GlobalParameters::ETP:
            case GlobalParameters::RR:
            case GlobalParameters::TEMPMAX:
            case GlobalParameters::TEMPMIN:
            case GlobalParameters::TEMPAVG:
            case GlobalParameters::GDD_0_30:
            case GlobalParameters::GDD_6_30:
            case GlobalParameters::GDD_6_34:
            case GlobalParameters::DEWPOINT:
            case GlobalParameters::WINDSPEED:
            case GlobalParameters::WINDGUSTS:
            case GlobalParameters::SOLARIRRADIANCE:
            case GlobalParameters::HUMR:
            case GlobalParameters::VAPOURPRESSURE:
                return true;
            default:
                return false;
        }
    }

    public static function getConstants() {
        return (new ReflectionClass(__CLASS__))->getConstants();
    }
}