#!/usr/bin/php
<?php
require_once 'functions.php';

define('RRD_FILE', 'data/inverter_%s_today.rrd');
define('RRD_FETCH', 'rrdtool fetch %s AVERAGE -r %d -s %d -e %d');
define('PVOUTPUT_URL', 'http://pvoutput.org/service/r1/addstatus.jsp');
define('TODAY_FILE', 'data/today_%s.csv');
define('FIELD', 'PAC');
define('RESOLUTION', 5);
define('MARGIN', 0.1);
$aSystems = array(
    '1204DQ0116' => array('16e7a916d69656e354d00461a4da1d2e40cfa4f1', '12419')
);

/* Fetch command line arguments */
$fToday = floatval($argv[1]);
$fPower = floatval($argv[2]);
$fVoltage = floatval($argv[3]);
$sSerial = $argv[4];

/* Fetch twilight data */
$iDay = date('z');
$aTwilight = getTwilight(date('Y'), $iDay);

/* Fetch today data */
$sTodayFile = sprintf(TODAY_FILE, $sSerial);
$aToday = array();
if (file_exists($sTodayFile)) {
    $aToday = explode(',', file_get_contents($sTodayFile));    
}
if (count($aToday) != 3 || $aToday[0] != $iDay) {
    $aToday = array($iDay, 0, strtotime($aTwilight[1]));
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
        $iInterval = $bFirst ? (($bFirst = false) || RESOLUTION) : $iDate - $iLast;
        if (($fValue = floatval($aRow[$iField])) > 0) {        
            $fEnergy += $iInterval * $fValue;
        }
        $iLast = $iDate;
    }    
}

/* Store today data */
$aToday[1] += $fEnergy / 1000 / 3600;
$aToday[2] = $iTime;
file_put_contents($sTodayFile, implode(',', $aToday));

/* Test */
file_put_contents('test', sprintf("[%s],%d,%s,%f,%f,%f,%f\n", date('r'), time(), $sSerial, $fToday, $fPower, $aToday[1], $fVoltage), FILE_APPEND);

/* Correct today data */
$fToday = $aToday[1] > ((1 + MARGIN) * $fEnergy) ? $fEnergy : $aToday[1];

/* Send data to PVOutput */
if (isset($aSystems[$sSerial])) {
    $rCurl = curl_init();
    curl_setopt_array($rCurl, array(
        CURLOPT_URL => PVOUTPUT_URL,
        CURLOPT_HTTPHEADER => array(
            sprintf('X-Pvoutput-Apikey: %s', $aSystems[$sSerial][0]),
            sprintf('X-Pvoutput-SystemId: %s',  $aSystems[$sSerial][1])),
        CURLOPT_POSTFIELDS => http_build_query(array(
            'd' => date('Ymd', $iTime),
            't' => date('H:i', $iTime),
            'v1' => 1000 * $fToday, // Wh
            'v2' => $fPower,
            'v6' => $fVoltage)),
            CURLOPT_RETURNTRANSFER => true));
    $sResult = curl_exec($rCurl);
    file_put_contents('pvtest', sprintf("[%s] %s\n", date('r'), $sResult), FILE_APPEND);
}