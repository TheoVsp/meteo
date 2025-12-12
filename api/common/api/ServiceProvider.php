<?php

class ServiceProvider
{
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PUBLIC SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function getData(WeatherParameters $weatherParameters, WeatherData $weatherData,&$error_msg): bool{

        GlobalLogger::$logger->debug("[SERVICE PROVIDER] Get data");

        $error_msg = array();
        if($this->controlParameters($weatherParameters,$error_msg)){
            GlobalLogger::$logger->error("[SERVICE PROVIDER] bad control parameters result");
            return false;
        }

        // prepare parameters
        $this->prepareParameters($weatherParameters);

        $isGlobalParameter = true;
        if(!GlobalParameters::isGlobalParameter($weatherParameters->getParameter(WeatherParameters::PARAMETER))){
            GlobalLogger::$logger->info("[SERVICE PROVIDER] Parameter ".$weatherParameters->getParameter(WeatherParameters::PARAMETER)." is not global parameter");
            $isGlobalParameter=false;
        }else{
            GlobalLogger::$logger->info("[SERVICE PROVIDER] Parameter ".$weatherParameters->getParameter(WeatherParameters::PARAMETER)." is global parameter");
        }

        //////////////////////////
        // DATES MODE SECTION
        // 2 modes : range or between 2 dates
        $is_range_mode = $weatherParameters->getParameter(WeatherParameters::DATERANGE);
        if($is_range_mode){
            // RANGE MODE
            $start_year = $weatherParameters->getParameter(WeatherParameters::STARTYEAR);
            $start_month = $weatherParameters->getParameter(WeatherParameters::STARTMONTH);
            $start_day = $weatherParameters->getParameter(WeatherParameters::STARTDAY);

            $end_year = $weatherParameters->getParameter(WeatherParameters::ENDYEAR);
            $end_month = $weatherParameters->getParameter(WeatherParameters::ENDMONTH);
            $end_day = $weatherParameters->getParameter(WeatherParameters::ENDDAY);

        }else{
            $datetime_start = DateTime::createFromFormat(FORMAT_DATE_EN,  $weatherParameters->getParameter(WeatherParameters::STARTDATE));
            $datetime_end = DateTime::createFromFormat(FORMAT_DATE_EN, $weatherParameters->getParameter(WeatherParameters::ENDDATE));

            $start_year = $datetime_start->format(FORMAT_DATE_YEAR);
            $end_year = $datetime_end->format(FORMAT_DATE_YEAR);
        }
        //////////////////////////

        //////////////////////////
        // PROVIDERS MODE SECTION
        if($weatherParameters->parameterExist(WeatherParameters::PROVIDER)){
            $multi_providers_mode = false;
            // create a std class
            $oneProvider = new stdClass();
            // add property nom with provider name
            $oneProvider->nom=$weatherParameters->getParameter(WeatherParameters::PROVIDER);
            // add into providers array
            $providers[]=$oneProvider;
        }else{
            $multi_providers_mode = true;
        }
        //////////////////////////

        // split by year
        for($year = $start_year; $year <= $end_year; $year++){

            // get list of providers dynamically for each year if multi provoders mode is enable
            if($multi_providers_mode){
                // search providers order for each year
                $providers = $this->listProvidersByPriority($weatherParameters->getParameter(WeatherParameters::PARAMETER),$year);
                GlobalLogger::$logger->debug("[SERVICE PROVIDER] Providers order for $year=".print_r($providers,true));
            }

            // clone parameter for change date later
            $weatherParameterstmp = clone $weatherParameters;

            if ($is_range_mode) {
                // RANGE MODE
                // modify startdate and endadate with current year
                $weatherParameterstmp->setParameter(WeatherParameters::STARTDATE, $year . $start_month . $start_day);
                $weatherParameterstmp->setParameter(WeatherParameters::ENDDATE, $year . $end_month . $end_day);
                $weatherParameterstmp->removeParameter(WeatherParameters::STARTDAY);
                $weatherParameterstmp->removeParameter(WeatherParameters::ENDDAY);
                $weatherParameterstmp->removeParameter(WeatherParameters::STARTMONTH);
                $weatherParameterstmp->removeParameter(WeatherParameters::ENDMONTH);
                $weatherParameterstmp->removeParameter(WeatherParameters::STARTYEAR);
                $weatherParameterstmp->removeParameter(WeatherParameters::ENDYEAR);
            } else {
                // if first year start with good day and month
                if ($year == $start_year) {
                    $start_month = $datetime_start->format("m");
                    $start_day = $datetime_start->format("d");
                } else {
                    // otherwise 1 january
                    $start_month = "01";
                    $start_day = "01";
                }

                // if end year finish with good day and month
                if ($year == $end_year) {
                    $end_month = $datetime_end->format("m");
                    $end_day = $datetime_end->format("d");
                } else {
                    // otherwise 31 december
                    $end_month = "12";
                    $end_day = "31";
                }
                // modify startdate and endadate with current year
                $weatherParameterstmp->setParameter(WeatherParameters::STARTDATE, $year . $start_month . $start_day);
                $weatherParameterstmp->setParameter(WeatherParameters::ENDDATE, $year . $end_month . $end_day);
            }
            GlobalLogger::$logger->debug("Startdate =" . $weatherParameterstmp->getParameter(WeatherParameters::STARTDATE));
            GlobalLogger::$logger->debug("Enddate =" . $weatherParameterstmp->getParameter(WeatherParameters::ENDDATE));

            // create all dates from start_date to enddate
            $datetime_start_tmp = DateTime::createFromFormat(FORMAT_DATE_EN, $weatherParameterstmp->getParameter(WeatherParameters::STARTDATE));
            $datetime_end_tmp = DateTime::createFromFormat(FORMAT_DATE_EN, $weatherParameterstmp->getParameter(WeatherParameters::ENDDATE))->add(new DateInterval('P1D'));
            $inter = new DateInterval('P1D');
            $weatherParameterstmp->setParameter(WeatherParameters::DATESPERIODE,new DatePeriod($datetime_start_tmp, $inter, $datetime_end_tmp));
            GlobalLogger::$logger->info("Created dates");

            // for each providers
            $missing_dates=null;
            foreach ($providers as $provider){

                // get name
                $provider_name = $provider->nom;
                GlobalLogger::$logger->info("[SERVICE PROVIDER] Provider $provider_name");

                // test if class name exists
                if(class_exists($provider_name)) {

                    // if is global paramter bt not supported
                    if($isGlobalParameter && !$provider_name::supportParameter($weatherParameterstmp->getParameter(WeatherParameters::PARAMETER))){
                        GlobalLogger::$logger->info("[SERVICE PROVIDER] Provider $provider_name not support global parameter");
                        continue;
                    }

                    // Hack
                    if($missing_dates != null && !$provider_name::canProvidesOneData($missing_dates)){
                        GlobalLogger::$logger->info("[SERVICE PROVIDER] Provider $provider_name can't provides one data");
                        continue;
                    }

                    // call provider constructor
                    $provider_class = new $provider_name();

                    // create temporary weather data
                    $datatmp = new WeatherData();

                    // get data from provider
                    $error_msg = "";
                    if (!$provider_class->getData($weatherParameterstmp, $datatmp, $error_msg)) {
                        // if error
                        GlobalLogger::$logger->error("[SERVICE PROVIDER] Provider $provider_name can't get any data ($error_msg)");

                    } else {

                        $all_data_found = $this->completeData($weatherParameterstmp,$datatmp,$weatherData,$provider_found_one_data,$missing_dates);

                        // add metadata if provider add one data
                        if($provider_found_one_data){

                            // add provider metadata for year
                            $weatherData->addMetadata($datatmp->getMetadataWithKeys(get_class($provider_class), WeatherData::METADATA_CONTENTS),
                                WeatherData::METADATA_OUTPUT,
                                (string)$year,
                                get_class($provider_class));

                            // remove from provider metadata because no sence in multiyears search
                            $datatmp->removeMetadataWithKeys(get_class($provider_class), WeatherData::METADATA_CONTENTS);

                            // add provider metadata
                            $weatherData->addMetadata($datatmp->getMetadata());
                        }

                        if($all_data_found){
                            break;
                        }

                        // continue to search
                    }
                }
            }
        }

        return true;
    }

