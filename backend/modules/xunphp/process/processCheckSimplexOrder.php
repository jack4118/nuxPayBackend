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
    $provider = new Provider($db);
    $message = new Message($db, $general, $provider);
    $xunCrypto = new XunCrypto($db, $post, $general);

    $logPath     = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    $log         = new Log($logPath, $logBaseName);
    $source = "processCheckSimplexOrder.php";

    while (true) {
        log_process("Start processCheckSimplexOrder", "Message");

        $params = array();
        $api_url = $config['simplex_api_url'].'/wallet/merchant/v2/events';

        $result = $post->curl_simplex($api_url, $params, 'GET');
        $events = $result['events'];

        // $payment_ids = array_column($events, 'payment_id');

        // if($payment_ids){
        //     $db->where('payment_id', $payment_ids, 'IN');
        //     $crypto_payment_tx = $db->map('payment_id')->ArrayBuilder()->get('xun_crypto_payment_transaction', null, 'id, business_id, payment_id, status');
        // }

        foreach($events as $event_data){
            $event_id = $event_data['event_id'];
            $event_name = $event_data['name'];
            $payment_data = $event_data['payment'];
            $payment_id = $payment_data['id'];
            $payment_status = $payment_data['status'];
            $fiat_amount_data = $payment_data['fiat_total_amount'];
            $fiat_amount = $fiat_amount_data['amount'];
            $fiat_currency = $fiat_amount_data['currency'];

            $crypto_amount_data = $payment_data['crypto_total_amount'];
            $crypto_amount = $crypto_amount_data['amount'];
            $crypto_currency = $crypto_amount_data['currency'];

            $crypto_symbol = $crypto_currency;

            if($crypto_symbol == 'USDT(TRC20)' || $crypto_symbol == 'USDT-TRC20'){
                $crypto_symbol = 'trx-usdt';
            }
            $db->where('symbol', $crypto_symbol);
            $wallet_type = $db->getValue('xun_marketplace_currencies', 'currency_id');

            if(!$wallet_type){
                log_process("Failed UPDATE DATA: $event_id, ERROR: ".$db->getLastQuery(), "Error", true);
                $errorParams = array(
                    'errorMsg'  => "Error Retrieved Wallet Type",
                    'errorInfo' => "(" . $crypto_symbol . ")" . " Symbol is not available in the database.",
                    'source'    => $source,
                );
                notifyError($errorParams); // send notification  
            }

            unset($updateArray);
            $updateArray = array(
                "fiat_amount" => $fiat_amount,
                "fiat_currency" => $fiat_currency,
                "crypto_amount" => $crypto_amount,
                "wallet_type"=> $wallet_type,
                "provider_response_string" => json_encode($event_data),
                "updated_at" => date("Y-m-d H:i:s")
            );


            $db->where('payment_id', $payment_id);
            $crypto_payment_tx = $db->getOne('xun_crypto_payment_transaction', 'id, status, business_id, provider_response_string');

            $payment_transaction_id = $crypto_payment_tx['id'];
            $crypto_payment_tx_status = $crypto_payment_tx['status'];
            $crypto_payment_tx_provider_response_string = $crypto_payment_tx['provider_response_string'];

            
            $db->where('payment_tx_id', $payment_transaction_id);
            $payment_request_data = $db->getOne('xun_crypto_payment_request', 'id, transaction_token, reference_id');

            $payment_request_id = $payment_request_data['id'];
            $transaction_token = $payment_request_data['transaction_token'];
            $reference_id = $payment_request_data['reference_id'];

            if($event_name == 'payment_request_submitted'){
                $updateArray['status'] = 'pending';
                $status = 'pending';
            }
            elseif($event_name == 'payment_simplexcc_declined'){
                $updateArray['status'] = 'cancelled';
                $status = 'cancelled';

            }
            elseif($event_name == 'payment_simplexcc_approved'){
                $updateArray['status'] = 'completed';
                $status = 'completed';

            }
            else if($event_name == 'payment_simplexcc_refunded'){
                $updateArray['status'] = 'refunded';
                $status = 'refunded';

            }
            
            if($crypto_payment_tx_status != 'pending'){
                continue;
            }
            elseif ($status == "pending" && $crypto_payment_tx_provider_response_string != ""){
                continue;
            }


            $db->where('payment_id', $payment_id);
            $updated = $db->update('xun_crypto_payment_transaction', $updateArray);

            if(!$updated){
                log_process("Failed UPDATE DATA: $event_id, ERROR: ".$db->getLastQuery(), "Error", true);
                $errorParams = array(
                    'errorMsg'  => "Error Updating Data",
                    'errorInfo' => $db->getLastError(),
                    'source'    => $source,
                );
                notifyError($errorParams); // send notification  

            }

            $updatedRequestArr = array(
                "status" => $status
            );

            $db->where('id', $payment_request_id);
            $updated_payment_request = $db->update('xun_crypto_payment_request', $updatedRequestArr);

            if(!$updated_payment_request){
                log_process("Failed UPDATE DATA: $event_id, ERROR: ".$db->getLastQuery(), "Error", true);
                $errorParams = array(
                    'errorMsg'  => "Error Updating Data",
                    'errorInfo' => $db->getLastError(),
                    'source'    => $source,
                );
                notifyError($errorParams); // send notification  

            }

            if($crypto_payment_tx){
                $business_id = $crypto_payment_tx['business_id'];

                $callback_params = array(
                    "transaction_token" => $transaction_token,
                    "fiat_amount" => $fiat_amount,
                    "fiat_symbol" => $fiat_currency,
                    "crypto_amount" => $crypto_amount,
                    "crypto_symbol" => $crypto_currency,
                    "payment_id" => $payment_id,
                    "type" => 'buy',
                    "status" => $status,
                    "reference_id" => $reference_id
                );
                $db->where('user_id', $business_id);
                $business_result = $db->getOne('xun_business', 'id, user_id, buysell_crypto_callback_url');
      
                if($business_result){
                    $callback_url = $business_result['buysell_crypto_callback_url'];

                    if($callback_url){
                        $curl_params = array(              
                            'command'=>'buySellCryptoCallback',
                            'params'=>$callback_params
                        );  
        
                        // $curl_header[] = "Content-Type: application/json";
                        $curl_header = array("Content-Type: application/json");
                        log_process("Curl url: ".$callback_url, true);
                        log_process("Curl header: ".json_encode($curl_header), true);
                        log_process("Curl params: ".json_encode($curl_params), true);

                        $cryptoResult = $post->curl_post($callback_url, $curl_params, 0, 1, $curl_header);
        
                        log_process("Curl result: ".json_encode($cryptoResult), true);

                        if (count($cryptoResult) == 0 || $cryptoResult['debug']['error'] == "1") {

                            $errorParams = array(
                                'errorMsg'  => "Error sending callback",
                                'errorInfo' => "Callback url: ".$callback_url."\nCallback return: ".json_encode($cryptoResult['debug']['rawResult'])."\nCurl Info: ".json_encode($cryptoResult['debug']['curlInfo']),
                                'source'    => $source,
                            );
                            notifyError($errorParams); // send notification
                            log_process("count: ".count($cryptoResult).", error: ".$cryptoResult['debug']['error'] , true);
                        }
                        
                        $webservice->developerOutgoingWebService($business_id, "buySellCryptoCallback", $callback_url, json_encode($curl_params), json_encode($cryptoResult) );


                        $jsonResult = json_decode($cryptoResult, true);
                        if ($jsonResult !== NULL || !is_array($cryptoResult) ){
                            $cryptoResult = $jsonResult;
                        }
                    }
                   
                }
             
    
                
            }

       	    if($status == 'pending'){
                log_process($payment_transaction_id.",".$crypto_payment_tx_status, "Skip Delete Event ID");
                continue;
            }  

            $delete_api_url =  $config['simplex_api_url'].'/wallet/merchant/v2/events/'.$event_id;
            $delete_result = $post->curl_simplex($delete_api_url, $params, "DELETE");

            if($delete_result['status'] != 'OK'){
                log_process("Failed DELETE Event: $event_id, ".json_encode($delete_result), "Error", true);
                $errorParams = array(
                    'errorMsg'  => "Error DELETE Simplex Event",
                    'errorInfo' => json_encode($delete_result),
                    'source'    => $source,
                );
                notifyError($errorParams); // send notification     
            }
            
        }
        
        // $db->where('name', 'simplex');
        // $provider_id = $db->getValue('provider', 'id');

        // $db->where('status', 'pending');
        // $db->where('provider_id', $provider_id);
        // $crypto_payment_tx = $db->get('xun_crypto_payment_transaction');

        // foreach($crypto_payment_tx as $key => $value){
        //     $id = $value['id'];
        //     $created_at = $value['created_at'];

        //     if($created_at < date(("Y-m-d H:i:is"), strtotime('-1 hour'))){
        //         $updateArray = array(
        //             "status" => "cancelled",
        //             "updated_at" => date("Y-m-d H:i:s")
        //         );

        //         log_process("CANCELLED(EXPIRED): $request_id ", "Error", true);

        //         $db->where('id', $id);
        //         $updated = $db->update('xun_crypto_payment_transaction', $updateArray);

        //         if(!$updated){
        //             log_process("Failed UPDATE DATA: $request_id, ERROR: ".$db->getLastQuery(), "Error", true);
        //             $errorParams = array(
        //                 'errorMsg'  => "Error Updating Data",
        //                 'errorInfo' => $db->getLastError(),
        //                 'source'    => $source,
        //             );
        //             notifyError($errorParams); // send notification     
        //         }


        //     }
        // }

  
        log_process("End processCheckSimplexOrder", "Message", true);
        
        sleep(30);
    }

    function log_process($msg, $type = "Message", $doubleSpace = false) {
        global $log;    
        global $verbose;
        
        if($doubleSpace) {
            $log->write( "\n" . date( 'Y-m-d H:i:s' ) . " $type - $msg\n" );
            echo "\n".date( 'Y-m-d H:i:s' ) . " $type - $msg\n" ;
        } else {
            $log->write( "\n" . date( 'Y-m-d H:i:s' ) . " $type - $msg" );
            echo "\n".date( 'Y-m-d H:i:s' ) . " $type - $msg" ;
        }
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

?>
    
