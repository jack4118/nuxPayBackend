<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$db->groupBy("user_id");
$addresses = $db->get("xun_crypto_user_address");

foreach ($addresses as $address){
    $username = $address["user_id"];
    $db->where("username", $username);
    $user_id = $db->getValue("xun_user", "id");

    echo "\n username $username, user_id $user_id";

    $update_data = [];
    $update_data["user_id"] = $user_id;

    $db->where("user_id", $username);
    $db->update("xun_crypto_user_address", $update_data);
}

$db->groupBy("user_id");
$addresses = $db->get("xun_crypto_user_address_verification");

foreach ($addresses as $address){
    $username = $address["user_id"];
    $db->where("username", $username);
    $user_id = $db->getValue("xun_user", "id");

    echo "\n username $username, user_id $user_id";

    $update_data = [];
    $update_data["user_id"] = $user_id;

    $db->where("user_id", $username);
    $db->update("xun_crypto_user_address_verification", $update_data);
}

$db->groupBy("user_id");
$addresses = $db->get("xun_crypto_user_transaction_verification");

foreach ($addresses as $address){
    $username = $address["user_id"];
    $db->where("username", $username);
    $user_id = $db->getValue("xun_user", "id");

    echo "\n username $username, user_id $user_id";

    $update_data = [];
    $update_data["user_id"] = $user_id;

    $db->where("user_id", $username);
    $db->update("xun_crypto_user_transaction_verification", $update_data);
}