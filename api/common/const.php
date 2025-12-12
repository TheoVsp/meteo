<?php

const SUPPORT = "Please contact DIF_INF_R2ID@ragt.fr";

// CSV delimiter for open without any action on Excel
const CSV_EXCEL_FR_DELIMETER = ";";
const CSV_DELIMETER = ",";

define("BOM", chr(0xEF) . chr(0xBB) . chr(0xBF));

// https://power.larc.nasa.gov/docs/services/api/
const NASA_LIMITE_RATE_COUNT = 60;
const NASA_LIMITE_RATE_TIME = 60;

//https://docs.meteoblue.com/en/weather-apis/packages-api/introduction
const METEOBLUE_LIMITE_RATE_COUNT = 500;
const METEOBLUE_LIMITE_RATE_TIME = 60;

const FORMAT_DATE_YEAR = "Y";
const FORMAT_DATE_MONTH = "m";
const FORMAT_DATE_DAY = "d";
const FORMAT_DATE_DAY_OF_YEAR = "z";
const FORMAT_DATE_EN = "Ymd";
const FORMAT_DATE_METEOBLUE_EN = "Y-m-d";
const FORMAT_DATE_POSTGRESDB_WITH_S = "Y-m-d H:i:s";
const FORMAT_DATE_POSTGRESDB_WITH_US = "Y-m-d H:i:s.u";
const FORMAT_DATE_POSTGRESDB_WITH_MS = "Y-m-d H:i:s.v";

const FORMAT="format";
const JSON="json";
const CSV="csv";

const CALCULATED_ADJUSTED= "CALCULATED_ADJUSTED";
const ADJUSTED="ADJUSTED";
const CALCULATED_PREDICTED= "CALCULATED_PREDICTED";
const PREDICTED="PREDICTED";

const NOT_IMPLEMENTED="Not implemented";