    public static function listProviders(): array{
        $request ="SELECT *  FROM ".GlobalEnv::$env_db_schema.".providers p ORDER BY p.nom ASC ";
        return GlobalDatabase::$database->list($request);
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PRIVATE SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function completeData(WeatherParameters $weatherParameters , WeatherData $subset , WeatherData $data, &$provider_found_one_data, &$missing_dates):bool
    {
        $datePeriodes = $weatherParameters->getParameter(WeatherParameters::DATESPERIODE);
        $nbdates=0;
        $nbfound=0;
        $nb_found_added=0;
        $missing_dates=array();
        $provider_found_one_data=false;
        foreach ($datePeriodes as $date){
            $nbdates++;
            $formateddate = $date->format(FORMAT_DATE_EN);
            if(!array_key_exists($formateddate,$data->getData())){
                if(array_key_exists($formateddate,$subset->getData())){
                    $s = $subset->getData();
                    $data->addData($s[$formateddate],$formateddate);
                    $nb_found_added++;
                    $nbfound++;
                    $provider_found_one_data=true;
                }else{
                    // not found
                    $missing_dates[]=$formateddate;
                }
            }else{
                $nbfound++;
            }
        }
        GlobalLogger::$logger->debug("[SERVICE PROVIDER] Total dates=$nbdates");
        GlobalLogger::$logger->debug("[SERVICE PROVIDER] Total found=$nbfound");
        GlobalLogger::$logger->debug("[SERVICE PROVIDER] Total found added=$nb_found_added");
        if($nbdates == $nbfound) {
            return true;
        }
        else {
            return false;
        }
    }

    private function controlParameters(WeatherParameters $weatherParameters, array &$errormsg): bool{

        // init error message
        // get all error not only first error
        $errormsg = array();

        ///////////////////////
        /// DATES SELECTION SECTION
        ///////////////////////
        $dateok=true;
        if(!($weatherParameters->parameterExistAndIsDate(WeatherParameters::STARTDATE)
            && $weatherParameters->parameterExistAndIsDate(WeatherParameters::ENDDATE)
        )){
            $dateok=false;
        }

        if(!($weatherParameters->parameterExistAndIsInt(WeatherParameters::STARTDAY)
            && $weatherParameters->parameterExistAndIsInt(WeatherParameters::ENDDAY)
            && $weatherParameters->parameterExistAndIsInt(WeatherParameters::STARTMONTH)
            && $weatherParameters->parameterExistAndIsInt(WeatherParameters::ENDMONTH)
            && $weatherParameters->parameterExistAndIsInt(WeatherParameters::STARTYEAR)
            && $weatherParameters->parameterExistAndIsInt(WeatherParameters::ENDYEAR)
        )){
            if(!$dateok){
                $errormsg[] = "Check 'startday', 'endday',  'startmonth', 'endmonth','startyear', 'endyear' parameters. Format : integer eg 10";
            }else{
                $weatherParameters->addParameter(WeatherParameters::DATERANGE,false);
            }
        }else if($dateok){
            $errormsg[] = "Only one way to select date : startdate/enddate or startday/endday/startmonth/endmonth/startyear/endyear";
        }else{
            // flush old erro
            $errormsg = array();
            $weatherParameters->addParameter(WeatherParameters::DATERANGE,true);
        }

        ///////////////////////
        /// FORMAT DATES CHECK SECTION
        ///////////////////////
        if($weatherParameters->getParameter(WeatherParameters::DATERANGE)){

        }else{
            if(false === DateTime::createFromFormat(FORMAT_DATE_EN,$weatherParameters->getParameter(WeatherParameters::STARTDATE))){
                $errormsg[] = "Bad date format for " . WeatherParameters::STARTDATE ." (format: YYYYMMDD eg 20210315)";
            }
            if(false === DateTime::createFromFormat(FORMAT_DATE_EN,$weatherParameters->getParameter(WeatherParameters::ENDDATE))){
                $errormsg[] = "Bad date format for " . WeatherParameters::ENDDATE ." (format: YYYYMMDD eg 20210315)";
            }
        }

        ///////////////////////
        /// PARAMETER SECTION
        ///////////////////////
        if(!$weatherParameters->parameterExist(WeatherParameters::PARAMETER)){
            $errormsg[] = "Need 'parameter' parameter";
        }

        ///////////////////////
        /// LOCATION SECTION
        ///////////////////////
        if(!$weatherParameters->parameterExistAndIsNumeric(WeatherParameters::LATITUDE)){
            $errormsg[]  = "'latitude' parameter isn't a numeric";
        }
        if(!$weatherParameters->parameterExistAndIsNumeric(WeatherParameters::LONGITUDE)){
            $errormsg[]  = "'longitude' parameter isn't a numeric";
        }
        if(!$weatherParameters->parameterExistAndIsNumeric(WeatherParameters::ALTITUDE)){
            $errormsg[]  = "'altitude' parameter isn't a numeric";
        }

        if(!empty($errormsg)){
            GlobalLogger::$logger->error(print_r($errormsg,true));
        }

        return !empty($errormsg);
    }

    private function prepareParameters(WeatherParameters $weatherParameters): void
    {

        // create location object and put it into WeatherParameters
        $location = new Location($weatherParameters->getParameter("name"),$weatherParameters->getParameter("latitude"),
            $weatherParameters->getParameter("longitude"),$weatherParameters->getParameter("altitude"));

        // add location object to parameters
        $weatherParameters->addParameter("location",$location);
    }

    private function specificPriorityForParameter($parameter, $year): bool
    {
        $request ="SELECT count(DISTINCT parameter) FROM ".GlobalEnv::$env_db_schema.".priorities vpr WHERE vpr.parameter='$parameter' AND vpr.year='$year'";
        return GlobalDatabase::$database->get($request)->count >= 1;
    }

    private function listProvidersByPriority($parameter, $year){

        if($this->specificPriorityForParameter($parameter,$year)){
            // specific priority
            $request ="SELECT * FROM ".GlobalEnv::$env_db_schema.".v_priorities_providers vpr WHERE vpr.parameter='$parameter' and vpr.year='$year' ORDER BY vpr.priority ASC ";
        }else{
            // default priority
            $request ="SELECT *  FROM ".GlobalEnv::$env_db_schema.".providers p ORDER BY p.default_priority ASC ";

        }
        return GlobalDatabase::$database->list($request);
    }
}