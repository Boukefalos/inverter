#!/usr/local/bin/php
<?php
echo '<pre>';
$sRRDFile = 'inverter.rrd';
$sRRDFetch = 'rrdtool fetch %s AVERAGE -r %d -s %d -e %d';
$sField = 'PAC';

/* Constrain data to fetch */
$iLast = strtotime('9:00');
$iStart = $iLast;
$iEnd = strtotime('17:00');
$iResolution = 5;

/* Extract fields */
$sData = command(sprintf($sRRDFetch, $sRRDFile, $iResolution, $iStart, $iEnd));
$aData = explode("\n", trim($sData));
$aFields = preg_split("~[\s]+~", array_shift($aData));
array_shift($aData);
$aFields = array_flip($aFields);

$bFirst = true;
$fTotal = 0;
if (isset($aFields[$sField])) {
    $iField = $aFields[$sField] + 1;
    array_shift($aData);
    foreach ($aData as $sRow) {
        $aRow = explode(' ', $sRow);
        if ($bFirst) var_dump($aRow);
        $iDate = substr($aRow[0], 0, -1);
        $iInterval = $bFirst ? (($bFirst = false) || $iResolution) : $iDate - $iLast;
        if (($fValue = floatval($aRow[$iField])) > 0) {        
            $fTotal += $iInterval * $fValue;
        }
        $iLast = $iDate;
    }    
}

/* Convert to kWh */
var_dump(count($aData));
var_dump($fTotal / 1000 / 3600);

function command($sCommand) {
    ob_start();
    system($sCommand);
    return ob_get_clean();
}