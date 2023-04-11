<?php 	

    $currentPath = __DIR__;
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.general.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.message.php');
    include($currentPath.'/../include/class.webservice.php');
    include($currentPath.'/../include/class.post.php');
    include($currentPath.'/../include/class.log.php');
    include($currentPath.'/../include/class.binance.php'); 

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $webservice  = new Webservice($db, "", "");
    $setting = new Setting($db);
    $general = new General($db, $setting);    
    $post = new Post($db, $webservice, $msgpack);
    $binance = new Binance(
        $config['binanceAPIKey'],
        $config['binanceAPISecret'],
        $config['binanceAPIURL'], //"https://api.binance.com/api/v3/",
        $config['binanceWAPIURL'] //"https://api.binance.com/wapi/v3/"
    );
    $logPath     = $currentPath.'/log/';
    $logBaseName = basename(__FILE__, '.php');
    $log         = new Log($logPath, $logBaseName);        
    
    $source = "processCheckOrder.php";

    
    $verbose = $argv[1];    
    $validStatus = array('NEW','PARTIALLY_FILLED','FILLED','CANCELED','PENDING_CANCEL','REJECTED','EXPIRED');

    $messageCode = array (
        'processError' => '90007' 
    );
    
    // START OF SCRIPT 

    while (true) {

            
        log_process("Start processCheckOrder", "Message");
        
        // get exchangeOrderRecords not 'FILLED'
        $exchangeOrderRecords = getExchangeOrderRecords();        
        foreach($exchangeOrderRecords as $exchangeOrderRecord) {            
            $recordID = $exchangeOrderRecord['id'];
            $orderID = $exchangeOrderRecord['reference_id'];
            $symbol = $exchangeOrderRecord['to_symbol'].$exchangeOrderRecord['from_symbol'];
            $recordStatus = $exchangeOrderRecord['status'];
            
            // query binance order
            $queryOrdeResult = $binance->queryOrder($symbol, $orderID);
            log_process("queryOrderResult ".json_encode($queryOrdeResult), "Debug");
            $binanceStatus = $queryOrdeResult['status'];
            
            if(empty($binanceStatus) || !in_array($binanceStatus, $validStatus)) {
                log_process("Invalid status, $binanceStatus", "Error");
            }
            
            log_process("recordID $recordID orderID $orderID $binanceStatus");

            if ($binanceStatus == 'NEW') {
                // new order 
                continue;   
            }  
            else if ($binanceStatus == $recordStatus) {
                // have already updated
            } 
            else {
                // binanceStatus different 
                
                $exchangeRes = updateExchangeOrderRecord($binanceStatus, $recordID, $orderID);                    
            }
        
            
        }
                
        log_process("End processCheckOrder", "Message", true);
        
        sleep(5);
    }

    // END OF SCRIPT 
    function updateExchangeOrderRecord($status, $recordID, $exchangeID){
        global $db, $source;

        $error = false;
        try {
            $updateParams = array(
                'status' => $status,
                'updated_at' => date( 'Y-m-d H:i:s' ),
            );
            $db->where('id', $recordID);
            $db->where('reference_id', $exchangeID);
            $db->update('xun_exchange_order', $updateParams);
        } catch (Exception $e) {
            $error = true;
        }
        
        if ($db->getLastErrno() !== 0 || $error) {
            // update error
            $returnData = array(
                'status' => 'error',
                'statusMsg' => "Failed to update xun_exchange_order",
                'data'   => $db->getLastError(),                                                        
            );            

            $errorParams = array(
                'errorMsg'  => $returnData['statusMsg'],
                'errorInfo' => $returnData['data'],
                'source'    => $source,
            );
            notifyError($errorParams); // send notification             
            return $returnData;
        }

        if ($status != 'FILLED') {
            $returnData = array(
                'status' => 'error',
                'statusMsg' => "Exchange order id $exchangeID is not FILLED",
                'data'   => $status,                                                        
            );            

            $errorParams = array(
                'errorMsg'  => $returnData['statusMsg'],
                'errorInfo' => $returnData['data'],
                'source'    => $source,
            );
            notifyNotFILLED($errorParams); // send notification             
            return $returnData;
        }

    }

    function getExchangeOrderRecords() {
        global $db;
        $db->where('status', 'FILLED', '!=');
        $exchangeOrderRecords = $db->get('xun_exchange_order');
        log_process("exchangeOrderRecords ".json_encode($exchangeOrderRecords), "Debug");
        log_process("Found ".count($exchangeOrderRecords)." not FILLED exchange order record(s)");
        return $exchangeOrderRecords;
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

        if ($verbose != '1' && $type=="Debug") {
            return '';
        }
        
        if($doubleSpace) {
            $log->write( "\n" . date( 'Y-m-d H:i:s' ) . " $type - $msg\n" );
            echo "\n".date( 'Y-m-d H:i:s' ) . " $type - $msg\n" ;
        } else {
            $log->write( "\n" . date( 'Y-m-d H:i:s' ) . " $type - $msg" );
            echo "\n".date( 'Y-m-d H:i:s' ) . " $type - $msg" ;
        }
    }    

?>
