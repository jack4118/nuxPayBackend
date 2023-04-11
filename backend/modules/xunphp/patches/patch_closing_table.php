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
//  Select all users
// $db->where('id', '15935');
$userRes = $db->get("xun_user", null, "id, username, type, created_at");


$db->where('address_type', 'nuxpay_wallet');
$db->where('active', 1);
$crypto_user_data = $db->map('user_id')->ArrayBuilder()->get('xun_crypto_user_address');

$wallet_type_list = $xunCurrency->get_cryptocurrency_list('a.currency_id');

foreach($userRes as $key => $value){
    $user_id = $value['id'];
    $internal_address = $crypto_user_data[$user_id]['address'];

    if(!$internal_address){
        $log->write(date("Y-m-d H:i:s")." No Internal Address User ID: $user_id \n");
        break;
    }
    // $wallet_info_data = $xunCrypto->get_wallet_info($internal_address);

    foreach($wallet_type_list as $wallet_key => $wallet_value){

        $wallet_type = $wallet_value;

        $balance_data = $xunCrypto->get_live_internal_balance($internal_address, $wallet_type, '', '2021-03-22');

        $bc_balance = $balance_data['finalBalance'];

        // $satoshi_balance = $wallet_info_data[$wallet_type]['balance'] ? $wallet_info_data[$wallet_type]['balance'] : '0';
        // $unit_conversion = $wallet_info_data[$wallet_type]['unitConversion'];

        $decimal_places = $xunCurrency->get_currency_decimal_places($wallet_type);

        // $bc_balance = bcdiv($satoshi_balance, $unit_conversion, $decimal_places);
        
        $db->where('created_at', '2021-03-23 00:00:00', '<');
        $db->where('business_id', $user_id);
        $db->where('wallet_type', $wallet_type);
        $db->where('transaction_type', array('withhold', 'release_withhold', 'fund_in_to_destination'), 'NOT IN');
        $invoice_transaction_data = $db->getOne('xun_payment_gateway_invoice_transaction', 'sum(credit) as totalCredit, sum(debit) as totalDebit');
    
        $totalCredit = $invoice_transaction_data['totalCredit'] ? $invoice_transaction_data['totalCredit'] : '0';
        $totalDebit = $invoice_transaction_data['totalDebit'] ? $invoice_transaction_data['totalDebit'] : '0';
    

        $balance = bcsub($totalCredit, $totalDebit, $decimal_places);
    
        if($balance != $bc_balance){
            $log->write(date("Y-m-d H:i:s")." User ID: $user_id Wallet Type: $wallet_type BC Balance: $bc_balance Table Balance: $balance\n");
            
        }

        if($balance < 0){
            $insertClosing = array(
                "user_id" => $user_id,
                "type" => $wallet_type,
                "date" => date("Y-m-d"),
                "total" => '0',
                "balance" => '0',
                "created_at" => date("Y-m-d H:i:s")
            );
        }
        else{
            if($balance > $bc_balance){
                $log->write(date("Y-m-d H:i:s")." Balance More than BC WalletUser ID: $user_id Wallet Type: $wallet_type BC Balance: $bc_balance Table Balance: $balance\n");
    
                $insertClosing = array(
                    "user_id" => $user_id,
                    "type" => $wallet_type,
                    "date" => $closingDate,
                    "total" => $bc_balance,
                    "balance" => $bc_balance,
                    "created_at" => date("Y-m-d H:i:s")
                );
    
            }
            else{
                $insertClosing = array(
                    "user_id" => $user_id,
                    "type" => $wallet_type,
                    "date" => $closingDate,
                    "total" => $balance,
                    "balance" => $balance,
                    "created_at" => date("Y-m-d H:i:s")
                );
            }
        }
       

        $db->insert('xun_acc_closing', $insertClosing);
        
        
    }

}

$company_address_list = $xunCrypto->company_wallet_address();

