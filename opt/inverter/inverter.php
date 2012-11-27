#!/usr/bin/php
<?php
$sWake = '7:30';
$sSleep = '17:30';
$sTask = '/opt/inverter/inverter.pl > /dev/null';

printf("Be awake between %s and %s\n", $sWake, $sSleep);
$fWake = getHour($sWake);
$fSleep = getHour($sSleep);
chdir('/opt/inverter/');

while (true) {
    // Check for current need to be awake
    $fNow = getHour();
    if (!($bAwake = $fNow >= $fWake)) {
        printf("[%s] Too early to wake!\n", date('r'));
    } else if ($bSleep = $fNow >= $fSleep) {
        printf("[%s] Time to sleep!\n", date('r'));
    }

    if ($bAwake && !$bSleep) {
        // Need to be awake now
        printf("[%s] Running task\n", date('r'));
        system($sTask);
        printf("[%s] Task ended\n", date('r'));
    } else {
        // Don't need to be awake now
        if (!$bAwake) {
            // Sleep untill wake time
            $iTime = strtotime($sWake);
        } else {
            // Sleep untill next day wake time
            $iTime = strtotime(sprintf('%s + 1 day', $sWake));
        }
        printf("[%s] Sleeping untill: %s\n", date('r'), date('r', $iTime));
        time_sleep_until($iTime);
    }
}
echo "\n";

function getHour($sTime = null) {
    $iTime = $sTime === null ? time() : strtotime($sTime);
    return date('H', $iTime) + date('i', $iTime) / 60;     
}
