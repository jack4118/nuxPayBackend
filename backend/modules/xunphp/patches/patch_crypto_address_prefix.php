<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$date = date("Y-m-d H:i:s");
$count = 0;

$address_result = $db->get("xun_crypto_user_address");

foreach ($address_result as $address_data) {
    
    $row_id  = $address_data["id"];
    $address = $address_data["address"];
    
    $prefix  = substr($address, 0, 2);
    
    if($prefix == "0x") continue;
    
    $count++;
    
    $user_id     = $address_data["user_id"];
    $new_address = "0x".$address;
    
    echo "$count: $user_id, $new_address\n";
    
    $updateData["address"] = $new_address;
    $updateData["updated_at"] = $date;
    
    $db->where("id", $row_id);
    $db->update("xun_crypto_user_address", $updateData);
    
}

?>