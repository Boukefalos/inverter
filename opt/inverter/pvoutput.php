#!/usr/bin/php
<?php
require_once 'functions.php';
require_once 'wunderground.php';

define('STATION', 'INOORDHO4');
define('RRD_FILE', 'data/inverter_%s_today.rrd');
define('RRD_FETCH', 'rrdtool fetch %s AVERAGE -r %d -s %d -e %d');
define('PVOUTPUT_URL', 'http://pvoutput.org/service/r1/addstatus.jsp');
define('TODAY_FILE', 'data/today_%s.csv');
define('FIELD', 'PAC');
define('RESOLUTION', 5);
define('MARGIN_ENERGY', 0.5);
define('MARGIN_TEMPERATURE', 0.4);
$aSystems = array(
    '1204DQ0116' => array('16e7a916d69656e354d00461a4da1d2e40cfa4f1', '12419')
);

/* Fake command line for debugging */
//$argv = array(null, 1.5, 1234, 230, '1204DQ0116');

/* Fetch command line arguments */
$fToday = floatval($argv[1]);   // Wh
$fPower = floatval($argv[2]);   // W
$fVoltage = floatval($argv[3]); // V
$sSerial = $argv[4];

/* Fetch temperature */
$aData = wunderground('conditions', sprintf('pws:%s', STATION));
$fTemperature = isset($aData['current_observation']['temp_c']) ? $aData['current_observation']['temp_c'] : null;

/* Fetch twilight data */
$iDay = date('z');
$aTwilight = getTwilight(date('Y'), $iDay);

/* Fetch today data */
$sTodayFile = sprintf(TODAY_FILE, $sSerial);
$aToday = array();
if (file_exists($sTodayFile)) {
    $aToday = explode(',', file_get_contents($sTodayFile));
    $aToday[1] = floatval($aToday[1]);
}
if (count($aToday) != 3 || $aToday[0] != $iDay) {
    $aToday = array($iDay, 0, strtotime($aTwilight[1]), null);
}
$iLast = $aToday[2];

/* Extract fields */
$iTime = time();
$sData = command(sprintf(RRD_FETCH, sprintf(RRD_FILE, $sSerial), RESOLUTION, $iLast, $iTime));
$aData = explode("\n", trim($sData));
$aFields = preg_split("~[\s]+~", array_shift($aData));
array_shift($aData);
$aFields = array_flip($aFields);

/* Process data */
$bFirst = true;
$fEnergy = 0;
if (isset($aFields[FIELD])) {
    $iField = $aFields[FIELD] + 1;
    array_shift($aData);
    foreach ($aData as $sRow) {
        $aRow = explode(' ', $sRow);
        $iDate = substr($aRow[0], 0, -1);
        $iInterval = $bFirst ? (($bFirst = false) || RESOLUTION) : $iDate - $iLast; // s
        if (($fValue = floatval($aRow[$iField])) > 0) { // W
            $fEnergy += $iInterval * $fValue; // Ws
        }
        $iLast = $iDate;
    }
}

/* Store today data */
$aToday[1] += $fEnergy / 3600; // Wh
$aToday[2] = $iTime;
$aToday[3] = $fTemperature;
file_put_contents($sTodayFile, implode(',', $aToday));

/* Correct today data */
$fToday = $fToday > ((1 + MARGIN_ENERGY) * $aToday[1]) ? $aToday[1] : $fToday;

/* Construct PVOutput data */
$aData = array(
    'd' => date('Ymd', $iTime),
    't' => date('H:i', $iTime),
    'v1' => $fToday,    // Wh
    'v2' => $fPower,    // W
    'v6' => $fVoltage); // V

/* Add (corrected) temperature when available */
if (isset($fTemperature)) {
    if (isset($aToday[3])) {
        $fTemperature = abs($aToday[3] - $fTemperature) > (MARGIN_TEMPERATURE * $aToday[3]) ? $aToday[3] : $fTemperature;
    }
    $aData['v5'] = $fTemperature; // ignore potential flaws in first temperature of the day
}

/* Store debug data */
file_put_contents('pvoutput.debug', json_encode(array($argv, $fEnergy, $aToday, $aData)) . "\n", FILE_APPEND);

/* Send data to PVOutput */
if (isset($aSystems[$sSerial])) {
    $rCurl = curl_init();
    curl_setopt_array($rCurl, array(
        CURLOPT_URL => PVOUTPUT_URL,
        CURLOPT_HTTPHEADER => array(
            sprintf('X-Pvoutput-Apikey: %s', $aSystems[$sSerial][0]),
            sprintf('X-Pvoutput-SystemId: %s',  $aSystems[$sSerial][1])),
        CURLOPT_POSTFIELDS => http_build_query($aData),
            CURLOPT_RETURNTRANSFER => true));
    $sResult = curl_exec($rCurl);
}