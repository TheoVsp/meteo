<?php
GlobalLogger::$logger->info("Routing");

$counturl = count(GlobalVar::$var_url);

if ($counturl === 2) {
    switch (GlobalVar::$var_request_method) {
        case 'GET':
            include 'get.php';
            break;

        default:
            send(405,"methode " . GlobalVar::$var_request_method . " Not Allowed");
            break;
    }
} else {
    send(404,GlobalVar::$var_not_found);
}
