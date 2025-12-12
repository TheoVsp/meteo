<?php
use Location\Coordinate;
use Location\Distance\Vincenty;

$latitude1 = isset($_GET["latitude1"]) ? htmlspecialchars($_GET["latitude1"]) : null;
$longitude1 = isset($_GET["longitude1"]) ? htmlspecialchars($_GET["longitude1"]) : null;

$latitude2 = isset($_GET["latitude2"]) ? htmlspecialchars($_GET["latitude2"]) : null;
$longitude2 = isset($_GET["longitude2"]) ? htmlspecialchars($_GET["longitude2"]) : null;


GlobalLogger::$logger->debug("Inputs:");
GlobalLogger::$logger->debug("  - latitude1 : $latitude1");
GlobalLogger::$logger->debug("  - longitude1 : $longitude1");
GlobalLogger::$logger->debug("  - latitude2 : $latitude2");
GlobalLogger::$logger->debug("  - longitude2 : $longitude2");

if($latitude1 === null || $longitude1 == null || $latitude2 === null ||$longitude2=== null)
{
    send(400, "Bad request");

}else{
    $coordinate1 = new Coordinate($latitude1, $longitude1);
    $coordinate2 = new Coordinate($latitude2, $longitude2);

    $calculator = new Vincenty();

    send(200, $calculator->getDistance($coordinate1, $coordinate2));
}
