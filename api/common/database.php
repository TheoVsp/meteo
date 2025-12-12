<?php
//////////////////////////////////////////////////////////////////
// DATABASE FUNCTIONS
//////////////////////////////////////////////////////////////////
require "vendor/autoload.php";

use R2n\R2nPgDatabase;

class GlobalDatabase{
    public static R2nPgDatabase $database;
}

GlobalDatabase::$database = new R2nPgDatabase(GlobalLogger::$logger,
    GlobalEnv::$env_db_host,
    GlobalEnv::$env_db_port,
    GlobalEnv::$env_db_name,
    GlobalEnv::$env_db_username,
    GlobalEnv::$env_db_password,
    GlobalEnv::$env_db_schema);