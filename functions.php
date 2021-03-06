<?php
// require_once 'wunderground.php';
require_once 'openweathermap.php';

define('DEFAULT_WAKE', '6:00');
define('DEFAULT_SLEEP', '22:00');

define('TWILIGHT_FILE', 'static/twilight_%d.csv');
define('STATION', 'INOORDHO104');
define('CITY', 2745978);

function getHour($sTime = null) {
    if (!is_numeric($sTime)) {
        $iTime = !isset($sTime) ? time() : strtotime($sTime);
    } else {
        $iTime = $sTime;
    }
    return date('H', $iTime) + date('i', $iTime) / 60;     
}

function getTwilight($iYear, $iDay) {
    $sTwilightFile = sprintf(TWILIGHT_FILE, $iYear);
    if (file_exists($sTwilightFile)) {
        $aDays = explode("\n", file_get_contents($sTwilightFile));
        if (isset($aDays[$iDay])) {
            $aDay = explode(',', $aDays[$iDay]);
            if ($aDay[0] == $iDay) {
                return $aDay;
            }
        }
    }
    return null;
}

function getWake(&$aTwilight = null) {
    if (!isset($aTwilight)) {
        $aTwilight = getTwilight(date('Y'), date('z') + 1);
    }
    return strtotime($sWake = isset($aTwilight) ? $aTwilight[1] : DEFAULT_WAKE);
}

function getSleep(&$aTwilight = null) {
    if (!isset($aTwilight)) {
        $aTwilight = getTwilight(date('Y'), date('z'));
    }
    return strtotime($sWake = isset($aTwilight) ? $aTwilight[3] : DEFAULT_WAKE);
}

function getTemperature($sStation = STATION, $iCity = CITY) {
    // $aData = wunderground('conditions', sprintf('pws:%s', STATION));
    // return isset($aData['current_observation']['temp_c']) ? $aData['current_observation']['temp_c'] : null;
    $aData = openweathermap(CITY);
    return isset($aData['main']['temp']) ? floatval($aData['main']['temp']) - 273.15 : null;
}

function command($sCommand) {
    ob_start();
    system($sCommand);
    return ob_get_clean();
}

function clean() {
    clearstatcache();
    gc_collect_cycles();
}
