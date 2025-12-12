<?php

class Rws extends Provider
{
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PUBLIC SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function supportParameter($parameter): bool
    {
        switch ($parameter) {
            case GlobalParameters::GDD_6_30:
            case GlobalParameters::GDD_0_30:
            case GlobalParameters::GDD_6_34:
                return true;
            default:
                return false;
        }
    }
    public static function canProvidesOneData(array $missingDates): bool
    {
        return true;
    }

    public function getData(WeatherParameters $weatherParameters, WeatherData $weatherData, &$error_msg): bool
    {
        GlobalLogger::$logger->info("[RWS] [STAT] getData");

        /// PARAMETER
        $parameter = $weatherParameters->getParameter(WeatherParameters::PARAMETER);
        if(!Rws::supportParameter($parameter)){
            $error_msg="Can't support parameter " . $parameter;
            GlobalLogger::$logger->error($error_msg);
            return false;
        }

        // Prepare data
        if($parameter == GlobalParameters::GDD_6_30){
            $vege_min=6;
            $vege_max=30;
        }elseif ($parameter == GlobalParameters::GDD_6_34){
            $vege_min=6;
            $vege_max=34;
        }elseif ($parameter == GlobalParameters::GDD_0_30){
            $vege_min=0;
            $vege_max=30;
        }else{
            $error_msg = "Parameter not implemented. We can't compute GDD";
            return false;
        }

        // get data
        $serviceProvider = new ServiceProvider();

        /// Minimal temperature
        $weatherParameters_temp_min= clone $weatherParameters;
        $weatherParameters_temp_min->setParameter(WeatherParameters::PARAMETER, GlobalParameters::TEMPMIN);
        $weatherData_temp_min=new WeatherData();
        if(!$serviceProvider->getData($weatherParameters_temp_min,$weatherData_temp_min,$error_msg)){
            return false;
        }

        // TODO REWORK
        $weatherData->addMetadata($weatherData_temp_min->getMetadata(),get_class($this), "TEMPMIN");

        /// Maximal temperature
        $weatherParameters_temp_max= clone $weatherParameters;
        $weatherParameters_temp_max->setParameter(WeatherParameters::PARAMETER, GlobalParameters::TEMPMAX);
        $weatherData_temp_max=new WeatherData();
        if(!$serviceProvider->getData($weatherParameters_temp_max,$weatherData_temp_max,$error_msg)){
            return false;
        }
        // TODO REWORK
        $weatherData->addMetadata($weatherData_temp_max->getMetadata(),get_class($this), "TEMPMAX");

        // compute
        $datePeriodes = $weatherParameters->getParameter(WeatherParameters::DATESPERIODE);
        $dates_not_found=0;
        $cpt_min_not_found=0;
        $cpt_max_not_found=0;
        $cpt_compute_error=0;
        $nbdates=0;

        foreach ($datePeriodes as $date) {
            $nbdates++;
            $formateddate = $date->format(FORMAT_DATE_EN);
            if(array_key_exists($formateddate,$weatherData_temp_min->getData())){
                if(array_key_exists($formateddate,$weatherData_temp_max->getData())){

                    if(self::computeGDD($weatherData_temp_min->getData()[$formateddate],$weatherData_temp_max->getData()[$formateddate],$vege_min,$vege_max,$value)){
                        $weatherData->addData($value, $formateddate);
                    }else{
                        $cpt_compute_error++;
                    }
                }else{
                    $cpt_max_not_found++;
                }
            }else{
                $cpt_min_not_found++;
            }
        }

        // stat
        $count_dates_found=count($weatherData->getData());
        GlobalLogger::$logger->info("[RWS] [STAT] nb date : $nbdates");
        GlobalLogger::$logger->info("[RWS] [STAT] date found : $count_dates_found");
        GlobalLogger::$logger->info("[RWS] [STAT] [ERROR] max not found: $cpt_max_not_found");
        GlobalLogger::$logger->info("[RWS] [STAT] [ERROR] min not found: $cpt_min_not_found");
        GlobalLogger::$logger->info("[RWS] [STAT] [ERROR] compute error: $cpt_compute_error");
        if ($count_dates_found === 0) {
            GlobalLogger::$logger->debug("[RWS] no result");
            return false;
        }

        if($count_dates_found === $nbdates) {
            $weatherData->addMetadata(WeatherData::DATA_COMPLETE,get_class($this), WeatherData::METADATA_CONTENTS);
        } else{
            $weatherData->addMetadata(WeatherData::DATA_PARTIAL,get_class($this), WeatherData::METADATA_CONTENTS);
        }
        return true;
    }

    public function executeDaily(&$error_msg): bool
    {
        $error_msg = NOT_IMPLEMENTED;
        return false;
    }

    public function executeRangeDaily(string $start_date, string $end_date, &$error_msg): bool
    {
        $error_msg = NOT_IMPLEMENTED;
        return false;
    }

    public function importInCache(string $start_date, string $end_date, Location $location, string $parameter_name, &$error_msg): bool
    {
        $error_msg = NOT_IMPLEMENTED;
        return false;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /// PRIVATE SECTION
    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Function to calculate Degres jour base minbase-maxbase
     * tmin temperature min in celcius
     * tmax temperature max un celcius
     * return number return temperature in base minbase-maxbase
     */
    public static function computeGDD(int $tmin, int $tmax, int $minbase, int $maxbase,&$output): bool
    {
        // checks
        if($tmin > $tmax){
            GlobalLogger::$logger->error("Tmin ($tmin) > Tmax ($tmax)");
            return false;
        }
        if($minbase > $maxbase){
            GlobalLogger::$logger->error("minbase ($minbase) > maxbase ($maxbase)");
            return false;
        }

        // compute section
        if($tmax >= $maxbase) {
            $output= round((($tmax+$tmin)/2)-$minbase-($tmax-$maxbase),2);
        }elseif ((($tmax+$tmin)/2) <= $minbase){
            $output= 0;
        }else{
            $output= round(((($tmax+$tmin)/2)-$minbase),2);
        }
        return true;
    }
}