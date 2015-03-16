#!/usr/bin/php
<?php
require_once 'rrd.php';

$sDataDirectory = 'data';
$aRRDFiles = array(
    'inverter.rrd' => array(5, '
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
    RRA:AVERAGE:0.5:6324:1001'),
    'today.rrd' => array(5, '
    DS:PAC:GAUGE:120:0:U
    DS:ETODAY:GAUGE:120:0:U
    RRA:AVERAGE:0.5:1:17280'));

$bFirst = true;
$aRRDKeys = array();
$i = 0;
foreach (glob($sDataDirectory . '/*.csv') as $sFile) {
    /* Extract header from csv file */
    $aData = explode("\n", trim(file_get_contents($sFile)));
    $sHeader = array_shift($aData);

    if ($bFirst) {
        $aHeader = array_flip(explode(',', $sHeader));
        foreach ($aRRDFiles as $sFile => $aRRD) {
            /* Determine fields to update in RRD database */
            $sContents = $aRRD[1];
            preg_match_all('~DS:([^:]+):~', $sContents, $aMatches);
            $aKeys = array();
            foreach ($aMatches[1] as $sField) {
                $aKeys[] = $aHeader[$sField] - 1;
            }
            $aRRDKeys[$sFile] = array_flip($aKeys);            
        }
    }

    foreach ($aData as $sEntry) {
        $aValues = array_slice(explode(',', $sEntry), 1, 12);
        $iTime = $aValues[0];
        if ($bFirst) {
            foreach ($aRRDFiles as $sFile => $aRRD) {
                /* Create RRD database */
                $iStep = $aRRD[0];
                $iStart = $iTime - 1;
                $sContents = $aRRD[1];
                RRD::create($sFile, $iStep, $iStart, $sContents);
            }
            $bFirst = false;
        }
        ++$i;
        foreach ($aRRDFiles as $sFile => $aRRD) {
            /* Update relevant fields in RRD database */
            $aValues = array_intersect_key($aValues, $aRRDKeys[$sFile]);
            RRD::update($sFile, $iTime, $aValues);
        }
    }
}