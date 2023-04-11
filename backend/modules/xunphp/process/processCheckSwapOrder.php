<?php 

    $currentPath = __DIR__;
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.general.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.message.php');
    include($currentPath.'/../include/class.webservice.php');
    include($currentPath.'/../include/class.post.php');
    include($currentPath.'/../include/class.provider.php');
    include($currentPath.'/../include/class.account.php');
    include($currentPath.'/../include/class.log.php');
    include($currentPath.'/../include/class.binance.php');
    include($currentPath.'/../include/class.xun_crypto.php');
    include($currentPath.'/../include/class.abstract_xun_user.php');
    include($currentPath.'/../include/class.xun_user_model.php');
    include($currentPath.'/../include/class.xun_business_model.php');
    include($currentPath.'/../include/class.xun_livechat_model.php');
    include($currentPath.'/../include/class.xun_wallet_transaction_model.php');
    include($currentPath.'/../include/class.xun_business_service.php');
    include($currentPath.'/../include/class.xun_wallet.php');
    include($currentPath.'/../include/class.aax.php'); 

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $webservice  = new Webservice($db, "", "");
    $setting = new Setting($db);
    $general = new General($db, $setting);
    $post = new Post($db, $webservice, $msgpack);
    // $partner = new Partner($db, $post, $setting);
    $binance = new Binance(
        $config['swapcoins']['binanceAPIKey'], 
        $config['swapcoins']['binanceAPISecret'], 
        $config['swapcoins']['binanceAPIURL'], 
        $config['swapcoins']['binanceWAPIURL']
    );
    $aax = new AAX(
        $config['aaxAPIKey'],
        $config['aaxAPISecret'],
        $config['aaxAPIURL']
    );
    $provider = new Provider($db);
    $message = new Message($db, $general, $provider);
    $xunCrypto = new XunCrypto($db, $post, $general);

    $logPath     = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    $log         = new Log($logPath, $logBaseName);

    $account = new Account($db, $setting, $message, $provider, $log);        
    
    $source = "processCheckSwapOrder.php";

    
    $verbose = $argv[1];   
    // validStatus is designed for binance 
    $validStatus = array('NEW','PARTIALLY_FILLED','FILLED','CANCELED','PENDING_CANCEL','REJECTED','EXPIRED');
    // validAAXStatus is designed for aax
    $validAAXStatus = array(
        0=>'NEW',//PENDING-NEW
        1=>'NEW',
        2=>'PARTIALLY_FILLED',
        3=>'FILLED',
        4=>'REJECTED',//CANCEL-REJECT
        5=>'CANCELED',
        6=>'REJECTED',
        7=>'EXPIRED',
        8=>'REJECTED');//BUSINESS-REJECT
    $messageCode = array (
        'processError' => '90007' 
    );
    
    // START OF SCRIPT 

    while (true) {

            
        log_process("Start processCheckSwapOrder", "Message");
        
        // get swap history with order status as not 'FILLED'
        $swapOrderRecords = getSwapOrderRecords();        
        foreach($swapOrderRecords as $swapOrderRecord) {            
            $recordID = $swapOrderRecord['id'];
            $orderID = $swapOrderRecord['order_id'];
            $fromSymbol = $swapOrderRecord['from_symbol'];
            $toSymbol = $swapOrderRecord['to_symbol'];
            $userID = $swapOrderRecord['business_id'];
            $username = $swapOrderRecord['business_name'];

            $fromAmount = $swapOrderRecord['from_amount'];
            $toAmount = $swapOrderRecord['to_amount_display'];

            $fromWalletType = $swapOrderRecord['from_wallet_type'];
            $toWalletType = $swapOrderRecord['to_wallet_type'];
            
            $recordStatus = $swapOrderRecord['order_status'];
            
            $commonSymbol = getCommonSymbol($fromSymbol, $toSymbol);

            $providerName =  $swapOrderRecord['provider_name'];       
            $status = $swapOrderRecord['status'];
            log_process("orderID: $orderID", "Debug");
            log_process("providerName: $providerName", "Debug");

            if($providerName=='aax'){

                log_Process("recordStatus $status","Debug");  
                // query aax order

                $queryOrderResult =$aax->retrieveSpotHistoricalOrders($orderID);
                $queryOrderResult =json_decode($queryOrderResult,true);
                if($queryOrderResult['code']!=1){
                    log_Process("query failed : ".json_encode($queryOrderResult));
                    continue;
                }
                log_Process("recordID $recordID orderID $orderID $AAXStatus");
            

                log_Process("queryOrderResult ".json_encode($queryOrderResult), "Debug");
                $AAXStatus = $queryOrderResult['data']['list'][0]['orderStatus'];
                $AAXStatus = $validAAXStatus[$AAXStatus];
                
                if(empty($AAXStatus) || !in_array($AAXStatus, $validStatus)) {
                    log_process("Invalid status ($AAXStatus) for order $orderID", "Error");
                    continue;
                }
                
                log_process("recordID $recordID orderID $orderID $AAXStatus");

                if ($AAXStatus == 'NEW') {
                    // new order 
                    continue;   
                }  
                else {
                    // AAXStatus different 
                    
                    $exchangeRes = updateSwapOrderRecord($AAXStatus, $recordID, $orderID, $userID, $username, $fromAmount, $toAmount, $fromWalletType, $toWalletType);                    
                }

            }
            else if($providerName=='binance'){
                // query binance order
                $queryOrderResult = $binance->queryOrder($commonSymbol, $orderID);
                log_process("queryOrderResult ".json_encode($queryOrderResult), "Debug");
                $binanceStatus = $queryOrderResult['status'];
                
                if(empty($binanceStatus) || !in_array($binanceStatus, $validStatus)) {
                    log_process("Invalid status ($binanceStatus) for order $orderID", "Error");
                    continue;
                }
                
                log_process("recordID $recordID orderID $orderID $binanceStatus");

                if ($binanceStatus == 'NEW') {
                    // new order 
                    continue;   
                }  
                else {
                    // binanceStatus different 
                    
                    $exchangeRes = updateSwapOrderRecord($binanceStatus, $recordID, $orderID, $userID, $username, $fromAmount, $toAmount, $fromWalletType, $toWalletType);                    
                }
            }        
                
        }
                
        log_process("End processCheckSwapOrder", "Message", true);
        
        sleep(30);
    }

    function getCommonSymbol($fromSymbol, $toSymbol) {
        global $db;

        $db->where('from_symbol', $fromSymbol);
        $db->where('to_symbol', $toSymbol);
        $commonSymbol = $db->getValue('xun_swap_setting', "common_symbol");
        return $commonSymbol;
    }

    // END OF SCRIPT 
    function updateSwapOrderRecord($orderStatus, $recordID, $orderID, $userID, $username, $fromAmount, $toAmount, $fromWalletType, $toWalletType){
        global $db, $source, $account, $xunCrypto, $config, $post, $general, $xun_numbers;



        $error = false;
        try {

            if ($orderStatus == 'FILLED') {
                $status = "completed";
            }
            else if (in_array($orderStatus, array('CANCELED', 'REJECTED', 'EXPIRED'))) {
                $status = "canceled";
            }
            else {
                $status = "processing";
            }

            log_process("Updating swap history id: $recordID, order id: $orderID", "Message", true);

            $updateParams = array(
                'status' => $status,
                'order_status' => $orderStatus,
                'updated_at' => date( 'Y-m-d H:i:s' ),
            );
            $db->where('id', $recordID);
            $db->where('order_id', $orderID);
            $db->update('xun_swap_history', $updateParams);
        } catch (Exception $e) {
            $error = true;
        }
        
        if ($db->getLastErrno() !== 0 || $error) {
            // update error
            $returnData = array(
                'status' => 'error',
                'statusMsg' => "Failed to update xun_swap_history",
                'data'   => $db->getLastError(),                                                        
            );            

            log_process("Failed to update swap history id: $recordID, order id: $orderID", "Error", true);

            $errorParams = array(
                'errorMsg'  => $returnData['statusMsg'],
                'errorInfo' => $returnData['data'],
                'source'    => $source,
            );
            notifyError($errorParams); // send notification             
            return $returnData;
        }

        $fromSatoshiAmount = $xunCrypto->get_satoshi_amount($fromWalletType, $fromAmount);
        $toSatoshiAmount = $xunCrypto->get_satoshi_amount($toWalletType, $toAmount);

        if ($orderStatus != 'FILLED') {
            $returnData = array(
                'status' => 'error',
                'statusMsg' => "Swap order id $orderID is not FILLED",
                'data'   => $orderStatus,                                                        
            );            

            $errorParams = array(
                'errorMsg'  => $returnData['statusMsg'],
                'errorInfo' => $returnData['data'],
                'source'    => $source,
            );

            log_process("Swap history id: $recordID, order id: $orderID is not yet filled.", "Message", true);
            //notifyNotFILLED($errorParams); // send notification             
            return $returnData;
        }

        // Retrieve the business' internal address
        $db->where('user_id', $userID);
        $db->where('address_type', 'nuxpay_wallet');
        $db->where('active', 1);
        $receiverAddress = $db->getValue('xun_crypto_user_address', "address");

        // Retrieve the company address
        $db->where('name', "swapInternalAddress");
        $senderAddress = $db->getValue('system_settings', "value");

        if ($orderStatus == "FILLED") {
            // If it's filled, need to credit the swapped amount to the user


            // Need to perform internal transfer
            $txObj = new stdClass();
            $txObj->userID = "0";
            $txObj->address = $senderAddress;

            log_process("Getting transaction token.", "Message", true);

            $xunBusinessService = new XunBusinessService($db);
            $transactionToken = $xunBusinessService->insertCryptoTransactionToken($txObj);

            // Insert into wallet transaction table
            $transactionObj = new stdClass();
            $transactionObj->status = "pending";
            $transactionObj->transactionHash = "";
            $transactionObj->transactionToken = $transactionToken;
            $transactionObj->senderAddress = $senderAddress;
            $transactionObj->recipientAddress = $receiverAddress;
            $transactionObj->userID = "0";
            $transactionObj->senderUserID = "swap_wallet";
            $transactionObj->recipientUserID = $userID;
            $transactionObj->walletType = $toWalletType;
            $transactionObj->amount = $toAmount;
            $transactionObj->addressType = "nuxpay_wallet";
            $transactionObj->transactionType = "receive";
            $transactionObj->escrow = 0;
            $transactionObj->referenceID = $recordID;
            $transactionObj->message = 'swap_from';
            $transactionObj->escrowContractAddress = '';
            $transactionObj->createdAt = date("Y-m-d H:i:s");
            $transactionObj->updatedAt = date("Y-m-d H:i:s");
            $transactionObj->expiresAt = '';

            log_process("Inserting into user wallet transaction.", "Message", true);

            var_dump($transactionObj);

            $xunWallet = new XunWallet($db);
            $walletTransactionID = $xunWallet->insertUserWalletTransaction($transactionObj);

            $txHistoryObj->status = "pending";
            $txHistoryObj->transactionID = "";
            $txHistoryObj->transactionToken = $transactionToken;
            $txHistoryObj->senderAddress = $senderAddress;
            $txHistoryObj->recipientAddress = $receiverAddress;
            $txHistoryObj->senderUserID = "swap_wallet";
            $txHistoryObj->recipientUserID = $userID;
            $txHistoryObj->walletType = strtolower($toWalletType);
            $txHistoryObj->amount = $toAmount;
            $txHistoryObj->transactionType = 'swap_from';
            $txHistoryObj->referenceID = $recordID;
            $txHistoryObj->createdAt = $date;
            $txHistoryObj->updatedAt = $date;
            // $txHistoryObj->fee = $final_miner_fee;
            // $txHistoryObj->feeWalletType = $miner_fee_wallet_type;
            // $txHistoryObj->exchangeRate = $exchangeRate;
            // $txHistoryObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
            $txHistoryObj->type = 'out';
            $txHistoryObj->gatewayType = "BC";

            $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);

            $transaction_history_id = $transaction_history_result['transaction_history_id'];
            $transaction_history_table = $transaction_history_result['table_name'];

            $updateWalletTx = array(
                "transaction_history_table" => $transaction_history_table,
                "transaction_history_id" => $transaction_history_id,
            );
    
            $xunWallet->updateWalletTransaction($walletTransactionID, $updateWalletTx);

            log_process("Calling wallet server to sign and perform internal transfer for wallet transaction: $walletTransactionID. Daily Table: $transaction_history_table. Daily Table ID: $transaction_history_id.", "Message", true);

            // Perform internal transfer
            $curlParams = array(
                "command" => "fundOutCompanyWallet",
                "params" => array(
                    "senderAddress" => $senderAddress,
                    "receiverAddress" => $receiverAddress,
                    "amount" => $toAmount,
                    "satoshiAmount" => $toSatoshiAmount,
                    "walletType" => $toWalletType,
                    "id" => $walletTransactionID,
                    "transactionToken" => $transactionToken,
                    "addressType" => "nuxpay_wallet",
                ),
            );

            $prepaidWalletServerURL = $config["giftCodeUrl"];
            $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);

            log_process("Curl response: ".json_encode($curlResponse), "Debug", true);

            if ($curlResponse['code'] == 1) {
                $updateData = array(
                    "to_tx_id" => $walletTransactionID,
                    "updated_at" => date("Y-m-d H:i:s"),
                );
                $db->where('id', $recordID);
                $db->update('xun_swap_history', $updateData);

                $tag = "Swapcoins";

                // $insertTx = array(
                //     "businessID" => $userID,
                //     "senderAddress" => $senderAddress,
                //     "recipientAddress" => $receiverAddress,
                //     "amount" => $toAmount,
                //     "amountSatoshi" => 0,
                //     "walletType" => $toWalletType,
                //     "credit" => $toAmount,
                //     "debit" => 0,
                //     "transactionType" => 'swap',
                //     "referenceID" => $recordID,
                //     "transactionDate" => date("Y-m-d H:i:s"),
                // );
                // $txID = $account->insertXunTransaction($insertTx);

                //$message = "Swap coins success\n";
                $message = "Business Name:".$username."\n";
                $message .= "From Amount:" .$fromAmount." ".$fromSymbol."\n";
                $message .= "To Amount:" .$toAmount." ".$toSymbol."\n";
                $message .= "Order Status: ".$orderStatus."\n";
                $message .= "Order ID: ".$orderID."\n";
                //$message .= "Reason: ".json_encode($curlResponse)."\n";
                $message .= "\nTime: ".date("Y-m-d H:i:s")."\n";
                $message .= "Source: processCheckSwapOrder(backend)\n";

                $thenux_params["tag"] = "Swapcoins Transfer Sent";
                $thenux_params["message"] = $message;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

            }
            else {

                log_process("Calling wallet server failed for wallet transaction: $walletTransactionID. Reason: ".json_encode($curlResponse), "Error", true);

                // Handle failed case
                $updateData = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s"),
                );
                $db->where('id', $walletTransactionID);
                $db->update('xun_wallet_transaction', $updateData);

                //Handle failed case for daily table
                $db->where('id', $transaction_history_id);
                $db->update($transaction_history_table, $updateData);

                $message = "Business Name:".$username."\n";
                $message .= "From Amount:" .$fromAmount." ".$fromSymbol."\n";
                $message .= "To Amount:" .$toAmount." ".$toSymbol."\n";
                $message .= "Order Status: ".$orderStatus."\n";
                $message .= "Order ID: ".$orderID."\n";
                $message .= "Reason: ".json_encode($curlResponse)."\n";
                $message .= "\nTime: ".date("Y-m-d H:i:s")."\n";
                $message .= "Source: processCheckSwapOrder(backend)\n";

                $thenux_params["tag"] = "Swapcoins Transfer Failed";
                $thenux_params["message"] = $message;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");
            }

        }

        if (in_array($orderStatus, array('CANCELED', 'REJECTED', 'EXPIRED'))) {
            // If it's any of these statuses, we refund customer
            log_process("Swap history id: $recordID, order id: $orderID is $orderStatus. Performing Refund internal transfer.", "Message", true);
            // Need to perform internal transfer

            $txObj = new stdClass();
            $txObj->userID = "0";
            $txObj->address = $senderAddress;

            $xunBusinessService = new XunBusinessService($db);
            $transactionToken = $xunBusinessService->insertCryptoTransactionToken($txObj);

            // Insert into wallet transaction table
            $transactionObj = new stdClass();
            $transactionObj->status = "pending";
            $transactionObj->transactionHash = "";
            $transactionObj->transactionToken = $transactionToken;
            $transactionObj->senderAddress = $senderAddress;
            $transactionObj->recipientAddress = $receiverAddress;
            $transactionObj->userID = "0";
            $transactionObj->senderUserID = "swap_wallet";
            $transactionObj->recipientUserID = $userID;
            $transactionObj->walletType = $fromWalletType;
            $transactionObj->amount = $fromAmount;
            $transactionObj->addressType = "nuxpay_wallet";
            $transactionObj->transactionType = "receive";
            $transactionObj->escrow = 0;
            $transactionObj->referenceID = $recordID;
            $transactionObj->message = 'swap_refund';
            $transactionObj->escrowContractAddress = '';
            $transactionObj->createdAt = date("Y-m-d H:i:s");
            $transactionObj->updatedAt = date("Y-m-d H:i:s");
            $transactionObj->expiresAt = '';

            $xunWallet = new XunWallet($db);
            $walletTransactionID = $xunWallet->insertUserWalletTransaction($transactionObj);

            log_process("Calling wallet server to sign and perform internal transfer for wallet transaction: $walletTransactionID.", "Message", true);

            // $txHistoryObj->paymentDetailsID = $payment_details_id;
            $txHistoryObj->status = "pending";
            $txHistoryObj->transactionID = "";
            $txHistoryObj->transactionToken = $transactionToken;
            $txHistoryObj->senderAddress = $senderAddress;
            $txHistoryObj->recipientAddress = $receiverAddress;
            $txHistoryObj->senderUserID = "swap_wallet";
            $txHistoryObj->recipientUserID = $userID;
            $txHistoryObj->walletType = strtolower($fromWalletType);
            $txHistoryObj->amount = $fromAmount;
            $txHistoryObj->transactionType = 'swap_refund';
            $txHistoryObj->referenceID = $recordID;
            $txHistoryObj->createdAt = $date;
            $txHistoryObj->updatedAt = $date;
            // $txHistoryObj->fee = $final_miner_fee;
            // $txHistoryObj->feeWalletType = $miner_fee_wallet_type;
            // $txHistoryObj->exchangeRate = $exchangeRate;
            // $txHistoryObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
            $txHistoryObj->type = 'out';
            $txHistoryObj->gatewayType = "BC";

            $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);

            $transaction_history_id = $transaction_history_result['transaction_history_id'];
            $transaction_history_table = $transaction_history_result['table_name'];

            $updateWalletTx = array(
                "transaction_history_table" => $transaction_history_table,
                "transaction_history_id" => $transaction_history_id,
            );
    
            $xunWallet->updateWalletTransaction($walletTransactionID, $updateWalletTx);
            
            // Perform internal transfer
            $curlParams = array(
                "command" => "fundOutCompanyWallet",
                "params" => array(
                    "senderAddress" => $senderAddress,
                    "receiverAddress" => $receiverAddress,
                    "amount" => $fromAmount,
                    "satoshiAmount" => $fromSatoshiAmount,
                    "walletType" => $fromWalletType,
                    "id" => $walletTransactionID,
                    "transactionToken" => $transactionToken,
                    "addressType" => "nuxpay_wallet",
                    "transactionHistoryTable" => $transaction_history_table,
                    "transactionHistoryID" => $transaction_history_id,
                ),
            );

            $prepaidWalletServerURL = $config["giftCodeUrl"];
            $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);
            if ($curlResponse['code'] == 1) {
                // $updateData = array(
                //     "to_tx_id" => $walletTransactionID,
                //     "updated_at" => date("Y-m-d H:i:s"),
                // );
                // $this->db->where('id', $recordID);
                // $this->db->update('xun_swap_history', $updateData);

                $tag = "Swapcoins";

                $insertTx = array(
                    "businessID" => $userID,
                    "senderAddress" => $senderAddress,
                    "recipientAddress" => $receiverAddress,
                    "amount" => $fromAmount,
                    "amountSatoshi" => $fromSatoshiAmount,
                    "walletType" => $fromWalletType,
                    "credit" => $fromAmount,
                    "debit" => 0,
                    "transactionType" => 'swap_refund',
                    "referenceID" => $recordID,
                    "transactionDate" => date("Y-m-d H:i:s"),
                );
                $txID = $account->insertXunTransaction($insertTx);


            }
            else {

                log_process("Calling wallet server failed for wallet transaction: $walletTransactionID. Reason: ".json_encode($curlResponse), "Error", true);

                // Handle failed case
                $updateData = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s"),
                );
                $db->where('id', $walletTransactionID);
                $db->update('xun_wallet_transaction', $updateData);

                $message = "Swap coins refund failed\n";
                $message .= "Business Name:".$username."\n";
                $message .= "From Amount:" .$fromAmount." ".$fromSymbol."\n";
                $message .= "To Amount:" .$toAmount." ".$toSymbol."\n";
                $message .= "Reason: ".json_encode($curlResponse)."\n";
                $message .= "\nTime: ".date("Y-m-d H:i:s")."\n";
                $message .= "Source: processCheckSwapOrder(backend)\n";

                $thenux_params["tag"] = "Swapcoins Refund failed";
                $thenux_params["message"] = $message;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
            }

        }

    }

    function getSwapOrderRecords() {
        global $db;
        $db->where('status', 'processing');
        //$db->where('order_status', array('CANCELED', 'REJECTED', 'EXPIRED'), 'NOT IN');
        $swapOrderRecords = $db->get('xun_swap_history');
        log_process("swapOrderRecords ".json_encode($swapOrderRecords), "Debug");
        log_process("Found ".count($swapOrderRecords)." not FILLED swap order record(s)");
        return $swapOrderRecords;
    }        

    function notifyNotFILLED($params) {
        global $general, $source;
        
        $message =  "Message: ".$params['errorMsg'];
        $message .= "\nInfo: ".$params['errorInfo'];
        $message .= "\n\nSource: $source";
        $message .= "\n\nTime: ".date( 'Y-m-d H:i:s' );


        $thenux_params["tag"] = "Binance Exchange";
        $thenux_params["message"] = $message;     
        $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");
        var_dump($thenux_result);
    }

    function notifyError($params) {
        global $general, $source;
        
        $message =  "Error Message: ".$params['errorMsg'];
        $message .= "\nInfo: ".$params['errorInfo'];
        $message .= "\n\nSource: $source";
        $message .= "\n\nTime: ".date( 'Y-m-d H:i:s' );


        $thenux_params["tag"] = "Process Error";
        $thenux_params["message"] = $message;     
        $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");
        var_dump($thenux_result);
    }

    function log_process($msg, $type = "Message", $doubleSpace = false) {
        global $log;    
        global $verbose;

        //if ($verbose != '1' && $type=="Debug") {
        //    return '';
        //}
        
        if($doubleSpace) {
            $log->write( "\n" . date( 'Y-m-d H:i:s' ) . " $type - $msg\n" );
            echo "\n".date( 'Y-m-d H:i:s' ) . " $type - $msg\n" ;
        } else {
            $log->write( "\n" . date( 'Y-m-d H:i:s' ) . " $type - $msg" );
            echo "\n".date( 'Y-m-d H:i:s' ) . " $type - $msg" ;
        }
    }  
    
    

?>