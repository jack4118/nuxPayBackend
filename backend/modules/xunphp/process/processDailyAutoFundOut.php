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
include_once $currentPath . "/../include/class.xun_coins.php";
include_once $currentPath . "/../include/class.xun_payment.php";

include_once $currentPath . "/../include/class.abstract_xun_user.php";
include_once $currentPath . "/../include/class.xun_user_model.php";
include_once $currentPath . "/../include/class.xun_user_service.php";
include_once $currentPath . "/../include/class.xun_business_model.php";
include_once $currentPath . "/../include/class.xun_business_service.php";
include_once $currentPath . "/../include/class.xun_livechat_model.php";
include_once $currentPath . "/../include/class.xun_miner_fee.php";
include_once $currentPath . "/../include/class.message.php";
include_once $currentPath . "/../include/class.provider.php";

$process_id = getmypid();
$fund_out_sender = $argv[1];

if($fund_out_sender == "") echo date('Y-m-d H:i:s')." $process_id fund out sender params missing\n";

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php')."_".$fund_out_sender;
$path = realpath($logPath);

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post       = new post();
$setting    = new Setting($db);
$general    = new General($db, $setting);
$log        = new Log($logPath, $logBaseName);
$xunXmpp    = new XunXmpp($db, $post);
$xunCrypto  = new XunCrypto($db, $post, $general);
$xunWallet  = new XunWallet($db);
$xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
$xunMinerFee = new XunMinerFee($db, $general, $setting, $log);
$xun_business_service = new XunBusinessService($db);

$xunCoins = new XunCoins($db, $setting);
$xunPayment = new XunPayment($db, $post, $general, $setting, $xunCrypto, $xunCoins);
$provider = new Provider($db);
$message = new Message($db, $general, $provider);

$process_name = $logBaseName;
$file_path = basename(__FILE__);
$output_path = $process_name . '.log';

$sender_list["service_charge"] = array(
                                        "system_setting" => array("marketplaceCompanyAccWalletAddress", "serviceChargeFundOutAddress"),
                                        "fund_out_type" => "external",
                                        "wallet_server" => "trading_fee",
                                        "balance_keep_in_address" => 10, // USD
                                    );

if(!isset($sender_list[$fund_out_sender])){
    $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name '$fund_out_sender' fund out sender not in list.\n");
    exit();
}

$db->where("name", $process_name);
$db->where("arg1", $fund_out_sender);
$process = $db->getOne("processes");

// check process status
if(!$process){
    $insertData = array(
        "name" => $process_name,
        "file_path" => $file_path,
        "output_path" => $output_path,
        "process_id" => $process_id,
        "arg1" => $fund_out_sender,
        "created_at" => date("Y-m-d H:i:s"),
        "updated_at" => date("Y-m-d H:i:s")
    );

    $process_row_id = $db->insert("processes", $insertData);
}else{

    if($process["disabled"]==1){
        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name Disabled.\n");
        exit();
    }

    /*if($process["process_id"]){
        // check running or dead
        exec("ps ".$process["process_id"], $pidOutput, $pidResult);

        if(count($pidOutput) >= 2){
            $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name previous process is still running\n");
            exit();
        }

        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name previous process halfway dead.\n");
        exit();
    }*/

    $updateData = [];
    $updateData["process_id"] = $process_id;
    $updateData["updated_at"] = date("Y-m-d H:i:s");

    $process_row_id = $process["id"];

    $db->where("id", $process_row_id);
    $db->update("processes", $updateData);
}

