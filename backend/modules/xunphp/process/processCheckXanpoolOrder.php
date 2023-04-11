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

    $source = "processCheckXanpoolOrder.php";
    while (true) {
        log_process("Start processCheckXanpoolOrder", "Message");

        $db->where('name', 'xanpool');
        $provider_id = $db->getValue('provider', 'id');

        $db->where('status', 'pending');
        $db->where('provider_id', $provider_id);
        $db->where('reference_id', '', '!=');
        $crypto_payment_tx = $db->get('xun_crypto_payment_transaction');

        foreach($crypto_payment_tx as $key => $value){
            $request_id = $value['reference_id'];
            $created_at = $value['created_at'];

            if($created_at < date(("Y-m-d H:i:is"), strtotime('-1 hour'))){
                $updateArray = array(
                    "status" => "cancelled",
                    "updated_at" => date("Y-m-d H:i:s")
                );

                log_process("CANCELLED(EXPIRED): $request_id ", "Error", true);

                $db->where('reference_id', $request_id);
                $updated = $db->update('xun_crypto_payment_transaction', $updateArray);

                if(!$updated){
                    log_process("Failed UPDATE DATA: $request_id, ERROR: ".$db->getLastQuery(), "Error", true);
                }


            }
            else{
                $params = array();
                $api_url = $config['xanpool_api_url'].'/api/requests/'.$request_id;
        
                $result = $post->curl_xanpool($api_url, $params, 'GET');
                if($result['error']){
                    log_process("ERROR: $request_id,  ".json_encode($result, true), "Error", true);

                }
                else {
                    $status = $result['status'] == 'initiated' ? 'pending' : $result['status'];
    
                    if($result['transaction']){

                        $transaction_status = $result['transaction']['status'];
                        log_process("Request ID: $request_id Transaction Status: $transaction_status", "Error", true);

                        if($transaction_status == 'expired_fiat_not_received' || $transaction_status == 'expired_btc_not_in_mempool'){
                            $status = 'cancelled';
                        }
                        else if($transaction_status == 'payout_failed'){
                            $status = 'failed';
                        }
                        else if($transaction_status == 'fiat_received'){
                            $status = 'pending';
                        }
                        else{
                            $status = $transaction_status;
                        }
                    }
                    $updateArray = array(
                        "status" => $status,
                        "updated_at" => date("Y-m-d H:i:s")
                    );
    
                    $db->where('reference_id', $request_id);
                    $updated = $db->update('xun_crypto_payment_transaction', $updateArray);
    
                    if(!$updated){
                        log_process("Failed UPDATE DATA: $request_id, ERROR: ".$db->getLastQuery(), "Error", true);
                        $errorParams = array(
                            'errorMsg'  => "Error Updating Data",
                            'errorInfo' => $db->getLastError(),
                            'source'    => $source,
                        );
                        notifyError($errorParams); // send notification     
                    }
                }
            } 
        
        }
  
        log_process("End processCheckXanpoolOrder", "Message", true);
        
        
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
    