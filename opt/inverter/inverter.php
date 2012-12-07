#!/usr/bin/php
<?php
require_once 'functions.php';
require_once 'System/Daemon.php'; // pear install -f System_Daemon

define('NAME', 'inverter');
define('TASK', '/opt/inverter/inverter.pl > /dev/null');
define('CWD', '/opt/inverter/');
define('MODE', 0755);
define('FILE_DAEMON_START', 'daemon_start.sh');
define('FILE_DAEMON_STOP', 'daemon_stop.sh');
define('DEFAULT_WAKE', '7:00');
define('DEFAULT_SLEEP', '19:00');
chdir(CWD);

/* Remove previous at entries */
foreach (explode("\n", trim(command('atq 2> /dev/null'))) as $sJob) {
    $sId = substr($sJob, 0, strpos($sJob, "\t"));
    $sJob = command(sprintf('at -c %s 2> /dev/null' . "\n", $sId));
    $aJob = explode("\n", trim(command(sprintf('at -c %s 2> /dev/null', $sId))));
    if (strpos(array_pop($aJob), NAME) !== false) {
        command(sprintf('atrm %s', $sId));
    }
}

/* Inverter daemon */
System_Daemon::setOptions(array(
    'appName' => NAME,
    'appDescription' => '',
    'authorName' => '',
    'authorEmail' => ''));

/* Install service */
if (isset($argv[1]) && $argv[1] == 'install') {
    System_Daemon::writeAutoRun(); // update-rc.d %s defaults

    /* Write scripts for scheduling with at */
    if (!file_exists(FILE_DAEMON_START)) {
        file_put_contents(FILE_DAEMON_START, sprintf("#!/bin/bash\nservice %s start", NAME));
        chmod(FILE_DAEMON_START, MODE);
    }
    if (!file_exists(FILE_DAEMON_STOP)) {
        file_put_contents(FILE_DAEMON_STOP, sprintf("#!/bin/bash\nservice %s stop", NAME));
        chmod(FILE_DAEMON_STOP, MODE);
    }
    exit;
}

/* Wake at sunrise, sleep at sunset */
$aTwilight = getTwilight(date('Y'), date('z'));
$fWake = getHour($sWake = isset($aTwilight) ? $aTwilight[1] : DEFAULT_WAKE);
$fSleep = getHour($sSleep = isset($aTwilight) ? $aTwilight[3] : DEFAULT_SLEEP);
System_Daemon::info(sprintf('Be awake between %s and %s', $sWake, $sSleep));

/* Start deamon */
System_Daemon::start();
$bStop = $bAlarm = false;

while (!$bStop && !System_Daemon::isDying()) {
    /* Check for current need to be awake */
    $fNow = getHour();
    if (!($bAwake = $fNow >= $fWake)) {
        System_Daemon::info('Too early to wake!');
    } else if ($bSleep = $fNow >= $fSleep) {
        System_Daemon::info('Time to sleep!');
    }

    if ($bAwake && !$bSleep) {
        /* Schedule next sleep time */
        /*if (!$bAlarm) {
            $sTime = date('H:i', strtotime($sSleep));
            System_Daemon::info(sprintf('Schedule sleep at %s', $sTime));
            command(sprintf('at -f %s %s 2> /dev/null', FILE_DAEMON_STOP, $sTime));
            $bAlarm = true;
        }*/

        /* Execute task */
        System_Daemon::info('Running task');
        command(TASK);
        System_Daemon::info('Task ended');
    } else  {
        $bStop = true;
    }
}

/* Schedule next wake time */
schedule_wake();

/* Stop daemon */
System_Daemon::stop();

function schedule_wake() {
    global $sWake;
    $sTime = date('H:i', strtotime($sWake)); // ignore slight deviation for next day
    System_Daemon::info(sprintf('Waiting untill %s', $sTime));
    command(sprintf('at -f %s %s 2> /dev/null', FILE_DAEMON_START, $sTime));
}
