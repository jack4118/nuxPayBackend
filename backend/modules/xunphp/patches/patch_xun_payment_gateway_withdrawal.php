<?php

include_once '../include/config.php';
include_once '../include/class.database.php';
include_once '../include/class.log.php';

$process_id = getmypid();

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$logPath =  '../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);
// echo "\n starting process";
$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Start process Patch Xun Payment Withdrawal\n");

$created_at = '2020-09-11 10:00:00';
$db->where('received_transaction_id', '', '!=');
$db->where('created_at', $created_at , '<' );
$db->where('status', 'success');
$crypto_history = $db->get('xun_crypto_history');

$crypto_address =$db->map('crypto_address')->ArrayBuilder()->get('xun_crypto_address');

$invoice_detail = $db->map('payment_address')->ArrayBuilder()->get('xun_payment_gateway_invoice_detail');

foreach($crypto_history as $key => $value){

    
    $pg_address = $value['address'];
    $business_id = $value['business_id'];
    $reference_id = $value['reference_id'];
    $sender_address = $value['sender_external'] ? $value['sender_external'] : $value['sender_internal'];
    $recipient_address = $value['recipient_external'] ? $value['recipient_external'] : $value['recipient_internal'];
    $amount = $value['amount'];
    $amount_receive = $value['amount_receive'];
    $transaction_fee = $value['transaction_fee'];
    $miner_fee = $value['miner_fee'];
    $wallet_type = $value['wallet_type'];
    $transaction_hash = $value['transaction_id'];
    $status = $value['status'];
    $created_at = $value['created_at'];
    $updated_at = $value['updated_at'];

    // echo "created_at".$created_at."\n";
    if($crypto_address[$pg_address]){
        $transaction_type = 'api_integration';
    }
    else if($invoice_detail[$pg_address]){
        $transaction_type = 'request_fund';
        // echo "transaction type".$transaction_type."\n";
    }
    else{
        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t ERROR: Invalid Address - $pg_address\n");
        continue;
    }

    
    $insertQuery = array(
        "reference_id" => $reference_id,
        "business_id" => $business_id,
        "sender_address" => $sender_address,
        "recipient_address" => $recipient_address,
        "amount" => $amount,
        "amount_receive" => $amount_receive,
        "transaction_fee" => $transaction_fee,
        "miner_fee" => $miner_fee,
        "wallet_type" => $wallet_type,
        "transaction_hash" => $transaction_hash,
        "status" => $status,
        "transaction_type" => $transaction_type,
        "created_at" => $created_at,
        "updated_at" => $updated_at,
    );
    // print_r($insertQuery);
    // $insertArray[] = $insertQuery;
    $inserted = $db->insert('xun_payment_gateway_withdrawal', $insertQuery);


    if(!$inserted){
        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t ERROR: Reference ID - $reference_id\n");
    }
    
}


$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t End process Patch Xun Payment Withdrawal\n");

?>