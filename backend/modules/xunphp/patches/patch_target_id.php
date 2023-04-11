<?php

include_once '../include/config.php';
include_once '../include/class.database.php';
include_once '../include/class.log.php';
include_once '../include/class.post.php';
include_once '../include/class.general.php';


$process_id = getmypid(); //process id  to get

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$post = new post();

$logPath =  '../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);
$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Start process for Patch Xun Payment Gateway Fund In table\n");


$db->where('reference_id', '', '!=' );               //refrence id not null
$db->where("(transaction_target = ? or transaction_id = ?)", Array('',''));                //target is null //t_id is null
$getFundInData = $db->get("xun_payment_gateway_fund_in");  // get db
// print_r($db);

foreach($getFundInData as $value){                 // everyline value get
    // $r_id     = $value["reference_id"];
    $r_id     = $value["reference_id"];
    $t_target = $value["transaction_target"];
    $t_id     = $value["transaction_id"];
    $t_t      = $value["transaction_type"];
    $w_type   = $value["wallet_type"];
    echo($r_id);
//////bc update transaction_target ///////////  
    if($t_t == 'blockchain'){
        $params = array(                //parameter to call api
            "referenceID" => $r_id,
            "walletType"  => $w_type
        );

        $return = $post->curl_crypto("getTransactionTokenDetails", $params, $cryptoType = 2); // api name
        $dataReturn = $return['data'];
        $status     = $return['status'];
        $data       = $dataReturn['0'];

        if($dataReturn && $status != 'error'){
            $r_idReturn     = $data['referenceID'];                   //api return
            $t_targetReturn = strtolower($data['transactionType']);
            $t_External_idReturn     = $data['externalTransactionID'];
            $t_Internal_idReturn     = $data['internalTransactionID'];
            if($t_External_idReturn != ''){
                $t_idReturn = $t_External_idReturn;
            }
            else{
                $t_idReturn = $t_Internal_idReturn;
            }

            if($r_id == $r_idReturn && $r_id != null && $t_target == null){     //update transaction_target
                $updateTarget = array(
                    "transaction_target" => $t_targetReturn,
                );
        
                $db->where("reference_id", $r_idReturn);
                $update =$db->update('xun_payment_gateway_fund_in', $updateTarget);
                if($updateFundIn){
                $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $r_id updated BC transaction target column \n");
                }
                if(!$updateFundIn){
                    $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $r_id BC transaction target error\n");
                    $log->write(date('Y-m-d H:i:s') . " ".$db->getLastError()." \n");
                }
            }
        
            if($r_id == $r_idReturn && $r_id != null && $t_id == null){     //update transaction_id
                $updateTID = array(
                    "transaction_id" => $t_idReturn,
                );
        
                $db->where("reference_id", $r_idReturn);
                $updateFundIn =$db->update('xun_payment_gateway_fund_in', $updateTID);
                if($updateFundIn){
                    $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $r_id updated BC transaction id column\n");
                }
                if(!$updateFundIn){
                    $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $r_id BC transaction Id error\n");
                    $log->write(date('Y-m-d H:i:s') . " ".$db->getLastError()." \n");
                }
            }   
        }
        else{
            $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $r_id  $message BC  \n");
                
        }
    }

//////pg update transaction_target ///////////  
    if($t_t == 'payment_gateway'){
        $params = array(                //parameter to call api
            "referenceId" => $r_id,
            "walletType"  => $w_type
        );
        
        $return = $post->curl_crypto("getTransactionDetails", $params, 1); // api name
        $dataReturn = $return['data'];   
        $status     = $return['status'];
        $message    = $return['message'];

        if($dataReturn && $status != 'error'){
            $r_idReturn     = $dataReturn['referenceId'];                   //api return
            $t_targetReturn       = strtolower($dataReturn['transactionType']);   
            $t_External_idReturn     = $dataReturn['externalTransactionID'] ? $dataReturn['externalTransactionID'] : $dataReturn['fundInTransactionid'];
            $t_Internal_idReturn     = $dataReturn['internalTransactionID'];
            if($t_External_idReturn != ''){
                $t_idReturn = $t_External_idReturn;
            }
            else{
                $t_idReturn = $t_Internal_idReturn;
            }


            if($r_id == $r_idReturn && $r_id != null && $t_target == null){     //update transaction_target
                $updateTarget = array(
                    "transaction_target" => $t_targetReturn,
                );
        
                $db->where("reference_id", $r_idReturn);
                $updateFundIn =$db->update('xun_payment_gateway_fund_in', $updateTarget);
                if($updateFundIn){
                $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $r_id updated PG transaction target column \n");
                }
                if(!$updateFundIn){
                    $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $r_id PG transaction target error\n");
                    $log->write(date('Y-m-d H:i:s') . " ".$db->getLastError()." \n");
                }
                
            }
        
            if($r_id == $r_idReturn && $r_id != null && $t_id == null){     //update transaction_id
                $updateTID = array(
                    "transaction_id" => $t_idReturn,
                );
        
                $db->where("reference_id", $r_idReturn);
                $updateFundIn =$db->update('xun_payment_gateway_fund_in', $updateTID);
                if($updateFundIn){
                    $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $r_id updated PG transaction id column\n");
                }
                if(!$updateFundIn){
                    $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $r_id PG transaction Id error\n");
                    $log->write(date('Y-m-d H:i:s') . " ".$db->getLastError()." \n");
                }
                
            }   
        }
        else{
            $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t $r_id $message PG \n");
                
        }
    }    
}
$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t End process for patching Xun Payment Gateway Fund In table\n");




?>