<?php

// start timer
$timer_start = microtime(true);

// for hash
$requesturi = $_SERVER['REQUEST_URI'];

$HASH = substr(hash('sha256', 'request=' . $requesturi . microtime(true). mt_rand() ),0,8);

include_once $_SERVER['DOCUMENT_ROOT'].'/common/core.php';

GlobalVar::$var_request_method =  $_SERVER["REQUEST_METHOD"];

GlobalLogger::$logger->info("URI [HASH:" . $HASH ."] [methode:".GlobalVar::$var_request_method."]: " . $requesturi);

// Get function into raw url
$rawurl = parse_url($requesturi, PHP_URL_PATH);

// Use in route files
GlobalVar::$var_not_found="Url " . $rawurl . " not found on server." . SUPPORT;

// ---------------------------------------------------------
// Routing
// search route.php file
// http://host:port/api/func/ => search /func/route.php
// ---------------------------------------------------------
GlobalVar::$var_url = array_filter(explode('/', $rawurl));
$page = GlobalVar::$var_url[2];
$routefile = __DIR__ . '/' . $page . '/route.php';
if (file_exists($routefile)) {
    // try to load route file
    try{
        require $routefile;
    }
    catch (Exception  $e){
        GlobalLogger::$logger->error("Server Error - " . print_r($e,true));
        http_response_code(500);
    }
} else {
    // url not found on server
    send(404,GlobalVar::$var_not_found);
}
GlobalLogger::$logger->info("URI: " . $requesturi . " generate in " . round((microtime(true) - $timer_start) * 1000 ) . " ms");
GlobalLogger::$logger->debug("**********************************************************************");
