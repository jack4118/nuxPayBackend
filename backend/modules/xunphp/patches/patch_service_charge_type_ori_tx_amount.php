<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$db->where('address_type', 'service_charge');
$xun_wallet_transaction = $db->get('xun_wallet_transaction');

foreach ($xun_wallet_transaction as $wallet_tx){
    $date_check[] = $wallet_tx['created_at'];
    $wallet_tx_id[] = $wallet_tx['wallet_transaction_id'];
}

$exception = array('service_charge', 'company_pool', 'company_acc', 'master_upline', 'upline', 'pay');
$db->where('created_at', $date_check, 'IN');
$db->where('address_type', $exception, 'NOT IN');
$matched_records = $db->get('xun_wallet_transaction');

// print_r($db->getLastQuery());

foreach($matched_records as $matched){
    $matched_date[$matched['created_at']]['address_type'] = $matched['address_type'];
    $matched_date[$matched['created_at']]['amount'] = $matched['amount'];
}
foreach($xun_wallet_transaction as $id_insert){
    $matched_date[$id_insert['created_at']]['id'] = $id_insert['id'];
}

foreach ($date_check as $date){
    $update_record[$matched_date[$date]['id']]['service_charge_type'] = $matched_date[$date]['address_type'];
    $update_record[$matched_date[$date]['id']]['amount'] = $matched_date[$date]['amount'];
}

foreach($update_record as $key => $value){
    $checking[] = $value['service_charge_type'];
    $updateData = array(
        'service_charge_type' => $value['service_charge_type'],
        'ori_tx_amount' => $value['amount']
    );
    $db->where('wallet_transaction_id', $key);
    $db->where('ori_tx_amount', 0);
    $db->where('service_charge_type', '');
    $db->update('xun_service_charge_audit', $updateData);
}
?>