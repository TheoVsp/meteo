<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common/const.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/env.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/log.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/curl.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/database.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/http.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/mail.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/core/Geo.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/core/TimeZone.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/core/Location.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/core/RawDataTable.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/core/Provider.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/core/GlobalParameters.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/core/GlobalUnits.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/core/WeatherData.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/core/WeatherParameters.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/core/GlobalUnits.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/ServiceProvider.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/providers/meteoblue/Meteoblue.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/providers/meteoblue/MeteoblueParameters.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/providers/meteoblue/MeteoblueUnits.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/providers/nasapower/Nasapower.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/providers/nasapower/NasapowerParameters.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/providers/nasapower/NasapowerUnits.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/providers/ecmwf/Ecmwf.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/providers/ecmwf/EcmwfParameters.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/providers/ecmwf/EcmwfUnits.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/providers/rws/Rws.php';
include_once $_SERVER['DOCUMENT_ROOT'].'/common/api/providers/rweather/Rweather.php';

class GlobalVar
{
    public static array $var_url;

    public static string $var_not_found= "";

    public static string $var_request_method= "";

    public static bool $var_authorized_create_location = false;
}

/**
 * Compare 2 Weather Type (ADJUSTED > PREDICTED)
 * @param string $type1 Weather Type (ADJUSTED,PREDICTED )
 * @param string $type2 Weather Type (ADJUSTED,PREDICTED )
 * @return number 0 if equal, 1 if type1 is greater than type2 and -1 if type1 is less than type2
 */
function compType(string $type1, string $type2): int
{
    // current order :
    // ADJUSTED > PREDICTED
    
    switch ($type1) {
        case CALCULATED_ADJUSTED:
        case ADJUSTED:
            switch ($type2) {
                case CALCULATED_ADJUSTED:
                case ADJUSTED:
                    return 0;
                case CALCULATED_PREDICTED:
                case PREDICTED:
                    return 1;
                default:
                    $msg="Data have bad weather type 2 (ADJUSTED,PREDICTED) : " . $type2;
                    GlobalLogger::$logger->error($msg);
                    throw new Exception($msg);
            }
        case CALCULATED_PREDICTED:
        case PREDICTED:
            switch ($type2) {
                case CALCULATED_ADJUSTED:
                case ADJUSTED:
                    return -1;
                case CALCULATED_PREDICTED:
                case PREDICTED:
                    return 0;
                default:
                    $msg="Data have bad weather type 2 (ADJUSTED,PREDICTED) : " . $type2;
                    GlobalLogger::$logger->error($msg);
                    throw new Exception($msg);
            }
        default:
            $msg="Data have bad weather type 1 (ADJUSTED,PREDICTED) : " . $type1;
            GlobalLogger::$logger->error($msg);
            throw new Exception($msg);
    }
}

function isFileExists($filepath): bool
{
    if(!file_exists($filepath)) {
        GlobalLogger::$logger->warn("Can't open $filepath");
        return false;
    }

    GlobalLogger::$logger->info("Can open $filepath");
    return true;
}

function generateCsv($data){
    // send header
    header("Content-Type:application/csv; charset=utf-8");
    http_response_code(200);

    // open file
    $csvfile = fopen("php://output", 'wb') or die("Can't open php://output");
    fwrite($csvfile, BOM);
    // ////////
    // HEADER
    GlobalLogger::$logger->info("Construct csv header");
    $headercsv = array();
    $headercsv[] = "Date";
    $headercsv[] = "Value";
    // put header
    fputcsv($csvfile, $headercsv, CSV_DELIMETER);

    // ////////
    // BODY
    GlobalLogger::$logger->info("Construct csv body");
    foreach ($data as $key => $value) {
        $bodycsv = array();
        $bodycsv[] = $key;
        $bodycsv[] = $value;
        if(empty($key))
            continue;
        fputcsv($csvfile, $bodycsv, CSV_DELIMETER);
    }

    // close file
    fclose($csvfile) or die("Can't close php://output");
}


function getDatimeWithLatency(int $day_latency){

    $now = new DateTime();
    if ($day_latency === 0) {
        return $now;
    }
    if($day_latency > 0) {
       return $now->sub(new DateInterval("P". ($day_latency+1) ."D"));
    }
    return $now->add(new DateInterval("P". (-$day_latency+1) ."D"));
}