foreach($company_address_list as $company_key => $company_value){
    $address_type = $company_value['type'];
    $internal_address = $company_key;
    if($address_type == 'payment_gateway'){
        break;
    }

    // echo "internal_address".$internal_address."\n";
    // if(!$internal_address){
    //     $log->write(date("Y-m-d H:i:s")." No Internal Address User ID: $user_id \n");
    //     break;
    // }
    // $wallet_info_data = $xunCrypto->get_wallet_info($internal_address);

    foreach($wallet_type_list as $key => $value){

        $wallet_type = $value;

        $external_address = $xunCrypto->get_external_address($internal_address, $wallet_type);
        $addressArr = array($internal_address, $external_address);

        $balance_data = $xunCrypto->get_live_internal_balance($internal_address, $wallet_type);
        $bc_balance = $balance_data['finalBalance'];


        // $satoshi_balance = $wallet_info_data[$wallet_type]['balance'] ? $wallet_info_data[$wallet_type]['balance'] : '0';
        // $unit_conversion = $wallet_info_data[$wallet_type]['unitConversion'];

        // $decimal_places = $xunCurrency->get_currency_decimal_places($wallet_type);

        // $bc_balance = bcdiv($satoshi_balance, $unit_conversion, $decimal_places);

        $db->where('created_at', '2021-03-23 00:00:00' , '<');
        $db->where('sender_address', $addressArr, 'IN');
        // $db->where('sender_user_id', $address_type);
        $db->where('status', 'completed');
        $db->where('wallet_type', $wallet_type);
        $send_tx_data = $db->getOne('xun_wallet_transaction', 'sum(amount) as totalDebit');

        $db->where('created_at', '2021-03-23 00:00:00' , '<');
        $db->where('recipient_address', $addressArr, 'IN');
        // $db->where('recipient_user_id', $address_type);
        $db->where('status', 'completed');
        $db->where('wallet_type', $wallet_type);
        $recipient_tx_data = $db->getOne('xun_wallet_transaction', 'sum(amount) as totalCredit');

        $db->where('created_at', '2021-03-23 00:00:00', '<');
        $db->where('wallet_type', $wallet_type);
        $db->where('address', $addressArr, 'IN');
        $miner_fee_tx_data = $db->getOne('xun_miner_fee_transaction', 'sum(credit) as totalCredit, sum(debit) as totalDebit');

        $minerFeeDecimalPlace = log($unit_conversion, 10);

        if($miner_fee_tx_data){
            $miner_fee_balance = bcdiv($miner_fee_tx_data['totalCredit'], $miner_fee_tx_data['totalDebit'], $minerFeeDecimalPlace);
        }

        $decimal_places = $xunCurrency->get_currency_decimal_places($wallet_type);

        $totalDebit = $send_tx_data['totalDebit'] ? $send_tx_data['totalDebit'] : '0';
        $totalCredit = $recipient_tx_data['totalCredit'] ? $recipient_tx_data['totalCredit'] : '0';

        $balance = bcsub($totalCredit, $totalDebit, $decimal_places);

        if($miner_fee_balance){
            $balance = bcsub($balance, $miner_fee_balance, $decimal_places);
        }


        if($balance > 0){
            $log->write(date("Y-m-d H:i:s")." Balance MORE THAN 0 User ID: $address_type Wallet Type: $wallet_type BC Balance: $bc_balance Table Balance: $balance\n");

        }
        //Offset Amount
        $offset_amount = $this->get_offset_balance($internal_address, $wallet_type);

        if($offset_amount != 0){
            $db->where('currency_id', $wallet_type);
            $unit_conversion = $db->getValue('xun_marketplace_currencies', 'unit_conversion');

            $balance_satoshi = bcmul($balance, $unit_conversion);

            $remaining_balance = bcadd($balance_satoshi, $offset_amount);

            $balance = bcdiv($remaining_balance, $unit_conversion, 8);
        }

        if($balance < 0){
            $insertClosing = array(
                "user_id" => $address_type,
                "type" => $wallet_type,
                "date" => date("Y-m-d"),
                "total" => '0',
                "balance" => '0',
                "created_at" => date("Y-m-d H:i:s")
            );
        }
        else{
            if($balance > $bc_balance){
                $log->write(date("Y-m-d H:i:s")." Balance MORE THAN BC WalletUser ID: $address_type Wallet Type: $wallet_type BC Balance: $bc_balance Table Balance: $balance\n");
    
                $insertClosing = array(
                    "user_id" => $address_type,
                    "type" => $wallet_type,
                    "date" => $closingDate,
                    "total" => $bc_balance,
                    "balance" => $bc_balance,
                    "created_at" => date("Y-m-d H:i:s")
                );
    
            }
            else{
                $insertClosing = array(
                    "user_id" => $address_type,
                    "type" => $wallet_type,
                    "date" => $closingDate,
                    "total" => $balance,
                    "balance" => $balance,
                    "created_at" => date("Y-m-d H:i:s")
                );
            }
        }
       

        $db->insert('xun_acc_closing', $insertClosing);

    
    }
}


?>