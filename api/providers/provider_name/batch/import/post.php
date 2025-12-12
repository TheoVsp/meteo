<?php
///////////////////////////////////
// INPUTS
///////////////////////////////////
$provider_name = htmlspecialchars(GlobalVar::$var_url[3], ENT_QUOTES);

$var = json_decode(file_get_contents('php://input'), TRUE);
$startdate = isset($var["startdate"]) ? htmlspecialchars($var["startdate"]) : "";
$enddate = isset($var["enddate"]) ? htmlspecialchars($var["enddate"]) : "";
$latitude = isset($var["latitude"]) ? htmlspecialchars($var["latitude"]) : null;
$longitude = isset($var["longitude"]) ? htmlspecialchars($var["longitude"]) : null;
$parameter = isset($var["parameter"]) ? htmlspecialchars($var["parameter"]) : "";

GlobalLogger::$logger->debug("Inputs:");
GlobalLogger::$logger->debug("  - provider_name : $provider_name");
GlobalLogger::$logger->debug("  - startdate : $startdate");
GlobalLogger::$logger->debug("  - enddate : $enddate");
GlobalLogger::$logger->debug("  - latitude : $latitude");
GlobalLogger::$logger->debug("  - longitude : $longitude");
GlobalLogger::$logger->debug("  - parameter : $parameter");

if(class_exists($provider_name)){
    $provider_class = new $provider_name();
    $msg="";
    $location = new Location(latitude:$latitude,longitude:$longitude);
    if(!$provider_class->importInCache($startdate,$enddate,$location,$parameter,$msg)){
        send(500,$msg);
    }
}else{
    send(400,"Provider not found");
}
