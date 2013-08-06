<?php
class RRD {
    const CREATE = 'create %s --step %d --start %d %s';
    const UPDATE = 'update %s %d:%s';
    const FETCH = 'fetch %s %s -r %d -s %d -e %d';

    protected static $oInstance;
    protected static $rProcess;
    protected static $aPipes = array();

    protected function __construct() {
        self::$rProcess = proc_open('rrdtool -', array(
           0 => array('pipe', 'r'),
           1 => array('pipe', 'w')), self::$aPipes);
        stream_set_blocking(self::$aPipes[1], false);
    }

    static function command($sCommand) {
        if (!isset(self::$rProcess)) {
            self::$oInstance = new self();
        }
        //echo $sCommand . "\n";
        fwrite(self::$aPipes[0], $sCommand . PHP_EOL);
        $nNull = null;
        $aRead = array(self::$aPipes[1]);
        stream_select($aRead, $nNull, $nNull, 10);
        return trim(stream_get_contents(self::$aPipes[1]));
    }

    static function create($sFile, $iStep, $iStart, $sContents) {
        $sCommand = sprintf(self::CREATE, $sFile, $iStep, $iStart, str_replace("\n", ' ', trim($sContents)));
        return RRD::command($sCommand);
    }

    static function update($sFile, $iTime, $aValues) {
        $sCommand = sprintf(self::UPDATE, $sFile, $iTime, implode(':', $aValues));
        return RRD::command($sCommand);
    }

    static function fetch($sFile, $iResolution, $iStart, $iEnd, $sType = 'AVERAGE') {
        $sCommand = sprintf(self::FETCH, $sFile, $sType, $iResolution, $iStart, $iEnd);
        $sData = RRD::command($sCommand);    
        $aData = explode("\n", trim($sData));
        $aFields = preg_split("~[\s]+~", array_shift($aData));
        $aFields = array_flip($aFields);
        array_shift($aData);
        array_pop($aData);
        $aValues = array();
        foreach ($aData as $sRow) {
            $aRow = explode(':', $sRow);
            $iTime = current($aRow);
            $aRow = explode(' ', trim(next($aRow)));
            foreach ($aRow as $iKey => $mValue) {
                if (strpos($mValue, 'nan') !== false) {
                    $aRow[$iKey] = null;
                }                
            }
            $aValues[$iTime] = $aRow;
        }
        return array($aFields, $aValues);
    }

    function __destruct() {
        fwrite(self::$aPipes[0], "quit\n");
        fclose(self::$aPipes[0]);
        fclose(self::$aPipes[1]);
        proc_close(self::$rProcess);
    }
}