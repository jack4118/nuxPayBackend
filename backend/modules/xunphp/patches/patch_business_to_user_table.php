<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$erlang_server = $config["erlang_server"];
$date = date("Y-m-d H:i:s");

// add xun_business record to xun_user

$db->where("id", 1, ">");
$xun_business_arr = $db->get("xun_business");
foreach ($xun_business_arr as $business){
    $business_id = $business["id"];
    $business_email = $business["email"];
    $business_name = $business["name"];
    $insert_data = array(
        "id" => $business_id,
        "server_host" => $erlang_server,
        "type" => "business",
        "nickname" => $business_name ? $business_name : '',
        "created_at" => $date,
        "updated_at" => $date
    );

    $user_id = $db->insert("xun_user", $insert_data);

    if($user_id){
        $update_data = [];
        $update_data["user_id"] = $user_id;
        $db->where("id", $business_id);
        $db->update("xun_business", $update_data);

        $db->where("email", $business_email);
        $db->update("xun_business_account", $update_data);
    }else{
        print_r($db);
    }
}

$update_user_data = [];
$update_user_data["type"] = "user";
$db->where("type", "");
$db->update("xun_user", $update_user_data);
?>