<?php
GlobalLogger::$logger->info("Routing");

$counturl = count(GlobalVar::$var_url);

if ($counturl >= 2) {
    $function2 = htmlspecialchars(GlobalVar::$var_url[2], ENT_QUOTES);
}

if ($counturl >= 4) {
    $function4 = htmlspecialchars(GlobalVar::$var_url[4], ENT_QUOTES);
}

if ($counturl >= 5) {
    $function5 = htmlspecialchars(GlobalVar::$var_url[5], ENT_QUOTES);
}

switch (GlobalVar::$var_request_method) {
    case 'GET':

        if($counturl === 2 && $function2 === "providers" ){
            include 'get.php';
            break;
        }

        if($counturl === 4 &&  $function4 === "query"){
            include 'provider_name/query/get.php';
            break;
        }

        send(404,GlobalVar::$var_not_found);
        break;

    case 'POST':

        if($counturl === 5 &&  $function4 === "batch" && $function5 === "import"){
            include 'provider_name/batch/import/post.php';
            break;
        }

        if($counturl === 5 &&  $function4 === "batch" && $function5 === "ondemand"){
            include 'provider_name/batch/ondemand/post.php';
            break;
        }

        if($counturl === 5 && $function4 === "batch" && $function5 === "daily"){
            include 'provider_name/batch/daily/post.php';
            break;
        }

        send(404,GlobalVar::$var_not_found);
        break;

    default:
        send(405,"methode " . GlobalVar::$var_request_method . " Not Allowed");
        break;
}