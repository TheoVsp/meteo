<?php

// send response
send(200,array(
    "message" => "Total execution time in miliseconds",
    "result" => round((microtime(true) - $timer_start) * 1000 )
));