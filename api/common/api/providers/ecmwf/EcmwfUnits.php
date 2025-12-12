<?php

class EcmwfUnits
{
    /**
     * Convert decimal degrees to degrees,minutes,second
     * @param float $dd decimal degrees
     * @param $degrees $degrees
     * @param $minutes $minutes
     * @param $seconds $seconds
     */
    public static function DDToDMS(float $dd, &$degrees , &$minutes, &$seconds): void{
        $degrees = (int)$dd;
        $minutes = (int)(($dd - $degrees) * 60);
        $seconds = (($dd - $degrees - $minutes/60) * 3600);
    }

    public static function DMSToEcmwfNc($dms_deg_lat, $dms_min_lat, $dms_deg_long, $dms_min_long, &$converted_latitude, &$converted_longitude ): void{

            # accurancy 10km
            $dms_final_lat = $dms_deg_lat + ($dms_min_lat * 0.01);
            $dms_final_long = $dms_deg_long + ($dms_min_long * 0.01);

            // 0 1800 3600 in NC file
            // -180.0 0 180.0 DMS
            $converted_longitude = round(1800 + ( 10 * $dms_final_long ));

            // 0 900 1800 in NC file
            // 90.0 0 -90.0 DMS
            $converted_latitude = round(900 + ( - 10 * $dms_final_lat ));
    }

    public static function DDToEcmwfDD(float $dd_lat, float $dd_long,&$round_lat, &$round_long){
        # accurancy 10km
        $round_lat = round($dd_lat,1);
        $round_long = round($dd_long,1);
    }

    public static function DDToEcmwfNc(float $dd_lat, float $dd_long,&$round_lat,&$round_long, &$converted_latitude, &$converted_longitude, &$msg): bool{

        $msg ="";

        // check limit lat and long
        if($dd_lat > 89 || $dd_lat < -89 || $dd_long > 179 || $dd_long < -179 )
        {
            $msg="Not valid latitude $dd_lat (-89 < lat < 89) or longitude $dd_long (-179 < long < 179)";
            GlobalLogger::$logger->error($msg);
            return false;
        }

        self::DDToEcmwfDD($dd_lat,$dd_long,$round_lat,$round_long);
        self::DDToDMS($round_lat, $dms_deg_lat , $dms_min_lat, $seconds);
        self::DDToDMS($round_long, $dms_deg_long , $dms_min_long, $seconds);
        self::DMSToEcmwfNc($dms_deg_lat, $dms_min_lat, $dms_deg_long,$dms_min_long,$converted_latitude,$converted_longitude );

        return true;
    }

    public static function isFillValue($value): bool
    {
        return $value == -99.0 || $value == -999.0 || $value == "-999" ;
    }

    public static function ecmwfToGlobal(string $variable,string|null $statistic,float $value, &$global_value): bool{

        // FILL VALUE
        if(self::isFillValue($value)) {
            return false;
        }
        switch($variable)
        {
            case '2m_temperature':
            case '2m_dewpoint_temperature':
                switch($statistic)
                {
                    case '24_hour_mean':
                    case '24_hour_maximum':
                    case '24_hour_minimum':
                        if($value < -100.0) {
                            return false;
                        }
                        $global_value = $value - 273.15; // kelvin
                        break;
                    default;
                        return false;
                }
                break;
            case '10m_wind_speed':
            case 'vapour_pressure':
                switch($statistic)
                {
                    case '24_hour_mean':
                        if($value < 0.0) {
                            return false;
                        }
                        $global_value = $value;
                        break;
                    default;
                        return false;
                }
                break;
            case 'precipitation_flux':
                if($value < 0.0) {
                    return false;
                }
                $global_value = $value;
                break;
            case 'solar_radiation_flux':
                if($value < 0){
                    return false;
                }
                $global_value = $value / 86400.0; // J/m2/day
                break;
            default;
                return false;
        }
        return true;
    }
}