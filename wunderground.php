<?php
define('KEY', '2556854ea765c351');
define('LIMIT_MINUTE', 10);
define('LIMIT_DAY', 500);
define('LIMIT_FILE', 'data/wunderground.json');

function wunderground($sService, $sQuery, $bDebug = false) {
    /* Get current date values */
    $iMinute = date('i');
    $iDay = date('z');

    if (file_exists(LIMIT_FILE)) {
        /* Read number of calls used */
        $sJSON = file_get_contents(LIMIT_FILE);
        $aJSON = json_decode($sJSON, true);
        $aCount = array(
            'minute' => $iMinute != $aJSON['minute'][0] ? 0 : $aJSON['minute'][1],
            'day' => $iDay != $aJSON['day'][0] ? 0 : $aJSON['day'][1]);
     } else {
        /* Initialise to zero */
        $aCount = array(
            'minute' => 0,
            'day' => 0);
    }

    /* Check call limits */
    $iWait = 0;
    if ($aCount['minute'] >= LIMIT_MINUTE) {
        $iWait = 60 - date('s');
        if ($bDebug === true) {
            printf("Minute limit (%d) reached, wait %d seconds\n", LIMIT_MINUTE, $iWait);
        }
        $aCount['minute'] = 0;
    } else if ($aCount['day'] >= LIMIT_DAY) {
        $iWait = strtotime('00:00 + 1 day') - time();    
        if ($bDebug === true) {
            printf("Daily limit (%d) reached, wait %d seconds\n", LIMIT_DAY, $iWait);
        }
        $aCount['day'] = 0;
    }

    /* Prevent from exceeding call limits */
    if ($iWait > 0) {
        //die("Try again later!\n");
        return null;
    }

    /* Update call counts */
    ++$aCount['minute'];
    ++$aCount['day'];

    /* Report number of calls used */
    if ($bDebug === true) {
        printf("Used %d/%d minutely and %d/%d daily calls\n", $aCount['minute'], LIMIT_MINUTE, $aCount['day'], LIMIT_DAY);
    }

    /* Write number of calls used to file */
    $aJSON = array(
        'minute' => array($iMinute, $aCount['minute']),
        'day' => array($iDay, $aCount['day']));
    file_put_contents(LIMIT_FILE, json_encode($aJSON));

    /* Perform actual call */
    $sUrl = sprintf('http://api.wunderground.com/api/%s/%s/q/%s.json', KEY, $sService, $sQuery);
    $sJSON = file_get_contents($sUrl);
    return json_decode($sJSON, true);
}
