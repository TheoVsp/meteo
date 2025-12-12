<?php
// store input
$weatherParameters = new WeatherParameters();
$weatherParameters->addSecuredStringsParameters($_GET);

// set default
$weatherParameters->setDefault(FORMAT,JSON);
$weatherParameters->setDefault(WeatherData::METADATA,true);
$weatherParameters->setDefault(WeatherParameters::ALTITUDE,0);

// pint all parameters
$weatherParameters->logAllParameters();

// add input parameter into metadata weather output data
$weatherData = new WeatherData();
foreach ($weatherParameters->getParameters() as $key => $input) {
    if($key === "key" && $input === GlobalEnv::$env_meteoblue_secret_location_creation){
        GlobalVar::$var_authorized_create_location = true;
        continue;
    }
    $weatherData->addMetadata($input, WeatherData::METADATA_INPUTS,$key);
}

// create service provider
$service = new ServiceProvider();
if ($service->getData($weatherParameters, $weatherData, $error_msg)) {

    // format
    $format = $weatherParameters->getParameter(FORMAT);
    if ($format === JSON) {

        // metadata
        if($weatherParameters->getParameter(WeatherData::METADATA)){
            send(200, $weatherData->getDataAndMetadata(), printresponse: false, withMessage: false);
        }else{
            send(200, $weatherData->getData(), printresponse: false, withMessage: false);
        }

    } elseif($format === CSV){
        generateCsv($weatherData->getData());
    }else{
        send(400,"Format $format not supported");
    }
} else {
    send(500, $error_msg);
}