<?php
$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_crypto.php";
include_once $currentPath . "/../include/class.xun_freecoin_payout.php";
include_once $currentPath . "/../include/class.xun_currency.php";
include_once $currentPath . "/../include/class.xun_wallet.php";
include_once $currentPath . "/../include/class.xun_wallet_transaction_model.php";
include_once $currentPath . "/../include/class.abstract_xun_user.php";
include_once $currentPath . "/../include/class.xun_user_model.php";
include_once $currentPath . "/../include/class.xun_user_service.php";
include_once $currentPath . "/../include/class.xun_business_model.php";
include_once $currentPath . "/../include/class.xun_business_service.php";
include_once  $currentPath . "/../include/class.xun_livechat_model.php";
include_once  $currentPath . "/../include/class.xun_wallet_transaction_model.php";
include_once  $currentPath . "/../include/class.xun_group_chat.php";
include_once  $currentPath . "/../include/class.xun_payment_gateway_model.php";
include_once  $currentPath . "/../include/class.xun_payment_gateway_service.php";
include_once  $currentPath . "/../include/class.xun_payment_gateway.php";


$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$xunCrypto = new XunCrypto($db, $post, $general);
$xunCurrency   = new XunCurrency($db);
$xunPaymentGateway = new XunPaymentGateway($db, $post, $general, $setting, $xunCrypto, $xunCoins);
$logPath = $currentPath.'/../log/';
$logBaseName = basename(__FILE__, '.php');

$log = new Log($logPath, $logBaseName);

$closingDate = '2021-03-23';

$business_marketer_commission_scheme = $db->get('xun_business_marketer_commission_scheme');

$reseller_data = $db->map('marketer_id')->ArrayBuilder()->get('reseller');

$db->where('created_at', '2021-03-23 00:00:00', '<');
$db->groupBy('business_marketer_commission_id');
$marketer_commission_transaction = $db->map('business_marketer_commission_id')->ArrayBuilder()->get('xun_marketer_commission_transaction', null, 'business_marketer_commission_id, sum(credit) as totalCredit, sum(debit) as totalDebit');

foreach($business_marketer_commission_scheme as $key => $value){
    $business_marketer_commission_id = $value['id'];
    // $user_id = $value['business_id'];
    $wallet_type = $value['wallet_type'];
    $marketer_id = $value['marketer_id'];
    
    $db->where('marketer_id', $marketer_id);
    $reseller_data = $db->getOne('reseller');

    $user_id = $reseller_data['user_id'];
    
    $totalCredit = $marketer_commission_transaction[$business_marketer_commission_id]['totalCredit'] ? $marketer_commission_transaction[$business_marketer_commission_id]['totalCredit'] : '0';
    $totalDebit = $marketer_commission_transaction[$business_marketer_commission_id]['totalDebit'] ? $marketer_commission_transaction[$business_marketer_commission_id]['totalDebit'] : '0';

    $balance = bcsub($totalCredit, $totalDebit, 8);

    $db->where('user_id', $user_id);
    $db->where('type', $wallet_type);
    $db->orderBy('date', 'DESC');
    $closing_data = $db->getOne('xun_acc_closing');

    $db->where('user_id', $user_id);
    $crypto_user_address= $db->getOne('xun_crypto_user_address');

    if($crypto_user_address){
        $internal_address = $crypto_user_address['address'];
        // $wallet_info_data = $xunCrypto->get_wallet_info($internal_address);
    }
    else{
        $log->write(date("Y-m-d H:i:s")." No Internal Address User ID: $user_id \n");
        continue;
    }

    $balance_data = $xunCrypto->get_live_internal_balance($internal_address, $wallet_type, '', '2021-03-23');

    $bc_balance = $balance_data['finalBalance'];
    // $satoshi_balance = $wallet_info_data[$wallet_type]['balance'] ? $wallet_info_data[$wallet_type]['balance'] : '0';
    // $unit_conversion = $wallet_info_data[$wallet_type]['unitConversion'];

    // $decimal_places = $xunCurrency->get_currency_decimal_places($wallet_type);

    // $bc_balance = bcdiv($satoshi_balance, $unit_conversion, $decimal_places);

    if($bc_balance != $balance){
        $log->write(date("Y-m-d H:i:s")." User ID: $user_id Wallet Type: $wallet_type BC Balance: $bc_balance Table Balance: $balance\n");
    }

    if($bc_balance < $balance){
        // $balance = $bc_balance;
        $log->write(date("Y-m-d H:i:s")." Balance More than BC WalletUser ID: $user_id Wallet Type: $wallet_type BC Balance: $bc_balance Table Balance: $balance\n");

    }

    if($balance < 0){
        $balance = 0;
    }

    if($balance > 0){
        $log->write(date("Y-m-d H:i:s")." Balance More than 0 User ID: $user_id Wallet Type: $wallet_type BC Balance: $bc_balance Table Balance: $balance\n");

    }
    if($closing_data){
        $closing_balance = $closing_data['balance'];
        $closing_id = $closing_data['id'];
        $balance = bcadd($closing_balance, $balance, 8);

        $updateClosing = array(
            "balance" => $balance
        );


        $db->where('id', $closing_id);
        $closing_updated = $db->update('xun_acc_closing', $updateClosing);
    }
    else{

        $arrayData = array(
            "user_id" => $user_id,
            "type" => $wallet_type,
            "date" => $closingDate,
            "total" => $balance,
            "balance" => $balance,
            "created_at" => date("Y-m-d H:i:s")
        );
        $db->insert('xun_acc_closing', $arrayData);

    }

 

}


?>