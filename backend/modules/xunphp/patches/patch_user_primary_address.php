<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$db->where("primary_address", "", "!=");
$primary_address_data = $db->get("xun_user");

foreach($primary_address_data as $data){
    $username = $data["username"];

    $db->where("user_id", $username);
    $db->where("active", 1);
    $user_internal_address = $db->getOne("xun_crypto_user_address");
    
    $primary_address = $data["primary_address"];

    if($user_internal_address && $user_internal_address["external_address"] == ""){
        $update_data = [];
        $update_data["external_address"] = $primary_address;

        $db->where("id", $user_internal_address["id"]);
        $db->update("xun_crypto_user_address", $update_data);
    }
}

echo "\n done";