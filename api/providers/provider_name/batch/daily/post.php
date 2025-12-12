<?php

$provider_name = htmlspecialchars(GlobalVar::$var_url[3], ENT_QUOTES);

GlobalLogger::$logger->debug("Inputs:");
GlobalLogger::$logger->debug("  provider_name : $provider_name");

if(class_exists($provider_name)){
    $provider_class = new $provider_name();
    $error_msg="";
    if(!$provider_class->executeDaily($error_msg)){
        send(500,$error_msg);
    }
}else{
    send(400,"Provider not found");
}