<?php

    include_once('../include/config.php');
    include_once('../include/class.database.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    $db->where("active", "1");
    $user_address = $db->get("xun_crypto_user_address");

    foreach($user_address as $address){
        
        $address = $address["address"];
        $username = $address["user_id"];
        $updated_at = $address["updated_at"];
        
        $db->where("username", $username);
        $user = $db->getOne("xun_user");
        
        $userId = $user["id"];
        
        $updateData["internal_address"] = $address;
        
        $db->where("user_id", $userId);
        $db->where("created_at", "> ".$updated_at);
        $db->update("xun_crypto_user_external_address", $updateData);
        
    }


?>
