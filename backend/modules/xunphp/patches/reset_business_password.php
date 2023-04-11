<?php

    include_once('../include/config.php');
    include_once('../include/class.database.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    $new_password = password_hash("abc123", PASSWORD_BCRYPT);

    $updateData["password"] = $new_password;
    $db->update("xun_business_account", $updateData);

?>