// Process start
$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name Start.\n");
echo date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name Start.\n";
try {
    // get fund out receiver account info
    // get fund out sender account info (wallet address, wallet server info)
    $sender_wallet_server_webservices = $config["tradingFeeURL_walletTransaction"];
    $fund_out_type = $sender_list[$fund_out_sender]["fund_out_type"]; // internal / external
    $fund_out_wallet_server = $sender_list[$fund_out_sender]["wallet_server"];
    $balance_keep_in_address = $sender_list[$fund_out_sender]["balance_keep_in_address"];

    //print_r($sender_list[$fund_out_sender]["system_setting"]);
    $db->where("name", $sender_list[$fund_out_sender]["system_setting"], "IN");
    $sender_setting_res = $db->get("system_settings", null, "name, value, reference");
    //print_r($sender_setting_res);


    foreach ($sender_setting_res as $key => $value) {
        //echo "name = ".$value["name"]."\n";
        if($value["name"] == "serviceChargeFundOutAddress"){
            $sender_setting_row["serviceChargeFundOutAddress"][$value["reference"]] = $value["value"];
            $wallet_type_list[] = $value["reference"];
        }else{
            //print_r($value);
            $sender_setting_row[$value["name"]] = $value["value"];
        }
    }


    //print_r($sender_setting_row);
    switch ($fund_out_sender) {
        case 'service_charge':
            $sender_wallet_address = $sender_setting_row["marketplaceCompanyAccWalletAddress"];
            $service_charge_fund_out_addr = $sender_setting_row["serviceChargeFundOutAddress"];
            break;
        
        default:
            $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t sender account $fund_out_sender info/addresss not found.\n");
            exit();
            break;
    }

    // get all coin
    // check USD value by each coin
    //echo "sender_wallet_address = $sender_wallet_address\n";
    //print_r($service_charge_fund_out_addr);
    //$wallet_type = "tetherusd";
    $coin_list = $xunCrypto->get_wallet_info($sender_wallet_address,$wallet_type_list);

    if(!$coin_list){
        $log->write(date('Y-m-d H:i:s') . " Get wallet info api error.\n");
        exit;
    }

    //print_r($coin_list);
    foreach ($coin_list as $key => $value) {
        $miner_wallet_list[$value["feeType"]] = $value["feeType"];
        $coin_list_ary[$value["walletType"]] =  $value;
    }
    //echo "coin_list_ary: ";
    //print_r($coin_list_ary);
    foreach($coin_list_ary as $wallet_type => $coin_data){

        //get today profit
        // $db->where("wallet_type", $wallet_type);
        // $db->where("recipient_address", $sender_wallet_address);
        // $db->where("created_at", date("Y-m-d 00:00:00"), ">=");
        // $todayAmt = $db->getValue("xun_wallet_transaction", "SUM(amount)");
        // $todayAmt = $todayAmt ?? 0;

        
        //get today profit
        $seekToday = true;
        $incrementLimit = 100;
        $nextLimit = 0;
        $lastTotal = 0;

        do {

            $todayAmt = 0;

            $historyParams["wallet_type"] = $wallet_type;
            $historyParams["address"] = $sender_wallet_address;
            $historyParams["transaction_list_limit"] = ($nextLimit==0 ? $incrementLimit : $nextLimit);
            $historyParams["order_by"] = "DESC";

            $historyResult = $xunCrypto->crypto_get_transaction_history_list($historyParams);

            if($historyResult['status']=="ok") {
                
                $historyData = $historyResult['data']['transactions'];

                if(count($historyData)==0) {
                    $seekToday = false;
                } else {

                    foreach($historyData as $trx) {

                        $trxId = $trx['id'];
                        $trxAmount = $trx['amountCoin'];
                        $trxTime = $trx['time'];
                        $trxStatus = $trx['status'];
                        $trxType = $trx['type'];
                        $trxDate = date("Ymd", strtotime($trxTime));

                        //echo "\n>>".$trxDate."|".$trxType."|".$trxStatus."|".$trxAmount;
                        if($trxDate==date("Ymd") && $trxType=="receive" && ($trxStatus=="confirmed" || $trxStatus=="pending" ) ) {
                            $todayAmt = bcadd($todayAmt, $trxAmount);
                        }

                        $last_transaction_date = $trxDate;
                        $last_transaction_id = $trxId;

                        if($trxDate!=date("Ymd")) {
                            $seekToday = false;
                        }
                    }
                }

                if(count($historyData)==$lastTotal) {
                    $seekToday = false;
                }

                $lastTotal = count($historyData);
                $nextLimit = count($historyData) + $incrementLimit;

            } else {
                $log->write(date('Y-m-d H:i:s') . "Transaction history api error.\n");
                exit;
            }
            
        } while ($seekToday);


        //if($coin_data[])
        $decimal = log($coin_data["unitConversion"], 10);
        // Formula : (balance / unitConversion) x USD exchangeRate = USD value
        //echo bcdiv($coin_data["balance"], $coin_data["unitConversion"], $decimal).",decimal = $decimal\n";
        $miner_fee_wallet_type = $coin_data["feeType"];
        $miner_fee_wallet_info = $coin_list_ary[$miner_fee_wallet_type];
        $miner_fee_unit_conversion = $miner_fee_wallet_info["unitConversion"];
        $sender_miner_fee_balance = $xunMinerFee->getMinerFeeBalance($sender_wallet_address, $miner_fee_wallet_type);

        $coin_balance = bcsub($coin_data["balance"], $todayAmt, $decimal);
        $coin_balance = ($coin_balance < 0 ? 0 : $coin_balance);
        $coin_amount = bcdiv($coin_balance, $coin_data["unitConversion"], $decimal);
        
        //echo "\n\n>>>".$wallet_type." => ".$coin_amount." | ". $coin_balance." | ".$coin_data['unitConversion']. " | ".$decimal;
        
        if($miner_fee_wallet_type == $wallet_type){
            $coin_amount -= $sender_miner_fee_balance;
        }

        $usd_value = bcmul($coin_amount, $coin_data["exchangeRate"]["usd"], $decimal);
        $value["decimal"] = $decimal;
        $value["usd_value"] = $usd_value;

        $total_wallet_coin_amount = $coin_amount;
        /*if($miner_fee_wallet_type == $wallet_type){
            $coin_amount -= $sender_miner_fee_balance;
        }*/

        $log->write(date('Y-m-d H:i:s') . "WalletType: ".$wallet_type." | todayAmt: ".$todayAmt." | Balance: ".$coin_data['balance']." | CoinAmount: ".$coin_amount." USDValue: ".$usd_value."\n");

        //echo "\nCheckUSDTValue: ".$usd_value."|".$coin_data["balance"]."|".$coin_amount2."|".$sender_miner_fee_balance;
        // build fund out list
        if($usd_value > 10 && isset($service_charge_fund_out_addr[$wallet_type])){
            //echo "more than 100, ".$coin_data["walletType"].", Value = $usd_value\n";
            
            // transfer 95 USD
            // if($wallet_type == "ethereum"){
            //     $transfer_amount = bcdiv($usd_value,$coin_data["exchangeRate"]["usd"],8);
            // }else{
            //     $transfer_amount = bcdiv($usd_value,$coin_data["exchangeRate"]["usd"],8);
            // }
            // //$transfer_amount = 1;
            // $transfer_amount_satoshi = bcmul($transfer_amount,$coin_data["unitConversion"],0);

            // $transfer_amount = bcdiv($coin_balance, $coin_data["unitConversion"], $decimal);
            // $transfer_amount_satoshi = (int)$coin_balance;

            $transfer_amount = $coin_amount;
            $transfer_amount_satoshi = bcmul($coin_amount, $coin_data["unitConversion"]);

            //echo "transfer_amount = $transfer_amount, transfer_amount_satoshi = $transfer_amount_satoshi\n";
            //echo "\nusd_value: ".$wallet_type." | ". $transfer_amount." | ".$transfer_amount_satoshi;

            // get minerfee
            $post_params = [];
            $post_params["senderAddress"] = $sender_wallet_address;
            $post_params["receiverAddress"] = $service_charge_fund_out_addr[$wallet_type];
            $post_params["amount"] = $transfer_amount_satoshi;
            $post_params["walletType"] = $wallet_type;
            $post_params["minerCalculation"] = 1; // 1 is calculate miner fees only, other fully verify

            $minerfee_res = $post->curl_crypto("calculateMinerFee", $post_params, 2);
            echo "miner fee 1";
            print_r($minerfee_res);

            $log->write(date('Y-m-d H:i:s') . "Calculate Miner Fee | WalletType: ".$wallet_type." | ".json_encode($post_params)."\n");
            $log->write(date('Y-m-d H:i:s') . "Calculate Miner Fee Return | WalletType: ".$wallet_type." | ".json_encode($minerfee_res)."\n");


            if($minerfee_res["status"] == "error" && $minerfee_res["statusMsg"]){
                $log->write(date('Y-m-d H:i:s') . "Calculate Miner Fee ERROR | WalletType: ".$wallet_type." | ".json_encode($minerfee_res)."\n");
                $tag = "Comp Profit F/O Error";
                $message_d = "Coin : ".$wallet_type."\n";
                $message_d .= "Balance:" .$coin_amount."\n";
                $message_d .= "Reason : ".$minerfee_res["statusMsg"]."\n";
                $message_d .= "Time: ".date("Y-m-d H:i:s")."\n";

                $thenux_params["tag"]         = $tag;
                $thenux_params["message"]     = $message_d;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_issues");
                continue;
            }

            if($minerfee_res["status"] == "ok"){
                $minerFeeRequired = $minerfee_res["data"]["txFee"];

                //$transfer_amount_satoshi -= $minerFeeRequired;

                if($wallet_type == $miner_fee_wallet_type){
                    $transfer_amount_satoshi = bcsub($transfer_amount_satoshi, $minerFeeRequired, 0);

                    $transfer_amount = bcdiv($transfer_amount_satoshi, $coin_data["unitConversion"], $decimal);
    
                }
                
                $post_params = [];
                $post_params["senderAddress"] = $sender_wallet_address;
                $post_params["receiverAddress"] = $service_charge_fund_out_addr[$wallet_type];
                $post_params["amount"] = $transfer_amount_satoshi;
                $post_params["walletType"] = $wallet_type;

                $minerfee_res = $post->curl_crypto("calculateMinerFee", $post_params, 2);

                $log->write(date('Y-m-d H:i:s') . "Calculate Miner Fee | WalletType: ".$wallet_type." | ".json_encode($post_params)."\n");
                $log->write(date('Y-m-d H:i:s') . "Calculate Miner Fee Return | WalletType: ".$wallet_type." | ".json_encode($minerfee_res)."\n");
                echo "miner fee 2";
                print_r($minerfee_res);

            // if($minerfee_res["status"] == "ok"){
                $miner_fee_wallet_type = strtolower($miner_fee_wallet_type);
                $tx_fee = $minerfee_res["data"]["txFee"] ?: $minerfee_res["data"]["minerFees"]["txFee"];

                $miner_fee_decimal = log10($miner_fee_unit_conversion);
                $tx_fee_decimal = bcdiv($tx_fee, $miner_fee_unit_conversion, $miner_fee_decimal);


                echo "\n\n\nHere - $tx_fee_decimal <= $sender_miner_fee_balance | $sender_wallet_address $miner_fee_wallet_type\n\n";
                if($miner_fee_wallet_type != "ethereum" || $tx_fee_decimal <= $sender_miner_fee_balance){
                    $tx_obj = new stdClass();
                    $tx_obj->userID = '0';
                    $tx_obj->address = $sender_wallet_address;
    
                    //echo "insert tx token";
                    $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);
                    //echo "transaction_token = $transaction_token";
                    $xunWallet = new XunWallet($db);
                    $transactionObj->status = 'pending';
                    $transactionObj->transactionHash = '';
                    $transactionObj->transactionToken = $transaction_token;
                    $transactionObj->senderAddress = $sender_wallet_address;
                    $transactionObj->recipientAddress = $service_charge_fund_out_addr[$wallet_type];
                    $transactionObj->userID = '';
                    $transactionObj->senderUserID = 'Company Profit Fund Out';
                    $transactionObj->recipientUserID = '';
                    $transactionObj->walletType = $wallet_type;
                    $transactionObj->amount = $transfer_amount;
                    $transactionObj->addressType = 'external_transfer';
                    $transactionObj->transactionType = 'send';
                    $transactionObj->escrow = 0;
                    $transactionObj->referenceID = $service_charge_transaction_id;
                    $transactionObj->escrowContractAddress = '';
                    $transactionObj->createdAt = $date;
                    $transactionObj->updatedAt = $date;
                    $transactionObj->expiresAt = '';
                    $transactionObj->fee = '';
                    $transactionObj->feeUnit = '';
    
    //print_r($transactionObj);
                $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);  

                    $txHistoryObj->paymentDetailsID = '';
                    $txHistoryObj->status = 'pending';
                    $txHistoryObj->transactionID = "";
                    $txHistoryObj->transactionToken = $transaction_token;
                    $txHistoryObj->senderAddress = $sender_wallet_address;
                    $txHistoryObj->recipientAddress = $service_charge_fund_out_addr[$wallet_type];
                    $txHistoryObj->senderUserID = 'Company Profit Fund Out';
                    $txHistoryObj->recipientUserID = '';
                    $txHistoryObj->walletType = $wallet_type;
                    $txHistoryObj->amount = $transfer_amount;
                    $txHistoryObj->transactionType = 'external_transfer';
                    $txHistoryObj->referenceID = $service_charge_transaction_id;
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

                    $company_pool_params = array(
                            "receiverAddress" => $service_charge_fund_out_addr[$wallet_type],
                            "amount" => $transfer_amount_satoshi,
                            "walletType" => $wallet_type,
                            "walletTransactionID" => $transaction_id,
                            "transactionToken" => $transaction_token,
    
                        );
    
                    //echo "company_pool_params = ".$config["companyProfitWallet"];
                    print_r($company_pool_params);
                    $company_wallet_result = $post->curl_post($config["companyProfitWallet"], $company_pool_params, 0);

                    $log->write(date('Y-m-d H:i:s') . "Fund Out | WalletType: ".$wallet_type." | ".json_encode($company_pool_params)."\n");
                    $log->write(date('Y-m-d H:i:s') . "Fund Out Return | WalletType: ".$wallet_type." | ".json_encode($company_wallet_result)."\n");

                    $calculate_miner_fee_result = $company_wallet_result["result"];
                    if(($miner_fee_wallet_type == "ethereum" || $miner_fee_wallet_type == 'tron') && $calculate_miner_fee_result["status"] == "ok"){
                        $signed_tx_fee = $calculate_miner_fee_result["data"]["txFee"] ?: $calculate_miner_fee_result["data"]["minerFees"]["txFee"];
                        $signed_tx_fee_decimal = bcdiv($signed_tx_fee, $miner_fee_unit_conversion, $miner_fee_decimal);

                        if($signed_tx_fee_decimal > 0){
                            $miner_fee_tx_data = array(
                                "address" => $sender_wallet_address,
                                "reference_id" => $transaction_id,
                                "reference_table" => "xun_wallet_transaction",
                                "type" => "miner_fee_payment",
                                "wallet_type" => $miner_fee_wallet_type,
                                "debit" => $signed_tx_fee_decimal,
                            );
                            $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);
                        }
                    }

                    $fund_out_ary[] = array("transferAmount" => $transfer_amount,
                                            "walletType" => $wallet_type,
                    );
                    //echo "company_wallet_result = ";
                    //print_r($company_wallet_result);
                }
            }else{
                // $tag = "Daily Fundout Profit Process Error";
                $tag = "Comp Profit F/O Error";
                $message_d = "Coin : ".$wallet_type."\n";
                $message_d .= "Balance:" .$coin_amount."\n";
                $message_d .= "Reason : ".$minerfee_res["statusMsg"]."\n";
                $message_d .= "Time: ".date("Y-m-d H:i:s")."\n";

                $thenux_params["tag"]         = $tag;
                $thenux_params["message"]     = $message_d;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
            }

        }
    } 
    print_r($fund_out_ary);
    // $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name End.\n");
    //$log->write(date('Y-m-d H:i:s') . " Fund Out Data : \n".implode("\n",$fund_out_ary));
    
    echo date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name End.\n";
    //echo date('Y-m-d H:i:s') . " Fund Out Data : \n".implode("\n",$fund_out_ary)."\n";

    if(count($fund_out_ary) > 0){
        $tag = "Daily Fundout Profit Report";
        $message_d = "";

        foreach ($fund_out_ary as $key => $value) {
            $message_d .= "Coin = " .$value["walletType"]."\n";
            $message_d .= "Amount = ".$value["transferAmount"]."\n\n";
        }


        $message_d .= "Time: ".date("Y-m-d H:i:s")."\n";
        print_r($message_d);
        $thenux_params["tag"]         = $tag;
        $thenux_params["message"]     = $message_d;
        $thenux_params["mobile_list"] = $xun_numbers;
        $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
    }

    // get coin miner fee
    /*if($fund_out_type == "external"){
        foreach ($miner_wallet_list as $key => $value) {
            $crypto_webservices = $config["cryptoWalletUrl"];
            $crypto_parner = $config["cryptoBCPartnerSite"];
            $minerfee_list[$key] = $coin_list[$key];

            // get minerfee
            $post_params = [];
            $post_params["senderAddress"] = $sender_wallet_address;
            $post_params["receiverAddress"] = $receiver_wallet_address_list;
            $post_params["amount"] = 1;
            $post_params["walletType"] = $minerfee_list[$key]["walletType"];
            $post_params["minerCalculation"] = 1; // 1 is calculate miner fees only, other fully verify

            $minerfee_res = $post->curl_crypto("calculateMinerFee", $post_params, 2);
            print_r($minerfee_res);
            if($minerfee_res["status"] == "ok"){
                $minerfee_list[$key]["minerfee"] = $minerfee_res["data"]["txFee"];
            }else{
                $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$minerfee_list[$key]["walletName"]." Get minerfee Error: ".json_encode($minerfee_res)."\n");
                // exit();
                continue;
            }
            unset($minerfee_res);
        }
    }
    //print_r($minerfee_list);

    // print_r($coin_list);
    foreach ($coin_list as $key => &$value) {
        // will return how many 0 E.g 100000000,10 get 8
        $decimal = log($value["unitConversion"], 10);

        // Formula : (balance / unitConversion) x USD exchangeRate = USD value
        $usd_value = bcmul(bcdiv($value["balance"], $value["unitConversion"], $decimal), $value["exchangeRate"]["usd"], $decimal);
        $value["decimal"] = $decimal;
        $value["usd_value"] = $usd_value;

        // build fund out list
        if($usd_value > 100){
            // check balance after minerfee
            $minerfee = 0;
            $minerfee_details = $minerfee_list[$value["feeType"]];
            if(isset($minerfee_details["minerfee"]) && $minerfee_details["minerfee"] > 0) $minerfee = $minerfee_details["minerfee"];

            // get latest balance
            unset($latest_details_list);
            $latest_details_list = $xunCrypto->get_wallet_info($sender_wallet_address);

            $latest_coin_details = $latest_details_list[$value["walletType"]];
            $latest_minerfee_details = $latest_details_list[$value["feeType"]];

            // miner fee wallet need keep 5 USD for pay other fund out miner fee
            if(in_array($value["walletType"], $miner_wallet_list)){ 
                $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." Same minerfee wallet\n");
                // calculate Fund out amount Remark: keep 5 USD in address 
                // Formula : (USD / USD exchangeRate) * unitConversion = Coin Value
                // Formula : latest_balance - minerfee - Coin Value = fund out amount
                $fund_out_amount = $value["balance"] - $minerfee - bcmul(bcdiv($balance_keep_in_address / $value["exchangeRate"]["usd"], $decimal), $value["unitConversion"], $decimal);
            }
            else{
                $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." Diff minerfee wallet\n");
                // check insufficien miner fee
                // $minerfee_wallet_new_balance = $latest_minerfee_details["balance"] - $minerfee;
                
                // // insufficient pay miner fee
                // if($minerfee_wallet_new_balance < 0){
                //     // send notification

                //     $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." Insufficient balance pay miner fee. ".$value["walletType"].": ".$latest_minerfee_details["balance"]." Minerfee: ".$minerfee." Deducted: ".$minerfee_wallet_new_balance."\n");
                //     continue;
                // }

                $fund_out_amount = $value["balance"];
            }
            $value["fund_out_amount"] = "0.1";//$fund_out_amount;

            $value["minerfee_usd_value"] = bcmul(bcdiv($minerfee, $minerfee_details["unitConversion"], log($minerfee_details["unitConversion"], 10)), $minerfee_details["exchangeRate"]["usd"], log($minerfee_details["unitConversion"], 10));
            $value["minerfee"] = $minerfee;
            $fund_out_list[$key] = $value;
        }

        $log->write(date("Y-m-d H:i:s")." ---------------------------------------------------\n");
        $log->write(date("Y-m-d H:i:s")." Name: ".$value["walletName"]." Unit: ".$value["unit"]."\n");
        $log->write(date("Y-m-d H:i:s")." Balance: ".$value["balance"]." USD_Rate: ".$value["exchangeRate"]["usd"]." UnitConversion: ".$value["unitConversion"]." Decimal: ".$decimal."\n");
        $log->write(date("Y-m-d H:i:s")." USD_Value:".$usd_value."\n");
    }

    // loop fund out coin
    print_r($fund_out_list);
    if(!count($fund_out_list)) 
        $log->write(date("Y-m-d H:i:s")." No coin meet fund out condition.\n");
    else
        $log->write(date("Y-m-d H:i:s")." Perform fund out.\n");

    foreach ($fund_out_list as $key => $value) {
        $log->write(date("Y-m-d H:i:s")." ---------------------------------------------------\n");
        $log->write(date("Y-m-d H:i:s")." ($fund_out_type) Fund out Coin: ".$value["walletName"]." Unit: ".$value["unit"]." USD_Value: ".$value["usd_value"]."\n");

        // insert transaction 
        $transactionObj = new stdClass();
        $transactionObj->status = "pending";
        $transactionObj->transactionHash = "";
        $transactionObj->transactionToken = "";
        $transactionObj->senderAddress = $sender_wallet_address;
        $transactionObj->recipientAddress = $receiver_wallet_address_list;
        $transactionObj->userID = "";
        $transactionObj->senderUserID = "";
        $transactionObj->recipientUserID = "";
        $transactionObj->walletType = $value["walletType"];
        $transactionObj->amount = $value["fund_out_amount"];
        $transactionObj->addressType = "internal_transfer";
        $transactionObj->transactionType = "send";
        $transactionObj->escrow = 0;
        $transactionObj->referenceID = "";
        $transactionObj->escrowContractAddress = "";
        $transactionObj->createdAt = date("Y-m-d H:i:s");
        $transactionObj->updatedAt = date("Y-m-d H:i:s");
        $transactionObj->expiresAt = '';

        $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);
        if(empty($transaction_id))
            $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." insert transaction failed.\n");
        else
            $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." transaction_id: $transaction_id\n");

        if($fund_out_type == "external"){
            $crypto_webservices = $config["cryptoWalletUrl"];
            $crypto_parner = $config["cryptoBCPartnerSite"];

            // get noce and sign
            $post_params = [];
            $post_params["senderAddress"] = $sender_wallet_address;
            $post_params["receiverAddress"] = $receiver_wallet_address_list;
            $post_params["amount"] = $value["fund_out_amount"];
            $post_params["walletType"] = $value["walletType"];
            $post_params["minerCalculation"] = 2; // 1 is calculate miner fees only, other fully verify

            $veri_res = $post->curl_crypto("calculateMinerFee", $post_params, 2);
            print_r($minerfee_res);

            // fund out
        }
        else{
            // Internal Fund out
            // call wallet server sign transaction
            // call blockchain
            $post_params = [];
            $post_params["walletTransactionID"] = $transaction_id;
            $post_params["receiverAddress"] = $receiver_wallet_address_list;
            $post_params["amount"] = $value["fund_out_amount"];
            $post_params["walletType"] = $value["walletType"];
           // $fund_out_response = $xunCompanyWallet->fundOut($fund_out_wallet_server, $post_params);

            if($fund_out_response["code"] == "1"){
                $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." Success.\n");
            }else{
                $log->write(date("Y-m-d H:i:s")." ".print_r($fund_out_response, 1)."\n");
                $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." Failed.\n");
            }
        }
    }*/

} catch (Exception $e) {
    $msg = $e->getMessage();

    $message_d = $process_name . "\n";
    $message_d .= "Time : " . date("Y-m-d H:i:s");
    $message_d .= $msg;

    $thenux_params["tag"] = "Process Error";
    $thenux_params["message"] = $message_d;
    $thenux_params["mobile_list"] = ["+60192135135", "+60186757884"];
    // $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_wallet_transaction");
}

