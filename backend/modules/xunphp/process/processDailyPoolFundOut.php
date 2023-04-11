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

include_once $currentPath . "/../include/class.abstract_xun_user.php";
include_once $currentPath . "/../include/class.xun_user_model.php";
include_once $currentPath . "/../include/class.xun_user_service.php";
include_once $currentPath . "/../include/class.xun_business_model.php";
include_once $currentPath . "/../include/class.xun_business_service.php";
include_once $currentPath . "/../include/class.xun_livechat_model.php";
include_once $currentPath . "/../include/class.xun_miner_fee.php";
include_once $currentPath . "/../include/class.xun_currency.php";

include_once $currentPath . "/../include/class.xun_coins.php";
include_once $currentPath . "/../include/class.xun_payment.php";
include_once $currentPath . "/../include/class.message.php";
include_once $currentPath . "/../include/class.provider.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$log = new Log($logPath, $logBaseName);
$xunXmpp = new XunXmpp($db, $post);
$xunCrypto = new XunCrypto($db, $post, $general);
$xunWallet = new XunWallet($db);
$xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
$xunMinerFee = new XunMinerFee($db, $general, $setting, $log);
$xun_business_service = new XunBusinessService($db);
$xunCurrency = new XunCurrency($db);

$xunCoins = new XunCoins($db, $setting);
$xunPayment = new XunPayment($db, $post, $general, $setting, $xunCrypto, $xunCoins);
$provider = new Provider($db);
$message = new Message($db, $general, $provider);

$process_id = getmypid();
$transaction_type = $argv[1];

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php') . "_" . $transaction_type;
$log = new Log($logPath, $logBaseName);
$path = realpath($logPath);

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

// Process start
$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . "\t $process_name - $transaction_type Start.\n");

$today = date("Y-m-d 00:00:00");

$binance_address = $setting->systemSetting['bcExternalCompanyPoolAddress'];
$external_transfer_service_charge_url = $config['externalTransferServiceChargeURL'];
$trading_fee_address = $setting->systemSetting['marketplaceTradingFeeWalletAddress'];
$pool_fund_out_threshold = $setting->systemSetting['poolFundOutThreshold'];

$wallet_type_list = array('tetherusd', 'tronusdt');
$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . "\t PG Fund In and Auto Fund Out Pool Fund Our\n");

