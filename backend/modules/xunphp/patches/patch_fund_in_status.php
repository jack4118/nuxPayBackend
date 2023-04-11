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

$db->where('transaction_id', '', '!=');
$db->where('status', '');
$db->where('type', 'fund_in');
// $db->where('transaction_type', 'payment_gateway');
$fund_in_data = $db->get('xun_payment_gateway_fund_in', null, 'transaction_id, id, transaction_type');

foreach($fund_in_data as $key => $value){
    $transaction_id = $value['transaction_id'];
    
    $tx_hash_list[] = $transaction_id;
}

$count = count($tx_hash_list);
$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Count: $count \n");


$db->where('received_transaction_id', $tx_hash_list, 'IN');
$crypto_history = $db->map('received_transaction_id')->ArrayBuilder()->get('xun_crypto_history', null, 'id, received_transaction_id, status');

unset($tx_hash_list);
foreach($fund_in_data as $k1 => $v1){
    
    $transaction_hash = $v1['transaction_id'];
   
    if($crypto_history[$transaction_hash]){
        $status = strtolower($crypto_history[$transaction_hash]['status']);
        $update_status = array(
            "status" => $status == 'received' || $status == 'pending' ? 'success' : $status,
        );
        $db->where('transaction_id', $transaction_hash);
        $updated = $db->update('xun_payment_gateway_fund_in', $update_status);

        if(!$updated){
            $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Tx Hash: $transaction_hash not updated\n");
        }
        else{
            $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Tx Hash: $transaction_hash updated\n");
            unset($crypto_history[$transaction_hash]);
        }

       
    }
    else{
        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Tx Hash not found: $transaction_hash\n");
    }
}

$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t End process Patch Fund In Table\n");







    
?>