$updateData = [];
$updateData["process_id"] = '';
$updateData["updated_at"] = date("Y-m-d H:i:s");

$db->where("id", $process_row_id);
$db->update("processes", $updateData);

$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name End.\n");

// update_monitoring();

// function update_monitoring()
// {
//     global $config;
//     $env = $config["environment"];
//     if ($env == "prod") {

//         $senderUrl = "http://xunmonitoring.backend/server_process_record.php";
//         $fields = array("SERVERNAME" => "SGPRODAPI_PHP_001",
//             "SERVERID" => "i-0f35b94beb3ca6d16",
//             "PUBLICIP" => "",
//             "PRIVATEIP" => "10.2.0.193",
//             "SERVERTYPE" => "t3.large",
//             "PROCESS_NAME" => basename(__FILE__, '.php'),
//             "STATUS" => "active",
//             "URGENCY_LEVEL" => "Critical",
//         );
//         $dataString = json_encode($fields);

//         $ch = curl_init($senderUrl);
//         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
//         curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
//         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//         curl_setopt($ch, CURLOPT_HTTPHEADER, array(
//             'Content-Type: application/json',
//             'Content-Length: ' . strlen($dataString))
//         );
//         curl_setopt($ch, CURLOPT_TIMEOUT, 10);

//         $response = curl_exec($ch);
//         curl_close($ch);

//         return $response;
//     }
// }
