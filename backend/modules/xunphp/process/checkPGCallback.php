<?php

$currentPath = __DIR__;
include($currentPath.'/../include/config.php');
include($currentPath.'/../include/class.database.php');
include($currentPath.'/../include/class.general.php');
include($currentPath.'/../include/class.setting.php');
include($currentPath.'/../include/class.message.php');
include($currentPath.'/../include/class.webservice.php');
include($currentPath.'/../include/class.post.php');        
include($currentPath.'/../include/class.log.php');
include($currentPath.'/../include/class.binance.php'); 
include($currentPath.'/../include/class.provider.php'); 

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$webservice  = new Webservice($db, "", "");
$setting = new Setting($db);
$general = new General($db, $setting);
$post = new Post($db, $webservice, $msgpack);
$binance = new Binance(
    $config['binanceAPIKey'],
    $config['binanceAPISecret'],
    $config['binanceAPIURL'], //"https://api.binance.com/api/v3/",
    $config['binanceWAPIURL'] //"https://api.binance.com/wapi/v3/"
);
$provider = new Provider($db);
$message = new Message($db, $general, $provider);

$logPath = $currentPath.'/../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);

$today = date("Ymd");
$today_date = date("Y-m-d", strtotime("0 day"));
$webservice_table = "xun_web_services_".$today;
$db->where('data_out', '');
$db->where('data_in', '%paymentGatewayCallback%', 'LIKE');
$db->where("created_at between '". $today_date ." 00:00:00' and DATE_ADD(now() ,INTERVAL -5 MINUTE)");
$callback_data = $db->get($webservice_table);
$process_id = getmypid();

$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Start Check PG Callback\n");

if($callback_data){
    foreach($callback_data as $key => $value){
        // print_r($value['data_in']);

        $data_in = stripslashes($value['data_in']);
        $decoded_data_in = json_decode($data_in, true);

        $receivedTxID = $decoded_data_in['params']['receivedTxID'];
        $status = $decoded_data_in['params']['status'];
        $txID = $decoded_data_in['params']['txID'];
        $Amount = $decoded_data_in['params']['amount'];
        $url = $value['command'];
        $created_at = $value['created_at'];

        $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t PG Callback Empty Received TX ID: $receivedTxID\n");

    
        $message_d = "Callback URL: $url\n\n";
        $message_d .= "Received TX ID : " . $receivedTxID."\n";
        $message_d .= "TxID : " . $txID."\n";
        $message_d .= "Amount : " . $Amount."\n";
        $message_d .= "Status : " . $status."\n\n";
        $message_d .= "Time : " . $created_at ."\n";
        
        
        $erlang_params["tag"] = "PG Callback Return Empty";
        $erlang_params["message"] = $message_d;
        $erlang_params["mobile_list"] = $callback_notify_numbers;
        $xmpp_result = $general->send_thenux_notification($erlang_params, "thenux_issues");

    }

}
else{
    $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t No Empty Callback Return\n\n");

}
?>