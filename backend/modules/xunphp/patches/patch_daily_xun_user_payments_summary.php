<?php

$currentPath = __DIR__;

// get date from argument (required)
$startdate = '';
if (!is_null($argv[1])) {
    list($y, $m, $d) = explode("-", $argv[1]);
    if(checkdate($m, $d, $y)){
        $startdate = $argv[1];
    } else {
        echo "\nStart Date ".$argv[1]." is not appropriate.\n\n";
        exit;
    }
} else {
    echo "\nPlease provide a starting date with the format of YYYY-MM-DD.\n\n";
    exit;
}

$period = new DatePeriod(
    new DateTime($startdate.' 00:00:00'),
    new DateInterval('P1D'),
    new DateTime(date('Y-m-d H:i:s', strtotime('yesterday 23:59:59')))
);

$pathToCronProcess = $currentPath."/../process/cronXunUserPaymentsSummary.php";
foreach($period as $date) {
    $dateTime = $date->format('Y-m-d');

    $result = shell_exec('php '.$pathToCronProcess.' '.$dateTime);
    echo $result;
}

echo "Patch complete.\n\n";

?>