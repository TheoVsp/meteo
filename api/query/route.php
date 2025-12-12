<?php
GlobalLogger::$logger->info("Routing");

$counturl = count(GlobalVar::$var_url);

if ($counturl >= 2) {
    $function2 = htmlspecialchars(GlobalVar::$var_url[2], ENT_QUOTES);
}

if ($counturl >= 3) {
    $function3 = htmlspecialchars(GlobalVar::$var_url[3], ENT_QUOTES);
}

switch (GlobalVar::$var_request_method) {
    case 'GET':

        if($counturl === 3 && $function2 === "query" && $function3 === "daily"){
            include 'daily/get.php';
            break;
        }
        send(404,GlobalVar::$var_not_found);
        break;

    default:
        send(405,"methode " . GlobalVar::$var_request_method . " Not Allowed");
        break;
}