foreach($wallet_type_list as $wallet_type){
    try {
        $db->where('reference', "%$wallet_type%", 'LIKE');
        $db->where('name', 'bcExternalCompanyPoolAddress');
        $binance_address = $db->getValue('system_settings', 'value');

        if(!$binance_address){
            continue;
        }
        
        $db->where('pool_transferred', 0);
        $db->where('status', 'success');
        // $db->where('created_at', $today, '>=');
        $db->where('created_at', $today, '<');
        $db->where('wallet_type', $wallet_type);
        $copyDb= $db->copy();
        $crypto_history_data = $db->get('xun_crypto_history',null,  'id, miner_fee, exchange_rate');

        $db->where('pool_wallet_type', $wallet_type);
        $db->where('gateway_type', 'PG');
        $db->where('pool_transferred', 0);
        $db->where('status', "confirmed");
        $db->where('created_at', $today, "<"); // Cut off time
        $db->orderBy('id', "ASC");
        $fund_out_details_data = $db->get('xun_crypto_fund_out_details', null, 'id, pool_amount, exchange_rate');

        $total_miner_fee = 0;
        $total_miner_fee_usd = 0;
        if ($crypto_history_data) {
            foreach ($crypto_history_data as $key => $value) {
                $miner_fee = $value['miner_fee'];
                $exchange_rate = $value['exchange_rate'];
                $total_miner_fee = bcadd($total_miner_fee, $miner_fee, 8);

                $miner_fee_usd = $xunCurrency->get_conversion_amount('usd', $wallet_type, $miner_fee, true, $exchange_rate);
                $total_miner_fee_usd = bcadd($total_miner_fee_usd, $miner_fee_usd, 2);
            }

            $crypto_history_ids = array_column($crypto_history_data, 'id');
            $total_miner_fee_satoshi = $xunCrypto->get_satoshi_amount($wallet_type, $total_miner_fee);

        }

        if($fund_out_details_data){
            foreach ($fund_out_details_data as $fund_out_key => $fund_out_value){
                $pool_amount = $fund_out_value['pool_amount'];
                $exchange_rate = $fund_out_value['exchange_rate'];
                $total_miner_fee = bcadd($total_miner_fee, $pool_amount, 8);

                $miner_fee_usd = $xunCurrency->get_conversion_amount('usd', $wallet_type, $pool_amount, true, $exchange_rate);
                $total_miner_fee_usd = bcadd($total_miner_fee_usd, $miner_fee_usd, 2);

            }

            $fund_out_details_ids = array_column($fund_out_details_data, 'id');
            $total_miner_fee_satoshi = $xunCrypto->get_satoshi_amount($wallet_type, $total_miner_fee);
        }

        $wallet_info = $xunCrypto->get_wallet_info($trading_fee_address, $wallet_type);
        $log->write(date('Y-m-d H:i:s') . " Wallet Info: ".json_encode($wallet_info)."\n");
                  
        $miner_fee_wallet_type = strtolower($wallet_info[$wallet_type]['feeType']);

        if ($total_miner_fee_usd > $pool_fund_out_threshold) {
            $tx_obj = new stdClass();
            $tx_obj->userID = '0';
            $tx_obj->address = $trading_fee_address;

            $date = date("Y-m-d H:i:s");
            //echo "insert tx token";
            $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);
            //echo "transaction_token = $transaction_token";
            $xunWallet = new XunWallet($db);
            $transactionObj->status = 'pending';
            $transactionObj->transactionHash = '';
            $transactionObj->transactionToken = $transaction_token;
            $transactionObj->senderAddress = $trading_fee_address;
            $transactionObj->recipientAddress = $binance_address;
            $transactionObj->userID = '';
            $transactionObj->senderUserID = 'trading_fee';
            $transactionObj->recipientUserID = 'Pool Fund Out';
            $transactionObj->walletType = $wallet_type;
            $transactionObj->amount = $total_miner_fee;
            $transactionObj->addressType = 'external_transfer';
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
            $txHistoryObj->senderAddress = $trading_fee_address;
            $txHistoryObj->recipientAddress = $binance_address;
            $txHistoryObj->senderUserID = 'trading_fee';
            $txHistoryObj->recipientUserID = 'Pool Fund Out';
            $txHistoryObj->walletType = $wallet_type;
            $txHistoryObj->amount = $total_miner_fee;
            $txHistoryObj->transactionType = 'external_transfer';
            $txHistoryObj->referenceID = '';
            $txHistoryObj->createdAt = $date;
            $txHistoryObj->updatedAt = $date;
            $txHistoryObj->type = 'in';
            $txHistoryObj->gatewayType = "BC";

            $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
            $transaction_history_id = $transaction_history_result['transaction_history_id'];
            $transaction_history_table = $transaction_history_result['table_name'];

            $updateWalletTx = array(
                "transaction_history_id" => $transaction_history_id,
                "transaction_history_table" => $transaction_history_table
            );
            $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);

            $pool_params = array(
                "receiverAddress" => $binance_address,
                "amount" => $total_miner_fee_satoshi,
                "walletType" => $wallet_type,
                "walletTransactionID" => $transaction_id,
                "transactionToken" => $transaction_token,

            );

            $company_wallet_result = $post->curl_post($external_transfer_service_charge_url, $pool_params, 0);

            $log->write(date('Y-m-d H:i:s') . " Result: ".json_encode($company_wallet_result)."\n");
            
            if($company_wallet_result['code'] == 1){
                $miner_fee_wallet_info = $xunCrypto->get_wallet_info($trading_fee_address, $miner_fee_wallet_type);
                
                $miner_fee_unit_conversion = strtolower($miner_fee_wallet_info[$miner_fee_wallet_type]['unitConversion']);

                $return = $xunCrypto->calculate_miner_fee($trading_fee_address, $binance_address, $total_miner_fee_satoshi, $wallet_type, 1);
                

                if($return['code'] == 0){
                    $txFee = $return['data']['txFee'];
                    
                    if($miner_fee_wallet_type != $wallet_type){
                        $miner_fee_decimal = log10($miner_fee_unit_conversion);

                        $signed_tx_fee_decimal = bcdiv($txFee, $miner_fee_unit_conversion, $miner_fee_decimal);
                        
                        if($signed_tx_fee_decimal > 0){
                            $miner_fee_tx_data = array(
                                "address" => $trading_fee_address,
                                "reference_id" => $transaction_id,
                                "reference_table" => "xun_wallet_transaction",
                                "type" => "miner_fee_payment",
                                "wallet_type" => $miner_fee_wallet_type,
                                "debit" => $signed_tx_fee_decimal,
                            );
                            $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);
                        }
                    }

                }

                $minerFeeBalance = $xunMinerFee->getMinerFeeBalance($trading_fee_address, $miner_fee_wallet_type);
                $minerFeeBalanceUSD = $xunCurrency->get_conversion_amount('usd', $miner_fee_wallet_type, $minerFeeBalance);
                
                $miner_fee_threshold = $setting->systemSetting['bcMinerFeeLowThreshold'];

                if($minerFeeBalanceUSD < $miner_fee_threshold ){
                    $tag = "Low Miner Fee Balance";
                    $message_d = "Type: Service Charge Address\n";
                    $message_d .= "Address: ".$trading_fee_address."\n";
                    $message_d .= "Miner Fee:".$signed_tx_fee_decimal."\n";
                    $message_d .= "Miner Fee Wallet Balance: ".$minerFeeBalance."\n";
                    $message_d .= "Wallet Type:".$miner_fee_wallet_type."\n";
                    $message_d .= "Time: ".date("Y-m-d H:i:s")."\n";

                    $thenux_params["tag"]         = $tag;
                    $thenux_params["message"]     = $message_d;
                    $thenux_params["mobile_list"] = $xun_numbers;
                    $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_issues");
                }
                $updateArray = array(
                    "pool_transferred" => 1,
                    "updated_at" => date("Y-m-d H:i:s"),
                );

                if($crypto_history_ids){
                    $db->where('id', $crypto_history_ids, 'IN');
                    $updated = $db->update('xun_crypto_history', $updateArray);

                    if(!$updated){
                        echo "Xun Crypto History Update Failed".$db->getLastError()."\n";
                    }
                }
                
                if($fund_out_details_ids){
                    $db->where('id', $fund_out_details_ids, 'IN');
                    $updated = $db->update('xun_crypto_fund_out_details', $updateArray);

                    if(!$updated){
                        echo "Xun Crypto Fund Out Details Failed".$db->getLastError()."\n";
                    }
                }

                $tag = "Pool Fund Out";

                $message_d = "Pool Amount:" .$total_miner_fee."\n";
                $message_d .= "Pool Amount (USD):".$total_miner_fee_usd."\n";
                $message_d .= "Wallet Type:".$wallet_type."\n";
                $message_d .= "Time: " . date("Y-m-d H:i:s") . "\n";

                $thenux_params["tag"] = $tag;
                $thenux_params["message"] = $message_d;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");


            }
            else{
                $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . "\t Failed Pool Fund Out: $total_miner_fee \n");

                $updateData = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $db->where('id', $transaction_id);
                $db->update('xun_wallet_transaction', $updateData);

                $db->where('id', $transaction_history_id);
                $db->update($transaction_history_table, $updateData);
                
                $tag = "Failed Pool Fund Out";

                $message_d = "Pool Amount:" .$total_miner_fee."\n";
                $message_d .= "Pool Amount (USD):".$total_miner_fee_usd."\n";
                $message_d .= "Wallet Type:".$wallet_type."\n";
                $message_d .= "Time: " . date("Y-m-d H:i:s") . "\n";

                $thenux_params["tag"] = $tag;
                $thenux_params["message"] = $message_d;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

            }

        } else {
            $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . "\t Below Pool Threshold: $total_miner_fee_usd USD\n");

            $tag = "Below Pool Threshold";

            $message_d = "Pool Amount:" .$total_miner_fee."\n";
            $message_d .= "Pool Amount (USD):".$total_miner_fee_usd."\n";
            $message_d .= "Wallet Type:".$wallet_type."\n";
            $message_d .= "Time: " . date("Y-m-d H:i:s") . "\n";

            $thenux_params["tag"] = $tag;
            $thenux_params["message"] = $message_d;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

        }

    } catch (Exception $e) {
        $msg = $e->getMessage();

        $message_d = $process_name . "\n";
        $message_d .= "Time : " . date("Y-m-d H:i:s");
        $message_d .= $msg;

        $thenux_params["tag"] = "Process Error";
        $thenux_params["message"] = $message_d;
        $thenux_params["mobile_list"] = $xun_numbers;
        $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");
    }

}