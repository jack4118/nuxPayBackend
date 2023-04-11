<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_crypto.php";
include_once $currentPath . "/../include/class.xun_xmpp.php";
include_once $currentPath . "/../include/class.xun_wallet.php";
include_once $currentPath . "/../include/class.xun_company_wallet.php";
include_once $currentPath . "/../include/class.xun_wallet_transaction_model.php";
include_once $currentPath . "/../include/class.xun_marketer.php";
include_once $currentPath . "/../include/class.xun_currency.php";
include_once $currentPath . "/../include/class.xun_coins.php";
include_once $currentPath . "/../include/class.xun_payment.php";

include_once $currentPath . "/../include/class.abstract_xun_user.php";
include_once $currentPath . "/../include/class.xun_user_model.php";
include_once $currentPath . "/../include/class.xun_user_service.php";
include_once $currentPath . "/../include/class.xun_business_model.php";
include_once $currentPath . "/../include/class.xun_business_service.php";
include_once $currentPath . "/../include/class.xun_livechat_model.php";
include_once $currentPath . "/../include/class.xun_miner_fee.php";

include_once $currentPath . "/../include/class.xun_coins.php";
include_once $currentPath . "/../include/class.xun_payment.php";
include_once $currentPath . "/../include/class.message.php";
include_once $currentPath . "/../include/class.provider.php";

$process_id = getmypid();

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php');
$path = realpath($logPath);

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$log = new Log($logPath, $logBaseName);
$xunXmpp = new XunXmpp($db, $post);
$xunCrypto = new XunCrypto($db, $post, $general);
$xunWallet = new XunWallet($db);
$xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
$xun_business_service = new XunBusinessService($db);
$xunMarketer = new XunMarketer($db, $setting, $general);
$xunCurrency = new XunCurrency($db);
$xunMinerFee = new XunMinerFee($db, $general, $setting, $log);
$xun_business_service = new XunBusinessService($db);
$xunCoins = new XunCoins($db, $setting);
$xunPayment = new XunPayment($db, $post, $general, $setting, $xunCrypto, $xunCoins);
$provider      = new Provider($db);
$message       = new Message($db, $general, $provider);

$process_name = $logBaseName;
$file_path = basename(__FILE__);
$output_path = $process_name . '.log';

$db->where("name", $process_name);
$process = $db->getOne("processes");

// check process status
if (!$process) {
    $insertData = array(
        "name" => $process_name,
        "file_path" => $file_path,
        "output_path" => $output_path,
        "process_id" => $process_id,
        "created_at" => date("Y-m-d H:i:s"),
        "updated_at" => date("Y-m-d H:i:s"),
    );

    $process_row_id = $db->insert("processes", $insertData);
} else {

    if ($process["disabled"]==1) {
        $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . "\t $process_name Disabled.\n");
        exit();
    }

    $updateData = [];
    $updateData["process_id"] = $process_id;
    $updateData["updated_at"] = date("Y-m-d H:i:s");

    $process_row_id = $process["id"];

    $db->where("id", $process_row_id);
    $db->update("processes", $updateData);
}

// Process start
$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . "\t $process_name Start.\n");

