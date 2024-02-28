<?php
/*
 * Perform this when onclick() button
 */
$in = \filter_input(\INPUT_GET,'in');
$logfile = 'logs/'.date('Ym').'.csv';
$ipaddress = '';
if (getenv('HTTP_CLIENT_IP')) {
    $ipaddress = getenv('HTTP_CLIENT_IP');
} else if(getenv('HTTP_X_FORWARDED_FOR')) {
    $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
} else if(getenv('HTTP_X_FORWARDED')) {
    $ipaddress = getenv('HTTP_X_FORWARDED');
} else if(getenv('HTTP_FORWARDED_FOR')) {
    $ipaddress = getenv('HTTP_FORWARDED_FOR');
} else if(getenv('HTTP_FORWARDED')) {
   $ipaddress = getenv('HTTP_FORWARDED');
} else if(getenv('REMOTE_ADDR')) {
    $ipaddress = getenv('REMOTE_ADDR');
} else {
    $ipaddress = 'UNKNOWN';
}
function logger($str) {
    global $logfile, $ipaddress;
    $out = fopen($logfile,'a');
    fputcsv(
        $out, 
        array(
            preg_replace('/-07:00/','',date('c')),
            $ipaddress,
            $str
        )
    ); 
    fclose($out);
}

logger("tel:".$in);