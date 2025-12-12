<?php

use Location\Coordinate;
use Location\Distance\Vincenty;

class Geo
{
    public static function distance($latitude1, $longitude1,$latitude2, $longitude2, $round = false): float{
        $coordinate1 = new Coordinate($latitude1, $longitude1);
        $coordinate2 = new Coordinate($latitude2, $longitude2);
        $calculator = new Vincenty();
        if($round === false){
            return $calculator->getDistance($coordinate1, $coordinate2);
        }else{
            return round($calculator->getDistance($coordinate1, $coordinate2)/1000.0,$round);
        }
    }
}