<?php

$counturl = count(GlobalVar::$var_url);

GlobalLogger::$logger->info("Routing");

switch (GlobalVar::$var_request_method) {
    case 'GET':

        if($counturl === 3 && GlobalVar::$var_url[3] === "distance2points"){
            include 'distance2points/get.php';
            break;
        }

        send(404,GlobalVar::$var_not_found);
        break;

    default:
        send(405,"methode " . GlobalVar::$var_request_method . " Not Allowed");
        break;
}

