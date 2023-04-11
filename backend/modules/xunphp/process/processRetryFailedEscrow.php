<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_marketplace.php";
include_once $currentPath . "/../include/class.xun_xmpp.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$xunMarketplace = new XunMarketplace($db, $post, $general);
$xunXmpp = new XunXmpp($db, $post);

$process_id = getmypid();

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);

// echo "\n starting process";
$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Start process\n");


$process_name = $logBaseName;
$file_path = basename(__FILE__);
$output_path = $process_name . '.log';

$db->where("name", $process_name);
$process = $db->getOne("processes");

$date = date("Y-m-d H:i:s");

if(!$process){
    $insertData = array(
        "name" => $process_name,
        "file_path" => $file_path,
        "output_path" => $output_path,
        "process_id" => $process_id,
        "created_at" => $date,
        "updated_at" => $date
    );

    $process_row_id = $db->insert("processes", $insertData);
}else{
    if($process["process_id"]){
        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t Previous process is still running\n");

        return;
    }
    $updateData = [];
    $updateData["process_id"] = $process_id;
    $updateData["updated_at"] = $date;

    $process_row_id = $process["id"];

    $db->where("id", $process_row_id);
    $db->update("processes", $updateData);
}

$db->where("processed", 0);
$result = $db->get("xun_marketplace_escrow_error");

foreach ($result as $data){
    $data_bin = $data["data"];
    $username = $data["username"];
    $data_arr = json_decode($data_bin);

    $advertisement_id = $data_arr->advertisement_id;
    $order_id = $data_arr->advertisement_order_id;
    $amount = $data_arr->amount;
    $walletType = $data_arr->walletType;
    
    $xunMarketplace->escrow_fund_out($advertisement_id, $order_id, $username, $amount, $walletType);
    $update_data = [];
    $update_data["processed"] = 1;
    $update_data["updated_at"] = date("Y-m-d H:i:s");
    
    $db->where("id", $data["id"]);
    $db->update("xun_marketplace_escrow_error", $update_data);
}

$updateData = [];
$updateData["process_id"] = '';
$updateData["updated_at"] = date("Y-m-d H:i:s");

$db->where("id", $process_row_id);
$db->update("processes", $updateData);

// echo "\n end process";
$log->write(date('Y-m-d H:i:s')." PID: ". $process_id ." \t End process\n\n");