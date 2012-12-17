#!/usr/bin/php
<?php
require_once 'functions.php';
require_once 'daemon.php';

define('NAME', 'inverter');
define('TASK', '/opt/inverter/inverter.pl > /dev/null');
define('CWD', '/opt/inverter/');
define('DEFAULT_WAKE', '7:00');
define('DEFAULT_SLEEP', '19:00');

/* Initialize */
chdir(CWD);
daemon_init();

/* Remove previous at entries */
foreach (explode("\n", trim(command('atq 2> /dev/null'))) as $sJob) {
    $sId = substr($sJob, 0, strpos($sJob, "\t"));
    $sJob = command(sprintf('at -c %s 2> /dev/null' . "\n", $sId));
    $aJob = explode("\n", trim(command(sprintf('at -c %s 2> /dev/null', $sId))));
    if (strpos(array_pop($aJob), NAME) !== false) {
        command(sprintf('atrm %s', $sId));
    }
}

/* Wake at sunrise, sleep at sunset */
$aTwilight = getTwilight(date('Y'), date('z'));
$fWake = getHour($sWake = isset($aTwilight) ? $aTwilight[1] : DEFAULT_WAKE);
$fSleep = getHour($sSleep = isset($aTwilight) ? $aTwilight[3] : DEFAULT_SLEEP);
System_Daemon::info(sprintf('Be awake between %s and %s', $sWake, $sSleep));

/* Check appropriate state */
$fNow = getHour();
if (!($bAwake = $fNow >= $fWake)) {
    System_Daemon::info('Too early to wake!');
} else if ($bSleep = $fNow >= $fSleep) {
    System_Daemon::info('Time to sleep!');
}
schedule_wake();

if ($bAwake && !$bSleep) {
    schedule_sleep();
    daemon_run();
}

function schedule_wake() {
    global $sWake;
    $sTime = date('H:i', strtotime($sWake)); // ignore slight deviation for next day
    System_Daemon::info(sprintf('Schedule wake at %s', $sTime));
    command(sprintf('at -f %s %s 2> /dev/null', FILE_DAEMON_START, $sTime));
}

function schedule_sleep() {
    global $sSleep;
    $sTime = date('H:i', strtotime($sSleep));
    System_Daemon::info(sprintf('Schedule sleep at %s', $sTime));
    command(sprintf('at -f %s %s 2> /dev/null', FILE_DAEMON_STOP, $sTime));
}