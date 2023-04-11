<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$xun_escrow_report = $db->get('xun_escrow_report');

foreach ($xun_escrow_report as $escrow_report){
    $wallet_transaction_id = $escrow_report['wallet_transaction_id'];
    $transaction_type = $escrow_report['transaction_type'];
    $id = $escrow_report['id'];
    
    $db->where('id', $wallet_transaction_id);
    if ($transaction_type == "1"){
        $reporter_address = $db->getValue('xun_wallet_transaction','sender_address');
    }else if ($transaction_type == "2"){
        $reporter_address = $db->getValue('xun_wallet_transaction', 'recipient_address');
    }
    
    $db->where('address', $reporter_address);
    $wallet_user_id = $db->getValue('xun_crypto_user_address', 'user_id');

    $update_data = [];
    $update_data["wallet_user_id"] = $wallet_user_id;

    $db->where('id', $id);
    $db->update('xun_escrow_report', $update_data);
}