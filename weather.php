#!/usr/bin/php
<?php
// require_once 'wunderground.php';
require_once 'openweathermap.php';

// define('STATION', 'INHASSUM4');
define('CITY', 2745978);

// $aData = wunderground('conditions', sprintf('pws:%s', STATION));
$aData = openweathermap(CITY);
echo $fTemperature = isset($aData['main']['temp']) ? floatval($aData['main']['temp']) - 273.15 : null;
