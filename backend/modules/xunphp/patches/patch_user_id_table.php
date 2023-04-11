<?php

    include_once('../include/config.php');
    include_once('../include/class.database.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);


    //get mobile from xun_user_id_table
    $get_username = $db->rawQuery("SELECT `id`, `user_username` FROM xun_user_id"); 

    foreach ($get_username as $value) {

        $id = $value["id"];
        $user_username = $value["user_username"];

    	$updateData["user_id"] 	= $user_username;
        $db->where("user_id" ,$id);
        $db->update("xun_crypto_user_address", $updateData);

    }
   	
?>
