<?php

include_once '../include/config.php';
include_once '../include/class.database.php';
include_once '../include/class.post.php';
include_once '../include/class.xun_currency.php';
include_once '../include/class.general.php';
include_once '../include/class.setting.php';
include_once '../include/class.log.php';

$process_id = getmypid();

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$post = new post();
$xunCurrency   = new XunCurrency($db);
$setting = new Setting($db);
$general = new General($db, $setting);

$logPath =  '../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);
// echo "\n starting process";
$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Start process Patch Reseller Referral Code\n");

$db->where('referral_code', '');
$db->where('status', 'approved');
$reseller = $db->get('reseller');

foreach($reseller as $key => $value){
    $reseller_id = $value['id'];

    while (1) {
        $referral_code = $general->generateAlpaNumeric(6, 'referral_code');

        $db->where('referral_code', $referral_code);
        $result = $db->get('reseller');

        if (!$result) {
            break;
        }
    }

    $updateArray= array(
        "referral_code" => $referral_code,
        "updated_at" => date("Y-m-d H:i:s")
    );

    $db->where('id', $reseller_id);
    $updated = $db->update('reseller', $updateArray);

    if(!$updated){
        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Reseller ID: $reseller_id not updated \n");
    }
    else{
        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Reseller ID: $reseller_id , Referral Code: $referral_code\n");
    }
   
}





?>