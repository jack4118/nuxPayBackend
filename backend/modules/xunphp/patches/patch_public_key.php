<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$db->groupBy("key_user_id");
$user_id_result = $db->get("xun_public_key");

foreach($user_id_result as $user_id){
    
    $user_id_array[] = $user_id["key_user_id"];
    
}

$date = date("Y-m-d H:i:s");

foreach($user_id_array as $user_id){
    
    $db->where("key_user_id", $user_id);
    $db->where("status", "1");
    $db->orderBy("updated_at", "DESC");
    $key_result = $db->getOne("xun_public_key");

    $updateData["updated_at"] = $date;
    $updateData["status"] = "0";

    $db->where("key_user_id", $user_id);
    $db->where("status", "1");
    $db->where("id", $key_result["id"], "!=");
    $db->update("xun_public_key", $updateData);
    
}