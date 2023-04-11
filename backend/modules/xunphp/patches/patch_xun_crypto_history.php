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
$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Start process Patch Xun Crypto History\n");

$marketplace_currencies = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies');

$db->where('status', 'success');
// $db->where('transaction_id', '0xd52077cff8917350b5d7049df6759bd97bcb5a5bbc80c451191906dd20102312');
$db->where('transaction_id', '', '!=');
$db->where('received_transaction_id', '');
$crypto_history = $db->get('xun_crypto_history');

foreach($crypto_history as $key => $value){

    $received_transaction_id = $value['received_transaction_id'];
    $transaction_id = $value['transaction_id'];
    $reference_id = $value['reference_id'];
    $wallet_type = strtolower($value['wallet_type']);
    $address = $value['address'];
    $amount = $value['amount'];
    $amount_receive = $value['amount_receive'];
    $transaction_fee = $value['transaction_fee'];
    $tx_fee_wallet_type = $value['tx_fee_wallet_type'];
    $exchange_rate = $value['exchange_rate'];
    $miner_fee = $value['miner_fee'];
    $miner_fee_wallet_type = $value['miner_fee_wallet_type'];
    $sender_internal = $value['sender_internal'];
    $sender_external = $value['sender_external'];
    $recipient_internal = $value['recipient_internal'];
    $recipient_external = $value['recipient_external'];
    
    
    $symbol = strtoupper($marketplace_currencies[$wallet_type]['symbol']);

    $params = array(
        "creditUnit" => $symbol,
        "sentTxID" => $transaction_id
    );

 
    $return = $post->curl_crypto("patchPartnerNotify", $params);


    $dataReturn = $return['data'];
    $status = $return['status'];
    

    if($dataReturn && $status != 'error'){
        $creditDetails = $dataReturn['creditDetails'];
        $pg_address = $dataReturn['address'];
    
        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
        $decimal_places = $decimal_place_setting["decimal_places"];
    
        $amount_details = $creditDetails['amountDetails'];
        $amount_value = $amount_details['amount'];
        $amount_rate = $amount_details['rate'];
        $final_amount = $amount_value ? bcdiv($amount_value, $amount_rate, $decimal_places) : 0;
    
        $amount_receive_details = $creditDetails['amountReceiveDetails'];
        $amount_receive_amount = $amount_receive_details['amount'];
        $amount_receive_rate = $amount_receive_details['rate'];
        $final_amount_receive = $amount_value ? bcdiv($amount_receive_amount, $amount_receive_rate, $decimal_places) : 0;
    
        $service_charge_details = $creditDetails["serviceChargeDetails"];
        $service_charge_value = $service_charge_details['amount'];
        $service_charge_rate = $service_charge_details['rate'];
        $service_charge_wallet_type = $service_charge_details['type'];
        $service_charge_wallet_type = $service_charge_wallet_type ? $service_charge_wallet_type : $wallet_type;
        $final_service_charge = $service_charge_value ? bcdiv($service_charge_value, $service_charge_rate, $decimal_places) : 0;
    
        $miner_amount_details = $creditDetails["minerAmountDetails"];
        $miner_fee_value = $miner_amount_details['amount'];
        $miner_fee_rate = $miner_amount_details['rate'];
        $miner_fee_wallet_type = $miner_amount_details['type'];
        $miner_fee_wallet_type = $miner_fee_wallet_type ? $miner_fee_wallet_type : $wallet_type;
        $final_miner_fee = $miner_fee_value ? bcdiv($miner_fee_value, $miner_fee_rate, $decimal_places) : 0;
    
        $patch_sender_internal = $dataReturn['sender']['internal'];
        $patch_sender_external = $dataReturn['sender']['external'];
        $patch_recipient_internal = $dataReturn['recipient']['internal'];
        $patch_recipient_external = $dataReturn['recipient']['external'];
    
        $updateArray = array(
            "received_transaction_id" => $received_transaction_id ? $received_transaction_id : $dataReturn['receivedTxID'],
            "reference_id" => $reference_id,
            "wallet_type" => $wallet_type,
            "amount_receive" => $amount_receive ? $amount_receive : $final_amount_receive,
            "transaction_fee" => $transaction_fee ? $transaction_fee : $final_service_charge,
            "miner_fee" => $miner_fee ? $miner_fee : $final_miner_fee,
            "address" => $address ? $address : $pg_address,
            "sender_internal" => $sender_internal ? $sender_internal : $patch_sender_internal,
            "sender_external" => $sender_external ? $sender_external : $patch_sender_external,
            "recipient_internal" => $recipient_internal ? $recipient_internal : $patch_recipient_internal,
            "recipient_external" => $recipient_external ? $recipient_external : $patch_recipient_external,
            "updated_at" => date("Y-m-d H:i:s"),
    
            
        );

    
        $db->where('transaction_id' , $transaction_id);
        $updated = $db->update('xun_crypto_history', $updateArray);
       

        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $transaction_id updated \n");
    }
    else{
        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $transaction_id ERROR \n");
    }
    

}

$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t End process Patch Xun Crypto History\n");





?>
