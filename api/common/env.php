<?php

/**
 * Store env variables
 */
class GlobalEnv{

    public static string $env_db_host;
    public static string $env_db_port;
    public static string $env_db_name;
    public static string $env_db_schema;
    public static string $env_db_username;
    public static string $env_db_password;
    
    public static string $env_log_file;
    public static string $env_log_level;

    public static string $env_db_log_file;
    public static string $env_db_log_level;
  
    public static string $env_ecmwf_ram_dir;
    public static string $env_ecmwf_persistence_dir;
    public static string $env_ecmwf_default_latency;
    public static string $env_ecmwf_min_year;

    public static string $env_meteoblue_host;
    public static string $env_meteoblue_api_key;
    public static string $env_meteoblue_max_locations;
    public static string $env_meteoblue_max_number_year_history;
    public static string $env_meteoblue_packages_forecast;
    public static string $env_meteoblue_packages_histo;
    public static string $env_meteoblue_default_latency;
    public static string $env_meteoblue_min_year;
    public static string $env_meteoblue_secret_location_creation;

    public static string $env_nasa_max_retry;
    public static string $env_nasa_retry_time;
    public static string $env_nasa_solar_latency_ceres_syn;
    public static string $env_nasa_solar_latency_nrt;
    public static string $env_nasa_meteo_latency_nrt;
    public static string $env_nasa_default_latency_nrt;

    public static string $env_rweather_mode_locale_input;
    public static string $env_rweather_mode_locale_output;

    public static string $env_mail_smtp_host;
    public static string $env_mail_smtp_port;
    public static string $env_mail_admins;
}

/**
 * Get environment variable
 * @param string $envvar name of environement variable
 * @param string $default default value if not found
 * @return string return value
 */
function getEnvi(string $envvar,string $default): string
{
    // get env var
	if($var = getenv($envvar)){
	    // found 
	    // trim it
		return trim($var, '"');
	}

    // return default
    return $default;
}

////////////////////////////
// Environment variables
////////////////////////////

// DB
GlobalEnv::$env_db_host=getEnvi('DB_HOST',"");
GlobalEnv::$env_db_port=getEnvi('DB_PORT',"");
GlobalEnv::$env_db_name=getEnvi('DB_NAME',"");
GlobalEnv::$env_db_schema=getEnvi('DB_SCHEMA',"");
GlobalEnv::$env_db_username=getEnvi('DB_USERNAME',"");
GlobalEnv::$env_db_password=getEnvi('DB_PASSWORD',"");

// LOG
GlobalEnv::$env_log_file=getEnvi('LOG_FILE',"/var/www/html/logs/log.txt");
GlobalEnv::$env_log_level=getEnvi('LOG_LEVEL',"INFO");
// ECMWF
GlobalEnv::$env_ecmwf_ram_dir=getEnvi('ECMWF_RAM_DIR',"");
GlobalEnv::$env_ecmwf_persistence_dir=getEnvi('ECMWF_PERSISTENCE_DIR',"");
GlobalEnv::$env_ecmwf_default_latency=getEnvi('ECMWF_DEFAULT_LATENCY',"9");
GlobalEnv::$env_ecmwf_min_year=getEnvi('ECMWF_MIN_YEAR',"2016");

// METEOBLUE
GlobalEnv::$env_meteoblue_host=getEnvi('METEOBLUE_HOST',"");
GlobalEnv::$env_meteoblue_api_key=getEnvi('METEOBLUE_API_KEY',"");
GlobalEnv::$env_meteoblue_max_locations=getEnvi('METEOBLUE_MAX_LOCATIONS',"0");
GlobalEnv::$env_meteoblue_max_number_year_history=getEnvi('METEOBLUE_MAX_YEAR',"4"); // 5 - current
GlobalEnv::$env_meteoblue_packages_forecast=getEnvi('METEOBLUE_PACKAGES_FORECAST',"agro-day_basic-day_solar-day");
GlobalEnv::$env_meteoblue_packages_histo=getEnvi('METEOBLUE_PACKAGES_HISTO',"historyagro-day_historybasic-day_historysolar-day");
GlobalEnv::$env_meteoblue_default_latency=getEnvi('METEOBLUE_DEFAULT_LATENCY',"-7");
GlobalEnv::$env_meteoblue_min_year=getEnvi('METEOBLUE_MIN_YEAR',"2018");
GlobalEnv::$env_meteoblue_secret_location_creation=getEnvi('METEOBLUE_SECRET_LOCATION_CREATION',"");

// NASA
GlobalEnv::$env_nasa_max_retry=getEnvi('NASA_MAX_RETRY',"60");
GlobalEnv::$env_nasa_retry_time=getEnvi('NASA_RETRY_TIME',"3");
GlobalEnv::$env_nasa_solar_latency_ceres_syn=getEnvi('NASA_SOLAR_LATENCY_CERES_SYN',"120");
GlobalEnv::$env_nasa_solar_latency_nrt=getEnvi('NASA_SOLAR_LATENCY_NRT',"8");
GlobalEnv::$env_nasa_meteo_latency_nrt=getEnvi('NASA_METEO_LATENCY_NRT',"3");
GlobalEnv::$env_nasa_default_latency_nrt=getEnvi('NASA_DEFAULT_LATENCY_NRT',"8");

// RWEATHER
GlobalEnv::$env_rweather_mode_locale_input=getEnvi('RWEATHER_MODE_LOCALE_INPUT',"/var/www/html/common/api/providers/rweather/scripts/tmp/input");
GlobalEnv::$env_rweather_mode_locale_output=getEnvi('RWEATHER_MODE_LOCALE_OUTPUT',"/var/www/html/common/api/providers/rweather/scripts/tmp/output");

// MAIL
GlobalEnv::$env_mail_smtp_host=getEnvi('MAIL_SMTP_HOST',"");
GlobalEnv::$env_mail_smtp_port=getEnvi('MAIL_PORT',"25");
GlobalEnv::$env_mail_admins=getEnvi('MAIL_ADMINS',"");
