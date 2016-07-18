<?php
require_once 'System/Daemon.php'; // pear install -f System_Daemon

define('NAME', 'inverter');
define('TASK', '/opt/inverter/inverter.pl > /dev/null');
define('CWD', '/opt/inverter/');
define('FILE_DAEMON_START', 'daemon_start.sh');
define('FILE_DAEMON_STOP', 'daemon_stop.sh');
define('MODE', 0755);
define('PROCESS_POLL', 30);

function daemon_init() {
    global $sName;

    /* Daemon options */
    System_Daemon::setOptions(array(
        'appName' => NAME,
        'appDescription' => '',
        'authorName' => '',
        'authorEmail' => ''));

    /* Derive process name */
    $sName = basename(substr(TASK, 0, strpos(TASK, ' ')));
}

function daemon_install() {
    global $argv;

    System_Daemon::writeAutoRun(); // update-rc.d %s defaults

    /* Write scripts for scheduling with at */
    if (isset($argv[2]) && $argv[2] == 'schedule') {
        if (!file_exists(FILE_DAEMON_START)) {
            file_put_contents(FILE_DAEMON_START, sprintf("#!/bin/bash\nservice %s start", NAME));
            chmod(FILE_DAEMON_START, MODE);
        }
        if (!file_exists(FILE_DAEMON_STOP)) {
            file_put_contents(FILE_DAEMON_STOP, sprintf("#!/bin/bash\nservice %s stop", NAME));
            chmod(FILE_DAEMON_STOP, MODE);
        }
    }
}

function daemon_run() {
    global $rProcess;

    /* Hook onto daemon termination handler */
    System_Daemon::setSigHandler(SIGTERM, 'daemon_sigterm_handler');
    
    /* Start deamon */
    System_Daemon::start();
    while (!System_Daemon::isDying()) {
        System_Daemon::info('Open process');
        $rProcess = proc_open(TASK, array(), $aPipes);
        do {
            System_Daemon::isRunning(); // required for deamon to respond properly
            sleep(PROCESS_POLL);        // gets interrupted on process termination
            $aStatus = proc_get_status($rProcess);
        } while ($aStatus['running']);
        System_Daemon::info('Process ended');
    }
}

function daemon_sigterm_handler($iSigNo) {
    global $sName;
    system(sprintf('pkill %s', $sName));
    System_Daemon::stop();
}
