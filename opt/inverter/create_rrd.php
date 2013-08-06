#!/usr/bin/php
<?php
$sDataDirectory = 'data';
$aRRDCreate = array(
    'inverter.rrd' => 'create %s
    --step 5
    --start %d
    DS:TEMP:GAUGE:120:U:U
    DS:VPV:GAUGE:120:U:U
    DS:IAC:GAUGE:120:U:U
    DS:VAC:GAUGE:120:U:U
    DS:FAC:GAUGE:120:U:U
    DS:PAC:GAUGE:120:0:U
    DS:ETOTAL:GAUGE:120:0:U
    DS:ETODAY:GAUGE:120:0:U
    RRA:MIN:0.5:1:720
    RRA:MIN:0.5:17:1017
    RRA:MIN:0.5:120:1008
    RRA:MIN:0.5:535:1002
    RRA:MIN:0.5:6324:1001
    RRA:MAX:0.5:1:720
    RRA:MAX:0.5:17:1017
    RRA:MAX:0.5:120:1008
    RRA:MAX:0.5:535:1002
    RRA:MAX:0.5:6324:1001
    RRA:AVERAGE:0.5:1:720
    RRA:AVERAGE:0.5:17:1017
    RRA:AVERAGE:0.5:120:1008
    RRA:AVERAGE:0.5:535:1002
    RRA:AVERAGE:0.5:6324:1001',
    'today.rrd' => 'create %s
    --step 5
    --start %d    
    DS:PAC:GAUGE:120:0:U
    DS:ETODAY:GAUGE:120:0:U
    RRA:AVERAGE:0.5:1:17280');
$aRRDFiles = array_keys($aRRDCreate);

/* Spawn process that accepts commands from STDIN */
$rHandle = popen('rrdtool - > /dev/null', 'w');
$bFirst = true;
$aRRDKeys = array();
$i = 0;
foreach (glob($sDataDirectory . '/*.csv') as $sFile) {
    /* Extract header from csv file */
    $aData = explode("\n", trim(file_get_contents($sFile)));
    $sHeader = array_shift($aData);
    
    if ($bFirst) {
        $aHeader = array_flip(explode(',', $sHeader));
        foreach ($aRRDCreate as $sRRDFile => $sRRDCreate) {
            /* Determine fields to update in RRD database */
            preg_match_all('~DS:([^:]+):~', $sRRDCreate, $aMatches);
            $aKeys = array();
            foreach ($aMatches[1] as $sField) {
                $aKeys[] = $aHeader[$sField] - 1;
            }
            $aRRDKeys[$sRRDFile] = array_flip($aKeys);            
        }
    }

    foreach ($aData as $sEntry) {
        $aValues = array_slice(explode(',', $sEntry), 1, 12);
        if ($bFirst) {
            foreach ($aRRDCreate as $sRRDFile => $sRRDCreate) {
                /* Create RRD database */
                $sCommand = str_replace("\n", ' ', sprintf($sRRDCreate, $sRRDFile, $aValues[0] - 1)) . "\n";
                fwrite($rHandle, $sCommand);
            }
            $bFirst = false;
        }
        ++$i;
        foreach ($aRRDFiles as $sRRDFile) {
            /* Update relevant fields in RRD database */
            $aRRDValues = array_intersect_key($aValues, $aRRDKeys[$sRRDFile]);
            $sCommand = sprintf("update %s %d:%s\n", $sRRDFile, $aValues[0], implode(':', $aRRDValues));
            //printf('[%d] %s', $i, $sCommand); 
            fwrite($rHandle, $sCommand);
        }
    }
}
fwrite($rHandle, "quit\n");
pclose($rHandle);