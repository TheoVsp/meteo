<?php

$parameters = GlobalParameters::getConstants();

$results = array();

foreach ($parameters as $parameter) {
    GlobalUnits::get($parameter,$unit);
    $result = array();
    $result["name"] = $parameter;
    $result["unit"] = $unit;
    $results[] = $result;
}

send(200, $results,printresponse: false,withMessage: false);