<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$partnerDB = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], "thenuxPartner");

$partnerDB->where("is_registered", 1);
$registered_partner_user = $partnerDB->get("business_user");

$coin_id = 1;

if(!empty($registered_partner_user)){
    $mobile_arr = array_column($registered_partner_user, "mobile");

    $db->where("username", $mobile_arr, "IN");
    $db->where("type", "user");
    $user_id_arr = $db->map("username")->ArrayBuilder()->get("xun_user", null, "id, username, nickname");

    $insert_data_arr = [];

    foreach($registered_partner_user as $data){
        $username = $data["mobile"];

        $user_id = $user_id_arr[$username]["id"];

        $insert_data = array(
            "user_id" => $user_id,
            "business_coin_id" => $coin_id,
            "created_at" => date("Y-m-d H:i:s")
        );

        $insert_data_arr[] = $insert_data;
    }

    if(!empty($insert_data_arr)){
        $row_ids = $db->insertMulti("xun_user_coin", $insert_data_arr);
        if(!$row_ids){
            print_r($db);
        }
    }
}

?>