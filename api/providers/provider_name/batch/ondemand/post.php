<?php
///////////////////////////////////
// INPUTS
///////////////////////////////////
$provider_name = htmlspecialchars(GlobalVar::$var_url[3], ENT_QUOTES);

$var = json_decode(file_get_contents('php://input'), TRUE);
$startdate = isset($var["startdate"]) ? htmlspecialchars($var["startdate"]) : null;
$enddate = isset($var["enddate"]) ? htmlspecialchars($var["enddate"]) : null;

GlobalLogger::$logger->debug("Inputs:");
GlobalLogger::$logger->debug("  - startdate : $startdate");
GlobalLogger::$logger->debug("  - enddate : $enddate");
GlobalLogger::$logger->debug("  - provider_name : $provider_name");

if(class_exists($provider_name)){
    $provider_class = new $provider_name();
    $msg="";
    if(!$provider_class->executeRangeDaily($startdate,$enddate,$msg)){
        send(500,$msg);
    }
}else{
    send(400,"Provider not found");
}
