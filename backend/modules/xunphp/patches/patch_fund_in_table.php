<?php

include_once '../include/config.php';
include_once '../include/class.database.php';
include_once '../include/class.post.php';
include_once '../include/class.xun_currency.php';
include_once '../include/class.general.php';
include_once '../include/class.setting.php';
include_once '../include/class.log.php';

$process_id = getmypid();

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$post = new post();
$xunCurrency   = new XunCurrency($db);
$setting = new Setting($db);
$general = new General($db, $setting);

$logPath =  '../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);
// echo "\n starting process";
$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Start process Patch Fund In Table\n");

$db->where('created_at', '2020-11-20 00:00:00', '<');
$db->where('received_transaction_id', '', '!=');
$crypto_history = $db->get('xun_crypto_history', null, 'received_transaction_id, reference_id, business_id, sender_internal, sender_external, address, amount, amount_receive, transaction_fee, miner_fee, wallet_type , exchange_rate, miner_fee_wallet_type, miner_fee_exchange_rate, created_at');

foreach($crypto_history as $key => $value){
    $received_tx_id = $value['received_transaction_id'];
    $reference_id = $value['reference_id'];
    $business_id = $value['business_id'];
    $sender_address = $value['sender_external'] ? $value['sender_external'] : $value['sender_internal'];
    $receiver_address = $value['address'];
    $amount = $value['amount'];
    $amount_receive = $value['amount_receive'];
    $transaction_fee = $value['transaction_fee'];
    $miner_fee = $value['miner_fee'];
    $wallet_type = $value['wallet_type'];
    $exchange_rate = $value['exchange_rate'];
    $miner_fee_wallet_type = $value['miner_fee_wallet_type'];
    $miner_fee_exchange_rate = $value['miner_fee_exchange_rate'];
    $created_at = $value['created_at'];

    $insertArray = array(
        "transaction_id" => $received_tx_id,
        "reference_id" => $reference_id,
        "business_id" => $business_id,
        "sender_address" => $sender_address,
        "receiver_address" => $receiver_address,
        "amount" => $amount,
        "amount_receive" => $amount_receive,
        "transaction_fee" => $transaction_fee,
        "miner_fee" => $miner_fee,
        "wallet_type" => $wallet_type,
        "exchange_rate" => $exchange_rate,
        "miner_fee_wallet_type" => $miner_fee_wallet_type,
        "miner_fee_exchange_rate" => $miner_fee_exchange_rate,
        "transaction_type" => "payment_gateway",
        "type" => "fund_in",
        "created_at" => $created_at
    );

    $insertMulti[] = $insertArray;

}

$inserted = $db->insertMulti('xun_payment_gateway_fund_in', $insertMulti);

$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t End process Patch Fund In Table\n");

?>