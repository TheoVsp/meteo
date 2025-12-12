<?php
require "vendor/autoload.php";

use R2n\R2nLogger;

/**
 * Store logger
 */
class GlobalLogger{
    public static R2nLogger $logger;
}

GlobalLogger::$logger = new R2nLogger(hash:$HASH,logFilename:GlobalEnv::$env_log_file ,level:GlobalEnv::$env_log_level);

GlobalLogger::$logger->info("*************************************************************************");
GlobalLogger::$logger->info("Start logging at level = [" . GlobalLogger::$logger->getLevel() . "]");