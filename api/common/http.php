<?php

// ///////////////////////////////////////////////////////////
// UTILS
// ///////////////////////////////////////////////////////////
function send(int $code, $msg = "", bool $headerJson = true, bool $printresponse = true, bool $raw = false,bool $withMessage=true)
{
    if ($headerJson) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($code);
    GlobalLogger::$logger->info("http " . $code);
    $response = "";
    if (!empty($msg)) {

        if ($raw) {
            $output = $msg;
        } else {
            if($withMessage) {
                $response = array(
                    "message" => $msg
                );
            }else{
                $response = $msg;
            }
            $output = json_encode($response, JSON_UNESCAPED_SLASHES);
        }
        if ($printresponse) {
            GlobalLogger::$logger->info($response);
        }

        // compress support
        if(isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            $supportsGzip = str_contains($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
        }
        else {
            $supportsGzip=false;
        }
        if ($supportsGzip) {
            $content = gzencode(trim(preg_replace('/\s+/', ' ', $output)), 9);
            header('Content-Encoding: gzip');
            echo $content;
        } else {
            echo $output;
        }
    }
}