try {
    $externalTransferCompanyPoolURL = $config['externalTransferCompanyPoolURL'];
    $prepaidWalletServerURL =  $config["giftCodeUrl"];

    $wallet_balance_threshold = $setting->systemSetting['marketerMinThresholdWalletBalance'];

    $db->orderBy("id", "DESC");
    $db->where("marketer_id", 0, "<>");
    $db->where("disabled", 0);
    $db->where("destination_address", "", "<>");
    $xun_business_marketer_result = $db->get('xun_business_marketer_commission_scheme');

    $wallet_type_result = array_column($xun_business_marketer_result, 'wallet_type');

    $db->where('currency_id', $wallet_type_result, 'IN');
    $xun_marketplace_currencies = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies', null, 'name, currency_id, unit_conversion');

    $cryptocurrency_rate_result = $xunCurrency->get_cryptocurrency_rate($wallet_type_result);

    foreach ($xun_business_marketer_result as $key => $value) {
        $business_marketer_commission_id = $value['id'];
        $marketer_id = $value['marketer_id'];
        $business_id = $value['business_id'];
        $wallet_type = $value['wallet_type'];
        $destination_address = $value['destination_address'];
        $db->where('user_id', $business_id);
        $xun_business = $db->getOne('xun_business');

        $business_name = $xun_business['name'];

        $db->where('id', $business_id);
        $user_service_charge_result = $db->getOne('xun_user', 'id, username, service_charge_rate');

        $db->where('marketer_id', $marketer_id);
        $reseller_detail = $db->getOne('reseller');

        $reseller_user_id = $reseller_detail['user_id'];
        
        $db->where('user_id', $reseller_user_id);
        $db->where('address_type', 'nuxpay_wallet');
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        $internal_address = $crypto_user_address['address'];

        if($internal_address == $destination_address || $internal_address == ''){
            continue;
        }
    
        $service_charge_rate = $user_service_charge_result['service_charge_rate'];

        $marketplaceTradingFee = $setting->systemSetting["marketplaceTradingFee"];

        if ($service_charge_rate == $marketplaceTradingFee ) {
            $company_pool_address = $setting->systemSetting["marketplaceCompanyPoolWalletAddress2"];
        } else {
            $company_pool_address = $setting->systemSetting["marketplaceCompanyPoolWalletAddress"];
        }

        $db->where('created_at', date("Y-m-d 00:00:00"), '<');
        $db->where('business_marketer_commission_id', $business_marketer_commission_id);
        $db->where('wallet_type', $wallet_type);
        $sum_result = $db->getOne('xun_marketer_commission_transaction', 'SUM(credit) as sumCredit, SUM(debit) as sumDebit');

        $marketer_wallet_balance = '0.0000000';
        if ($sum_result) {
            $sum_credit = $sum_result['sumCredit'];
            $sum_debit = $sum_result['sumDebit'];

            $marketer_wallet_balance = bcsub($sum_credit, $sum_debit, 8);

        }

        if ($marketer_wallet_balance > 0) {
            $ret_val= $xunCrypto->crypto_validate_address($destination_address, $wallet_type, 'external');
            $log->write(date('Y-m-d H:i:s') . "ret_val:" .json_encode($ret_val) . "\n");

            ///need to add send message when the destination  address is not valid
            if($ret_val['code'] == 1){
                $ret_val1 = $xunCrypto->crypto_validate_address($destination_address, $wallet_type, 'internal');
 
                if($ret_val1['code'] ==1){
                    // unset($marketer_destination_result[$key]);
                    break;
                }
                else{
                    $destination_address = $ret_val1['data']['address'];
                    $transaction_type = $ret_val1['data']['addressType'];
                }
            }
            else{
                $destination_address = $ret_val['data']['address'];
                $transaction_type = $ret_val['data']['addressType'];
            }
            $wallet_info = $xunCrypto->get_wallet_info($internal_address, $wallet_type);

            $lc_wallet_type = strtolower($wallet_type);
            $walletBalance = $wallet_info[$lc_wallet_type]['balance'];
            $unitConversion = $wallet_info[$lc_wallet_type]['unitConversion'];
            $minerFeeWalletType = strtolower($wallet_info[$lc_wallet_type]['feeType']);
            $original_wallet_type = $wallet_info[$lc_wallet_type]['walletType'];

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($lc_wallet_type, true);
            $decimal_places = $decimal_place_setting["decimal_places"];

            $miner_decimal_place_setting = $xunCurrency->get_currency_decimal_places($minerFeeWalletType, true);
            $miner_fee_decimal_places = $miner_decimal_place_setting['decimal_places'];

            //Call get wallet info to get the miner fee balance if the miner fee is not charged in the same wallet type
            if ($minerFeeWalletType != $wallet_type) {
                $miner_fee_wallet_info = $xunCrypto->get_wallet_info($internal_address, $minerFeeWalletType);
                // $minerFeeBalance = $miner_fee_wallet_info[$minerFeeWalletType]['balance'];
                $minerFeeUnitConversion = $miner_fee_wallet_info[$minerFeeWalletType]['unitConversion'];
                $minerFeeBalance = $xunMinerFee->getMinerFeeBalance($internal_address, $minerFeeWalletType);
                $converted_miner_fee_balance = $minerFeeBalance;

                $balance_miner_fee_wallet = bcdiv($miner_fee_wallet_info[$minerFeeWalletType]['balance'], $minerFeeUnitConversion, 18);
            } else {
                $minerFeeBalance = $walletBalance;
                $minerFeeUnitConversion = $unitConversion;
                $converted_miner_fee_balance = bcdiv($minerFeeBalance, $minerFeeUnitConversion, $miner_fee_decimal_places);
            }

            $satoshi_amount = bcmul($marketer_wallet_balance, $unitConversion);
            $log->write("debug transaction_type - ".json_encode($transaction_type)."\n");

            if($transaction_type == 'external'){
                //calculate miner fee only
                $return = $xunCrypto->calculate_miner_fee($internal_address, $destination_address, $satoshi_amount, $wallet_type, 1);
                $miner_fee = $return['data']['txFee'];
            }else if($transaction_type == 'internal')
            {
                $miner_fee =  '0';
            }
            $log->write("debug - ".json_encode($miner_fee)."\n");
            $log->write("debug - ".json_encode($minerFeeUnitConversion)."\n");
            $converted_miner_fee = bcdiv($miner_fee, $minerFeeUnitConversion, 18);

            if ($balance_miner_fee_wallet >= $converted_miner_fee)
            {
                $converted_miner_fee =  '0';
            }

            if ($wallet_type != $minerFeeWalletType) {
                $lowercase_miner_wallet_type = strtolower($minerFeeWalletType);

                $original_miner_fee_amount = $converted_miner_fee;
                $converted_miner_fee = $xunCurrency->get_conversion_amount($lc_wallet_type, $lowercase_miner_wallet_type, $converted_miner_fee, true);
                $convertedSatoshiMinerFee = bcmul($converted_miner_fee, $unitConversion);

            } else {
                $convertedSatoshiMinerFee = $miner_fee;
                $converted_miner_fee = $xunCurrency->round_miner_fee($minerFeeWalletType, $converted_miner_fee);
            }

            $amountWithoutMinerFee = bcsub($marketer_wallet_balance, $converted_miner_fee, $decimal_places);
            $amountSatoshiWithoutMinerFee = $xunCrypto->get_satoshi_amount($wallet_type, $amountWithoutMinerFee, $unitConversion);

            $original_wallet_usd_rate = $cryptocurrency_rate_result[$wallet_type];
            $original_amount_usd = bcmul($marketer_wallet_balance, $original_wallet_usd_rate, $decimal_places);

            $wallet_usd_rate = $cryptocurrency_rate_result[$wallet_type];
            $amountWithoutMinerUSD = bcmul($amountWithoutMinerFee, $wallet_usd_rate, $decimal_places);

            // if($minerFeeWalletType != $wallet_type) {
            //     if($converted_miner_fee_balance < $original_miner_fee_amount){
            //         $tag = "Insufficient Miner Fee Balance";
            //         $message_d = "Business Marketer Commission ID: ".$business_marketer_commission_id."\n";
            //         $message_d .= "Miner Fee:".$original_miner_fee_amount."\n";
            //         $message_d .= "Miner Fee Wallet Balance: ".$converted_miner_fee_balance."\n";
            //         $message_d .= "Wallet Type:".$minerFeeWalletType."\n";
            //         $message_d .= "Time: ".date("Y-m-d H:i:s")."\n";
    
            //         $thenux_params["tag"]         = $tag;
            //         $thenux_params["message"]     = $message_d;
            //         $thenux_params["mobile_list"] = $xun_numbers;
            //         $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
            //         continue;
            //     }
            // }else{
            //     if ($miner_fee > $minerFeeBalance) {
            //         $converted_miner_fee_balance = bcdiv($minerFeeBalance, $minerFeeUnitConversion, $miner_fee_decimal_places);
            //         $tag = "Insufficient Miner Fee Balance";
            //         $message_d = "Business Marketer Commission ID: " . $business_marketer_commission_id . "\n";
            //         $message_d .= "Miner Fee:" . $converted_miner_fee . "\n";
            //         $message_d .= "Miner Fee Wallet Balance: " . $converted_miner_fee_balance . "\n";
            //         $message_d .= "Wallet Type:" . $minerFeeWalletType . "\n";
            //         $message_d .= "Time: " . date("Y-m-d H:i:s") . "\n";
    
            //         $thenux_params["tag"] = $tag;
            //         $thenux_params["message"] = $message_d;
            //         $thenux_params["mobile_list"] = $xun_numbers;
            //         $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
            //         continue;
            //     }
            // }
            

            // if ($amountWithoutMinerFee <= 0) {

            //     $tag = "Insufficient Marketer Miner Fee";
            //     $message_d = "Business Marketer Commission ID: " . $business_marketer_commission_id . "\n";
            //     $message_d .= "Amount:" . $amountWithoutMinerFee . "\n";
            //     $message_d .= "Miner Fee:" . $converted_miner_fee . "\n";
            //     $message_d .= "Wallet Type:" . $wallet_type . "\n";
            //     $message_d .= "Time: " . date("Y-m-d H:i:s") . "\n";

            //     $thenux_params["tag"] = $tag;
            //     $thenux_params["message"] = $message_d;
            //     $thenux_params["mobile_list"] = $xun_numbers;
            //     $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
            //     continue;
            // }

            if ($amountWithoutMinerUSD < $wallet_balance_threshold) {

                $displayAmount = $amountWithoutMinerFee > 0 ? $amountWithoutMinerFee : 0;
                $displayAmountUSD = $amountWithoutMinerUSD > 0 ? $amountWithoutMinerUSD : 0 ;

                $tag = "Below Marketer Threshold";
                $message_d = "Business Name:".$business_name."\n\n";
                $message_d .= "Original Amount:" .$marketer_wallet_balance."\n";
                $message_d .= "Miner Fee:".$converted_miner_fee."\n";
                $message_d .= "Remaining Amount:".$displayAmount."\n";
                $message_d .= "Remaining Amount(USD):".$displayAmountUSD."\n\n";
                $message_d .= "Wallet Type:".$wallet_type."\n";
                $message_d .= "Time: " . date("Y-m-d H:i:s") . "\n";

                $thenux_params["tag"] = $tag;
                $thenux_params["message"] = $message_d;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

                continue;

            }

            //When fund out the current marketer commission only
            $fund_out_marketer_transaction_id = $xunCrypto->insertMarketerCommissionTransaction($business_marketer_commission_id, $amountWithoutMinerFee, $amountSatoshiWithoutMinerFee, $original_wallet_type, 0, $amountWithoutMinerFee, 0,$destination_address, 'Daily Fund Out');
            $log->write(date('Y-m-d H:i:s') . " Debug minerFeeWalletType: " .json_encode($minerFeeWalletType) . "\n");
            $log->write(date('Y-m-d H:i:s') . " Debug wallet_type: " .json_encode($wallet_type) . "\n");

            if ($minerFeeWalletType != $wallet_type) {
                $log->write(date('Y-m-d H:i:s') . "sum:" .json_encode($original_miner_fee_amount) . "\n");

                if($original_miner_fee_amount > 0){
                    $miner_fee_transaction_id = $xunCrypto->insertMarketerCommissionTransaction($business_marketer_commission_id, $converted_miner_fee, $convertedSatoshiMinerFee, $original_wallet_type, 0, $converted_miner_fee, 0, "Original Miner Fee Amount: " . $original_miner_fee_amount, 'Miner Fee Fund Out', $fund_out_marketer_transaction_id);
                    
                    $miner_fee_pool_address = $setting->systemSetting['minerFeePoolAddress'];

                    $tx_obj = new stdClass();
                    $tx_obj->userID = $reseller_user_id;
                    $tx_obj->address = $internal_address;
        
                    $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);
                    $xunWallet = new XunWallet($db);
                    $minerTransactionObj->status = 'pending';
                    $minerTransactionObj->transactionHash = '';
                    $minerTransactionObj->transactionToken = $transaction_token;
                    $minerTransactionObj->senderAddress = $internal_address;
                    $minerTransactionObj->recipientAddress = $miner_fee_pool_address;
                    $minerTransactionObj->userID = $reseller_id;
                    $minerTransactionObj->senderUserID = $reseller_user_id;
                    $minerTransactionObj->recipientUserID = 'miner_pool';
                    $minerTransactionObj->walletType = $wallet_type;
                    $minerTransactionObj->amount = $converted_miner_fee;
                    $minerTransactionObj->addressType = 'miner_pool';
                    $minerTransactionObj->transactionType = 'send';
                    $minerTransactionObj->escrow = 0;
                    $minerTransactionObj->referenceID = $miner_fee_transaction_id;
                    $minerTransactionObj->escrowContractAddress = '';
                    $minerTransactionObj->createdAt = $date;
                    $minerTransactionObj->updatedAt = $date;
                    $minerTransactionObj->expiresAt = '';
                    $minerTransactionObj->fee = '';
                    $minerTransactionObj->feeUnit = '';

                    $txHistoryObj->paymentDetailsID = '';
                    $txHistoryObj->status = 'pending';
                    $txHistoryObj->transactionID = "";
                    $txHistoryObj->transactionToken = "";
                    $txHistoryObj->senderAddress = $internal_address;
                    $txHistoryObj->recipientAddress = $miner_fee_pool_address;
                    $txHistoryObj->senderUserID = 'company_pool';
                    $txHistoryObj->recipientUserID = $reseller_id;
                    $txHistoryObj->walletType = $wallet_type;
                    $txHistoryObj->amount = $converted_miner_fee;
                    $txHistoryObj->transactionType = 'miner_pool';
                    $txHistoryObj->referenceID = $miner_fee_transaction_id;
                    $txHistoryObj->createdAt = $date;
                    $txHistoryObj->updatedAt = $date;
                    $txHistoryObj->type = 'in';
                    $txHistoryObj->gatewayType = "BC";
        
                    $miner_transaction_id = $xunWallet->insertUserWalletTransaction($minerTransactionObj);

                    $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                    $miner_transaction_history_id = $transaction_history_result['transaction_history_id'];
                    $miner_transaction_history_table = $transaction_history_result['table_name'];
    
                    $updateWalletTx = array(
                        "transaction_history_id" => $miner_transaction_history_id,
                        "transaction_history_table" => $miner_transaction_history_table
                    );
                    $xunWallet->updateWalletTransaction($miner_transaction_id, $updateWalletTx);

                    $curlParams = array(
                        "command" => "fundOutCompanyWallet",
                        "params" => array(
                            "senderAddress" => $internal_address,
                            "receiverAddress" => $miner_fee_pool_address,
                            "amount" => $converted_miner_fee,
                            "satoshiAmount" => $convertedSatoshiMinerFee,
                            "walletType" => $wallet_type,
                            "id" => $miner_transaction_id,
                            "transactionToken" => $transaction_token,
                            "addressType" => "nuxpay_wallet",
                        ),
                    );
                    
                    $log->write(date('Y-m-d H:i:s') . "curlParams:" .json_encode($curlParams) . "\n");
                    $minerCurlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);
                    
                    $log->write(date('Y-m-d H:i:s') . "minerCurlResponse:" .json_encode($minerCurlResponse) . "\n");
                    
                    if ($minerCurlResponse['code'] == 1) {
                        $tag = "Marketer Miner Fund Out";   

                    } else {

                        $update_wallet_transaction_arr = array(
                            "status" => 'failed',
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('id', $miner_transaction_id);
                        $db->update('xun_wallet_transaction', $update_wallet_transaction_arr);

                        $db->where('id', $transaction_history_id);
                        $db->update($transaction_history_table, $update_wallet_transaction_arr);
                       
                        $tag = "Failed Marketer Miner Fund Out";
                        $additional_message = "Error Message: " . $minerCurlResponse["message_d"] . "\n";
                        $additional_message .= "Input: " . json_encode($company_pool_params) . "\n";
                    }

                    $thenux_params["tag"]         = $tag;
                    $thenux_params["message"]     = $message_d;
                    $thenux_params["mobile_list"] = $xun_numbers;
                    $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");


                    $xunWallet = new XunWallet($db);
                    $transactionObj->status = 'pending';
                    $transactionObj->transactionHash = '';
                    $transactionObj->transactionToken = '';
                    $transactionObj->senderAddress = $company_pool_address;
                    $transactionObj->recipientAddress = $internal_address;
                    $transactionObj->userID = $reseller_user_id;
                    $transactionObj->senderUserID = 'company_pool';
                    $transactionObj->recipientUserID = $reseller_user_id;
                    $transactionObj->walletType = $minerFeeWalletType;
                    $transactionObj->amount = $original_miner_fee_amount;
                    $transactionObj->addressType = 'nuxpay_wallet';
                    $transactionObj->transactionType = 'send';
                    $transactionObj->escrow = 0;
                    $transactionObj->referenceID = $miner_fee_transaction_id;
                    $transactionObj->escrowContractAddress = '';
                    $transactionObj->createdAt = $date;
                    $transactionObj->updatedAt = $date;
                    $transactionObj->expiresAt = '';
                    $transactionObj->fee = '';
                    $transactionObj->feeUnit = '';

                    $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);  

                    $txHistoryObj->paymentDetailsID = '';
                    $txHistoryObj->status = 'pending';
                    $txHistoryObj->transactionID = "";
                    $txHistoryObj->transactionToken = "";
                    $txHistoryObj->senderAddress = $company_pool_address;
                    $txHistoryObj->recipientAddress = $internal_address;
                    $txHistoryObj->senderUserID = 'company_pool';
                    $txHistoryObj->recipientUserID = $reseller_user_id;
                    $txHistoryObj->walletType = $minerFeeWalletType;
                    $txHistoryObj->amount = $original_miner_fee_amount;
                    $txHistoryObj->transactionType = 'nuxpay_wallet';
                    $txHistoryObj->referenceID = $miner_fee_transaction_id;
                    $txHistoryObj->createdAt = $date;
                    $txHistoryObj->updatedAt = $date;
                    $txHistoryObj->type = 'in';
                    $txHistoryObj->gatewayType = "BC";
                    $txHistoryObj->isInternal = 1;
        
                    $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                    $transaction_history_id = $transaction_history_result['transaction_history_id'];
                    $transaction_history_table = $transaction_history_result['table_name'];
    
                    $updateWalletTx = array(
                        "transaction_history_id" => $transaction_history_id,
                        "transaction_history_table" => $transaction_history_table
                    );
                    $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);

                    //  insert to miner fee table
                    $miner_fee_tx_data = array(
                        "address" => $company_pool_address,
                        "reference_id" => $transaction_id,
                        "reference_table" => "xun_wallet_transaction",
                        "type" => 'miner_fee_payment',
                        "wallet_type" => $minerFeeWalletType,
                        "debit" => $original_miner_fee_amount,
                    );
                    $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);

                     //  insert to miner fee table
                     $miner_fee_tx_data = array(
                        "address" => $internal_address,
                        "reference_id" => $transaction_id,
                        "reference_table" => "xun_wallet_transaction",
                        "type" => 'fund_in',
                        "wallet_type" => $minerFeeWalletType,
                        "credit" => $original_miner_fee_amount,
                    );
                    $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);

                    $company_pool_params = array(
                        "receiverAddress" => $internal_address,
                        "amount" => $original_miner_fee_amount,
                        "walletType" => $minerFeeWalletType,
                        "walletTransactionID" => $transaction_id,
                        // "transactionToken" => $transaction_token,
                        "senderAddress" => $company_pool_address,

                    );
                    // $company_pool_result = $post->curl_post($internalTransferCompanyPoolURL, $company_pool_params, 0, 0, array(), 1, 1);
                    $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
                    $company_pool_result = $xunCompanyWallet->fundOut('company_pool', $company_pool_params);

                    if($company_pool_result['code'] ==0){
                        //  full marketer commission

                        $new_marketer_wallet_balance = $xunMarketer->get_marketer_commission_balance($business_marketer_commission_id, $wallet_type);
                        $total_new_marketer_wallet_balance = bcadd($new_marketer_wallet_balance, $amountWithoutMinerFee, $decimal_places);
                        $fund_out_failed_id = $xunCrypto->insertMarketerCommissionTransaction($business_marketer_commission_id, $amountWithoutMinerFee, $amountSatoshiWithoutMinerFee, $wallet_type, $amountWithoutMinerFee, 0, $total_new_marketer_wallet_balance, '', 'Fund Out Failed');
                        
                        $update_wallet_transaction_arr = array(
                            "status" => 'failed',
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('id', $transaction_id);
                        $db->update('xun_wallet_transaction', $update_wallet_transaction_arr);
                        
                        $balance = $xunMinerFee->getMinerFeeBalance($company_pool_address, $minerFeeWalletType);
                        $balance = $balance + $original_miner_fee_amount;

                        $update_MinerFeeTransaction_arr = array(
                            "debit" => 0,
                            "balance" => $balance,
                        );
                        $db->where('address',$company_pool_address);
                        $db->where('reference_id', $transaction_id);
                        $db->where('reference_table', 'xun_wallet_transaction');
                        $db->where('type', 'miner_fee_payment');
                        $db->update('xun_miner_fee_transaction', $update_MinerFeeTransaction_arr);
                        
                        $tag = "Failed Marketer Fund Out";
                        $additional_message = "Error Message: " . $company_pool_result["message_d"] . "\n";
                        $additional_message .= "Input: " . json_encode($company_pool_params) . "\n"; 

                      
                    }
                    sleep(3);
                    continue;
                }
            } else {
                $miner_fee_transaction_id = $xunCrypto->insertMarketerCommissionTransaction($business_marketer_commission_id, $converted_miner_fee, $convertedSatoshiMinerFee, $original_wallet_type, 0, $converted_miner_fee, 0, '', 'Miner Fee Fund Out', $fund_out_marketer_transaction_id);
            }

            $tx_obj = new stdClass();
            $tx_obj->userID = $reseller_user_id;
            $tx_obj->address = $internal_address;

            $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);
            $xunWallet = new XunWallet($db);
            $transactionObj->status = 'pending';
            $transactionObj->transactionHash = '';
            $transactionObj->transactionToken = $transaction_token;
            $transactionObj->senderAddress = $internal_address;
            $transactionObj->recipientAddress = $destination_address;
            $transactionObj->userID = $reseller_id;
            $transactionObj->senderUserID = $reseller_user_id;
            $transactionObj->recipientUserID = '';
            $transactionObj->walletType = $wallet_type;
            $transactionObj->amount = $amountWithoutMinerFee;
            $transactionObj->addressType = 'marketer';
            $transactionObj->transactionType = 'send';
            $transactionObj->escrow = 0;
            $transactionObj->referenceID = '';
            $transactionObj->escrowContractAddress = '';
            $transactionObj->createdAt = $date;
            $transactionObj->updatedAt = $date;
            $transactionObj->expiresAt = '';
            $transactionObj->fee = '';
            $transactionObj->feeUnit = '';

            $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

            $txHistoryObj->paymentDetailsID = '';
            $txHistoryObj->status = 'pending';
            $txHistoryObj->transactionID = "";
            $txHistoryObj->transactionToken = $transaction_token;
            $txHistoryObj->senderAddress = $internal_address;
            $txHistoryObj->recipientAddress = $destination_address;
            $txHistoryObj->senderUserID = $reseller_user_id;
            $txHistoryObj->recipientUserID = '';
            $txHistoryObj->walletType = $wallet_type;
            $txHistoryObj->amount = $amountWithoutMinerFee;
            $txHistoryObj->transactionType = 'marketer';
            $txHistoryObj->referenceID = '';
            $txHistoryObj->createdAt = $date;
            $txHistoryObj->updatedAt = $date;
            $txHistoryObj->type = 'in';
            $txHistoryObj->gatewayType = "BC";
            $txHistoryObj->isInternal = $transaction_type == 'internal' ? 1 : 0;

            $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
            $transaction_history_id = $transaction_history_result['transaction_history_id'];
            $transaction_history_table = $transaction_history_result['table_name'];

            $updateWalletTx = array(
                "transaction_history_id" => $transaction_history_id,
                "transaction_history_table" => $transaction_history_table
            );
            $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);

            if($transaction_type == 'external'){
    
                $curlParams = array(
                    "command" => "fundOutExternal",
                    "params" => array(
                        "senderAddress" => $internal_address,
                        "receiverAddress" => $destination_address,
                        "amount" => $amountWithoutMinerFee,
                        "walletType" => $wallet_type,
                        "transactionToken" => $transaction_token,
                        "walletTransactionID" => $transaction_id
                    )
                );
                
            }
            else if($transaction_type == 'internal'){
            
                $curlParams = array(
                    "command" => "fundOutCompanyWallet",
                    "params" => array(
                        "senderAddress" => $internal_address,
                        "receiverAddress" => $destination_address,
                        "amount" => $amountWithoutMinerFee,
                        "satoshiAmount" => $amountSatoshiWithoutMinerFee,
                        "walletType" => $wallet_type,
                        "id" => $transaction_id,
                        "transactionToken" => $transaction_token,
                        "addressType" => "nuxpay_wallet",
                    ),
                );
            
            }
        

            $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);
            $log->write(date('Y-m-d H:i:s') . "Debug curlResponse:" . $curlResponse . "\n");

            if ($curlResponse['code'] == 1) {
                $update_wallet_transaction_id = array(
                    "reference_id" => $transaction_id,
                    "updated_at" => date("Y-m-d H:i:s"),
                );
                $db->where('id', $fund_out_marketer_transaction_id);
                $db->update('xun_marketer_commission_transaction', $update_wallet_transaction_id);
                $log->write(date('Y-m-d H:i:s') . "Success Fund Out Wallet Transaction ID:" . $transaction_id . "\n");

                $tag = "Marketer Fund Out";
            } else {
                $new_marketer_wallet_balance = $xunMarketer->get_marketer_commission_balance($business_marketer_commission_id, $wallet_type);
                $total_new_marketer_wallet_balance = bcadd($new_marketer_wallet_balance, $marketer_wallet_balance, $decimal_places);

                if($transaction_type == 'external'){
                    $fund_out_failed_id = $xunCrypto->insertMarketerCommissionTransaction($business_marketer_commission_id, $marketer_wallet_balance, $satoshi_amount, $original_wallet_type, $marketer_wallet_balance, 0, $total_new_marketer_wallet_balance, '', 'Daily Fund Out Failed');
                }
                else{
                    $fund_out_failed_id = $xunCrypto->insertMarketerCommissionTransaction($business_marketer_commission_id, $marketer_wallet_balance, $satoshi_amount, $original_wallet_type, 0, $marketer_wallet_balance, $total_new_marketer_wallet_balance, '', 'Daily Fund Out Failed');
                }

                $update_wallet_transaction_arr = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s"),
                );
                $db->where('id', $transaction_id);
                $db->update('xun_wallet_transaction', $update_wallet_transaction_arr);
                $log->write(date('Y-m-d H:i:s') . "Failed Fund Out Wallet Transaction ID:" . $transaction_id . "Failed Reason: " . $curlResponse['message_d'] . "\n");

                $db->where('id', $transaction_history_id);
                $db->update($transaction_history_table, $update_wallet_transaction_arr);
                
                $tag = "Failed Marketer Fund Out";
                $additional_message = "Error Message: " . $curlResponse["message_d"] . "\n";
                $additional_message .= "Input: " . json_encode($curlParams) . "\n";
            }

            $message_d = "Daily Fund Out\n";
            $message_d .= "Business Name:".$business_name."\n";
            $message_d .= "Amount:" .$amountWithoutMinerFee."\n";
            $message_d .= "Wallet Type:".$wallet_type."\n";

            if($additional_message){
                $message_d .= $additional_message;
            }
            $message_d .= "Time: ".date("Y-m-d H:i:s")."\n";

            $thenux_params["tag"]         = $tag;
            $thenux_params["messages"]     = $message_d;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
        }

    }
} catch (Exception $e) {
    $msg = $e->getMessage();

    $message_d = $process_name . "\n";
    $message_d .= "Time : " . date("Y-m-d H:i:s");
    $message_d .= $msg;

    $thenux_params["tag"] = "Process Error";
    $thenux_params["message"] = $message_d;
    $thenux_params["mobile_list"] = ["+60124466833", "+60102208361", "+60184709181"];
    $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
}

$updateData = [];
$updateData["process_id"] = '';
$updateData["updated_at"] = date("Y-m-d H:i:s");

$db->where("id", $process_row_id);
$db->update("processes", $updateData);

$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . "\t $process_name End.\n");
