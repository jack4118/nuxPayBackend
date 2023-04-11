<?php

    include_once('../include/config.php');
    include_once('../include/class.database.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    $payment_method_settings = $db->get('xun_payment_method_settings');
    
    $db->where('record_type', 'system');
    $marketplace_payment_method = $db->map('payment_type')->ArrayBuilder()->get('xun_marketplace_payment_method');

    // print_r($marketplace_payment_method);
    foreach($payment_method_settings as $payment_method){
        $updateData['payment_method_id'] = $marketplace_payment_method[$payment_method['name']]['id'];
        
        $db->where('name', 'Online Banking', 'NOT LIKE');
        $db->where('id', $payment_method['id']);
        $db->update('xun_payment_method_settings', $updateData);
    }

?>
