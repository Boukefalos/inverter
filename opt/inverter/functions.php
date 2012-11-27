<?php
define('TWILIGHT_FILE', 'data/twilight_%d.csv');

function getHour($sTime = null) {
    $iTime = $sTime === null ? time() : strtotime($sTime);
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

function command($sCommand) {
    ob_start();
    system($sCommand);
    return ob_get_clean();
}

function clean() {
    clearstatcache();
    gc_collect_cycles();
}