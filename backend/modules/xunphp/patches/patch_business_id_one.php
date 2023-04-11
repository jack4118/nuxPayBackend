<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$erlang_server = $config["erlang_server"];
$date = date("Y-m-d H:i:s");

$db->where("id", 1);
$user_id_1 = $db->getOne("xun_user");

unset($user_id_1["id"]);
$row_id = $db->insert("xun_user", $user_id_1);

if(!$row_id){
    print_r($db);
    return;
}

$db->where("id", 1);
$db->delete("xun_user");

$business_id = 1;

$db->where("id", 1);
$business_id_1 = $db->getOne("xun_business");

$business_email = $business_id_1["email"];
$business_name = $business_id_1["name"];
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
?>