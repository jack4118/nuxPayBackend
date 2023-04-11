<?php
    
    /**
     * Script to summarize developer's activity in daily basis
     */

    $currentPath = __DIR__;
    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include_once $currentPath . "/../include/config.php";
    include_once $currentPath . "/../include/class.database.php";
    include_once $currentPath . "/../include/class.log.php";
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $log = new Log($logPath, $logBaseName);

    // get date from argument if exist
    $startdate = '';
    if (!is_null($argv[1])) {
        list($y, $m, $d) = explode("-", $argv[1]);
        if(checkdate($m, $d, $y)){
            $startdate = $argv[1];
        } else {
            echo "Start Date ".$argv[1]." is not appropriate.\n";
            exit;
        }
    }

    if ($startdate == '') {
        $startdate = date('Y-m-d', strtotime("yesterday"));
    }

    $startTimestamp = $startdate." 00:00:00";
    $endTimestamp = $startdate." 23:59:59";
    $log->write(date('Y-m-d H:i:s') . " Message - cron daily developer activity starts. StartDate: ". $startTimestamp. " EndDate: ".$endTimestamp ."\n");
    echo "Start Date: ".$startTimestamp."\n";
    echo "End Date: ".$endTimestamp."\n";

    // check if data exist, delete all if have record on the date
    $db->where('date', $startdate);
    if ($db->has('developer_activity_daily_summary')) {
        $db->where('date', $startdate);
        $db->delete('developer_activity_daily_summary');
        $log->write(date('Y-m-d H:i:s') . " Message - Records exist on ". $startdate. ". Deleting before reinsert new data.\n");
        echo "Records exist on ".$startdate.". Deleting...\n";
    }

    // Start
    $db->where('created_at', $startTimestamp, '>=');
    $db->where('created_at', $endTimestamp, '<=');
    $db->groupBy('user_id');
    $db->groupBy('command');
    $records = $db->get('developer_activity_log', null, 'direction, user_id, command, count(*) AS activity_count');

    // catch empty records
    if (count($records) == 0) {
        $log->write(date('Y-m-d H:i:s') . " Message - ". $startdate. " have no activity.\n\n");
        echo $startdate." has no activity.";
        exit;
    }

    foreach($records as $record) {
        $insertData = array(
            'date' => $startdate,
            'user_id' => $record['user_id'],
            'direction' => $record['direction'],
            'command' => $record['command'],
            'activity_count' => $record['activity_count'],
            'created_at' => date('Y-m-d H:i:s')
        );
        $db->insert('developer_activity_daily_summary', $insertData);   
    }

    $log->write(date('Y-m-d H:i:s') . " Message - ". count($records) . " inserted into db\n\n");
    echo count($records)." records inserted into db.\n";

    echo "End\n\n";
    
?>
