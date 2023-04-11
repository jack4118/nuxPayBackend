<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$update_data = [];
$update_data["status"] = "pending_escrow";

$db->where("status", "pending_fund_in");
$db->update("xun_marketplace_advertisement", $update_data);

$db->where("status", "pending_fund_in");
$db->update("xun_marketplace_advertisement_order_cache", $update_data);

$db->groupBy("table_name");
$cache_table = $db->get("xun_marketplace_advertisement_order_cache");
foreach($cache_table as $data){
    $table_name = $data["table_name"];
    $db->where("status", "pending_fund_in");
    $db->update($table_name, $update_data);
}
