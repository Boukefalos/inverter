#!/usr/bin/php
<?php
require_once 'wunderground.php';

define('STATION', 'INOORDHO104');
$aData = wunderground('conditions', sprintf('pws:%s', STATION));
echo $fTemperature = isset($aData['current_observation']['temp_c']) ? $aData['current_observation']['temp_c'] : null;