<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the API Related Database code.
     * Date  29/06/2017.
    **/
    class XunCrypto {
        private $recipient_address_data;
        private $sender_address_data;
        private $callback_address_type;
        
        function __construct($db, $post, $general) {
            $this->db = $db;
            $this->post = $post;
			$this->general = $general;
        }
        
        public function validate_access_token($access_token){

            global $setting;

            if($access_token != $setting->getCryptoAccessToken()){
                return false;
            }

            return true;
        }
        
        function convert_bc_to_pg_callback($params) {


            $exchangeRate = $params['exchangeRate']['USD'];
            $amount = bcdiv($params['amount'], $params['amountRate'], (strlen($params['amountRate'])-1) ); 

            $miner = bcdiv($params['fee'], $params['feeRate'], (strlen($params['feeRate'])-1) );

            $minerusd = bcmul($miner, $params['minerFeeExchangeRate'], 4);
            $minercoin = bcdiv($minerusd, $exchangeRate, (strlen($params['amountRate'])-1) );
            $minercoinSatoshi = bcmul($minercoin, $params['amountRate']);

            $status = $params['status']=="confirmed"?"success":$params['status'];


            //INPUT
            $tx_input['receivedTxID'] = $params['exTransactionHash'];
            $tx_input['amount'] = $amount;
            $tx_input['unit'] = $params['amountUnit'];
            $tx_input['type'] = $params['wallet_type'];
            $tx_input['exchangeRate'] = $params['exchangeRate']['USD'];
            $tx_input['referenceID'] = $params['referenceID'];
            $tx_input['charges'] = array("amount"=>"0", "unit"=>$params['amountUnit'], "type"=>$params['wallet_type'], "exchangeRate"=>$exchangeRate);
            $tx_input['minerFee'] = array("amount"=>$minercoin, "unit"=>$params['amountUnit'], "type"=>$params['wallet_type'], "exchangeRate"=>$exchangeRate);
            $tx_input['ethMinerFee'] = array("amount"=>$miner, "unit"=>$params['feeUnit'], "type"=>$params['minerFeeWalletType'], "exchangeRate"=>$params['minerFeeExchangeRate']);


            //OUTPUT
            $tx_output['destination'] = array("amount"=>$amount, "unit"=>$params['amountUnit'], "type"=>$params['wallet_type'], "exchangeRate"=>$exchangeRate);
            $tx_output['charges'] = array("amount"=>"0", "unit"=>$params['amountUnit'], "type"=>$params['wallet_type'], "exchangeRate"=>$exchangeRate);
            $tx_output['minerFee'] = array("amount"=>$minercoin, "unit"=>$params['amountUnit'], "type"=>$params['wallet_type'], "exchangeRate"=>$exchangeRate);
            $tx_output['ethMinerFee'] = array("amount"=>$miner, "unit"=>$params['feeUnit'], "type"=>$params['minerFeeWalletType'], "exchangeRate"=>$params['minerFeeExchangeRate']);


            //CREDIT DETAIL
            $tx_credit['amountDetails'] = array("amount"=>$params['amount'], "unit"=>$params['amountUnit'], "rate"=>$params['amountRate'], "type"=>$params['wallet_type'], "exchangeRate"=>$params['exchangeRate']);

            $tx_credit['amountReceiveDetails'] = array("amount"=>$params['amount'], "unit"=>$params['amountUnit'], "rate"=>$params['amountRate'], "type"=>$params['wallet_type'], "exchangeRate"=>$params['exchangeRate']);

            $tx_credit['serviceChargeDetails'] = array("amount"=>"0", "unit"=>$params['amountUnit'], "rate"=>$params['amountRate'], "type"=>$params['wallet_type'], "exchangeRate"=>$params['exchangeRate']);

            $tx_credit['minerAmountDetails'] = array("amount"=>$minercoinSatoshi, "unit"=>$params['amountUnit'], "rate"=>$params['amountRate'], "type"=>$params['wallet_type'], "exchangeRate"=>$params['exchangeRate']);

            $tx_credit['ethMinerAmountDetails'] = array("amount"=>$params['fee'], "unit"=>$params['feeUnit'], "rate"=>$params['feeRate'], "type"=>$params['minerFeeWalletType'], "exchangeRate"=>array("USD"=>$params['minerFeeExchangeRate']));


            //
            $pg_params['receivedTxID'] = $params['exTransactionHash'];
            $pg_params['referenceID'] = $params['referenceID'];
            $pg_params['txDetails'] = array("input"=>array($tx_input), "output"=>array($tx_output));
            $pg_params['txID'] = $params['transactionHash'];
            $pg_params['amount'] = $amount." ".$params['amountUnit'];
            $pg_params['amountReceive'] = $amount." ".$params['amountUnit'];
            $pg_params['serviceCharge'] = "0 ".$params['amountUnit'];
            $pg_params['minerAmount'] = $minercoin." ".$params['amountUnit'];
            $pg_params['address'] = $params['sender'];
            $pg_params['status'] = $status;
            $pg_params['transactionDate'] = $params['time'];
            $pg_params['transactionUrl'] = "";
            $pg_params['type'] = $params['wallet_type'];
            $pg_params['transactionType'] = $params['target'];
            $pg_params['sender'] = array("internal"=>"", "external"=>$params['sender']);
            $pg_params['recipient'] = array("internal"=>$params['recipient'], "external"=>$params['referenceAddress']);
            $pg_params['creditDetails'] = $tx_credit;
            $pg_params['ip'] = $params['ip'];
            $pg_params['gw_type'] = "BC";
            $pg_params['confirmation'] = $params['confirmation'];


            return $this->transaction_callback($pg_params);

        }

        function save_crypto_callback($params){
            global $config, $xunXmpp, $xunUser, $xunSms, $xunEmail, $setting, $xun_numbers, $log, $xunCurrency, $xunCompanyWalletAPI, $account;
            $db = $this->db;
            $post = $this->post;
            $general = $this->general;

            $account_address = $params["sender"];
            $wallet_type = $params["wallet_type"];
            $type = $params["type"];
            $target = $params["target"];
            $recipient = $params["recipient"];    
            $reference_address = $params["referenceAddress"];
            $amount = $params["amount"];
            $amount_rate = $params["amountRate"];
            $fee = $params["fee"];
            $fee_unit = strtolower(trim($params["feeUnit"]));
            $fee_rate = $params["feeRate"];
            $transaction_hash = $params["transactionHash"];
            $ex_transaction_hash = $params["exTransactionHash"];
            $reference_id = $params["referenceID"];
            $confirmation = $params["confirmation"];
            $status = $params["status"];
            $timestamp = $params["transaction_timestamp"];
            $success_time = trim($params["successTime"]);
            $is_contract = trim($params["isContract"]);
            $exchange_rate = implode(":", $params["exchangeRate"]);
            $created_at = date("Y-m-d H:i:s");
            $oriTarget = $params["target"];
            $params['minerFeeExchangeRate'] = 0;
            
            // YF1
            if($status== "confirmed" && $type == "receive"){
                $joinDateLimit = $setting->systemSetting['userJoinDateLimit'];
                
                $db->where('address', $recipient);
                $userId = $db->getOne('xun_crypto_user_address');

                $db->where('id', $userId['user_id']);
                $db->where('type', 'business');
                $recipientDetails = $db->getOne('xun_user');

                if($recipientDetails){
                    $recipientJoinDate = $recipientDetails['created_at'];
                    $recipientJoinTimeStamp = strtotime($recipientJoinDate);
    
                    //if user is register after $joinDateLimit then only will fet the message
                    if($recipientJoinTimeStamp > $joinDateLimit){
    
                        $recipientName = $recipientDetails['nickname'];
                        $recipientMobile = $recipientDetails['username'];
                        $recipientEmail =  $recipientDetails['email'];
    
                        $source = $recipientDetails["register_site"];
    
                        $db->where('source', $source);
                        $site = $db->getOne('site');
    
                        $companyName = $site['source'];
                        $domain = $site['domain'];
                        
                        $decimal = $xunCurrency->get_currency_decimal_places($wallet_type);
                        $receiveAmount = bcdiv($amount, $amount_rate, $decimal);
                        
                        $db->where('currency_id', $wallet_type);
                        $currencies = $db->getOne('xun_marketplace_currencies');
    
                        $symbol = strtoupper($currencies["display_symbol"]);
    
                        if($recipientMobile){
                                    $return_message = $this->get_translation_message('B00372') /*"%%companyName%%: You have received %%amount%% %%currency%%. Find out more details at %%domain%%"*/;
                                    $return_message2 = str_replace("%%companyName%%", $companyName, $return_message);
                                    $return_message3 = str_replace("%%amount%%", $receiveAmount, $return_message2);
                                    $return_message4 = str_replace("%%currency%%", $symbol, $return_message3);
                                    $return_message5 = str_replace("%%domain%%", $domain, $return_message4);
                                    $newParams["message"] = $return_message5;
                                    $newParams["recipients"] = $recipientMobile;
                                    $newParams["ip"] = $ip;
                                    $newParams["companyName"] = $companyName;
                                    $newParams["sendType"] = "2way";
                                    //$xunSms->send_sms($newParams);
                        }
                        if($recipientEmail){
                            $receiveFundParam = array(
                            // "sender_name" => $payerName,
                            "receiver_name" => $recipientName, 
                            "amount" => $receiveAmount,
                            "symbol" => $symbol,
                            // "description"=> $description,
                            );
    
                                $emailDetail = $xunEmail->getReceiveFundEmailBC($source, $receiveFundParam);
                                
                                $emailParams["subject"] = $emailDetail['emailSubject'];
                                $emailParams["body"] = $emailDetail['html'];
                                $emailParams["recipients"] = array($recipientEmail);
                                $emailParams["emailFromName"] = $emailDetail['emailFromName'];
                                $emailParams["emailAddress"] = $emailDetail['emailAddress'];
                                $emailParams["emailPassword"] = $emailDetail['emailPassword'];
                                $emailParams["companyName"] = $companyName;
                                $msg = $general->sendEmail($emailParams);
    
                        }
                    }
                }
              
            }
                // YF2

            if($fee_unit){
                $db->where('symbol', $fee_unit);
                $marketplace_currencies = $db->getOne('xun_marketplace_currencies', 'currency_id, name, symbol');
                $miner_fee_wallet_type = $marketplace_currencies['currency_id'];

                if($miner_fee_wallet_type == strtolower($wallet_type)){
                    $miner_fee_exchange_rate = $exchange_rate;
                }
                else{
                    $cryptocurrency_result = $xunCurrency->get_cryptocurrency_rate(array($miner_fee_wallet_type));
                    $miner_fee_exchange_rate = $cryptocurrency_result[$miner_fee_wallet_type];
                }
               $params['minerFeeExchangeRate'] = $miner_fee_exchange_rate;
               $params['minerFeeWalletType'] = $miner_fee_wallet_type;
            }

            //PUSH BC CALLBACK TO PG
            if($type=="receive" ) {

                $db->where("e.external_address", $reference_address);
                $db->where("a.address_type", "nuxpay_wallet");
                $db->where("a.active", 1);
                $db->join("xun_crypto_external_address e", "a.address=e.internal_address", "INNER");
                $bcDetail = $db->getOne("xun_crypto_user_address a");
                $log->write("\n".date('Y-m-d')." Message - bcDetail ".json_encode($bcDetail));
                if($bcDetail) {
                    return $this->convert_bc_to_pg_callback($params);
                }
            }
           
            $amount_decimal = $this->get_decimal_amount($wallet_type, $amount, $amount_rate);
            $fee_decimal = $this->get_decimal_amount($wallet_type, $fee, $fee_rate);

            $db->where("transaction_hash", $transaction_hash);
            $cryptoTransactionRecord = $db->getOne("xun_crypto_transaction_hash");

            if($cryptoTransactionRecord){
                if(!(in_array($cryptoTransactionRecord["status"], ["completed", "confirmed", "failed"]) && $status == "pending")){
                    //  update cryptp_transaction_hash if callback status is not pending to prevent overwrite
                    $updateStatus = array(
                        "status" => $status,
                    );
    
                    $db->where('transaction_hash', $transaction_hash);
                    $updated = $db->update('xun_crypto_transaction_hash', $updateStatus);
                }
            }

            $db->where("reference_id", $reference_id);
            $db->where("type", $type);
            $crypto_callback_data = $db->getOne("xun_crypto_callback", "id, status, transaction_hash");
            $log->write("\n".date('Y-m-d')." Message - crypto_callback_data ".json_encode($crypto_callback_data));
            if($crypto_callback_data){
                if($status != "pending"){
                    $update_callback_data = [];
                    $update_callback_data["status"] = $status;
                    $update_callback_data["confirmation"] = $confirmation;
                    $update_callback_data["success_time"] = $success_time;
                    $db->where("id", $crypto_callback_data["id"]);
                    $db->update("xun_crypto_callback", $update_callback_data);
                }
            }else{
                $insert_callback_data = array(
                    "account_address" => $account_address,
                    "wallet_type" => $wallet_type,
                    "type" => $type,
                    "recipient" => $recipient,
                    "amount" => $amount_decimal,
                    "fee" => $fee_decimal,
                    "transaction_hash" => $transaction_hash,
                    "exchange_rate" => $exchange_rate,
                    "confirmation" => $confirmation,
                    "status" => $status,
                    "created_at" => $created_at,
                    "target" => $target,
                    "ex_transaction_hash" => $ex_transaction_hash,
                    "reference_id" => $reference_id,
                    "reference_address" => $reference_address,
                    "fee_unit" => $fee_unit,
                    "success_time" => $success_time,
                    "is_contract" => $is_contract
                );
    
                $db->insert("xun_crypto_callback", $insert_callback_data);
                $log->write("\n".date('Y-m-d')." Message - insert xun_crypto_callback ".json_encode($db->getLastError()));
                $log->write("\n".date('Y-m-d')." Message - insert xun_crypto_callback ".json_encode($db->getLastQuery()));
            }

            //rebuild params for erlang side
            $xun_recipient = $this->get_xun_user_by_crypto_address($recipient);
            if($xun_recipient["code"] == 0){
                $xun_recipient_username = $xun_recipient["name"];
                $target = $xun_recipient["type"] ? $xun_recipient["type"] : $target;
                $direction = 'receive';

                if(isset($xun_recipient["type"]) && !empty($xun_recipient["type"])){
                    $xun_recipient_address_data = array(
                        "user_id" => $xun_recipient["type"],
                        "address" => $recipient
                    );

                    $this->recipient_address_data = $xun_recipient_address_data;
                }
            }
            else {
                $xun_recipient_user = $xun_recipient["xun_user"];
                $xun_recipient_username = $xun_recipient_user["username"];
                $xun_recipient_address_data = $xun_recipient["user_address_data"];
                $this->recipient_address_data = $xun_recipient_address_data;
            }
            
            $xun_sender = $this->get_xun_user_by_crypto_address($account_address);

            if($xun_sender["code"] == 0){
                $xun_sender_username = $xun_sender["name"];
                $target = (in_array($target, ["internal", "external"]) && $xun_sender["type"]) ? $xun_sender["type"] : $target;
                $direction = 'send';

                if($xun_sender['type']== 'payment_gateway'){
                    $this->sender_address_data = $xun_sender;
                }

                if(isset($xun_sender["type"]) && !empty($xun_sender["type"])){
                    $xun_sender_address_data = array(
                        "user_id" => $xun_sender["type"],
                        "address" => $account_address
                    );

                    $this->sender_address_data = $xun_sender_address_data;
                }
            }
            else{
                $xun_sender_user = $xun_sender["xun_user"];
                $xun_sender_username = $xun_sender_user["username"];
                $xun_sender_address_data = $xun_sender["user_address_data"];
                $this->sender_address_data = $xun_sender_address_data;
                $this->sender_address_data["xun_user"] = $xun_sender_user;
            }

            if($type == "send" && $xun_sender_address_data["address_type"] == "reward"){
                $this->callback_address_type = "company_pool";
            }else if($type == "receive" && $xun_recipient_address_data["address_type"] == "reward"){
                $this->callback_address_type = "company_pool";
            }else{
                $this->callback_address_type = "internal_address";
            }
           
            $exchange_rate_arr = $params["exchangeRate"] == '' ? array("USD" => "0.00") : $params["exchangeRate"];

            $newParams["target_username"] = $target_username;
            $newParams["account_address"] = $account_address;
            $newParams["recipient"] = $recipient;
            $newParams["type"] = $type;
            $newParams["wallet_type"] = $wallet_type;
            $newParams["target"] = $target;
            $newParams["reference_address"] = $reference_address;
            $newParams["amount"]  = $amount;
            $newParams["fee"] = $fee;
            $newParams["transaction_hash"] = $transaction_hash;
            $newParams["exchange_rate"] = json_encode($exchange_rate_arr);
            $newParams["confirmation"] = $confirmation;
            $newParams["status"] = $status;
            $newParams["timestamp"] = $timestamp;
            $newParams["id"] = $params["id"];
            $newParams["time"] = $params["time"];
            $newParams["is_contract"] = $params["isContract"];
            
            $newParams["crypto_callback"] = "wallet";
            
            if($params["isContract"]){
                $contract_details = $params["contractDetails"];
                $newParams["contract_name"] = $contract_details["name"];
                $newParams["contract_reference_id"] = $contract_details["referenceID"];
                $newParams["contract_status"] = $contract_details["status"];
                $newParams["contract_address"] = $contract_details["contractAddress"];
                $newParams["escrow_address"] = $contract_details["escrowAddress"];
                $newParams["escrow_nounce"] = $contract_details["escrowNounce"];
                $newParams["nounce"] = $contract_details["nonce"];
            }

            //$this->insert_business_sending_queue($newParams, $xun_sender_user, $xun_recipient_user);

            $prefix  = substr($recipient, 0, 2);
    
            if($prefix != "0x") $recipient = "0x".$recipient;
            
            $prefix  = substr($account_address, 0, 2);
    
            if($prefix != "0x") $account_address = "0x".$account_address;


            if($oriTarget == "internal"){
                // check escrow transaction

               $padded_amount = $this->check_crypto_internal_transaction($params, $amount_decimal);
           }
           elseif($oriTarget == "external"){
               $padded_amount = $this->check_crypto_external_transaction($params, $amount_decimal);
           }




            $transaction_callback_user = null;

            //send notification
            if($target == 'external' && $type == 'send'){
                $tag = "Wallet Fund Out";
                $tagAmt = "Wallet Fund Out with Amount";
                $db->where("address", $account_address);
                $address_result = $db->getOne("xun_crypto_user_address");
                $user_id = $address_result["user_id"];

                $db->where("id", $user_id);
                $transaction_callback_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, disabled, type");
                $username = $transaction_callback_user["username"];
                $nickname = $transaction_callback_user["nickname"];
                $user_type = $transaction_callback_user["type"];

                $device_info_arr = $this->get_user_device_info($username);
                $user_device = $device_info_arr[$username];

                $db->where("user_id", $user_id);
                $db->where("name", "lastLoginIP");
                $ip_callback_user = $db->getOne("xun_user_setting", "value");
                $ip = $ip_callback_user["value"];
                
                $msg = "Sender\n";
                if($user_type == "user"){
                    $user_country_info_arr = $xunUser->get_user_country_info([$username]);
                    $user_country_info = $user_country_info_arr[$username];
                    $user_country = $user_country_info["name"];
                    $msg .= "Username: $nickname\n";
                    $msg .= "Phone Number: $username\n";
                    $msg .= "IP: $ip\n";
                    $msg .= "Country: $user_country\n";
                    $msg .= "Device: $user_device\n";
                }else{
                    $msg .= "Business Name: $nickname\n";
                }

                $msg .= "\nSend To\n";
                $msg .= "Wallet Address: $recipient\n";
                $msg .= "Tx ID: $transaction_hash\n";

                $msg .= "\nCoin: $wallet_type\n";

                $msgMeetingTeam = $msg . "Amount: $padded_amount\n";
                $msgMeetingTeam .= "Status: $status\n";
                $msgMeetingTeam .= "Time: $created_at\n";

                $msg .= "Status: $status\n";
                $msg .= "Time: $created_at\n";
                
                //print_r($msg);
            } else if($target == 'external' && $type == 'receive'){
                $tag = "Wallet Fund In";
                $tagAmt = "Wallet Fund In with Amount";
                
                $db->where("address", $recipient);
                $address_result = $db->getOne("xun_crypto_user_address");
                $user_id = $address_result["user_id"];
                $db->where("id", $user_id);
                $transaction_callback_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");
                $username = $transaction_callback_user["username"];
                $nickname = $transaction_callback_user["nickname"];
                $user_type = $transaction_callback_user["type"];
                $device_info_arr = $this->get_user_device_info($sender, $username);
                $user_device = $device_info_arr[$username];

                $db->where("user_id", $user_id);
                $db->where("name", "lastLoginIP");
                $ip_callback_user = $db->getOne("xun_user_setting", "value");
                $ip = $ip_callback_user["value"];

                $msg = "Recipient\n";
                if($user_type == "user"){
                    $user_country_info_arr = $xunUser->get_user_country_info([$username]);
                    $user_country_info = $user_country_info_arr[$username];
                    $user_country = $user_country_info["name"];
    
                    $msg .= "Username: $nickname\n";
                    $msg .= "Phone Number: $username\n";
                    $msg .= "IP: $ip\n";
                    $msg .= "Country: $user_country\n";
                    $msg .= "Device: $user_device\n";
                }else{
                    $msg .= "Business Name: $nickname\n";
                }

                $msg .= "\nReceive From\n";
                $msg .= "Wallet Address: $account_address\n";
                $msg .= "Tx ID: $transaction_hash\n";

                $msg .= "\nCoin: $wallet_type\n";

                $msgMeetingTeam = $msg . "Amount: $padded_amount\n";
                $msgMeetingTeam .= "Status: $status\n";
                $msgMeetingTeam .= "Time: $created_at\n";

                $msg .= "Status: $status\n";
                $msg .= "Time: $created_at\n";
            } else if($target == 'internal' && $type == 'send'){
                $tag = "Wallet Internal Send/Receive";
                $tagAmt = "Wallet Internal Send & Receive with Amount";
                
                $db->where("address", $account_address);
                $sender_result = $db->getOne("xun_crypto_user_address");
                $sender_user_id = $sender_result["user_id"];
                
                $db->where("address", $recipient);
                $recipient_result = $db->getOne("xun_crypto_user_address");
                $recipient_user_id = $recipient_result["user_id"];

                $db->where("id", $sender_user_id);
                $sender_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");
                $sender_username = $sender_user["username"];
                $sender_nickname = $sender_user["nickname"];
                $sender_type = $sender_user["type"];

                $db->where("user_id", $sender_user_id);
                $db->where("name", "lastLoginIP");
                $ip_callback_sender = $db->getOne("xun_user_setting", "value");
                $sender_ip = $ip_callback_sender["value"];

                $db->where("id", $recipient_user_id);
                $recipient_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");
                $recipient_username = $recipient_user["username"];
                $recipient_nickname = $recipient_user["nickname"];
                $recipient_type = $recipient_user["type"];

                $db->where("user_id", $recipient_user_id);
                $db->where("name", "lastLoginIP");
                $ip_callback_recipient = $db->getOne("xun_user_setting", "value");
                $recipient_ip = $ip_callback_recipient["value"];

                $device_info_arr = $this->get_user_device_info($sender_username, $recipient_username);
                
                $sender_device = $device_info_arr[$sender_username];
                $recipient_device = $device_info_arr[$recipient_username];

                $user_country_info_arr = $xunUser->get_user_country_info([$sender_username, $recipient_username]);
                $sender_country_info = $user_country_info_arr[$sender_username];
                $sender_country = $sender_country_info["name"];
                $recipient_country_info = $user_country_info_arr[$recipient_username];
                $recipient_country = $recipient_country_info["name"];

                
                $msg = "Type: Sending\n";
                $msg .= "Sender\n";
                if($sender_type == "user"){
                    $msg .= "Username: $sender_nickname\n";
                    $msg .= "Phone number: $sender_username\n";
                    $msg .= "IP: $sender_ip\n";
                    $msg .= "Country: $sender_country\n";
                    $msg .= "Device: $sender_device\n";
                }else{
                    $msg .= "Business Name: $sender_nickname\n";
                }
                $msg .= "\nRecipient\n";
                if($recipient_type == "user"){
                    $msg .= "Username: $recipient_nickname\n";
                    $msg .= "Phone number: $recipient_username\n";
                    $msg .= "IP: $recipient_ip\n";
                    $msg .= "Country: $recipient_country\n";
                    $msg .= "Device: $recipient_device\n";
                }else{
                    $msg .= "Business Name: $recipient_nickname\n";
                }

                //$msg .= "\nSending method: $sending_method\n";
                $msg .= "\nCoin: $wallet_type\n";

                $msgMeetingTeam = $msg . "Amount: $padded_amount\n";
                $msgMeetingTeam .= "Status: $status\n";
                $msgMeetingTeam .= "Time: $created_at\n";

                $msg .= "Status: $status\n";
                $msg .= "Time: $created_at\n";

                $transaction_callback_user = $sender_user;
                $transaction_callback_user["receiver_user"] = $recipient_user;
                
            } else if($target == 'internal' && $type == 'receive'){

                $recipient = $reference_address;

                $tag = "Wallet Internal Send/Receive";
                $tagAmt = "Wallet Internal Send & Receive with Amount";

                $db->where("address", $account_address);
                $sender_result = $db->getOne("xun_crypto_user_address");
                $sender_user_id = $sender_result["user_id"];
                
                $db->where("address", $recipient);
                $recipient_result = $db->getOne("xun_crypto_user_address");
                $recipient_user_id = $recipient_result["user_id"];
                

                $db->where("id", $sender_user_id);
                $sender_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");
                $sender_nickname = $sender_user["nickname"];
                $sender_username = $sender_user["username"];
                $sender_type = $sender_user["type"];

                $db->where("user_id", $sender_user_id);
                $db->where("name", "lastLoginIP");
                $ip_callback_sender = $db->getOne("xun_user_setting", "value");
                $sender_ip = $ip_callback_sender["value"];

                $db->where("id", $recipient_user_id);
                $recipient_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");
                $recipient_username = $recipient_user["username"];
                $recipient_nickname = $recipient_user["nickname"];
                $recipient_type = $recipient_user["type"];

                $db->where("user_id", $recipient_user_id);
                $db->where("name", "lastLoginIP");
                $ip_callback_recipient = $db->getOne("xun_user_setting", "value");
                $recipient_ip = $ip_callback_recipient["value"];
                
                $device_info_arr = $this->get_user_device_info($sender_username, $recipient_username);
                
                $sender_device = $device_info_arr[$sender_username];
                $recipient_device = $device_info_arr[$recipient_username];

                $user_country_info_arr = $xunUser->get_user_country_info([$sender_username, $recipient_username]);
                $sender_country_info = $user_country_info_arr[$sender_username];
                $sender_country = $sender_country_info["name"];
                $recipient_country_info = $user_country_info_arr[$recipient_username];
                $recipient_country = $recipient_country_info["name"];

                $msg = "Type: Receiving\n";
                $msg .= "Sender\n";
                if($sender_type == "user"){
                    $msg .= "Username: $sender_nickname\n";
                    $msg .= "Phone number: $sender_username\n";
                    $msg .= "IP: $sender_ip\n";
                    $msg .= "Country: $sender_country\n";
                    $msg .= "Device: $sender_device\n";
                }else{
                    $msg .= "Business Name: $sender_nickname\n";
                }
                $msg .= "\nRecipient\n";
                if($recipient_type == "user"){
                    $msg .= "Username: $recipient_nickname\n";
                    $msg .= "Phone number: $recipient_username\n";
                    $msg .= "IP: $recipient_ip\n";
                    $msg .= "Country: $recipient_country\n";
                    $msg .= "Device: $recipient_device\n";
                }else{
                    $msg .= "Business Name: $recipient_nickname\n";
                }
                //$msg .= "\nSending method: $sending_method\n";
                $msg .= "\nCoin: $wallet_type\n";

                $msgMeetingTeam = $msg . "Amount: $padded_amount\n";
                $msgMeetingTeam .= "Status: $status\n";
                $msgMeetingTeam .= "Time: $created_at\n";

                $msg .= "Status: $status\n";
                $msg .= "Time: $created_at\n";

                $transaction_callback_user = $recipient_user;
                $transaction_callback_user["sender_user"] = $sender_user;

                
            }
            // else if($target == 'escrow' && $type == 'receive'){
            //     if($direction == 'receive'){

            //         $tag = "Escrow Wallet Receive";
                    
            //         $db->where("address", $account_address);
            //         $sender_result = $db->getOne("xun_crypto_user_address");
            //         $sender_user_id = $sender_result["user_id"];
                    
            //         $db->where("id", $sender_user_id);
            //         $sender_user = $db->getOne("xun_user", "id, username, nickname, type");
            //         $sender_username = $sender_user["username"];
            //         $sender_nickname = $sender_user["nickname"];
    
            //         $device_info_arr = $this->get_user_device_info($sender_username);
            //         $sender_device = $device_info_arr[$sender_username];
    
            //         $user_country_info_arr = $xunUser->get_user_country_info([$sender_username]);
            //         $sender_country_info = $user_country_info_arr[$sender_username];
            //         $sender_country = $sender_country_info["name"];
    
            //         $msg .= "Sender: $sender_username\n";
            //         $msg .= "Username: $sender_nickname\n";
            //         $msg .= "Device: $sender_device\n";
            //         $msg .= "Country: $sender_country\n";
            //         $msg .= "Recipient: Escrow\n";
            //         $msg .= "Status: $status\n";
            //         $msg .= "Time: $created_at\n";
            //     }else{
            //         $tag = "Wallet Escrow Send/Receive";
                
            //         $db->where("address", $recipient);
            //         $recipient_result = $db->getOne("xun_crypto_user_address");
            //         $recipient_user_id = $recipient_result["user_id"];
                    
            //         $db->where("id", $recipient_user_id);
            //         $recipient_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");
            //         $recipient_username = $recipient_user["username"];
            //         $recipient_nickname = $recipient_user["nickname"];
                        
            //         $device_info_arr = $this->get_user_device_info($recipient_username);
            //         $recipient_device = $device_info_arr[$recipient_username];

            //         $user_country_info_arr = $xunUser->get_user_country_info([$recipient_username]);
            //         $recipient_country_info = $user_country_info_arr[$recipient_username];
            //         $recipient_country = $recipient_country_info["name"];

            //         $recipient_country_info = $user_country_info_arr[$recipient_username];
            //         $recipient_country = $recipient_country_info["name"];

            //         $msg = "Type: Receive\n";
            //         $msg .= "Sender: Escrow\n";
            //         $msg .= "Recipient: $recipient_username\n";
            //         $msg .= "Username: $recipient_nickname\n";
            //         $msg .= "Country: $recipient_country\n";
            //         $msg .= "Device: $recipient_device\n";
            //         $msg .= "Coin: $wallet_type\n";

            //         $msgMeetingTeam = $msg . "Amount: $padded_amount\n";
            //         $msgMeetingTeam .= "Status: $status\n";
            //         $msgMeetingTeam .= "Time: $created_at\n";

            //         $msg .= "Status: $status\n";
            //         $msg .= "Time: $created_at\n";

            //         $transaction_callback_user = $recipient_user;
            //     }
            // }
            // else if($target == 'escrow' && $type == 'send'){
            //     if($direction == 'send'){
            //         $tag = "Escrow Wallet Send";
                    
            //         $db->where("address", $recipient);
            //         $recipient_result = $db->getOne("xun_crypto_user_address");
            //         $recipient_user_id = $recipient_result["user_id"];
    
            //         $db->where("id", $recipient_user_id);
            //         $recipient_user = $db->getOne("xun_user", "id, username, nickname, type");
            //         $recipient_username = $recipient_user["username"];
            //         $recipient_nickname = $recipient_user["nickname"];
    
            //         $device_info_arr = $this->get_user_device_info($recipient_username);
            //         $recipient_device = $device_info_arr[$recipient_username];
    
            //         $user_country_info_arr = $xunUser->get_user_country_info([$recipient_username]);
            //         $recipient_country_info = $user_country_info_arr[$recipient_username];
            //         $recipient_country = $recipient_country_info["name"];
    
            //         $msg .= "Sender: Escrow\n";
            //         $msg .= "Recipient: $recipient_username\n";
            //         $msg .= "Username: $recipient_nickname\n";
            //         $msg .= "Device: $recipient_device\n";
            //         $msg .= "Country: $recipient_country\n";
            //         $msg .= "Status: $status\n";
            //         $msg .= "Time: $created_at\n";
            //     }else{
            //         $tag = "Wallet Escrow Send/Receive";
                    
            //         $db->where("address", $account_address);
            //         $sender_result = $db->getOne("xun_crypto_user_address");
            //         $sender_user_id = $sender_result["user_id"];
                    
            //         $db->where("id", $sender_user_id);
            //         $sender_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");
            //         $sender_username = $sender_user["username"];
            //         $sender_nickname = $sender_user["nickname"];

            //         $device_info_arr = $this->get_user_device_info($sender_username);
            //         $sender_device = $device_info_arr[$sender_username];
                    
            //         $user_country_info_arr = $xunUser->get_user_country_info([$sender_username]);
            //         $sender_country_info = $user_country_info_arr[$sender_username];
            //         $sender_country = $sender_country_info["name"];

            //         $msg = "Type: Send\n";
            //         $msg .= "Sender: $sender_username\n";
            //         $msg .= "Username: $sender_nickname\n";
            //         $msg .= "Country: $sender_country\n";
            //         $msg .= "Device: $sender_device\n";
            //         $msg .= "Recipient: Escrow\n";
            //         $msg .= "Coin: $wallet_type\n";

            //         $msgMeetingTeam = $msg . "Amount: $padded_amount\n";
            //         $msgMeetingTeam .= "Status: $status\n";
            //         $msgMeetingTeam .= "Time: $created_at\n";

            //         $msg .= "Status: $status\n";
            //         $msg .= "Time: $created_at\n";

            //         $transaction_callback_user = $sender_user;
            //     }
                
            // } 
            else if($target == 'trading_fee' && $type == 'receive'){
                $tag = "Trading Fee Wallet Receive";

                if($xun_sender_user){
                    $sender_nickname = $xun_sender_user["nickname"];
                    $sender_username = $xun_sender_user["username"];

                    $device_info_arr = $this->get_user_device_info($sender_username);
                    $sender_device = $device_info_arr[$sender_username];
                    
                    $user_country_info_arr = $xunUser->get_user_country_info([$sender_username]);
                    $sender_country_info = $user_country_info_arr[$sender_username];
                    $sender_country = $sender_country_info["name"];
                    
                    $msg .= "Sender: $sender_username\n";
                    $msg .= "Username: $sender_nickname\n";
                    $msg .= "Country: $sender_country\n";
                    $msg .= "Device: $sender_device\n";
                }
                $msg .= "Recipient: Trading Fee\n";
                $msg .= "Status: $status\n";
                $msg .= "Time: $created_at\n";
                
            } else if($target == 'trading_fee' && $type == 'send'){
                $tag = "Trading Fee Wallet Send";
                
                $db->where("address", $recipient);
                $recipient_result = $db->getOne("xun_crypto_user_address");
                $recipient_user_id = $recipient_result["user_id"];

                $db->where("id", $recipient_user_id);
                $recipient_user = $db->getOne("xun_user", "id, username, nickname, type");
                $recipient_username = $recipient_user["username"];
                $recipient_nickname = $recipient_user["nickname"];

                $device_info_arr = $this->get_user_device_info($recipient_username);
                $recipient_device = $device_info_arr[$recipient];

                $user_country_info_arr = $xunUser->get_user_country_info([$recipient_username]);
                $recipient_country_info = $user_country_info_arr[$recipient_username];
                $recipient_country = $recipient_country_info["name"];
                
                $msg .= "Sender: Trading Fee\n";
                $msg .= "Recipient: $recipient_username\n";
                $msg .= "Username: $recipient_nickname\n";
                $msg .= "Device: $recipient_device\n";
                $msg .= "Country: $recipient_country\n";
                $msg .= "Status: $status\n";
                $msg .= "Time: $created_at\n";
                
            } else if($target == 'company_pool' && $type == 'receive'){
                $tag = "Company Pool Wallet Receive";

                $msg .= "Sender: Trading Fee\n";
                $msg .= "Recipient: Company Pool\n";
                $msg .= "Status: $status\n";
                $msg .= "Time: $created_at\n";
                
            } else if($target == 'company_pool' && $type == 'send'){
                $tag = "Company Pool Wallet Send";
                
                $msg .= "Sender: Company Pool\n";
                if($xun_recipient_user){
                    $db->where("address", $recipient);
                    $recipient_result = $db->getOne("xun_crypto_user_address");
                    $recipient_user_id = $recipient_result["user_id"];
    
                    $db->where("id", $recipient_user_id);
                    $recipient_user = $db->getOne("xun_user", "id, username, nickname, type");
                    $recipient_username = $recipient_user["username"];
                    $recipient_nickname = $recipient_user["nickname"];
    
                    $device_info_arr = $this->get_user_device_info($recipient_username);
                    $recipient_device = $device_info_arr[$recipient_username];
    
                    $user_country_info_arr = $xunUser->get_user_country_info([$recipient_username]);
                    $recipient_country_info = $user_country_info_arr[$recipient_username];
                    $recipient_country = $recipient_country_info["name"];

                    $msg .= "Recipient: $recipient_username\n";
                    $msg .= "Username: $recipient_nickname\n";
                    $msg .= "Device: $recipient_device\n";
                    $msg .= "Country: $recipient_country\n";
                }else{
                    $msg .= "Recipient: Company Account\n";
                }
                $msg .= "Status: $status\n";
                $msg .= "Time: $created_at\n";
            }
            // else if($target == 'freecoin' && $type == 'send'){
            //     $tag = "Free coin Wallet Send";
                
            //     $db->where("address", $recipient);
            //     $recipient_result = $db->getOne("xun_crypto_user_address");
            //     $recipient_user_id = $recipient_result["user_id"];

            //     $db->where("id", $recipient_user_id);
            //     $recipient_user = $db->getOne("xun_user", "id, username, nickname, type");
            //     $recipient_username = $recipient_user["username"];
            //     $recipient_nickname = $recipient_user["nickname"];

            //     $device_info_arr = $this->get_user_device_info($recipient_username);
            //     $recipient_device = $device_info_arr[$recipient_username];

            //     $user_country_info_arr = $xunUser->get_user_country_info([$recipient_username]);
            //     $recipient_country_info = $user_country_info_arr[$recipient_username];
            //     $recipient_country = $recipient_country_info["name"];
                
            //     $msg .= "Sender: Free coin\n";
            //     $msg .= "Recipient: $recipient_username\n";
            //     $msg .= "Username: $recipient_nickname\n";
            //     $msg .= "Device: $recipient_device\n";
            //     $msg .= "Country: $recipient_country\n";
            //     $msg .= "Status: $status\n";
            //     $msg .= "Time: $created_at\n";

            // } 
            else if($target == "payment_gateway"){

                $tag = "Payment Gateway Wallet";
                
                if($xun_sender_username){
                    $db->where("address", $account_address);
                    $sender_result = $db->getOne("xun_crypto_user_address");
                    $sender_user_id = $sender_result["user_id"];
                    
                    $db->where("id", $sender_user_id);
                    $sender_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");

                    if($sender_user) {
                        $sender_username = $sender_user["username"];
                        $sender_nickname = $sender_user["nickname"];
                    } else {

                        $db->where("d.type", $wallet_type);
                        $db->where("d.status", 1);
                        $db->where("d.destination_address", $reference_address);
                        $db->where("w.status", 1);
                        $db->join("xun_crypto_wallet w", "w.id=d.wallet_id", "INNER");
                        $db->join("xun_user u", "u.id=w.business_id", "INNER");
                        $sender_user = $db->getOne("xun_crypto_destination_address d", "u.id, u.username, u.nickname, u.wallet_callback_url");
                        
                        $sender_username = $sender_user["username"];
                        $sender_nickname = $sender_user["nickname"];
                    }
                    

                    $device_info_arr = $this->get_user_device_info($sender_username);
                    $sender_device = $device_info_arr[$sender_username];
                    
                    $user_country_info_arr = $xunUser->get_user_country_info([$sender_username]);
                    $sender_country_info = $user_country_info_arr[$sender_username];
                    $sender_country = $sender_country_info["name"];

                    $msg = "Type: Send\n";
                    $msg .= "Sender: $sender_username\n";
                    $msg .= "Username: $sender_nickname\n";
                    $msg .= "Country: $sender_country\n";
                    $msg .= "Device: $sender_device\n";
                    $msg .= "Recipient: Payment Gateway\n";
                    $msg .= "Coin: $wallet_type\n";

                    $msgMeetingTeam = $msg . "Amount: $padded_amount\n";

                    $msg .= "Status: $status\n";
                    $msg .= "Time: $created_at\n";

                    $transaction_callback_user = $sender_user;
                }else{
                    $db->where("address", $recipient);
                    $recipient_result = $db->getOne("xun_crypto_user_address");
                    $recipient_user_id = $recipient_result["user_id"];
                    
                    $db->where("id", $recipient_user_id);
                    $recipient_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");
                    $recipient_username = $recipient_user["username"];
                    $recipient_nickname = $recipient_user["nickname"];
                        
                    $device_info_arr = $this->get_user_device_info($recipient_username);
                    $recipient_device = $device_info_arr[$recipient_username];

                    $user_country_info_arr = $xunUser->get_user_country_info([$recipient_username]);
                    $recipient_country_info = $user_country_info_arr[$recipient_username];
                    $recipient_country = $recipient_country_info["name"];

                    $recipient_country_info = $user_country_info_arr[$recipient_username];
                    $recipient_country = $recipient_country_info["name"];

                    $msg = "Type: Receive\n";
                    $msg .= "Sender: Payment Gateway\n";
                    $msg .= "Recipient: $recipient_username\n";
                    $msg .= "Username: $recipient_nickname\n";
                    $msg .= "Country: $recipient_country\n";
                    $msg .= "Device: $recipient_device\n";
                    $msg .= "Coin: $wallet_type\n";

                    $msgMeetingTeam = $msg . "Amount: $padded_amount\n";
                    $msgMeetingTeam .= "Status: $status\n";
                    $msgMeetingTeam .= "Time: $created_at\n";

                    $transaction_callback_user = $recipient_user;
                }
            }
            // else if($target == "topup"){
            //     $tag = "Top Up Wallet";

            //     if($type == "receive"){
            //         if($direction == 'receive'){
            //             //  top up wallet receive
            //             $tag .= " Receive";
                        
            //             $db->where("address", $account_address);
            //             $sender_result = $db->getOne("xun_crypto_user_address");
            //             $sender_user_id = $sender_result["user_id"];
                        
            //             $db->where("id", $sender_user_id);
            //             $sender_user = $db->getOne("xun_user", "id, username, nickname, type");
            //             $sender_username = $sender_user["username"];
            //             $sender_nickname = $sender_user["nickname"];
        
            //             $device_info_arr = $this->get_user_device_info($sender_username);
            //             $sender_device = $device_info_arr[$sender_username];
        
            //             $user_country_info_arr = $xunUser->get_user_country_info([$sender_username]);
            //             $sender_country_info = $user_country_info_arr[$sender_username];
            //             $sender_country = $sender_country_info["name"];
        
            //             $msg .= "Sender: $sender_username\n";
            //             $msg .= "Username: $sender_nickname\n";
            //             $msg .= "Device: $sender_device\n";
            //             $msg .= "Country: $sender_country\n";
            //             $msg .= "Coin: $wallet_type\n";
            //             $msg .= "Amount: $padded_amount\n";
            //             $msg .= "Status: $status\n";
            //             $msg .= "Time: $created_at\n";
            //         }else{
            //             $tag = "Wallet Top Up Send/Receive";
                    
            //             $db->where("address", $recipient);
            //             $recipient_result = $db->getOne("xun_crypto_user_address");
            //             $recipient_user_id = $recipient_result["user_id"];
                        
            //             $db->where("id", $recipient_user_id);
            //             $recipient_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");
            //             $recipient_username = $recipient_user["username"];
            //             $recipient_nickname = $recipient_user["nickname"];
                            
            //             $device_info_arr = $this->get_user_device_info($recipient_username);
            //             $recipient_device = $device_info_arr[$recipient_username];
    
            //             $user_country_info_arr = $xunUser->get_user_country_info([$recipient_username]);
            //             $recipient_country_info = $user_country_info_arr[$recipient_username];
            //             $recipient_country = $recipient_country_info["name"];
    
            //             $recipient_country_info = $user_country_info_arr[$recipient_username];
            //             $recipient_country = $recipient_country_info["name"];
    
            //             $msg = "Type: Receive\n";
            //             $msg .= "Recipient: $recipient_username\n";
            //             $msg .= "Username: $recipient_nickname\n";
            //             $msg .= "Country: $recipient_country\n";
            //             $msg .= "Device: $recipient_device\n";
            //             $msg .= "Coin: $wallet_type\n";
            //             $msg .= "Amount: $padded_amount\n";
            //             $msg .= "Status: $status\n";
            //             $msg .= "Time: $created_at\n";
    
            //             $transaction_callback_user = $recipient_user;
            //         }
            //     }else{
            //         if($direction == 'send'){
            //             $tag .= " Send";
                        
            //             $db->where("address", $recipient);
            //             $recipient_result = $db->getOne("xun_crypto_user_address");
            //             $recipient_user_id = $recipient_result["user_id"];
        
            //             $db->where("id", $recipient_user_id);
            //             $recipient_user = $db->getOne("xun_user", "id, username, nickname, type");
            //             $recipient_username = $recipient_user["username"];
            //             $recipient_nickname = $recipient_user["nickname"];
        
            //             $device_info_arr = $this->get_user_device_info($recipient_username);
            //             $recipient_device = $device_info_arr[$recipient_username];
        
            //             $user_country_info_arr = $xunUser->get_user_country_info([$recipient_username]);
            //             $recipient_country_info = $user_country_info_arr[$recipient_username];
            //             $recipient_country = $recipient_country_info["name"];
        
            //             $msg .= "Recipient: $recipient_username\n";
            //             $msg .= "Username: $recipient_nickname\n";
            //             $msg .= "Device: $recipient_device\n";
            //             $msg .= "Country: $recipient_country\n";
            //             $msg .= "Coin: $wallet_type\n";
            //             $msg .= "Amount: $padded_amount\n";
            //             $msg .= "Status: $status\n";
            //             $msg .= "Time: $created_at\n";
            //         }else{
            //             $tag = "Wallet Top Up Send/Receive";
                        
            //             $db->where("address", $account_address);
            //             $sender_result = $db->getOne("xun_crypto_user_address");
            //             $sender_user_id = $sender_result["user_id"];
                        
            //             $db->where("id", $sender_user_id);
            //             $sender_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");
            //             $sender_username = $sender_user["username"];
            //             $sender_nickname = $sender_user["nickname"];
    
            //             $device_info_arr = $this->get_user_device_info($sender_username);
            //             $sender_device = $device_info_arr[$sender_username];
                        
            //             $user_country_info_arr = $xunUser->get_user_country_info([$sender_username]);
            //             $sender_country_info = $user_country_info_arr[$sender_username];
            //             $sender_country = $sender_country_info["name"];
    
            //             $msg = "Type: Send\n";
            //             $msg .= "Sender: $sender_username\n";
            //             $msg .= "Username: $sender_nickname\n";
            //             $msg .= "Country: $sender_country\n";
            //             $msg .= "Device: $sender_device\n";
            //             $msg .= "Coin: $wallet_type\n";
            //             $msg .= "Amount: $padded_amount\n";
            //             $msg .= "Status: $status\n";
            //             $msg .= "Time: $created_at\n";
    
            //             $transaction_callback_user = $sender_user;
            //         }
            //     }
            // }



            $thenux_params["tag"]         = $tag;
            $thenux_params["message"]     = $msg;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result                = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");


            if (!empty($msgMeetingTeam)){
                $mobile_list = $config["meetingTeamMobileList"];
                $thenux_params["tag"]         = $tagAmt;
                $thenux_params["message"]     = $msgMeetingTeam;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result                = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
            }
            //end send notification


            // send transaction callback
            if (!empty($transaction_callback_user)){
                // call post_wallet_callback
                $this->post_wallet_callback($params, $transaction_callback_user);
            }
            

            $xunCompanyWalletAPI->cryptoCallbackAndUpdateBCStage($account_address, $recipient, $transaction_hash, $type);


            return array("status" => "ok", "statusMsg" => "success", "code" => 1, "params" => $params, "erlangReturn" => $erlangReturn);
            
        }

        function post_wallet_callback($crypto_params, $xun_user){
            global $setting;
            $db     = $this->db;
            $post   = $this->post;

            // get myr rate
            $wallet_callback_url = $xun_user["wallet_callback_url"];
            if(!empty($wallet_callback_url)){
                $username = $xun_user["username"];
                $nickname = $xun_user["nickname"];
                if(isset($xun_user["receiver_user"])){
                    $receiver_user = $xun_user["receiver_user"];
                    $crypto_params["receiver_nickname"] = $receiver_user["nickname"];
                    $crypto_params["receiver_mobile_number"] = $receiver_user["username"];
                }elseif(isset($xun_user["sender_user"])){
                    $sender_user = $xun_user["sender_user"];
                    $crypto_params["sender_nickname"] = $sender_user["nickname"];
                    $crypto_params["sender_mobile_number"] = $sender_user["username"];
                }
                $transaction_token = $crypto_params["transactionToken"];
                $exchangeRate = $crypto_params["exchangeRate"];
                $usd_rate = $exchangeRate["USD"];

                $db->where("currency", "myr");
                $myr_exchange = $db->getValue("xun_currency_rate", "exchange_rate");
                $myr_exchange = $setting->setDecimal($myr_exchange, "marketplacePrice");
                
                if(!empty($usd_rate)){
                    $myr_rate = bcmul((string)$usd_rate, (string)$myr_exchange, 8);
                }
                
                $myr_rate = $myr_rate ? $myr_rate : 0;
                
                if ($exchangeRate){
                    $exchangeRate["MYR"] = $myr_rate;
                }
                $crypto_params["exchangeRate"] = $exchangeRate;
                $crypto_params["mobile_number"] = $username;
                $crypto_params["nickname"] = $nickname ? $nickname : '';

                $db->where("transaction_token", $transaction_token);
                $transaction_ref_id = $db->getValue("xun_crypto_user_transaction_verification", "reference_id");

                $crypto_params["qrRef"] = $transaction_ref_id;
                //send callback
                // $cryptoResult = $post->curl_crypto("walletCallback", $crypto_params, 0, $wallet_callback_url);
                $curl_params = array(
                    "command" => "walletCallback",
                    "params" => $crypto_params
                );
                $cryptoResult = $post->curl_post($wallet_callback_url, $curl_params, 0);
            }
        }

        function post_nuxpay_wallet_callback($params, $pg_transaction_token, $payment_callback_url, $payment_id, $business_id){

            global $webservice;
            $db = $this->db;
            $post = $this->post;

            $exchangeRate = $params['exchangeRate']['USD'];
            $amount = bcdiv($params['amount'], $params['amountRate'], (strlen($params['amountRate'])-1) ); 

            $miner = bcdiv($params['fee'], $params['feeRate'], (strlen($params['feeRate'])-1) );

            $minerusd = bcmul($miner, $params['minerFeeExchangeRate'], 4);
            $minercoin = bcdiv($minerusd, $exchangeRate, (strlen($params['amountRate'])-1) );
            $minercoinSatoshi = bcmul($minercoin, $params['amountRate']);

            $status = $params['status']=="confirmed"?"success":$params['status'];


            //INPUT
            $tx_input['receivedTxID'] = $params['exTransactionHash'];
            $tx_input['amount'] = $amount;
            $tx_input['unit'] = $params['amountUnit'];
            $tx_input['type'] = $params['wallet_type'];
            $tx_input['exchangeRate'] = $params['exchangeRate']['USD'];
            $tx_input['referenceID'] = $params['referenceID'];
            $tx_input['charges'] = array("amount"=>"0", "unit"=>$params['amountUnit'], "type"=>$params['wallet_type'], "exchangeRate"=>$exchangeRate);
            $tx_input['minerFee'] = array("amount"=>$minercoin, "unit"=>$params['amountUnit'], "type"=>$params['wallet_type'], "exchangeRate"=>$exchangeRate);
            $tx_input['ethMinerFee'] = array("amount"=>$miner, "unit"=>$params['feeUnit'], "type"=>$params['minerFeeWalletType'], "exchangeRate"=>$params['minerFeeExchangeRate']);


            //OUTPUT
            $tx_output['destination'] = array("amount"=>$amount, "unit"=>$params['amountUnit'], "type"=>$params['wallet_type'], "exchangeRate"=>$exchangeRate);
            $tx_output['charges'] = array("amount"=>"0", "unit"=>$params['amountUnit'], "type"=>$params['wallet_type'], "exchangeRate"=>$exchangeRate);
            $tx_output['minerFee'] = array("amount"=>$minercoin, "unit"=>$params['amountUnit'], "type"=>$params['wallet_type'], "exchangeRate"=>$exchangeRate);
            $tx_output['ethMinerFee'] = array("amount"=>$miner, "unit"=>$params['feeUnit'], "type"=>$params['minerFeeWalletType'], "exchangeRate"=>$params['minerFeeExchangeRate']);


            //CREDIT DETAIL
            $tx_credit['amountDetails'] = array("amount"=>$params['amount'], "unit"=>$params['amountUnit'], "rate"=>$params['amountRate'], "type"=>$params['wallet_type'], "exchangeRate"=>$params['exchangeRate']);

            $tx_credit['amountReceiveDetails'] = array("amount"=>$params['amount'], "unit"=>$params['amountUnit'], "rate"=>$params['amountRate'], "type"=>$params['wallet_type'], "exchangeRate"=>$params['exchangeRate']);

            $tx_credit['serviceChargeDetails'] = array("amount"=>"0", "unit"=>$params['amountUnit'], "rate"=>$params['amountRate'], "type"=>$params['wallet_type'], "exchangeRate"=>$params['exchangeRate']);

            $tx_credit['minerAmountDetails'] = array("amount"=>$minercoinSatoshi, "unit"=>$params['amountUnit'], "rate"=>$params['amountRate'], "type"=>$params['wallet_type'], "exchangeRate"=>$params['exchangeRate']);

            $tx_credit['ethMinerAmountDetails'] = array("amount"=>$params['fee'], "unit"=>$params['feeUnit'], "rate"=>$params['feeRate'], "type"=>$params['minerFeeWalletType'], "exchangeRate"=>array("USD"=>$params['minerFeeExchangeRate']));


            //
            $pg_params['receivedTxID'] = "";
            $pg_params['referenceID'] = $params['referenceID'];
            $pg_params['txDetails'] = array("input"=>array($tx_input), "output"=>array($tx_output));
            $pg_params['txID'] = $params['exTransactionHash'];
            $pg_params['amount'] = $amount." ".$params['amountUnit'];
            $pg_params['amountReceive'] = $amount." ".$params['amountUnit'];
            $pg_params['serviceCharge'] = "0 ".$params['amountUnit'];
            $pg_params['minerAmount'] = $minercoin." ".$params['amountUnit'];
            $pg_params['address'] = $params['recipient'];
            $pg_params['status'] = $status;
            $pg_params['transactionDate'] = $params['time'];
            $pg_params['transactionUrl'] = "";
            $pg_params['type'] = $params['wallet_type'];
            // $pg_params['transactionType'] = $params['target'];
            $pg_params['sender'] = array("internal"=>"", "external"=>$params['sender']);
            $pg_params['recipient'] = array("internal"=>$params['recipient'], "external"=>$params['referenceAddress']);
            $pg_params['creditDetails'] = $tx_credit;

            $db->where('transaction_token', $pg_transaction_token);
            $pg_payment_tx_data = $db->getOne('xun_payment_gateway_payment_transaction');
            if($pg_payment_tx_data){
                $client_reference_id = $pg_payment_tx_data['reference_id'];
                $pg_transaction_token = $pg_payment_tx_data['transaction_token'];
            }
            
            $pg_params['clientReferenceID'] = $client_reference_id ? $client_reference_id : '';
            $pg_params['transactionToken'] = $pg_transaction_token ? $pg_transaction_token : '';
            $pg_params['paymentTxID'] = $payment_id ? $payment_id : '';

            $db->where('payment_id', $payment_id);
            $payment_details_data = $db->getOne('xun_payment_details', 'id, tx_exchange_rate, fiat_currency_id');

            $tx_exchange_rate = $payment_details_data['tx_exchange_rate'];
            $fiat_currency_id = $payment_details_data['fiat_currency_id'];

            $fiat_tx_amount = bcmul($amount, $tx_exchange_rate,2);
            $pg_params['fiatDetails'] = array(
                "amount" => $fiat_tx_amount ? $fiat_tx_amount : '',
                "unit" => strtoupper($fiat_currency_id) ? strtoupper($fiat_currency_id) : '',
                "exchangeRate" => $tx_exchange_rate ? $tx_exchange_rate : '',
            );
            $pg_params['transactionType'] = 'zero_fee';
            $curl_params = array(
                "command" => "paymentGatewayCallback",
                "params" => $pg_params
            );

            $cryptoResult = $post->curl_post($payment_callback_url, $curl_params, 0);

            
            $webservice->developerOutgoingWebService($business_id, "paymentGatewayCallback", $payment_callback_url, json_encode($curl_params), json_encode($cryptoResult) );

        }
        
        function insert_business_sending_queue($crypto_params, $xun_sender_user, $xun_recipient_user, $tag = "Service Notification")
        {
            global $config;
            $server_host = $config["erlang_server"];

            $db = $this->db;
            $type = $crypto_params["type"];
            $target = $crypto_params["target"];

            if($xun_sender_user){
                $xun_sender_username = $xun_sender_user["username"];
                $xun_sender_type = $xun_sender_user["type"];
                $xun_sender_name = $xun_sender_user["nickname"];
                $xun_sender_id = $xun_sender_user["id"];
            }

            if($xun_recipient_user){
                $xun_recipient_username = $xun_recipient_user["username"];
                $xun_recipient_type = $xun_recipient_user["type"];
                $xun_recipient_name = $xun_recipient_user["nickname"];
                $xun_recipient_id = $xun_recipient_user["id"];
            }

            $target_username_arr = [];
            
            $is_business = false;

            $crypto_params["address_type"] = $this->callback_address_type;
            if($type == "send"){
                if($xun_sender_type == "user"){
                    $target_username = $xun_sender_username;
                    $target_username_arr = [$target_username];

                    // $chatroom_user_id = $xun_sender_user["id"];
                }else{
                    $business_id = $xun_sender_username;
                    $db->where("business_id", $business_id);
                    $db->where("status", 1);
                    $db->where("employment_status", "confirmed");
                    $xun_employee_arr = $db->map("mobile")->ArrayBuilder()->get("xun_employee", null, "mobile, old_id, name");
                    $employee_arr = array_map(function($v){
                        return $v["old_id"];
                    }, $xun_employee_arr);
                    $target_username_arr = array_keys($employee_arr);
                    $is_business = true;
                }
            }else{
                if($xun_recipient_type == "user"){
                    $target_username = $xun_recipient_username;
                    $target_username_arr = [$target_username];

                    // $chatroom_user_id = $xun_recipient_user["id"];
                }else{
                    $business_id = $xun_recipient_username;
                    $db->where("business_id", $business_id);
                    $db->where("status", 1);
                    $db->where("employment_status", "confirmed");
                    $xun_employee_arr = $db->map("mobile")->ArrayBuilder()->get("xun_employee", null, "mobile, old_id, name");
                    $employee_arr = array_map(function($v){
                        return $v["old_id"];
                    }, $xun_employee_arr);
                    
                    $target_username_arr = array_keys($employee_arr);
                    $is_business = true;

                    if($this->callback_address_type == "company_pool"){
                        //  add employee nickname
                        $reference_address = $crypto_params["reference_address"];
                        $db->where("a.external_address", $reference_address);
                        $db->join("xun_user b","a.user_id=b.id", "LEFT");
                        $recipient_employee_user_data = $db->getOne("xun_user_crypto_external_address a", "b.id, b.username");

                        $recipient_employee = $xun_employee_arr[$recipient_employee_user_data["username"]];
                        $recipient_employee_name = $recipient_employee["name"];
                    }
                }
            }

            if ($xun_recipient_type == "business" || $xun_sender_type == "business"){
                //  create livegroupchat

                if ($xun_recipient_type == "business" && $xun_sender_type == "business"){
                    $chatroom_user_id = $xun_recipient_id;
                    $chatroom_business_id = $xun_sender_id;
                } else if ($xun_recipient_type == "business"){
                    $chatroom_business_id = $xun_recipient_id;
                    if ($xun_sender_type == "user"){
                        $chatroom_user_id = $xun_sender_id;
                        $chatroom_user_mobile = $xun_sender_username;
                    }else{
                        $chatroom_user_id = '';
                        $chatroom_user_mobile = '';
                    }
                } else if ($xun_sender_type == "business"){
                    $chatroom_business_id = $xun_sender_id;

                    if ($xun_recipient_type == "user"){
                        $chatroom_user_id = $xun_recipient_id;
                        $chatroom_user_mobile = $xun_recipient_username;
                    }else{
                        $chatroom_user_id = '';
                        $chatroom_user_mobile = '';
                    }
                }

                $xunBusinessService = new XunBusinessService($db);


                $chatroom_data = $xunBusinessService->getLiveGroupChatRoomDetailsForBusinessToBusiness($chatroom_user_id, $chatroom_business_id);

                if(!$chatroom_data)
                {
                    //  create live group chat room
                    $chatroom_host = "livegroupchat." . $server_host;
                    
                    $chatroom_obj = new stdClass();
                    $chatroom_obj->user_id = $chatroom_user_id;
                    $chatroom_obj->business_id = $chatroom_business_id;                    
                    $chatroom_obj->chatroom_host = $chatroom_host;
                    $chatroom_obj->user_mobile = $chatroom_user_mobile;
                    $chatroom_obj->user_host = $server_host;
                    $chatroom_obj->employee_mobile = '';

                    $chatroom_data = $xunBusinessService->createLiveGroupChatRoom($chatroom_obj);
                }

                $chatroom_id = $chatroom_data["old_id"];
                $chatroom_host = $chatroom_data["host"];
                $chatroom_jid = $chatroom_id . '@' . $chatroom_host;
                $crypto_params["chatroom_jid"] = $chatroom_jid;
                $crypto_params["tag"] = $tag;
            }

            $insert_data = [];
            $xun_sender_type = $xun_sender_type ? ($xun_sender_type == "user" ? "personal" : $xun_sender_type) : '';

            $xun_recipient_type = $xun_recipient_type ? ($xun_recipient_type == "user" ? "personal" : $xun_recipient_type) : '';
            
            for($i = 0; $i < count($target_username_arr); $i++){
                $username = $target_username_arr[$i];
                $insert_params = $crypto_params;
                $insert_params["target_username"] = $username;
                $insert_params["xun_sender"] = $xun_sender_username ? $xun_sender_username : '';
                $insert_params["sender_type"] = $xun_sender_type ? $xun_sender_type : '';
                $insert_params["sender_name"] = $xun_sender_name ? $xun_sender_name : '';
                $insert_params["xun_recipient"] = $xun_recipient_username ? $xun_recipient_username : '';
                $insert_params["recipient_type"] = $xun_recipient_type ? $xun_recipient_type : '';
                $insert_params["recipient_name"] = $xun_recipient_name ? $xun_recipient_name : '';
                if($recipient_employee_name){
                    $insert_params["recipient_employee_name"] = $recipient_employee_name;
                }
                if($is_business === true){
                    //  employee jid
                    $recipient_jid = $employee_arr[$username] . '@' . 'livechat.' . $server_host;
                }else{
                    $recipient_jid = $username . '@' . $server_host;
                }
                $insert_params["recipient_jid"] = $recipient_jid;

                $xun_business_sending_queue_insertData = array(
                    "data" => json_encode($insert_params),
                    "message_type" => "crypto_callback",
                    "created_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $insert_data[] = $xun_business_sending_queue_insertData;
            }


            $ids = $db->insertMulti('xun_business_sending_queue', $insert_data);
            return $ids;
        }

	function generate_app_new_address($params){

            global $config;

            $db = $this->db;
			$post = $this->post;

            $username = $params["username"];
			$business_id = $params["business_id"];
			$wallet_type = $params["wallet_type"];

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

			if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        	}

			if (!$wallet_type) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
            }

			$db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }



			$db->where("business_id", $business_id);
            $db->where("type", $wallet_type);
            $wallet_result = $db->getOne("xun_crypto_wallet");

            if(!$wallet_result){
                $fields = array("business_id", "type", "status", "created_at", "updated_at");
                $values = array($business_id, $wallet_type, "1", $currentDate, $currentDate);
                $insertData = array_combine($fields, $values);

                $wallet_id = $db->insert("xun_crypto_wallet", $insertData);
            }else{
                $wallet_id = $wallet_result["id"];
            }

            if($wallet_result["status"] == "0"){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00152') /*Wallet is set to off*/);
            }

            $db->where('id', $business_id);
            $xun_user = $db->getOne('xun_user', 'nickname');
            $cryptoParams["type"] = $wallet_type;
            $cryptoParams['businessID'] = $business_id;
            $cryptoParams['businessName']= $xun_user['nickname'];
                       
            $cryptoResult = $post->curl_crypto("getNewAddress", $cryptoParams);

            if($cryptoResult["code"] != 0){
                return array('code' => $cryptoResult["code"], 'message' => "FAILED", 'message_d' => $cryptoResult["message"]);
            }

            $currentDate = date("Y-m-d H:i:s");
            $new_address = $cryptoResult["data"]["address"];

            if(!$new_address){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00151') /*Address not generated.*/);
            }

            $fields = array("wallet_id", "crypto_address", "type", "status", "created_at", "updated_at");
            $values = array($wallet_id, $new_address, "in", "1", $currentDate, $currentDate);
            $insertData = array_combine($fields, $values);

            $db->insert("xun_crypto_address", $insertData);

            $result["new_address"] = $new_address;
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00095') /*Address Successfully Generated*/, "code" => 1, "result" => $result);

        }

		function generate_new_address($params, $source = null, $ip = null){
            
            global $config, $xunUser, $xunPaymentGateway;
            
            $db = $this->db;
            $post = $this->post;
            
            $business_id = $params["business_id"];
            $wallet_type = $params["wallet_type"];
            $apikey      = $params["apikey"];
            
            if($apikey){
                
                $db->where("apikey", $apikey);
                $db->where("status", "1");
                $apikey_result = $db->getOne("xun_crypto_apikey");
                
                if(!$apikey_result){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00148') /*Invalid Apikey.*/);
                }
                
                if(time() > strtotime($apikey_result["expired_at"])){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00149') /*Apikey has expired*/);
                }
                
                $business_id = $apikey_result["business_id"];
                
            }else{
                
                if (!$business_id) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
                }
                
            }
         
            if (!$wallet_type) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
            }
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }
            $db->where("currency_id", $wallet_type);
            $currency = $db->getOne("xun_marketplace_currencies");

            $db->where("a.business_id", $business_id);
            $db->where("a.type", $wallet_type);
            $db->join('xun_crypto_destination_address b','b.wallet_id=a.id');
            $wallet_result = $db->getOne("xun_crypto_wallet a", 'a.*,b.destination_address');
            if(!$currency){
                return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid Wallet Type");
            }
            if(!$wallet_result){
                // $db->where("currency_id", $wallet_type);
                // $db->where("is_payment_gateway", 1);
                // $pg_wallet_type = $db->getOne("xun_coins");
                // if(!$pg_wallet_type){
                //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid wallet type.");
                // }
                // $fields = array("business_id", "type", "status", "created_at", "updated_at");
                // $values = array($business_id, $wallet_type, "1", $currentDate, $currentDate);
                // $insertData = array_combine($fields, $values);
                
                // $wallet_id = $db->insert("xun_crypto_wallet", $insertData);
                return array("code" => 0, "message" => "FAILED", "message_d" => "Please set destination address before generating a new address");
            }else{
                $wallet_id = $wallet_result["id"];
            }
            
            if($wallet_result["status"] == "0"){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00152') /*Wallet is set to off*/);
            }
             
            $db->where('id', $business_id);
            $xun_user = $db->getOne('xun_user', 'nickname');
            $cryptoParams["type"] = $wallet_type;
            $cryptoParams['businessID'] = $business_id;
            $cryptoParams['businessName']= $xun_user['nickname'];

            $cryptoResult = $post->curl_crypto("getNewAddress", $cryptoParams);
            
            if($cryptoResult["code"] != 0){
                return array('code' => $cryptoResult["code"], 'message' => "FAILED", 'message_d' => $cryptoResult["message"]);
            }
            
            $currentDate = date("Y-m-d H:i:s");
            $new_address = $cryptoResult["data"]["address"];
            
            if(!$new_address){
//                $alphanumberic  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
//                $new_address    = substr(str_shuffle($alphanumberic), 0, 32);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00151') /*Address not generated.*/);
            }
            
            //	id	user_id	crypto_address	wallet_type	type	status	created_at	updated_at
            $fields = array("wallet_id", "crypto_address", "type", "status", "created_at", "updated_at");
            $values = array($wallet_id, $new_address, "in", "1", $currentDate, $currentDate);
            $insertData = array_combine($fields, $values);
            
            $db->insert("xun_crypto_address", $insertData);
            
            $result["new_address"] = $new_address;


            
                $xun_user = $xunPaymentGateway->get_nuxpay_user_details($business_id);

                $nickname = $xun_user["nickname"];
                $phone_number = $xun_user["username"];

                $user_country_info_arr = $xunUser->get_user_country_info([$phone_number]);
                $user_country_info = $user_country_info_arr[$phone_number];
                $user_country = $user_country_info["name"];

                $tag = "Generate Address";
                $message = "Username: ".$nickname."\n";
                $message .= "Phone number: ".$phone_number."\n";
                $message .= "IP: " .$ip."\n";
                $message .= "Country: " .$user_country."\n";                
                $message .= "Address: " .$new_address."\n";
                $message .= "Type: " .$wallet_type."\n";
                $message .= "Status: Success\n";
                $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                $message .= "Source: " .$source."\n";

                $xunPaymentGateway->send_nuxpay_notification($tag, $message);

            
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00095') /*Address Successfully Generated*/, "code" => 1, "result" => $result);
            
        }

	function set_app_destination_address($params){

	    global $config;

            $db = $this->db;

	    $username = $params["username"];

	    if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
	    }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

	    return $this->set_destination_address($params);

	}

        function set_destination_address($params, $source = "business"){
            global $config, $xunPaymentGateway;
            
            $db = $this->db;
            $post = $this->post;
            
            $business_id            = $params["business_id"];
            $wallet_type            = $params["wallet_type"];
            $destination_address    = $params["destination_address"];
            $status                 = strlen($params["status"]) > 0 ? $params["status"] : "1";
            
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }
            
            if (!$wallet_type) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
            }
            
            if (!$destination_address) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00153') /*Destination Address cannot be empty*/);
            }
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }
            
            $wallet_type = strtolower($wallet_type);
            $currentDate = date("Y-m-d H:i:s");
            $db->where("currency_id", $wallet_type);
            $db->where("is_payment_gateway", 1);
            $pg_wallet_type = $db->getOne("xun_coins", "id, currency_id, pg_fee_wallet_type");
            if(!$pg_wallet_type){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid wallet type.");
            }
            $db->where("business_id", $business_id);
            $db->where("type", $wallet_type);
            $wallet_result = $db->getOne("xun_crypto_wallet");

            if(!$wallet_result){
                $fields = array("business_id", "type", "status", "created_at", "updated_at");
                $values = array($business_id, $wallet_type, $status, $currentDate, $currentDate);
                $insertData = array_combine($fields, $values);

                $wallet_id = $db->insert("xun_crypto_wallet", $insertData);
            }else{
		$wallet_id = $wallet_result["id"];

		if($wallet_result["status"] != $status) {
	            $updateData["updated_at"] = $currentDate;
        	    $updateData["status"] = $status;
	
        	    $db->where("id", $wallet_id);
		    $db->update("xun_crypto_wallet", $updateData);
		}

            }

//            if($wallet_result["status"] == "0"){
//                return array('code' => 0, 'message' => "FAILED", 'message_d' => return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00151') /*Wallet is set to off*/););
//            }

	    $db->where("wallet_id", $wallet_id);
        $db->where("type", "in");

            $address_result = $db->get("xun_crypto_address");

            if(!$address_result){

                $db->where('id', $business_id);
                $xun_user = $db->getOne('xun_user', 'nickname');
                $cryptoParams["type"] = $wallet_type;
                $cryptoParams['businessID'] = $business_id;
                $cryptoParams['businessName']= $xun_user['nickname'];

                $cryptoResult = $post->curl_crypto("getNewAddress", $cryptoParams);

                if($cryptoResult["code"] != 0){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $cryptoResult["message"]);
                }

                $new_address = $cryptoResult["data"]["address"];

                if(!$new_address){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00155') /*Address not generated.*/);
                }

                //	id	user_id	crypto_address	wallet_type	type	status	created_at	updated_at
                $fields = array("wallet_id", "crypto_address", "type", "status", "created_at", "updated_at");
                $values = array($wallet_id, $new_address, "in", "1", $currentDate, $currentDate);
                $insertData = array_combine($fields, $values);

                $db->insert("xun_crypto_address", $insertData); 

            }

            //validate destination address
            $cryptoParams["type"] = $wallet_type;
            $cryptoParams["address"] = $destination_address;

            $cryptoResult = $post->curl_crypto("validateAddress", $cryptoParams);

            if($cryptoResult["code"] != 0){
                
                $translations_message = $this->get_translation_message('E00161') /*Invalid %%wallet_type%% destination address.*/;
                $return_message = str_replace("%%wallet_type%%", ucfirst($wallet_type), $translations_message);

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $return_message);
            }

            $db->where("wallet_id", $wallet_id);
            $dest_result = $db->getOne("xun_crypto_destination_address");

            if(!$dest_result){
                $fields = array("wallet_id", "type", "destination_address", "status", "created_at", "updated_at");
                $values = array($wallet_id, $wallet_type, $destination_address, "1", $currentDate, $currentDate);
                $insertData = array_combine($fields, $values);

                $db->insert("xun_crypto_destination_address", $insertData); 
            }else{
                $updateData["destination_address"] = $destination_address;
                $updateData["updated_at"] = $currentDate;
		$updateData["status"] = 1;

                $db->where("wallet_id", $wallet_id);
                $db->update("xun_crypto_destination_address", $updateData);
            }

            if($status == 1 && $wallet_type != $pg_wallet_type["pg_fee_wallet_type"]){
                $save_delegate_address_result = $this->save_delegate_address($business_id);
                if(isset($save_delegate_address_result["code"]) && $save_delegate_address_result["code"] == 0){
                    return $save_delegate_address_result;
                }
            }

            // if($source == 'nuxpay'){
                $db->where('id', $business_id);
                $db->where('register_site', "nuxpay");
                $xun_user = $db->getOne('xun_user');
                $phone_number = $xun_user["username"];
                $nickname = $xun_user["nickname"];
                $email = $xun_user["email"];

                $message .= "Username: ".$nickname."\n";
                $message .= "Email: ".$email."\n";
                $message .= "Phone number: " .$phone_number."\n";
                $message .= "Cryptocurrency type: ".$wallet_type."\n";
                $message .= "Destination Address: ".$destination_address."\n";
                $message .= "Time: ".date("Y-m-d H:i:s")."\n";
                $message .= "Source: ".$source."\n";
                $tag = "Set Destination Address";
                
                $xunPaymentGateway->send_nuxpay_notification($tag, $message);
            // }
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00096') /*Addresses successfully set*/, "code" => 1, "result" => $result);
            
        }

        function set_destination_address_status($params, $user_id) {

            $db = $this->db;

            $unit            = $params["unit"];
            $destination_address    = $params["destination_address"];
            $status                 = $params["status"];


            if (!$user_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Business ID cannot be empty.');
            }

            if (!$unit) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Unit cannot be empty.');
            }

            if (!$destination_address) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Destination Address cannot be empty.');
            }

            //get wallet type
            $db->where("symbol", $unit);
            $db->where("status", 1);
            $currencyDetail = $db->getOne("xun_marketplace_currencies");

            if($currencyDetail) {
                $wallet_type = $currencyDetail['currency_id'];
            } else {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Something went wrong, please try again later.');
            }
            

            $db->where("business_id", $user_id);
            $db->where("type", $wallet_type);
            $db->where("status", 1);
            $cryptoWalletDetail = $db->getOne("xun_crypto_wallet");

            if($cryptoWalletDetail) {
                $wallet_id = $cryptoWalletDetail['id'];

                if($status) {
                    //Deactivate other destination address
                    $db->where("wallet_id", $wallet_id);
                    $db->where("type", $wallet_type);
                    $db->where("status", 1);
                    $db->where("destination_address", $destination_address, "<>");
                    $db->update("xun_crypto_destination_address", array("status"=>0, "updated_at"=>date("Y-m-d H:i:s")));

                    //activate destination addres
                    $db->where("wallet_id", $wallet_id);
                    $db->where("type", $wallet_type);
                    $db->where("status", 0);
                    $db->where("destination_address", $destination_address);
                    $db->update("xun_crypto_destination_address", array("status"=>1, "updated_at"=>date("Y-m-d H:i:s")));

                    return array('code' => 1, 'message' => "Success", 'message_d' => 'Successfully update the destination address status.');

                } else {

                    //deactivate destination address
                    $db->where("wallet_id", $wallet_id);
                    $db->where("type", $wallet_type);
                    $db->where("status", 1);
                    $db->where("destination_address", $destination_address);
                    $db->update("xun_crypto_destination_address", array("status"=>0, "updated_at"=>date("Y-m-d H:i:s")));

                    return array('code' => 1, 'message' => "Success", 'message_d' => 'Successfully update the destination address status.');
                }

            } else {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Something went wrong, please try again later.');
            }

        }

        function set_destination_address_v1($params){
            global $config, $xunPaymentGateway;
            
            $db = $this->db;
            $post = $this->post;
            
            $business_id            = $params["business_id"];
            $crypto_address_details = $params["crypto_address_details"];
            // $wallet_type            = $params["wallet_type"];
            // $destination_address    = $params["destination_address"];
            // $status                 = strlen($params["status"]) > 0 ? $params["status"] : "1";

            $date = date("Y-m-d H:i:s");
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002'), 'data'=> 'a'/*Business ID cannot be empty*/);
            }
       
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') , 'data'=> 'b'/*Invalid business id.*/);
            }
            $wallet_type_arr  =[];
            foreach($crypto_address_details as $key => $value){
                $wallet_type = $value['wallet_type'];
                $destination_address = $value['destination_address'];
                $status = $value['status'];

                if (!$wallet_type) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150'),  'data'=> 'c' /*Wallet Type cannot be empty*/);
                }
                
                if (!$destination_address) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00153'), 'data'=> 'd' /*Destination Address cannot be empty*/);
                }

                if(!$status){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Status cannot be empty" /*Destination Address cannot be empty*/);
                }

                $wallet_type = strtolower($wallet_type);
                if(!in_array($wallet_type, $wallet_type_arr)){
                    array_push($wallet_type_arr, $wallet_type);
                }               

            }

            $currentDate = date("Y-m-d H:i:s");
            $db->where("currency_id", $wallet_type_arr, "IN");
            $db->where("is_payment_gateway", 1);
            $pg_wallet_type = $db->map("currency_id")->ArrayBuilder()->get("xun_coins", null, "id, currency_id, pg_fee_wallet_type");

            $available_wallet_type = array_keys($pg_wallet_type);
            // if(!$pg_wallet_type){
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid wallet type.");
            // }

            //get wallet type that does not exist or not activated
            $check_wallet_type = array_diff($wallet_type_arr, $available_wallet_type);
            
            if($check_wallet_type){
                $invalid_wallet_type = implode(",", $check_wallet_type);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid wallet type" , "developer_msg" => "$invalid_wallet_type are invalid or not activated");
            }

            $db->where("business_id", $business_id);
            $db->where("type", $wallet_type_arr, 'IN');
            $wallet_result = $db->get("xun_crypto_wallet");

            $active_wallet_type = array_column($wallet_result, "type");

            //check wallet type that does not exist in crypto wallet table
            $not_exist_wallet_type = array_diff($wallet_type_arr, $active_wallet_type);

            $wallet_id_list = [];
            foreach($wallet_result as $wallet_key => $wallet_value){
                $wallet_id = $wallet_value['id'];
                $wallet_type = $wallet_value['type'];

                if(!in_array($wallet_id, $wallet_id_arr)){
                    array_push($wallet_id_arr, $wallet_id);
                }

                $wallet_arr = array(
                    "wallet_id" => $wallet_id,

                );

                $wallet_id_list[$wallet_type] = $wallet_arr;

            }

            //create a wallet record that does not exist in crypto wallet table
            foreach($not_exist_wallet_type as $crypto_wallet_value){

                $insert_wallet= array(
                    "business_id" => $business_id,
                    "type" => $crypto_wallet_value,
                    "status" => 1,
                    "created_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $wallet_id = $db->insert('xun_crypto_wallet', $insert_wallet);

                $wallet_id_details = array(
                    "wallet_id" => $wallet_id,
                );
                $wallet_id_list[$crypto_wallet_value] = $wallet_id_details;
            }
            
            $wallet_id_arr = array_column($wallet_id_list, "wallet_id");
            $db->where('wallet_id', $wallet_id_arr, 'IN');
            $destination_address_result = $db->map('wallet_id')->ArrayBuilder()->get('xun_crypto_destination_address');

            $need_delegate_address = 0;
            //update or insert the destination address into the crypto_destination_address table
            foreach($crypto_address_details as $crypto_details_key => $crypto_details_value){
                $wallet_type = strtolower($crypto_details_value['wallet_type']);
                $destination_address = $crypto_details_value['destination_address'];
                $status = $crypto_details_value['status'];
                $wallet_id = $wallet_id_list[$wallet_type]['wallet_id'];

                $update_wallet = array(
                    "status" => $status,
                    "updated_at" => date("Y-m-d H:i:s"),
                );

                $db->where('id', $wallet_id);
                $updated_wallet = $db->update('xun_crypto_wallet', $update_wallet);

                if($status == 1 && $wallet_type != $pg_wallet_type[$wallet_type]["pg_fee_wallet_type"]){
                    $need_delegate_address = 1;
                }

                if($destination_address_result[$wallet_id]){
                    $update_address = array(
                        "destination_address" => $destination_address,
                        "status" => $status,
                        "updated_at" => date("Y-m-d H:i:s")
                    );

                    $db->where('wallet_id', $wallet_id);
                    $updated = $db->update('xun_crypto_destination_address', $update_address);

                    if(!$updated){
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => "Update address failed" , "developer_msg" => "Wallet ID $wallet_id failed to update address");
                    }

                }
                else{
                    $insert_address = array(
                        "wallet_id" => $wallet_id,
                        "type" => $wallet_type,
                        "destination_address" => $destination_address,
                        "status" => $status, 
                        "created_at" => date("Y-m-d H:i:s"),
                        "updated_at" => date("Y-m-d H:i:s"),
                    );

                    $destination_address_id = $db->insert('xun_crypto_destination_address', $insert_address);

                    if(!$destination_address_id){
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insert data failed" , "developer_msg" => "$wallet_type failed to insert");
                    }
                }

            }

	    //
	    $db->where("business_id", $business_id);
            $db->where("status", 1);
            $db->where("type", $wallet_type_arr, 'NOT IN');
            $getOffDetail = $db->get("xun_crypto_wallet");
	    
            foreach($getOffDetail as $detail) {
		$db->where("id", $detail['id']);
		$db->update("xun_crypto_wallet", array("status"=>0, "updated_at"=>date("Y-m-d H:i:s") ));

		$db->where("wallet_id", $detail['id']);
		$db->update("xun_crypto_destination_address", array("status"=>0, "destination_address"=>"", "updated_at"=>date("Y-m-d H:i:s") ));
            }

            if($need_delegate_address){
                // get new delegate address if business doesnt have
                $result = $this->save_delegate_address($business_id);
                if(isset($result["code"]) && $result["code"] == 0){
                    return $result;
                }
            }

            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00096') /*Addresses successfully set*/, "code" => 1, 'check_wallet_type'=>$check_wallet_type);
        }

        private function save_delegate_address($business_id){
            $db = $this->db;
            $post = $this->post;

            $date = date("Y-m-d H:i:s");

            // get new delegate address if business doesnt have
            $xun_payment_gateway_service = new XunPaymentGatewayService($db);
            $search_business_delegate_address = new stdClass();
            $search_business_delegate_address->userId = $business_id;
            $delegate_address_data = $xun_payment_gateway_service->getPaymentGatewayDelegateAddress($search_business_delegate_address);
            
            if(!$delegate_address_data){
                $delegate_address_wallet_type = "ethereum";
                $crypto_params["type"] = $delegate_address_wallet_type;
                
                $db->where('id', $business_id);
                $xun_user = $db->getOne('xun_user', 'nickname');
                $crypto_params['businessID'] = $business_id;
                $crypto_params['businessName']= $xun_user['nickname'];

                $crypto_results = $post->curl_crypto("getNewAddress", $crypto_params);

                if($crypto_results["code"] != 0){
                    return array('code' => 0, 'message' => "FAILED", '
                    message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 
                    'developer_msg' => $crypto_results["message"]);
                }

                $pg_address = $crypto_results["data"]["address"];

                if(!$pg_address){
                    return array('code' => 0, 'message' => "FAILED", 
                    'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 
                    "developer_msg" => "Address not generated.");
                }

                try{
                    $insert_delegate_address_data = array(
                        "user_id" => $business_id,
                        "address" => $pg_address,
                        "created_at" => $date,
                        "updated_at" => $date
                    );

                    $xun_payment_gateway_service->insertPaymentGatewayDelegateAddress($insert_delegate_address_data);
                }catch (Exception $e){
                    return array('code' => 0, 'message' => "FAILED", 
                        'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 
                        "developer_msg" => $e->getMessage());
                }
            }
        }

        function get_payment_gateway_delegate_address($params)
        {
            $db = $this->db;
            
            $business_id = trim($params["business_id"]);

            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002')/*Business ID cannot be empty*/);
            }
            
            $xun_payment_gateway_service = new XunPaymentGatewayService($db);
            $search_business_delegate_address = new stdClass();
            $search_business_delegate_address->userId = $business_id;
            $delegate_address_data = $xun_payment_gateway_service->getPaymentGatewayDelegateAddress($search_business_delegate_address);

            $delegate_address = $delegate_address_data["address"] ? $delegate_address_data["address"] : "";
            $return_data = array(
                "address" => $delegate_address
            );
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Delegate address.", "data" => $return_data);
        }

        function get_xun_user_by_crypto_address($crypto_address){
            global $setting;
            $db = $this->db;

            $db->where("address", $crypto_address);

            $db->orWhere("address",preg_replace('/^0x/', '', $crypto_address));
            $cryptoResult = $db->getOne("xun_crypto_user_address");

            if(!$cryptoResult){
                $wallet_data = $this->check_company_wallet_address($crypto_address);

                if($wallet_data["type"]){
                    return array("code" => 0, "type" => $wallet_data["type"], "name" => $wallet_data["name"]);
                }else{
                    return array("code" => 0, "error_message" => $this->get_translation_message('E00156') /*Invalid address*/);
                }
            }


            $user_id = $cryptoResult["user_id"];

            $db->where("id", $user_id);
            $xun_user = $db->getOne("xun_user", "id, username, nickname, wallet_callback_url, type");

            if($xun_user["type"] == "business"){
                $db->where("user_id", $user_id);
                $business_name = $db->getValue("xun_business", "name");
                $xun_user["nickname"] = $business_name;
                $xun_user["username"] = $user_id;
            }

            // update used column 
            if($cryptoResult["used"] == 0){
                $update_data = [];
                $update_data["used"] = 1;
                $update_data["updated_at"] = date("Y-m-d H:i:s");
    
                $db->where("id", $cryptoResult["id"]);
                $db->update("xun_crypto_user_address", $update_data);
            }

            if(!$cryptoResult){
                return array("code" => 0, "error_message" => $this->get_translation_message('E00157') /*Invalid user*/);
            }

            return array("code" => 1, "xun_user" => $xun_user, "user_address_data" => $cryptoResult);
        }

    function get_payment_gateway_coin_list($params){

        global $config;

            $db = $this->db;

            $username = $params["username"];

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

            $db->where("is_payment_gateway", 1);
            $result_coin_list = $db->get("xun_coins");

         
            foreach($result_coin_list as $coin_list){
                $returnCoinList[] = $coin_list["currency_id"];
            }

            if (!$returnCoinList) {
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
            }
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => "Coin Listing", "code" => 1, "result" => $returnCoinList);

    }


        function get_app_destination_address($params){

            global $config;

            $db = $this->db;

            $username = $params["username"];
                        $business_id = $params["business_id"];
                        $wallet_type = $params["wallet_type"];

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }

            $currentDate = date("Y-m-d H:i:s");

            if($wallet_type != '') {
                $db->where("currency_id", $wallet_type);
            }
            $db->where("is_payment_gateway", 1);
            $result_coin_list = $db->get("xun_coins");

            if(!$result_coin_list){
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
            }
            foreach($result_coin_list as $coin_list){
                $currency_id = $coin_list["currency_id"];

                $db->where("business_id", $business_id);
                $db->where("type", $currency_id);
                $wallet_result = $db->getOne("xun_crypto_wallet");

                if(!$wallet_result){
                    $fields = array("business_id", "type", "status", "created_at", "updated_at");
                    $values = array($business_id, $currency_id, 0, $currentDate, $currentDate);
                    $insertData = array_combine($fields, $values);

                    $wallet_id = $db->insert("xun_crypto_wallet", $insertData);
                    $wallet_status = 0;
                }else{
                    $wallet_id = $wallet_result["id"];
                    $wallet_status = $wallet_result["status"];
                }


                $db->where("wallet_id", $wallet_id);
                $db->where("type", $currency_id);
                $db->where("status", 1);
                $destination_address_result = $db->getOne("xun_crypto_destination_address");

                if(!$destination_address_result){
                        $destination_address = "";
                }else{
                        $destination_address = $destination_address_result["destination_address"];
                }

                $result["status"] = $wallet_status;
                $result["destination_address"] = $destination_address;
                $coin_result[$currency_id] = $result;

            }

            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00097') /*Destination Address Sent*/, "code" => 1, "result" => $coin_result);

        }

        function get_destination_address($params){
            global $setting, $xunErlang, $xunServiceCharge, $xunCurrency;
            $db = $this->db;
            $general = $this->general;
            $date = date("Y-m-d H:i:s");

            $wallet_type = strtolower($params["wallet_type"]);
            $address     = $params["address"];
            $amount      = $params["amount"];
            $bypass_threshold = $params['bypass_threshold'];
            $miner_fee_usd   = $params['miner_fee']; //PG fund in pass miner fee amount
            
            $db->where("crypto_address", $address);
            $address_result = $db->getOne("xun_crypto_address");

            $amount_onhold = 0;
            
            if(!$address_result){
                $db->where('payment_address', $address);
                $db->join('xun_payment_gateway_payment_transaction b', 'a.pg_transaction_id = b.id', 'LEFT');
		        $db->join('xun_user u', 'u.id=b.business_id', 'LEFT');
                $pg_invoice_detail= $db->getOne('xun_payment_gateway_invoice_detail a', 'b.business_id, u.nickname' );
                
                if($pg_invoice_detail){
                    $business_id = $pg_invoice_detail['business_id'];
                    $db->where('a.business_id', $business_id);
                    $db->where('a.type', $wallet_type);
                    $db->where('b.status', 1);
                    $db->where('a.status', 1);
                    $db->join('xun_crypto_destination_address b', 'a.id = b.wallet_id', 'LEFT');
                    $dest_result = $db->getOne('xun_crypto_wallet a', 'b.destination_address' );
                    
                    $db->where('user_id', $business_id);
                    $db->where('name', 'minerFeeThreshold');
                    $miner_fee_threshold = $db->getValue('xun_user_setting', 'value');

                    $db->where('id', $business_id);
                    $systemUserDetail = $db->getOne("xun_user");
                    $user_id = $systemUserDetail['id'];
                    $user_nickname = $systemUserDetail['nickname'];
                    $result['user_id'] = $user_id;
                    $result['business_name'] = $user_nickname;

                    // check if `allowSwitchCurrency` is set in xun_user_setting
                    // change the destination address to nuxpay wallet if allowSwitchCurrency=1
                    // so that payment will be send directly into nuxpay wallet and perform autoswap later
                    $db->where('user_id', $business_id);
                    $db->where('name', 'allowSwitchCurrency');
                    $isAllowSwitchCurrency = $db->getValue('xun_user_setting', 'value');

                    if ($isAllowSwitchCurrency) {
                        $db->where('user_id', $business_id);
                        $db->where('address_type', 'nuxpay_wallet');
                        $db->where('active', 1);                        
                        $internalAddress = $db->getValue('xun_crypto_user_address', 'address');
                        $crypto_result = $this->crypto_get_external_address($internalAddress, strtolower($wallet_type));
                        if($crypto_result["status"] == "ok"){
                            $crypto_data = $crypto_result["data"];
                            $dest_address = $crypto_data["address"];
                        }else{
                            $status_msg = $crypto_result["statusMsg"];
                            return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
                        }          
                    } else {
                        $dest_address = $dest_result["destination_address"];
                    }
            
                    $result['destination_address'] = $dest_address ? $dest_address : '';
                    $result['isDelegate'] = 0;
                    $result['transactionOnhold'] = $amount_onhold;
                    $result['amount'] = $amount;
                    if($amount_onhold){
                        $action = 'hold';
                    }else if($miner_fee_usd){
                        if($miner_fee_threshold > 0){
                            if($miner_fee_usd > $miner_fee_threshold){
                                $action = 'threshold';
                            }
                            else{
                                if(!$dest_result){
                                    $action = 'hold';
                                }
                                else{
                                    $action = 'release';
                                }
                            }
                        }
                        else{
                            if(!$dest_result){
                                $action = 'hold';
                            }
                            else{
                                $action = 'release';
                            }
                        }
                           
                    }
                    else{
                        if(!$dest_result){
                            $action = 'hold';
                        }
                        else{
                            $action = 'release';
                        }
                       
                    }
                    $result['action'] = $action;
                    
                    return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00097') /*Destination Address Sent*/, "code" => 1, "result" => $result);
                }
                $xun_payment_gateway_service = new XunPaymentGatewayService($db);
                $search_business_delegate_address = new stdClass();
                $search_business_delegate_address->address = $address;
                $delegate_address_data = $xun_payment_gateway_service->getPaymentGatewayDelegateAddress($search_business_delegate_address);
                if(!$delegate_address_data){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00144') /*Address Not Found.*/);
                }

                $result["isDelegate"] = 1;
                $result['transactionOnhold'] = $amount_onhold;
                $result['amount'] = $amount;
                return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00097') /*Destination Address Sent*/, "code" => 1, "result" => $result);
            }
            
            $wallet_id = $address_result["wallet_id"];
            
            $db->where("id", $wallet_id);
            $wallet_result = $db->getOne("xun_crypto_wallet");
            
            if($wallet_result["status"] == "0"){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00152') /*Wallet is set to off*/);
            }

            $business_id = $wallet_result["business_id"];

            $db->where('id', $business_id);
            $user_result = $db->getOne('xun_user');
            $service_charge_rate = $user_result["service_charge_rate"];

            if(!$bypass_threshold){
                $db->where('user_id', $business_id);
                $db->where('name', $wallet_type.'Threshold');
                $user_setting = $db->getOne('xun_user_setting');
                
                if($user_setting){
                    $threshold_amount = $user_setting['value'];
                    $usd_amount = $xunCurrency->calculate_cryptocurrency_rate_by_wallet_type($wallet_type, $amount);
                    
                    if($threshold_amount > $usd_amount){
                        $amount_onhold = 1;
                        $amount = 0;
                    }

                }

                $db->where('user_id', $business_id);
                $db->where('name', 'isDailyFundOut');
                $isDailyFundOut = $db->getValue('xun_user_setting', 'value');

                if($isDailyFundOut){
                    $amount_onhold = 1;
                    $amount = 0;
                }
            }

            $db->where('user_id', $business_id);
            $db->where('name', 'minerFeeThreshold');
            $miner_fee_threshold = $db->getValue('xun_user_setting', 'value');

            if($address_result["type"] == "out"){
                // $service_charge_transaction_type = "send";
                $address_id = $address_result["id"];

                $xun_payment_gateway_service = new XunPaymentGatewayService($db);
                $fund_out_destination_result = $xun_payment_gateway_service->getFundOutDestinationAddressByWalletIDandAddressID($wallet_id, $address_id);
                $destination_address_type = $fund_out_destination_result["address_type"];

                if($destination_address_type == "internal"){
                    $internal_address = $fund_out_destination_result["destination_address"];
    
                    //  get external address
                    $crypto_result = $this->crypto_get_external_address($internal_address, $wallet_type);
                    if($crypto_result["status"] == "ok"){
                        $crypto_data = $crypto_result["data"];
                        $dest_address = $crypto_data["address"];
                    }else{
                        $status_msg = $crypto_result["statusMsg"];
                        return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
                    }
                }else if($destination_address_type == "external"){
                    $dest_address = $fund_out_destination_result["destination_address"];
                }
            }else{
                $db->where("wallet_id", $wallet_id);
                $db->where('status', 1);
                $dest_result = $db->getOne("xun_crypto_destination_address");

                // NOTE: every new pg_address have to check if it is belongs to buy/sell request,
                //       before checking if the user had set for `allowSwitchCurrency`
                //       to avoid returning wrong destination address
                // check if pg_address exist in xun_crypto_payment_request
                // if exist, means the received amount should be forward to the actual destination address
                $db->where('pg_address', $address);
                $buySellCryptoResult = $db->getOne('xun_crypto_payment_request');

                if ($buySellCryptoResult) {

                    $dest_address = $buySellCryptoResult['destination_address'];
                
                } else {
                    // check if `allowSwitchCurrency` is set in xun_user_setting
                    // change the destination address to nuxpay wallet if allowSwitchCurrency=1
                    // so that payment will be send directly into nuxpay wallet and perform autoswap later
                    $db->where('user_id', $business_id);
                    $db->where('name', 'allowSwitchCurrency');
                    $isAllowSwitchCurrency = $db->getValue('xun_user_setting', 'value');

                    if ($isAllowSwitchCurrency) {
                        $db->where('user_id', $business_id);
                        $db->where('address_type', 'nuxpay_wallet');
                        $db->where('active', 1);                        
                        $internalAddress = $db->getValue('xun_crypto_user_address', 'address');
                        $crypto_result = $this->crypto_get_external_address($internalAddress, strtolower($wallet_type));
                        
                        if($crypto_result["status"] == "ok"){
                            $crypto_data = $crypto_result["data"];
                            $dest_address = $crypto_data["address"];
                        }else{
                            $status_msg = $crypto_result["statusMsg"];
                            return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
                        }          
                    } else {

                        $dest_address = $dest_result["destination_address"];

                    }
                }
            }


            // DEFAULT CHECKING ON xun_crypto_address
            if ($address_result) {
                if ($address_result['destination_address'] != '') {
                    $dest_address = $address_result['destination_address'];
                }
            }

            $result["destination_address"] = $dest_address;
            $result["isDelegate"] = 0;
            $result["user_id"] = $business_id;
            $result["business_name"] = $user_result["nickname"];
            $result["transactionOnhold"] = $amount_onhold ? $amount_onhold : 0;
            $result['amount'] = $amount;
            if($amount_onhold){
                $action = 'hold';
            }else if($miner_fee_usd){                
                if($miner_fee_threshold > 0){
                    if($miner_fee_usd > $miner_fee_threshold){
                        $action = 'threshold';
                    }
                    else{                        
                        if(strlen($dest_address) == 0){
                            $action = 'hold';
                        }
                        else{
                            $action = 'release';
                        }
                    }
                }
                else{                    
                    //IF user did not set destination adddress
                    if(strlen($dest_address) == 0){
                        $action = 'hold';
                    }
                    else{
                        $action = 'release';
                    }
                }
                
            }
            else{
                //IF user did not set destination adddress
                if(strlen($dest_address) == 0){
                    $action = 'hold';
                }
                else{
                    $action = 'release';
                }
            }
            $result['action'] = $action;
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00097') /*Destination Address Sent*/, "code" => 1, "result" => $result);
        }

        function get_service_charge($params){
            global $setting, $xunErlang, $xunServiceCharge, $xunPaymentGateway, $xunPayment;
            global $xunCurrency;
            $db = $this->db;
            $general = $this->general;
            $date = date("Y-m-d H:i:s");

            $wallet_type = trim($params["wallet_type"]);
            $address = trim($params["address"]);
            $amount = trim($params["amount"]);
            $check_service_charge = trim($params["check_service_charge"]);
            $received_transaction_hash = trim($params['received_transaction_id']);
            //$transaction_hash = trim($params['tx_id']);
            // $transaction_type = trim($params["transactionType"]);

            $transaction_type = "external";

            if ($address == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Address cannot be empty");
            }

            if ($wallet_type == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
            }

            if ($amount == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Amount cannot be empty");
            }

            if ($transaction_type == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction type cannot be empty");
            }
            // if(!$check_service_charge){
            //     if($received_transaction_hash == ''){
            //         return array('code' => 0, 'message' => "FAILED", 'message_d' => "Received Transaction ID cannot be empty");
            //     }
            // }

            $wallet_type = strtolower($wallet_type);
            $db->where("crypto_address", $address);
            $address_result = $db->getOne("xun_crypto_address");
            
            // check if it's payment gateway address or internal address
            if($address_result){
                $wallet_id = $address_result["wallet_id"];
                $db->where("id", $wallet_id);
                $wallet_result = $db->getOne("xun_crypto_wallet");
                
                if($wallet_result["status"] == "0"){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00152') /*Wallet is set to off*/);
                }

                $user_id = $wallet_result["business_id"];
                $db->where('id', $user_id);
                $user_result = $db->getOne('xun_user');
                // $service_charge_rate = $user_result["service_charge_rate"];

                if($address_result["type"] == "out"){
                    $service_charge_transaction_type = "send";
                }else{
                    $service_charge_transaction_type = "receive";
                }
                $service_charge_type = "payment_gateway";
            }else{
                $invoice_details = $xunPaymentGateway->getInvoiceDetailsByAddress($address);

                if($invoice_details){
                    $service_charge_type = "payment_gateway";

                    $user_id = $invoice_details['business_id'];
                    $db->where('id', $user_id);
                    $user_result = $db->getOne('xun_user');
                    $service_charge_transaction_type = "receive";

                }
                else{
                    $xun_user_service = new XunUserService($db);
                    $user_address_data = $xun_user_service->getAddressDetailsByAddress($address);
                    if(!$user_address_data){
                        return array("status" => "error", "message" => "FAILED", "message_d" => "Invalid address", "code" => 0);
                    }

                    $user_id = $user_address_data["user_id"];
                    $db->where('id', $user_id);
                    $user_result = $db->getOne('xun_user');

                    if($user_result["type"] != "business"){
                        $result = [];
                        $result["service_charge"]["amount"] = "0.00";
                        return array("status" => "ok", "message" => "SUCCESS", "message_d" => "Service charge details.", "code" => 1, "result" => $result);
                    }
                    $service_charge_type = "external_transfer";
                    $service_charge_transaction_type = "receive";
                }
            }
            $service_charge_rate = $user_result["service_charge_rate"];

            
            if($amount != '' && ($service_charge_rate > 0 || $service_charge_rate == null)){
                //  convert to ethereum for custom erc20 tokens
                $db->where("currency_id", $wallet_type);
                $xun_coin = $db->getOne("xun_coins", "currency_id, pg_fee_wallet_type");
                $service_charge_wallet_type = $xun_coin["pg_fee_wallet_type"];

                $db->where('user_id', $user_id);
                $business_account = $db->getOne('xun_business_account', 'account_type, upgraded_date');
                $account_type = $business_account['account_type'];

                $service_charge_calculation_amount = $amount;
  
                if($service_charge_wallet_type != $wallet_type){
                    $service_charge_calculation_amount = $xunCurrency->get_conversion_amount($service_charge_wallet_type, $wallet_type, $amount);
                }

                $xun_commission = new XunCommission($db, $setting, $general);
                $commission_details = $xun_commission->get_commission_details($service_charge_calculation_amount, $service_charge_wallet_type, $service_charge_rate);
    
                $result["service_charge"] = $commission_details;
                $service_charge_amount = $account_type == "basic" ? "0.00000000" : $commission_details["amount"] ;
                $recipient_address = $commission_details["address"];
            }
            else{
                $service_charge_amount = "0.00";
            }

            if($service_charge_amount > 0){
                $xunWallet = new XunWallet($db);
                $payment_tx_data =  $xunPayment->getPaymentTxDetailsByAddress($address);

                $payment_tx_id = $payment_tx_data['payment_tx_id'];
                $payment_method_id = $payment_tx_data['payment_method_id'];

                $db->where('payment_tx_id', $payment_tx_id);
                $db->where('payment_method_id', $payment_method_id);
                $payment_details_data = $db->getOne('xun_payment_details');

                $payment_details_id = $payment_details_data['id'];

                $transactionObj = new stdClass();
                $transactionObj->status = "pending";
                $transactionObj->transactionHash = "";
                $transactionObj->transactionToken = "";
                $transactionObj->senderAddress = "";
                $transactionObj->recipientAddress = $recipient_address;
                $transactionObj->userID = $user_id;
                $transactionObj->senderUserID = $user_id;
                $transactionObj->recipientUserID = "trading_fee";
                $transactionObj->walletType = $service_charge_wallet_type;
                $transactionObj->amount = $service_charge_amount;
                $transactionObj->addressType = "service_charge";
                $transactionObj->transactionType = "receive";
                $transactionObj->escrow = 0;
                $transactionObj->referenceID = '';
                $transactionObj->escrowContractAddress = '';
                $transactionObj->createdAt = $date;
                $transactionObj->updatedAt = $date;
                $transactionObj->expiresAt = '';

                if(!$check_service_charge){
                    if($received_transaction_hash){
                        $db->where('received_transaction_hash', $received_transaction_hash);
                        $service_charge_tx_data = $db->getOne('xun_service_charge_audit');
                    }
                    
                    if(!$service_charge_tx_data || !$received_transaction_hash){
                        $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

                        $transactionType = "service_charge";
    
                        $txHistoryObj->paymentDetailsID = $payment_details_id;
                        $txHistoryObj->status = "pending";
                        $txHistoryObj->transactionID = "";
                        $txHistoryObj->transactionToken = "";
                        $txHistoryObj->senderAddress = "";
                        $txHistoryObj->recipientAddress = $recipient_address;
                        $txHistoryObj->senderUserID = $user_id;
                        $txHistoryObj->recipientUserID = "trading_fee";
                        $txHistoryObj->walletType = $service_charge_wallet_type;
                        $txHistoryObj->amount = $service_charge_amount;
                        $txHistoryObj->transactionType = "service_charge";
                        $txHistoryObj->referenceID = '';
                        $txHistoryObj->createdAt = $date;
                        $txHistoryObj->updatedAt = $date;
                        // $transactionObj->fee = $final_miner_fee;
                        // $transactionObj->feeWalletType = $miner_fee_wallet_type;
                        // $txHistoryObj->exchangeRate = $exchange_rate;
                        // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                        $txHistoryObj->type = 'in';
                        $txHistoryObj->gatewayType = "BC";
            
                        $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                        $fund_out_id = $transaction_history_result['transaction_history_id'];
                        $fund_out_table = $transaction_history_result['table_name'];
    
                        $updateWalletTx = array(
                            "transaction_history_id" => $fund_out_id,
                            "transaction_history_table" => $fund_out_table
                        );
                        $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);
    
                        $result["service_charge"]["transaction_id"] = $transaction_id;
                        
                        $service_charge_user_id = $user_id;
                        
                        $new_params = array(
                            "user_id" => $service_charge_user_id,
                            "wallet_transaction_id" => $transaction_id,
                            "fund_out_table" => $fund_out_table,
                            "fund_out_id" => $fund_out_id,
                            "received_transaction_hash" => $received_transaction_hash,
                            // "transaction_hash" => $transaction_hash,
                            "amount" => $service_charge_amount,
                            "wallet_type" => $service_charge_wallet_type,
                            "service_charge_type" => $service_charge_type,
                            "transaction_type" => $service_charge_transaction_type,
                            "ori_tx_wallet_type" => $wallet_type,
                            "ori_tx_amount" => $amount
                        );
                            
                        $service_charge_id = $xunServiceCharge->insert_service_charge($new_params);
                        $result['service_charge']['service_charge_id'] = $service_charge_id;
                    }
                    else{
                        $wallet_transaction_id = $service_charge_tx_data['wallet_transaction_id'];
                        $service_charge_id = $service_charge_tx_data['id'];
                        
                        $result["service_charge"]["transaction_id"] = $wallet_transaction_id;
                        $result["service_charge"]["service_charge_id"] = $service_charge_id;

                    }
                    
                    
                }   
                $service_charge_address = $result["service_charge"]["address"];
    
                $external_address_data = $this->get_external_address($service_charge_address, $service_charge_wallet_type);
    
                if(isset($external_address_data["code"]) && $external_address_data["code"] == 0){
                    return $external_address_data;
                }
    
                $result["service_charge"]["address"] = $external_address_data;

            }else{
                $result["service_charge"]["amount"] = $service_charge_amount;
            }
            
            //System User
            $db->where("id", "1");
            $systemUserDetail = $db->getOne("xun_user");
            $user_id = $systemUserDetail['id'];
            $user_nickname = $systemUserDetail['nickname'];
            $result['user_id'] = $user_id;
            $result['business_name'] = $user_nickname;

            $xun_payment_gateway_service = new XunPaymentGatewayService($db);
            $search_business_delegate_address = new stdClass();
            $search_business_delegate_address->userId = $user_id;
            $delegate_address_data = $xun_payment_gateway_service->getPaymentGatewayDelegateAddress($search_business_delegate_address);
            $delegate_address = $delegate_address_data["address"];

            $result["delegate_address"] = $delegate_address ? $delegate_address : "";

            return array("status" => "ok", "message" => "SUCCESS", "message_d" => "Service charge details.", "code" => 1, "result" => $result);
            
        }

        function get_external_address($address, $wallet_type){
            $db = $this->db;

            $db->where("internal_address", $address);
            $db->where("wallet_type", $wallet_type);

            $external_address_data = $db->getOne("xun_crypto_external_address");

            if($external_address_data){
                return $external_address_data["external_address"];
            }
            $crypto_result = $this->crypto_get_external_address($address, $wallet_type);
            if($crypto_result["status"] == "ok"){
                $crypto_data = $crypto_result["data"];
                $external_address = $crypto_data["address"];
            }else{
                $status_msg = $crypto_result["statusMsg"];
                return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
            }
            
            $date = date("Y-m-d H:i:s");
            
            $insert_data = array(
                "internal_address" => $address,
                "external_address" => $external_address,
                "wallet_type" => $wallet_type,
                "created_at" => $date,
                "updated_at" => $date
            );

            $db->insert("xun_crypto_external_address", $insert_data);

            return $external_address;
        }

	    function get_app_address_list($params){

            $db = $this->db;
			$general = $this->general;

            $username = $params["username"];
            $business_id = $params["business_id"];
            $wallet_type = $params["wallet_type"];
            $last_id  = $params['last_id'] ? $params['last_id'] : 0;
            $fetch_limit   = $params["fetch_limit"] ? $params["fetch_limit"] : 20;
		    $order_by = $params["order_by"] ? $params["order_by"] : "desc";

            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }

            if (!$wallet_type) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
            }

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }


            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }

            $db->where("business_id", $business_id);
            $db->where("type", $wallet_type);
            $wallet_result = $db->getOne("xun_crypto_wallet");

            if($wallet_result["status"] == "0"){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00152') /*Wallet is set to off*/);
            }

			$wallet_id = $wallet_result["id"];

		    $db->where("wallet_id", $wallet_id);
		    $db->where("type", "in");
			if(!($order_by == "desc" && $last_id == "0")) {
		    	$db->where("id", $last_id, $order_by == "asc" ? ">": "<");
			}
	    	$db->orderBy("id", $order_by);
            $address_result = $db->get("xun_crypto_address", $fetch_limit);


            foreach($address_result as $addresses){
                $addresses["created_at"] = $general->formatDateTimeToIsoFormat($addresses["created_at"]);
                $addresses["updated_at"] = $general->formatDateTimeToIsoFormat($addresses["updated_at"]);
                $return[] = $addresses;
            }

            if (!$return) {
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
            }


            $db->where("wallet_id", $wallet_id);
            $db->where("type", $wallet_type);
            $db->where("status", 1);
            $destination_address_result = $db->getOne("xun_crypto_destination_address");

            if(!$destination_address_result){
                $destination_address = "";
            }else{
                $destination_address = $destination_address_result["destination_address"];
            }


            $returnData["addresses"] = $return;
			$returnData["destination_address"] = $destination_address;
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00098') /*Address Listing*/, "code" => 1, "result" => $returnData);

        }

        function get_address_list($params){
            
            $db = $this->db;
            
            $business_id = $params["business_id"];
            $wallet_type = $params["wallet_type"];
            $pageNumber  = $params['page'] ? $params['page'] : 1;
            $pageLimit   = 50;
            
            $startLimit  = ($pageNumber-1) * $pageLimit;
            $limit       = array($startLimit, $pageLimit);
            
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }
            
            if (!$wallet_type) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
            }
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }
            
            $db->where("business_id", $business_id);
            $db->where("type", $wallet_type);
            $wallet_result = $db->getOne("xun_crypto_wallet");
            
            if($wallet_result["status"] == "0"){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00152') /*Wallet is set to off*/);
            }
            
            $db->where("wallet_id", $wallet_result["id"]);
            $db->where("type", "in");

            $copyDb = $db->copy();
            $address_result = $db->get("xun_crypto_address", $limit);
            
            foreach($address_result as $addresses){
                
                $return[] = $addresses;
                
            }
            
            if (!$return) {
                return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
            }
            
            $totalRecords = $copyDb->getValue("xun_crypto_address", "count(id)");
            
            $returnData["addresses"] = $return;
            $returnData['totalPage']   = ceil($totalRecords / $limit[1]);
            $returnData['pageNumber']  = $pageNumber;
            $returnData['totalRecord'] = $totalRecords;
            $returnData['numRecord']   = $limit[1];
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00098') /*Address Listing*/, "code" => 1, "result" => $returnData);
        
        }

	function get_app_paymentgateway_status($params) {

            global $config;

            $db = $this->db;

            $username = $params["username"];
			$business_id = $params["business_id"];

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }


			$db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }


	    $db->where("a.business_id", $business_id);
	    $db->join("xun_crypto_destination_address b", "a.id=b.wallet_id", "INNER");
            $wallet_result = $db->getOne("xun_crypto_wallet a");

            if(!$wallet_result){
                return array("status" => "ok", "message" => "SUCCESS", "message_d" => "Payment gateway setup status", "code" => 1, "has_setup" => false);
            } else {
				return array("status" => "ok", "message" => "SUCCESS", "message_d" => "Payment gateway setup status", "code" => 1, "has_setup" => true);
	    }

	}

	function set_app_wallet_status($params){

	    global $config;

            $db = $this->db;

            $username = $params["username"];

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

            return $this->set_wallet_status($params);

	}

        function set_wallet_status($params){
            
            $db = $this->db;
            
            $business_id = $params["business_id"];
            $wallet_type = $params["wallet_type"];
            $status      = strlen($params["status"]) > 0 ? $params["status"] : "1";
            $currentDate = date("Y-m-d H:i:s");
            
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }
            
            if (!$wallet_type) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
            }
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }
            
            /*            $db->where("business_id", $business_id);
            $db->where("type", $wallet_type);
            $wallet_result = $db->getOne("xun_crypto_wallet");
            
            if($wallet_result["status"] == "0"){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet is set to off");
            }*/
            
            $db->where("business_id", $business_id);
            $db->where("type", $wallet_type);
            $wallet_result = $db->getOne("xun_crypto_wallet");
            if(!$wallet_result){
                $fields = array("business_id", "type", "status", "created_at", "updated_at");
                $values = array($business_id, $wallet_type, $status, $currentDate, $currentDate);
                $insertData = array_combine($fields, $values);

                $wallet_id = $db->insert("xun_crypto_wallet", $insertData);
            }else{
                $updateData["updated_at"] = date("Y-m-d H:i:s");
                $updateData["status"] = $status;
                
                $db->where("id", $wallet_result["id"]);
                $db->update("xun_crypto_wallet", $updateData);
            }
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00099') /*Wallet Status Updated*/, "code" => 1, "result" => $returnData);
        
        }

	function app_generate_apikey($params){

            global $config;

            $db = $this->db;

            $username = $params["username"];

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

            return $this->generate_apikey($params);


	}

        function generate_apikey($params, $source = 'business'){
            
            global $xunPaymentGateway;
            $db = $this->db;
            
            $business_id  = $params["business_id"];
            $expired_at   = $params["expired_at"] ? $params["expired_at"]:"2999-12-31";
            $reference = $params["reference"] ? $params["reference"] : "";
            $today = date('Y-m-d');
            
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }

            if ($expired_at < $today){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00589') /*Expiry date must be later than today.*/);
            }

            $db->where('user_id', $business_id);
            $business_account = $db->getOne('xun_business_account');

            $account_type = $business_account['account_type'];

            if($account_type == 'basic'){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00595') /*Please upgrade to premium account to generate api key.*/, 'error_code' => -103);
            }
            
            //generate the apikey
            while (1) {

                $alphanumberic  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $apikey         = substr(str_shuffle($alphanumberic), 0, 32);

                $db->where('apikey', $apikey);
                $result = $db->get('xun_crypto_apikey');

                if (!$result) {
                    break;
                }
                
            }
            
            $fields = array("business_id", "apikey", "reference", "status", "created_at", "updated_at", "expired_at");
            $values = array($business_id, $apikey, $reference, "1", date("Y-m-d H:i:s"), date("Y-m-d H:i:s"), $expired_at);
            $insertData = array_combine($fields, $values);
            
            $db->insert("xun_crypto_apikey", $insertData);
            // if($source == 'nuxpay'){
                $db->where('id', $business_id);
                $db->where('register_site', 'nuxpay');
                $xun_user = $db->getOne('xun_user');

                $nickname = $xun_user["nickname"];
                $phone_number = $xun_user["username"];
    
                $return_message = "API Key successfully generated.";
                $tag = "Generate API Key";
                $message = "Username: ".$nickname. "\n";
                $message .= "Phone number: ".$phone_number. "\n";
                $message .= "Expiry Date: ".$expired_at."\n";
                $message .= "Reference name: ".$reference."\n";
                $message .= "Status: SUCCESS\n";
                $message .= "Message: ".$return_message."\n"; 
                $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                $message .= "Source: ".$source."\n";
    
                $xunPaymentGateway->send_nuxpay_notification($tag, $message);
            // }
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00100') /*API Key successfully generated.*/, "code" => 1, "result" => "");
        
        }

	function app_delete_apikey($params){

	    global $config;

            $db = $this->db;

            $username = $params["username"];
	    $business_id = $params["business_id"];
	    $apikey_id  = $params["apikey_id"];

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

	    if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }

            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
	    }

	    if (!$apikey_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00159') /*API Key id cannot be empty.*/);
            }

	    $updateData["status"] = 0;
            $updateData["updated_at"] = date("Y-m-d H:i:s");

	    $db->where("id", $apikey_id);
	    $db->where("business_id", $business_id);
            $db->update("xun_crypto_apikey", $updateData);

	    return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00160') /*API Key deleted.*/, "code" => 1, "result" => "");

	}

        function delete_apikey($params){
            
            $db = $this->db;
            
            $apikey_id  = $params["apikey_id"];
            
            if (!$apikey_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00159') /*API Key id cannot be empty.*/);
            }
            
            $updateData["status"] = 0;
            $updateData["updated_at"] = date("Y-m-d H:i:s");
            
            $db->where("id", $apikey_id);
            $db->update("xun_crypto_apikey", $updateData);
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00160') /*API Key deleted.*/, "code" => 1, "result" => "");
        
        }

        function get_app_apikey_list($params) {

            global $config;

            $db = $this->db;
            $general = $this->general;

            $username = $params["username"];
            $business_id = $params["business_id"];
            $apikey_id  = $params["apikey_id"];

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }

            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }

            $db->where("business_id", $business_id);
            $db->where("status", "1");
            $db->orderBy("id", "DESC");
            $apikey_result = $db->get("xun_crypto_apikey");

            foreach($apikey_result as $apikey_data){
                $apikey_data["created_at"] = $general->formatDateTimeToIsoFormat($apikey_data["created_at"]);
                $apikey_data["updated_at"] = $general->formatDateTimeToIsoFormat($apikey_data["updated_at"]);
                $apikey_data["expired_at"] = $general->formatDateTimeToIsoFormat($apikey_data["expired_at"]);
                $return[] = $apikey_data;
            }

            if (!$return) {
                return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
            }

            $returnData["apikeys"] = $return;

            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00101') /*API Key Listing*/, "code" => 1, "result" => $returnData);

        }

        function get_apikey_list($params, $sourceName = 'business'){
            global $setting;
            $db = $this->db;
            // $post   = $this->post;
            
            $member_page_limit = $setting->getMemberPageLimit();
            $page_number       = $params["page"];
            $page_size         = $params["page_size"] ? $params["page_size"] : $member_page_limit;
            $business_id       = $params["business_id"];
            $from_datetime     = $params["from_datetime"];
            $to_datetime       = $params["to_datetime"];
            $status            = $params['status'];

            $date = date("Y-m-d H:i:s");
            
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/, 'here');
            }
            
            if ($from_datetime) {
                $from_datetime  = date("Y-m-d H:i:s", $from_datetime);
                $db->where("created_at", $from_datetime, ">=");
            }

            if ($to_datetime) {
                $to_datetime  = date("Y-m-d H:i:s", $to_datetime);
                $db->where("created_at", $to_datetime, "<=");
            }
            
            if($status == 'active'){
                $db->where('expired_at', $date, '>=');
            }
            elseif($status == 'expired'){
                $db->where('expired_at', $date, '<');
            }

            $db->where('status', 1);
            $db->where("business_id", $business_id);

            if($page_number < 1){
                $page_number = 1;
            }

            $start_limit = ($page_number -1) * $page_size;
            $limit       = array($start_limit, $page_size);

            $db->orderBy("id", "DESC");
            $copyDb = $db->copy();
            $apikey_result = $db->get("xun_crypto_apikey", $limit);
            $totalRecord = $copyDb->getValue('xun_crypto_apikey', 'count(id)');

            foreach($apikey_result as $apikey_data){
                $return[] = $apikey_data;
                
            }

            foreach($return as $key => $value){
                if ($return[$key]['expired_at'] == "2999-12-31 00:00:00"){
                    $return[$key]['expired_at'] = '';
                }
            }
            
            if (!$return) {
                return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
            }
            
            $returnData["apikeys"] = $return;
            $returnData["totalRecord"] = $totalRecord;
            $returnData["numRecord"] = $page_size;
            $returnData["totalPage"] = ceil($totalRecord/$page_size);
            $returnData["pageNumber"] = $page_number;
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00101') /*API Key Listing*/, "code" => 1, "result" => $returnData);
        
        }

		function get_app_transaction_detail($params) {

            global $config;

            $db = $this->db;
            $general = $this->general;

            $username = $params["username"];
	    $id = $params["id"];
	    $reference_id = $params["reference_id"];
            $transaction_id = $params["transaction_id"];

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            if ($id == '' && $transaction_id == '' && $reference_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Id/Transaction Id/Reference Id cannot be empty");
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

            if ($transaction_id != "") {
                $db->where("transaction_id", $transaction_id);
	    } else if ($reference_id != "") {
		$db->where("reference_id", $reference_id);
	    } else {
                $db->where("id", $id);
            }

            $detail_result = $db->getOne("xun_crypto_history");

            if (!$detail_result) {
                return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
            }

            $detail_result["transaction_date"] = $general->formatDateTimeToIsoFormat($detail_result["transaction_date"]);
            $detail_result["created_at"] = $general->formatDateTimeToIsoFormat($detail_result["created_at"]);
            $detail_result["updated_at"] = $general->formatDateTimeToIsoFormat($detail_result["updated_at"]);

            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00154') /*Transaction History.*/, "code" => 1, "result" => $detail_result);

        }

	function get_app_transaction_list($params) {

            global $config;

            $db = $this->db;
			$general = $this->general;

            $username = $params["username"];
            $business_id = $params["business_id"];
            $wallet_type = $params["wallet_type"];
            $status      = $params["status"];
            $from        = $params["from"];
            $to          = $params["to"];
            $type        = $params["type"]; // in / out
	    
	    	//$last_id  = $params['last_id'] ? $params['last_id'] : 0;
            //$fetch_limit   = $params["fetch_limit"] ? $params["fetch_limit"] : 20;
	    	//$order_by = $params["order_by"] ? $params["order_by"] : "desc";

	    	if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }

	    	if(!$wallet_type) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet Type cannot be empty");
	    	}

	    	if(!$status) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Status cannot be empty");
            }

	    	if (!$from) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "From date cannot be empty");
            }

	    	if (!$to) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "To date cannot be empty");
	    	}

            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }

	    	$db->where("business_id", $business_id);

			if($wallet_type != "allcoin"){
            	$db->where("wallet_type", $wallet_type);
			}
            $db->where("created_at", $from." 00:00:00", ">=");
            $db->where("created_at", $to." 23:59:59", "<=");
            
			if($status != "all"){
				$db->where("status", $status);
            }
            
            if($type != ''){
                $db->where("type", $type);
            }

	    	//$db->where("id", $last_id, $order_by == "asc" ? ">": "<");
	    	//$db->orderBy("id", $order_by);
	    	//$history_result = $db->get("xun_crypto_history", $fetch_limit);

			$db->orderBy("id", "desc");
			$history_result = $db->get("xun_crypto_history");

            foreach($history_result as $history){
				$history["transaction_date"] = $general->formatDateTimeToIsoFormat($history["transaction_date"]);
				$history["created_at"] = $general->formatDateTimeToIsoFormat($history["created_at"]);
				$history["updated_at"] = $general->formatDateTimeToIsoFormat($history["updated_at"]);
                $return[] = $history;
            }

            if (!$return) {
                return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
            }

            $returnData["transaction_list"] = $return;

            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00154') /*Transaction History.*/, "code" => 1, "result" => $returnData);

    }
    

        function get_transaction_list($params){
            
            global $setting;

            $db     = $this->db;
            $post   = $this->post;
            
            $member_page_limit  = $setting->getMemberPageLimit();
            $page_number        = $params["page"];
            $page_size          = $params["page_size"] ? $params["page_size"] : $member_page_limit;
            $business_id        = $params["business_id"];
            $wallet_type        = $params["wallet_type"];
            $status             = $params["status"];
            // $address     = $params["address"];
            // $coin_type             = $params['coin_type'];
            $from               = $params["from"];
            $to                 = $params["to"];
            
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }
            
           //if ($address) {
           //    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Address cannot be empty");
           //}
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business", "name");

            $db->where("id", $business_id);
            $business_mobile = $db->getOne("xun_user", "username");

            // if(!$wallet_type){
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00424') /*Wallet type does not exist.*/, "developer_msg" => "Wallet type not found in xun_coins table.");
            // }


            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }
            
            $db->where("business_id", $business_id);
            
            // if($coin_type){
            //     $db->where('a.wallet_type', $coin_type);
            // }

            if ($wallet_type) {
                $db->where("wallet_type", $wallet_type);
            }
            
            if ($from) {
                $from = date("Y-m-d H:i:s", $from);
                $db->where("created_at", $from, ">=");
            }
            if ($to) {
                $to = date("Y-m-d H:i:s", $to);
                $db->where("created_at", $to, "<=");
            }
            
            if($status){
                $db->where("status", $status);
            }

            if ($page_number < 1){
                $page_number = 1;
            }

            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);
            
            $db->orderBy("transaction_date", "DESC");
            $copyDb = $db->copy();
            $history_result = $db->get("xun_crypto_history", $limit);
            $totalRecord = $copyDb->getValue('xun_crypto_history', 'count(id)');
            
            $marketplace_currencies = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies', null, 'currency_id, name, symbol, image');
            
            $db->where('type', 'cryptocurrency');
            $img_list = $db->get('xun_marketplace_currencies', null, 'currency_id, image, symbol');
            
            foreach($img_list as $img_obj){
                $return_img_list[$img_obj['currency_id']] = $img_obj['image']; 
                $return_symbol_list[$img_obj['currency_id']] = $img_obj['symbol'];

            }

            foreach($history_result as $history){
                // $wallet_type = $history['wallet_type'];
                // $wallet_type = strtolower($wallet_type);

                // $image = $marketplace_currencies[$wallet_type]['image'];
                // $unit = $marketplace_currencies[$wallet_type]['symbol'];
                // $uc_unit = strtoupper($unit);
                // $history['image']= $image;
                // $history['currency_unit'] = $uc_unit;
                $history['name'] = $business_result['name'];
                $history['phone_number'] = $business_mobile['username'];
                $return[] = $history;
                // $return['name'] = $business_result['name'];
                // $return['phone_number'] = $business_result['phone_number'];

            }
            
            if (!$return) {
                return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
            }
            $num_record = !$see_all ? $page_size : $totalRecord;
            $total_page = ceil($totalRecord/$num_record);
            $returnData['crypto_symbol_list'] = $return_symbol_list;
            $returnData['crypto_img_list'] = $return_img_list;
            $returnData["transaction_list"] = $return;
            $returnData["totalPage"] = $total_page;
            $returnData["totalRecord"] = $totalRecord;
            $returnData["numRecord"] = $page_size;
            // $returnData["totalPage"] = ceil($totalRecord/$page_size);
            $returnData["pageNumber"] = $page_number;
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00154') /*Transaction History.*/, "code" => 1, "result" => $returnData);
        
        }
        
        function get_wallets_destination_address($params){
            
            $db     = $this->db;
            $post   = $this->post;
            
            $business_id = $params["business_id"];
            
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }
            
            $db->where("business_id", $business_id);
            $wallet_result = $db->get("xun_crypto_wallet");
            
            foreach($wallet_result as $wallet_data){
                
                $wallet_id = $wallet_data["id"];
                $wallet_name = $wallet_data["type"];
                
                $db->where("wallet_id", $wallet_id);
                $dest_result = $db->getOne("xun_crypto_destination_address");
                
                $return[$wallet_name] = $dest_result["destination_address"];
                
            }
            
            $returnData["destination_addresses"] = $return;
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00089') /*Wallet Destination Addresses.*/, "code" => 1, "result" => $returnData);
        
        }

        function get_wallets_destination_address_v1($params){
            
            $db     = $this->db;
            $post   = $this->post;
            
            $business_id = $params["business_id"];
            $wallet_type = $params['wallet_type'];
            
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }
            
            if($wallet_type){
                $db->where('type', $wallet_type);
            }
            $db->where("business_id", $business_id);
            $db->orderBy("type");
            $wallet_result = $db->get("xun_crypto_wallet");
            
            foreach($wallet_result as $wallet_data){
                
                $wallet_id = $wallet_data["id"];
                $wallet_name = $wallet_data["type"];
                
                $db->where("a.wallet_id", $wallet_id);
                $db->join("xun_marketplace_currencies b", "a.type=b.currency_id", "LEFT");
                $dest_result = $db->get("xun_crypto_destination_address a", null, "a.wallet_name, a.destination_address, a.status, b.image, b.symbol, b.display_symbol");
                
                $address_list = [];
                foreach($dest_result as $key=> $value){
                    $address_list = array(
                        "address" => $value["destination_address"],
                        "wallet_name" => $value["wallet_name"],
                        "status" => $value['status'],
                        "image" => $value['image'],
                        "symbol" => strtoupper($value['symbol']),
                        "display_symbol" => strtoupper($value['display_symbol']),
                    );
                    $return[$wallet_name][] = $address_list;
                }
            }
            
            $returnData["destination_addresses"] = $return;
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00089') /*Wallet Destination Addresses.*/, "code" => 1, "result" => $returnData);
        
        }
        
        function get_wallet_type($params, $user_id){
            
            global $config, $db, $xunSwapcoins;
            $provider = $params['provider'];
            $setting_type = $params['setting_type'];
            $usergetID  = $params['user_id'];
            $tx_wallet_type = $params['tx_wallet_type'];

            if($usergetID == ""){ //get header if body empty
                $user_id;
            }
            else{               // get header if body empty
                $user_id = $usergetID;
            };
            
           // $wallet_list = $config["cryptoWalletType"];

            if($setting_type == 'payment_gateway'){
    
                $db->where('user_id', $user_id);
                $db->where('name', 'showWallet');
                $user_setting = $db->getOne('xun_user_setting');
                if($user_setting){
                    $show_coin_arr = json_decode($user_setting['value']);
                }
                else{
                    $show_coin_arr = array();
                }
                if($show_coin_arr){
                    $db->where("a.currency_id", $show_coin_arr, "IN");
                //     $db->orWhere("a.is_default", 1);
                // }else{
                //     $db->where("a.is_default", 1);
    
                }

            }
            else if($setting_type == 'nuxpay_wallet'){

                //WALLET
                $db->where('user_id', $user_id);
                $db->where('name', 'showNuxpayWallet');
                $user_setting = $db->getOne('xun_user_setting');
                if($user_setting){
                    $show_coin_arr = json_decode($user_setting['value']);
                }
                else{
                    $show_coin_arr = array();
                }
                if($show_coin_arr){
                    $db->where("a.currency_id", $show_coin_arr, "IN");
                //     $db->orWhere("a.is_default", 1);
                // }else{
                //     $db->where("a.is_default", 1);

                }
            }
            else if($setting_type == 'both'){

                //BOTH
                $db->where('user_id', $user_id);
                $db->where('name', array('showWallet', 'showNuxpayWallet'), 'IN');
                $user_setting_data = $db->map('name')->ArrayBuilder()->get('xun_user_setting', null, 'id, name, value');

                
                if($user_setting_data['showNuxpayWallet']){
                    $show_coin_arr_Nuxpay = json_decode($user_setting_data['showNuxpayWallet']['value']);
                }
                else{
                    $show_coin_arr_Nuxpay = array();
                }
                if($user_setting_data['showWallet']){
                    $show_coin_arr = json_decode($user_setting_data['showWallet']['value']);
                }
                else{
                    $show_coin_arr = array();
                }
    
                $combinedList = array_unique(array_merge_recursive($show_coin_arr_Nuxpay, $show_coin_arr));    //merge data
                if($combinedList){
                    $db->where("a.currency_id", $combinedList, "IN");
                }
            }
            else if($setting_type == 'payment_gateway_filter'){
                $db->where('user_id', $user_id);
                $db->where('name', 'showWallet');
                $user_setting = $db->getOne('xun_user_setting');
                if($user_setting){
                    $show_coin_arr = json_decode($user_setting['value']);
                }
                else{
                    $show_coin_arr = array();
                }
                if($show_coin_arr){
                    foreach($show_coin_arr as $key => $value){
                        if ($tx_wallet_type != $value)
                        {
                            $swapSetting = $xunSwapcoins->getSwapSetting($value, $tx_wallet_type);
                            if ($swapSetting['code'] == 0) {
                                    unset($show_coin_arr[$key]);
                            }
                        }
                   }
                }
                if($show_coin_arr){
                    $db->where("a.currency_id", $show_coin_arr, "IN");
                }
            }
            else{
                $db->where('a.is_payment_gateway', '1');
            }
            
            $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'INNER');
            $db->orderBy("a.sequence", "ASC");
            $xun_coins = $db->get('xun_coins a', null, 'b.name, b.currency_id, b.image, b.symbol, b.display_symbol');

            if($provider){
                $db->where('name', $provider);
                $provider_id = $db->getValue('provider', 'id');

                if(!$provider_id){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Provider not found.");
                }

                $db->where('provider_id', $provider_id);
                $db->where('name', 'supportedCurrencies');
                $provider_setting_data = $db->getValue('provider_setting','value');
                
                $supportedCurrenciesList = explode(",", $provider_setting_data);
                
                $db->where('symbol', $supportedCurrenciesList, 'IN');
                $db->orderBy('name', 'ASC');
                $marketplace_currencies = $db->get('xun_marketplace_currencies', null, 'name, currency_id, image, symbol');
                // print_r($db);
                $data['currency_list'] = $marketplace_currencies;

                return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00090') /*Wallet Types.*/, 'data' => $data);
            
            }

           foreach($xun_coins as $key => $value){
                $wallet_type = $value['currency_id'];
                $name = $value['name'];
                $image = $value['image'];
                $symbol = $value['symbol'];
                $display_symbol = $value['display_symbol'];

                $wallet_list[] = $wallet_type;
                $coin_array = array(
                    "name" => $name,
                    "wallet_type" => $wallet_type,
                    "image" => $image,
                    "symbol" => strtoupper($symbol),
                    "display_symbol" => strtoupper($display_symbol)
                );
                $coin_list[] = $coin_array;
                
            }
            $returnData["wallet_types"] = $wallet_list;
            $returnData["coin_data"] = $coin_list;
            //For buy crypto listing
            $data['currency_list'] = $xun_coins;
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00090') /*Wallet Types.*/, "code" => 1, "result" => $returnData, 'data' => $data);
        
        }
        
        function get_wallet_data($params){
            
            $db     = $this->db;
            
            $business_id    = $params["business_id"];
            
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }
            
            $db->where("business_id", $business_id);
            $wallet_result = $db->get("xun_crypto_wallet");
            
            foreach($wallet_result as $wallet){
                
                $return[] = $wallet;
                
            }
            
            $returnData["wallets"] = $return;
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00091') /*Wallet Data.*/, "code" => 1, "result" => $returnData);
            
        }
        
        function transaction_callback($params){

            global $xunPaymentGateway, $xunCurrency, $xunSms, $xunEmail, $setting, $general, $xunPayment, $log, $xunSwapcoins, $webservice;
            $db     = $this->db;
            $post   = $this->post;
            $callback_error = false;
            $received_tx_id     =   $params["receivedTxID"];
            $transaction_id     =   $params["txID"];
            $amount             =   $params["amount"] ? $params["amount"] : 0;
            $status             =   $params["status"];
            $transaction_date   =   $params["transactionDate"] ? date("Y-m-d H:i:s", strtotime($params["transactionDate"])) :  date("Y-m-d H:i:s");
            $wallet_type        =   $params["type"];
            $address            =   $params["address"] ? $params["address"] : "-";
            $transaction_url    =   $params["transactionUrl"] ? $params["transactionUrl"] : "-";
            $reference_id       =   $params["referenceID"] ? $params["referenceID"] : 0;
            $amount_receive     =   $params["amountReceive"] ? $params["amountReceive"] : 0;
            $service_charge     =   $params["serviceCharge"] ? $params["serviceCharge"] : 0;
            $received_txs_arr   =   $params['txDetails']['input'];
            $credit_arr         =   $params['txDetails']['output'][0];
            $gw_type            =   $params['gw_type']==""?"PG":$params['gw_type'];
            $date               =   date("Y-m-d H:i:s");

            // wentin test // 
            $target             =   $params["transactionType"];
            $transaction_hash   =   $params["transactionHash"];

            $log->write("\n".date('Y-m-d')." Debug - params ".json_encode($params));

            $log->write("\n".date('Y-m-d')." Debug - transaction_callback ".json_encode($transaction_callback));

            $transaction_type = trim($params["transactionType"]);
            $sender =   $params["sender"];
            $recipient = $params["recipient"];
            // $credit_details  = $params["creditDetails"];
            // $amount_details = $credit_details["amountDetails"];
            // $amount_receive_details = $credit_details["amountReceiveDetails"];
            // $service_charge_details = $credit_details["serviceChargeDetails"];
            $exchange_rate = $credit_details["exchangeRate"];
            // $miner_amount_details = $credit_details["minerAmountDetails"];
            $timestamp = trim($params["transactionDate"]);
            $reference_id = trim($params["referenceID"]);
            // $eth_miner_amount_details = $credit_details['ethMinerAmountDetails'];
            
            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
            $decimal_places = $decimal_place_setting["decimal_places"];        

            // $amount_value = $amount_details['amount'];
            // $amount_rate = $amount_details['rate'];
            // $final_amount = $amount_value ? bcdiv($amount_value, $amount_rate, $decimal_places) : 0;

            // $amount_receive_value = $amount_receive_details['amount'];
            // $amount_receive_rate = $amount_receive_details['rate'];
            // $final_amount_receive = $amount_receive_value ? bcdiv($amount_receive_value, $amount_receive_rate, $decimal_places) : 0;
            
            // $service_charge_value = $service_charge_details['amount'];
            // $service_charge_rate = $service_charge_details['rate'];
            // $service_charge_wallet_type = $service_charge_details['type'];
            // $service_charge_wallet_type = $service_charge_wallet_type ? $service_charge_wallet_type : $wallet_type;
            // $final_service_charge = $service_charge_value ? bcdiv($service_charge_value, $service_charge_rate, $decimal_places) : 0;

            // $miner_fee_value = $miner_amount_details['amount'];
            // $miner_fee_rate = $miner_amount_details['rate'];
            // $miner_fee_wallet_type = $miner_amount_details['type'];
            // $miner_fee_wallet_type = $miner_fee_wallet_type ? $miner_fee_wallet_type : $wallet_type;
            // $final_miner_fee = $miner_fee_value ? bcdiv($miner_fee_value, $miner_fee_rate, $decimal_places) : 0;
            
            $wallet_type = strtolower($wallet_type);
            $miner_fee_wallet_type = strtolower($miner_fee_wallet_type);
            $service_charge_wallet_type = strtolower($service_charge_wallet_type);

            // $exchangeRate = $exchange_rate["USD"];
            // $miner_fee_exchange_rate = $eth_miner_amount_details['exchangeRate']['USD'] ? $eth_miner_amount_details['exchangeRate']['USD'] : $miner_amount_details['exchangeRate']['USD'];
            // $miner_fee_exchange_rate = $miner_fee_exchange_rate ?: '';

            // $actual_miner_fee_value = $eth_miner_amount_details['amount'] ? $eth_miner_amount_details['amount'] : $miner_fee_value;
            // $actual_miner_fee_wallet_type = $eth_miner_amount_details['type'] ? $eth_miner_amount_details['type'] : $miner_fee_wallet_type;
            // $actual_miner_fee_rate  = $eth_miner_amount_details['rate'] ? $eth_miner_amount_details['rate'] : $miner_fee_rate;
            // $miner_fee_decimal_place_setting = $xunCurrency->get_currency_decimal_places($actual_miner_fee_wallet_type, true);
            // $miner_fee_decimal_places = $miner_fee_decimal_place_setting["decimal_places"];
            // $final_actual_miner_fee = $actual_miner_fee_value ? bcdiv($actual_miner_fee_value, $actual_miner_fee_rate, $miner_fee_decimal_places) : 0;

            $sender_internal_address = $sender['internal'] ? $sender['internal'] : '';
            $sender_external_address = $sender['external'] ? $sender['external'] : '';
            $recipient_internal_address = $recipient['internal'] ? $recipient['internal'] : '';
            $recipient_external_address = $recipient['external'] ? $recipient['external'] : '';

            $ori_params = $params;

            $sender_address = is_array($sender) ? (!empty($sender["internal"]) ? $sender["internal"] : $sender["external"]) : trim($sender);
            $recipient_address = is_array($recipient) ? (!empty($recipient["internal"]) ? $recipient["internal"] : $recipient["external"]) : trim($recipient);

            $skip_pg_callback = 0; //skip pg callback to merchant
            $auto_swap_callback = 0; // send autoswap callback to merchant if set to '1';
            $auto_swap_fail_reason = '';
            $autoSwapPendingStatus = '';
            $log->write("\n".date('Y-m-d')." Debug - gw_type ".json_encode($gw_type));
            if($gw_type=="BC") {

                $db->where("e.external_address", $recipient_external_address);
                $db->where("a.address_type", "nuxpay_wallet");
                $db->where("a.active", 1);
                $db->join("xun_crypto_external_address e", "a.address=e.internal_address", "INNER");
                $business_id = $db->getValue("xun_crypto_user_address a", "a.user_id");

                $pg_address_type = "in";

                $db->where('crypto_address', $sender_address);
                $pg_address_data = $db->getOne('xun_crypto_address');
                $log->write("\n".date('Y-m-d')." Debug - pg_address_data ".json_encode($pg_address_data));
                if($pg_address_data){
                    $skip_pg_callback = 1;
                }

                $db->where('a.payment_address', $sender_address);
                $db->join('xun_payment_gateway_payment_transaction b', 'a.pg_transaction_id = b.id', 'LEFT');
                $receive_fund_data= $db->getOne('xun_payment_gateway_invoice_detail a', 'a.id, a.payment_address, b.business_id');
                $log->write("\n".date('Y-m-d')." Debug - receive_fund_data ".json_encode($receive_fund_data));
                $log->write("\n".date('Y-m-d')." Debug - status ".json_encode($status));
                if($receive_fund_data){
                    $skip_pg_callback = 1;
                }

                $db->where('user_id',$business_id);
                $db->where('name', 'allowSwitchCurrency');
                $isAllowSwitchCurrency = $db->getValue('xun_user_setting', 'value');

                // trigger for `received` status autoswapCallback
                $log->write("\n".date('Y-m-d')." Debug - confirmation ".json_encode($params));
                if ($transaction_type == 'external' && $status == 'pending' && $params['confirmation'] == 1 && $isAllowSwitchCurrency == '1') {
                    $log->write("\n".date('Y-m-d')." Debug - pending autoswap cryptocallback received. Trigger autoswap callback.");
                    $auto_swap_callback = 1; // set this flag for autoswap callback
                    $autoSwapPendingStatus = 'received';
                }

                // Check if the sender_address is from nuxpay's payment method. --> TABLE: xun_payment_method
                // If data exist, it means that merchant had set their destination to NuxPay wallet, 
                // and so the sender_address is the address generated from merchant request.
                // Then, check if the received credit type is equivalent to payment method (merchant request) --> TABLE: xun_payment_transaction
                if ($transaction_type == 'external' && $status == 'success') {
                    $db->where('address', $sender_external_address);
                    $db->where('type', 'payment_gateway');
                    $db->orderBy('created_at', 'DESC');
                    $paymentId = $db->getValue('xun_payment_method', 'payment_tx_id');
                    
                    // If true, means this is a payment to own's NuxPay wallet.
                    // Check and see if merchant had set `allowSwitchCurrency` to true --> TABLE: xun_user_setting
                    // If `allowSwitchCurrency=1`, swap the received amount to default requested credit type
                    if ($paymentId) {
                        $log->write("\n".date('Y-m-d')." Message - payment details detected! user is transfering payment amount to NuxPay wallet.");
                        
                        if ($isAllowSwitchCurrency == '1') {
                            $auto_swap_callback = 1; // set this flag for autoswap callback
                            $log->write("\n".date('Y-m-d')." Message - user ".$business_id." had set allowDefaultCurrency.");
                            
                            // first, check the default requested credit type is equivalent to received credit type
                            // swap the credit if false
                            $db->where('id', $paymentId);
                            $paymentTxnDetails = $db->getOne('xun_payment_transaction');
                            $log->write("\n".date('Y-m-d')." Message - received amount type: ".$wallet_type.", requested type: ".$paymentTxnDetails['wallet_type']);

                            // check also if the payment is from other merchant or from own payment
                            // only perform swap if all critiria is correct
                            if ($wallet_type != $paymentTxnDetails['wallet_type'] && $paymentTxnDetails['business_id'] == $business_id) {
                                $log->write("\n".date('Y-m-d')." Message - Credit type is different! Auto swapping...");

                                // get available exchange_swap provider
                                $db->where('type', 'exchange_swap');
                                $db->where('disabled', 0);
                                $providerIDs = $db->get('provider');
                                if (count($providerIDs) > 1) {
                                    $log->write("\n".date('Y-m-d')." Error - there is more than 1 exchange_swap provider available. Please choose only one provider in database.");
                                    $auto_swap_fail_reason = 'Something went wrong. Please contact us. ERROR_REF:1';
                                } else {
                                    $fromAmount = (explode(' ', $amount)[0]) ? (explode(' ', $amount)[0]) : $amount;
                                    $swapParams = array(
                                        'businessID' => $business_id,
                                        'fromWalletType' => $wallet_type,
                                        'toWalletType' => $paymentTxnDetails['wallet_type'],
                                        'fromAmount' => $fromAmount,
                                        'toAmount' => $paymentTxnDetails['crypto_amount'],
                                    );
                                    $swapEstimation = $xunSwapcoins->estimateSwapCoinRate($swapParams, $providerIDs[0]['id']);
                                    $log->write("\n".date('Y-m-d')." Debug - swap estimation ".json_encode($swapEstimation));
                                    if ($swapEstimation['code'] == 0) {
                                        $log->write("\n".date('Y-m-d')." Error - auto awap estimation result ".json_encode($swapEstimation));
                                        if ($swapEstimation['message_d'] == 'Amount is too small for swap.') {
                                            $auto_swap_fail_reason = 'Received amount is zero.';
                                        } 
                                        else if (strpos($swapEstimation['message_d'], 'From amount must be at least') !== false) {
                                            $auto_swap_fail_reason = 'Received amount does not meet minimum requirement.';
                                        } 
                                        else {
                                            $auto_swap_fail_reason = 'Something went wrong. Please contact us. ERROR_REF:2';
                                        }
                                    } else {
                                        $swapResult = $xunSwapcoins->autoSwap($swapEstimation['data']['referenceID'], $providerIDs[0]['id'], $paymentTxnDetails['transaction_token']);
                                        
                                        if($swapResult['code'] == 0) {
                                            $log->write("\n".date('Y-m-d')." Error - swap result ".json_encode($swapResult));

                                            $tag = "Failed Auto Swap";

                                            $message = "Business Name: ".$business_name."\n";
                                            $message .= "TxID: ".$transaction_hash."\n";
                                            $message .= "Amount: ".$amount."\n";
                                            $message .= "Wallet Type: ".$wallet_type."\n";
                                            $message .= "Msg Return: ".json_encode($res)."\n";
                                            $message .= "Created At: ".date("Y-m-d H:i:s")."\n";
                    
                                            $notificationParams = array(
                                                "tag"   => $tag,
                                                "message" => $message
                                            );
                    
                                            $general->send_thenux_notification($notificationParams, "thenux_issues");   

                                            $auto_swap_fail_reason = 'Something went wrong. Please contact us. ERROR_REF:3';
                                        } else {
                                            $log->write("\n".date('Y-m-d')." Debug - swap result ".json_encode($swapResult));
                                            $auto_swap_fail_reason = '';

                                            // update swap history into xun_payment_details
                                            $pgDetailsUpdate = array(
                                                'swap_history_id' => $swapResult['data']['swapHistoryID'],
                                            );
                                            $transactionHistoryTable = 'xun_payment_transaction_history_'.date('Ymd');
                                            $db->where('transaction_id', $received_tx_id);
                                            $pgPaymentDetailID = $db->getValue($transactionHistoryTable, 'payment_details_id');
                                            $log->write("\n".date('Y-m-d')." Debug - pgDetailsID ".$pgPaymentDetailID);
                                            $db->where('id', $pgPaymentDetailID);
                                            $db->update('xun_payment_details', $pgDetailsUpdate);

                                            // $tag = "Success Auto Swap";

                                            // $message = "Business Name: ".$business_name."\n";
                                            // $message .= "TxID: ".$transaction_id."\n";
                                            // $message .= "Amount: ". explode(" ",$amount)[0]."\n";
                                            // $message .= "Wallet Type: ".$wallet_type."\n";
                                            // $message .= "Result: ".json_encode($res)."\n";
                                            // $message .= "Created At: ".date("Y-m-d H:i:s")."\n";
                    
                                            // $notificationParams = array(
                                            //     "tag"   => $tag,
                                            //     "message" => $message
                                            // );
                    
                                            // $general->send_thenux_notification($notificationParams, "thenux_issues");   
                                        }
                                    }
                                }

                            } else {
                                if ($paymentTxnDetails['business_id'] != $business_id) {
                                    // rare case, but it might happens... money comes in, why not :) 
                                    $log->write("\n".date('Y-m-d')." Message - Payment is from other merchant, skip swapping.");
                                    $auto_swap_fail_reason = 'Something went wrong. Please contact us. ERROR_REF:4';
                                } else {
                                    $auto_swap_fail_reason = 'Credit type received are same as requested. Autoswap skipped.';
                                    $log->write("\n".date('Y-m-d')." Message - Credit type received is same as payment method, skip swapping.");
                                }

                            }
                        }

                    }
                }           
            } else {

                $db->where("crypto_address", $address);
                $address_result = $db->getOne("xun_crypto_address");
                $pg_address_type = $address_result["type"];
                $wallet_id = $address_result["wallet_id"];
                
                $db->where("id", $wallet_id);
                $wallet_result = $db->getOne("xun_crypto_wallet");
                $business_id = $wallet_result["business_id"];

            }

            if(!$business_id){  
                $db->where('payment_address', $address);
                $db->join('xun_payment_gateway_payment_transaction b', 'a.pg_transaction_id = b.id', 'LEFT');
                $invoice_detail= $db->getOne('xun_payment_gateway_invoice_detail a', 'a.id, a.payment_address, b.business_id');
                if($invoice_detail){
                    $business_id = $invoice_detail['business_id'];
                    $pg_address_type = 'in';
                }
                
            }

            $consolidate_wallet_address = $setting->systemSetting['requestFundConsolidateWalletAddress'];
            $joinDateLimit = $setting->systemSetting['userJoinDateLimit'];
            if (!$business_id){

                $db->where('crypto_address', $address);
                $addressId = $db->getOne('xun_crypto_address');
                
                $db->where('id', $addressId['wallet_id']);
                $getUserId = $db->getOne('xun_crypto_wallet');
                $business_id = $getUserId['business_id'];
            }
            
            $db->where('id', $business_id);
            $userSite = $db->getOne('xun_user');

            $recipientJoinDate = $userSite['created_at'];
            $recipientJoinTimeStamp = strtotime($recipientJoinDate);

            if ($recipientJoinTimeStamp < $joinDateLimit){

                if ($status == 'success'){

                    $db->where('payment_address', $address);
                    $paymentGatewayInvoiceDetail = $db->getOne('xun_payment_gateway_invoice_detail');

                    //if Gateway type is BC and paymentGatewayInvoiceDetail is empty
                    if(!$paymentGatewayInvoiceDetail && $gw_type == "BC"){
                        $recipient_external = $recipient["external"];
                        $db->orderBy('id', 'desc');
                        $db->where('payment_address', $recipient_external);
                        $paymentGatewayInvoiceDetail = $db->getOne('xun_payment_gateway_invoice_detail');
                    }
                    $db->where('id', $business_id);
                    $userSite = $db->getOne('xun_user');

                    $source = $userSite["register_site"];

                    $db->where('source', $source);
                    $site = $db->getOne('site');

                    $companyName = $site['source'];
                    $domain = $site['domain'];
                    $receiveAmount = $received_txs_arr[0]["amount"];

                    if ($paymentGatewayInvoiceDetail){
                        
                        $receiverName = $paymentGatewayInvoiceDetail["payee_name"];
                        $receiverMobile = $paymentGatewayInvoiceDetail["payee_mobile_phone"];
                        $receiverEmail = $paymentGatewayInvoiceDetail["payee_email_address"];
                        $payerName = $paymentGatewayInvoiceDetail["payer_name"];
                        $description = $paymentGatewayInvoiceDetail["payment_description"];
                        $invoice_gw_type = $paymentGatewayInvoiceDetail["gw_type"];

                        $db->where('currency_id', $wallet_type);
                        $currencies = $db->getOne('xun_marketplace_currencies');

                        $symbol = strtoupper($currencies["symbol"]);
                        if($receiverMobile){
                                
                            if($invoice_gw_type == "PG" && $gw_type == "PG"){
                                $return_message = $this->get_translation_message('B00371') /*"%%companyName%%: You have received %%amount%% %%currency%% from %%senderName%%. Find out more details at %%domain%%"*/;
                                $return_message2 = str_replace("%%companyName%%", $companyName, $return_message);
                                $return_message3 = str_replace("%%amount%%", $receiveAmount, $return_message2);
                                $return_message4 = str_replace("%%currency%%", $symbol, $return_message3);
                                $return_message5 = str_replace("%%senderName%%", $payerName, $return_message4);
                                $return_message6 = str_replace("%%domain%%", $domain, $return_message5);
                                $newParams["message"] = $return_message6;
                                $newParams["recipients"] = $receiverMobile;
                                $newParams["ip"] = $ip;
                                $newParams["companyName"] = $companyName;
                                $newParams["sendType"] = "2way";
                                //$xunSms->send_sms($newParams);
                            }
                            elseif($invoice_gw_type == "BC" && $gw_type == "BC"){
                                $return_message = $this->get_translation_message('B00372') /*"%%companyName%%: You have received %%amount%% %%currency%%. Find out more details at %%domain%%"*/;
                                $return_message2 = str_replace("%%companyName%%", $companyName, $return_message);
                                $return_message3 = str_replace("%%amount%%", $receiveAmount, $return_message2);
                                $return_message4 = str_replace("%%currency%%", $symbol, $return_message3);
                                $return_message5 = str_replace("%%domain%%", $domain, $return_message4);
                                $newParams["message"] = $return_message5;
                                $newParams["recipients"] = $receiverMobile;
                                $newParams["ip"] = $ip;
                                $newParams["companyName"] = $companyName;
                                $newParams["sendType"] = "2way";
                                //$xunSms->send_sms($newParams);
                            }
                        }
                            
                        if($receiverEmail){
                            $receiveFundParam = array(
                            "sender_name" => $payerName,
                            "receiver_name" => $receiverName, 
                            "amount" => $receiveAmount,
                            "symbol" => $symbol,
                            "description"=> $description,
                            );
                            if($invoice_gw_type == "PG"){
                            $emailDetail = $xunEmail->getReceiveFundEmailPG($source, $receiveFundParam);
                            }
                            if($invoice_gw_type == "BC"){
                            $emailDetail = $xunEmail->getReceiveFundEmailBC($source, $receiveFundParam);
                            }
                            $emailParams["subject"] = $emailDetail['emailSubject'];
                            $emailParams["body"] = $emailDetail['html'];
                            $emailParams["recipients"] = array($receiverEmail);
                            $emailParams["emailFromName"] = $emailDetail['emailFromName'];
                            $emailParams["emailAddress"] = $emailDetail['emailAddress'];
                            $emailParams["emailPassword"] = $emailDetail['emailPassword'];
                            $emailParams["companyName"] = $companyName;
                            $msg = $general->sendEmail($emailParams);
                                
                        }                
                    }
                }
            }
            
            // YF3
            if ($recipientJoinTimeStamp > $joinDateLimit){

                if ($status == 'received'){

                    $db->where('crypto_address', $address);
                    $addressId = $db->getOne('xun_crypto_address');

                    $db->where('id', $addressId['wallet_id']);
                    $getUserId = $db->getOne('xun_crypto_wallet');

                    $recipientName = $userSite['nickname'];
                    $recipientMobile = $userSite['username'];
                    $recipientEmail =  $userSite['email'];
                    $source = $userSite["register_site"];

                    $db->where('source', $source);
                    $site = $db->getOne('site');

                    $companyName = $site['source'];
                    $domain = $site['domain'];
                    $receiveAmount = $received_txs_arr[0]["amount"];

                    $db->where('currency_id', $wallet_type);
                    $currencies = $db->getOne('xun_marketplace_currencies');

                    $symbol = strtoupper($currencies["display_symbol"]);
                    $description = "-";

                    if($recipientMobile){                    
                        $return_message = $this->get_translation_message('B00372') /*"%%companyName%%: You have received %%amount%% %%currency%%. Find out more details at %%domain%%"*/;
                        $return_message2 = str_replace("%%companyName%%", $companyName, $return_message);
                        $return_message3 = str_replace("%%amount%%", $receiveAmount, $return_message2);
                        $return_message4 = str_replace("%%currency%%", $symbol, $return_message3);
                        $return_message5 = str_replace("%%domain%%", $domain, $return_message4);
                        $newParams["message"] = $return_message5;
                        $newParams["recipients"] = $recipientMobile;
                        $newParams["ip"] = $ip;
                        $newParams["companyName"] = $companyName;
                        $newParams["sendType"] = "2way";
                        //$xunSms->send_sms($newParams);
                            
                    }
                            
                    if($recipientEmail){
                        $receiveFundParam = array(
                        // "sender_name" => $payerName,
                        "receiver_name" => $recipientName, 
                        "amount" => $receiveAmount,
                        "symbol" => $symbol,
                        "description"=> $description,
                        );
                        $emailDetail = $xunEmail->getReceiveFundEmailBC($source, $receiveFundParam);
                        $emailParams["subject"] = $emailDetail['emailSubject'];
                        $emailParams["body"] = $emailDetail['html'];
                        $emailParams["recipients"] = array($recipientEmail);
                        $emailParams["emailFromName"] = $emailDetail['emailFromName'];
                        $emailParams["emailAddress"] = $emailDetail['emailAddress'];
                        $emailParams["emailPassword"] = $emailDetail['emailPassword'];
                        $emailParams["companyName"] = $companyName;
                        $msg = $general->sendEmail($emailParams);
                            
                    }
                }
            }
            // YF4

            if( ($status == 'pending' || $status == 'success')&& $recipient_internal_address != $consolidate_wallet_address && $gw_type == "PG" ){

                $destination_details = $credit_arr['destination'];
                $service_charge_details = $credit_arr['charges'];
                $miner_fee_details = $credit_arr['minerFee'];
                $eth_miner_fee_details = $credit_arr['ethMinerFee'];

                $wallet_type = strtolower($destination_details['type']);
                $miner_fee_wallet_type = strtolower($miner_fee_details['type']);
                $service_charge_wallet_type = strtolower($service_charge_details['type']);
                $eth_miner_fee_wallet_type = strtolower($eth_miner_fee_details['type']);

                $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
                $decimal_places = $decimal_place_setting["decimal_places"];

                $total_amount = $destination_details['amount'] ? $destination_details['amount'] : 0;
                $total_service_charge = $service_charge_details['amount'] ? $service_charge_details['amount'] : 0;
                $total_miner_fee = $miner_fee_details['amount'] ? $miner_fee_details['amount'] : 0;

                $total_amount_receive = bcadd($total_amount, $total_service_charge, $decimal_places);
                $total_amount_receive = bcadd($total_amount_receive, $total_miner_fee, $decimal_places);
                
                $db->where('reference_id', $reference_id);
                // $db->where('transaction_hash', $transaction_id);
                $pg_withdrawal = $db->getOne('xun_payment_gateway_withdrawal');

                if($pg_withdrawal){
                    $withdrawal_id = $pg_withdrawal['id'];
                    $updateWithdrawal = array(
                        "business_id" => $business_id,
                        "amount" => $total_amount,
                        "amount_receive" => $total_amount_receive,
                        "transaction_fee" => $total_service_charge,
                        "miner_fee" => $total_miner_fee,
                        "wallet_type" => $wallet_type,
                        "sender_address" => $address,
                        "recipient_address" => $recipient_external_address ? $recipient_external_address : $recipient_internal_address,
                        "transaction_hash" => $transaction_id,
                        "status" => $status,
                        "updated_at" => date("Y-m-d H:i:s")
                    );
                    $db->where('reference_id', $reference_id);
                    $db->update('xun_payment_gateway_withdrawal', $updateWithdrawal);
                }
                else{
                    $insertWithdrawal = array(
                        "reference_id" => $reference_id,
                        "business_id" => $business_id,
                        "sender_address" => $address,
                        "recipient_address" => $recipient_external_address ? $recipient_external_address : $recipient_internal_address,
                        "amount" => $total_amount,
                        "amount_receive" => $total_amount_receive,
                        "transaction_fee" => $total_service_charge,
                        "miner_fee" => $total_miner_fee,
                        "wallet_type" => $wallet_type,
                        "transaction_hash" => $transaction_id,
                        "status" => $status,
                        "transaction_type" => ($invoice_detail || $gw_type=='BC') ? "request_fund" : "api_integration",
                        "created_at" => date("Y-m-d H:i:s"),
                        "updated_at" => date("Y-m-d H:i:s")
                    );

                    $withdrawal_id = $db->insert('xun_payment_gateway_withdrawal', $insertWithdrawal);
                }

                $total_fee_charges_amount = bcadd($total_service_charge, $total_miner_fee, $decimal_places);

                $compensate_fee_amount = $setting->systemSetting['compensateFeeAmount']; //Compensate Fee Amount (USD)

                $db->where('id', $business_id);
                $user_data = $db->getOne('xun_user', 'id, nickname, reseller_id');

                $reseller_id = $user_data['reseller_id'];
                if($compensate_fee_amount > 0 && $reseller_id != '0'){

                    if($status == 'success'){
                        $db->where('a.business_id', $business_id);
                        $db->where('a.transaction_type', 'refund_fee');
                        $db->where('a.deleted', 0);
                        $db->join('xun_wallet_transaction b', 'a.reference_id = b.id', 'LEFT');
                        $invoice_transaction = $db->get('xun_payment_gateway_invoice_transaction a', null, 'a.id, a.credit, a.wallet_type, b.exchange_rate');
            
                        if($invoice_transaction){
                            $total_usd_amount = '0.00';
                            foreach($invoice_transaction as $key => $value){
                                $credit = $value['credit'];
                                
                                $exchange_rate = $value['exchange_rate'];
                                $id = $value['id'];
            
                                $usd_amount =  $xunCurrency->get_conversion_amount('usd', $wallet_type, $credit, true, $exchange_rate);
                
                                $total_usd_amount = bcadd($total_usd_amount, $usd_amount, 2);
                                
                            }
                        }

                        $total_usd_amount = ceil($total_usd_amount);
    
                        $total_fee_charges_usd = $xunCurrency->get_conversion_amount("usd", $wallet_type, $total_fee_charges_amount, true);
            
                        $remaining_refund_amount = bcsub($compensate_fee_amount, $total_usd_amount, 2);
            
                         if($remaining_refund_amount > 0){
                            $remaining_amount_usd = bcsub($remaining_refund_amount, $total_fee_charges_usd, 2);
                            
                            if($remaining_amount_usd  > 0){
                                $refund_amount_usd = $total_fee_charges_usd;
                            }
                            else{
                                $refund_amount_usd = $remaining_refund_amount;
                            } 
        
                            $refund_amount = $xunCurrency->get_conversion_amount($wallet_type, "usd", $refund_amount_usd);
    
                            $refund_fee_params = array(
                                "business_id" => $business_id,
                                "amount" => $refund_amount,
                                "wallet_type" => $wallet_type,
                            );
            
                            $xunFreecoinPayout = new XunFreecoinPayout($db, $setting, $general);
                            $xunFreecoinPayout->process_refund_fee_transaction($refund_fee_params);
                
                        }    
         
                        
                    }
                    
                }  
           

            }
            
            foreach($received_txs_arr as $tx_key => $tx_value){
                $reference_id = $tx_value['referenceID'];
                $received_tx_id = $tx_value['receivedTxID'];
     
                // $amount_details = $tx_value['destination'];
                $service_charge_details = $tx_value['charges'];
                $miner_amount_details = $tx_value['minerFee'];
                $eth_miner_amount_details = $tx_value['ethMinerFee'];

                $wallet_type = strtolower($tx_value['type']);
                $miner_fee_wallet_type = strtolower($miner_amount_details['type']);
                $service_charge_wallet_type = strtolower($service_charge_details['type']);
                $actual_miner_fee_wallet_type = $eth_miner_amount_details['type'] ? strtolower($eth_miner_amount_details['type']) : $miner_fee_wallet_type ;

                $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
                $decimal_places = $decimal_place_setting["decimal_places"];
                
                $final_amount_receive = $tx_value['amount'] ? $tx_value['amount'] : 0;
                $final_service_charge = $service_charge_details['amount'] ? $service_charge_details['amount'] : 0;
                $final_miner_fee = $miner_amount_details['amount'] ? $miner_amount_details['amount'] : 0 ;
                $final_actual_miner_fee = $eth_miner_amount_details['amount'] ? $eth_miner_amount_details['amount'] : $final_miner_fee;

                $exchangeRate = $miner_amount_details['exchangeRate'];
                $miner_fee_exchange_rate = $eth_miner_amount_details['exchangeRate'] ? $eth_miner_amount_details['exchangeRate'] : $miner_amount_details['exchangeRate'];


                if($gw_type=="BC") {
                    $final_amount = $final_amount_receive;
                } else {
                    $final_amount = bcsub($final_amount_receive, $final_service_charge, $decimal_places);
                    $final_amount = bcsub($final_amount, $final_miner_fee, $decimal_places);
                }


                if($gw_type=="BC") {
                    $db->where("gw_type", $gw_type);
                } else {
                    $db->where("gw_type", array("", $gw_type), "IN");
                }
                $db->where("reference_id", $reference_id);
                $history_result = $db->getOne("xun_crypto_history");

                $db->where("reference_id", $reference_id);
                $payment_details_result = $db->getOne('xun_payment_details');
                
                $transaction_date = date("Y-m-d H:i:s");

                if( ($gw_type=="PG" && $status == 'received' && $skip_pg_callback == 0) || ($gw_type=="BC" && $status=="success" && $skip_pg_callback == 0) ) {

                    if($gw_type =="PG"){
                        $db->where("transaction_id", $received_tx_id);
                        $db->where('reference_id', $reference_id);
                    }
                    else{
                        $db->where("transaction_id", $transaction_id);

                    }
                    
                    $db->where("transaction_type", ($gw_type=="BC" ? "blockchain" : "payment_gateway"));
                    $fundInDetail = $db->getOne("xun_payment_gateway_fund_in");

                    if($fundInDetail) {

                        $update_fund_in_data = array(
                            "reference_id" => $reference_id,
                            "business_id" => $business_id,
                            "sender_address" => $sender_external_address ? $sender_external_address : $sender_internal_address,
                            "receiver_address" => $address,
                            "amount" => $final_amount,
                            "amount_receive" => $final_amount_receive,
                            "transaction_fee" => $final_service_charge,
                            "miner_fee" => $final_miner_fee,
                            "exchange_rate" => $exchangeRate,
                            "miner_fee_exchange_rate" => $miner_fee_exchange_rate,
                            "wallet_type" => $wallet_type,
                            "status" => $status == 'received' ? 'success' : $status,
                            "miner_fee_wallet_type" => $miner_fee_wallet_type
                        );

                        $db->where("id", $fundInDetail['id']);
                        $db->update('xun_payment_gateway_fund_in', $update_fund_in_data);

                    } else {

                        $insert_fund_in_data = array(
                            // "transaction_id" => $received_tx_id,
                            "reference_id" => $reference_id,
                            "business_id" => $business_id,
                            "sender_address" => $sender_external_address ? $sender_external_address : $sender_internal_address,
                            "receiver_address" => $address,
                            "amount" => $final_amount,
                            "amount_receive" => $final_amount_receive,
                            "transaction_fee" => $final_service_charge,
                            "miner_fee" => $final_miner_fee,
                            "exchange_rate" => $exchangeRate,
                            "miner_fee_exchange_rate" => $miner_fee_exchange_rate,
                            "wallet_type" => $wallet_type,
                            "miner_fee_wallet_type" => $miner_fee_wallet_type,
                            "type" => "fund_in",

                            // wentin test //
                            "transaction_target" => $target,
                            "transaction_id" => $received_tx_id ?: $transaction_hash,

                            "transaction_type" => ($gw_type=="BC" ? "blockchain" : "payment_gateway"),
                            "status" => $status == 'received' ? 'success' : $status,
                            "created_at" => date("Y-m-d H:i:s")
                        );

                        $inserted = $db->insert('xun_payment_gateway_fund_in', $insert_fund_in_data);

                    }

                }

                if(!$payment_details_result && $skip_pg_callback == 0){

                    $payment_tx_details_result = $xunPayment->getPaymentTxDetailsByAddress($address);
                    $payment_tx_id = $payment_tx_details_result['payment_tx_id'];
                    $payment_method_id = $payment_tx_details_result['payment_method_id'];

                    $db->where('id', $payment_tx_id);
                    $fiat_currency_id = $db->getValue('xun_payment_transaction', 'fiat_currency_id');
                    
                    while(true){
                        $payment_id = "P".time();
                        $db->where('payment_id', $payment_id);
                        $check_payment_details = $db->getOne('xun_payment_details');

                        if(!$check_payment_details){
                            break;
                        }

                    }

                    $transactionObj->paymentID = $payment_id;
                    $transactionObj->paymentTxID = $payment_tx_id;
                    $transactionObj->paymentMethodID = $payment_method_id;
                    $transactionObj->status = $status;
                    $transactionObj->senderInternalAddress = $sender_internal_address ? $sender_internal_address : '';
                    $transactionObj->senderExternalAddress = $sender_external_address ? $sender_external_address : '';
                    $transactionObj->recipientInternalAddress = $recipient_internal_address ? $recipient_internal_address : '';
                    $transactionObj->recipientExternalAddress = $recipient_external_address ? $recipient_external_address : '';
                    $transactionObj->pgAddress = $address ? $address : '';
                    $transactionObj->senderUserID = '';
                    $transactionObj->recipientUserID = $business_id;
                    $transactionObj->walletType = $wallet_type;
                    $transactionObj->amount = $final_amount_receive;
                    $transactionObj->referenceID = $reference_id;
                    $transactionObj->createdAt = $date;
                    $transactionObj->updatedAt = $date;
                    $transactionObj->fee = $padded_fee;
                    $transactionObj->feeWalletType = $miner_fee_wallet_type;

                    if($fiat_currency_id){
                        $currency_rate_data = $xunCurrency->get_currency_rate(array($fiat_currency_id));    
                        $fiat_currency_rate = $currency_rate_data[$fiat_currency_id];
                        $txExchangeRate = bcmul($exchangeRate, $fiat_currency_rate, 8);

                    }
                    else{
                        $txExchangeRate = $exchangeRate;
                    }

                    $transactionObj->txExchangeRate = $txExchangeRate;
                    $transactionObj->fiatCurrencyID = $fiat_currency_id ?  $fiat_currency_id : 'usd';

                    $payment_details_id = $xunPayment->insert_payment_details($transactionObj);

                    if(!$payment_details_id){
                        $log->write("\n " . $date . " function:transaction_callback - Insert Payment Details. Error:" . $db->getLastError());

                    }

                    $xunWallet = new XunWallet($db);
                    
                    // check if it's pg address
                    $db->where("crypto_address", $address);
                    $xun_crypto_address = $db->getOne("xun_crypto_address", "id, crypto_address");

                    if($xun_crypto_address){
                        $address_type = "payment_gateway";
                    }else{
                        $address_type = "external_transfer";
                    }
                    
                    $db->where('transaction_hash', $receivedTxID);
                    $wallet_transaction_result = $db->getOne('xun_wallet_transaction', 'id, transaction_history_table, transaction_history_id');

                    if($wallet_transaction_result){
                        $wallet_tx_id = $wallet_transaction_result['id'];
                        $transaction_history_table = $wallet_transaction_result['transaction_history_table'];
                        $transaction_history_id = $wallet_transaction_result['transaction_history_id'];

                        $db->where('id', $transaction_history_id);
                        $transaction_history_result = $db->getOne($transaction_history_table);

                        if($transaction_history_result){
                            $updateData = array(
                                "status" =>  $status == "success" || $status == 'received' || $status == 'pending' ? "success" : $status
                            );
                            $db->where('id', $transaction_history_id);
                            $db->update($transaction_history_table, $updateData);
                        }
                        else{
                            $transactionType = "fund_in";
                            $txHistoryObj->paymentDetailsID = $payment_details_id;
                            $txHistoryObj->status = $status == "success" || $status == 'received' || $status == 'pending' ? "success" : $status;
                            $txHistoryObj->transactionID = $received_tx_id;
                            $txHistoryObj->transactionToken = "";
                            $txHistoryObj->senderAddress = $sender_external_address ? $sender_external_address : $sender_internal_address;
                            $txHistoryObj->recipientAddress = $gw_type == 'PG' ? $address : $recipient_external_address;
                            $txHistoryObj->senderUserID = '';
                            $txHistoryObj->recipientUserID = $business_id;
                            $txHistoryObj->walletType = $wallet_type;
                            $txHistoryObj->amount = $final_amount_receive;
                            $txHistoryObj->transactionType = $transactionType;
                            $txHistoryObj->referenceID = '';
                            $txHistoryObj->createdAt = $date;
                            $txHistoryObj->updatedAt = $date;
                            $txHistoryObj->fee = $final_miner_fee;
                            $txHistoryObj->feeWalletType = $miner_fee_wallet_type;
                            $txHistoryObj->exchangeRate = $exchangeRate;
                            $txHistoryObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                            $txHistoryObj->type = 'in';
                            $txHistoryObj->gatewayType = $gw_type;
        
        
                            $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
        
                            $transaction_history_id = $transaction_history_result['transaction_history_id'];
                            $transaction_history_table = $transaction_history_result['table_name'];
        
                            $updateWalletTx = array(
                                "transaction_history_id" => $transaction_history_id,
                                "transaction_history_table" => $transaction_history_table
                            );
                            $xunWallet->updateWalletTransaction($wallet_tx_id, $updateWalletTx);
                        }

                        $updateWalletTxStatus = array(
                            "status" => $status == "success" || $status == 'received' || $status == 'pending' ? "completed" : $status
                        );


                        $xunWallet->updateWalletTransaction($wallet_tx_id, $updateWalletTxStatus);

                    }
                    else{
                        $transactionObj->status = $status == 'success' || $status == 'received' || $status == 'pending' ?  'completed' : $status;
                        $transactionObj->transactionHash = $received_tx_id;
                        $transactionObj->transactionToken = "";
                        $transactionObj->senderAddress = $sender_external_address ? $sender_external_address : $sender_internal_address;
                        $transactionObj->recipientAddress = $gw_type == 'PG' ? $address : $recipient_external_address;
                        $transactionObj->userID = $business_id;
                        $transactionObj->senderUserID = '';
                        $transactionObj->recipientUserID = $business_id;
                        $transactionObj->walletType = $wallet_type;
                        $transactionObj->amount = $final_amount_receive;
                        $transactionObj->addressType = $address_type;
                        $transactionObj->transactionType = "receive";
                        $transactionObj->escrow = 0;
                        $transactionObj->referenceID = '';
                        $transactionObj->escrowContractAddress = '';
                        $transactionObj->createdAt = $date;
                        $transactionObj->updatedAt = $date;
                        $transactionObj->expiresAt = '';
                        $transactionObj->fee = '';
                        $transactionObj->feeUnit = '';
                        $transactionObj->bcReferenceID = $bc_reference_id;
    
                        $wallet_tx_id = $xunWallet->insertUserWalletTransaction($transactionObj);
    
                        $transactionType = "fund_in";
                        $txHistoryObj->paymentDetailsID = $payment_details_id;
                        $txHistoryObj->status =  $status == "success" || $status == 'received' || $status == 'pending' ? "success" : $status;
                        $txHistoryObj->transactionID = $received_tx_id;
                        $txHistoryObj->transactionToken = "";
                        $txHistoryObj->senderAddress = $sender_external_address ? $sender_external_address : $sender_internal_address;
                        $txHistoryObj->recipientAddress = $gw_type == 'PG' ? $address : $recipient_external_address;
                        $txHistoryObj->senderUserID = '';
                        $txHistoryObj->recipientUserID = $business_id;
                        $txHistoryObj->walletType = $wallet_type;
                        $txHistoryObj->amount = $final_amount_receive;
                        $txHistoryObj->transactionType = $transactionType;
                        $txHistoryObj->referenceID = '';
                        $txHistoryObj->createdAt = $date;
                        $txHistoryObj->updatedAt = $date;
                        $txHistoryObj->fee = $final_miner_fee;
                        $txHistoryObj->feeWalletType = $miner_fee_wallet_type;
                        $txHistoryObj->exchangeRate = $exchangeRate;
                        $txHistoryObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                        $txHistoryObj->type = 'in';
                        $txHistoryObj->gatewayType = $gw_type;
    
    
                        $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
    
                        if(!$transaction_history_result){
                            $log->write("\n " . $date . " function:transaction_callback - Insert Payment Transaction History. Error:" . $db->getLastError());
                        }
    
                        $transaction_history_id = $transaction_history_result['transaction_history_id'];
                        $fund_in_transaction_history_table = $transaction_history_result['table_name'];
    
                        $updateWalletTx = array(
                            "transaction_history_table" => $fund_in_transaction_history_table,
                            "transaction_history_id" => $transaction_history_id,
                        );
    
                        $xunWallet->updateWalletTransaction($wallet_tx_id, $updateWalletTx);
    
                        $update_data = array(
                            "fund_in_table" => $fund_in_transaction_history_table,
                            "fund_in_id" => $transaction_history_id
                        );
                        $db->where('id', $payment_details_id);
                        $db->update('xun_payment_details', $update_data);
                    }
                    

                }
                else if($payment_details_result && $skip_pg_callback == 0 && $gw_type == 'PG'){

                    $payment_details_id = $payment_details_result['id'];
                    $fund_out_table = $payment_details_result['fund_out_table'];
                    $fund_out_id = $payment_details_result['fund_out_id'];
                    $payment_id = $payment_details_result['payment_id'];

                    if($status == 'pending' || $status == 'success'){
                        $update_payment_details_data = array(
                            "recipient_internal_address" => $recipient_internal_address,
                            "recipient_external_address" => $recipient_external_address,
                            "pg_address" => $address,
                            "service_charge_amount" => $final_service_charge,
                            "service_charge_wallet_type" => $service_charge_wallet_type,
                            "amount" => $final_amount,
                            "fee_amount" => $final_miner_fee,
                            "fee_wallet_type" => $miner_fee_wallet_type,
                            "actual_fee_amount" => $final_actual_miner_fee,
                            "actual_fee_wallet_type" => $eth_miner_fee_wallet_type,
                            "status" => $status == "success" || $status == 'received' || $status == 'pending' ? "success" : $status,
                        );

                        $db->where('id', $payment_details_id);
                        $db->update('xun_payment_details', $update_payment_details_data);

                        if($fund_out_table){
                         
                            $update_fund_out_transaction_data  = array(
                                "status" => $status == "success" || $status == 'received' || $status == 'pending' ? "success" : $status,
                                "amount" => $final_amount_receive,
                                "fee_amount" => $final_miner_fee,
                                "fee_wallet_type" => $miner_fee_wallet_type,
                                "updated_at" => date("Y-m-d H:i:s")
                            );

                            $db->where('id', $fund_out_id);
                            $db->update($db->escape($fund_out_table), $update_fund_out_transaction_data);
                        }
                        else{
                            $transactionType = "fund_out";
                            $transactionObj->paymentDetailsID = $payment_details_id;
                            $transactionObj->status = $status == "success" || $status == 'received' || $status == 'pending' ? "success" : $status;
                            $transactionObj->transactionID = $transaction_id;
                            $transactionObj->transactionToken = "";
                            $transactionObj->senderAddress = $address ? $address : '';
                            $transactionObj->recipientAddress = $recipient_external_address ? $recipient_external_address : $recipient_internal_address;
                            $transactionObj->senderUserID = $business_id;
                            $transactionObj->recipientUserID = '';
                            $transactionObj->walletType = $wallet_type;
                            $transactionObj->amount = $final_amount_receive;
                            $transactionObj->transactionType = $transactionType;
                            $transactionObj->referenceID = '';
                            $transactionObj->createdAt = $date;
                            $transactionObj->updatedAt = $date;
                            $transactionObj->serviceChargeAmount = $final_service_charge;
                            $transactionObj->serviceChargeWalletType = $service_charge_wallet_type;
                            $transactionObj->fee = $final_miner_fee;
                            $transactionObj->feeWalletType = $miner_fee_wallet_type;
                            $transactionObj->actualFeeAmount = $final_actual_miner_fee;
                            $transactionObj->actualFeeWalletType = $eth_miner_fee_wallet_type;
                            $transactionObj->exchangeRate = $exchangeRate;
                            $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                            $transactionObj->type = 'out';
                            $transactionObj->gatewayType = $gw_type;
                            $transactionObj->isInternal  = $transaction_type == 'internal' ? 1 : 0;
        
        
                            $transaction_history_result = $xunPayment->insert_payment_transaction_history($transactionObj);

                            if(!$transaction_history_result){
                                $log->write("\n " . $date . " function:transaction_callback - Insert Payment Transaction History. Error:" . $db->getLastError());
                            }
        
                            $fund_out_transaction_history_id = $transaction_history_result['transaction_history_id'];
                            $fund_out_table = $transaction_history_result['table_name'];

                            $update_payment_details_fund_out = array(
                                "fund_out_table" =>  $fund_out_table,
                                "fund_out_id" => $fund_out_transaction_history_id,
                                "service_charge_amount" => $final_service_charge,
                                "service_charge_wallet_type" => $service_charge_wallet_type,
                                "amount" => $final_amount,
                                "actual_fee_amount" => $final_actual_miner_fee,
                                "actual_fee_wallet_type" => $eth_miner_fee_wallet_type,
                                "status" => $status == "success" || $status == 'received' || $status == 'pending' ? "success" : $status
                            );
                            $db->where('id', $payment_details_id);
                            $db->update('xun_payment_details', $update_payment_details_fund_out);
        
                        }
                    }


                }
                else if($payment_details_result){
                    
                    $payment_details_id = $payment_details_result['id'];
                    $fund_in_table = $payment_details_result['fund_in_table'];
                    $fund_in_id = $payment_details_result['fund_in_id'];
                    $payment_id = $payment_details_result['payment_id'];

                    $updateData = array(
                        "status" => $status == "success" || $status == 'received' || $status == 'pending' ? "success" : $status
                    );

                    $db->where('id', $payment_details_id);
                    $db->update('xun_payment_details', $updateData);

                    $db->where('id', $fund_in_id);
                    $db->update($fund_in_table, $updateData);

                }

                if(!$history_result && $skip_pg_callback == 0){
                    $insertData = array(
                        "received_transaction_id" => $received_tx_id,
                        "reference_id" => $reference_id,
                        "transaction_id" => $transaction_id,
                        "business_id" => $business_id,
                        "sender_internal" => $sender_internal_address,
                        "sender_external" => $sender_external_address,
                        "recipient_internal" => $recipient_internal_address,
                        "recipient_external" => $recipient_external_address,
                        "amount" => $final_amount,
                        "amount_receive" => $final_amount_receive,
                        "transaction_fee" => $final_service_charge,
                        "miner_fee" => $final_miner_fee,
                        "tx_fee_wallet_type" => $service_charge_wallet_type,
                        "miner_fee_wallet_type" => $miner_fee_wallet_type,
                        "exchange_rate" => $exchangeRate,
                        "miner_fee_exchange_rate" => $miner_fee_exchange_rate,
                        "actual_miner_fee_amount" => $final_actual_miner_fee,
                        "actual_miner_fee_wallet_type" => $actual_miner_fee_wallet_type,
                        "status" => $status,
                        "transaction_date" => $transaction_date,
                        "transaction_url" => $transaction_url,
                        "wallet_type" => $wallet_type,
                        "type" => $pg_address_type,
                        "address" => $address,
                        "created_at" => date("Y-m-d H:i:s"),
                        "gw_type" => $gw_type
                    );

                    // $fields = array("reference_id", "transaction_id", "business_id", "amount", "amount_receive", "transaction_fee", "status", "transaction_date", "transaction_url", "wallet_type", "created_at");
                    // $values = array($reference_id, $transaction_id, $business_id, $amount, $amount_receive, $service_charge, $status, $transaction_date, $transaction_url, $wallet_type, date("Y-m-d H:i:s"));
    
                    // $insertData = array_combine($fields, $values);
                    $row_id = $db->insert("xun_crypto_history", $insertData);  
    
                }else{
                    $fields = array("received_transaction_id", "transaction_id", "business_id", "sender_internal" , "sender_external" , "recipient_internal" , "recipient_external", "amount", "amount_receive", "transaction_fee", "miner_fee", "status", "transaction_date", "transaction_url", "wallet_type", "tx_fee_wallet_type", "miner_fee_wallet_type", "exchange_rate" , "miner_fee_exchange_rate", "actual_miner_fee_amount", "actual_miner_fee_wallet_type", "address", "withdrawal_id", "updated_at");

                    $values = array($received_tx_id, $transaction_id, $business_id, $sender_internal_address, $sender_external_address, $recipient_internal_address, $recipient_external_address, $final_amount, $final_amount_receive, $final_service_charge, $final_miner_fee, $status, $transaction_date, $transaction_url, $wallet_type, $service_charge_wallet_type, $miner_fee_wallet_type, $exchangeRate, $miner_fee_exchange_rate, $final_actual_miner_fee, $actual_miner_fee_wallet_type, $address,$withdrawal_id, date("Y-m-d H:i:s"));
    
                    $updateData = array_combine($fields, $values);

                    $row_id = $history_result["id"];
                    $db->where("id", $row_id);
                    $db->update("xun_crypto_history", $updateData);
                }

            }
           

            // check if user had set `allowSwitchCurrency`
            // skip pg callback and send autoswap callback instead
            // $db->where('user_id',$business_id);
            // $db->where('name', 'allowSwitchCurrency');
            // $isAllowSwitchCurrency = $db->getValue('xun_user_setting', 'value');
            // if ($isAllowSwitchCurrency) {
            //     $log->write("\n".date('Y-m-d')." Debug - BusinessID ".$business_id." had set allowSwitchCurrency to true. Skip PG callback.");
            //     $skip_pg_callback = 1;
            // }

            // FORWARDING CALLBACK TO MERCHANT
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            if($business_result && $skip_pg_callback == 0){
                
                if($business_result["pg_callback_url"]){
                    $log->write("\n".date('Y-m-d')." Message - PaymentGateway callback triggered.");

                    unset($params["transactionType"]);
                    unset($params['gw_type']);
                    unset($params['ip']);

                    if($payment_id){
                        $db->where('payment_id', $payment_id);
                        $payment_details_data = $db->getOne('xun_payment_details', 'id, payment_tx_id, tx_exchange_rate, fiat_currency_id');
            
                        $tx_exchange_rate = $payment_details_data['tx_exchange_rate'];
                        $fiat_currency_id = $payment_details_data['fiat_currency_id'];
                        $payment_tx_id = $payment_details_data['payment_tx_id'];

                        $db->where('id', $payment_tx_id);
                        $payment_tx_data = $db->getOne('xun_payment_transaction', 'id, transaction_token');

                        $pg_transaction_token = $payment_tx_data['transaction_token'];

                        $db->where('transaction_token', $pg_transaction_token);
                        $pg_payment_tx_data = $db->getOne('xun_payment_gateway_payment_transaction'); 
            
                    }
                    else{
                        $db->where('address', $address);
                        $pg_payment_tx_data = $db->getOne('xun_payment_gateway_payment_transaction');
                    }

                 
                    if($pg_payment_tx_data){
                        $client_reference_id = $pg_payment_tx_data['reference_id'];
                        $pg_transaction_token = $pg_payment_tx_data['transaction_token'];
                    }
                    else{
                        $client_reference_id = 0;
                        $pg_transaction_token = "";
                    }
                    
                    $params['clientReferenceID'] = $client_reference_id ? $client_reference_id : '';
                    $params['transactionToken'] = $pg_transaction_token ? $pg_transaction_token : '';
                    $params['paymentTxID'] = $payment_id ? $payment_id : '';

                    // $cryptoResult = $post->curl_crypto("paymentGatewayCallback", $params, 0, $business_result["pg_callback_url"]);


                    $fiat_tx_amount = bcmul($final_amount_receive, $tx_exchange_rate,2);
                    $params['fiatDetails'] = array(
                        "amount" => $fiat_tx_amount ? $fiat_tx_amount : '',
                        "unit" => strtoupper($fiat_currency_id) ? strtoupper($fiat_currency_id) : '',
                        "exchangeRate" => $tx_exchange_rate ? $tx_exchange_rate : '',
                    );
                    $params['transactionType'] = 'payment_gateway';

                    $db->where('name', 'callbackForwarderURL');
                    $db->where('value','','!=');
                    $db->where('user_id', $business_id);
                    $forwarderDetail = $db->getOne('xun_user_setting');

                    if($forwarderDetail) {
                        
                        $callbackurl = $forwarderDetail['value'];

                        $curl_params = array('url'=>$business_result["pg_callback_url"],
                                            'data'=>array('command'=>'paymentGatewayCallback',
                                                        'params'=>$params));

                    } else {

                        $callbackurl = $business_result["pg_callback_url"];

                        $curl_params = array(
                            "command" => "paymentGatewayCallback",
                            "params" => $params
                        );

                    }

                    

                    $curl_header[] = "Content-Type: application/json";
                    $cryptoResult = $post->curl_post($callbackurl, $curl_params, 0, 1, $curl_header);


                    //keep developer log
                    $webservice->developerOutgoingWebService($business_id, "paymentGatewayCallback", $callbackurl, json_encode($curl_params), json_encode($cryptoResult) );


                    $jsonResult = json_decode($cryptoResult, true);
                    if ($jsonResult !== NULL || !is_array($cryptoResult) ){
                        $cryptoResult = $jsonResult;
                    }

                    //return null
                    if (!isset($cryptoResult)){
                        $callbackError = true;
                    } else if ($cryptoResult["status"] != 'ok'){
                        $callbackError = true;
                    }

                    //Send notification
                    if ($callbackError){
                        $noti_business_name = $business_result['name'];
                        $noti_transaction_id = $transaction_id;
                        $noti_callback_url = $business_result["pg_callback_url"];
                        $noti_created_at = date("Y-m-d H:i:s");
                        $noti_crypto_result = ($cryptoResult == null) ? 'null' : (gettype($cryptoResult) == 'array') ? json_encode($cryptoResult, JSON_PRETTY_PRINT) : $cryptoResult; 

                        $notificationMessage = "Business Name: {$noti_business_name}\nTransaction ID: {$noti_transaction_id}\nCallback URL: {$noti_callback_url}\nCreated At: {$noti_created_at}\nCallback Return: {$noti_crypto_result}";

                        $notificationParams = array(
                            "tag"   => "PG Callback Error",
                            "message" => $notificationMessage
                        );

                        $general->send_thenux_notification($notificationParams, "thenux_issues");   
                    }
                }
                
            }
            
            // AUTOSWAP CALLBACK TO MERCHANT
            if ($business_result && $auto_swap_callback == 1) {
                if($business_result["pg_callback_url"]){
                    $log->write("\n".date('Y-m-d')." Message - Autoswap callback triggered.");

                    // get payment id and transaction token
                    $db->where('address', $address);
                    $pgPaymentTransactionID = $db->getValue('xun_payment_method', 'payment_tx_id');
                    $db->where('id', $pgPaymentTransactionID);
                    $pgTransactionToken = $db->getValue('xun_payment_transaction', 'transaction_token');
                    $db->where('transaction_token', $pgTransactionToken);
                    $pgPaymentID = $db->getValue('xun_payment_gateway_payment_transaction', 'payment_id');

                    $log->write("\n".date('Y-m-d')." Debug - pgid & token ".$pgTransactionToken." ".$pgPaymentID);

                    // get transaction details
                    $transactionHistoryTable = 'xun_payment_transaction_history_'.date('Ymd');
                    $db->where('transaction_id', $received_tx_id);
                    $pgPaymentDetailID = $db->getValue($transactionHistoryTable, 'payment_details_id');
                    $log->write("\n".date('Y-m-d')." Debug - pgDetailsID ".$pgPaymentDetailID);
                    $db->where('id', $pgPaymentDetailID);
                    $pgPaymentDetail = $db->getOne('xun_payment_details');
                    $log->write("\n".date('Y-m-d')." Debug - pgPaymentDetails ".json_encode($pgPaymentDetail));
                    
                    // get fiat currency &  exchange rate
                    $db->where('id', $pgPaymentTransactionID);
                    $fiatCurrencyResult = $db->getOne('xun_payment_transaction', 'fiat_currency_id, fiat_currency_exchange_rate');
                    $log->write("\n".date('Y-m-d')." Debug - fiat currenct result ".json_encode($fiatCurrencyResult));
                    
                    // get fundin transaction history
                    $db->where('id', $pgPaymentDetail['fund_in_id']);
                    $fundInDetailHistory = $db->getOne($pgPaymentDetail['fund_in_table']);
                    $log->write("\n".date('Y-m-d')." Debug - fundInDetailHistory ".json_encode($fundInDetailHistory));
                    
                    // get swap details
                    $fromWalletType = '';
                    $fromSymbol = '';
                    $toWalletType = '';
                    $toSymbol = '';
                    $log->write("\n".date('Y-m-d')." Debug - swapResult ".json_encode($swapResult));
                    if ($swapResult) {
                        if ($swapResult['code'] == 1) {
                            $fromWalletType = $swapResult['data']['fromWalletType'] ? $swapResult['data']['fromWalletType'] : '';
                            $fromSymbol = $swapResult['data']['fromSymbol'] ? $swapResult['data']['fromSymbol'] : '';
                            $toWalletType = $swapResult['data']['toWalletType'] ? $swapResult['data']['toWalletType'] : '';
                            $toSymbol = $swapResult['data']['toSymbol'] ? $swapResult['data']['toSymbol'] : '';
                        }
                    } else {
                        $db->where('id', $pgPaymentTransactionID);
                        $toWalletType = $db->getValue('xun_payment_transaction', 'wallet_type');
                        $log->write("\n".date('Y-m-d')." Debug - xun_payment_method ".json_encode($fromWalletType));

                        $db->where('currency_id', $toWalletType, 'LIKE');
                        $toSymbol = $db->getValue('xun_marketplace_currencies','symbol');
                        $toSymbol = strtoupper($toSymbol);
                        $log->write("\n".date('Y-m-d')." Debug - xun_marketplace_currencies ".json_encode($fromSymbol));
                    }

                    // build callback data
                    $paymentID = $pgPaymentID ? $pgPaymentID : '';
                    $transactionToken = $pgTransactionToken ? $pgTransactionToken : '';
                    if ($autoSwapPendingStatus == 'received') {
                        $swapStatus = $autoSwapPendingStatus;
                    } else {
                        $swapStatus = ($auto_swap_fail_reason == '') ? 'pending' : 'skip';
                    }
                    $fromType = $credit_arr['destination']['type'] ? $credit_arr['destination']['type'] : '';
                    $fromUnit = $credit_arr['destination']['unit'] ? $credit_arr['destination']['unit'] : '';
                    $pgReferenceID = $pgPaymentDetail['reference_id'] ? $pgPaymentDetail['reference_id'] : '';
                    $pgSender = $pgPaymentDetail['sender_external_address'] ? $pgPaymentDetail['sender_external_address'] : '';
                    $pgRecipient = $pgPaymentDetail['recipient_external_address'] ? $pgPaymentDetail['recipient_external_address'] : '';
                    $pgTxnID = $fundInDetailHistory['transaction_id'] ? $fundInDetailHistory['transaction_id'] : '';
                    $pgAmount = $fundInDetailHistory['amount'] ? $fundInDetailHistory['amount'] : '';
                    $pgExchangeRate = $fundInDetailHistory['exchange_rate'] ? $fundInDetailHistory['exchange_rate'] : ''; // USD
                    $pgExchangeRate = bcmul($pgExchangeRate, $fiatCurrencyResult['fiat_currency_exchange_rate'], 8); // Requested Currency
                    $pgCurrencyId = $fiatCurrencyResult['fiat_currency_id'] ? strtoupper($fiatCurrencyResult['fiat_currency_id']) : "USD";
                    $pgFiatAmount = bcmul($pgAmount, $pgExchangeRate, 2);
                    $fromFiatAmount = $credit_arr['destination']['amount'] ? bcmul($credit_arr['destination']['amount'], $pgExchangeRate, 2) : '0';

                    $autoswapCallbackData = array(
                        'referenceID' => $pgReferenceID,
                        'paymentTxID' => $paymentID,
                        'transactionToken' => $transactionToken,
                        'transactionDate' => date('Y-m-d H:i:s'),
                        'status' => $swapStatus,
                        'statusMsg' => $auto_swap_fail_reason,
                        'receivedTxDetails' => array(
                            'txID' => $received_tx_id,
                            'address' => $address,
                            'amount' => explode(' ', $amount)[0],
                            'unit' => $fromUnit,
                            'type' => $fromType,
                            'fiatAmount' => $fromFiatAmount,
                            'fiatUnit' => $pgCurrencyId,
                            'exchangeRate' => $pgExchangeRate,
                        ),
                        'swapTxDetails' => array(
                            'txID' => '',
                            'address' => '',
                            'amount' => '',
                            'unit' => $toSymbol,
                            'type' => $toWalletType,
                            'fiatAmount' => '',
                            'fiatUnit' => $pgCurrencyId,
                            'exchangeRate' => '',
                        ),
                        'paymentTxDetails' => array(
                            'txID' => $pgTxnID,
                            'sender' => $pgSender,
                            'recipient' => $pgRecipient,
                            'address' => $address,
                            'amount' => $pgAmount,
                            'unit' => $fromUnit,
                            'type' => $fromType,
                            'fiatAmount' => $pgFiatAmount,
                            'fiatUnit' => $pgCurrencyId,
                            'exchangeRate' => $pgExchangeRate
                        ),
                    );
                    // get callback url
                    $db->where('user_id', $business_id);
                    $db->where('name', 'businessCallbackURL');
                    $recipientCallbackURL = $db->getValue('xun_user_setting', 'value');
                    $log->write("\n".date('Y-m-d')." Message - callback URL: ".$recipientCallbackURL );

                    // callback
                    $callbackurl = $recipientCallbackURL;
                    $curl_header[] = "Content-Type: application/json";
                    $curl_params = array(
                        "command" => "autoSwapCallback",
                        "params" => $autoswapCallbackData
                    );

                    $log->write("\n".date('Y-m-d')." Debug - Autoswap callback params ".json_encode($curl_params));
                    $cryptoResult = $post->curl_post($callbackurl, $curl_params, 0, 1, $curl_header);
                    $log->write("\n".date('Y-m-d')." Debug - Autoswap callback result ".json_encode($cryptoResult));

                    $webservice->developerOutgoingWebService($business_id, "autoSwapCallback", $callbackurl, json_encode($curl_params), json_encode($cryptoResult) );

                    $jsonResult = json_decode($cryptoResult, true);
                    if ($jsonResult !== NULL || !is_array($cryptoResult) ){
                        $cryptoResult = $jsonResult;
                    }

                    //return null
                    if (!isset($cryptoResult)){
                        $callbackError = true;
                    } else if ($cryptoResult["status"] != 'ok'){
                        $callbackError = true;
                    }

                    //Send notification
                    if ($callbackError){
                        $noti_business_name = $business_result['name'];
                        $noti_transaction_id = $transaction_id;
                        $noti_callback_url = $business_result["pg_callback_url"];
                        $noti_created_at = date("Y-m-d H:i:s");
                        $noti_crypto_result = ($cryptoResult == null) ? 'null' : (gettype($cryptoResult) == 'array') ? json_encode($cryptoResult, JSON_PRETTY_PRINT) : $cryptoResult; 

                        $notificationMessage = "Business Name: {$noti_business_name}\nTransaction ID: {$noti_transaction_id}\nCallback URL: {$noti_callback_url}\nCreated At: {$noti_created_at}\nCallback Return: {$noti_crypto_result}";

                        $notificationParams = array(
                            "tag"   => "PG Callback Error",
                            "message" => $notificationMessage
                        );

                        $general->send_thenux_notification($notificationParams, "thenux_issues");   
                    }
                }
            }

            $db->where("id", $business_id);
            $xun_business_user = $db->getOne("xun_user");
            $business_name = $business_result["name"];
            $xun_business_user["nickname"] = $business_name;
            $xun_business_user["username"] = $business_id;

            //  send crypto callback
            if($pg_address_type == "in"){
                // get sender's user details
                $xun_sender = $this->get_xun_user_by_crypto_address($sender_address);
                // return array("code" => 1, "xun_user" => $xun_user, "user_address_data" => $cryptoResult);
                $xun_sender_user = $xun_sender["code"] === 1 ? $xun_sender["xun_user"] : null;
                $xun_recipient_user = $xun_business_user;
                $type = "receive";

            }else{
                // get recipient's user details
                $xun_recipient = $this->get_xun_user_by_crypto_address($recipient_address);
                $xun_recipient_user = $xun_recipient["code"] === 1 ? $xun_recipient["xun_user"] : null;
                $xun_sender_user = $xun_business_user;
                $type = 'send';
            }

            $exchange_rate_arr = $exchange_rate == '' ? array("USD" => "0.00") : $exchange_rate;

            if($gw_type=="BC") {

                $this->merchant_pg_from_bc_callback_handler($ori_params, $row_id);

            } else {

                $newParams["target_username"] = $target_username;
                $newParams["account_address"] = $sender_address;
                $newParams["address"] = $address;
                $newParams["recipient"] = $recipient_address;
                $newParams["type"] = $type;
                $newParams["wallet_type"] = $wallet_type;
                $newParams["target"] = $transaction_type;
                $newParams["amount"]  = $amount_details["amount"];
                $newParams["amount_receive"] = $amount_receive_details["amount"];
                $newParams["amount_receive_unit"] = $amount_receive_details["unit"];
                $newParams["service_charge"] = $service_charge_details["amount"];
                $newParams["service_charge_unit"] = $service_charge_details["unit"];
                $newParams["transaction_id"] = $transaction_id;
                $newParams["exchange_rate"] = json_encode($exchange_rate_arr);
                $newParams["status"] = $status;
                $newParams["timestamp"] = $timestamp;
                $newParams["time"] = $transaction_date;
                $newParams["reference_id"] = $reference_id;
                $newParams["crypto_callback"] = "payment_gateway";

                $tag = "Payment Gateway Notification";
                $this->insert_business_sending_queue($newParams, $xun_sender_user, $xun_recipient_user, $tag);

                //  check if it's story pg address
                $this->story_pg_callback_handler($ori_params, $row_id);


                $ori_params['business_id']= $business_id;
                $this->merchant_pg_callback_handler($ori_params, $row_id);

            }

            $db->where('id', $business_id);
            $xun_user =  $db->getOne('xun_user');
            $nickname = $xun_user["nickname"];
            $phone_number = $xun_user["username"];
            $email = $xun_user["email"];

            $tag = "Transaction History";;
            $message .= "Username: ".$nickname."\n";
            $message .= "Email: ".$email."\n";
            $message .= "Phone number: ".$phone_number."\n";
            $message .= "Cryptocurrency type: ".$wallet_type."\n";
            // $message .= "Currency: USD\n";
            $message .= "Amount:" .$amount."\n";
            $message .= "Trx Hash: ".$transaction_id."\n";
            $message .= "Status: ".$status."\n";
            $message .= "Time: ".date("Y-m-d H:i:s")."\n";


            $xunPaymentGateway->send_nuxpay_notification($tag, $message);

            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00092') /*Callback Saved.*/, "code" => 1, "result" => $returnData);
            
        }
        
        function get_username_from_address($params){
            $db     = $this->db;
            
            $address     =   $params["address"];
            
            $db->where("address", $address);
            $address_result = $db->getOne("xun_crypto_user_address");
            
            if(!$address_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00144') /*Address Not Found.*/);
            }
            
            $user_id = $address_result["user_id"];
            $db->where("id", $user_id);
            $username = $db->getValue("xun_user", "username");

            $returnData["username"] = $username;
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00093') /*Address Returned.*/, "code" => 1, "result" => $returnData);
        }

        public function verify_user_crypto_transaction_token($params)
        {
            $db = $this->db;
            $now = date("Y-m-d H:i:s");

            $address = trim($params["address"]);
            $transaction_token = trim($params["transaction_token"]);

            $db->where("address", $address);
            $db->where("transaction_token", $transaction_token);

            $crypto_transaction = $db->getOne("xun_crypto_user_transaction_verification");
            if(!$crypto_transaction){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00145') /*Invalid transaction token.*/);
            }
            
            if($crypto_transaction["verified"] === 1){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00146') /*Transaction token has been used.*/);
            }
            
            if ($crypto_transaction["expires_at"] < $now){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00147') /*Transaction token has expired.*/);
            }

            $updateData = [];
            $updateData["updated_at"] = $now;
            $updateData["verified"] = 1;
            $db->where("id", $crypto_transaction["id"]);
            $db->update("xun_crypto_user_transaction_verification", $updateData);

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00094') /*Transaction token is valid.*/);
        }

	public function set_app_callback_url($params){

	    global $config;

            $db = $this->db;

            $username = $params["username"];

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

	    return $this->set_callback_url($params);

	}

        public function set_callback_url($params){
            
            $db     = $this->db;
            $date   = date("Y-m-d H:i:s");

            $business_id    = trim($params["business_id"]);
            $callback_url   = trim($params["callback_url"]);
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00032'));
            }

            $db->where('user_id', $business_id);
            $business_account = $db->getOne('xun_business_account');

            $account_type = $business_account['account_type'];

            if($account_type == 'basic'){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00595') /*Please upgrade to premium account to generate api key.*/, 'error_code' => -103);
            }
              
            $updateData["pg_callback_url"] = $callback_url;
            $updateData["updated_at"]      = $date;

            $db->where("id", $business_result["id"]);
            $db->update("xun_business", $updateData);
                             
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Callback URL Updated.");
            
        }

	public function get_app_callback_url($params){

	    global $config;

            $db = $this->db;

            $username = $params["username"];

            if ($username == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
            }

            $db->where("username", $username);
            $db->where("disabled", 0);

            $xun_user = $db->getOne("xun_user", "id, username, nickname");
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

	    return $this->get_callback_url($params);

	}

        public function get_callback_url($params){
        
            $db     = $this->db;
            $date   = date("Y-m-d H:i:s");            

            $business_id    = trim($params["business_id"]);
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00032'));
            }
            
            $returnData["callback_url"] = $business_result["pg_callback_url"];
                             
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Callback URL.", "result" => $returnData);
            
        }      
        
        public function get_developer_data($params) {
            $db     = $this->db;
            $time   = $params['time'];
            $userID = $params['user_id'];
            $returnData = array();

            // get data based on selected timeframe
            switch($time) {
                case "4h":
                    $startDate = date('Y-m-d', strtotime('-3 day'));
                    $period = new DatePeriod(
                        new DateTime($startDate.' 20:00:00'),
                        new DateInterval('PT4H'),
                        new DateTime(date('Y-m-d H:i:s', strtotime('today 23:59:59')))
                    );
                    $test = array(); // Debug purpose
                    $initialTimeFrame = '';
                    foreach($period as $key=>$date) {
                        if ($key == 0) {
                            $initialTimeFrame = $date->format('Y-m-d H:i:s');
                            continue;
                        }
                        $currentTimeFrame = $date->format('Y-m-d H:i:s');
                        $startInterval = $initialTimeFrame;
                        $currentDate = explode(' ', $startInterval)[0];
                        $currentTime = explode(' ', $startInterval)[1];

                        // determine endtime
                        if (strtotime($currentTime) < strtotime('04:00:00')){
                            $endTime = '03:59:59';
                        } 
                        else if (strtotime($currentTime) < strtotime('08:00:00') && strtotime($currentTime) >= strtotime('04:00:00')) {
                            $endTime = '07:59:59';
                        } 
                        else if (strtotime($currentTime) < strtotime('12:00:00') && strtotime($currentTime) >= strtotime('08:00:00')) {
                            $endTime = '11:59:59';
                        }
                        else if (strtotime($currentTime) < strtotime('16:00:00') && strtotime($currentTime) >= strtotime('12:00:00')) {
                            $endTime = '15:59:59';
                        }
                        else if (strtotime($currentTime) < strtotime('20:00:00') && strtotime($currentTime) >= strtotime('16:00:00')) {
                            $endTime = '19:59:59';
                        }
                        else {
                            $endTime = '23:59:59';
                        }
                        
                        $endInterval = $currentDate." ".$endTime;

                        $test[] = "start: ". $startInterval . " end: " . $endInterval; // Debug purpose

                        $db->where('user_id', $userID);
                        $db->where('created_at', $startInterval, '>=');
                        $db->where('created_at', $endInterval, '<=');
                        $db->groupBy('direction');
                        $results = $db->get('developer_activity_log', null, 'direction, count(*) AS activity_count');

                        // loop through all the result and build data for graph
                        // Notes : total of 3 cases
                        //          1. array is empty (in=0, out=0)
                        //          2. array consist of only 1 data (in | out)
                        //          3. array consist of 2 datas (in & out)
                        $inCount = 0;
                        $outCount = 0;

                        // case 1
                        if (count($results) == 0) {
                            // in & out count is 0
                        }
                        // case 2 & case 3
                        if (count($results) > 0) {
                            foreach($results as $result) {
                                if ($result['direction'] == 'in') {
                                    $inCount = $result['activity_count'];
                                } else {
                                    $outCount = $result['activity_count'];
                                }
                            }

                        }
                        
                        // if ($inCount != 0) {    // comment this if want to get value with zero count
                            $graphDataIn[] = array(
                                'date'  => $currentTimeFrame,
                                'value' => $inCount
                            );

                        // }
                        // if ($outCount != 0) {   // comment this if want to get value with zero count
                            $graphDataOut[] = array(
                                'date' => $currentTimeFrame,
                                'value' => $outCount
                            );
                        // }

                        // update initialTimeframe
                        $initialTimeFrame = $currentTimeFrame;
                    }


                    break;

                case "12h":
                    $startDate = date('Y-m-d', strtotime('-5 day'));
                    $period = new DatePeriod(
                        new DateTime($startDate.' 12:00:00'),
                        new DateInterval('PT12H'),
                        new DateTime(date('Y-m-d H:i:s', strtotime('today 23:59:59')))
                    );

                    // Start query for each interval
                    $test = array(); // Debug purpose
                    $initialTimeFrame = '';
                    $graphDataIn = array();
                    $graphDataOut = array();
                    foreach($period as $key=>$date) {
                        if ($key == 0) {
                            $initialTimeFrame = $date->format('Y-m-d H:i:s');
                            continue;
                        }

                        $currentTimeFrame = $date->format('Y-m-d H:i:s');
                        $startInterval = $initialTimeFrame;
                        $currentDate = explode(' ', $startInterval)[0];
                        $endTime = ($key % 2 != 0) ? '23:59:59' : '11:59:59';
                        $endInterval = $currentDate." ".$endTime;

                        $test[] = "start: ". $startInterval . " end: " . $endInterval; // Debug purpose

                        $db->where('user_id', $userID);
                        $db->where('created_at', $startInterval, '>=');
                        $db->where('created_at', $endInterval, '<=');
                        $db->groupBy('direction');
                        $results = $db->get('developer_activity_log', null, 'direction, count(*) AS activity_count');

                        // loop through all the result and build data for graph
                        // Notes : total of 3 cases
                        //          1. array is empty (in=0, out=0)
                        //          2. array consist of only 1 data (in | out)
                        //          3. array consist of 2 datas (in & out)
                        $inCount = 0;
                        $outCount = 0;

                        // case 1
                        if (count($results) == 0) {
                            // in & out count is 0
                        }
                        // case 2 & case 3
                        if (count($results) > 0) {
                            foreach($results as $result) {
                                if ($result['direction'] == 'in') {
                                    $inCount = $result['activity_count'];
                                } else {
                                    $outCount = $result['activity_count'];
                                }
                            }

                        }
                        // if ($inCount != 0) {    // comment this if want to get value with zero count
                            $graphDataIn[] = array(
                                'date'  => $currentTimeFrame,
                                'value' => $inCount
                            );
                        // }
                        // if ($outCount != 0) {   // comment this if want to get value with zero count
                            $graphDataOut[] = array(
                                'date' => $currentTimeFrame,
                                'value' => $outCount
                            );
                        // }

                        // update initial timeframe
                        $initialTimeFrame = $currentTimeFrame;
                    }
                    
                    break;

                case "24h":
                    $startDate = date('Y-m-d', strtotime('-15 day'));
                    $period = new DatePeriod(
                        new DateTime($startDate.' 00:00:00'),
                        new DateInterval('P1D'),
                        new DateTime(date('Y-m-d H:i:s', strtotime('yesterday 23:59:59')))
                    );

                    // Start query for each interval
                    $test = array(); // Debug purpose
                    $graphDataIn = array();
                    $graphDataOut = array();
                    foreach($period as $key=>$date) {
                        
                        $currentTimeFrame = $date->format('Y-m-d');
                        $test[] = $currentTimeFrame; // Debug purpose

                        $db->where('user_id', $userID);
                        $db->where('date', $currentTimeFrame);
                        $db->groupBy('direction');
                        $results = $db->get('developer_activity_daily_summary', null, 'direction, sum(activity_count) AS activity_count');

                        // loop through all the result and build data for graph
                        // Notes : total of 3 cases
                        //          1. array is empty (in=0, out=0)
                        //          2. array consist of only 1 data (in | out)
                        //          3. array consist of 2 datas (in & out)
                        $inCount = 0;
                        $outCount = 0;

                        // case 1
                        if (count($results) == 0) {
                            // in & out count is 0
                        }
                        // case 2 & case 3
                        if (count($results) > 0) {
                            foreach($results as $result) {
                                if ($result['direction'] == 'in') {
                                    $inCount = $result['activity_count'];
                                } else {
                                    $outCount = $result['activity_count'];
                                }
                            }

                        }
                        // if ($inCount != 0) {    // comment this if want to get value with zero count
                            $graphDataIn[] = array(
                                'date'  => $currentTimeFrame,
                                'value' => $inCount
                            );
                        // }
                        // if ($outCount != 0) {   // comment this if want to get value with zero count
                            $graphDataOut[] = array(
                                'date' => $currentTimeFrame,
                                'value' => $outCount
                            );
                        // }
                    }

                    // query for today's data
                    $db->where('user_id', $userID);
                    $db->where('created_at', date('Y-m-d 00:00:00'), '>=');
                    $db->groupBy('direction');
                    $results = $db->get('developer_activity_log', null, 'direction, count(*) AS activity_count');

                    $test[] = date('Y-m-d'); // Debug purpose

                    // loop through all the result and build data for graph
                    // Notes : total of 3 cases
                    //          1. array is empty (in=0, out=0)
                    //          2. array consist of only 1 data (in | out)
                    //          3. array consist of 2 datas (in & out)
                    $inCount = 0;
                    $outCount = 0;

                    // case 1
                    if (count($results) == 0) {
                        // in & out count is 0
                    }
                    // case 2 & case 3
                    if (count($results) > 0) {
                        foreach($results as $result) {
                            if ($result['direction'] == 'in') {
                                $inCount = $result['activity_count'];
                            } else {
                                $outCount = $result['activity_count'];
                            }
                        }

                    }
                    // if ($inCount != 0) {    // comment this if want to get value with zero count
                        $graphDataIn[] = array(
                            'date'  => date('Y-m-d'),
                            'value' => $inCount
                        );
                    // }
                    // if ($outCount != 0) {   // comment this if want to get value with zero count
                        $graphDataOut[] = array(
                            'date' => date('Y-m-d'),
                            'value' => $outCount
                        );
                    // }
                    
                    break;

                case "1w":
                    $startDate = date('Y-m-d', strtotime('-3 month'));
                    $period = new DatePeriod(
                        new DateTime($startDate.' 00:00:00'),
                        new DateInterval('P7D'),
                        new DateTime(date('Y-m-d H:i:s', strtotime('yesterday 23:59:59')))
                    );

                    // Start query for each interval
                    $test = array(); // Debug purpose
                    $initialTimeFrame = '';
                    $graphDataIn = array();
                    $graphDataOut = array();
                    $finalTimeFrame = '';
                    foreach($period as $key=>$date) {
                        if ($key == 0) {
                            $initialTimeFrame = $date->format('Y-m-d');
                            continue;
                        }

                        $currentTimeFrame = $date->format('Y-m-d');
                        $startInterval = $initialTimeFrame;
                        $endInterval = $currentTimeFrame;

                        $test[] = "start: ". $startInterval . " end: " . $endInterval; // Debug purpose

                        $db->where('user_id', $userID);
                        $db->where('date', $startInterval, '>');
                        $db->where('date', $endInterval, '<=');
                        $db->groupBy('direction');
                        $results = $db->get('developer_activity_daily_summary', null, 'direction, sum(activity_count) AS activity_count');
                        
                        // loop through all the result and build data for graph
                        // Notes : total of 3 cases
                        //          1. array is empty (in=0, out=0)
                        //          2. array consist of only 1 data (in | out)
                        //          3. array consist of 2 datas (in & out)
                        $inCount = 0;
                        $outCount = 0;

                        // case 1
                        if (count($results) == 0) {
                            // in & out count is 0
                        }
                        // case 2 & case 3
                        if (count($results) > 0) {
                            foreach($results as $result) {
                                if ($result['direction'] == 'in') {
                                    $inCount = $result['activity_count'];
                                } else {
                                    $outCount = $result['activity_count'];
                                }
                            }

                        }
                        // if ($inCount != 0) {    // comment this if want to get value with zero count
                            $graphDataIn[] = array(
                                'date'  => $currentTimeFrame,
                                'value' => $inCount
                            );
                        // }
                        // if ($outCount != 0) {   // comment this if want to get value with zero count
                            $graphDataOut[] = array(
                                'date' => $currentTimeFrame,
                                'value' => $outCount
                            );
                        // }

                        // update initial timeframe
                        $initialTimeFrame = $currentTimeFrame;
                        $finalTimeFrame = $currentTimeFrame;
                    }

                    // query for today's data
                    $finalDateTime = date('Y-m-d', strtotime('+1 day', strtotime($finalTimeFrame)));
                    $db->where('user_id', $userID);
                    $db->where('created_at', $finalDateTime." 00:00:00", '>=');
                    $db->groupBy('direction');
                    $results = $db->get('developer_activity_log', null, 'direction, count(*) AS activity_count');

                    $test[] = "start: ". $finalDateTime . " end: " . date('Y-m-d'); // Debug purpose

                    // loop through all the result and build data for graph
                    // Notes : total of 3 cases
                    //          1. array is empty (in=0, out=0)
                    //          2. array consist of only 1 data (in | out)
                    //          3. array consist of 2 datas (in & out)
                    $inCount = 0;
                    $outCount = 0;

                    // case 1
                    if (count($results) == 0) {
                        // in & out count is 0
                    }
                    // case 2 & case 3
                    if (count($results) > 0) {
                        foreach($results as $result) {
                            if ($result['direction'] == 'in') {
                                $inCount = $result['activity_count'];
                            } else {
                                $outCount = $result['activity_count'];
                            }
                        }

                    }
                    // if ($inCount != 0) {    // comment this if want to get value with zero count
                        $graphDataIn[] = array(
                            'date'  => date('Y-m-d'),
                            'value' => $inCount
                        );
                    // }
                    // if ($outCount != 0) {   // comment this if want to get value with zero count
                        $graphDataOut[] = array(
                            'date' => date('Y-m-d'),
                            'value' => $outCount
                        );
                    // }
                    

                    break;
                case "1m":
                    $startDate = date('Y-m-d', strtotime('-1 year'));
                    $period = new DatePeriod(
                        new DateTime($startDate.' 00:00:00'),
                        new DateInterval('P1M'),
                        new DateTime(date('Y-m-d H:i:s', strtotime('yesterday 23:59:59')))
                    );

                    // Start query for each interval
                    $test = array(); // Debug purpose
                    $initialTimeFrame = '';
                    $graphDataIn = array();
                    $graphDataOut = array();
                    $finalTimeFrame = '';
                    foreach($period as $key=>$date) {
                        if ($key == 0) {
                            $initialTimeFrame = $date->format('Y-m-d');
                            continue;
                        }

                        $currentTimeFrame = $date->format('Y-m-d');
                        $startInterval = $initialTimeFrame;
                        $endInterval = $currentTimeFrame;

                        $test[] = "start: ". $startInterval . " end: " . $endInterval; // Debug purpose

                        $db->where('user_id', $userID);
                        $db->where('date', $startInterval, '>');
                        $db->where('date', $endInterval, '<=');
                        $db->groupBy('direction');
                        $results = $db->get('developer_activity_daily_summary', null, 'direction, sum(activity_count) AS activity_count');
                        
                        // loop through all the result and build data for graph
                        // Notes : total of 3 cases
                        //          1. array is empty (in=0, out=0)
                        //          2. array consist of only 1 data (in | out)
                        //          3. array consist of 2 datas (in & out)
                        $inCount = 0;
                        $outCount = 0;

                        // case 1
                        if (count($results) == 0) {
                            // in & out count is 0
                        }
                        // case 2 & case 3
                        if (count($results) > 0) {
                            foreach($results as $result) {
                                if ($result['direction'] == 'in') {
                                    $inCount = $result['activity_count'];
                                } else {
                                    $outCount = $result['activity_count'];
                                }
                            }

                        }
                        // if ($inCount != 0) {    // comment this if want to get value with zero count
                            $graphDataIn[] = array(
                                'date'  => $currentTimeFrame,
                                'value' => $inCount
                            );

                        // }
                        // if ($outCount != 0) {   // comment this if want to get value with zero count
                            $graphDataOut[] = array(
                                'date' => $currentTimeFrame,
                                'value' => $outCount
                            );
                        // }

                        // update initial timeframe
                        $initialTimeFrame = $currentTimeFrame;
                        $finalTimeFrame = $currentTimeFrame;
                    }

                    // query for today's data
                    $finalDateTime = date('Y-m-d', strtotime('+1 day', strtotime($finalTimeFrame)));
                    $db->where('user_id', $userID);
                    $db->where('created_at', $finalDateTime." 00:00:00", '>=');
                    $db->groupBy('direction');
                    $results = $db->get('developer_activity_log', null, 'direction, count(*) AS activity_count');

                    $test[] = "start: ". $finalDateTime . " end: " . date('Y-m-d'); // Debug purpose

                    // loop through all the result and build data for graph
                    // Notes : total of 3 cases
                    //          1. array is empty (in=0, out=0)
                    //          2. array consist of only 1 data (in | out)
                    //          3. array consist of 2 datas (in & out)
                    $inCount = 0;
                    $outCount = 0;

                    // case 1
                    if (count($results) == 0) {
                        // in & out count is 0
                    }
                    // case 2 & case 3
                    if (count($results) > 0) {
                        foreach($results as $result) {
                            if ($result['direction'] == 'in') {
                                $inCount = $result['activity_count'];
                            } else {
                                $outCount = $result['activity_count'];
                            }
                        }

                    }
                    // if ($inCount != 0) {    // comment this if want to get value with zero count
                        $graphDataIn[] = array(
                            'date'  => date('Y-m-d'),
                            'value' => $inCount
                        );
                    // }
                    // if ($outCount != 0) {   // comment this if want to get value with zero count
                        $graphDataOut[] = array(
                            'date' => date('Y-m-d'),
                            'value' => $outCount
                        );
                    // }

                    break;
            }

            $returnData['debug'] = $test;
            $returnData['inRequest'] = $graphDataIn;
            $returnData['outRequest'] = $graphDataOut;

            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Developer Data.", "result" => $returnData);
        }

        public function get_developer_io_command_list($params) {
            $db             = $this->db;
            $returnData     = array();
            $commandList    = array();
            $validCommands  = array();

            $db->groupBy('command');
            $commandList = $db->getValue('developer_activity_log', 'command', null);

            if (count($commandList) != 0) {

                // remove numeric command as that command is for testing purpose
                foreach($commandList as $list) {
                    $lastCharIsNumeric = is_numeric(substr($list, -1));

                    if (!$lastCharIsNumeric) {
                        $validCommands[] = $list;
                    }
                }
            }

            $returnData['data'] = $validCommands;

            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Developer Data.", "result" => $returnData);
        }

        public function get_developer_io_data($params) {
            global $setting;

            $db             = $this->db;
            $page           = $params['page'];
            $userID         = $params['user_id'];
            $searchParams   = $params['search'];
            $returnData     = array();

            $page_limit         = $setting->systemSetting["memberBlogPageLimit"];
            $page_number        = $params["page"] ? $params["page"] : 1;
            $page_size          = $params["page_size"] ? $params["page_size"] : $page_limit;
            $start_limit        = ($page_number - 1) * $page_size;
            $limit              = array((string)$start_limit, $page_size);

            // build search query
            if (count($searchParams) != 0) {
                foreach($searchParams as $search) {
                    if ($search['type'] == 'start') {
                        $db->where('created_at', $search['value'].' 00:00:00', '>=');
                    }
                    if ($search['type'] == 'end') {
                        $db->where('created_at', $search['value'].' 00:00:00', '<=');
                    }
                    if ($search['type'] == 'direction' && $search['value'] != 'all') {
                        $db->where('direction', $search['value']);
                    }
                    if ($search['type'] == 'command' && $search['value'] != 'all') {
                        $db->where('command', $search['value']);
                    }
                }
            }

            $queryCol = array(
                'id',
                'direction',
                'command',
                'data_in',
                'data_out',
                'created_at'
            );
            $db->where('user_id', $userID);
            $db->orderBy('created_at', 'DESC');
            $copyDb = $db->copy();
            $results = $db->get('developer_activity_log', $limit, $queryCol);

            $data = array();
            $outputArray = array();
            foreach($results as $record) {
                $data[] = array(
                    'Date' => date('M d, Y h:i A', strtotime($record['created_at'])),
                    'Command' => $record['command'],
                    'Type' => $record['direction'] == 'in' ? $this->get_translation_message('M00160') : $this->get_translation_message('M02064'),
                );
                $outputArray[] = array(
                    'id' => $record['id'],
                    'dataIn' => $record['data_in'],
                    'dataOut' => $record['data_out']
                );
            }

            // build pagination data
            $totalRecord = $copyDb->getValue('developer_activity_log', 'count(id)');
            $total_page = ceil($totalRecord/$page_size);


            $returnData['debug'] = $searchParams;
            $returnData['data'] = $data;
            $returnData['output'] = $outputArray;

            $returnData["totalPage"] = $total_page;
            $returnData['pageNumber']   = $page_number;
            $returnData['totalRecord']  = $totalRecord;
            $returnData['numRecord'] = $page_size;

            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Developer Data.", "result" => $returnData);
        }

        public function check_crypto_internal_transaction($params, $padded_amount){
            global $setting, $xunMarketplace, $general, $xunServiceCharge, $xunPay, $log, $xunXmpp, $xunPaymentGateway, $config, $xun_numbers, $account, $xunPayment, $xunCurrency, $webservice;
            $db = $this->db;
            $post = $this->post;
            $xunWallet = new XunWallet($db);

            $date = date("Y-m-d H:i:s");
            $target = $params["target"];
            $transaction_type = $params["type"];
            $status = $params["status"];
            $recipient_address = $params["recipient"];
            $sender_address = $params["sender"];
            $amount = $params["amount"];
            $transaction_hash = $params["transactionHash"];
            $wallet_type = $params["wallet_type"];
            $feeUnit = $params["feeUnit"];
            $exchange_rate = $params['exchangeRate']['USD'];
            $bc_reference_id = $params['referenceID'];

            // $escrow_address = $setting->systemSetting["marketplaceEscrowWalletAddress"];
            $trading_fee_address = $setting->systemSetting["marketplaceTradingFeeWalletAddress"];
            $company_pool_address = $setting->systemSetting["marketplaceCompanyPoolWalletAddress"];
            // $consolidate_address = $setting->systemSetting["requestFundConsolidateWalletAddress"];
            $miner_fee_delegate_wallet = $setting->systemSetting["minerFeeDelegateWalletAddress"];
            $redeem_code_agent_wallet = $setting->systemSetting['redeemCodeAgentAddress'];

            $freecoin_address = $setting->systemSetting["freecoinWalletAddress"];
            // $pay_address = $setting->systemSetting["payWalletAddress"];


            $company_address_list = $this->company_wallet_address();
            $recipient_address_info = $company_address_list[$recipient_address];
            $recipient_address_type = $recipient_address_info ? $recipient_address_info["type"] : null;

            $sender_address_info = $company_address_list[$sender_address];
            $sender_address_type = $sender_address_info ? $sender_address_info["type"] : null;
            // check if the recipient address type is company pool then set company pool address as recipient address
            if($recipient_address_type == 'company_pool'){
                $company_pool_address = $recipient_address;
            }

            $db->where("currency_id", $wallet_type);
            $wallet_info = $db->getOne("xun_marketplace_currencies", null, "currency_id, unit_conversion");

            // $padding = $wallet_info ? $wallet_info["unit_conversion"] : 100000000;

            // $padded_amount = bcdiv((string)$amount, (string)$padding, 8);
            
            if($transaction_hash == ''){
                return $padded_amount;
            }
            if($target != "internal"){
                return $padded_amount;
            }
            if($status != "confirmed" && $status != "failed"){
                return $padded_amount;
            }    

                 

            // if($transaction_type == "receive" && $recipient_address_type == "escrow"){
            //     // fund in, sell ad, sell order

            //     // update
            //     $db->where("transaction_hash", $transaction_hash);
            //     $transaction_rec = $db->getOne("xun_marketplace_escrow_transaction");

            //     if($transaction_rec){
            //         $updateData = [];
            //         $updateData["debit"] = $padded_amount;
            //         $updateData["account_address"] = $sender_address;
            //         $updateData["status"] = "confirmed";
            //         $updateData["updated_at"] = $date;

            //         $db->where("id", $transaction_rec["id"]);
            //         $db->update("xun_marketplace_escrow_transaction", $updateData);

            //         $xunMarketplace->update_advertisement_order_transaction($transaction_rec["advertisement_id"], $transaction_rec["advertisement_order_id"], $transaction_type);
            //     }else{
            //         $insertData = array(
            //             "transaction_hash" => $transaction_hash,
            //             "account_address" => $sender_address,
            //             "type" => $transaction_type,
            //             "debit" => $padded_amount,
            //             "status" => "confirmed",
            //             "created_at" => $date,
            //             "updated_at" => $date
            //         );
                    
            //         $db->insert("xun_marketplace_escrow_transaction", $insertData);
            //     }
            // }else if($transaction_type == "send" && $sender_address_type == "escrow"){
            //     // fund out, buy ad, buy order

            //     // update
            //     $db->where("transaction_hash", $transaction_hash);
            //     $transaction_rec = $db->getOne("xun_marketplace_escrow_transaction");

            //     if($transaction_rec){
            //         $updateData = [];
            //         $updateData["credit"] = $padded_amount;
            //         $updateData["account_address"] = $recipient_address;
            //         $updateData["status"] = "confirmed";
            //         $updateData["updated_at"] = $date;

            //         $db->where("id", $transaction_rec["id"]);
            //         $db->update("xun_marketplace_escrow_transaction", $updateData);

            //         $xunMarketplace->update_advertisement_order_transaction($transaction_rec["advertisement_id"], $transaction_rec["advertisement_order_id"], $transaction_type);
            //     }else{
            //         $insertData = array(
            //             "transaction_hash" => $transaction_hash,
            //             "account_address" => $recipient_address,
            //             "credit" => $padded_amount,
            //             "status" => "confirmed",
            //             "type" => $transaction_type,
            //             "created_at" => $date,
            //             "updated_at" => $date
            //         );
                    
            //         $db->insert("xun_marketplace_escrow_transaction", $insertData);
            //     }
            // } else 
            if($transaction_type == "send" && $sender_address_type == "company_pool"){
                // update
                $wallet_transaction_record = $xunWallet->getWalletTransactionByTxHash($transaction_hash);
                
                if($wallet_transaction_record){
                    if($wallet_transaction_record["status"] === "completed"){
                        return;
                    }
                    $address_type = $recipient_address_type == "company_acc" ? "company_pool" : "master_upline";
                    $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);



                    $ret_val = $wallet_transaction_return["id"];
                    if($status == "confirmed" || $status == "failed"){

                        $transactionStatus = $wallet_transaction_record['status'];
                        $wallet_transaction_id = $wallet_transaction_record['id'];
                        $db->where('reference_id', $wallet_transaction_id);
                        $marketer_transaction_commission = $db->getOne('xun_marketer_commission_transaction');
                        $business_marketer_commission_id = $marketer_transaction_commission['business_marketer_commission_id'];
                        $walletType = $marketer_transaction_commission['wallet_type'];

                        $db->where('id', $business_marketer_commission_id);
                        $commission_scheme = $db->getOne('xun_business_marketer_commission_scheme');
                        $business_id = $commission_scheme['business_id'];

                        $db->where("user_id", $business_id);
                        $business_name = $db->getValue("xun_business", "name");
   
                        $db->where('wallet_type', $walletType);
                        $db->where('business_marketer_commission_id', $business_marketer_commission_id);
                        $db->orderBy('id', 'DESC');
                        $latest_commission_transaction = $db->getOne('xun_marketer_commission_transaction','SUM(credit) as sumCredit, SUM(debit) as sumDebit');
                        $balance_amount = '0.00000000';
                            if($latest_commission_transaction){
                            $sum_credit = $latest_commission_transaction['sumCredit'];
                            $sum_debit = $latest_commission_transaction['sumDebit'];
    
                            $balance_amount = bcsub($sum_credit, $sum_debit, 8);
    
                            //$balance = $latest_commission_transaction['balance'];
                        }
                      
                        $cryptoValue = bcdiv($amount, $amount_rate, 8);   
    
                        if(($transactionStatus == 'completed') && $return['address_type'] == 'marketer'){
                            $tag = "Marketer Fund Out";
                            // $message = "Business Marketer Commission ID: ".$business_marketer_commission_id."\n";
                            $message = "Business Name:".$business_name."\n";
                            $message .= "Amount:" .$cryptoValue."\n";
                            $message .= "Wallet Type:".$walletType."\n";
                            $message .= "Transaction Type: internal\n";
                            $message .= "Status: ".$transactionStatus."\n";
                            $message .= "Time: ".date("Y-m-d H:i:s")."\n";
        
        
                            $thenux_params["tag"]         = $tag;
                            $thenux_params["message"]     = $message;
                            $thenux_params["mobile_list"] = $xun_numbers;
                            $xmpp_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

                        }
                    
                        $totalCryptoAmount = bcadd($balance_amount, $cryptoValue, 8);
                        if(($transactionStatus == 'failed') && $return['address_type'] == 'marketer'){
                            $tag = "Failed Marketer Fund Out";
                            $message = "Business Name:".$business_name."\n";
                            $message .= "Amount:" .$totalCryptoAmount."\n";
                            $message .= "Wallet Type:".$walletType."\n";
                            $message .= "Transaction Type: internal\n";
                            $message .= "Status: ".$transactionStatus."\n";
                            $message .= "Time: ".date("Y-m-d H:i:s")."\n";
        
        
                            $thenux_params["tag"]         = $tag;
                            $thenux_params["message"]     = $message;
                            $thenux_params["mobile_list"] = $xun_numbers;
                            $xmpp_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

                        }
                        
                        if($return['address_type'] == 'marketer' && $status == 'failed'){
                            $wallet_transaction_id = $return['id'];
    
                            $db->where('reference_id', $wallet_transaction_id);
                            $marketerCommissionTransaction = $db->getOne('xun_marketer_commission_transaction');
                            
                            $business_marketer_commission_id = $marketerCommissionTransaction['business_marketer_commission_id'];
                            $marketerCommissionWalletType = $marketerCommissionTransaction['wallet_type'];
    
                            $db->where('business_marketer_commission_id', $business_marketer_commission_id);
                            $db->where('wallet_type', $marketerCommissionWalletType);
                            $db->orderBy('id', 'DESC');
                            $latestMarketerCommissionTransaction = $db->getOne('xun_marketer_commission_transaction');
                            
                            $latestBalance = $latestMarketerCommissionTransaction['balance'];
    
                            $cryptoFiatArr[$wallet_type]['value'] = $cryptoValue;
    
                            $crypto_fiat_rate = $xunCurrency->calculate_crypto_fiat_rate($cryptoFiatArr);
                            $walletFiatRateUSD = $crypto_fiat_rate[$wallet_type]['usd'];
    
                            $newBalance = bcsub($latestBalance, $walletFiatRateUSD, 8);
    
                            //check if failed callback transaction is added, if no then add a return in marketer commission trasaction table
                            $db->where('type', 'Callback Failed');
                            $db->where('reference_id', $wallet_transaction_id);
                            $checkFailedTxAdded = $db->getOne('xun_marketer_commission_transaction');
                        
                            if(!$checkFailedTxAdded){
                           
                                $marketer_commission_transaction_id = $this->insertMarketerCommissionTransaction($business_marketer_commission_id, $cryptoValue, $amount, $marketerCommissionWalletType, $cryptoValue, 0, $totalCryptoAmount, 'Crypto Callback Failed', 'Callback Failed', $wallet_transaction_id);
                            }
                            
                        }
    
                    }
                }
                // else{
                //     $db->where("transaction_hash", $transaction_hash);
                //     $transaction_rec = $db->getOne("xun_marketplace_escrow_transaction");
    
                //     if($transaction_rec){
                //         $updateData = [];
                //         $updateData["credit"] = $padded_amount;
                //         $updateData["account_address"] = $recipient_address;
                //         $updateData["status"] = "confirmed";
                //         $updateData["updated_at"] = $date;
    
                //         $db->where("id", $transaction_rec["id"]);
                //         $db->update("xun_marketplace_escrow_transaction", $updateData);
    
                //         $xunMarketplace->update_advertisement_order_transaction($transaction_rec["advertisement_id"], $transaction_rec["advertisement_order_id"], $transaction_type);
                //     }else{
                //         $insertData = array(
                //             "transaction_hash" => $transaction_hash,
                //             "account_address" => $recipient_address,
                //             "credit" => $padded_amount,
                //             "status" => "confirmed",
                //             "type" => $transaction_type,
                //             "created_at" => $date,
                //             "updated_at" => $date
                //         );

                //         $db->insert("xun_marketplace_escrow_transaction", $insertData);                        
                //     }
                // }
            } 
            else if($transaction_type == "send" && $sender_address_type == "trading_fee"){
                // update company pool status
                $wallet_transaction_record = $xunWallet->getWalletTransactionByTxHash($transaction_hash);
                if($wallet_transaction_record){
                    if($wallet_transaction_record["status"] === "completed"){
                        return;
                    }
                    $address_type = "company_pool";
                    $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                    $ret_val = $wallet_transaction_return["id"];

                    if($recipient_address_type == "company_pool"){
                        if($ret_val){
                            //  fund out to upline and company pool
                            $service_charge_transaction_id = $wallet_transaction_record["reference_id"];
                            $service_charge_record = $xunServiceCharge->get_service_charge_by_id($service_charge_transaction_id);
                            $service_charge_user_id = $service_charge_record["user_id"];
                            $new_params = [];
                            $new_params["wallet_type"] = $wallet_type;
                            $new_params["amount"] = $padded_amount;
                            $new_params["user_id"] = $service_charge_user_id;
                            $new_params["sender_address"] = $company_pool_address;
                            $service_charge_transaction_id = $wallet_transaction_record["reference_id"];


                            $this->process_fund_in_to_company_pool_wallet($new_params, $service_charge_transaction_id);
                        }
                    }
                }

                // else{
                //     $db->where("transaction_hash", $transaction_hash);
                //     $transaction_rec = $db->getOne("xun_marketplace_escrow_transaction");
    
                //     if($transaction_rec){
                //         $updateData = [];
                //         $updateData["credit"] = $padded_amount;
                //         $updateData["account_address"] = $recipient_address;
                //         $updateData["status"] = "confirmed";
                //         $updateData["updated_at"] = $date;
    
                //         $db->where("id", $transaction_rec["id"]);
                //         $db->update("xun_marketplace_escrow_transaction", $updateData);
    
                //         $xunMarketplace->update_advertisement_order_transaction($transaction_rec["advertisement_id"], $transaction_rec["advertisement_order_id"], $transaction_type);
                //     }else{
                //         $insertData = array(
                //             "transaction_hash" => $transaction_hash,
                //             "account_address" => $recipient_address,
                //             "credit" => $padded_amount,
                //             "status" => "confirmed",
                //             "type" => $transaction_type,
                //             "created_at" => $date,
                //             "updated_at" => $date
                //         );
                        
                //         $db->insert("xun_marketplace_escrow_transaction", $insertData);
                //         $this->update_crypto_transaction_hash($params, $padded_amount);
                //     }
                // }
            } 
            else if ($transaction_type == "send" && $sender_address_type == "freecoin") {
                // fund out from freecoin wallet

                $db->where("transaction_hash", $transaction_hash);
                $transaction_hash_record = $db->getOne("xun_crypto_transaction_hash", "id");
                if(!$transaction_hash_record){
                    $insertData = array(
                        "transaction_hash" => $transaction_hash,
                        "sender_address" => $sender_address,
                        "recipient_address" => $recipient_address,
                        "amount" => $padded_amount,
                        "type" => $target,
                        "wallet_type" => strtolower($wallet_type),
                        "status" => $status == 'confirmed' ? 'completed' : $status,
                        "exchange_rate" => $exchange_rate,
                        "bc_reference_id" => $bc_reference_id,
                        "created_at" => $date
                    );
    
                    $row_id = $db->insert("xun_crypto_transaction_hash", $insertData);
                    // check freecoin table
                    
                    // $xunFreecoinPayout->cryptoCallbackUpdate($transaction_hash);

                }

                $address_type = "nuxpay_wallet";
                $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);

                $fund_in_params = $params;

                $pg_fund_in_id = $wallet_transaction_return['reference_id'];
                $fund_in_params['pg_fund_in_id'] = $pg_fund_in_id;
                $fund_in_params['wallet_transaction_id'] = $wallet_transaction_return['id'];
                $fund_in_params['status'] = $status;

                $xunFreecoinPayout = new XunFreecoinPayout($db, $setting, $general);
                $xunFreecoinPayout->update_fund_in_transaction($fund_in_params);


            }
            // else if($recipient_address_type == "topup"){
            //     $address_type = "pay";
            //     $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);

            //     if(!isset($wallet_transaction_return["is_completed"]) && $wallet_transaction_return["is_completed"] == 0){
            //         $wallet_transaction_id = $wallet_transaction_return["id"];
            //         $wallet_transaction_status = $wallet_transaction_return["status"];
    
            //         if($wallet_transaction_status == "completed"){
            //             //  query xun_pay_transaction table
            //             $this->process_pay_transaction($wallet_transaction_id);
            //         }
            //     }
            // }else if($transaction_type == "send" && $sender_address_type == "topup"){
            //     //  refund from pay wallet
            //     $address_type = "pay";

            //     //  update crypto transaction hash
            //     //  update wallet transaction
            //     //  update pay transacton

            //     $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);

            //     $this->process_pay_refund_transction($wallet_transaction_return);
            // }
            else if( $recipient_address_type == "payment_gateway" ){
                $address_type = "payment_gateway";
                $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);

                if($wallet_transaction_return["address_type"] == "story"){
                    //  check if this transaction has already completed
                    //  only update once in case of duplicated callback
                    if(!isset($wallet_transaction_return["is_completed"]) && $wallet_transaction_return["is_completed"] == 0){
                        $wallet_transaction_id = $wallet_transaction_return["id"];
                        $wallet_transaction_status = $wallet_transaction_return["status"];
        
                        if($wallet_transaction_status == "completed"){
                            //$this->process_story_transaction($wallet_transaction_return);
                        }
                    }
                }

            } 
            // else if($transaction_type == "send" && $sender_address_type == "story"){
            //     //  refund from story wallet
            //     $address_type = "story";

            //     //  update crypto transaction hash
            //     //  update wallet transaction
            //     //  update story transacton

            //     $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);

            //     $this->process_story_refund_transaction($wallet_transaction_return);
                
            // } 
            else if ($transaction_type == "send"){
                
                //  prepaid wallet fund out
                $sender_address_data = $this->sender_address_data;                
                if(isset($sender_address_data) && $sender_address_data["address_type"] == "prepaid"){
                    $address_type = "prepaid";
                    $this->update_wallet_transaction($params, $padded_amount, $address_type);
                }                
                // elseif($sender_address_data['address_type'] == 'reward'){
                //     $address_type = 'reward';
                //     $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                //     $wallet_transaction_id = $wallet_transaction_return["id"];
                //     if($wallet_transaction_id){
                //         $this->process_business_reward_redemption($wallet_transaction_return, 'reward');
                //     }
                    
                // }
                else{
                    //  only save crypto transaction for send (user update when send)
                    $address_type = "personal";
                    $return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
            
                    $address_type = $return['address_type'];
                    $reference_id = $return['reference_id'];

                     if($address_type=="withdrawal"){
                       
                        $update_status = array(
                            "status" => $status == 'confirmed' ? 'success' : $status,
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('id', $return['reference_id']);
                        $updated = $db->update('xun_request_fund_withdrawal', $update_status);
                        
                        $exchange_rate = $params['exchangeRate']['USD'];
                        $fee = bcdiv($fee, $feeRate,18);
                        $miner_fee_usd_amount = bcmul($fee, $params['minerFeeExchangeRate'], 18);
                        $miner_fee = bcdiv($miner_fee_usd_amount, $exchange_rate, 18);
    
                        $update_withdrawal = array(
                            "amount" => $padded_amount,
                            "miner_fee" => $miner_fee,
                            "status" => $status == 'confirmed' ? 'success' : $status,
                            "transaction_hash" => $transaction_hash,
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('reference_id', $return['reference_id']);
                        $db->where('transaction_type', 'manual_withdrawal');
                        $updated_pg_withdrawal = $db->update('xun_payment_gateway_withdrawal', $update_withdrawal);
    
                        $db->where('reference_id', $return['reference_id']);
                        $db->where('transaction_type', 'manual_withdrawal');
                        $pg_withdrawal_data = $db->getOne('xun_payment_gateway_withdrawal ', 'business_id, transaction_fee, miner_fee, wallet_type');
    
                        $transaction_fee = $pg_withdrawal_data['transaction_fee'];
                        $miner_fee = $pg_withdrawal_data['miner_fee'];
                        $user_id = $pg_withdrawal_data['business_id'];
                        
                        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
                        $decimal_places = $decimal_place_setting["decimal_places"];
                
                        $total_charges = bcadd($transaction_fee, $miner_fee, $decimal_places);
                        $total_fee_charges_usd =  $xunCurrency->get_conversion_amount('usd', $wallet_type, $total_charges, true, $exchange_rate);
    
                        $compensate_fee_amount = $setting->systemSetting['compensateFeeAmount']; //Compensate Fee Amount (USD)
    
                        $db->where('id', $user_id);
                        $user_data = $db->getOne('xun_user', 'id, nickname, reseller_id');
        
                        
                        if($status == 'confirmed'){
                            $tag = "Request Fund Withdrawal";
                            $message = "Business ID: ".$user_id."\n";
                            $message .= "Tx Hash:".$transaction_hash."\n";
                            $message .= "Amount:" .$padded_amount."\n";
                            $message .= "Wallet Type:".$wallet_type."\n";
                            $message .= "Transaction Type: internal\n";
                            $message .= "Time: ".date("Y-m-d H:i:s")."\n";
    
                            $thenux_params["tag"]         = $tag;
                            $thenux_params["message"]     = $message;
                            $thenux_params["mobile_list"] = $xun_numbers;
                            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                            
                        }
    
                        if($status == 'failed'){
                            $balance = $xunPaymentGateway->getUserRequestFundBalance($wallet_type, $return['user_id']);
                            $total_balance = bcadd($balance, $padded_amount, 8);
                            
                            $insertTx = array(
                                "business_id" => $return['user_id'],
                                "sender_address" => $recipient_address,
                                "recipient_address" => $sender_address,
                                "amount" => $padded_amount,
                                "amount_satoshi" => $amount,
                                "wallet_type" => $wallet_type,
                                "credit" => $padded_amount,
                                "debit" => 0,
                                "balance" => $total_balance,
                                "transaction_type" => "refund_withdrawal",
                                "reference_id" => $return['id'],
                                "created_at" => $date,
                            );
                    
                            $invoice_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertTx);
    
                            $tag = "Request Fund Withdrawal Fail";
                            $message = "Business ID: ".$user_id."\n";
                            $message .= "Amount:" .$padded_amount."\n";
                            $message .= "Wallet Type:".$wallet_type."\n";
                            $message .= "Transaction Type: internal\n";
                            $message .= "Time: ".date("Y-m-d H:i:s")."\n";
    
                            $thenux_params["tag"]         = $tag;
                            $thenux_params["message"]     = $message;
                            $thenux_params["mobile_list"] = $xun_numbers;
                            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                        }
    
                    }
                }
            }
            else if ($transaction_type == "receive"){ 
                //  fund in to prepaid wallet                
                $recipient_address_data = $this->recipient_address_data;
                $sender_address_data = $this->sender_address_data;                
                $escrowInternalAddress = $setting->systemSetting['escrowInternalAddress'];
                $swapCoinInternalAddress = $setting->systemSetting['swapInternalAddress'];                  
                
                // if(isset($recipient_address_data) && $recipient_address_data["address_type"] == "prepaid"){
                //     $address_type = "prepaid";
                //     $this->update_wallet_transaction($params, $padded_amount, $address_type);
                // }else 
                if($recipient_address_type == "trading_fee"){
          
                    // update wallet_transaction table
                    $address_type = "service_charge";
                    $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                    $wallet_transaction_id = $wallet_transaction_return["id"];           

                    if($wallet_transaction_return["is_completed"] === 1){
                        return;
                    }

                    if($wallet_transaction_id){
                        $status = "completed";
                        $service_charge_data = $xunServiceCharge->update_service_charge($wallet_transaction_id, $status);

                        //  fund out to upline and company pool
                        $new_params = [];
                        $new_params["wallet_type"] = $wallet_type;
                        $new_params["amount"] = $padded_amount;
                        $new_params["user_id"] = $service_charge_data["user_id"];
                        $new_params["sender_address"] = $trading_fee_address;
                        $new_params["transaction_callback_user_id"] = $wallet_transaction_return["user_id"];
                        
                        $this->process_fund_in_to_service_charge_wallet($new_params, $service_charge_data["id"]);
                    }
                }
                // else if($recipient_address_data["address_type"] == "reward"){
                //     //  reward redemption
                //     $address_type = "reward";

                //     $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                //     $wallet_transaction_id = $wallet_transaction_return["id"];
                //     if($wallet_transaction_id){
                //         $this->process_business_reward_redemption($wallet_transaction_return, 'redemption');
                //     }
                // }
                
                else if($sender_address_data['type'] == 'payment_gateway'){
                    if(!is_null($this->recipient_address_data)){
                        $recipientUserID = $this->recipient_address_data["user_id"];
                    }

                    $address_type = "payment_gateway";
                    
                    $transactionObj->status = $status == 'confirmed' ? 'completed' : $status;
                    $transactionObj->transactionHash = $transaction_hash;
                    $transactionObj->transactionToken = "";
                    $transactionObj->senderAddress = $sender_address;
                    $transactionObj->recipientAddress = $recipient_address;
                    $transactionObj->userID = $recipientUserID ? $recipientUserID : '';
                    $transactionObj->senderUserID = '';
                    $transactionObj->recipientUserID = $recipientUserID;
                    $transactionObj->walletType = $wallet_type;
                    $transactionObj->amount = $padded_amount;
                    $transactionObj->addressType = $address_type;
                    $transactionObj->transactionType = $transaction_type;
                    $transactionObj->escrow = 0;
                    $transactionObj->referenceID = '';
                    $transactionObj->escrowContractAddress = '';
                    $transactionObj->createdAt = $date;
                    $transactionObj->updatedAt = $date;
                    $transactionObj->expiresAt = '';
                    $transactionObj->fee = $padded_fee;
                    $transactionObj->feeUnit = $feeUnit;

                    $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);  

                    $txHistoryObj->status = $status;
                    $txHistoryObj->transactionID = $transaction_hash;
                    $txHistoryObj->transactionToken = "";
                    $txHistoryObj->senderAddress = $sender_address;
                    $txHistoryObj->recipientAddress = $recipient_address;
                    $txHistoryObj->senderUserID = $recipientUserID ? $recipientUserID : "";
                    $txHistoryObj->recipientUserID = "";
                    $txHistoryObj->walletType = $wallet_type;
                    $txHistoryObj->amount = $padded_amount;
                    $txHistoryObj->transactionType = "payment_gateway";
                    $txHistoryObj->referenceID = '';
                    $txHistoryObj->createdAt = $date;
                    $txHistoryObj->updatedAt = $date;
                    $txHistoryObj->type = 'in';
                    $txHistoryObj->gatewayType = "BC";
        
                    $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                    $fund_out_id = $transaction_history_result['transaction_history_id'];
                    $fund_out_table = $transaction_history_result['table_name'];

                    $updateWalletTx = array(
                        "transaction_history_table" => $fund_out_table,
                        "transaction_history_id" => $fund_out_id,
                    );

                    $db->where('id', $transaction_id);
                    $db->update('xun_wallet_transaction', $updateWalletTx);
                }
                else if($recipient_address_type == 'freecoin'){

                    $db->where('transaction_hash', $transaction_hash);
                    $db->where("recipient_address", $recipient_address);
                    $tx_hash_result = $db->getOne('xun_crypto_transaction_hash');

                    if($tx_hash_result){
                        if($status == "confirmed" || $status == 'failed'){
                            $address_type = "internal_transfer";
                            $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type, $padded_fee);
                        }
                        else{
                            return $padded_amount;
                        }

                        $transaction_id = $wallet_transaction_return["id"];
                    }
                    else{    
                        $insertData = array(
                            "transaction_hash" => $transaction_hash,
                            "ex_transaction_hash" => $ex_transaction_hash,
                            "sender_address" => $sender_address,
                            "recipient_address" => $recipient_address,
                            "amount" => $padded_amount,
                            "type" => $target,
                            "wallet_type" => strtolower($wallet_type),
                            "transaction_token" => $transaction_token ? $transaction_token : '',
                            "status" => $status == 'confirmed' ? 'completed' : $status,
                            "bc_reference_id" => $bc_reference_id,
                            "created_at" => $date
                        );
        
                        $row_id = $db->insert("xun_crypto_transaction_hash", $insertData);

                        if(!$row_id){
                            $log->write(date("Y-m-d H:i:s") . " check_internal_callback # Db error: " . $db->getLastError() . "\n");
                        }

                        if(!is_null($this->recipient_address_data)){
                            $recipientUserID = $this->recipient_address_data["user_id"];
                        }

                       
                        $address_type = "internal_transfer";
                      
                        $transactionObj->status = $status;
                        $transactionObj->transactionHash = $transaction_hash;
                        $transactionObj->transactionToken = "";
                        $transactionObj->senderAddress = $sender_address;
                        $transactionObj->recipientAddress = $recipient_address;
                        $transactionObj->userID = $recipientUserID ? $recipientUserID : '';
                        $transactionObj->senderUserID = '';
                        $transactionObj->recipientUserID = $recipientUserID;
                        $transactionObj->walletType = $wallet_type;
                        $transactionObj->amount = $padded_amount;
                        $transactionObj->addressType = $address_type;
                        $transactionObj->transactionType = $transaction_type;
                        $transactionObj->escrow = 0;
                        $transactionObj->referenceID = '';
                        $transactionObj->escrowContractAddress = '';
                        $transactionObj->createdAt = $date;
                        $transactionObj->updatedAt = $date;
                        $transactionObj->expiresAt = '';
                        $transactionObj->fee = $padded_fee;
                        $transactionObj->feeUnit = $feeUnit;
                        $transactionObj->exchangeRate = $exchange_rate;

                        $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

                        // $transactionObj->paymentID = $payment_id;
                        // $transactionObj->paymentMethodID = '';
                        $transactionObj->status = "pending";
                        $transactionObj->senderInternalAddress ='';
                        $transactionObj->senderExternalAddress = $sender_address;
                        $transactionObj->recipientInternalAddress = '';
                        $transactionObj->recipientExternalAddress = $recipient_address;
                        $transactionObj->senderUserID = '';
                        $transactionObj->recipientUserID = $recipientUserID;
                        $transactionObj->walletType = $wallet_type;
                        $transactionObj->amount = $padded_amount;
                        // $transactionObj->serviceChargeAmount = $service_charge_amount;
                        // $transactionObj->serviceChargeWalletType = $service_charge_wallet_type;
                        $transactionObj->referenceID = '';
                        $transactionObj->createdAt = $date;
            
                        $payment_details_id = $xunPayment->insert_payment_details($transactionObj);
                        $payment_details_ids[] = $payment_details_id;
        
                        $txHistoryObj->paymentDetailsID = $payment_details_id;
                        $txHistoryObj->status = "pending";
                        $txHistoryObj->transactionID = "";
                        $txHistoryObj->transactionToken = "";
                        $txHistoryObj->senderAddress = $sender_address;
                        $txHistoryObj->recipientAddress = $recipient_address;
                        $txHistoryObj->senderUserID = "";
                        $txHistoryObj->recipientUserID = "";
                        $txHistoryObj->walletType = $wallet_type;
                        $txHistoryObj->amount = $padded_amount;
                        $txHistoryObj->transactionType = "fund_in";
                        $txHistoryObj->referenceID = '';
                        $txHistoryObj->createdAt = $date;
                        $txHistoryObj->updatedAt = $date;
                        $txHistoryObj->type = 'in';
                        $txHistoryObj->gatewayType = "BC";
                        $txHistoryObj->isInternal = 1;
                        $txHistoryObj->fee = '';
                        $txHistoryObj->feeUnit = '';
            
                        $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                        $fund_out_id = $transaction_history_result['transaction_history_id'];
                        $fund_out_table = $transaction_history_result['table_name'];
        
                        $updatePaymentDetails = array(
                            "fund_out_table" => $fund_out_table,
                            "fund_out_id" => $fund_out_id
                        );
        
                        $db->where('id', $payment_details_id);
                        $db->update('xun_payment_details', $updatePaymentDetails);

                        $updateWalletTx = array(
                            "transaction_history_table" => $fund_out_table,
                            "transaction_history_id" => $fund_out_id,
                        );

                        $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);
                    }
                
                    if($status == 'confirmed'){
                        $tag = "Freecoin Topup";
                        $message = "Amount: ".$padded_amount."\n";
                        $message .= "Wallet Type:".$wallet_type."\n";
                        $message .= "Time: ".date("Y-m-d H:i:s")."\n";
    
                        $thenux_params["tag"]         = $tag;
                        $thenux_params["message"]     = $message;
                        $thenux_params["mobile_list"] = $xun_numbers;
                        $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                    }
                   
                }
                else if ($recipient_address == $swapCoinInternalAddress) {

                    // Swapcoin company receive
                    $wallet_transaction_record = $xunWallet->getWalletTransactionByTxHash($transaction_hash);                    
                    $message = $wallet_transaction_record['message'];
                    $reference_id = $wallet_transaction_record['reference_id'];                    
                    $address_type = "nuxpay_wallet";
                    $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                    $wallet_transaction_id = $wallet_transaction_return["id"];

                    $db->where('id', $reference_id);
                    $swapHistoryRes = $db->getOne('xun_swap_history');

                    if(!$swapHistoryRes){
                        $lq = $db->getLastQuery();
                        $tag = "Swap History Record Not found";
                        $message = "Swap ID:".$reference_id."\n";
                        $message .= "Last Query:".$lq."\n";
    
                        $erlang_params["tag"]         = $tag;
                        $erlang_params["message"]     = $message;
                        $erlang_params["mobile_list"] = $xun_numbers;
                        $xmpp_result                  = $general->send_thenux_notification($erlang_params, "thenux_issues");
                        return $padded_amount;
                    }
                    $receiver_user_id = $swapHistoryRes['business_id'];

                    $transactionObj->status = 'pending';
                    $transactionObj->transactionHash = '';
                    $transactionObj->transactionToken = "";
                    $transactionObj->senderAddress = $recipient_address;
                    $transactionObj->recipientAddress = $sender_address;
                    $transactionObj->userID = $receiver_user_id ? $receiver_user_id : '';
                    $transactionObj->senderUserID = 'swap_wallet';
                    $transactionObj->recipientUserID = $receiver_user_id;
                    $transactionObj->walletType = $swapHistoryRes['to_wallet_type'];
                    $transactionObj->amount = $swapHistoryRes['to_amount_display'];
                    $transactionObj->addressType = 'swap_coin';
                    $transactionObj->transactionType = $transaction_type;
                    $transactionObj->escrow = 0;
                    $transactionObj->referenceID = '';
                    $transactionObj->escrowContractAddress = '';
                    $transactionObj->createdAt = $date;
                    $transactionObj->updatedAt = $date;
                    $transactionObj->expiresAt = '';

                    $received_transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj); 

                    // $txHistoryObj->paymentDetailsID = $payment_details_id;
                    $txHistoryObj->status = 'pending';
                    $txHistoryObj->transactionID = '';
                    $txHistoryObj->transactionToken = '';
                    $txHistoryObj->senderAddress = $recipient_address;
                    $txHistoryObj->recipientAddress = $sender_address;
                    $txHistoryObj->senderUserID = 'swap_wallet';
                    $txHistoryObj->recipientUserID = $receiver_user_id;
                    $txHistoryObj->walletType = $swapHistoryRes['to_wallet_type'];
                    $txHistoryObj->amount = $swapHistoryRes['to_amount_display'];
                    $txHistoryObj->transactionType = 'swapcoin';
                    $txHistoryObj->referenceID = '';
                    $txHistoryObj->createdAt = $date;
                    $txHistoryObj->updatedAt = $date;
                    //    $txHistoryObj->fee = $final_miner_fee;
                    //    $txHistoryObj->feeWalletType = $miner_fee_wallet_type;
                    $txHistoryObj->exchangeRate = $exchangeRate;
                    $txHistoryObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
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
                    $xunWallet->updateWalletTransaction($received_transaction_id, $updateWalletTx);
   
                    $command = "swapcoinswap";

                    $crypto_params = array(
                        "businessID" => $swapHistoryRes['business_id'],
                        "businessName" => $swapHistoryRes['business_name'],
                        "referenceID" => $swapHistoryRes['reference_id'],
                        "fromAddress" => $sender_address,
                        "toAddress" => $recipient_address,
                        "internalTransactionID" => $transaction_hash,
                        "fromCreditName" => $swapHistoryRes['from_wallet_type'],
                        "fromSymbol" => $swapHistoryRes['from_symbol'],
                        "toCreditName" => $swapHistoryRes['to_wallet_type'],
                        "toSymbol" => $swapHistoryRes['to_symbol'],
                        "fromAmount" => $swapHistoryRes['from_amount'],
                        "toAmount" => $swapHistoryRes['to_amount'],
                        "toAmountRequest" => $swapHistoryRes['to_amount_display'],
                        "priceMarket" => $swapHistoryRes['price_market'],
                        "priceRequest" => $swapHistoryRes['price_display'],
                        "exchangeRateMarket" => $swapHistoryRes['exchange_rate_market'],
                        "exchangeRateRequest" => $swapHistoryRes['exchange_rate_display'],
                        "marginPercentage" => $swapHistoryRes['margin_percentage'],
                        "swapWalletTransactionID" => $received_transaction_id,    
                    );

                    $result = $post->curl_crypto($command, $crypto_params, 2);

                    if($result['status'] == 'error'){

                        $tag = "Swap BC Error";
                        $message = "Swap Reference ID: ".$swapHistoryRes['reference_id']."\n";
                        $message .= "Tx Hash:".$transaction_hash."\n";
                        $message .= "Business Name:".$business_name."\n";
                        $message .= "From Amount:".$swapHistoryRes['from_amount']."\n";
                        $message .= "To Amount:".$swapHistoryRes['to_amount']."\n";
                        $message .= "From Wallet Type:".$swapHistoryRes['from_wallet_type']."\n";
                        $message .= "To Wallet Type:".$swapHistoryRes['to_wallet_type']."\n";
    
                        $erlang_params["tag"]         = $tag;
                        $erlang_params["message"]     = $message;
                        $erlang_params["mobile_list"] = $xun_numbers;
                        $xmpp_result                  = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_issues");
                    }

                    $bc_reference_id = $params['referenceID'];
                    $insertWithdrawal = array(
                        "reference_id" => $bc_reference_id,
                        "business_id" => $receiver_user_id,
                        "sender_address" => $sender_address,
                        "recipient_address" => $recipient_address,
                        "amount" => $padded_amount,
                        "amount_receive" => $padded_amount,
                        "transaction_fee" => '0',
                        "miner_fee" => '0',
                        "transaction_hash" => $transaction_hash,
                        "wallet_type" => strtolower($wallet_type),
                        "status" => $status == 'confirmed' ? 'success' : $status,
                        "transaction_type" => 'swap_coin',
                        "escrow_id" => 0,
                        "created_at" => date("Y-m-d H:i:s"),
                        "updated_at" => date("Y-m-d H:i:s")
                    );

                    $db->insert('xun_payment_gateway_withdrawal', $insertWithdrawal);

                }
                    
                else if ($sender_address == $swapCoinInternalAddress) {
                    // Swapcoin company send
                    $wallet_transaction_record = $xunWallet->getWalletTransactionByTxHash($transaction_hash);       
                      
                    $message = $wallet_transaction_record['message'];
                    $reference_id = $wallet_transaction_record['reference_id'];         
                    $transaction_history_table = $wallet_transaction_record['transaction_history_table'];
                    $transaction_history_id = $wallet_transaction_record['transaction_history_id'];           
                    $address_type = "nuxpay_wallet";
                    $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                    $wallet_transaction_id = $wallet_transaction_return["id"];

                    $db->where('to_tx_id', $wallet_transaction_id);
                    $swapHistoryRes = $db->getOne('xun_swap_history');
                    $receiver_user_id = $swapHistoryRes['business_id'];

                    $updateSwapStatus = array(
                        "status" => $status == 'confirmed' ? 'completed' : $status
                    );

                    $db->where('to_tx_id', $wallet_transaction_id);
                    $db->update('xun_swap_history', $updateSwapStatus);

                    $updateAmount = array(
                        "amount"=> $padded_amount,
                        "wallet_type" => strtolower($wallet_type),
                    );

                    $db->where('id', $transaction_history_id);
                    $db->update($transaction_history_table, $updateAmount);

                    $bc_reference_id = $params['referenceID'];
                    $insertFundIn = array(
                        "business_id" => $receiver_user_id,
                        // "transaction_id" => $transaction_hash,
                        "reference_id" => $bc_reference_id,
                        "sender_address" => $sender_address,
                        "receiver_address" => $recipient_address,
                        "amount" => $padded_amount,
                        "amount_receive" => $padded_amount,
                        "transaction_fee" => '0',
                        "miner_fee" => '0',
                        "wallet_type" => strtolower($wallet_type),

                        // wentin test //
                        "transaction_target" => $target,
                        "transaction_id" => $received_tx_id ?: $transaction_hash,


                        "exchange_rate" => $exchange_rate,
                        "type" => "swap_coin",
                        "transaction_type" => "blockchain",
                        "status" => $status == 'confirmed' ? 'success' : $status,
                        "created_at" => date("Y-m-d H:i:s")
                    );

                    $fund_in_id = $db->insert('xun_payment_gateway_fund_in', $insertFundIn);
       
                    // Insert accounting
                    $insertTx = array(
                        "businessID" => $receiver_user_id,
                        "senderAddress" => $sender_address,
                        "recipientAddress" => $recipient_address,
                        "amount" => $padded_amount,
                        "amountSatoshi" => 0,
                        "walletType" => strtolower($wallet_type),
                        "credit" => $padded_amount,
                        "debit" => 0,
                        "transactionType" => 'swapcoin',
                        "referenceID" => $reference_id,
                        "transactionDate" => date("Y-m-d H:i:s"),
                    );
                    $txID = $account->insertXunTransaction($insertTx);

                    // $txHistoryObj->paymentDetailsID = $payment_details_id;
                    // $txHistoryObj->status = $status == 'confirmed' ? 'success' : $status;
                    // $txHistoryObj->transactionID = $transaction_hash;
                    // $txHistoryObj->transactionToken = $transaction_token;
                    // $txHistoryObj->senderAddress = $sender_address;
                    // $txHistoryObj->recipientAddress = $recipient_address;
                    // $txHistoryObj->senderUserID = $business_id;
                    // $txHistoryObj->recipientUserID = $receiver_user_id;
                    // $txHistoryObj->walletType = strtolower($wallet_type);
                    // $txHistoryObj->amount = $padded_amount;
                    // $txHistoryObj->transactionType = 'swapcoin';
                    // $txHistoryObj->referenceID = $reference_id;
                    // $txHistoryObj->createdAt = $date;
                    // $txHistoryObj->updatedAt = $date;
                    // $txHistoryObj->fee = $final_miner_fee;
                    // $txHistoryObj->feeWalletType = $miner_fee_wallet_type;
                    // $txHistoryObj->exchangeRate = $exchangeRate;
                    // $txHistoryObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                    // $txHistoryObj->type = 'out';
                    // $txHistoryObj->gatewayType = "BC";

                    // $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);

                    // if transaction token not null in db, means that this swap transaction
                    // is triggered by payment gateway (auto-swap)
                    if (strlen($swapHistoryRes['transaction_token']) != 0) {
                        $log->write("\n".date('Y-m-d')." Message - auto swap success callback. RecipientID: ".$receiver_user_id );

                        $db->where('transaction_token', $swapHistoryRes['transaction_token']);
                        $pgPaymentResult = $db->getOne('xun_payment_gateway_payment_transaction');

                        // get requested fiat currency details
                        $db->where('transaction_token', $swapHistoryRes['transaction_token']);
                        $fiatCurrencyDetails = $db->getOne('xun_payment_transaction', 'id, fiat_currency_id, fiat_currency_exchange_rate');

                        // get actual transaction details
                        // Note: one payment transaction can have multiple fundins
                        $db->where('swap_history_id', $swapHistoryRes['id']);
                        $pgPaymentDetail = $db->getOne('xun_payment_details');

                        // get received transaction details history
                        $db->where('id', $pgPaymentDetail['fund_in_id']);
                        $pgFundinDetailsHistory = $db->getOne($pgPaymentDetail['fund_in_table']);

                        // get sent transction details history
                        $db->where('id', $pgPaymentDetail['fund_in_id']);
                        $pgFundoutDetailsHistory = $db->getOne($pgPaymentDetail['fund_out_table']);

                        // get cryptocurrency rate
                        $db->where('cryptocurrency_id', strtolower($swapHistoryRes['to_wallet_type']), 'LIKE');
                        $cryptoCurrencyRate = $db->getOne('xun_cryptocurrency_rate');
                        // get latest requested fiat currency rate
                        $db->where('currency', $fiatCurrencyDetails['fiat_currency_id']);
                        $latestCurrencyExchangeRate = $db->getValue('xun_currency_rate', 'exchange_rate');

                        // get callback url
                        $db->where('user_id', $receiver_user_id);
                        $db->where('name', 'businessCallbackURL');
                        $recipientCallbackURL = $db->getValue('xun_user_setting', 'value');
                        $log->write("\n".date('Y-m-d')." Message - callback URL: ".$recipientCallbackURL );

                        // auto swap success callback to recipient
                        if ($recipientCallbackURL) {
                            // build callback data
                            $fromWalletType = $swapHistoryRes['from_wallet_type'];
                            $fromSymbol = $swapHistoryRes['from_symbol'];
                            $toWalletType = $swapHistoryRes['to_wallet_type'];
                            $toSymbol = $swapHistoryRes['to_symbol'];
                            $pgReferenceID = $pgPaymentDetail['reference_id'];
                            $paymentID = $pgPaymentResult['payment_id'];
                            $transactionToken = $pgPaymentResult['transaction_token'];
                            $swapStatus = ($swapHistoryRes['order_status'] == 'FILLED') ? 'success' : 'failed';
                            $auto_swap_fail_reason = ($swapHistoryRes['order_status'] == 'FILLED') ? '' : 'Swap order '.strtolower($swapHistoryRes['order_status']);
                            $pgReceivedTxID = $pgFundinDetailsHistory['transaction_id'];
                            $pgSender = $pgPaymentDetail['sender_external_address'];
                            $pgRecipient = $pgPaymentDetail['recipient_external_address'];
                            $pgReceivedAmount = $pgFundinDetailsHistory['amount'];
                            $pgReceivedExchangeRate = $pgFundinDetailsHistory['exchange_rate']; // USD
                            $pgReceivedExchangeRate = bcmul($pgReceivedExchangeRate, $fiatCurrencyDetails['fiat_currency_exchange_rate'], 8); // Requested currency rate
                            $pgReceivedFiatCurrency = $fiatCurrencyDetails['fiat_currency_id'] ? strtoupper($fiatCurrencyDetails['fiat_currency_id']) : 'USD';
                            $pgReceivedFiatAmount = bcmul($pgReceivedAmount, $pgReceivedExchangeRate, 2);
                            $pgTxID = $pgFundoutDetailsHistory['transaction_id'];
                            $pgAmount = $pgPaymentDetail['amount'];
                            $pgFiatAmount = bcmul($pgAmount, $pgReceivedExchangeRate, 2);
                            $toExchangeRate = $cryptoCurrencyRate['value'] ? $cryptoCurrencyRate['value'] : '';
                            $toExchangeRate = bcmul($toExchangeRate, $latestCurrencyExchangeRate, 8);
                            $toFiatAmount = bcmul($padded_amount,$toExchangeRate, 2);

                            $autoswapCallbackData = array(
                                'referenceID' => $pgReferenceID,
                                'paymentTxID' => $paymentID,
                                'transactionToken' => $transactionToken,
                                'transactionDate' => date('Y-m-d H:i:s'),
                                'status' => $swapStatus,
                                'statusMsg' => $auto_swap_fail_reason,
                                'receivedTxDetails' => array(
                                    'txID' => $pgTxID,
                                    'address' => $pgPaymentResult['address'],
                                    'amount' => $pgAmount,
                                    'unit' => $fromSymbol,
                                    'type' => $fromWalletType,
                                    'fiatAmount' => $pgFiatAmount,
                                    'fiatUnit' => $pgReceivedFiatCurrency,
                                    'exchangeRate' => $pgReceivedExchangeRate,
                                ),
                                'swapTxDetails' => array(
                                    'txID' => '',
                                    'address' => '',
                                    'amount' => ($swapStatus == 'success') ? $padded_amount : '',
                                    'unit' => $toSymbol,
                                    'type' => $toWalletType,
                                    'fiatAmount' => ($swapStatus == 'success') ? $toFiatAmount : '',
                                    'fiatUnit' => $pgReceivedFiatCurrency,
                                    'exchangeRate' => ($swapStatus == 'success') ? $toExchangeRate : '',
                                ),
                                'paymentTxDetails' => array(
                                    'txID' => $pgReceivedTxID,
                                    'sender' => $pgSender,
                                    'recipient' => $pgRecipient,
                                    'address' => $pgPaymentResult['address'],
                                    'amount' => $pgReceivedAmount,
                                    'unit' => $fromSymbol,
                                    'type' => $fromWalletType,
                                    'fiatAmount' => $pgReceivedFiatAmount,
                                    'fiatUnit' => $pgReceivedFiatCurrency,
                                    'exchangeRate' => $pgReceivedExchangeRate
                                ),
                            );
        
                            // callback
                            $callbackurl = $recipientCallbackURL;
                            $curl_header[] = "Content-Type: application/json";
                            $curl_params = array(
                                "command" => "autoSwapCallback",
                                "params" => $autoswapCallbackData
                            );

                            $log->write("\n".date('Y-m-d')." Debug - Autoswap callback params ".json_encode($curl_params));
                            $cryptoResult = $post->curl_post($callbackurl, $curl_params, 0, 1, $curl_header);
                            $log->write("\n".date('Y-m-d')." Debug - Autoswap callback result ".json_encode($cryptoResult));

                            $webservice->developerOutgoingWebService($receiver_user_id, "autoSwapCallback", $callbackurl, json_encode($curl_params), json_encode($cryptoResult) );

                            // final transfer to actual destination, use withdrawal function
                            // first, trace for the actual destination address, skip fundout if not exit
                            $log->write("\n".date('Y-m-d')." Debug - fiatCurrencyDetails ".json_encode($fiatCurrencyDetails['id']));

                            $db->where('payment_tx_id', $fiatCurrencyDetails['id']);
                            $db->where('wallet_type', $toWalletType);
                            $pgAddress = $db->getValue('xun_payment_method', 'address');
                            $log->write("\n".date('Y-m-d')." Message - Looking for actual destination address, PG address: ".$pgAddress);

                            if ($pgAddress) {
                                $db->where('crypto_address', $pgAddress);
                                $actualDestinationAddress = $db->getValue('xun_crypto_address', 'destination_address');
                                $log->write("\n".date('Y-m-d')." Debug - Actual destination: ".$actualDestinationAddress);

                                if ($actualDestinationAddress) {
                                    $log->write("\n".date('Y-m-d')." Message - Actual destination found, perform fund out.");
                                    
                                    $fundOutParams = array(
                                        "business_id" => $receiver_user_id,
                                        "wallet_type" => $toWalletType,
                                        "amount" => $padded_amount,
                                        "destination_address" => $actualDestinationAddress
                                    );
                                    $log->write("\n".date('Y-m-d')." Debug - Fundout params ". json_encode($fundOutParams));
                                    $fundOutResult = $xunPaymentGateway->create_nuxpay_invoice_withdrawal($fundOutParams);
                                    $log->write("\n".date('Y-m-d')." Debug - Fundout result ". json_encode($fundOutResult));

                                }
                            }

                        }

                    }

                }
                else if($recipient_address == $escrowInternalAddress){
                    
                    // escrow                                        
                    $wallet_transaction_record = $xunWallet->getWalletTransactionByTxHash($transaction_hash);                    
                    $message = $wallet_transaction_record['message'];
                    $reference_id = $wallet_transaction_record['reference_id'];                    
                    $address_type = "nuxpay_wallet";
                    $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                    $wallet_transaction_id = $wallet_transaction_return["id"];

                    if($message == 'send_escrow'){
                        $db->where('id', $reference_id);
                        $send_fund = $db->getOne('xun_payment_gateway_send_fund');
                        if(!$send_fund){
                            return $padded_amount;
                        }

                        if($send_fund['status'] != 'pending' || $send_fund['status'] == 'ready'){
                            return $padded_amount;
                        }

                        $recipient_mobile_number = $send_fund['recipient_mobile_number'];
                        $recipient_email_address = $send_fund['recipient_email_address'];
                        $business_id = $send_fund['business_id'];

                        $tx_type = $send_fund['tx_type'];

                        if($recipient_email_address){
                            $db->where('username', $recipient_email_address);
                        }
                        
                        if($recipient_mobile_number){
                            $db->where('email', $recipient_mobile_number);
                        }                        

                        $receiver_user_data = $db->getOne('xun_user');

                        $receiver_user_id = $receiver_user_data['id'];
                        $update_status = array(
                            "status" => $status == 'confirmed' ? 'ready' : 'failed',
                            "updated_at" => date("Y-m-d H:i:s")
                        );

                        $db->where('id', $reference_id);
                        $updated = $db->update('xun_payment_gateway_send_fund', $update_status);

                        $bc_reference_id = $params['referenceID'];                        

                        // get escrow id
                        $db->where('reference_id', $send_fund['id']);
                        $db->where('tx_type', 'send');
                        $escrow_table = $db->getOne('xun_escrow');

                        if ($escrow_table && $tx_type != "redeem_code") {
                            $insertWithdrawal['escrow_id'] = $escrow_table['id'];
                        }

                        if($tx_type == "redeem_code") {
                            // update 

                            // $db->where('transaction_hash', $escrow_table['receive_tx_hash']);
                            // $withdrawal_table = $db->getOne('xun_payment_gateway_withdrawal');
                            // $insertWithdrawal['sender_address'] = $withdrawal_table['sender_address'];

                            $updateWithdrawal = array(
                                "reference_id" => $bc_reference_id,                                
                                "recipient_address" => $recipient_address,
                                "transaction_hash" => $transaction_hash,
                                "status" => $status == 'confirmed' ? 'escrow' : $status,
                                "updated_at" => date("Y-m-d H:i:s")
                            );      
                            $db->where('escrow_id', $escrow_table['id'] );
                            $db->update('xun_payment_gateway_withdrawal', $updateWithdrawal);
                            
                        } else {

                            $db->where('tx_type','send');
                            $db->where('reference_id', $send_fund['id']);
                            $escrow_table = $db->getOne('xun_escrow');

                            $insertWithdrawal = array(
                                "reference_id" => $bc_reference_id,
                                "business_id" => $business_id,
                                "sender_address" => $sender_address,
                                "recipient_address" => $recipient_address,
                                "amount" => $padded_amount,
                                "amount_receive" => $padded_amount,
                                "transaction_fee" => '0',
                                "miner_fee" => '0',
                                "transaction_hash" => $transaction_hash,
                                "wallet_type" => strtolower($wallet_type),
                                "status" => $status == 'confirmed' ? 'Escrow' : $status,
                                "transaction_type" => 'send_fund',
                                "escrow_id" => $escrow_table['id'],
                                "created_at" => date("Y-m-d H:i:s"),
                                "updated_at" => date("Y-m-d H:i:s")
                            );
                            $db->insert('xun_payment_gateway_withdrawal', $insertWithdrawal);
                        }

                        

                        // update xun_escrow
                        $updateEscrowData = array(                            
                            'receive_tx_hash'          => $transaction_hash,
                            'status'          => $status == 'confirmed' ? 'ready' : 'failed',
                            "updated_at" => date("Y-m-d H:i:s")
                        );
                        
                        $db->where('reference_id', $reference_id);
                        $db->where('tx_type','send');
                        $db->update('xun_escrow', $updateEscrowData);

                        // nened invoice for this?
                    }
                }
                else if($recipient_address_type == 'redeem_code'){
                    
                    $wallet_transaction_record = $xunWallet->getWalletTransactionByTxHash($transaction_hash);
            
                    $message = $wallet_transaction_record['message'];
                    $reference_id = $wallet_transaction_record['reference_id'];

                    $address_type = "nuxpay_wallet";
                    $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                    $wallet_transaction_id = $wallet_transaction_return["id"];                    
                    if($message == 'send_fund'){
                        $db->where('id', $reference_id);
                        $send_fund = $db->getOne('xun_payment_gateway_send_fund');
                        if(!$send_fund){
                            return $padded_amount;
                        }

                        if($send_fund['status'] != 'pending' || $send_fund['status'] == 'activated'){
                            return $padded_amount;
                        }

                        $recipient_mobile_number = $send_fund['recipient_mobile_number'];
                        $recipient_email_address = $send_fund['recipient_email_address'];
                        $business_id = $send_fund['business_id'];
                        $escrow = $send_fund['escrow'];

                        if($recipient_email_address){
                            $db->where('username', $recipient_email_address);
                        }
                        
                        if($recipient_mobile_number){
                            $db->where('email', $recipient_mobile_number);
                        }

                        $receiver_user_data = $db->getOne('xun_user');

                        $receiver_user_id = $receiver_user_data['id'];
                        $update_status = array(
                            "status" => $status == 'confirmed' ? 'activated' : 'failed',
                            "updated_at" => date("Y-m-d H:i:s")
                        );

                        $db->where('id', $reference_id);
                        $updated = $db->update('xun_payment_gateway_send_fund', $update_status);

                        if ($escrow) {
                            $db->where('reference_id', $send_fund['id']);
                            $db->where('tx_type', 'send');
                            $escrow_table = $db->getOne('xun_escrow');
                        }

                        $bc_reference_id = $params['referenceID'];
                        $insertWithdrawal = array(
                            "reference_id" => $bc_reference_id,
                            "business_id" => $business_id,
                            "sender_address" => $sender_address,
                            "recipient_address" => $recipient_address,
                            "amount" => $padded_amount,
                            "amount_receive" => $padded_amount,
                            "transaction_fee" => '0',
                            "miner_fee" => '0',
                            "transaction_hash" => $transaction_hash,
                            "wallet_type" => strtolower($wallet_type),
                            "status" => $status == 'confirmed' ? 'success' : $status,
                            "transaction_type" => 'send_fund',
                            "created_at" => date("Y-m-d H:i:s"),
                            "updated_at" => date("Y-m-d H:i:s")
                        );

                        if ($escrow && $escrow_table) {
                            $insertWithdrawal['escrow_id'] = $escrow_table['id'];
                            $insertWithdrawal['status'] = $status == 'confirmed' ? 'prepaid' : $status;//overwrite
                        }

                        $db->insert('xun_payment_gateway_withdrawal', $insertWithdrawal);

                        if($send_fund['escrow'] == '1') {
                            // update xun_escrow
                            $update_status = array(
                                "status" => $status == 'confirmed' ? 'ready' : 'failed',                            
                                "receive_tx_hash" => $transaction_hash,
                                "updated_at" => date("Y-m-d H:i:s")
                            );             
                            
                            $db->where('reference_id', $send_fund['id']);
                            $db->where('tx_type', 'send');                        
                            $updated = $db->update('xun_escrow', $update_status);
                        }

                    }
                }
                else if($sender_address_type == 'redeem_code'){
                    $wallet_transaction_record = $xunWallet->getWalletTransactionByTxHash($transaction_hash);
                    
                    $message = $wallet_transaction_record['message'];
                    $reference_id = $wallet_transaction_record['reference_id'];

                    $address_type = "nuxpay_wallet";
                    $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                    $wallet_transaction_id = $wallet_transaction_return["id"];                    
                    if($message = "redeem_code"){
                        $db->where('id', $reference_id);
                        $db->where('status', 'redeemed');
                        $send_fund = $db->getOne('xun_payment_gateway_send_fund');
                        if(!$send_fund){
                            return $padded_amount;
                        }

                        $send_fund_id = $send_fund['id'];
                        $recipient_mobile_number = $send_fund['recipient_mobile_number'];
                        $recipient_email_address = $send_fund['recipient_email_address'];
                        $business_id = $send_fund['business_id'];
                        $receiver_user_id = $send_fund['redeemed_by'];

                        $db->where('id', $business_id);
                        $db->where('disabled', 0);
                        $db->where('type', 'business');
                        $sender_user_data = $db->getOne('xun_user');

                        $source = $sender_user_data['register_site'];
                        if($recipient_email_address){
                            $db->where('email', $recipient_email_address);
                        }
                        
                        if($recipient_mobile_number){
                            $db->where('username', $recipient_mobile_number);
                        }
                        $db->where('register_site', $source);
                        $receiver_user_data = $db->getOne('xun_user');

                        $update_status = array(
                            "status" => $status == 'confirmed' ? 'success' : 'failed',
                            "redeemed_at" => date("Y-m-d H:i:s"),
                            "updated_at" => date("Y-m-d H:i:s")
                        );

                        $db->where('id', $reference_id);
                        $updated = $db->update('xun_payment_gateway_send_fund', $update_status);

                        $bc_reference_id = $params['referenceID'];
                        $insertFundIn = array(
                            "business_id" => $receiver_user_id,
                            //"transaction_id" => $transaction_hash,
                            "reference_id" => $bc_reference_id,
                            "sender_address" => $sender_address,
                            "receiver_address" => $recipient_address,
                            "amount" => $padded_amount,
                            "amount_receive" => $padded_amount,
                            "transaction_fee" => '0',
                            "miner_fee" => '0',
                            "wallet_type" => strtolower($wallet_type),
                            "exchange_rate" => $exchange_rate,
                            "type" => "redeem_code",

                            //wentin test//
                            "transaction_target" => $target,
                            "transaction_id" => $received_tx_id ?: $transaction_hash,

                            "transaction_type" => "blockchain",
                            "status" => $status == 'confirmed' ? 'success' : $status,
                            "created_at" => date("Y-m-d H:i:s")
                        );
                        
                        $fund_in_id = $db->insert('xun_payment_gateway_fund_in', $insertFundIn);
                        
                        $balance = $xunPaymentGateway->get_user_balance($receiver_user_id, $wallet_type);
                        $new_balance = bcadd($balance, $padded_amount,8);
                        $satoshi_amount = $this->get_satoshi_amount($wallet_type, $padded_amount);
                        $insertData = array(
                            "business_id" => $receiver_user_id,
                            "sender_address" => $sender_address,
                            "recipient_address" => $recipient_address,
                            "amount" => $padded_amount,
                            "amount_satoshi" => $satoshi_amount,
                            "wallet_type" => strtolower($wallet_type),
                            "credit" => $padded_amount,
                            "debit" => '0',
                            "balance" => $new_balance,
                            "reference_id" => $send_fund_id,
                            "transaction_type" => "redeem_code",
                            "created_at" => date("Y-m-d H:i:s"),
                            "gw_type" => "BC",
                            "transaction_hash" => $transaction_hash
                        );
    
                        $invoice_tx_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertData);

                        $receiver_name = $receiver_user_data['nickname'];
                        $sender_name = $sender_user_data['nickname'];
                        $tag = "Redeem PIN";
                        $message = "Business Name: ".$sender_name."\n";
                        $message .= "Redeemed By: ".$receiver_name."\n";
                        $message .= "Tx Hash: ".$transaction_hash."\n";
                        $message .= "Amount:" .$padded_amount."\n";
                        $message .= "Wallet Type:".$wallet_type."\n";
                        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                        $thenux_params["tag"]         = $tag;
                        $thenux_params["message"]     = $message;
                        $thenux_params["mobile_list"] = $xun_numbers;
                        $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                        
                    }
                } 
                else if($sender_address == $escrowInternalAddress) {
                    // escrow release                    
                    $wallet_transaction_record = $xunWallet->getWalletTransactionByTxHash($transaction_hash);

                    $message = $wallet_transaction_record['message'];
                    $reference_id = $wallet_transaction_record['reference_id'];
                    $address_type = "nuxpay_wallet";
                    $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                    $wallet_transaction_id = $wallet_transaction_return["id"];
                    
                    if($message == "release_escrow"){
                        $db->where('id', $reference_id);
                        $send_fund = $db->getOne('xun_payment_gateway_send_fund');                        
                        if(!$send_fund){
                            return $padded_amount;
                        }

                        if ($send_fund['tx_type'] == 'redeem_code') {
                            $send_fund_id = $send_fund['id'];
                            $db->where('id', $send_fund['redeemed_by']);                                                                               
                            $xun_user_receiver = $db->getOne('xun_user');
                        } else {
                            $send_fund_id = $send_fund['id'];
                            $recipient_mobile_number = $send_fund['recipient_mobile_number'];
                            $recipient_email_address = $send_fund['recipient_email_address'];
                            $business_id = $send_fund['business_id'];
                            
                            if($send_fund['recipient_mobile_number']) {
                                $db->where('username', $send_fund['recipient_mobile_number']);                            
                            } else if ($send_fund['recipient_email_address']) {
                                $db->where('email', $send_fund['recipient_email_address']);                            
                            } else {
                                return $padded_amount;
                            }

                            // $db->where('register_site', $source);                        
                            $xun_user_receiver = $db->getOne('xun_user');                        
                        }                                            
                        
                        $receiver_user_id = $xun_user_receiver['id'];
                        
                        $db->where('id', $business_id);
                        $db->where('disabled', 0);
                        $db->where('type', 'business');
                        $sender_user_data = $db->getOne('xun_user');

                        $update_status = array(
                            "status" => $status == 'confirmed' ? 'success' : 'failed',                            
                            "updated_at" => date("Y-m-d H:i:s")
                        );                        
                        // update xun_payment_gateway_send_fund
                        $db->where('id', $reference_id);
                        $updated = $db->update('xun_payment_gateway_send_fund', $update_status);                        

                        // update xun_escrow
                        $update_status = array(
                            "status" => $status == 'confirmed' ? 'success' : 'failed',                            
                            "release_tx_hash" => $transaction_hash,
                            "updated_at" => date("Y-m-d H:i:s")
                        );             
                        
                        $db->where('reference_id', $send_fund['id']);
                        $db->where('tx_type', 'send');                        
                        $updated = $db->update('xun_escrow', $update_status);
                    
                        $bc_reference_id = $params['referenceID'];
                        
                        // old
                        // already inserted, so user can see that money is actually coming in

                        // $insertFundIn = array(
                        //     "business_id" => $receiver_user_id,
                        //     "transaction_id" => $transaction_hash,
                        //     "reference_id" => $bc_reference_id,
                        //     "sender_address" => $sender_address,
                        //     "receiver_address" => $recipient_address,
                        //     "amount" => $padded_amount,
                        //     "amount_receive" => $padded_amount,
                        //     "transaction_fee" => '0',
                        //     "miner_fee" => '0',
                        //     "wallet_type" => strtolower($wallet_type),
                        //     "exchange_rate" => $exchange_rate,
                        //     "type" => "release_escrow",
                        //     "transaction_type" => "blockchain",
                        //     "created_at" => date("Y-m-d H:i:s")
                        // );                        
                        
                        // $fund_in_id = $db->insert('xun_payment_gateway_fund_in', $insertFundIn);

                        $db->where('reference_id', $send_fund_id);
                        $db->where('tx_type', 'send');
                        $escrow_table = $db->getOne('xun_escrow');

                        
                        // update xun_payment_gateway_withdrawal
                        $update_status = array(
                            "status" => $status == 'confirmed' ? 'success' : 'failed',                            
                            "updated_at" => date("Y-m-d H:i:s")
                        );
                        $db->where('escrow_id', $escrow_table['id']);
                        $db->update('xun_payment_gateway_withdrawal',$update_status);

                        $updateFundIn = array(
                            "transaction_id" => $transaction_hash,
                            "reference_id"  => $bc_reference_id,
                            "exchange_rate" => $exchange_rate,
                            "status" => "success",
                            "type" => "release_escrow",
                        );

                        $db->where('escrow_id', $escrow_table['id']);
                        $db->update('xun_payment_gateway_fund_in', $updateFundIn);
                        
                        $balance = $xunPaymentGateway->get_user_balance($receiver_user_id, $wallet_type);
                        $new_balance = bcadd($balance, $padded_amount,8);
                        $satoshi_amount = $this->get_satoshi_amount($wallet_type, $padded_amount);
                        $insertData = array(
                            "business_id" => $receiver_user_id,
                            "sender_address" => $sender_address,
                            "recipient_address" => $recipient_address,
                            "amount" => $padded_amount,
                            "amount_satoshi" => $satoshi_amount,
                            "wallet_type" => strtolower($wallet_type),
                            "credit" => $padded_amount,
                            "debit" => '0',
                            "balance" => $new_balance,
                            "reference_id" => $send_fund_id,
                            "transaction_type" => "release_escrow",
                            "created_at" => date("Y-m-d H:i:s"),
                            "gw_type" => "BC",
                            "transaction_hash" => $transaction_hash
                        );                        
    
                        $invoice_tx_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertData);

                        $receiver_name = $receiver_user_data['nickname'];
                        $sender_name = $sender_user_data['nickname'];
                        $tag = "Escrow";
                        $message = "Business Name: ".$sender_name."\n";
                        $message .= "Redeemed By: ".$receiver_name."\n";
                        $message .= "Tx Hash: ".$transaction_hash."\n";
                        $message .= "Amount:" .$padded_amount."\n";
                        $message .= "Wallet Type:".$wallet_type."\n";
                        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                        $thenux_params["tag"]         = $tag;
                        $thenux_params["message"]     = $message;
                        $thenux_params["mobile_list"] = $xun_numbers;
                        $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                    }
                }
                else if($sender_address_type == "miner_pool"){
                    $address_type = "nuxpay_wallet";
                    $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                }
                else{
                    $db->where('address', $recipient_address);
                    $db->where('active', 1);
                    $user_internal_address = $db->getOne('xun_crypto_user_address');
                    if ($recipient_address == $setting->systemSetting['escrowInternalAddress']) {

                    } else if(!$user_internal_address){
                        return $padded_amount;
                    }

                    $transactionToken = $params["transactionToken"];

                    if($transactionToken){
                        $db->where('crypto_transaction_token', $transactionToken);
                        // $db->where('payment_type', 'zero_fee');
                        $pg_payment_tx_data = $db->getOne('xun_payment_gateway_payment_transaction');

                        $business_id = $pg_payment_tx_data['business_id'];
                        
                        $pg_transaction_token = $pg_payment_tx_data['transaction_token'];
                        //send callback to ttwo
                        $db->where("user_id", $business_id);
                        $business_result = $db->getOne("xun_business");

                        $db->where('transaction_token', $transactionToken);
                        $payment_details_data = $db->getOne('xun_payment_details');
                        
                        if($pg_payment_tx_data && $payment_details_data){
                            $client_reference_id = $pg_payment_tx_data['reference_id'] ? $pg_payment_tx_data : 0;

                            $update_status = array(
                                "status" => $status == 'confirmed' ? 'success' : $status
                            );

                            $db->where('crypto_transaction_token', $transactionToken);
                            $db->update('xun_payment_gateway_payment_transaction', $update_status);

                            $payment_id = $payment_details_data['payment_id'];

                            if($business_result['pg_callback_url']){
                                $this->post_nuxpay_wallet_callback($params, $pg_transaction_token, $business_result['pg_callback_url'], $payment_id, $business_id);

                            }
                            
                        }

                    }

                    $internal_address = $user_internal_address['address'];
                    $user_id = $user_internal_address['user_id'];

                    $db->where('id', $user_id);
                    $xun_user = $db->getOne('xun_user');

                    $user_type = $xun_user['type'];
                    
                    $address_type = "nuxpay_wallet";
                    $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type);
                    $wallet_transaction_id = $wallet_transaction_return["id"];
                    $reference_id = $wallet_transaction_return['reference_id'];
                    $message = $wallet_transaction_return['message'];

                    if($message == 'send_fund'){
                        $db->where('id', $reference_id);
                        $send_fund_data = $db->getOne('xun_payment_gateway_send_fund');
                        if($send_fund_data){
                            if($status == 'confirmed'){
                                $update_status = array(
                                    "status" => $status == 'confirmed' ? 'success' : $status,
                                    "redeemed_by" => $user_id,
                                    "updated_at" => date("Y-m-d H:i:s")
                                );

                                $db->where('id', $reference_id);
                                $updated = $db->update('xun_payment_gateway_send_fund', $update_status);

                                $bc_reference_id = $params['referenceID'];
                                $insertFundIn = array(
                                    "business_id" => $user_id,
                                    // "transaction_id" => $transaction_hash,
                                    "reference_id" => $bc_reference_id,
                                    "sender_address" => $sender_address,
                                    "receiver_address" => $recipient_address,
                                    "amount" => $padded_amount,
                                    "amount_receive" => $padded_amount,
                                    "transaction_fee" => '0',
                                    "miner_fee" => '0',

                                    // wentin //
                                    "transaction_target" => $target,
                                    "transaction_id" => $received_tx_id ?: $transaction_hash,


                                    "wallet_type" => strtolower($wallet_type),
                                    "exchange_rate" => $exchange_rate,
                                    "type" => "receive_fund",
                                    "transaction_type" => "blockchain",
                                    "status" => $status == 'confirmed' ? 'success' : $status,
                                    "created_at" => date("Y-m-d H:i:s")
                                );
                                
                                $fund_in_id = $db->insert('xun_payment_gateway_fund_in', $insertFundIn);
                              

                                $balance = $xunPaymentGateway->get_user_balance($user_id, $wallet_type);
                                $new_balance = bcadd($balance, $padded_amount,8);
                                $satoshi_amount = $this->get_satoshi_amount($wallet_type, $padded_amount);

                                $insertData = array(
                                    "business_id" => $user_id,
                                    "sender_address" => $sender_address,
                                    "recipient_address" => $recipient_address,
                                    "amount" => $padded_amount,
                                    "amount_satoshi" => $amount,
                                    "wallet_type" => strtolower($wallet_type),
                                    "credit" => $padded_amount,
                                    "debit" => '0',
                                    "balance" => $new_balance,
                                    "reference_id" => $reference_id,
                                    "transaction_type" => "receive_fund",
                                    "created_at" => date("Y-m-d H:i:s"),
                                    "gw_type" => "BC",
                                    "transaction_hash" => $transaction_hash
                                );
            
                                $request_fund_tx_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertData);

                                $db->where('transaction_hash', $transaction_hash);
                                $walletTransaction = $db->getOne('xun_wallet_transaction');
                                $business_id = $walletTransaction['user_id'];
                                $escrow_id = $walletTransaction['escrow'];

                                $insertWithdrawal = array(
                                    "reference_id" => $bc_reference_id,
                                    "business_id" => $business_id,
                                    "sender_address" => $sender_address,
                                    "recipient_address" => $recipient_address,
                                    "amount" => $padded_amount,
                                    "amount_receive" => $padded_amount,
                                    "transaction_fee" => '0',
                                    "miner_fee" => '0',
                                    "transaction_hash" => $transaction_hash,
                                    "wallet_type" => strtolower($wallet_type),
                                    "status" => $status == 'confirmed' ? 'success' : $status,
                                    "transaction_type" => 'send_fund',
                                    "escrow_id" => $escrow_id,
                                    "created_at" => date("Y-m-d H:i:s"),
                                    "updated_at" => date("Y-m-d H:i:s")
                                );

                                $db->insert('xun_payment_gateway_withdrawal', $insertWithdrawal);

                            }
                        }                           
                    } else{
                        $db->where('reference_id', $wallet_transaction_id);
                        $db->where('type', 'fund_in');
                        $db->where('reference_table', 'xun_wallet_transaction');
                        $miner_fee_transaction = $db->getOne('xun_miner_fee_transaction');

                        if($miner_fee_transaction){
    
                            $miner_fee_transaction_id = $miner_fee_transaction['id'];
    
                            $tx_data = array(
                                "miner_fee_transaction_id" => $miner_fee_transaction_id,
                                "miner_fee_reference_id" => $reference_id,
                                "internal_address" => $internal_address,
                                "user_id" => $user_id,
                                "miner_fee_wallet_transaction_id" => $wallet_transaction_id,
                            );
                            $this->process_withdrawal($tx_data);
                        }
                    }
                    
                }
            }
            return $padded_amount;
        }

        function get_user_device_info($sender, $recipient = null)
        {
            $db = $this->db;

            $mobile_arr = $recipient ? [$sender, $recipient] : [$sender];
            $db->where("mobile_number", $mobile_arr, "in");
            $device_infos = $db->map("mobile_number")->ObjectBuilder()->get("xun_user_device", null, "id, mobile_number, app_version, os");

            $sender_device_info = (array)$device_infos[$sender];
            $sender_device = $sender_device_info["os"];
            if ($sender_device == 1){$sender_device = "Android";}
            else if ($sender_device == 2){$sender_device = "iOS";}
            if($recipient){
                $recipient_device_info = (array)$device_infos[$recipient];
                $recipient_device = $recipient_device_info["os"];
                if ($recipient_device == 1){$recipient_device = "Android";}
                else if ($recipient_device == 2){$recipient_device = "iOS";}

                return array($sender => $sender_device, $recipient => $recipient_device);
            }
            
            return array($sender => $sender_device);
        }

        public function get_translation_message($message_code)
        {
            // Language Translations.
            global $general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();

            $message = $translations[$message_code][$language];
            return $message;
        }

        private function update_crypto_transaction_hash($params, $padded_amount){
            $db = $this->db;
            
            $transaction_hash = $params["transactionHash"];
            $ex_transaction_hash = trim($params["exTransactionHash"]);
            $target = $params["target"]; //internal or external
            $status = $params['status'];
            $bc_reference_id = $params['referenceID'];

            $db->where("transaction_hash", $transaction_hash);
            $transaction_hash_record = $db->getOne("xun_crypto_transaction_hash", "id");
            if(!$transaction_hash_record){
                $date = date("Y-m-d H:i:s");

                $recipient_address = $params["recipient"];
                $sender_address = $params["sender"];
                $wallet_type = $params["wallet_type"];
                $transaction_token = $params["transactionToken"];
                $exchange_rate = implode(":",  $params['exchangeRate']);

                $insertData = array(
                    "transaction_hash" => $transaction_hash,
                    "ex_transaction_hash" => $ex_transaction_hash,
                    "sender_address" => $sender_address,
                    "recipient_address" => $recipient_address,
                    "amount" => $padded_amount,
                    "type" => $target,  
                    "wallet_type" => strtolower($wallet_type),
                    "transaction_token" => $transaction_token ? $transaction_token : '',
                    "status" => $status == 'confirmed' ? 'completed' : $status,
                    "exchange_rate" => $exchange_rate ? $exchange_rate : '0',
                    "bc_reference_id" => $bc_reference_id ? $bc_reference_id : '0',
                    "created_at" => $date
                );

                $row_id = $db->insert("xun_crypto_transaction_hash", $insertData);
                // if (!$row_id) print_r($db);
            }

            return $row_id ? $row_id : $transaction_hash_record["id"];
        }

        private function update_wallet_transaction($params, $paddedAmount, $addressType, $paddedFee = ''){
            $db = $this->db;
            
            $senderAddress = $params["sender"];
            $recipientAddress = $params["recipient"];
            $transactionHash = $params["transactionHash"];
            $transactionToken = $params["transactionToken"];
            $walletType = $params["wallet_type"];
            $transactionType = $params["type"];
            $target = trim($params["target"]);
            $exTransactionHash = trim($params["exTransactionHash"]);
            $feeUnit = $params["feeUnit"];
            $status = $params["status"];
            $minerFeeExchangeRate = $params['minerFeeExchangeRate'];
            $exchangeRate =  implode(":",  $params['exchangeRate']);
            $bcReferenceID = $params['referenceID'];

            if($transactionHash == ''){
                return;
            }
            $this->update_crypto_transaction_hash($params, $paddedAmount);

            if(!is_null($this->sender_address_data)){
                $senderUserID = $this->sender_address_data["user_id"];
            }

            if(!is_null($this->recipient_address_data)){
                $recipientUserID = $this->recipient_address_data["user_id"];
            }

            $transactionObj = new stdClass();
            $transactionObj->transactionHash = $transactionHash;
            $transactionObj->transactionToken = $transactionToken;
            $transactionObj->senderAddress = $senderAddress;
            $transactionObj->recipientAddress = $recipientAddress;
            $transactionObj->userID = $userID ? $userID : '';
            $transactionObj->senderUserID = $senderUserID;
            $transactionObj->recipientUserID = $recipientUserID;
            $transactionObj->walletType = $walletType;
            $transactionObj->amount = $paddedAmount;
            $transactionObj->transactionType = $transactionType;
            $transactionObj->addressType = $addressType;
            $transactionObj->exTransactionHash = $exTransactionHash;
            $transactionObj->fee = $paddedFee;
            $transactionObj->feeUnit = $feeUnit;
            $transactionObj->status = $status;
            $transactionObj->exchangeRate = $exchangeRate;
            $transactionObj->minerFeeExchangeRate = $minerFeeExchangeRate;
            $transactionObj->bcReferenceID = $bcReferenceID;
            $xunWallet = new XunWallet($db);

            $wallet_transaction_return = $xunWallet->cryptoCallbackUpdate($transactionObj, $target);
            $wallet_transaction_id = $wallet_transaction_return["id"];

            
            return $wallet_transaction_return;
        }

        public function business_get_wallet_info($params){
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();

            $xun_business_service = new XunBusinessService($db);

            $business_id = trim($params["business_id"]);
            $api_key = trim($params["api_key"]);
            $coin_type = trim($params["coin_type"]);

            if ($business_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business ID cannot be empty.");
            }
            if ($api_key == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "API key cannot be empty.");
            }

            if (!$xun_business_service->validateApiKey($business_id, $api_key)) {
                $crypto_api_key_validation = $xunCrypto->validate_crypto_api_key($api_key, $business_id);

                if(isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0){
                    return $general->getResponseArr(0, 'E00148');/*Invalid Apikey.*/
                }
            }

            $address_type = "personal";
            if($coin_type == "credit"){
                $address_type = "credit";

                $business_coin_params = new XunBusinessCoinModel($db);
                $business_coin_params->setWalletType($wallet_type);
                $business_coin_params->setType($coin_type);

                $columns = "id, business_id, wallet_type, type, unit_conversion, status";

                $business_coin = $xun_business_service->getBusinessCoin($business_coin_params, $columns);
                $wallet_type = $business_coin->getWalletType();
            }

            $business_address_data = $xun_business_service->getActiveAddressByUserIDandType($business_id, $address_type);

            if(!$business_address_data){
                return array("code" => 0, "message" => "FAILED", "message_d" => "No wallet found.");
            }

            $internal_address = $business_address_data["address"];

            try{
                $wallet_info = $this->get_wallet_info($internal_address, $wallet_type);
            }catch(Exception $e){
                $error_message = $e->getMessage();
                
                return $general->getResponseArr(0, 'E00200', $error_message);
            }

            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Wallet info.", "data" => $wallet_info);
        }

        public function business_get_wallet_transaction_history($params){
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();

            $xun_business_service = new XunBusinessService($db);

            $business_id = trim($params["business_id"]);
            $api_key = trim($params["api_key"]);

            $wallet_type = $params["wallet_type"];
            $address = $params["address"];
            $transaction_list_limit = $params["transaction_list_limit"];
            $transaction_date = $params["transaction_date"];
            $order_by = $params["order_by"];
            $transaction_id = $params["transaction_id"];
            $transaction_hash = $params["transaction_hash"];
            $coin_type = $params["coin_type"];

            if ($business_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business ID cannot be empty.");
            }
            if ($api_key == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "API key cannot be empty.");
            }
            if ($transaction_list_limit == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction list limit cannot be empty.");
            }
            if ($order_by == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Order by cannot be empty.");
            }
            if (isset($transaction_id) == false && $transaction_hash == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Both transaction id or transaction hash cannot be empty.");
            }

            if (!$xun_business_service->validateApiKey($business_id, $api_key)) {
                $crypto_api_key_validation = $xunCrypto->validate_crypto_api_key($api_key, $business_id);

                if(isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0){
                    return $general->getResponseArr(0, 'E00148');/*Invalid Apikey.*/
                }
            }

            if($coin_type == "credit" && !$address){
                // get business credit address
                $crypto_user_address = $xun_business_service->getActiveAddressByUserIDandType($business_id, "credit");
                if(!$crypto_user_address){
                    return $general->getResponseArr(0, "", "Business does not have a valid credit wallet address.");
                }
                $address = $crypto_user_address["address"];

                $columns = "id, business_id, wallet_type, type";
                $business_coin = new XunBusinessCoinModel($db);
                $business_coin->setBusinessID($business_id);
                $business_coin->setType($coin_type);
                $business_coin_data = $xun_business_service->getBusinessCoin($business_coin, $columns);
                
                if(!$business_coin_data){
                    return $general->getResponseArr(0, '', "Business does not have a valid credit coin.");
                }

                $wallet_type = $business_coin_data->getWalletType();
            }

            $new_params = array(
                "wallet_type" => $wallet_type,
                "address" => $address,
                "transaction_list_limit" => $transaction_list_limit,
                "order_by" => $order_by,
                "transaction_id" => $transaction_id,
                "transaction_hash" => $transaction_hash,
                "transaction_date" => $transaction_date
            );

            $crypto_result = $this->crypto_get_transaction_history_list($new_params);

            if($crypto_result["status"] == "ok"){
                $return_data = $crypto_result["data"];
                return array("code" => 1, "message" => "SUCCESS", "message_d" => "Transaction History Listing", "data" => $return_data);
            }else{
                $return_data = $crypto_result["data"];
                return array("code" => 0, "message" => "FAILED", "message_d" => $crypto_result["statusMsg"], "data" => $return_data);
            }
        }
        
        public function get_wallet_info($address, $wallet_type = null){
            global $config;
            $db = $this->db;
            $post = $this->post;

            $crypto_url = $config["cryptoWalletUrl"];

            $params = [];
            $params["address"] = $address;
            $params["languageType"] = "en";
            $params["creditSort"] = "asc";
            if($wallet_type){
                $params["walletType"] = $wallet_type; 
            }
            
            $command = "getWalletInfo";
            $crypto_params = [];
            $crypto_params["command"] = $command;
            $crypto_params["partnerSite"] = $config["cryptoBCPartnerSite"];//$config["cryptoPartnerName"];
            $crypto_params["params"] = $params;

            $crypto_result = $post->curl_post($crypto_url, $crypto_params, 0);

            if($crypto_result["code"] === 0){
                $wallet_info_arr = $crypto_result["data"];

                $arr_len = count($wallet_info_arr);

                $result = array();

                for ($i = 0; $i<$arr_len; $i++){
                    $arr_data = $wallet_info_arr[$i];

                    $db->where('address', $address);
                    $db->where('wallet_type', $arr_data['walletType']);
                    $db->where('disabled', 0);
                    $offset_amount = $db->getValue('xun_crypto_wallet_offset', 'amount');
    
                    if($offset_amount < 0){
                        $balance = bcadd($arr_data['balance'], $offset_amount);
                        $arr_data['balance'] = $balance;
                    }

                    $result[strtolower($arr_data["walletType"])] = $arr_data;
                }
            }else{
                $crypto_status_msg = $crypto_result["statusMsg"];
                throw new Exception($crypto_status_msg);
            }

            return $result;
        }
        
        public function get_wallet_info_by_wallet_type($address, $wallet_type)
        {
            $wallet_info = $this->get_wallet_info($address, $wallet_type);

            return $wallet_info[$wallet_type];
        }

        public function get_wallet_balance($address, $wallet_type)
        {
            $wallet_type = strtolower($wallet_type);
            $wallet_info = $this->get_wallet_info_by_wallet_type($address, $wallet_type);

            if ($wallet_info)
            {
                $balance = $wallet_info["balance"];
                $unit_conversion = $wallet_info["unitConversion"];

                $balance_decimal = bcdiv($balance, $unit_conversion, 8);
            }

            return $balance_decimal;
        }

        public function get_wallet_unit_conversion($wallet_type){
            $db = $this->db;
            $db->where("currency_id", $wallet_type);
            $wallet_info = $db->getOne("xun_marketplace_currencies", null, "currency_id, unit_conversion");

            $unit_conversion = $wallet_info ? $wallet_info["unit_conversion"] : 100000000;

            return $unit_conversion;
        }

        public function get_satoshi_amount($wallet_type, $amount, $unit_conversion = null)
        {
            if(!$unit_conversion){
                $unit_conversion = $this->get_wallet_unit_conversion($wallet_type);
            }

            $satoshi_amount = bcmul((string)$amount, (string)$unit_conversion, 0);
	    $arr_satoshi_amount = explode(".", $satoshi_amount);
	    $satoshi_amount2 = $arr_satoshi_amount[0];

            return $satoshi_amount2;
        }

        public function get_decimal_amount($wallet_type, $amount, $unit_conversion = null)
        {
            if (!$unit_conversion){
                $unit_conversion = $this->get_wallet_unit_conversion($wallet_type);
            }

            $decimal_amount = bcdiv((string)$amount, (string)$unit_conversion, 8);

            return $decimal_amount;
        }

        public function company_wallet_address(){
            global $setting;

            // $escrow_address = $setting->systemSetting["marketplaceEscrowWalletAddress"];
            $trading_fee_address = $setting->systemSetting["marketplaceTradingFeeWalletAddress"];
            $company_pool_address = $setting->systemSetting["marketplaceCompanyPoolWalletAddress"];
            $company_pool_address2 = $setting->systemSetting['marketplaceCompanyPoolWalletAddress2'];
            $company_acc_address = $setting->systemSetting["marketplaceCompanyAccWalletAddress"];
            $freecoin_address = $setting->systemSetting["freecoinWalletAddress"];
            // $pay_address = $setting->systemSetting["payWalletAddress"];
            // $story_address = $setting->systemSetting["storyWalletAddress"];
            $payment_gateway_address = $setting->systemSetting["paymentGatewayWalletAddress"];
            $payment_gateway_address_arr = explode("#", $payment_gateway_address);
            $miner_fee_delegate_wallet_address = $setting->systemSetting['minerFeeDelegateWalletAddress'];
            $redeem_code_agent_wallet_address = $setting->systemSetting['redeemCodeAgentAddress'];
            $swap_coin_internal_address = $setting->systemSetting['swapInternalAddress'];
            $miner_fee_pool_address = $setting->systemSetting['minerFeePoolAddress'];

            $return_data = array(
                // $escrow_address => array(
                //     "type" => "escrow",
                //     "name" => "escrow",
                // ),
                $trading_fee_address => array(
                    "type" => "trading_fee",
                    "name" => "Referral Commission",
                ),
                $company_pool_address => array(
                    "type" => "company_pool",
                    "name" => "Master Dealer Commission"
                ),
                $company_acc_address => array(
                    "type" => "company_acc",
                    "name" => "Company account"
                ),
                $freecoin_address => array(
                    "type" => "freecoin",
                    "name" => "TheNux Airdrop",
                ),
                // $pay_address => array(
                //     "type" => "topup",
                //     "name" => "Top Up",
                // ),
                // $story_address => array(
                //     "type" => "story",
                //     "name" => "Story",
                // ),
                $company_pool_address2 => array(
                    "type" => "company_pool",
                    "name" => "Master Dealer Commission",
                ),
                $miner_fee_delegate_wallet_address => array(
                    "type" => "miner_fee",
                    "name" => "Miner Fee Wallet",
                ),
                $redeem_code_agent_wallet_address => array(
                    "type" => "redeem_code",
                    "name" => "Redeem Code",
                ),
                $swap_coin_internal_address => array(
                    "type" => "swap_wallet",
                    "name" => "Swap Wallet",
                ),
                $miner_fee_pool_address => array(
                    "type" => "miner_pool",
                    "name" => "Miner Pool",
                ),
            );

            foreach($payment_gateway_address_arr as $address){
                $return_data[$address] = array(
                    "type" => "payment_gateway",
                    "name" => "Payment Gateway"
                );
            }

            return $return_data;
        }

        public function check_company_wallet_address($crypto_address){
            global $setting;

            $address_list = $this->company_wallet_address();
            $address_data = $address_list[$crypto_address];

            return $address_data;
        }

        public function process_fund_in_to_service_charge_wallet($params, $service_charge_transaction_id){
            global $xunServiceCharge, $xunCurrency, $xunReferral, $setting, $xunErlang, $xunPayment;
            $db = $this->db;
            $post = $this->post;
            $general = $this->general;

            $sender_address = $params["sender_address"];
            $wallet_type = $params["wallet_type"];
            $transaction_user_id = $params["user_id"];
            $transaction_callback_user_id = $params["transaction_callback_user_id"];

            $db->where("id", $transaction_user_id);
            $xun_user = $db->getOne("xun_user", "id as user_id, type");
    
            $service_charged_user_id = $xunErlang->get_service_charge_user_id($xun_user);

            $date = date("Y-m-d H:i:s");

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);

            $service_charge_params = $params;
            $service_charge_params["user_id"] = $service_charged_user_id;
            $service_charge_params["transaction_callback_user_id"] = $transaction_callback_user_id;


            $service_charge_breakdown = $xunServiceCharge->calculate_upline_trading_fee_quantity($service_charge_params, $decimal_place_setting);
            //  add record to wallet transction for upline and company pool
            //  process fund out
            //  calculate amount for upline

            $xunWallet = new XunWallet($db);

            $transaction_status = "pending";
            $transaction_type = "send";

            $company_address_list = $this->company_wallet_address();

            foreach($service_charge_breakdown as $key => $data){
                $destination_address = $data["destination_address"];
                $data_amount = $data["amount"];
                $address_type = $data["address_type"];
                $user_id = $data["user_id"];

                if($data_amount <= 0){
                    break;
                }

                if(isset($company_address_list[$destination_address])){
                    $recipient_user_id = $company_address_list[$destination_address]["type"];
                }else{
                    $recipient_user_id = $user_id;
                }

                if(isset($company_address_list[$sender_address])){
                    $sender_user_id = $company_address_list[$sender_address]["type"];
                }else{
                    $sender_user_id = "";
                }

                $transactionObj = new stdClass();
                $transactionObj->status = $transaction_status;
                $transactionObj->transactionHash = "";
                $transactionObj->transactionToken = "";
                $transactionObj->senderAddress = $sender_address;
                $transactionObj->recipientAddress = $destination_address;
                $transactionObj->userID = $user_id ? $user_id : '';
                $transactionObj->senderUserID = $sender_user_id;
                $transactionObj->recipientUserID = $recipient_user_id;
                $transactionObj->walletType = $wallet_type;
                $transactionObj->amount = $data_amount;
                $transactionObj->addressType = $address_type;
                $transactionObj->transactionType = $transaction_type;
                $transactionObj->escrow = 0;
                $transactionObj->referenceID = $service_charge_transaction_id;
                $transactionObj->escrowContractAddress = '';
                $transactionObj->createdAt = $date;
                $transactionObj->updatedAt = $date;
                $transactionObj->expiresAt = '';
    

                $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

                $transactionType = "internal_transfer";

                $txHistoryObj->paymentDetailsID = '';
                $txHistoryObj->status = $transaction_status;
                $txHistoryObj->transactionID = "";
                $txHistoryObj->transactionToken = "";
                $txHistoryObj->senderAddress = $sender_address;
                $txHistoryObj->recipientAddress = $destination_address;
                $txHistoryObj->senderUserID = $sender_user_id;
                $txHistoryObj->recipientUserID = $recipient_user_id ? $recipient_user_id : '';
                $txHistoryObj->walletType = $wallet_type;
                $txHistoryObj->amount = $data_amount;
                $txHistoryObj->transactionType = $address_type;
                $txHistoryObj->referenceID = '';
                $txHistoryObj->createdAt = $date;
                $txHistoryObj->updatedAt = $date;
                // $transactionObj->fee = $final_miner_fee;
                // $transactionObj->feeWalletType = $miner_fee_wallet_type;
                $txHistoryObj->exchangeRate = $exchange_rate;
                // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
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

                if($transaction_id){
                    // fund out
                    $walletServer = "trading_fee";
                    $postParams = [];
                    $postParams["walletTransactionID"] = $transaction_id;
                    $postParams["receiverAddress"] = $destination_address;
                    $postParams["amount"] = $data_amount;
                    $postParams["walletType"] = $wallet_type;
                    $postParams['transactionHistoryTable'] = $transaction_history_table;
                    $postParams['transactionHistoryID'] = $transaction_history_id;
                    $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
                    $walletResponse = $xunCompanyWallet->fundOut($walletServer, $postParams);


                    // if ($address_type == "upline"){
                    //     //  insert to xun_referral_transaction
                    //     $xunReferral->insert_referral_transaction($user_id, null, $data_amount, $service_charged_user_id, 0, $wallet_type, $transaction_id);

                    //     $xun_commission = new XunCommission($db, $setting, $general);
                    //     $xun_commission->send_received_commission_message($user_id);
                    // }
                }
            }
        }

        public function process_fund_in_to_company_pool_wallet($params, $service_charge_transaction_id){
            /**
             * params: [sender_address, wallet_type, amount, user_id]
             */
            /////////////
            global $xunServiceCharge, $xunCurrency, $xunReferral, $setting, $xunErlang, $log, $xunPayment;
            $db = $this->db;
            $post = $this->post;
            $general = $this->general;

            $sender_address = $params["sender_address"];
            $wallet_type = $params["wallet_type"];
            $transaction_user_id = $params["user_id"];

            $db->where("id", $transaction_user_id);
            $xun_user = $db->getOne("xun_user", "id as user_id, type");
    
            $service_charged_user_id = $xunErlang->get_service_charge_user_id($xun_user);
            
            $date = date("Y-m-d H:i:s");

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
            $decimal_places= $decimal_place_setting['decimal_places'];
            $service_charge_params = $params;
            $service_charge_params["user_id"] = $service_charged_user_id;
            $marketer_commission_scheme = $xunServiceCharge->getBusinessMarketerCommissionScheme($transaction_user_id, $wallet_type);
            

            $has_master_upline_fee = 1;
            if($marketer_commission_scheme){
                $amount = $params['amount'];
                //$company_acc_pct = $setting->systemSetting["tradingFeeCompanyAccPercentage"];
                // foreach($marketer_commission_scheme as $marketer_commission_key => $marketer_commission_value){
                //     $commission_rate = $marketer_commission_value['commission_rate'];

                //     $total_marketer_commission_rate = bcadd($total_marketer_commission_rate, $commission_rate, 8);
                // }

                //Pass the original maount to this process marketer commission function and process the commission the remaining amount will be company acc's amount
                $remaining_amount = $this->process_marketer_commission($transaction_user_id, $amount,$wallet_type, $service_charge_transaction_id, $sender_address);
                //if marketer commission amount has remaining
                if($remaining_amount > 0){
                    // $company_acc_amount = bcadd($company_acc_amount, $remaining_amount, 8);
                    $company_acc_amount = $remaining_amount;
                }

                $service_charge_params['amount']= $company_acc_amount;
                $has_master_upline_fee = 0;
            }


            $service_charge_breakdown = $xunServiceCharge->calculate_master_upline_trading_fee_quantity($service_charge_params, $decimal_place_setting, $has_master_upline_fee);
            //  add record to wallet transction for master dealer and company pool
            //  process fund out
            //  calculate amount for upline

            $xunWallet = new XunWallet($db);

            $transaction_status = "pending";
            $transaction_type = "send";

            $company_address_list = $this->company_wallet_address();

            foreach($service_charge_breakdown as $key => $data){
                $destination_address = $data["destination_address"];
                $data_amount = $data["amount"];
                $address_type = $data["address_type"];
                $user_id = $data["user_id"];
        
                if($data_amount <= 0){
                    break;
                }

                if(isset($company_address_list[$destination_address])){
                    $recipient_user_id = $company_address_list[$destination_address]["type"];
                }else{
                    $recipient_user_id = $user_id;
                }

                if(isset($company_address_list[$sender_address])){
                    $sender_user_id = $company_address_list[$sender_address]["type"];
                }else{
                    $sender_user_id = "";
                }

                $transactionObj = new stdClass();
                $transactionObj->status = $transaction_status;
                $transactionObj->transactionHash = "";
                $transactionObj->transactionToken = "";
                $transactionObj->senderAddress = $sender_address;
                $transactionObj->recipientAddress = $destination_address;
                $transactionObj->userID = $user_id ? $user_id : '';
                $transactionObj->senderUserID = $sender_user_id;
                $transactionObj->recipientUserID = $recipient_user_id;
                $transactionObj->walletType = $wallet_type;
                $transactionObj->amount = $data_amount;
                $transactionObj->addressType = $address_type;
                $transactionObj->transactionType = $transaction_type;
                $transactionObj->escrow = 0;
                $transactionObj->referenceID = $service_charge_transaction_id;
                $transactionObj->escrowContractAddress = '';
                $transactionObj->createdAt = $date;
                $transactionObj->updatedAt = $date;
                $transactionObj->expiresAt = '';
    
                $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

                $transactionType = $address_type;

                $txHistoryObj->paymentDetailsID = '';
                $txHistoryObj->status = $transaction_status;
                $txHistoryObj->transactionID = "";
                $txHistoryObj->transactionToken = "";
                $txHistoryObj->senderAddress = $sender_address;
                $txHistoryObj->recipientAddress = $destination_address;
                $txHistoryObj->senderUserID = $sender_user_id;
                $txHistoryObj->recipientUserID = $recipient_user_id ? $recipient_user_id : '';
                $txHistoryObj->walletType = $wallet_type;
                $txHistoryObj->amount = $data_amount;
                $txHistoryObj->transactionType = $address_type;
                $txHistoryObj->referenceID = '';
                $txHistoryObj->createdAt = $date;
                $txHistoryObj->updatedAt = $date;
                // $transactionObj->fee = $final_miner_fee;
                // $transactionObj->feeWalletType = $miner_fee_wallet_type;
                // $txHistoryObj->exchangeRate = $exchange_rate;
                // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
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
                // Check if the transaction is company account transaction
    
                if($transaction_id){
                    // fund out
                    $walletServer = "company_pool";
                    $postParams = [];
                    $postParams["walletTransactionID"] = $transaction_id;
                    $postParams["receiverAddress"] = $destination_address;
                    $postParams["amount"] = $data_amount;
                    $postParams["walletType"] = $wallet_type;
                    $postParams["senderAddress"] = $sender_address; //company pool address depending on service charge rate.
                    $postParams['transactionHistoryTable'] = $transaction_history_table;
                    $postParams['transactionHistoryID'] = $transaction_history_id;
                    $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
                    $walletResponse = $xunCompanyWallet->fundOut($walletServer, $postParams);

                    // if ($address_type == "master_upline"){
                    //     //  insert to xun_referral_transaction
                    //     $xunReferral->insert_referral_transaction($user_id, null, $data_amount, $service_charged_user_id, 1, $wallet_type, $transaction_id);

                    //     $xun_commission = new XunCommission($db, $setting, $general);
                    //     $xun_commission->send_received_commission_message($user_id);
                    // }
                }
            }
        }


        public function process_pay_transaction($wallet_transaction_id){
            global $xunPay;
            $pay_transaction_rec = $xunPay->get_product_transaction_by_wallet_transaction_id($wallet_transaction_id);

            //  check if status is pending
            if(!empty($pay_transaction_rec) && $pay_transaction_rec["status"] == "pending"){
                $xunPay->process_request_to_provider($pay_transaction_rec);
            }
        }

        public function process_pay_refund_transction($wallet_transaction){
            global $xunPay;

            $wallet_transaction_id = $wallet_transaction["id"];
            $wallet_transaction_status = $wallet_transaction["status"];

            $pay_transaction_status = "refunded";
            $pay_transaction_id = $wallet_transaction["reference_id"];

            $wallet_transaction_address_type = $wallet_transaction["address_type"];

            $update_obj = new stdClass();
            $update_obj->id = $pay_transaction_id;
            $update_obj->status = $pay_transaction_status;
            if($wallet_transaction_address_type == "pay"){
                $xunPay->update_pay_transaction_status($update_obj);
            }else if($wallet_transaction_address_type == "payCallbackRefund"){
                $xunPay->update_pay_transaction_item_status($update_obj);
            }
        }

        public function process_story_transaction($wallet_transaction){
            global $xunStory;
            // $xunStory->update_donation_callback($wallet_transaction);
        }

        public function process_story_refund_transaction($wallet_transaction){
            global $xunStory;

            $wallet_transaction_id = $wallet_transaction["id"];
            $wallet_transaction_status = $wallet_transaction["status"];

            $story_transaction_status = "refunded";
            $story_transaction_id = $wallet_transaction["reference_id"];

            $wallet_transaction_address_type = $wallet_transaction["address_type"];
            $update_obj = new stdClass();
            $update_obj->id = $story_transaction_id;
            $update_obj->status = $story_transaction_status;

            if($wallet_transaction_address_type == "story"){
                $xunStory->update_transaction_status($update_obj);
            }
        }

        public function business_crypto_validate_address($params){
            $post = $this->post;
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();
            $xun_business_service = new XunBusinessService($db);

            $business_id = trim($params["business_id"]);
            $api_key = trim($params["api_key"]);
            $wallet_type = trim($params["wallet_type"]);
            $destination_address = trim($params["destination_address"]);

            if ($business_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business ID cannot be empty.");
            }
            if ($api_key == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "API key cannot be empty.");
            }
            if ($wallet_type == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet type cannot be empty.");
            }
            if ($destination_address == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Destination address cannot be empty.");
            }

            if (!$xun_business_service->validateApiKey($business_id, $api_key)) {
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $translations['E00148'][$language]/*Invalid Apikey.*/);
            }

            //  validate wallet type
            $wallet_type = strtolower($wallet_type);

            $db->where("currency_id", $wallet_type);
            $currency_data = $db->getOne("xun_marketplace_currencies", "id, currency_id, type");
    
            if(!$currency_data || $currency_data["type"] != 'cryptocurrency'){
                return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid wallet type.", "errorCode" => -100);
            }

            // destination address is external address
            $validate_destination_address_result = $this->crypto_validate_address($destination_address, $wallet_type, "external");

            // validate address
            $crypto_data = $validate_destination_address_result["data"];
            if($validate_destination_address_result["code"] == 0){

                return array("code" => 1, "message" => "SUCCESS", "message_d" => "Destination address validation.", "data" => $crypto_data);

            }else{
                $status_msg = $validate_destination_address_result["statusMsg"];
                return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg, "data" => $crypto_data);
            }
        }

        public function busines_payment_gateway_verify_fundout($params){
            $post = $this->post;
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();
            $xun_business_service = new XunBusinessService($db);

            $business_id = trim($params["business_id"]);
            $api_key = trim($params["api_key"]);
            $amount = trim($params["amount"]);
            $wallet_type = trim($params["wallet_type"]);
            $destination_address = trim($params["destination_address"]);

            if ($business_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business ID cannot be empty.");
            }
            if ($api_key == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "API key cannot be empty.");
            }
            if ($amount == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Amount cannot be empty.");
            }
            if ($wallet_type == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet type cannot be empty.");
            }
            if ($destination_address == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Destination address cannot be empty.");
            }

            if (!$xun_business_service->validateApiKey($business_id, $api_key)) {
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $translations['E00148'][$language]/*Invalid Apikey.*/);
            }

            //  validate wallet type

            $wallet_type = strtolower($wallet_type);

            $db->where("currency_id", $wallet_type);
            $currency_data = $db->getOne("xun_marketplace_currencies", "id, currency_id, type");
    
            if(!$currency_data || $currency_data["type"] != 'cryptocurrency'){
                return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid wallet type.", "errorCode" => -100);
            }

            $satoshi_amount = $this->get_satoshi_amount($wallet_type, $amount);

            if($satoshi_amount <= 0){
                return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid amount.");
            }

            $xun_payment_gateway_service = new XunPaymentGatewayService($db);

            $business_address_data = $xun_business_service->getActiveInternalAddressByUserID($business_id);

            if(!$business_address_data){
                return array("code" => 0, "message" => "FAILED", "message_d" => "No wallet found.");
            }

            $sender_address = $business_address_data["address"];

            $wallet_obj = new stdClass();
            $wallet_obj->businessID = $business_id;
            $wallet_obj->type = $wallet_type;
            $wallet_obj->status = 1;

            $wallet_result = $xun_payment_gateway_service->createWallet($wallet_obj);

            $wallet_id = $wallet_result["id"];

            $address_result = $xun_payment_gateway_service->getFundOutDestinationAddress($wallet_id, $destination_address);

            if(!$address_result){

                $db->where('id', $business_id);
                $xun_user = $db->getOne('xun_user', 'nickname');

                $crypto_params["type"] = $wallet_type;
                $crypto_params['businessID'] = $business_id;
                $crypto_params['businessName'] = $xun_user['nickname'];

                $crypto_results = $post->curl_crypto("getNewAddress", $crypto_params);

                if($crypto_results["code"] != 0){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_results["message"]);
                }

                $pg_address = $crypto_results["data"]["address"];

                if(!$pg_address){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00155') /*Address not generated.*/);
                }

                $address_obj = new stdClass();
                $address_obj->walletID = $wallet_id;
                $address_obj->cryptoAddress = $pg_address;
                $address_obj->status = "1";
                $address_obj->type = "out";
                $address_id = $xun_payment_gateway_service->insertBusinessPaymentGatewayAddress($address_obj);
                
                $xun_user_service = new XunUserService($db);
                $crypto_user_address = $xun_user_service->getAddressDetailsByAddress($destination_address);
    
                if(empty($crypto_user_address)){
                    // destination address is external address
                    $validate_destination_address_result = $this->crypto_validate_address($destination_address, $wallet_type, "external");

                    if($validate_destination_address_result["code"] == 1){
                        return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid address.", "errorCode" => -100);
                    }
                    $address_type = "external";
                }else{
                    // destination address is internal address
                    $address_type = "internal";
                }
                //  insert into destination table
                $dest_address_obj = new stdClass();
                $dest_address_obj->walletID = $wallet_id;
                $dest_address_obj->addressID = $address_id;
                $dest_address_obj->status = "1";
                $dest_address_obj->destinationAddress = $destination_address;
                $dest_address_obj->addressType = $address_type;
                $dest_address_id = $xun_payment_gateway_service->insertBusinessPaymentGatewayFundOutDestinationAddress($dest_address_obj);
            }else{
                $pg_address = $address_result["crypto_address"];
                $address_id = $address_result["address_id"];
            }

            // validate address
            $validate_address_result = $this->crypto_validate_address($pg_address, $wallet_type, "external");

            if($validate_address_result["status"] == "ok"){
                $crypto_data = $validate_address_result["data"];
                if($crypto_data["addressType"] == "internal" && $crypto_data["status"] == "valid"){
                    $pg_internal_address = $crypto_data["address"];
                }else{
                    return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid payment gateway address.");
                }
            }else{
                $status_msg = $validate_address_result["statusMsg"];
                return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
            }

            //  verify internal transfer
            $receiver_address = $pg_internal_address;
            
            $verify_internal_transfer_result = $this->crypto_verify_internal_transfer($sender_address, $receiver_address, $satoshi_amount, $wallet_type);
            if($verify_internal_transfer_result["status"] == "ok"){
                $crypto_data = $verify_internal_transfer_result["data"];
                $nonce = $verify_internal_transfer_result["nonce"];

                //  insert to xun_wallet_transaction
                $xunWallet = new XunWallet($db);

                $address_type = "payment_gateway_fund_out";
                $transaction_type = "send";

                $transaction_obj = new stdClass();
                $transaction_obj->status = "pending";
                $transaction_obj->transactionHash = "";
                $transaction_obj->transactionToken = "";
                $transaction_obj->senderAddress = $sender_address;
                $transaction_obj->recipientAddress = $receiver_address;
                $transaction_obj->userID = $business_id;
                $transaction_obj->walletType = $wallet_type;
                $transaction_obj->amount = $amount;
                $transaction_obj->addressType = $address_type;
                $transaction_obj->transactionType = $transaction_type;
                $transaction_obj->escrow = 0;
                $transaction_obj->referenceID = '';
    
                $transaction_id = $xunWallet->insertUserWalletTransaction($transaction_obj);

                if($transaction_id){
                    $pg_transaction_obj = new stdClass();
                    $pg_transaction_obj->userID = $business_id;
                    $pg_transaction_obj->walletTransactionID = $transaction_id;
                    $pg_transaction_obj->addressID = $address_id;
                    $pg_transaction_id = $xun_payment_gateway_service->insertFundOutTransaction($pg_transaction_obj);
                }

                $return_data = [];
                $return_data["sign_data"] = $crypto_data;
                $return_data["receiver_address"] = $pg_internal_address;
                $return_data["reference_id"] = $pg_transaction_id;
                return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "data" => $return_data);
            }else{
                $status_msg = $verify_internal_transfer_result["statusMsg"];
                return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
            }
        }

        public function busines_payment_gateway_fundout($params)
        {
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();
            
            $xun_business_service = new XunBusinessService($db);

            $business_id = trim($params["business_id"]);
            $api_key = trim($params["api_key"]);
            $signed_transaction_data = trim($params["signed_transaction_data"]);
            $pg_transaction_id = trim($params["reference_id"]);
            // $pg_address = trim($params["payment_gateway_address"]); // pg external address

            if ($business_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business ID cannot be empty.");
            }
            if ($api_key == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "API key cannot be empty.");
            }
            if ($signed_transaction_data == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "signed_transaction_data cannot be empty.");
            }
            if ($pg_transaction_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Reference ID cannot be empty.");
            }

            if (!$xun_business_service->validateApiKey($business_id, $api_key)) {
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $translations['E00148'][$language]/*Invalid Apikey.*/);
            }

            $business_address_data = $xun_business_service->getActiveInternalAddressByUserID($business_id, "id, address");
            
            $business_internal_address = $business_address_data["address"];

            $tx_obj = new stdClass();
            $tx_obj->userID = $business_id;
            $tx_obj->address = $business_internal_address;

            $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);

            $xun_payment_gateway_service = new XunPaymentGatewayService($db);

            $pg_fund_out_transaction_data = $xun_payment_gateway_service->getFundOutTransaction($pg_transaction_id);
            if(empty($pg_fund_out_transaction_data)){
                return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid reference ID.");
                
            }
            $address_id = $pg_fund_out_transaction_data["address_id"];
            $wallet_transaction_id = $pg_fund_out_transaction_data["wallet_transaction_id"];
            $crypto_address_data = $xun_payment_gateway_service->getBusinessPaymentGatewayAddressByID($address_id);
            //  get pg_address
            if(empty($crypto_address_data)){
                return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid address.");
            }

            $pg_address = $crypto_address_data["crypto_address"];

            $crypto_result = $this->crypto_internal_transfer($signed_transaction_data, $pg_address, $transaction_token);

            if($crypto_result["status"] == "ok"){
                $crypto_data = $crypto_result["data"];
                $transaction_hash = $crypto_data["transactionHash"];
                //  update wallet transaction

                $update_wallet_transaction = [];
                $update_wallet_transaction["transaction_hash"] = $transaction_hash;
                $db->where("id", $wallet_transaction_id);
                $db->where("status", "completed", "!=");
                $db->update("xun_wallet_transaction", $update_wallet_transaction);

                return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "data" => $crypto_data);
            }else{
                $status_msg = $crypto_result["statusMsg"];
                return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
            }
        }

        public function create_pg_fund_out_address($business_id, $wallet_type, $destination_address, $new_address = null){
            $db = $this->db;
            $post = $this->post;

            $xun_payment_gateway_service = new XunPaymentGatewayService($db);

            $xun_user_service = new XunUserService($db);
            $crypto_user_address = $xun_user_service->getAddressDetailsByAddress($destination_address);

            if(empty($crypto_user_address)){
                // destination address is external address
                $validate_destination_address_result = $this->crypto_validate_address($destination_address, $wallet_type, "external");

                if($validate_destination_address_result["code"] == 1){
                    throw new Exception("Invalid address", -100);
                    // return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid address.", "errorCode" => -100);
                }
                $address_type = "external";
            }else{
                // destination address is internal address
                $address_type = "internal";
            }
            
            $wallet_obj = new stdClass();
            $wallet_obj->businessID = $business_id;
            $wallet_obj->type = $wallet_type;
            $wallet_obj->status = 1;

            $wallet_result = $xun_payment_gateway_service->createWallet($wallet_obj);

            $wallet_id = $wallet_result["id"];

            $generate_new_address = false;
            if($new_address == true){
                $generate_new_address = true;
            }else{
                $address_result = $xun_payment_gateway_service->getFundOutDestinationAddress($wallet_id, $destination_address);

                if(!$address_result){
                    $generate_new_address = true;
                }
            }

            if($generate_new_address){

                $db->where('id', $business_id);
                $xun_user = $db->getOne('xun_user', 'nickname');
                $crypto_params["type"] = $wallet_type;
                $crypto_params['businessID'] = $business_id;
                $crypto_params['businessName']= $xun_user['nickname'];

                $crypto_results = $post->curl_crypto("getNewAddress", $crypto_params);

                if($crypto_results["code"] != 0){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_results["message"]);
                }

                $pg_address = $crypto_results["data"]["address"];

                if(!$pg_address){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00155') /*Address not generated.*/);
                }

                $address_obj = new stdClass();
                $address_obj->walletID = $wallet_id;
                $address_obj->cryptoAddress = $pg_address;
                $address_obj->status = "1";
                $address_obj->type = "out";

                $address_id = $xun_payment_gateway_service->insertBusinessPaymentGatewayAddress($address_obj);
                
                //  insert into destination table
                $dest_address_obj = new stdClass();
                $dest_address_obj->walletID = $wallet_id;
                $dest_address_obj->addressID = $address_id;
                $dest_address_obj->status = "1";
                $dest_address_obj->destinationAddress = $destination_address;
                $dest_address_obj->addressType = $address_type;
                $dest_address_id = $xun_payment_gateway_service->insertBusinessPaymentGatewayFundOutDestinationAddress($dest_address_obj);
            }else{
                $pg_address = $address_result["crypto_address"];
                $address_id = $address_result["address_id"];
            }

            $return_data = [];
            $return_data["address_id"] = $address_id;
            $return_data["address"] = $pg_address;
            return $return_data;
        }

        public function validate_payment_gateway_address($pg_address, $wallet_type){
            // validate pg external address to get internal address
            $validate_address_result = $this->crypto_validate_address($pg_address, $wallet_type, "external");

            if($validate_address_result["status"] == "ok"){
                $crypto_data = $validate_address_result["data"];

                if($crypto_data["addressType"] == "internal" && $crypto_data["status"] == "valid"){
                    // $pg_internal_address = $crypto_data["address"];
                    return $crypto_data;
                }else{
                    // return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid payment gateway address.");
                    throw new Exception("Invalid payment gateway address.", -100);
                }
            }else{
                $status_msg = $validate_address_result["statusMsg"];
                throw new Exception($status_msg);
                // return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
            }
        }

        public function crypto_verify_internal_transfer($sender_address, $receiver_address, $amount, $wallet_type)
        {
            $post = $this->post;
            if($wallet_type == "bitcoin"){
                $amount = (int)$amount;
            }else{
                $amount = (string)$amount;
            }

            $command = "verifyInternalTransfer";

            $params = array(
                "senderAddress" => $sender_address,
                "receiverAddress" => $receiver_address,
                "amount" => $amount,
                "walletType" => $wallet_type,
            );

            $crypto_result = $post->curl_crypto($command, $params, 2);

            return $crypto_result;
        }

        public function crypto_internal_transfer($signed_transaction_data, $transaction_ref = null, $transaction_token = null)
        {
            $post = $this->post;

            $command = "internalTransfer";

            $params = array(
                "signedTransactionData" => $signed_transaction_data
            );

            if($transaction_ref){
                $params["transactionRef"] = $transaction_ref;
            }
            if($transaction_token){
                $params["transactionToken"] = $transaction_token;
            }

            $crypto_result = $post->curl_crypto($command, $params, 2);

            return $crypto_result;
        }

        public function crypto_get_external_address($internal_address, $wallet_type){
            $post = $this->post;

            $command = "getWalletAddress";

            $params = array(
                "walletType" => $wallet_type,
                "address" => $internal_address,
            );
            $result = $post->curl_crypto($command, $params, 2);

            return $result;
        }
        
        public function crypto_validate_address($address, $wallet_type, $transaction_type){
            $post = $this->post;

            $command = "validateAddress";

            $params = array(
                "walletType" => $wallet_type,
                "address" => $address,
                "transactionType" => $transaction_type
            );
            $result = $post->curl_crypto($command, $params, 2);

            return $result;
        }
        
        public function crypto_get_transaction_history_list($params){
            $post = $this->post;

            $wallet_type = $params["wallet_type"];
            $address = $params["address"];
            $transaction_list_limit = $params["transaction_list_limit"];
            $transaction_date = $params["transaction_date"];
            $order_by = $params["order_by"];
            $transaction_id = $params["transaction_id"];
            $transaction_hash = $params["transaction_hash"];

            $command = "getTransactionHistoryList";

            $curlParams = array(
                "walletType" => $wallet_type,
                "address" => $address,
                "txnsListLimit" => $transaction_list_limit,
                "txnsDate" => $transaction_date,
                "orderBy" => $order_by
            );

            if(isset($transaction_id)){
                $curlParams["txnsID"] = $transaction_id;
            }else if(isset($transaction_hash)){
                $curlParams["txnsHash"] = $transaction_hash;
            }

            $result = $post->curl_crypto($command, $curlParams, 2);

            return $result;
        }

        public function crypto_bc_create_multi_wallet($address, $wallet_type){
            $post = $this->post;
            // Array
            // (
            //     [command] => createMultiWallet
            //     [partnerSite] => xun
            //     [params] => Array
            //         (
            //             [address] => 0xa8fdc4a39b4bdbb609d4774ecb74bbf80ecdfeae //internal
            //             [walletType] => ethereum
            //         )
            
            // )
            $command = "createMultiWallet";

            $params = array(
                "walletType" => $wallet_type,
                "address" => $address
            );
            $result = $post->curl_crypto($command, $params, 2);

            return $result;
        }

        public function crypto_update_transaction_hash($params){
            global $log;
            $db = $this->db;

            global $xunServiceCharge, $setting, $config;
            $trading_fee_address = $setting->systemSetting["marketplaceTradingFeeWalletAddress"];

            $transactionID = $params["transaction_id"];
            $senderAddress = $params["sender_address"];
            $transactionHash = $params["transaction_hash"];
            $transactionType = $params["transaction_type"];
            $pgTransactionHash = $params["pg_transaction_hash"];
            // $fundOutTable = $db->escape($params['fun_out_table']);
            // $fundOutID = $params['fund_out_id'];
            $serviceChargeAuditID = $params['service_charge_id'];

            if($transactionID ==''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction ID cannot be empty.");
            }

            if($senderAddress ==''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Sender Address cannot be empty.");
            }
            
            if($transactionHash ==''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction Hash cannot be empty.");
            }
            
            if($transactionType ==''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction Type cannot be empty.");
            }

            if($pgTransactionHash == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Fund Out Transaction Hash cannot be empty.");
            }

            // update pg_crypto_history to include service charge transaction id
            $updateCryptoHistory = array(
                'service_charge_transaction_id' => $transactionHash,
                'updated_at' => date("Y-m-d H:i:s")
            );
            $db->where('transaction_id', $pgTransactionHash);
            $db->update('xun_crypto_history', $updateCryptoHistory);            

            $db->where('id', $transactionID);
            $wallet_transaction_result = $db->getOne('xun_wallet_transaction');

            if(!$wallet_transaction_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet Transaction not found.");
            }

            if($wallet_transaction_result["status"] == "completed"){
                return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
            }

            $db->where('id', $serviceChargeAuditID);
            $service_charge_result = $db->getOne('xun_service_charge_audit', 'fund_out_table, fund_out_id');

            if($service_charge_result){
                $fundOutTable = $service_charge_result['fund_out_table'];
                $fundOutID = $service_charge_result['fund_out_id'];
                if($fundOutTable && $fundOutID){
                    $db->where('id', $fundOutID);
                    $fund_out_transaction_result = $db->getOne($fundOutTable);
        
                    if($fund_out_transaction_result["status"] == "completed"){
                        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
                    }
                }
            }
            

            if($transactionType == "external"){
                $db->where("ex_transaction_hash", $transactionHash);
                $db->where("recipient_address", $trading_fee_address);
            }else if($transactionType == "internal"){
                $db->where("transaction_hash", $transactionHash);
            }else{
                return array("code" => 0, 'message' => "FAILED", "message_d" => "Invalid transaction type.");
            }
                        
            $cryptoTransactionRecord = $db->getOne("xun_crypto_transaction_hash");

            $wallet_type = $wallet_transaction_result["wallet_type"];
            $padded_amount = $wallet_transaction_result["amount"];
            $trading_fee_address = $wallet_transaction_result["recipient_address"];

            // if($config['consolidate_fee_charges'] == 1){
            //     // deduct miner fee
            //     $db->where('transaction_id', $pgTransactionHash);
            //     $historyRecord = $db->getOne('xun_crypto_history');
            //     $padded_amount = bcsub($padded_amount, $historyRecord['miner_fee'], 8);
            
            // }

            if ($cryptoTransactionRecord) {
                $transactionStatus = "completed";
                $service_charge_status = "completed";
            }
            else{
                $transactionStatus ="wallet_success";
            }
    
           $updateTransactionHash = array(
               "sender_address" => $senderAddress,
               "transaction_hash" => $transactionHash,
               "status" => $transactionStatus,
               "updated_at" =>  date("Y-m-d H:i:s"),
           );

           $db->where('id', $transactionID);
           $db->update('xun_wallet_transaction', $updateTransactionHash);

            if($fundOutTable && $fundOutID){
                $updateFundOutTransactionHash = array(
                    "sender_address" => $senderAddress,
                    "transaction_id" => $transactionHash,
                    "status" => $transactionStatus,
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $db->where('id', $fundOutID);
                $db->update($fundOutTable, $updateFundOutTransactionHash);
            }
         
           $service_charge_data = $xunServiceCharge->update_service_charge($transactionID, $service_charge_status, $pgTransactionHash, $transactionHash);
            if($transactionStatus == "completed"){

                //  fund out to upline and company pool
                $new_params = [];
                $new_params["wallet_type"] = $wallet_type;
                $new_params["amount"] = $padded_amount;
                $new_params["user_id"] = $service_charge_data["user_id"];
                $new_params["sender_address"] = $trading_fee_address;
                $new_params["transaction_callback_user_id"] = $wallet_transaction_result["user_id"];
                $this->process_fund_in_to_service_charge_wallet($new_params, $service_charge_data["id"]);
            }                        
           
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Wallet Transaction Hash Updated");
        }


        public function check_crypto_external_transaction($params, $padded_amount){
            global $setting, $xunServiceCharge, $log, $xunCurrency, $xunXmpp, $xunMinerFee, $xunPaymentGateway, $config, $xun_numbers, $xunPayment;
            $db = $this->db;
            $general = $this->general;

            $xunWallet = new XunWallet($db);

            $date = date("Y-m-d H:i:s");
            $target = $params["target"];
            $transaction_type = $params["type"];
            $status = $params["status"];
            $recipient_address = $params["recipient"];
            $sender_address = $params["sender"];
            $amount = $params["amount"];
            $ex_transaction_hash = $params["exTransactionHash"];
            $transaction_hash = $params["transactionHash"];
            $wallet_type = $params["wallet_type"];
            $amount_rate = $params["amountRate"];
            $fee = $params["fee"];
            $feeRate = $params["feeRate"];
            $feeUnit = $params["feeUnit"];
            $exchange_rate = $params['exchangeRate']['USD'];
            $bc_reference_id = $params['referenceID'];

  
            // if($transaction_type == "receive" && $params["transactionHash"] == ''){
                // $params["transactionHash"] = $ex_transaction_hash;//for external the transaction hash will be the ex_transaction_hash
            // }

            $log->write("\n running check_crypto_external_transaction\n");
            $log->write("params ".json_encode($params)."\n");

            $trading_fee_address = $setting->systemSetting["marketplaceTradingFeeWalletAddress"];
            $company_pool_address = $setting->systemSetting["marketplaceCompanyPoolWalletAddress"];
            // $consolidate_address = $setting->systemSetting["requestFundConsolidateWalletAddress"];           
            $miner_fee_delegate_wallet = $setting->systemSetting["minerFeeDelegateWalletAddress"];
            $freecoin_address = $setting->systemSetting["freecoinWalletAddress"];
            
            $company_address_list = $this->company_wallet_address();
            $recipient_company_address_data = $company_address_list[$recipient_address];
            $sender_company_address_data = $company_address_list[$sender_address];

            $recipient_address_type = '';
            $sender_address_type = '';
            if($recipient_company_address_data && $recipient_company_address_data["type"] != ''){
                $recipient_address_type = $recipient_company_address_data["type"];
            }

            if($sender_company_address_data && $sender_company_address_data["type"] != ''){
                $sender_address_type = $sender_company_address_data["type"];
            }

            // $padded_amount = bcdiv((string)$amount, (string)$amount_rate, 18);
            $padded_fee = bcdiv($fee, $feeRate, 18);

            $minerFeeSenderWalletAddress = $setting->systemSetting['minerFeeSenderWalletAddress'];
            $minerFeeFundInWalletAddress = explode(",", $minerFeeSenderWalletAddress);

            // external tranfer to trading fee (payment gateway)
            if($recipient_address == $trading_fee_address && !in_array($sender_address, $minerFeeFundInWalletAddress)){
            // if($transaction_type == "receive" && $recipient_address == $trading_fee_address && $status == "confirmed"){
                if($transaction_type == "receive"){
                    if($status == "confirmed"){
                        //  fund in to prepaid wallet
                        $recipient_address_data = $this->recipient_address_data;

                        if($config['consolidate_fee_charges'] == 1){
                            $wallet_transaction_record = $xunWallet->getWalletTransactionByTxHash($ex_transaction_hash);

                            $wallet_transaction_id = $wallet_transaction_record['id'];
                            $db->where('wallet_transaction_id', $wallet_transaction_id);
                            $service_charge_data = $db->getOne('xun_service_charge_audit');

                            $transaction_id = $service_charge_data['transaction_hash'];
                            $service_charge_type = $service_charge_data['service_charge_type'];
                            $service_charge_amount = $service_charge_data['amount'];

                            if($service_charge_type == 'payment_gateway'){
                                // // deduct miner fee
                                // $db->where('transaction_id', $transaction_id);
                                // $historyRecord = $db->get('xun_crypto_history');

                                // if($historyRecord){
                                //     foreach($historyRecord as $key => $value){
                                //         $padded_amount = bcsub($padded_amount, $value['miner_fee'], $decimal_places);
                                //     }
                                   
                                // }
                                $padded_amount = $service_charge_amount;
                            }
                            else if($service_charge_type == 'pg_external_transfer'){
                                // $db->where('service_charge_tx_hash', $ex_transaction_hash);
                                // $fund_out_details = $db->get('xun_crypto_fund_out_details');

                                // if($fund_out_details){
                                //     foreach($fund_out_details as $key => $value){
                                //         $pool_amount = $value['pool_amount'];
                                //         $total_pool_amount = bcadd($total_pool_amount, $pool_amount, 8);
                                //     }
                                //     $padded_amount = bcsub($padded_amount, $total_pool_amount, 8);
                                // }
                                $padded_amount = $service_charge_amount;
                            }
                        }
                       
                        // update wallet_transaction table
                        $address_type = "service_charge";
        
                        $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type, $padded_fee);
                        $wallet_transaction_id = $wallet_transaction_return["id"];
        
                        if($wallet_transaction_return["is_completed"] === 1){
                            return;
                        }
                        if($wallet_transaction_id){

                            $service_charge_tx_data = $xunServiceCharge->updateServiceChargeTransaction($params, $padded_amount);
                            if(!$service_charge_tx_data){
                                return;
                            }
                            $status = "completed";
                            $service_charge_data = $xunServiceCharge->update_service_charge($wallet_transaction_id, $status);
        
                            if(!$service_charge_data || $service_charge_data['is_completed'] == 1 ){
                                return;
                            }



                            //  fund out to upline and company pool
                            $new_params = [];
                            $new_params["wallet_type"] = $wallet_type;
                            $new_params["amount"] = $padded_amount;
                            $new_params["user_id"] = $service_charge_data["user_id"];
                            $new_params["sender_address"] = $trading_fee_address;
                            $new_params["transaction_callback_user_id"] = $wallet_transaction_return["user_id"];


                            $this->process_fund_in_to_service_charge_wallet($new_params, $service_charge_data["id"]);
                        }
                    }else if($status == "failed"){
                        $address_type = "service_charge";
                        $this->update_wallet_transaction($params, $padded_amount, $address_type, $padded_fee);
                    }
                }
            }elseif($transaction_type == "send"){
                if($status == "confirmed" || $status == "failed"){
                    $address_type = "external_transfer";
                    $return = $this->update_wallet_transaction($params, $padded_amount, $address_type, $padded_fee);

                    $address_type = $return['address_type'];
                    $reference_id = $return['reference_id'];
                    $wallet_type = strtolower($wallet_type);

                    if($address_type=="withdrawal"){
                        $update_status = array(
                            "status" => $status == 'confirmed' ? 'success' : $status,
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('id', $return['reference_id']);
                        $updated = $db->update('xun_request_fund_withdrawal', $update_status);
                        
                        $exchange_rate = $params['exchangeRate']['USD'];
                        $fee = bcdiv($fee, $feeRate,18);
                        $miner_fee_usd_amount = bcmul($fee, $params['minerFeeExchangeRate'], 18);
                        $miner_fee = bcdiv($miner_fee_usd_amount, $exchange_rate, 18);

                        $update_withdrawal = array(
                            "amount" => $padded_amount,
                            "miner_fee" => $miner_fee,
                            "status" => $status == 'confirmed' ? 'success' : $status,
                            "transaction_hash" => $transaction_hash,
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('reference_id', $return['reference_id']);
                        $db->where('transaction_type', 'manual_withdrawal');
                        $updated_pg_withdrawal = $db->update('xun_payment_gateway_withdrawal', $update_withdrawal);

                        $db->where('reference_id', $return['reference_id']);
                        $db->where('transaction_type', 'manual_withdrawal');
                        $pg_withdrawal_data = $db->getOne('xun_payment_gateway_withdrawal ', 'business_id, transaction_fee, miner_fee, wallet_type');

                        $transaction_fee = $pg_withdrawal_data['transaction_fee'];
                        $miner_fee = $pg_withdrawal_data['miner_fee'];
                        $user_id = $pg_withdrawal_data['business_id'];
                        
                        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
                        $decimal_places = $decimal_place_setting["decimal_places"];
                
                        $total_charges = bcadd($transaction_fee, $miner_fee, $decimal_places);
                        $total_fee_charges_usd =  $xunCurrency->get_conversion_amount('usd', $wallet_type, $total_charges, true, $exchange_rate);

                        $compensate_fee_amount = $setting->systemSetting['compensateFeeAmount']; //Compensate Fee Amount (USD)

                        $db->where('id', $user_id);
                        $user_data = $db->getOne('xun_user', 'id, nickname, reseller_id');
        
                        $reseller_id = $user_data['reseller_id'];
                        if($status == 'confirmed' && $compensate_fee_amount > 0 && $reseller_id != '0'){
                            $db->where('a.business_id', $user_id);
                            $db->where('a.transaction_type', 'refund_fee');
                            $db->where('a.deleted', 0);
                            $db->join('xun_wallet_transaction b', 'a.reference_id = b.id', 'LEFT');
                            $invoice_transaction = $db->get('xun_payment_gateway_invoice_transaction a', null, 'a.id, a.credit, a.wallet_type, b.exchange_rate');
                
                            if($invoice_transaction){
                                $total_usd_amount = '0.00';
                                foreach($invoice_transaction as $key => $value){
                                    $credit = $value['credit'];
                                    
                                    $exchange_rate = $value['exchange_rate'];
                                    $id = $value['id'];
                
                                    $usd_amount =  $xunCurrency->get_conversion_amount('usd', $wallet_type, $credit, true, $exchange_rate);
                    
                                    $total_usd_amount = bcadd($total_usd_amount, $usd_amount, 2);
                                     
                                }
                            }

                            $total_usd_amount = ceil($total_usd_amount);

                            $remaining_refund_amount = bcsub($compensate_fee_amount, $total_usd_amount, 2);
                
                            if($remaining_refund_amount > 0){
                                $remaining_amount_usd = bcsub($remaining_refund_amount, $total_fee_charges_usd, 2);
                                
                                if($remaining_amount_usd  > 0){
                                    $refund_amount_usd = $total_fee_charges_usd;
                                }
                                else{
                                    $refund_amount_usd = $remaining_refund_amount;
                                } 
                                $refund_amount = $xunCurrency->get_conversion_amount($wallet_type, "usd", $refund_amount_usd);
        
                                $refund_fee_params = array(
                                    "business_id" => $user_id,
                                    "amount" => $refund_amount,
                                    "wallet_type" => $wallet_type,
                                );
                                $xunFreecoinPayout = new XunFreecoinPayout($db, $setting, $general);
                                $xunFreecoinPayout->process_refund_fee_transaction($refund_fee_params);
                    
                            }    

                            $tag = "Request Fund Withdrawal";
                            $message = "Business ID: ".$return['user_id']."\n";
                            $message .= "Tx Hash:".$transaction_hash."\n";
                            $message .= "Amount:" .$padded_amount."\n";
                            $message .= "Wallet Type:".$wallet_type."\n";
                            $message .= "Transaction Type: external\n";
                            $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                            $thenux_params["tag"]         = $tag;
                            $thenux_params["message"]     = $message;
                            $thenux_params["mobile_list"] = $xun_numbers;
                            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                            
                        }

                        if($status == 'failed'){
                            $balance = $xunPaymentGateway->getUserRequestFundBalance($wallet_type, $return['user_id']);
                            $total_balance = bcadd($balance, $padded_amount, 8);
                            
                            $insertTx = array(
                                "business_id" => $return['user_id'],
                                "sender_address" => $recipient_address,
                                "recipient_address" => $sender_address,
                                "amount" => $padded_amount,
                                "amount_satoshi" => $amount,
                                "wallet_type" => $wallet_type,
                                "credit" => $padded_amount,
                                "debit" => 0,
                                "balance" => $total_balance,
                                "transaction_type" => "refund_withdrawal",
                                "reference_id" => $return['id'],
                                "created_at" => $date,
                            );
                    
                            $invoice_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertTx);

                            $tag = "Request Fund Withdrawal Fail";
                            $message = "Business ID: ".$return['user_id']."\n";
                            $message .= "Amount:" .$padded_amount."\n";
                            $message .= "Wallet Type:".$wallet_type."\n";
                            $message .= "Miner Fee: " .$padded_fee." ".$feeUnit."\n";
                            $message .= "Transaction Type: external\n";
                            $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                            $thenux_params["tag"]         = $tag;
                            $thenux_params["message"]     = $message;
                            $thenux_params["mobile_list"] = $xun_numbers;
                            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                        }

                    }
                    else{

                        $transactionStatus = $return['status'];
                        $wallet_transaction_id = $return['id'];
                        $db->where('reference_id', $wallet_transaction_id);
                        $marketer_transaction_commission = $db->getOne('xun_marketer_commission_transaction');
                        $business_marketer_commission_id = $marketer_transaction_commission['business_marketer_commission_id'];
                        $walletType = $marketer_transaction_commission['wallet_type'];

                        $db->where('id', $business_marketer_commission_id);
                        $commission_scheme = $db->getOne('xun_business_marketer_commission_scheme');
                        $business_id = $commission_scheme['business_id'];

                        $db->where("user_id", $business_id);
                        $business_name = $db->getValue("xun_business", "name");

                        $db->where('wallet_type', $walletType);
                        $db->where('business_marketer_commission_id', $business_marketer_commission_id);
                        $db->orderBy('id', 'DESC');
                        $latest_commission_transaction = $db->getOne('xun_marketer_commission_transaction','SUM(credit) as sumCredit, SUM(debit) as sumDebit');
                        $balance_amount = '0.00000000';
                        if($latest_commission_transaction){
                            $sum_credit = $latest_commission_transaction['sumCredit'];
                            $sum_debit = $latest_commission_transaction['sumDebit'];

                            $balance_amount = bcsub($sum_credit, $sum_debit, 8);

                            //$balance = $latest_commission_transaction['balance'];
                        }
                    
                        $cryptoValue = bcdiv($amount, $amount_rate, 8);   

                        if(($transactionStatus == 'completed') && $return['address_type'] == 'marketer'){
                            $tag = "Marketer Fund Out";
                            $message = "Business Name:".$business_name."\n";
                            $message .= "Amount:" .$cryptoValue."\n";
                            $message .= "Wallet Type:".$walletType."\n";
                            $message .= "Miner Fee: " .$padded_fee." ".$feeUnit."\n";
                            $message .= "Status: ".$transactionStatus."\n";
                            $message .= "Time: ".date("Y-m-d H:i:s")."\n";
        
        
                            $thenux_params["tag"]         = $tag;
                            $thenux_params["message"]     = $message;
                            $thenux_params["mobile_list"] = $xun_numbers;
                            $xmpp_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                        }
                    
                        $totalCryptoAmount = bcadd($balance_amount, $cryptoValue, 8);
                        if(($transactionStatus == 'failed') && $return['address_type'] == 'marketer'){
                            $tag = "Failed Marketer Fund Out";
                            $message = "Business Name:".$business_name."\n";
                            $message .= "Amount:" .$totalCryptoAmount."\n";
                            $message .= "Wallet Type:".$walletType."\n";
                            $message .= "Miner Fee: " .$padded_fee." ".$feeUnit."\n";
                            $message .= "Status: ".$transactionStatus."\n";
                            $message .= "Time: ".date("Y-m-d H:i:s")."\n";
        
        
                            $thenux_params["tag"]         = $tag;
                            $thenux_params["message"]     = $message;
                            $thenux_params["mobile_list"] = $xun_numbers;
                            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                        }

                        
                        if($return['address_type'] == 'marketer' && $status == 'failed'){
                            $wallet_transaction_id = $return['id'];

                            $db->where('reference_id', $wallet_transaction_id);
                            $marketerCommissionTransaction = $db->getOne('xun_marketer_commission_transaction');
                            
                            $business_marketer_commission_id = $marketerCommissionTransaction['business_marketer_commission_id'];
                            $marketerCommissionWalletType = $marketerCommissionTransaction['wallet_type'];

                            $db->where('business_marketer_commission_id', $business_marketer_commission_id);
                            $db->where('wallet_type', $marketerCommissionWalletType);
                            $db->orderBy('id', 'DESC');
                            $latestMarketerCommissionTransaction = $db->getOne('xun_marketer_commission_transaction');
                            
                            $latestBalance = $latestMarketerCommissionTransaction['balance'];

                            $cryptoFiatArr[$wallet_type]['value'] = $cryptoValue;

                            $crypto_fiat_rate = $xunCurrency->calculate_crypto_fiat_rate($cryptoFiatArr);
                            $walletFiatRateUSD = $crypto_fiat_rate[$wallet_type]['usd'];

                            $newBalance = bcsub($latestBalance, $walletFiatRateUSD, 8);

                            //check if failed callback transaction is added, if no then add a return in marketer commission trasaction table
                            $db->where('type', 'Callback Failed');
                            $db->where('reference_id', $wallet_transaction_id);
                            $checkFailedTxAdded = $db->getOne('xun_marketer_commission_transaction');
                        
                            if(!$checkFailedTxAdded){
                        
                                $marketer_commission_transaction_id = $this->insertMarketerCommissionTransaction($business_marketer_commission_id, $cryptoValue, $amount, $marketerCommissionWalletType, $cryptoValue, 0, $totalCryptoAmount, 'Crypto Callback Failed', 'Callback Failed', $wallet_transaction_id);
                            }
                            
                        }
                    }

                }
            }
            elseif($transaction_type == "receive"){
                // $db->where("sender_address", $sender_address);
                // $db->where("recipient_address", $recipient_address);
                $db->where('ex_transaction_hash', $ex_transaction_hash);
                $db->where("recipient_address", $recipient_address);
                $tx_hash_result = $db->getOne('xun_crypto_transaction_hash');

                $db->where('bc_reference_id', $bc_reference_id);
                $db->where('transaction_hash', $ex_transaction_hash);
                $wallet_transaction_exist = $db->getOne('xun_wallet_transaction', 'id');

                if($tx_hash_result && $wallet_transaction_exist){
                    if($status == "confirmed"){
                        $address_type = "external_transfer";
                        $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type, $padded_fee);
                    }
                    elseif($status == "failed")
                    {
                        $address_type = "external_transfer";
                        $wallet_transaction_return = $this->update_wallet_transaction($params, $padded_amount, $address_type, $padded_fee);
                    }
                    else{
                        return $padded_amount;
                    }

                    $transaction_id = $wallet_transaction_return["id"];
                }
                else{    
                    $insertData = array(
                        "transaction_hash" => $transaction_hash,
                        "ex_transaction_hash" => $ex_transaction_hash,
                        "sender_address" => $sender_address,
                        "recipient_address" => $recipient_address,
                        "amount" => $padded_amount,
                        "type" => $target,
                        "wallet_type" => strtolower($wallet_type),
                        "transaction_token" => $transaction_token ? $transaction_token : '',
                        "status" => $status == 'confirmed' ? 'completed' : $status,
                        "created_at" => $date
                    );
    
                    $row_id = $db->insert("xun_crypto_transaction_hash", $insertData);

                    if(!$row_id){
                        $log->write(date("Y-m-d H:i:s") . " check_external_callback # Db error: " . $db->getLastError() . "\n");
                    }

                    if(!is_null($this->recipient_address_data)){
                        $recipientUserID = $this->recipient_address_data["user_id"];
                    }

                    // check if it's pg address
                    $db->where("crypto_address", $sender_address);
                    $xun_crypto_address = $db->getOne("xun_crypto_address", "id, crypto_address");

                    if($xun_crypto_address){
                        $address_type = "payment_gateway";
                    }else{
                        $address_type = "external_transfer";
                    }
                    
                    $transactionObj->status = $status;
                    $transactionObj->transactionHash = $ex_transaction_hash;
                    $transactionObj->transactionToken = "";
                    $transactionObj->senderAddress = $sender_address;
                    $transactionObj->recipientAddress = $recipient_address;
                    $transactionObj->userID = $recipientUserID ? $recipientUserID : '';
                    $transactionObj->senderUserID = '';
                    $transactionObj->recipientUserID = $recipientUserID;
                    $transactionObj->walletType = $wallet_type;
                    $transactionObj->amount = $padded_amount;
                    $transactionObj->addressType = $address_type;
                    $transactionObj->transactionType = $transaction_type;
                    $transactionObj->escrow = 0;
                    $transactionObj->referenceID = '';
                    $transactionObj->escrowContractAddress = '';
                    $transactionObj->createdAt = $date;
                    $transactionObj->updatedAt = $date;
                    $transactionObj->expiresAt = '';
                    $transactionObj->fee = $padded_fee;
                    $transactionObj->feeUnit = $feeUnit;
                    $transactionObj->bcReferenceID = $bc_reference_id;


                    $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);  


                    $txHistoryObj->status = $status;
                    $txHistoryObj->transactionID = $ex_transaction_hash;
                    $txHistoryObj->transactionToken = "";
                    $txHistoryObj->senderAddress = $sender_address;
                    $txHistoryObj->recipientAddress = $recipient_address;
                    $txHistoryObj->senderUserID = '';
                    $txHistoryObj->recipientUserID = $recipientUserID;
                    $txHistoryObj->walletType = $wallet_type;
                    $txHistoryObj->amount = $padded_amount;
                    $txHistoryObj->transactionType = $address_type;
                    $txHistoryObj->referenceID = '';
                    $txHistoryObj->createdAt = $date;
                    $txHistoryObj->updatedAt = $date;
                    $txHistoryObj->fee = $padded_fee;
                    $txHistoryObj->feeUnit = $feeUnit;
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
                    
                }
                
                $minerFeeSenderWalletAddress = $setting->systemSetting['minerFeeSenderWalletAddress'];
                $minerFeeFundInWalletAddress = explode(",", $minerFeeSenderWalletAddress);

                if($status == "confirmed"){
                    if($transaction_id){
                        if(in_array($recipient_address_type, ["company_pool", "company_acc", "miner_fee", 'trading_fee', 'miner_pool']) && in_array($sender_address, $minerFeeFundInWalletAddress)){
                            
                            $miner_fee_tx_data = array(
                                "address" => $recipient_address,
                                "wallet_type" => $wallet_type,
                                "reference_id" => $transaction_id,
                                "reference_table" => "xun_wallet_transaction",
                                "credit" => $padded_amount,
                                "type" => "fund_in"
                            );
    
                            $miner_fee_tx_id = $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);
                        }
                    }
                    
                    if($recipient_address_type == 'freecoin'){
                        $tag = "Freecoin Topup";
                        $message = "Amount: ".$padded_amount."\n";
                        $message .= "Wallet Type:".$wallet_type."\n";
                        $message .= "Time: ".date("Y-m-d H:i:s")."\n";
    
                        $thenux_params["tag"]         = $tag;
                        $thenux_params["message"]     = $message;
                        $thenux_params["mobile_list"] = $xun_numbers;
                        $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                    }
                }

                
            }

            return $padded_amount;
        }


        public function add_new_coin($params){
            global $log, $xunXmpp;

            $db = $this->db;

            $name = trim($params["name"]);
            $unit_conversion = trim($params["unit_conversion"]);
            $wallet_type = trim($params["wallet_type"]);
            $unit = trim($params["unit"]);
            $total_supply = trim($params["total_supply"]);
            
            if($name == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "name cannot be empty");
            }

            if($unit_conversion == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "unit_conversion cannot be empty");
            }

            if($wallet_type == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "wallet_type cannot be empty");
            }
            if($unit == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "unit cannot be empty");
            }

            $unit = strtolower($unit);
            $wallet_type = strtolower($wallet_type);
            $is_custom_coin = 0;
            $currency_type = "cryptocurrency";
            $date = date("Y-m-d H:i:s");

            $fiat_currency_id = "";
            $reference_price = 1;
            $this->handle_add_new_coin($name, $wallet_type, $unit, $unit_conversion, $currency_type, $is_custom_coin, $fiat_currency_id, $reference_price, $total_supply);

            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
        }

        public function handle_add_new_coin($name, $wallet_type, $unit, $unit_conversion, $currency_type, $is_custom_coin, $fiat_currency_id, $reference_price, $total_supply, $bg_image_url = "", $image_url = "", $image_md5 = "", $font_color = ""){
            global $log, $xun_numbers;
            $db = $this->db;

            $unit = strtolower($unit);
            $wallet_type = strtolower($wallet_type);
            $date = date("Y-m-d H:i:s");

            $insertCurrenciesData = array(
                "name" => $name,
                "type" => "cryptocurrency",
                "symbol" => $unit,
                "currency_id" => $wallet_type,
                "fiat_currency_id" => "",
                "unit_conversion" => $unit_conversion,
                "fiat_currency_id" => $fiat_currency_id,
                "fiat_currency_reference_price" => $reference_price,
                "total_supply" => $total_supply,
                "image" => $image_url,
                "image_md5" => $image_md5,
                "bg_image_url" => $bg_image_url,
                "status" => 0,
                "is_show_new_coin" => 0,
                "font_color" => $font_color,
                "created_at" => $date,
                "updated_at" => $date
            );

            try{
                $row_id = $db->insert("xun_marketplace_currencies", $insertCurrenciesData);
                if(!$row_id){
                    throw new Exception($db->getLastError());
                }
            }catch(Exception $e){
                $trace = $e->getTrace();
                $function = $trace[0]["function"];
                $class = $trace[0]["class"];
                $log->write("\n " . date("Y-m-d H:i:s") . " - Class: $class, Function: $function, Error: " . $e->getMessage());
            }
            
            $insertCoinData = array(
                "currency_id" => $wallet_type,
                "unit_conversion" => $unit_conversion,
                "type" => $currency_type,
                "is_show_new_coin" => 0,
                "is_custom_coin" => $is_custom_coin,
                "is_marketplace" => 0,
                "is_gift_code_coin" => 0,
                "is_payment_gateway" => 0,
                "is_pay" => 0,
                "created_at" => $date,
                "updated_at" => $date
            );

            try{
                $row_id = $db->insert("xun_coins", $insertCoinData);
                if(!$row_id){
                    throw new Exception($db->getLastError());
                }
            }catch(Exception $e){
                $trace = $e->getTrace();
                $function = $trace[0]["function"];
                $class = $trace[0]["class"];
                $log->write("\n " . date("Y-m-d H:i:s") . " - Class: $class, Function: $function, Error: " . $e->getMessage());
            }
            
            // $tag = "New Coin Added";
            // $content = "Name: " . $name;
            // $content .= "\nWallet type: " . $wallet_type;

            // $json_params = array(
            //     "business_id" => "1",
            //     "tag" => $tag,
            //     "message" => $content,
            //     "mobile_list" => $xun_numbers,
            // );

            // $insert_data = array(
            //     "data" => json_encode($json_params),
            //     "message_type" => "business",
            //     "created_at" => date("Y-m-d H:i:s"),
            //     "updated_at" => date("Y-m-d H:i:s"),
            // );

            // $ids = $db->insert('xun_business_sending_queue', $insert_data);
            // return $ids;
        }

        public function crypto_get_wallet_balance($params){
            $post = $this->post;
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();
            $xun_business_service = new XunBusinessService($db);

            $business_id = trim($params["business_id"]);
            $api_key = trim($params["api_key"]);
            $wallet_type = trim($params["wallet_type"]);

            if ($business_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business ID cannot be empty.");
            }
            if ($api_key == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "API key cannot be empty.");
            }
            if ($wallet_type == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet type cannot be empty.");
            }

            $crypto_api_key_validation = $this->validate_crypto_api_key($api_key, $business_id);

            if(isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0){
                if (!$xun_business_service->validateApiKey($business_id, $api_key)) {
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => $translations['E00148'][$language]/*Invalid Apikey.*/);
                }
            }

            $db->where('business_id', $business_id);
            $db->where('wallet_type', $wallet_type);
            $bc_external_address = $db->getOne('blockchain_external_address');
            if (!$bc_external_address){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business does not have an associated fund out address.");
            }

            $external_address = $bc_external_address["address"];

            //  validate wallet type
            $wallet_type = strtolower($wallet_type);
            $command = "getExternalBalance";
            $params = array(
                "walletType" => $wallet_type,
                "walletAddress" => $external_address
            );

            $crypto_result = $post->curl_crypto($command, $params, 2);

            if ($crypto_result['status'] == 'ok'){
                $crypto_data = $crypto_result['data'];
                $balance = $crypto_data['balance'];
                $unit = $crypto_data['unit'];
                $unit_conversion = $crypto_data['unitConversion'];

                $converted_balance = bcdiv((string)$balance, (string)$unit_conversion, log10($unit_conversion));
                $exchange_rate = $crypto_data['exchangeRate'];

                $return_data['balance'] = $converted_balance;
                $return_data['unit'] = $unit;
                $return_data['exchangeRate'] = $exchange_rate;

                return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "data" => $return_data);
            }else{
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_result['statusMsg']);
            }
        }

        public function crypto_external_transfer($params){
            global $setting, $xunServiceCharge, $xunCurrency, $xunPayment;
            $post = $this->post;
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();
            $xun_business_service = new XunBusinessService($db);

            $business_id = trim($params["business_id"]) ? trim($params["business_id"]) :  trim($params["account_id"]);
            $api_key = trim($params["api_key"]);
            $wallet_type = trim($params["wallet_type"]);
            $reference_id = trim($params["reference_id"]);
            $recipient_address = trim($params["recipient_address"]);
            $amount = trim($params["amount"]);
            $date = date("Y-m-d H:i:s");

            if ($business_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Account ID cannot be empty.");
            }
            if ($api_key == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "API key cannot be empty.");
            }
            if ($wallet_type == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet type cannot be empty.");
            }
            if ($reference_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Reference ID cannot be empty.");
            }
            if ($recipient_address == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Recipient Address cannot be empty.");
            }
            if ($amount == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Amount cannot be empty.");
            }

            $crypto_api_key_validation = $this->validate_crypto_api_key($api_key, $business_id);

            if(isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0){
                if (!$xun_business_service->validateApiKey($business_id, $api_key)) {
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => $translations['E00148'][$language]/*Invalid Apikey.*/);
                }
            }

            $db->where("id", $business_id);
            $user_data = $db->getOne("xun_user","id, type, service_charge_rate, nickname");
            $business_name = $user_data['nickname'];
            $service_charge_rate = $user_data["service_charge_rate"];
            // $db->where('user_id', $business_id);
            // $business_internal_address = $db->getValue('xun_crypto_user_address', 'address');

            //  validate wallet type
            $wallet_type = strtolower($wallet_type);
            
            //blockchain external address checking
            $db->where('wallet_type', $wallet_type);
            $db->where('business_id', $business_id);
            $db->where('status', 1);
            $bc_external_address = $db->getOne('blockchain_external_address');
            if (!$bc_external_address){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Business ID.");
            }
            $db->where("user_id", $business_id);
            $db->where("name", ["bcExternalTransferServiceChargeBearer", "bcExternalTransferMinerFeeBearer"], "IN");
            $bc_fee_bearer_setting_arr = $db->map("name")->ArrayBuilder()->get("xun_user_setting", null, "name, value");

            $service_charge_bearer = $bc_fee_bearer_setting_arr["bcExternalTransferServiceChargeBearer"];
            $miner_fee_bearer = $bc_fee_bearer_setting_arr["bcExternalTransferMinerFeeBearer"];

            $service_charge_bearer = $service_charge_bearer ?: "sender";
            $miner_fee_bearer = $miner_fee_bearer ?: "sender";

            $is_bc_coin_wallet = in_array($wallet_type, ["ethereum", "bitcoin"]);
            if($is_bc_coin_wallet && $miner_fee_bearer == "tokenWallet"){
                $miner_fee_bearer = "sender";
            }

            $business_external_address = $bc_external_address["address"];
            $tx_obj = new stdClass();
            $tx_obj->userID = $business_id;
            $tx_obj->address = $business_external_address;

            $transaction_token_arr = [];
            $tx_token_count = in_array($miner_fee_bearer, ["receiver", "tokenWallet"]) ? 3 : 2;

            for($i = 0; $i<$tx_token_count; $i++){
                $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);
                $transaction_token_arr[] = $transaction_token;
            }

            $satoshi_amount = $this->get_satoshi_amount($wallet_type, $amount);

            //  get service charge
            $service_charge_params = array(
                "user_id" => $business_id,
                "amount" => $amount,
                "wallet_type" => $wallet_type,
                "service_charge_rate" => $service_charge_rate
            );

            $service_charge_result = $this->get_user_service_charge($service_charge_params);
            // $company_pool_address = $setting->systemSetting["bcExternalCompanyPoolAddress"];
            $db->where('reference', "%$wallet_type%", 'LIKE');
            $db->where('name', 'bcExternalCompanyPoolAddress');
            $company_pool_address = $db->getValue('system_settings', 'value');

            $command = "externalTransfer";
            $params = array(
                "walletType" => $wallet_type,
                "walletAddress" => $business_external_address,
                "transactionToken" => $transaction_token_arr,
                "receiverAddress" => $recipient_address,
                "amount" => $satoshi_amount,
                "minerFeeBearer" => $miner_fee_bearer,
                "serviceChargeBearer" => $service_charge_bearer,
                "companyPoolAddress" => $company_pool_address ? $company_pool_address : '',
                "user_id" => $business_id
            );

            //System User
            $db->where("id", "1");
            $systemUserDetail = $db->getOne("xun_user");
            $service_charge_user_id = $systemUserDetail['id'];

            if($service_charge_result){
                $service_charge_amount = $service_charge_result["amount"];
                $service_charge_wallet_type = $service_charge_result["unit"];
                $service_charge_address = $service_charge_result["address"];
                $service_charge_amount_satoshi = $this->get_satoshi_amount($service_charge_wallet_type, $service_charge_amount);
                $external_address_data = $this->get_external_address($service_charge_address, $service_charge_wallet_type);
                if(isset($external_address_data["code"]) && $external_address_data["code"] == 0){
                    return $external_address_data;
                }

                $service_charge_external_address = $external_address_data;

                $params["serviceCharge"] = array(
                    "amount" => $service_charge_amount_satoshi,
                    "walletType" => $service_charge_wallet_type,
                    "address" => $service_charge_external_address,
                    "user_id" => $service_charge_user_id
                );
            }

            $insertData = array(
                'business_id' => $business_id,
                'sender_address' => $business_external_address,
                'recipient_address' => $recipient_address,
                'amount' => $amount,
                'wallet_type' => $wallet_type,
                'tx_token' => $transaction_token,
                'service_charge_address' => $service_charge_address ?: '',
                'service_charge_amount' => $service_charge_amount ?: 0,
                'service_charge_wallet_type' => $service_charge_wallet_type ?: '',
                'pool_address' => $company_pool_address ?: '',
                'reference_id' => $reference_id,
                "gateway_type" => "BC",
                'order_processed' => 1,
                'pool_transferred' => 1,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s")
            );
            $row_id = $db->insert('xun_crypto_fund_out_details', $insertData);
            if(!$row_id){
                return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error" => $db->getLastError());
            }

            $payment_transaction_params = array(
                "business_id" => $business_id,
                "crypto_amount" => $amount,
                "wallet_type" => $wallet_type,
                "transaction_type" => "auto_fund_out"
            );
        
            $payment_tx_id = $xunPayment->insert_payment_transaction($payment_transaction_params);

            if(!$payment_tx_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }
           
            $payment_method_params = array(
                "address" => $recipient_address,
                "wallet_type" => $wallet_type,
                "payment_tx_id" => $payment_tx_id,
                "type" => "external"
            );

    
            $payment_method_id = $xunPayment->insert_payment_method($payment_method_params);

            if(!$payment_method_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }

            $transactionObj->paymentID = $payment_id;
            $transactionObj->paymentTxID = $payment_tx_id;
            $transactionObj->paymentMethodID = $payment_method_id;
            $transactionObj->status = "pending";
            $transactionObj->senderInternalAddress ='';
            $transactionObj->senderExternalAddress = $business_external_address;
            $transactionObj->recipientInternalAddress = '';
            $transactionObj->recipientExternalAddress = $recipient_address;
            $transactionObj->senderUserID = '';
            $transactionObj->recipientUserID = $business_id;
            $transactionObj->walletType = $wallet_type;
            $transactionObj->amount = $amount;
            $transactionObj->serviceChargeAmount = $service_charge_amount;
            $transactionObj->serviceChargeWalletType = $service_charge_wallet_type;
            $transactionObj->referenceID = '';
            $transactionObj->createdAt = $date;

            $payment_details_id = $xunPayment->insert_payment_details($transactionObj);

            $crypto_result = $post->curl_crypto($command, $params, 2);

            if ($crypto_result['status'] == 'ok'){
                $crypto_data = $crypto_result['data'];
                $transaction_hash = $crypto_data['transactionHash'];
                $balance = $crypto_data['balance'];
                $unit = $crypto_data['unit'];
                $unit_conversion = $crypto_data['unitConversion'];

                $converted_balance = bcdiv($balance, $unit_conversion, 8);
                $exchange_rate = $crypto_data['exchangeRate'];

                $return_data = $crypto_data;
                unset($return_data["transactionHash"]);
                $return_data['transaction_hash'] = $transaction_hash;
                $return_data['balance'] = $converted_balance;
                $return_data['unit'] = $unit;
                $return_data['exchangeRate'] = $exchange_rate;

                $service_charge_details = $crypto_data['serviceChargeDetails'];
                $service_charge_tx_hash = $service_charge_details['transactionHash'];

                $fee_charge_details = $crypto_data['feeChargeDetails'];
                $fee_charge_amount = $fee_charge_details['amount'];
                $fee_charge_conversion_rate = $fee_charge_details['conversionRate'];
                
                // if(!$is_bc_coin_wallet){
                //     $fee_details = $crypto_data['feeDetails'];
                //     $fee_balance = $fee_details['balance'];
                //     $fee_conversion_rate = $fee_details['conversionRate'];
                //     $fee_wallet_type = $fee_details['walletType'];
    
                //     $fee_balance_decimal = $fee_balance ? bcdiv($fee_balance, $fee_conversion_rate, log10($fee_conversion_rate)) : 0;
    
                //     $miner_fee_notification_params = array(
                //         "balance" => $fee_balance_decimal,
                //         "wallet_type" => $fee_wallet_type,
                //         "business_id" => $business_id,
                //         "business_name" => $business_name,
                //         "address" => $business_external_address,
                //         "transaction_type" => "BC external fund out"
                //     );
                //     $this->miner_fee_low_balance_notification($miner_fee_notification_params);
                // }

                $pool_amount = $fee_charge_amount ? bcdiv($fee_charge_amount, $fee_charge_conversion_rate, log10($fee_charge_conversion_rate)) : 0;

                $tx_details = array(
                    'amountDetails' => $crypto_data['amountDetails'] ?: [],
                    'serviceChargeDetails' => $service_charge_details ?: [],
                    'feeChargeDetails' => $crypto_data['feeChargeDetails'] ?: [],
                    'feeDetails' => $crypto_data['feeDetails'] ?: [],
                );

                $updateData = array(
                    'status' => 'pending',
                    'tx_hash' => $transaction_hash,
                    'service_charge_tx_hash' => $service_charge_tx_hash ?: '',
                    'pool_tx_hash' => $crypto_data['feeChargeDetails']['transactionHash'] ?: '',
                    'pool_amount' => $pool_amount,
                    'pool_wallet_type' => $crypto_data['feeChargeDetails']['walletType'] ?: 0,
                    'exchange_rate' => $crypto_data['amountDetails']['exchangeRate']['USD'] ?: 0,
                    "miner_fee_exchange_rate" => $crypto_data['feeDetails']['exchangeRate']['USD'] ? $crypto_data['feeDetails']['exchangeRate']['USD'] : $crypto_data['feeChargeDetails']['exchangeRate']['USD'],
                    'transaction_details' => json_encode($tx_details),
                    "bc_reference_id" => $crypto_data['referenceID'],
                    'updated_at' => date("Y-m-d H:i:s")
                );
                $db->where("id", $row_id);
                $db->update('xun_crypto_fund_out_details', $updateData);
          
                $txHistoryObj->paymentDetailsID = $payment_details_id;
                $txHistoryObj->withdrawalID = $row_id;
                $txHistoryObj->status = "pending";
                $txHistoryObj->transactionID = $transaction_hash;
                $txHistoryObj->transactionToken = "";
                $txHistoryObj->senderAddress = $business_external_address;
                $txHistoryObj->recipientAddress = $recipient_address;
                $txHistoryObj->senderUserID = $business_id;
                $txHistoryObj->recipientUserID = "";
                $txHistoryObj->walletType = $wallet_type;
                $txHistoryObj->amount = $amount;
                $txHistoryObj->transactionType = "auto_fund_out";
                $txHistoryObj->referenceID = $reference_id;
                $txHistoryObj->createdAt = $date;
                $txHistoryObj->updatedAt = $date;
                // $transactionObj->fee = $final_miner_fee;
                // $transactionObj->feeWalletType = $miner_fee_wallet_type;
                // $txHistoryObj->exchangeRate = $exchange_rate;
                // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                $txHistoryObj->type = 'in';
                $txHistoryObj->gatewayType = "BC";
    
                $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);

                $fund_out_id = $transaction_history_result['transaction_history_id'];
                $fund_out_table = $transaction_history_result['table_name'];

                $updatePaymentDetails= array(
                    'status' => 'pending',
                    'fund_out_transaction_id' => $transaction_hash,
                    "fee_amount" => $pool_amount,
                    'fee_wallet_type' => $crypto_data['feeChargeDetails']['walletType'] ?: 0,
                    // 'tx_exchange_rate' => $crypto_data['amountDetails']['exchangeRate']['USD'] ?: 0,
                    // "miner_fee_exchange_rate" => $crypto_data['feeDetails']['exchangeRate']['USD'] ? $crypto_data['feeDetails']['exchangeRate']['USD'] : $crypto_data['feeChargeDetails']['exchangeRate']['USD'],
                    "fund_out_table" => $fund_out_table,
                    "fund_out_id" => $fund_out_id,
                    "reference_id" => $crypto_data['referenceID'],

                );

                $db->where('id', $payment_details_id);
                $db->update('xun_payment_details', $updatePaymentDetails);
            
                if($service_charge_amount > 0 && isset($service_charge_tx_hash)){
                    $xunWallet = new XunWallet($db);
            
                    $transactionObj = new stdClass();
                    $transactionObj->status = "wallet_success";
                    $transactionObj->transactionHash = $service_charge_tx_hash;
                    $transactionObj->transactionToken = "";
                    $transactionObj->senderAddress = $business_external_address;
                    $transactionObj->recipientAddress = $service_charge_address;
                    $transactionObj->userID = $business_id;
                    $transactionObj->senderUserID = $business_id;
                    $transactionObj->recipientUserID = "trading_fee";
                    $transactionObj->walletType = $service_charge_wallet_type;
                    $transactionObj->amount = $service_charge_amount;
                    $transactionObj->addressType = "service_charge";
                    $transactionObj->transactionType = "send";
                    $transactionObj->escrow = 0;
                    $transactionObj->referenceID = '';
                    $transactionObj->escrowContractAddress = '';
                    $transactionObj->createdAt = $date;
                    $transactionObj->updatedAt = $date;
                    $transactionObj->expiresAt = '';
    
                    $sc_transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

                    $txHistoryObj->paymentDetailsID = $payment_details_id;
                    $txHistoryObj->status = "wallet_success";
                    $txHistoryObj->transactionID = $service_charge_tx_hash;
                    $txHistoryObj->transactionToken = "";
                    $txHistoryObj->senderAddress = $business_external_address;
                    $txHistoryObj->recipientAddress = $service_charge_address;
                    $txHistoryObj->senderUserID = $business_id;
                    $txHistoryObj->recipientUserID = "trading_fee";
                    $txHistoryObj->walletType = $service_charge_wallet_type;
                    $txHistoryObj->amount = $service_charge_amount;
                    $txHistoryObj->transactionType = "service_charge";
                    $txHistoryObj->referenceID = '';
                    $txHistoryObj->createdAt = $date;
                    $txHistoryObj->updatedAt = $date;
                    // $transactionObj->fee = $final_miner_fee;
                    // $transactionObj->feeWalletType = $miner_fee_wallet_type;
                    // $txHistoryObj->exchangeRate = $exchange_rate;
                    // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                    $txHistoryObj->type = 'in';
                    $txHistoryObj->gatewayType = "BC";
        
                    $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                    $service_charge_fund_out_id = $transaction_history_result['transaction_history_id'];
                    $service_charge_fund_out_table = $transaction_history_result['table_name'];

                    $updateWalletTx = array(
                        "transaction_history_id" => $service_charge_fund_out_id,
                        "transaction_history_table" => $service_charge_fund_out_table
                    );
                    $xunWallet->updateWalletTransaction($sc_transaction_id, $updateWalletTx);
                    
                    $service_charge_user_id = $business_id;
                    
                    $new_params = array(
                        "user_id" => $service_charge_user_id,
                        "wallet_transaction_id" => $sc_transaction_id,
                        "transaction_hash" => $transaction_hash,
                        "service_charge_transaction_hash" => $service_charge_tx_hash,
                        "amount" => $service_charge_amount,
                        "wallet_type" => $service_charge_wallet_type,
                        "service_charge_type" => 'bc_external_transfer',
                        "transaction_type" => 'send',
                        "ori_tx_wallet_type" => $wallet_type,
                        "ori_tx_amount" => $amount,
                        "fund_out_table" => $service_charge_fund_out_table,
                        "fund_out_id" => $service_charge_fund_out_id
                    );
                    
                    $xunServiceCharge->insert_service_charge($new_params);

                    $update_fund_out_data = array(
                        "service_charge_wallet_tx_id" => $sc_transaction_id 
                    );
                    $db->where("id", $row_id);
                    $db->update('xun_crypto_fund_out_details', $update_fund_out_data);
                }

                $crypto_data['referenceID'] = $reference_id;
                
                $unitConversion = $crypto_data['amountDetails']['conversionRate'];
                $wallet_type = $crypto_data['amountDetails']['walletType'];

                $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
                $decimal_places = $decimal_place_setting["decimal_places"];
                
                $transaction_amount = $crypto_data['amountDetails']['amount'];
                $converted_transaction_amount = bcdiv($transaction_amount, $unitConversion, $decimal_places);
                $crypto_data['amountDetails']['amount'] = $converted_transaction_amount;

                $service_charge_amount = $crypto_data['serviceChargeDetails']['amount'];
                $converted_service_charge_amount = bcdiv($service_charge_amount, $unitConversion, $decimal_places);
                $crypto_data['serviceChargeDetails']['amount'] = $converted_service_charge_amount;

                $miner_fee_amount = $crypto_data['feeChargeDetails']['amount'];
                $converted_miner_fee_amount = bcdiv($miner_fee_amount, $unitConversion, $decimal_places);
                $crypto_data['feeChargeDetails']['amount'] = $converted_miner_fee_amount;

                if($crypto_data['feeDetails']){
                    $minerFeeUnitConversion = $crypto_data['feeDetails']['conversionRate'];
                    $minerFeeWalletType = $crypto_data['feeDetails']['walletType'];
                    
                    $miner_fee_decimal_place_setting = $xunCurrency->get_currency_decimal_places($minerFeeWalletType, true);
                    $miner_fee_decimal_place = $miner_fee_decimal_place_setting["decimal_places"];
                    
                    $original_miner_fee_amount = $crypto_data['feeDetails']['amount'];
                    $converted_ori_miner_fee = bcdiv($original_miner_fee_amount, $minerFeeUnitConversion, $miner_fee_decimal_place);
                    $crypto_data['feeDetails']['amount'] = $converted_ori_miner_fee;
                }

                unset($crypto_data['transactionDetails']);
                unset($crypto_data['transactionToken']);
                unset($crypto_data['amountDetails']['conversionRate']);
                unset($crypto_data['serviceChargeDetails']['conversionRate']);
                unset($crypto_data['feeChargeDetails']['conversionRate']);
                unset($crypto_data['feeDetails']['conversionRate']);
                unset($crypto_data['feeDetails']['balance']);  

                return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "data" => $crypto_data);
            }else{
                $updateData = array(
                    'status' => 'failed',
                    'remark' => $crypto_result['data']['errorMessage'] ? $crypto_result['data']['errorMessage'] : "Error from crypto",
                    'updated_at' => date("Y-m-d H:i:s")
                );
                $db->where("id", $row_id);
                $db->update('xun_crypto_fund_out_details', $updateData);

                $updatePaymentDetails = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $db->where('id', $payment_details_id);
                $db->update('xun_payment_details', $updatePaymentDetails);
                
                // $crypto_data = $crypto_result["data"];
                $crypto_data['referenceID'] = $reference_id;
                return array('code' => 0, 'message' => "FAILED", 'message_d' => ($crypto_result['data']['errorMessage'] ? $crypto_result['data']['errorMessage'] : "Error from crypto"), "data" => $crypto_data);
            }

        }
        
        public function wallet_server_verify_token($params){
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();

            $sender_address = trim($params['sender_address']);
            $transaction_token = trim($params['transaction_token']);

            if (!$transaction_token)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction Token cannot be empty.");

            $db->where('expires_at', date("Y-m-d H:i:s"), ">");     //expired checking
            $db->where('address', $sender_address);
            $db->where('transaction_token', $transaction_token);
            $tx_token_verification = $db->getOne('xun_crypto_user_transaction_verification');

            if(!$tx_token_verification){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "The transaction token is invalid.");
            }else if($tx_token_verification['verified'] == 1){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "The transaction token was used.");
            }else if($tx_token_verification['verified'] == 0){
                //update
                $updateData['verified'] = 1;
                $db->where('id', $tx_token_verification['id']);
                $db->update('xun_crypto_user_transaction_verification', $updateData);
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "The transaction token verified successfully.");
            }

        }

        public function crypto_external_fund_out_callback($crypto_params){
            global $xunCurrency, $setting, $xunServiceCharge, $xunPayment;
            $post = $this->post;
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();
            
            $reference_id = trim($crypto_params['referenceID']);
            $transaction_token = trim($crypto_params['transactionToken']);
            $transaction_hash = trim($crypto_params['transactionHash']);
            $transaction_details = $crypto_params['transactionDetails'];
            $amount_details = $crypto_params['amountDetails'];
            $feeDetails = $crypto_params['feeDetails'];
            $status = trim($crypto_params['status']);
            $serviceChargeDetails = $crypto_params['serviceChargeDetails'];
            $multiTxTokenWalletType = $setting->systemSetting['autoFundOutMultiTxTokenWalletType'];
            $multiTxTokenWalletTypeArr = explode(",", $multiTxTokenWalletType);
            $wallet_type = strtolower($crypto_params['amountDetails']['walletType']);
            

            if (!$reference_id)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Reference ID cannot be empty.");

            if (!$transaction_details)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction Details cannot be empty.");

            if(!in_array($wallet_type, $multiTxTokenWalletTypeArr)){
                if (!$transaction_hash)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction Hash cannot be empty.");
            }

            if (!$status)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Status cannot be empty.");

            if (!is_array($amount_details))
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Amount Details need to be in array form.");


            if($transaction_hash){
                $db->where("tx_hash", $transaction_hash);
                $crypto_fund_out = $db->getOne("xun_crypto_fund_out_details");
            }
          
            if (!$crypto_fund_out)
            {
                $db->where('bc_reference_id',$reference_id);
                $crypto_fund_out = $db->getOne("xun_crypto_fund_out_details");
                if(!$crypto_fund_out){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid reference id.");
                }
                
            }
            else{
                $hash_exist = 1;
            }

            $db->where('fund_out_transaction_id', $transaction_hash);
            $payment_details_data = $db->get('xun_payment_details');

            if(!$payment_details_data){
                $db->where('reference_id', $reference_id);
                $payment_details_data = $db->get('xun_payment_details');

                if(!$payment_details_data){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid reference id.");
                }
            }
            else{
                $payment_details_hash_exist = 1;
            }

            $business_external_address = $crypto_fund_out['sender_address'];
            $service_charge_address = $crypto_fund_out['service_charge_address'];
            $gateway_type = $crypto_fund_out['gateway_type'];

            $tx_details = array(
                'amountDetails' => $crypto_params['amountDetails'] ?: [],
                'serviceChargeDetails' => $serviceChargeDetails ?: [],
                'feeChargeDetails' => $crypto_params['feeChargeDetails'] ?: [],
                'feeDetails' => $crypto_params['feeDetails'] ?: [],
            );

            $unitConversion = $crypto_params['amountDetails']['conversionRate'];
            $wallet_type = strtolower($crypto_params['amountDetails']['walletType']);

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
            $decimal_places = $decimal_place_setting["decimal_places"];

            $service_charge_exchange_rate = $serviceChargeDetails['exchangeRate']['USD'];
            $service_charge_tx_hash = $serviceChargeDetails['transactionHash'];
            $service_charge_wallet_type = $serviceChargeDetails['walletType'];

            $service_charge_usd_amount = $xunCurrency->get_conversion_amount("usd", $wallet_type, $converted_service_charge_amount, true, $service_charge_exchange_rate);
                
            $fee_charge_details = $crypto_params['feeChargeDetails'];
            $pool_tx_hash = $fee_charge_details['transactionHash'];

            $pool_wallet_type = $fee_charge_details['walletType'];
            if($gateway_type == 'PG'){
                $transaction_amount = $crypto_params['amountDetails']['actualAmount'];
               
                $crypto_params['amountDetails']['amount'] = $transaction_amount;
    
                $total_service_charge_amount = $serviceChargeDetails['actualAmount'];
                $crypto_params['serviceChargeDetails']['amount'] = $total_service_charge_amount;
    
                $pool_amount = $fee_charge_details['actualAmount'];

                $crypto_params['feeChargeDetails']['amount'] = $pool_amount;
                $miner_fee_exchange_rate = $crypto_params['feeChargeDetails']['exchangeRate']['USD'];

                $db->where('status', 'failed', '!=');
                $db->where("bc_reference_id", $reference_id);
                $total_fund_out = $db->getValue('xun_crypto_fund_out_details', 'count(id)');
                
                $divided_pool_amount = bcdiv($pool_amount, $total_fund_out, 8);

                $actual_fee_amount = $crypto_params['feeDetails']['amount'];
                $actualUnitConversion = $crypto_params['feeDetails']['conversionRate'];

                $converted_actual_fee_amount = bcdiv($actual_fee_amount, $actualUnitConversion, 18);

                $actual_miner_fee_wallet_type = $crypto_params['feeDetails']['walletType'];

                $updateData = array(
                    "tx_hash" => $transaction_hash,
                    "transaction_details" => json_encode($tx_details),
                    "service_charge_tx_hash" => $transaction_hash,
                    "pool_tx_hash" => $transaction_hash,
                    "pool_amount" => $divided_pool_amount,
                    "pool_wallet_type" => strtolower($pool_wallet_type),
                    "exchange_rate" => $crypto_params['amountDetails']['exchangeRate']['USD'],
                    "miner_fee_exchange_rate" => $crypto_params['feeDetails']['exchangeRate']['USD'] ? $crypto_params['feeDetails']['exchangeRate']['USD'] : $crypto_params['feeChargeDetails']['exchangeRate']['USD'],
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                );
    
            }
            else{
                $transaction_amount = $crypto_params['amountDetails']['amount'];
                $converted_transaction_amount = bcdiv($transaction_amount, $unitConversion, $decimal_places);

                $crypto_params['amountDetails']['amount'] = $converted_transaction_amount;

                $service_charge_amount = $crypto_params['serviceChargeDetails']['amount'];
                $converted_service_charge_amount = bcdiv($service_charge_amount, $unitConversion, $decimal_places);

                $crypto_params['serviceChargeDetails']['amount'] = $converted_service_charge_amount;

                $miner_fee_amount = $crypto_params['feeChargeDetails']['amount'];

                $pool_amount = bcdiv($miner_fee_amount, $unitConversion, $decimal_places);

                $divided_pool_amount = $pool_amount;

                $crypto_params['feeChargeDetails']['amount'] = $divided_pool_amount;
            }
          
            $updateData = array(
                "tx_hash" => $transaction_hash,
                "transaction_details" => json_encode($tx_details),
                "service_charge_tx_hash" => $service_charge_tx_hash,
                "pool_tx_hash" => $pool_tx_hash,
                "pool_amount" => $divided_pool_amount,
                "pool_wallet_type" => strtolower($pool_wallet_type),
                "exchange_rate" => $crypto_params['amountDetails']['exchangeRate']['USD'],
                "miner_fee_exchange_rate" => $crypto_params['feeDetails']['exchangeRate']['USD'] ? $crypto_params['feeDetails']['exchangeRate']['USD'] : $crypto_params['feeChargeDetails']['exchangeRate']['USD'],
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            );

            if(!in_array($wallet_type, $multiTxTokenWalletTypeArr)){
            
                // $db->where('status', 'failed', '!=');
                if($hash_exist){
                    $db->where('tx_hash', $transaction_hash);
                }
                // else if(in_array($wallet_type, $multiTxTokenWalletTypeArr)){
                //     $db->where('transaction_token', $transaction_data['transaction_token']);
                // }
                else{
                    $db->where("bc_reference_id", $reference_id);
                }

                $db->where('tx_token', $transaction_token);
                $db->update('xun_crypto_fund_out_details', $updateData);


                $update_payment_details_data = array(
                    "fund_out_transaction_id" => $transaction_hash,
                    "service_charge_amount"  => $converted_service_charge_amount,
                    "service_charge_wallet_type" => strtolower($service_charge_wallet_type)? strtolower($service_charge_wallet_type) : $wallet_type,
                    "fee_amount" => $divided_pool_amount,
                    "fee_wallet_type" => strtolower($pool_wallet_type) ? strtolower($pool_wallet_type): $wallet_type,
                    "actual_fee_amount" => $converted_actual_fee_amount ? $converted_actual_fee_amount : '',
                    "actual_fee_wallet_type" => $actual_miner_fee_wallet_type ? $actual_miner_fee_wallet_type : '',
                    "status" => $status == 'confirmed' || $status == 'success' ? 'confirmed' : $status,
                    "updated_at" => date("Y-m-d H:i:s"),
                    
                );

                if($payment_details_hash_exist){
                    $db->where('fund_out_transaction_id', $transaction_hash);   
                }
                else{
                    $db->where('reference_id', $reference_id);
                }
                // $db->where('status', 'failed', '!=');
                $db->update('xun_payment_details', $update_payment_details_data);

                foreach($payment_details_data as $key => $payment_value){
                    $fund_out_table = $payment_value['fund_out_table'];
                    $fund_out_id = $payment_value['fund_out_id'];
        
                    $updateFundOut = array(
                        "transaction_id" => $transaction_hash,
                        "fee_amount" => $divided_pool_amount,
                        "fee_wallet_type" => $actual_miner_fee_wallet_type,
                        "exchange_rate" => $crypto_params['amountDetails']['exchangeRate']['USD'],
                        "miner_fee_exchange_rate" =>  $crypto_params['feeDetails']['exchangeRate']['USD'] ? $crypto_params['feeDetails']['exchangeRate']['USD'] : $crypto_params['feeChargeDetails']['exchangeRate']['USD'],
                        "status" => $status == 'confirmed' ? 'success' : $status,
                        "updated_at" => date("Y-m-d H:i:s")
                    );
                    
                    if($fund_out_table && $fund_out_id){
                        $db->where('id', $fund_out_id);
                        $db->update($fund_out_table, $updateFundOut);
                    }
                }
            }
            else{
                $transactionDetails = $crypto_params['transactionDetails'];
                $db->where('reference_id', $reference_id);
                // $db->where('status', 'failed', '!=');
                $payment_details_arr = $db->map('transaction_token')->ArrayBuilder()->get('xun_payment_details', null, 'id, transaction_token,  fund_out_id, fund_out_table');
                foreach($transaction_details as $txd_key => $txd_value){

                    if($txd_value['tag'] != 'destination'){
                        continue;
                    }
                    $transaction_hash = $txd_value['transactionHash'];
                    $transaction_token = $txd_value['transactionToken'];

                    $updateData = array(
                        "tx_hash" => $transaction_hash,
                        "transaction_details" => json_encode($tx_details),
                        "service_charge_tx_hash" => $service_charge_tx_hash,
                        "pool_tx_hash" => $pool_tx_hash ? $pool_tx_hash : '',
                        "pool_amount" => $divided_pool_amount,
                        "pool_wallet_type" => strtolower($pool_wallet_type),
                        "exchange_rate" => $crypto_params['amountDetails']['exchangeRate']['USD'],
                        "miner_fee_exchange_rate" => $crypto_params['feeDetails']['exchangeRate']['USD'] ? $crypto_params['feeDetails']['exchangeRate']['USD'] : $crypto_params['feeChargeDetails']['exchangeRate']['USD'],
                        'status' => $status,
                        'updated_at' => date('Y-m-d H:i:s')
                    );

 
                    // $db->where('status', 'failed', '!=');
                    $db->where('tx_token', $txd_value['transactionToken']);
                    $db->update('xun_crypto_fund_out_details', $updateData);

                    $update_payment_details_data = array(
                        "fund_out_transaction_id" => $transaction_hash,
                        // "service_charge_amount"  => $converted_service_charge_amount,
                        "service_charge_wallet_type" => strtolower($service_charge_wallet_type),
                        "fee_amount" => $divided_pool_amount,
                        "fee_wallet_type" => strtolower($pool_wallet_type),
                        "actual_fee_amount" => $converted_actual_fee_amount,
                        "actual_fee_wallet_type" => $actual_miner_fee_wallet_type,
                        "status" => $status == 'confirmed' || $status == 'success' ? 'confirmed' : $status,
                        "updated_at" => date("Y-m-d H:i:s"),
                        
                    );

                    // if($payment_details_hash_exist){
                    //     $db->where('fund_out_transaction_id', $transaction_hash);   
                    // }
                    // else{
                        $db->where('transaction_token', $transaction_token);
                    // }
                    $db->where('reference_id', $reference_id);
                    $db->where('status', 'failed', '!=');
                    $db->update('xun_payment_details', $update_payment_details_data);

                    $payment_details_data = $payment_details_arr[$transaction_token];

                    $fund_out_table = $payment_details_data['fund_out_table'];
                    $fund_out_id = $payment_details_data['fund_out_id'];

                    $updateFundOut = array(
                        "transaction_id" => $transaction_hash,
                        "fee_amount" => $divided_pool_amount,
                        "fee_wallet_type" => $actual_miner_fee_wallet_type,
                        "exchange_rate" => $crypto_params['amountDetails']['exchangeRate']['USD'],
                        "miner_fee_exchange_rate" =>  $crypto_params['feeDetails']['exchangeRate']['USD'] ? $crypto_params['feeDetails']['exchangeRate']['USD'] : $crypto_params['feeChargeDetails']['exchangeRate']['USD'],
                        "status" => $status == 'confirmed' ? 'success' : $status,
                        "updated_at" => date("Y-m-d H:i:s")
                    );
                    
                    if($fund_out_table && $fund_out_id){
                        $db->where('id', $fund_out_id);
                        $db->update($fund_out_table, $updateFundOut);
                    }
                }
                
            }

            //  service charge
            $user_id = $crypto_fund_out["business_id"];

            $db->where("user_id", $user_id);
            $callback_url = $db->getValue("xun_business", "pg_callback_url");
            
            if(empty($callback_url)){
                $db->where('id', $user_id);
                $callback_url = $db->getValue('xun_user', 'wallet_callback_url');
            }

            $db->where('id', $user_id);
            $user_data = $db->getOne('xun_user', 'id,nickname,  reseller_id');
            $reseller_id = $user_data['reseller_id'];

            $miner_fee_usd_amount = $xunCurrency->get_conversion_amount("usd", $wallet_type, $converted_miner_fee_amount, true, $miner_fee_exchange_rate);

            $total_fee_charges_usd = bcadd($service_charge_usd_amount, $miner_fee_usd_amount, 2);

            if($status == 'confirmed'){
                $compensate_fee_amount = $setting->systemSetting['compensateFeeAmount']; //Compensate Fee Amount (USD)

                if($compensate_fee_amount > 0 && $reseller_id != '0'){
                 
                    $db->where('a.business_id', $user_id);
                    $db->where('a.transaction_type', 'refund_fee');
                    $db->where('a.deleted', 0);
                    $db->join('xun_wallet_transaction b', 'a.reference_id = b.id', 'LEFT');
                    $invoice_transaction = $db->get('xun_payment_gateway_invoice_transaction a', null, 'a.id, a.credit, a.wallet_type, b.exchange_rate');
        
                    if($invoice_transaction){
                        $total_usd_amount = '0.00';
                        foreach($invoice_transaction as $key => $value){
                            $credit = $value['credit'];
                            
                            $exchange_rate = $value['exchange_rate'];
                            $id = $value['id'];
        
                            $usd_amount =  $xunCurrency->get_conversion_amount('usd', $wallet_type, $credit, true, $exchange_rate);
            
                            $total_usd_amount = bcadd($total_usd_amount, $usd_amount, 2);
                             
                        }
                    }

                    $total_usd_amount = ceil($total_usd_amount);

                    $remaining_refund_amount = bcsub($compensate_fee_amount, $total_usd_amount, 2);
        
                    if($remaining_refund_amount > 0){
                        $remaining_amount_usd = bcsub($remaining_refund_amount, $total_fee_charges_usd, 2);
                        
                        if($remaining_amount_usd  > 0){
                            $refund_amount_usd = $total_fee_charges_usd;
                        }
                        else{
                            $refund_amount_usd = $remaining_refund_amount;
                        } 
    
                        $refund_amount = $xunCurrency->get_conversion_amount($wallet_type, "usd", $refund_amount_usd);

                        $refund_fee_params = array(
                            "business_id" => $user_id,
                            "amount" => $refund_amount,
                            "wallet_type" => $wallet_type,
                        );
                        $xunFreecoinPayout = new XunFreecoinPayout($db, $setting, $general);
                        $xunFreecoinPayout->process_refund_fee_transaction($refund_fee_params);
            
                    }    
                }
                    
            }

            $db->where('transaction_hash', $service_charge_tx_hash);
            $db->where('address_type', 'service_charge');
            $service_charge_wallet_tx = $db->getOne('xun_wallet_transaction');

            if($total_service_charge_amount > 0 && isset($service_charge_tx_hash) && !$service_charge_wallet_tx){
                $xunWallet = new XunWallet($db);
        
                $ret_val= $this->crypto_validate_address($service_charge_address, $wallet_type, 'external');
                if($ret_val['code'] == 1){
                    $service_charge_internal_address = $service_charge_address;
                }
                else{
                    $service_charge_internal_address = $ret_val['data']['address'];
                    $transaction_type = $ret_val['data']['addressType'];
                }
                $transactionObj = new stdClass();
                $transactionObj->status = "wallet_success";
                $transactionObj->transactionHash = $service_charge_tx_hash;
                $transactionObj->transactionToken = "";
                $transactionObj->senderAddress = $business_external_address;
                $transactionObj->recipientAddress = $service_charge_internal_address;
                $transactionObj->userID = $user_id;
                $transactionObj->senderUserID = $user_id;
                $transactionObj->recipientUserID = "trading_fee";
                $transactionObj->walletType = $service_charge_wallet_type;
                $transactionObj->amount = $total_service_charge_amount;
                $transactionObj->addressType = "service_charge";
                $transactionObj->transactionType = "send";
                $transactionObj->escrow = 0;
                $transactionObj->referenceID = '';
                $transactionObj->escrowContractAddress = '';
                $transactionObj->createdAt = $date;
                $transactionObj->updatedAt = $date;
                $transactionObj->exchangeRate = $crypto_params['serviceChargeDetails']['exchangeRate']['USD'];
                $transactionObj->expiresAt = '';

                $sc_transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

                $transactionType = "service_charge";

                $txHistoryObj->paymentDetailsID = $payment_details_id;
                $txHistoryObj->status = "wallet_success";
                $txHistoryObj->transactionID = $service_charge_tx_hash;
                $txHistoryObj->transactionToken = "";
                $txHistoryObj->senderAddress = $business_external_address;
                $txHistoryObj->recipientAddress = $recipient_address;
                $txHistoryObj->senderUserID = $user_id;
                $txHistoryObj->recipientUserID = "trading_fee";
                $txHistoryObj->walletType = $service_charge_wallet_type;
                $txHistoryObj->amount = $total_service_charge_amount;
                $txHistoryObj->transactionType = "service_charge";
                $txHistoryObj->referenceID = '';
                $txHistoryObj->createdAt = $date;
                $txHistoryObj->updatedAt = $date;
                // $transactionObj->fee = $final_miner_fee;
                // $transactionObj->feeWalletType = $miner_fee_wallet_type;
                // $txHistoryObj->exchangeRate = $exchange_rate;
                // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                $txHistoryObj->type = 'in';
                $txHistoryObj->gatewayType = "BC";
    
                $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                $fund_out_id = $transaction_history_result['transaction_history_id'];
                $fund_out_table = $transaction_history_result['table_name'];

                $updateWalletTx = array(
                    "transaction_history_id" => $fund_out_id,
                    "transaction_history_table" => $fund_out_table
                );
                $xunWallet->updateWalletTransaction($sc_transaction_id, $updateWalletTx);

                $service_charge_user_id = $user_id;
                
                $ori_tx_amount = bcadd($transaction_amount, $pool_amount,2);
                $new_params = array(
                    "user_id" => $service_charge_user_id,
                    "wallet_transaction_id" => $sc_transaction_id,       
                    "transaction_hash" => $transaction_hash,
                    "service_charge_transaction_hash" => $service_charge_tx_hash,
                    "fund_out_table" => $fund_out_table,
                    "fund_out_id" => $fund_out_id,
                    "amount" => $total_service_charge_amount,
                    "wallet_type" => strtolower($service_charge_wallet_type),
                    "service_charge_type" => 'pg_external_transfer',
                    "transaction_type" => 'send',
                    "ori_tx_wallet_type" => $wallet_type,
                    "ori_tx_amount" => $ori_tx_amount
                );
                
                $xunServiceCharge->insert_service_charge($new_params);

                $update_fund_out_data = array(
                    "service_charge_wallet_tx_id" => $sc_transaction_id 
                );
                $db->where("bc_reference_id", $reference_id);
                $db->update('xun_crypto_fund_out_details', $update_fund_out_data);
            }


            if($crypto_params['feeDetails']){
                $minerFeeUnitConversion = $crypto_params['feeDetails']['conversionRate'];
                $minerFeeWalletType = $crypto_params['feeDetails']['walletType'];
                
                if($gateway_type == 'PG'){
                    $original_miner_fee_amount = $crypto_params['feeDetails']['actualAmount'];
                    $crypto_params['feeDetails']['amount'] = $original_miner_fee_amount;
                }
                else{
                    $miner_fee_decimal_place_setting = $xunCurrency->get_currency_decimal_places($minerFeeWalletType, true);
                    $miner_fee_decimal_place = $miner_fee_decimal_place_setting["decimal_places"];
                    
                    $original_miner_fee_amount = $crypto_params['feeDetails']['amount'];
                    $converted_ori_miner_fee = bcdiv($original_miner_fee_amount, $minerFeeUnitConversion, $miner_fee_decimal_place);
                    $crypto_params['feeDetails']['amount'] = $converted_ori_miner_fee;
                }
                
            }

            $transactionDetails = $crypto_params['transactionDetails'];
            foreach($transactionDetails as $key => $value){

                $tag = $value['tag'];
                if($tag != 'destination'){
                    unset($transactionDetails[$key]);
                }
                else{
                    $transactionDetails[$key]['amount'] = $value['actualAmount'];
                    unset($transactionDetails[$key]['actualAmount']);
                    unset($transactionDetails[$key]['conversionRate']);
                    unset($transactionDetails[$key]['tag']);

                }
            }

            $crypto_params['transactionDetails'] = $transactionDetails;
            // unset($crypto_params['transactionDetails']);
            unset($crypto_params['transactionToken']);
            unset($crypto_params['amountDetails']['conversionRate']);
            unset($crypto_params['amountDetails']['actualAmount']);
            unset($crypto_params['serviceChargeDetails']['conversionRate']);
            unset($crypto_params['serviceChargeDetails']['actualAmount']);
            unset($crypto_params['feeChargeDetails']['conversionRate']);
            unset($crypto_params['feeChargeDetails']['actualAmount']);
            unset($crypto_params['feeDetails']['conversionRate']);
            unset($crypto_params['feeDetails']['balance']);
            unset($crypto_params['feeDetails']['actualAmount']);

            $params = $crypto_params;
            $params['referenceID'] = $crypto_fund_out['reference_id'];

            if (!empty($callback_url)){
                //send callback
                $curl_params = array(
                    "command" => "externalFundOutCallback",
                    "params" => $params
                );

                $cryptoResult = $post->curl_post($callback_url, $curl_params, 0);
                // $cryptoResult = $post->curl_crypto("externalFundOutCallback", $params, 0, $callback_url);
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "External Fund Out Callback succesfully.");
            }else{
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid user callback url.", 'test' => $user_data);
            }
        }

        public function crypto_external_transfer_by_batch($params){
            global $xunServiceCharge, $setting, $xunPayment;
            $post = $this->post;
            $db = $this->db;
            $general = $this->general;
            $language = $general->getCurrentLanguage();
            $translations = $general->getTranslations();
            $xun_business_service = new XunBusinessService($db);

            $business_id = trim($params["business_id"]) ? trim($params["business_id"]) :  trim($params["account_id"]);
            $api_key = trim($params["api_key"]);
            $wallet_type = trim($params["wallet_type"]);
            $transaction_details = $params['transaction_details'];
            $reference_id = trim($params["reference_id"]);
            // $recipient_address = trim($params["receiverAddress"]);
            // $amount = trim($params["amount"]);

            if ($business_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Account ID cannot be empty.");
            }
            if ($api_key == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "API key cannot be empty.");
            }
            if ($wallet_type == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet type cannot be empty.");
            }
            if ($reference_id == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Reference ID cannot be empty.");
            }
            // if ($recipient_address == '') {
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Recipient Address cannot be empty.");
            // }
            // if ($amount == '') {
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Amount cannot be empty.");
            // }
            if(!is_array($transaction_details)){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction Details should be in array form.");
            }
            if(count($transaction_details) < 1){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction Details cannot be empty.");
            }

            $crypto_api_key_validation = $this->validate_crypto_api_key($api_key, $business_id);

            if(isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0){
                if (!$xun_business_service->validateApiKey($business_id, $api_key)) {
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => $translations['E00148'][$language]/*Invalid Apikey.*/);
                }
            }

            // $db->where('user_id', $business_id);
            // $business_internal_address = $db->getValue('xun_crypto_user_address', 'address');
            $wallet_type = strtolower($wallet_type);
            // if($wallet_type != 'bitcoin'){
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet Type only accept bitcoin.");
            // }
            //blockchain external address checking
            $db->where('wallet_type', $wallet_type);
            $db->where('business_id', $business_id);
            $db->where('status', 1);
            $bc_external_address = $db->getOne('blockchain_external_address');
            if (!$bc_external_address){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Business ID.");
            }

            $db->where("id", $business_id);
            $user_data = $db->getOne("xun_user","id, type, service_charge_rate, nickname");
            $business_name = $user_data['nickname'];
            $service_charge_rate = $user_data["service_charge_rate"];

            $multiTxTokenWalletType = $setting->systemSetting['autoFundOutMultiTxTokenWalletType'];
            $multiTxTokenWalletTypeArr = explode(",", $multiTxTokenWalletType);
            
            $business_external_address = $bc_external_address["address"];

            $transaction_token = "";
           
            // //IF it is filecoin this transaction is generated for service charge transaction
            // $tx_obj = new stdClass();
            // $tx_obj->userID = $business_id;
            // $tx_obj->address = $business_external_address;

            // $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);

            if(in_array($wallet_type, $multiTxTokenWalletTypeArr)){
                $business_external_address = $bc_external_address["address"];
                $tx_obj = new stdClass();
                $tx_obj->userID = $business_id;
                $tx_obj->address = $business_external_address;
                $tx_obj->totalToken = count($transaction_details)+1;
    
                $receiver_transaction_token_arr = $xun_business_service->insertMultiCryptoTransactionToken($tx_obj);
                $transaction_token = $receiver_transaction_token_arr[0];
              
            }
            else{
                $tx_obj = new stdClass();
                $tx_obj->userID = $business_id;
                $tx_obj->address = $business_external_address;

                $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);
            }

           
            $total_amount = 0;
            $total_service_charge_amount = 0;
            $service_charge_external_address = '';
            $txTokenIndex = 1;
            foreach($transaction_details as &$transaction){
                if ($transaction['amount'] < 0 || empty($transaction['amount']))
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Transaction Amount.");

                if (empty($transaction['recipient_address']))
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Recipient Address.");

                $amount = $transaction['amount'];
                $satoshi_amount = $this->get_satoshi_amount($wallet_type, $amount);
                $service_charge_amount = 0;
                $service_charge_amount_satoshi = 0;
                if($service_charge_rate > 0){
                  
                    $service_charge_params = array(
                        "user_id" => $business_id,
                        "amount" => $amount,
                        "wallet_type" => $wallet_type,
                        "service_charge_rate" => $service_charge_rate
                    );
        
                    $service_charge_result = $this->get_user_service_charge($service_charge_params);
                    if($service_charge_result){
                        $service_charge_amount = $service_charge_result["amount"];
                        $service_charge_wallet_type = $service_charge_result["unit"];
                        $service_charge_address = $service_charge_result["address"];
                        $service_charge_amount_satoshi = $this->get_satoshi_amount($service_charge_wallet_type, $service_charge_amount);
                        $external_address_data = $this->get_external_address($service_charge_address, $service_charge_wallet_type);
                        if(isset($external_address_data["code"]) && $external_address_data["code"] == 0){
                            return $external_address_data;
                        }
                        
                        $total_service_charge_amount = bcadd($total_service_charge_amount, $service_charge_amount, 8);
        
                        $service_charge_external_address = $external_address_data;
                    }
                }

                $crypto_params['receiverAddress'] = $transaction['recipient_address'];
                $crypto_params['amount'] = $satoshi_amount;
                $crypto_params['serviceChargeAmount'] = $service_charge_amount_satoshi;

                if(in_array($wallet_type, $multiTxTokenWalletTypeArr)){
        
                    $crypto_params['transactionToken'] = $receiver_transaction_token_arr[$txTokenIndex];
                    $transaction['transaction_token'] = $receiver_transaction_token_arr[$txTokenIndex];
                    // unset($receiver_transaction_token_arr[$txTokenIndex]);
                }
                $transaction['service_charge_amount'] = $service_charge_amount;

                $crypto_transactionDetails[] = $crypto_params;
                
                $txTokenIndex++;
            }

            $db->where("user_id", $business_id);
            $db->where("name", ["bcExternalTransferServiceChargeBearer", "bcExternalTransferMinerFeeBearer"], "IN");
            $bc_fee_bearer_setting_arr = $db->map("name")->ArrayBuilder()->get("xun_user_setting", null, "name, value");

            $service_charge_bearer = $bc_fee_bearer_setting_arr["bcExternalTransferServiceChargeBearer"];
            $miner_fee_bearer = $bc_fee_bearer_setting_arr["bcExternalTransferMinerFeeBearer"];

            $service_charge_bearer = $service_charge_bearer ?: "sender";
            $miner_fee_bearer = $miner_fee_bearer ?: "sender";

            $is_bc_coin_wallet = in_array($wallet_type, ["ethereum", "bitcoin", "filecoin"]);
            if($is_bc_coin_wallet && $miner_fee_bearer == "tokenWallet"){
                $miner_fee_bearer = "sender";
            }

            //  get service charge
            $service_charge_params = array(
                "user_id" => $business_id,
                "amount" => $total_amount,
                "wallet_type" => $wallet_type,
                "service_charge_rate" => $service_charge_rate
            );

            $service_charge_result = $this->get_user_service_charge($service_charge_params);

            // $company_pool_address = $setting->systemSetting["bcExternalCompanyPoolAddress"];

            $db->where('reference', "%$wallet_type%", 'LIKE');
            $db->where('name', 'bcExternalCompanyPoolAddress');
            $company_pool_address = $db->getValue('system_settings', 'value');

            //  validate wallet type
            $command = "externalTransferByBatch";
            $params = array(
                "walletType" => $wallet_type,
                "walletAddress" => $business_external_address,
                "transactionToken" => $transaction_token,
                "transactionDetails" => $crypto_transactionDetails,
                "minerFeeBearer" => $miner_fee_bearer,
                "serviceChargeBearer" => $service_charge_bearer,
                "serviceChargeAddress" => $service_charge_external_address,
                "poolAddress" => $company_pool_address ? $company_pool_address : '',
                "userID" => $business_id,
                "serviceUserID" => "1"
            );

            $payment_transaction_params = array(
                "business_id" => $business_id,
                "crypto_amount" => $amount,
                "wallet_type" => $wallet_type,
                "transaction_type" => "auto_fund_out"
            );
        
            $payment_tx_id = $xunPayment->insert_payment_transaction($payment_transaction_params);

            if(!$payment_tx_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }

            foreach($transaction_details as $transaction_data){

                if(in_array($wallet_type, $multiTxTokenWalletTypeArr)){
                   $transaction_token = $transaction_data['transaction_token'];
                }
               
                $insertData = array(
                    'business_id' => $business_id,
                    'sender_address' => $business_external_address,
                    'recipient_address' => $transaction_data['recipient_address'],
                    'amount' => $transaction_data['amount'],
                    'wallet_type' => $wallet_type,
                    'tx_token' => $transaction_token,
                    'reference_id' => $reference_id,
                    "pool_address" => $company_pool_address ? $company_pool_address: '',
                    "gateway_type" => "PG",
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s")
                );
            
                if($transaction['service_charge_amount'] > 0){
                    $insertData['service_charge_address'] = $service_charge_external_address ?: '';
                    $insertData['service_charge_amount'] = $transaction_data['service_charge_amount'] ?: 0;
                    $insertData['service_charge_wallet_type'] = $service_charge_wallet_type ?: '';
                }

                $row_id = $db->insert('xun_crypto_fund_out_details', $insertData);

                $row_ids[] = $row_id;

                $payment_method_params = array(
                    "address" => $transaction_data['recipient_address'],
                    "wallet_type" => $wallet_type,
                    "payment_tx_id" => $payment_tx_id,
                    "type" => "external"
                );
    
                $payment_method_id = $xunPayment->insert_payment_method($payment_method_params);
    
                if(!$payment_method_id){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
                }
    
                $transactionObj->paymentID = $payment_id;
                $transactionObj->paymentTxID = $payment_tx_id;
                $transactionObj->paymentMethodID = $payment_method_id;
                $transactionObj->status = "pending";
                $transactionObj->senderInternalAddress ='';
                $transactionObj->senderExternalAddress = $business_external_address;
                $transactionObj->recipientInternalAddress = '';
                $transactionObj->recipientExternalAddress = $transaction_data['recipient_address'];
                $transactionObj->senderUserID = $business_id;
                $transactionObj->recipientUserID = '';
                $transactionObj->walletType = $wallet_type;
                $transactionObj->amount = $transaction_data['amount'];
                $transactionObj->transactionToken = $transaction_data['transaction_token'];
                if($transaction['service_charge_amount'] > 0){
                    $transactionObj->serviceChargeAmount = $transaction_data['service_charge_amount'] ?: 0;;
                    $transactionObj->serviceChargeWalletType =  $service_charge_wallet_type ?: '';
                }
                $transactionObj->referenceID = '';
                $transactionObj->createdAt = $date;
    
                $payment_details_id = $xunPayment->insert_payment_details($transactionObj);
                $payment_details_ids[] = $payment_details_id;

                $txHistoryObj->paymentDetailsID = $payment_details_id;
                $txHistoryObj->withdrawalID = $row_id;
                $txHistoryObj->status = "pending";
                $txHistoryObj->transactionID = "";
                $txHistoryObj->transactionToken = $transaction_data['transaction_token'];
                $txHistoryObj->senderAddress = $business_external_address;
                $txHistoryObj->recipientAddress = $transaction_data['recipient_address'];
                $txHistoryObj->senderUserID = $business_id;
                $txHistoryObj->recipientUserID = "";
                $txHistoryObj->walletType = $wallet_type;
                $txHistoryObj->amount = $transaction_data['amount'];
                $txHistoryObj->transactionType = "auto_fund_out";
                $txHistoryObj->referenceID = '';
                $txHistoryObj->createdAt = $date;
                $txHistoryObj->updatedAt = $date;
                $txHistoryObj->type = 'out';
                $txHistoryObj->gatewayType = "PG";
    
                $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                $fund_out_id = $transaction_history_result['transaction_history_id'];
                $fund_out_table = $transaction_history_result['table_name'];

                $updatePaymentDetails = array(
                    "fund_out_table" => $fund_out_table,
                    "fund_out_id" => $fund_out_id
                );

                $db->where('id', $payment_details_id);
                $db->update('xun_payment_details', $updatePaymentDetails);
            }

            // try{
            //     $row_id = $db->insertMulti('xun_crypto_fund_out_details', $multiple_insertData);
            // }
            // catch (Exception $e) {
            //     return array("code" => 0, "message" => "FAILED", "message_d" => "Failed to insert data.", "error" => $db->getLastError());
            // }

            $crypto_result = $post->curl_crypto($command, $params, 1);

            if ($crypto_result['status'] == 'ok'){
                $crypto_data = $crypto_result['data'];
                // $transaction_hash = $crypto_data['transactionHash'];
                $transaction_details = $crypto_data['transactionDetails'];
                $bc_reference_id = $crypto_data['referenceID'];
                $error_details = $crypto_data['errorDetails'];

                $updateData = array(                 
                    'bc_reference_id' => $bc_reference_id,
   
                );
                $db->where("id", $row_ids, 'IN');
                $db->update('xun_crypto_fund_out_details', $updateData);

                $updatePaymentDetailsData = array(
                    "reference_id"=> $bc_reference_id
                );
                $db->where("id", $payment_details_ids, 'IN');
                $db->update('xun_payment_details', $updatePaymentDetailsData);

                $receiver_address_arr = [];
                if($error_details){
                    foreach($error_details as $key => $value){
                        $receiver_address = $value['receiverAddress'];
                        $error_message = $value['reason'];

                        $update_status = array(
                            "status" => 'failed',
                            "remark" => $error_message,
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('recipient_address', $receiver_address);
                        $db->where('bc_reference_id', $bc_reference_id);
                        $updated = $db->update('xun_crypto_fund_out_details', $update_status);
                        $receiver_address_arr[] = $receiver_address;

                        $update_fund_out = array(
                            "status" => 'failed',
                            "updated_at" => date("Y-m-d H:i:s")
                        );

                        $db->where('recipient_external_address', $receiver_address);
                        $db->where('reference_id', $bc_reference_id);
                        $updated = $db->update('xun_payment_details', $update_fund_out);

                        $db->where('reference_id', $bc_reference_id);
                        $db->where('recipient_external_address', $receiver_address);
                        $payment_details = $db->get('xun_payment_details', null, 'fund_out_table, fund_out_id');

                        if($payment_details){
                            foreach($payment_details as $key => $value){
                                $fund_out_table = $db->escape($value['fund_out_table']);
                                $fund_out_id = $value['fund_out_id'];

                                $update_tx_history = array(
                                    "status" => 'failed',
                                    "updated_at" => date("Y-m-d H:i:s")
                                );
        
                                $db->where('id', $fund_out_id);
                                $updated = $db->update($fund_out_table, $update_tx_history);
                           
                            }
                        }
                       
                    }
                }

                // $service_charge_tx_hash = $transaction_hash;
                $updateData = array(
                    'status' => $crypto_data['status'],
                    // 'tx_hash' => $transaction_hash,
                    // 'service_charge_tx_hash' => $service_charge_tx_hash ?: '',
                    'transaction_details' => json_encode($transaction_details),
                    'updated_at' => date("Y-m-d H:i:s")
                );

                if($receiver_address_arr){
                    $db->where('recipient_address', $receiver_address_arr, 'NOT IN');
                }
                $db->where("bc_reference_id", $bc_reference_id);
                $updated = $db->update('xun_crypto_fund_out_details', $updateData);
                
                // if($total_service_charge_amount > 0 && isset($service_charge_tx_hash)){
                //     $xunWallet = new XunWallet($db);
            
                //     $transactionObj = new stdClass();
                //     $transactionObj->status = "wallet_success";
                //     $transactionObj->transactionHash = $service_charge_tx_hash;
                //     $transactionObj->transactionToken = "";
                //     $transactionObj->senderAddress = $business_external_address;
                //     $transactionObj->recipientAddress = $service_charge_address;
                //     $transactionObj->userID = $business_id;
                //     $transactionObj->senderUserID = $business_id;
                //     $transactionObj->recipientUserID = "trading_fee";
                //     $transactionObj->walletType = $service_charge_wallet_type;
                //     $transactionObj->amount = $total_service_charge_amount;
                //     $transactionObj->addressType = "service_charge";
                //     $transactionObj->transactionType = "send";
                //     $transactionObj->escrow = 0;
                //     $transactionObj->referenceID = '';
                //     $transactionObj->escrowContractAddress = '';
                //     $transactionObj->createdAt = $date;
                //     $transactionObj->updatedAt = $date;
                //     $transactionObj->expiresAt = '';
    
                //     $sc_transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);
                //     $service_charge_user_id = $business_id;
                    
                //     $new_params = array(
                //         "user_id" => $service_charge_user_id,
                //         "wallet_transaction_id" => $sc_transaction_id,
                //         "transaction_hash" => $service_charge_tx_hash,
                //         "amount" => $total_service_charge_amount,
                //         "wallet_type" => $service_charge_wallet_type,
                //         "service_charge_type" => 'bc_external_transfer',
                //         "transaction_type" => 'send',
                //         "ori_tx_wallet_type" => $wallet_type,
                //         "ori_tx_amount" => $amount
                //     );
                    
                //     $xunServiceCharge->insert_service_charge($new_params);

                //     $update_fund_out_data = array(
                //         "service_charge_wallet_tx_id" => $sc_transaction_id 
                //     );
                //     $db->where("id", $row_id, "IN");
                //     $db->update('xun_crypto_fund_out_details', $update_fund_out_data);
                // }
                unset($crypto_data['transactionToken']);
                unset($crypto_data['serviceChargeAddress']);
                unset($crypto_data['companyPoolAddress']);
                $crypto_data['referenceID'] = $reference_id;
                return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "data" => $crypto_data);
            }else{
                $updateData = array(
                    'status' => 'failed',
                    'remark' => $crypto_result['message'],
                    'updated_at' => date("Y-m-d H:i:s")
                );
                $db->where("id", $row_ids, "IN");
                $db->update('xun_crypto_fund_out_details', $updateData);

                $update_fund_out = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $db->where('id', $payment_details_ids, 'IN');
                $updated = $db->update('xun_payment_details', $update_fund_out);

                $db->where('id', $payment_details_ids, 'IN');
                $payment_details = $db->get('xun_payment_details', null, 'fund_out_table, fund_out_id');
                
                if($payment_details){
                    foreach($payment_details as $key => $value){
                        $fund_out_table = $value['fund_out_table'];
                        $fund_out_id = $value['fund_out_id'];
                        
                        $updateData = array(
                            'status' => 'failed',
                            'updated_at' => date("Y-m-d H:i:s")
                        );
                        $db->where("id", $fund_out_id);
                        $db->update($fund_out_table, $updateData);
                        
                    }
                }
               
                // $crypto_data = $crypto_result["data"];
                $crypto_data['referenceID'] = $reference_id;
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_result['message'], "data" => $crypto_data);
            }
        }

        function story_pg_callback_handler($params, $crypto_history_id){
            global $xunStory;
            $db = $this->db;

            $address = trim($params["address"]);
            $status = trim($params["status"]);
            $credit_details  = $params["creditDetails"];
            $sender  = $params["sender"];
            $recipient = $params["recipient"];

            $status = strtolower($status);

            if ($status == "pending" || $status == "processing"){
                return;
            }

            if (!$address){
                return;
            }

            $db->where("b.crypto_address", $address);
            $db->join("xun_crypto_address b", "b.id=a.address_id");
            $crypto_address_data = $db->getOne("xun_story_payment_gateway a", "a.story_id, a.wallet_type");

            if(!$crypto_address_data){
                return;
            }

            $story_id = $crypto_address_data["story_id"];
            if(!$story_id){
                return;
            }

            $wallet_type = $crypto_address_data["wallet_type"];

            $amount_details = $credit_details["amountDetails"];
            $amount = $amount_details["amount"];
            $amount_rate = $amount_details["rate"];

            $amount_decimal = $this->get_decimal_amount($wallet_type, $amount, $amount_rate);

            $sender_internal_address = $sender["internal"];
            $sender_external_address = $sender["external"];

            if($sender_internal_address){
                $sender_user = $this->get_xun_user_by_crypto_address($sender_internal_address);
                $xun_user = $sender_user["code"] == 1 ? $sender_user["xun_user"] : null;
            }

            // print_r($sender_user);
            $transaction_params = array(
                "address" => $address,
                "story_id" => $story_id,
                "crypto_history_id" => $crypto_history_id,
                "status" => $status,
                "wallet_type" => $wallet_type,
                "amount" => $amount_decimal,
                "sender" => $sender,
                "recipient" => $recipient,
            );

            $xunStory->update_donation_callback_from_pg($transaction_params, $xun_user);
        }

        function merchant_pg_from_bc_callback_handler($params, $crypto_history_id) {

            global $xunStory, $xunCurrency, $log, $setting, $xunSms, $xunEmail, $config, $xunPayment,$xunSwapcoins;
            $db = $this->db;
            $post = $this->post;
            $general = $this->general;

            $xun_business_service = new XunBusinessService($db);
            $prepaidWalletServerURL =  $config["giftCodeUrl"];

            $consolidate_wallet_address = $setting->systemSetting['requestFundConsolidateWalletAddress'];
            $address = trim($params["address"]);
            $status = trim($params["status"]);
            $credit_details  = $params["creditDetails"];
            $sender  = $params["sender"];
            $recipient = $params["recipient"];
            $crypto_wallet_type = strtolower(trim($params["type"]));
            $receivedTxID = $params['receivedTxID'];
            $date = date("Y-m-d H:i:s");
            $exchange_rate = $params['txDetails']['output'][0]['destination']['exchangeRate'];
            $wallet_type = strtolower(trim($params['type']));

            $status = strtolower($status);

            $sender_internal_address = $sender['internal'] ? $sender['internal'] : '';
            $sender_external_address = $sender['external'] ? $sender['external'] : '';
            $recipient_internal_address = $recipient['internal'] ? $recipient['internal'] : '';
            $recipient_external_address = $recipient['external'] ? $recipient['external'] : '';

            $sender_address = is_array($sender) ? (!empty($sender["internal"]) ? $sender["internal"] : $sender["external"]) : trim($sender);
            $recipient_address = is_array($recipient) ? (!empty($recipient["internal"]) ? $recipient["internal"] : $recipient["external"]) : trim($recipient);

            $amount_received_details = $credit_details["amountReceiveDetails"];
            $amount_received = $amount_received_details["amount"];
            $amount_received_rate = $amount_received_details["rate"];

            $amount_received_decimal = $this->get_decimal_amount($wallet_type, $amount_received, $amount_received_rate);

            $amount_details = $credit_details["amountDetails"];
            $amount = $amount_details["amount"];
            $amount_rate = $amount_details["rate"];

            $amount_decimal = $this->get_decimal_amount($wallet_type, $amount, $amount_rate);

            $miner_amount_details = $credit_details["minerAmountDetails"];
            $miner_amount = $miner_amount_details["amount"];
            $miner_amount_rate = $miner_amount_details["rate"];

            $miner_amount_decimal = $this->get_decimal_amount($wallet_type, $miner_amount, $miner_amount_rate);

            $status = strtolower($status);
            if ($status == "pending" || $status == "processing"){
                return;
            }

            if (!$address){
                return;
            }

            $db->where("status", "pending");
            $db->where("address", $address);
            $db->orderBy("id", "DESC");
            $pg_transaction_arr = $db->get("xun_payment_gateway_payment_transaction");

            // if(count($pg_transaction_arr) < 1){
            //     return;
            // }

            if($status == 'success'){

                $db->where('transaction_type', 'fund_in');
                $db->where("transaction_hash", $receivedTxID);
                // $db->where("gw_type", "BC");
                $invoiceTransactionDetail = $db->getOne("xun_payment_gateway_invoice_transaction");

                if(!$invoiceTransactionDetail) {

                    $decimal_place_setting = $xunCurrency->get_currency_decimal_places($crypto_wallet_type, true);
                    $decimal_places = $decimal_place_setting["decimal_places"];
                    
                    $business_id = $pg_transaction_arr[0]['business_id'];
                    if(!$business_id){
                        $db->where('address', $recipient['internal']);

                        $db->where('active', 1);
                        $db->where('address_type', 'nuxpay_wallet');
                        $crypto_user_address = $db->getOne('xun_crypto_user_address');
                        $business_id = $crypto_user_address['user_id'];
                    }

                    $db->where('transaction_type', array('withhold', 'release_withhold' ,'fund_in_to_destination'), 'NOT IN');
                    $db->where('wallet_type', $crypto_wallet_type);
                    $db->where('business_id', $business_id);
                    $total_tx_amount = $db->getOne('xun_payment_gateway_invoice_transaction', 'SUM(debit) as sumDebit , SUM(credit) as sumCredit');

                    $fund_balance = bcsub($total_tx_amount['sumCredit'], $total_tx_amount['sumDebit'], $decimal_places);

                    $new_balance = bcadd($fund_balance, $amount_decimal, $decimal_places);

                    $insertData = array(
                        "business_id" => $business_id,
                        "sender_address" => $sender['external'] ? $sender['external']: $sender['internal'],
                        "recipient_address" => $recipient['external'] ? $recipient['external'] : $recipient['internal'],
                        "amount" => $amount_received_decimal,
                        "amount_satoshi" => $amount_received,
                        "wallet_type" => $crypto_wallet_type,
                        "credit" => $amount_decimal,
                        "debit" => '0',
                        "balance" => $new_balance,
                        "miner_fee_amount" => $miner_amount_decimal,
                        "miner_fee_satoshi" => $miner_amount,
                        "miner_fee_wallet_type" => $crypto_wallet_type,
                        "reference_id" => $crypto_history_id ? $crypto_history_id : '',
                        "transaction_type" => "fund_in",
                        "created_at" => date("Y-m-d H:i:s"),
                        "gw_type" => "BC",
                        "transaction_hash" => $receivedTxID
                    );

                    $request_fund_tx_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertData);

                    $xunWallet = new XunWallet($db);
                    
                    // check if it's pg address
                    // $db->where("crypto_address", $address);
                    // $xun_crypto_address = $db->getOne("xun_crypto_address", "id, crypto_address");

                    // if($xun_crypto_address){
                    //     $address_type = "payment_gateway";
                    // }else{
                        $address_type = "external_transfer";
                    // }
                    
                    $db->where('address_type', $address_type);
                    $db->where('transaction_hash', $receivedTxID);
                    $wallet_transaction_result = $db->getOne('xun_wallet_transaction', 'id, transaction_history_table, transaction_history_id');

                    if($wallet_transaction_result){
                        $transaction_id = $wallet_transaction_result['id'];
                        $transaction_history_table = $wallet_transaction_result['transaction_history_table'];
                        $transaction_history_id = $wallet_transaction_result['transaction_history_id'];

                        $db->where('id', $transaction_history_id);
                        $transaction_history_result = $db->getOne($transaction_history_table);

                        if($transaction_history_result){
                            $updateData = array(
                                "status" => $status
                            );
                            $db->where('id', $transaction_history_id);
                            $db->update($transaction_history_table, $updateData);
                        }
                        else{
                            $transactionType = $address_type;

                            $txHistoryObj->paymentDetailsID = '';
                            $txHistoryObj->status = 'success';
                            $txHistoryObj->transactionID = $receivedTxID;
                            $txHistoryObj->transactionToken = '';
                            $txHistoryObj->senderAddress = $sender['external'] ? $sender['external']: $sender['internal'];
                            $txHistoryObj->recipientAddress = $recipient['external'] ? $recipient['external'] : $recipient['internal'];
                            $txHistoryObj->senderUserID = '';
                            $txHistoryObj->recipientUserID = $business_id;
                            $txHistoryObj->walletType = $crypto_wallet_type;
                            $txHistoryObj->amount = $amount_decimal;
                            $txHistoryObj->transactionType = $transactionType;
                            $txHistoryObj->referenceID = '';
                            $txHistoryObj->createdAt = $date;
                            $txHistoryObj->updatedAt = $date;
                            $txHistoryObj->exchangeRate = $exchange_rate;
                            $txHistoryObj->minerFeeExchangeRate = $exchange_rate;
                            $txHistoryObj->type = 'in';
                            $txHistoryObj->gatewayType = "BC";
        
                            $fund_in_transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
        
                            $transaction_history_id = $fund_in_transaction_history_result['transaction_history_id'];
                            $transaction_history_table = $fund_in_transaction_history_result['table_name'];
        
                            $updateWalletTx = array(
                                "transaction_history_id" => $transaction_history_id,
                                "transaction_history_table" => $transaction_history_table
                            );
                            $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);
                        }

                        $updateWalletTxStatus = array(
                            "status" => $status == "success" ? "completed" : $status
                        );

                        $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTxStatus);       
                    }  
                    else{
                        $transactionObj->status = $status == 'success' || $status == "received" ? 'completed' : $status;
                        $transactionObj->transactionHash = $receivedTxID;
                        $transactionObj->transactionToken = "";
                        $transactionObj->senderAddress = $sender['external'] ? $sender['external']: $sender['internal'];
                        $transactionObj->recipientAddress = $recipient['external'] ? $recipient['external'] : $recipient['internal'];
                        $transactionObj->userID = $business_id ? $business_id : '';
                        $transactionObj->senderUserID = '';
                        $transactionObj->recipientUserID = $business_id;
                        $transactionObj->walletType = $crypto_wallet_type;
                        $transactionObj->amount = $amount_decimal;
                        $transactionObj->addressType = $address_type;
                        $transactionObj->transactionType = "receive";
                        $transactionObj->escrow = 0;
                        $transactionObj->referenceID = '';
                        $transactionObj->escrowContractAddress = '';
                        $transactionObj->createdAt = $date;
                        $transactionObj->updatedAt = $date;
                        $transactionObj->expiresAt = '';
                        $transactionObj->fee = "";
                        $transactionObj->feeUnit = "";
                        $transactionObj->bcReferenceID = "";
    
                        $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);  
    
                        $transactionType = $address_type;
    
                        $txHistoryObj->paymentDetailsID = '';
                        $txHistoryObj->status = $status;
                        $txHistoryObj->transactionID = $receivedTxID;
                        $txHistoryObj->transactionToken = '';
                        $txHistoryObj->senderAddress = $sender['external'] ? $sender['external']: $sender['internal'];
                        $txHistoryObj->recipientAddress = $recipient['external'] ? $recipient['external'] : $recipient['internal'];
                        $txHistoryObj->senderUserID = '';
                        $txHistoryObj->recipientUserID = $business_id;
                        $txHistoryObj->walletType = $crypto_wallet_type;
                        $txHistoryObj->amount = $amount_decimal;
                        $txHistoryObj->transactionType = $transactionType;
                        $txHistoryObj->referenceID = '';
                        $txHistoryObj->createdAt = $date;
                        $txHistoryObj->updatedAt = $date;
                        $txHistoryObj->exchangeRate = $exchange_rate;
                        $txHistoryObj->minerFeeExchangeRate = $exchange_rate;
                        $txHistoryObj->type = 'in';
                        $txHistoryObj->gatewayType = "BC";
    
                        $fund_in_transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
    
                        $transaction_history_id = $fund_in_transaction_history_result['transaction_history_id'];
                        $transaction_history_table = $fund_in_transaction_history_result['table_name'];
    
                        $updateWalletTx = array(
                            "transaction_history_id" => $transaction_history_id,
                            "transaction_history_table" => $transaction_history_table
                        );
                        $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);
                    }

                    //Retrigger Send Fund for insufficient balance
                    $db->where('business_id', $business_id);
                    $db->where('status', 'low_balance');
                    $db->where('wallet_type', $crypto_wallet_type);
                    $db->orderBy('created_at', 'ASC');
                    $pending_send_fund_list = $db->get('xun_payment_gateway_send_fund');

                    $db->where('user_id', $business_id);
                    $db->where('address_type', 'nuxpay_wallet');
                    $db->where('active', 1);
                    $user_internal_address = $db->getOne('xun_crypto_user_address');

                    $db->where('id', $business_id);
                    $user_data = $db->getOne('xun_user', null, 'id, nickname, register_site');

                    $source = $user_data['register_site'];
                    $internal_address = $user_internal_address['address'];
                    
                    $db->where('currency_id', $crypto_wallet_type);
                    $marketplace_currencies = $db->getOne('xun_marketplace_currencies', 'id, currency_id, symbol');

                    $symbol = $marketplace_currencies['symbol'];
                    if($pending_send_fund_list){
                        foreach($pending_send_fund_list as $key => $value){
                            $send_fund_id = $value['id'];
                            $amount = $value['amount'];
                            $redeem_code = $value['redeem_code'];
                            $receiver_mobile_number = $value['recipient_mobile_number'];
                            $receiver_email_address = $value['recipient_email_address'];
                            $wallet_type = $value['wallet_type'];
                            $escrow = $value['escrow'];
                            $receiver_name = $value['recipient_name'];
                            $sender_name = $value['sender_name'];
                            $payment_description = $value['description'];
                            $pg_transaction_token = $value['pg_transaction_token'];
    
                            if($new_balance >= $amount){

                                if($receiver_mobile_number){
                                    $db->where('username', $receiver_mobile_number);
                                }
                        
                                if($receiver_email_address){
                                    $db->where('email', $receiver_email_address);
                                }
                                $db->where('register_site', $source);
                                $db->where('disabled', 0);
                                $db->where('type', 'business');
                                $recipient_user_data = $db->getOne('xun_user');
                                
                                if($recipient_user_data){    
                                    $recipient_business_id = $recipient_user_data['id'];
                                    $db->where('user_id', $recipient_business_id);
                                    $db->where('address_type', 'nuxpay_wallet');
                                    $db->where('active', 1);
                                    $crypto_user_address = $db->getOne('xun_crypto_user_address', 'id, user_id, address');
                                    
                                    if(!$crypto_user_address){
                                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00144') /*Address Not Found.*/);
                                    }
    
    
                                    if($escrow) {
                                        $destination_address = $setting->systemSetting['escrowInternalAddress'];
                                    } else {
                                        $destination_address = $crypto_user_address['address'];    
                                    }            
                                    
                                    $fund_type = 'fund_in';
                                    
                                }
                                else{
                                    $destination_address = $setting->systemSetting['redeemCodeAgentAddress'];
    
                                    $fund_type = 'redeem_code';
                                }

                                // Validate internal address
                                $ret_val1 = $this->crypto_validate_address($destination_address, $wallet_type, 'internal');        
                                if($ret_val1['code'] ==1){
                                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $ret_val1['statusMsg']);        
                                }
                                else{
                                    $destination_address = $ret_val1['data']['address'];
                                    $transaction_type = $ret_val1['data']['addressType'];
                                }
                            
                                $wallet_info = $this->get_wallet_info($internal_address, $wallet_type);
                                    
                                $lc_wallet_type = strtolower($wallet_type);
                                $walletBalance = $wallet_info[$lc_wallet_type]['balance'];
                                $unitConversion = $wallet_info[$lc_wallet_type]['unitConversion'];
                                $minerFeeWalletType = strtolower($wallet_info[$lc_wallet_type]['feeType']);
                                $symbol = strtolower($wallet_info[$lc_wallet_type]['unit']);

                                if($fund_type == 'fund_in' && $transaction_type == 'external'){
                                    $address_type = 'external_transfer';
                                }
                                if($fund_type == 'fund_in' && $transaction_type == 'internal'){
                                    $address_type = 'nuxpay_wallet';
                        
                                }
                                if($fund_type == 'redeem_code'){
                                    $address_type = 'redeem_code';
                        
                                }
    
                                $new_balance = bcsub($new_balance, $amount, 8);
                                $satoshi_amount = $this->get_satoshi_amount($wallet_type, $amount);
    
                                $insertTx = array(
                                    "business_id" => $business_id,
                                    "sender_address" => $internal_address,
                                    "recipient_address" => $destination_address,
                                    "amount"=> $amount,
                                    "amount_satoshi" => $satoshi_amount,
                                    "wallet_type" => $wallet_type,
                                    "credit" => 0,
                                    "debit" => $amount,
                                    "balance"=> $new_balance,
                                    "reference_id" => $send_fund_id,
                                    "transaction_type" => ($escrow &&  $fund_type =='fund_in') ? "send_escrow" :"send_fund",
                                    "gw_type" => "BC",
                                    "created_at" => date("Y-m-d H:i:s"),
                                );
                        
                                $invoice_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertTx);
                                
                                if(!$invoice_id){
                                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
                                }
                        
                                $tx_obj = new stdClass();
                                $tx_obj->userID = $business_id;
                                $tx_obj->address = $internal_address;
                        
                                $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);       
                                
                                $update_crypto_transaction_token = array(
                                    "crypto_transaction_token" => $transaction_token,
                                );
                                $db->where('transaction_token', $pg_transaction_token);
                                $db->update('xun_payment_gateway_payment_transaction', $update_crypto_transaction_token);
                                $xunWallet = new XunWallet($db);
                                $transactionObj->status = 'pending';
                                $transactionObj->transactionHash = '';
                                $transactionObj->transactionToken = $transaction_token;
                                $transactionObj->senderAddress = $internal_address;
                                $transactionObj->recipientAddress = $destination_address;
                                $transactionObj->userID = $business_id;
                                $transactionObj->senderUserID = $business_id;
                                if ($fund_type =='fund_in') {
                                    if ($escrow) {
                                        $transactionObj->recipientUserID = "escrow_wallet";
                                    } else {
                                        $transactionObj->recipientUserID = $recipient_business_id;
                                    }
                                } else {
                                    $transactionObj->recipientUserID = 'redeem_code';
                                }
                                $transactionObj->walletType = $wallet_type;
                                $transactionObj->amount = $amount;
                                $transactionObj->addressType = $address_type;
                                $transactionObj->transactionType = 'send';
                                $transactionObj->escrow = 0;
                                $transactionObj->referenceID = $send_fund_id;
                                $transactionObj->escrowContractAddress = '';
                                $transactionObj->createdAt = $date;
                                $transactionObj->updatedAt = $date;
                                if ($fund_type =='fund_in') {
                                    if ($escrow) {
                                        $transactionObj->message = "send_escrow";
                                    } else {
                                        $transactionObj->message = "send_fund";
                                    }
                                } else {
                                    $transactionObj->message = "send_fund";
                                }        
                                $transactionObj->expiresAt = '';
                                $transactionObj->fee = '';
                                $transactionObj->feeUnit = '';

                                $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);  

                                //NEW ACCOUNTING
                                while(true){
                                    $payment_id = "P".time();
                                    $db->where('payment_id', $payment_id);
                                    $check_payment_details = $db->getOne('xun_payment_details');

                                    if(!$check_payment_details){
                                        break;
                                    }

                                }


                                $db->where('transaction_token', $pg_transaction_token);
                                $payment_tx_data = $db->getOne('xun_payment_transaction');

                                if($payment_tx_data){
                                    $payment_tx_id = $payment_tx_data['id'];
                                    $fiat_currency_id = $payment_tx_data['fiat_currency_id'];

                                    $db->where('payment_tx_id', $paymeny_tx_id);
                                    $db->where('address', $destination_address);
                                    $payment_method_data = $db->getOne('xun_payment_method');

                                    if($payment_method_data){
                                        $payment_method_id = $payment_method_data['id'];
                                    }
                                    
                                }

                                
                                $paymentObj->paymentID = $payment_id;
                                $paymentObj->paymentTxID = $payment_tx_id;
                                $paymentObj->paymentMethodID = $payment_method_id;
                                $paymentObj->status = 'pending';
                                $paymentObj->senderInternalAddress = $internal_address;
                                $paymentObj->senderExternalAddress = '';
                                $paymentObj->recipientInternalAddress = $destination_address;
                                $paymentObj->recipientExternalAddress = '';
                                $paymentObj->pgAddress = '';
                                $paymentObj->senderUserID = $business_id;
                                $paymentObj->recipientUserID = $recipient_business_id ? $recipient_business_id : 'redeem_code';
                                $paymentObj->walletType = $wallet_type;
                                $paymentObj->amount = $amount;
                                $paymentObj->transactionToken = $transaction_token;
                                // $transactionObj->referenceID = $reference_id;
                                $paymentObj->createdAt = $date;
                                $paymentObj->updatedAt = $date;
                                // $transactionObj->fee = $padded_fee;
                                // $transactionObj->feeWalletType = $miner_fee_wallet_type;
                                // $transactionObj->exchangeRate = $exchangeRate;
                                // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;

                                if($fiat_currency_id){
                                    $currency_rate_data = $xunCurrency->get_currency_rate(array($fiat_currency_id));
                                    $fiat_currency_rate = $currency_rate_data[$fiat_currency_id];
   
                                    $txExchangeRate = bcmul($exchange_rate, $fiat_currency_rate, 8);

                                }
                                else{
                                    $txExchangeRate = $exchange_rate;
                                }

                                $paymentObj->txExchangeRate = $txExchangeRate;
                                $paymentObj->fiatCurrencyID = $fiat_currency_id ?  $fiat_currency_id : 'usd';

                                $payment_details_id = $xunPayment->insert_payment_details($paymentObj);

                                if(!$payment_details_id){
                                    $log->write("\n " . $date . " function:merchant_pg_from_bc_callback_handler - Insert Payment Details. Error:" . $db->getLastError());

                                }

                                $transactionType = "internal_transfer";

                                $txHistoryObj->paymentDetailsID = $payment_details_id;
                                $txHistoryObj->status = 'pending';
                                $txHistoryObj->transactionID = "";
                                $txHistoryObj->transactionToken = $transaction_token;
                                $txHistoryObj->senderAddress = $internal_address;
                                $txHistoryObj->recipientAddress = $destination_address;
                                $txHistoryObj->senderUserID = $business_id;
                                $txHistoryObj->recipientUserID = $recipient_business_id ? $recipient_business_id : 'redeem_code';
                                $txHistoryObj->walletType = $wallet_type;
                                $txHistoryObj->amount = $amount;
                                $txHistoryObj->transactionType = $transactionType;
                                $txHistoryObj->referenceID = '';
                                $txHistoryObj->createdAt = $date;
                                $txHistoryObj->updatedAt = $date;
                                $txHistoryObj->type = 'out';
                                $txHistoryObj->gatewayType = "BC";

                                $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);

                                if(!$transaction_history_result){
                                    $log->write("\n " . $date . " function:merchant_pg_from_bc_callback_handler - Insert Payment Transaction History. Error:" . $db->getLastError());
                                }

                                $transaction_history_id = $transaction_history_result['transaction_history_id'];
                                $transaction_history_table = $transaction_history_result['table_name'];

                                $update_payment_details_data = array(
                                    "fund_out_table" => $transaction_history_table,
                                    "fund_out_id" => $transaction_history_id
                                );
                                $db->where('id', $payment_details_id);
                                $db->update('xun_payment_details', $update_payment_details_data);

                                $updateWalletTx = array(
                                    "transaction_history_id" => $transaction_history_id,
                                    "transaction_history_table" => $transaction_history_table
                                );
                                $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);
                        
                                $curlParams = array(
                                    "command" => "fundOutCompanyWallet",
                                    "params" => array(
                                        "senderAddress" => $internal_address,
                                        "receiverAddress" => $destination_address,
                                        "amount" => $amount,
                                        "satoshiAmount" => $satoshi_amount,
                                        "walletType" => strtolower($wallet_type),
                                        "id" => $transaction_id,
                                        "transactionToken" => $transaction_token,
                                        "addressType" => "nuxpay_wallet",
                                        "transactionHistoryTable" => $transaction_history_table,
                                        "transactionHistoryID" => $transaction_history_id,
                                    ),
                                );
                            
                                $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0); 

                                if($curlResponse['code'] == 0){
                                    $update_status = array(
                                        "status" => 'failed',
                                        "updated_at" => date("Y-m-d H:i:s")
                                    );
                        
                                    $db->where('id', $send_fund_id);
                                    $db->update('xun_payment_gateway_send_fund', $update_status);
                        
                                    $db->where('id', $wallet_transaction_id);
                                    $db->update('xun_wallet_transaction', $update_status);
                                    
                                    $db->where('id', $transaction_history_id);
                                    $db->update($transaction_history_table, $update_status);
                        
                                    $update_balance  = array(
                                        "deleted" => 1,
                                    );
                        
                                    $db->where('id',$invoice_id);
                                    $db->update('xun_payment_gateway_invoice_transaction', $update_balance);
                        
                                    return array("code" => 0, "message" => "FAILED", "message_d" => $curlResponse['message_d'], "developer_msg" => $curlResponse);
                                }
                        
                                $data['send_fund_id'] = $send_fund_id;
                                if($redeem_code){
                                    $update_redeem_status = array(
                                        "status" => 'pending', //change redeem status from low_balance to pending
                                        "updated_at" => date("Y-m-d H:i:s")
                                    );

                                    $db->where('id', $send_fund_id);
                                    $update_send_fund = $db->update('xun_payment_gateway_send_fund', $update_redeem_status);

                                    if($receiver_mobile_number){
                        
                                        $db->where('source', $source);
                                        $site = $db->getOne('site');
                        
                                        $domain = $site['domain'];
                                        $return_message = $this->get_translation_message('B00366'); /*%%companyName%%: %%senderName%% sent funds to you via %%companyName%%. Your redemption pin is %%redeemCode%%. Redeem now from %%link%%.*/
                                        $return_message2 = str_replace("%%companyName%%", $source, $return_message);
                                        $return_message3 = str_replace("%%senderName%%", $sender_name, $return_message2);
                                        $return_message4 = str_replace("%%redeemCode%%", $redeem_code, $return_message3);
                                        $newParams["message"] = str_replace("%%link%%", $domain, $return_message4);
                                        $newParams["recipients"] = $receiver_mobile_number;
                                        $newParams["ip"] = $ip;
                                        $newParams["companyName"] = $source;
                                        //$xunSms->send_sms($newParams);
                                    }
                        
                                    if($receiver_email_address){
                                        $send_email_params = array(
                                            "sender_name" => $sender_name,
                                            "receiver_name" => $receiver_name,
                                            "amount" => $amount,
                                            "symbol" => $symbol,
                                            "description"=> $payment_description,
                                            "redeem_code" => $redeem_code,
                                        );
                        
                                        $emailDetail = $xunEmail->getSendFundEmail($source, $send_email_params);
                        
                                        $emailParams["subject"] = $emailDetail['emailSubject'];
                                        $emailParams["body"] = $emailDetail['html'];
                                        $emailParams["recipients"] = array($receiver_email_address);
                                        $emailParams["emailFromName"] = $emailDetail['emailFromName'];
                                        $emailParams["emailAddress"] = $emailDetail['emailAddress'];
                                        $emailParams["emailPassword"] = $emailDetail['emailPassword'];
                        
                                        $msg = $general->sendEmail($emailParams);
                                        
                                    }
                                    $data['redeem_code']= $redeem_code;
                                }
                        
                                $tag = "Send Fund";
                                $message = "Sender: ".$sender_name."\n";
                                $message .= "Receiver: ".$receiver_name."\n";
                                $message .= "Amount:" .$amount."\n";
                                $message .= "Wallet Type:".$wallet_type."\n";
                                $message .= "Time: ".date("Y-m-d H:i:s")."\n";
                        
                                $thenux_params["tag"]         = $tag;
                                $thenux_params["message"]     = $message;
                                $thenux_params["mobile_list"] = $xun_numbers;
                                $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                                
                            }
                            else{
                                break;
                            }
                        }
                    }
                }   
            }
        }

        function merchant_pg_callback_handler($params, $crypto_history_id){
            global $xunStory, $xunCurrency, $log, $setting;
            $db = $this->db;
            $consolidate_wallet_address = $setting->systemSetting['requestFundConsolidateWalletAddress'];
            $address = trim($params["address"]);
            $status = trim($params["status"]);
            $credit_details  = $params["creditDetails"];
            $sender  = $params["sender"];
            $recipient = $params["recipient"];
            $crypto_wallet_type = strtolower(trim($params["type"]));
            $receivedTxID = $params['receivedTxID'];
            $business_id = $params['business_id'];
            $destinationTxID = $params['txID'];
            $received_txs_arr  = $params['txDetails']['input'];
            $credit_arr  = $params['txDetails']['output'][0];

            $status = strtolower($status);

            $sender_address = is_array($sender) ? (!empty($sender["internal"]) ? $sender["internal"] : $sender["external"]) : trim($sender);
            $recipient_address = is_array($recipient) ? (!empty($recipient["internal"]) ? $recipient["internal"] : $recipient["external"]) : trim($recipient);

            $amount_received_details = $credit_details["amountReceiveDetails"];
            $amount_received = $amount_received_details["amount"];
            $amount_received_rate = $amount_received_details["rate"];

            $amount_received_decimal = $this->get_decimal_amount($wallet_type, $amount_received, $amount_received_rate);

            $amount_details = $credit_details["amountDetails"];
            $amount = $amount_details["amount"];
            $amount_rate = $amount_details["rate"];

            $amount_decimal = $this->get_decimal_amount($wallet_type, $amount, $amount_rate);

            $miner_amount_details = $credit_details["minerAmountDetails"];
            $miner_amount = $miner_amount_details["amount"];
            $miner_amount_rate = $miner_amount_details["rate"];

            $miner_amount_decimal = $this->get_decimal_amount($wallet_type, $miner_amount, $miner_amount_rate);

            $status = strtolower($status);
            $wallet_type        =   strtolower($params["type"]);

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
            $decimal_places = $decimal_place_setting["decimal_places"];

            if (!$address){
                return;
            }

            if($status == 'received')
            {
                $db->where('transaction_hash', $receivedTxID);
                $db->where('transaction_type', 'withhold');
                $withhold_data = $db->getOne('xun_payment_gateway_invoice_transaction', 'id');
                if(!$withhold_data){

                    $db->where('currency_id', $crypto_wallet_type);
                    $marketplace_currencies = $db->getOne('xun_marketplace_currencies', 'id,currency_id, unit_conversion');
                    foreach($received_txs_arr as $tx_key => $tx_value){
                        $unit_conversion = $marketplace_currencies['unit_conversion'];
                        $service_charge_details = $tx_value['charges'];
                        $miner_amount_details = $tx_value['minerFee'];
                        $eth_miner_amount_details = $tx_value['ethMinerFee'];
        
                        $wallet_type = strtolower($tx_value['type']);
                        $miner_fee_wallet_type = strtolower($miner_amount_details['type']);
                        $service_charge_wallet_type = strtolower($service_charge_details['type']);
                        $actual_miner_fee_wallet_type = $eth_miner_amount_details['type'] ? strtolower($eth_miner_amount_details['type']) : $miner_fee_wallet_type ;
                        
                        $final_amount_receive = $tx_value['amount'] ? $tx_value['amount'] : 0;
                        $final_service_charge = $service_charge_details['amount'] ? $service_charge_details['amount'] : 0;
                        $final_miner_fee = $miner_amount_details['amount'] ? $miner_amount_details['amount'] : 0 ;

                        $amount_receive_satoshi = bcmul($final_amount_receive, $unit_conversion, 0);
                        $miner_fee_satoshi = bcmul($final_miner_fee, $unit_conversion, 0);

        
                    }
                    $insertData = array(
                        "business_id" => $business_id,
                        "sender_address" => $sender['external'] ? $sender['external']: $sender['internal'],
                        "recipient_address" => $address,
                        "amount" => $final_amount_receive,
                        "amount_satoshi" => $amount_receive_satoshi,
                        "wallet_type" => $crypto_wallet_type,
                        "credit" => $final_amount_receive,
                        "debit" => '0',
                        "balance" => '0',
                        "miner_fee_amount" => $final_miner_fee,
                        "miner_fee_satoshi" => $miner_fee_satoshi,
                        "miner_fee_wallet_type" => $crypto_wallet_type,
                        "reference_id" => $crypto_history_id ? $crypto_history_id : 0,
                        "transaction_type" => "withhold",
                        "created_at" => date("Y-m-d H:i:s"),
                        "gw_type" => "PG",
                        "processed" => 0,
                        "transaction_hash" => $receivedTxID
                    );
                        
                    $invoice_tx_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertData);
                }
     
            }
            else if($status == 'success'){

                $destination_details = $credit_arr['destination'];
                $service_charge_details = $credit_arr['charges'];
                $miner_fee_details = $credit_arr['minerFee'];
                $eth_miner_fee_details = $credit_arr['ethMinerFee'];

                $total_amount = $destination_details['amount'] ? $destination_details['amount'] : 0;
                $total_service_charge = $service_charge_details['amount'] ? $service_charge_details['amount'] : 0;
                $total_miner_fee = $miner_fee_details['amount'] ? $miner_fee_details['amount'] : 0;

                $total_amount_receive = bcadd($total_amount, $total_service_charge, $decimal_places);
                $total_amount_receive = bcadd($total_amount_receive, $total_miner_fee, $decimal_places);
                
                $amount_receive_satoshi = $this->get_satoshi_amount($wallet_type, $total_amount_receive);
                $miner_fee_satoshi = $this->get_satoshi_amount($total_miner_fee, $miner_fee);


                $db->where('transaction_hash', $destinationTxID);
                $db->where('transaction_type', 'release_withhold');
                $release_withhold_data = $db->getOne('xun_payment_gateway_invoice_transaction', 'id');
                if(!$release_withhold_data)
                {
                    $insertData = array(
                        "business_id" => $business_id,
                        "sender_address" => $address,
                        "recipient_address" => $recipient['external'] ? $recipient['external'] : $recipient['internal'],
                        "amount" => $total_amount_receive,
                        "amount_satoshi" => $amount_receive_satoshi,
                        "wallet_type" => $crypto_wallet_type,
                        "credit" => '0',
                        "debit" => $total_amount_receive,
                        "balance" => '0',
                        "miner_fee_amount" => $total_miner_fee,
                        "miner_fee_satoshi" => $miner_fee_satoshi,
                        "miner_fee_wallet_type" => $crypto_wallet_type,
                        "reference_id" => $crypto_history_id ? $crypto_history_id : 0,
                        "transaction_type" => "release_withhold",
                        "created_at" => date("Y-m-d H:i:s"),
                        "gw_type" => "PG",
                        "processed" => "1",
                        "transaction_hash" => $destinationTxID
                    );
                    $invoice_tx_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertData);
                }
                

            } 
            elseif($status == 'pending'){
                $updateProcess = array(
                    "processed" => 1
                );

                $db->where('transaction_hash', $receivedTxID);
                $db->where('transaction_type', "withhold");
                $db->update('xun_payment_gateway_invoice_transaction', $updateProcess);
            }
                
            if ($status == "pending" || $status == "processing"){
                return;
            }

            // $db->where("address", $address);
            // $db->orderBy("id", "DESC");
            // $pg_transaction_arr = $db->get("xun_payment_gateway_payment_transaction");

            $db->where('a.address', $address);
            $db->where('a.type', 'payment_gateway');
           
            $db->join('xun_payment_transaction b', 'a.payment_tx_id = b.id' , 'LEFT');
            $db->join('xun_payment_gateway_payment_transaction c', 'c.transaction_token = b.transaction_token' , 'LEFT');
            $db->orderBy("c.id", "DESC");
            $pg_transaction_arr = $db->get('xun_payment_method a', null, 'b.transaction_token, c.*');
       
            if(count($pg_transaction_arr) < 1){
                return;
            }
            
            $db->where('payment_address', $address);
            $invoice_detail_arr = $db->getOne('xun_payment_gateway_invoice_detail', 'id, payment_amount, payment_currency, status');
       

            if($invoice_detail_arr){
                $payment_amount = $invoice_detail_arr['payment_amount'];
                
                $invoice_detail_id = $invoice_detail_arr['id'];
                $invoice_status = $invoice_detail_arr['status'];
                $db->where('address', $address);
                // $db->where('type', 'send');
                $db->where('status', 'success');
                $crypto_callback_result = $db->get('xun_crypto_history', null, 'wallet_type, amount_receive as amount');
                
                $total_paid_amount = '0.00000000';

                foreach($crypto_callback_result as $key => $value){
                    $wallet_type = strtolower($value['wallet_type']);
                     $decimalPlaceSetting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
                    
                    if($wallet_type == $invoice_detail_arr['payment_currency']){
                        $total_paid_amount = bcadd($total_paid_amount, $value['amount'], $decimalPlaceSetting['decimal_places']);
                    }
                    
                }

                $business_id = $pg_transaction_arr[0]['business_id'];
                if($invoice_status != 'success' && $status == 'success'){
                    $update_status_arr = array(
                        "status" => $payment_amount <= $total_paid_amount ? "success" : "short_paid",
                    );

                    $db->where('id', $invoice_detail_id);
                    $db->update('xun_payment_gateway_invoice_detail', $update_status_arr);

                    $decimal_place_setting = $xunCurrency->get_currency_decimal_places($crypto_wallet_type, true);
                    $decimal_places = $decimal_place_setting["decimal_places"];
                    
                    $db->where('transaction_type', 'fund_in_to_destination', '!=');
                    $db->where('wallet_type', $crypto_wallet_type);
                    $db->where('business_id', $business_id);
                    $total_tx_amount = $db->getOne('xun_payment_gateway_invoice_transaction', 'SUM(debit) as sumDebit , SUM(credit) as sumCredit');
                    $fund_balance = bcsub($total_tx_amount['sumCredit'], $total_tx_amount['sumDebit'], $decimal_places);

                    $new_balance = bcadd($fund_balance, $amount_decimal, $decimal_places);

                    $db->where('symbol', $feeUnit);
                    $db->where('type', 'cryptocurrency');
                    $marketplaceCurrencies = $db->getOne('xun_marketplace_currencies');

                    $db->where('b.business_id', $business_id);
                    $db->where('b.type', $crypto_wallet_type);
                    // $db->where('a.status', 1);
                    $db->where('a.destination_address', $recipient_address);
                    $db->join('xun_crypto_wallet b', 'a.wallet_id = b.id', 'LEFT');
                    $crypto_destination_address = $db->getOne('xun_crypto_destination_address a', 'a.id, a.destination_address');

                    $db->where('address_type', 'nuxpay_wallet');
                    $db->where('active', 1);
                    $db->where('user_id', $business_id);
                    $crypto_user_address = $db->getOne('xun_crypto_user_address', 'user_id, address');
                    $internal_address = $crypto_user_address['address'];

                    $db->where('transaction_hash', $destinationTxID);
                    $db->where('transaction_type', 'fund_in');
                    $fund_in_transaction_data = $db->get('xun_payment_gateway_invoice_transaction', null, 'id');

                    if(!$fund_in_transaction_data){
                        if($recipient_address == $internal_address){
                            $insertData = array(
                                "business_id" => $business_id,
                                "sender_address" => $sender['external'] ? $sender['external']: $sender['internal'],
                                "recipient_address" => $recipient['external'] ? $recipient['external'] : $recipient['internal'],
                                "amount" => $amount_received_decimal,
                                "amount_satoshi" => $amount_received,
                                "wallet_type" => $crypto_wallet_type,
                                "credit" => $amount_decimal,
                                "debit" => '0',
                                "balance" => $new_balance,
                                "miner_fee_amount" => $miner_amount_decimal,
                                "miner_fee_satoshi" => $miner_amount,
                                "miner_fee_wallet_type" => $crypto_wallet_type,
                                "reference_id" => $crypto_history_id,
                                "transaction_type" => "fund_in",
                                "created_at" => date("Y-m-d H:i:s"),
                                "gw_type" => "PG",
                                "transaction_hash" => $destinationTxID
                            );
                        }
                        else{
                            $insertData = array(
                                "business_id" => $business_id,
                                "sender_address" => $sender['external'] ? $sender['external']: $sender['internal'],
                                "recipient_address" => $recipient['external'] ? $recipient['external'] : $recipient['internal'],
                                "amount" => $amount_received_decimal,
                                "amount_satoshi" => $amount_received,
                                "wallet_type" => $crypto_wallet_type,
                                "credit" => $amount_decimal,
                                "debit" => '0',
                                "balance" => '0',
                                "miner_fee_amount" => $miner_amount_decimal,
                                "miner_fee_satoshi" => $miner_amount,
                                "miner_fee_wallet_type" => $crypto_wallet_type,
                                "reference_id" => $crypto_history_id,
                                "transaction_type" => "fund_in_to_destination",
                                "created_at" => date("Y-m-d H:i:s"),
                                "gw_type" => "PG",
                                "transaction_hash" => $destinationTxID
                            );
                        }
                      
                        $request_fund_tx_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertData);
                    }

                }
            }

            $has_pending = false;
            foreach($pg_transaction_arr as $data){
                if($data["status"] == "pending"){
                    $has_pending = true;
                    $pg_transaction = $data;
                    break;
                }
            }

            if($has_pending == false){
                $pg_transaction = $pg_transaction_arr[0];
            }

            $date = date("Y-m-d H:i:s");
            $wallet_type = $pg_transaction["wallet_type"];

            $amount_received_details = $credit_details["amountReceiveDetails"];
            $amount = $amount_received_details["amount"];
            $amount_rate = $amount_received_details["rate"];

            $amount_decimal = $this->get_decimal_amount($wallet_type, $amount, $amount_rate);

            if($wallet_type == $crypto_wallet_type){
                if(in_array($pg_transaction["status"], ["pending", "received"])){
                    $update_data = [];
                    $update_data["status"] = $status;
                    $update_data["amount"] = $amount_decimal;
                    $update_data["crypto_history_id"] = $crypto_history_id;
                    $update_data["updated_at"] = $date;
        
                    $db->where("id", $pg_transaction["id"]);
                    $db->update("xun_payment_gateway_payment_transaction", $update_data);
                }else{
                    // insert new record
                    $insert_tx_data = $pg_transaction;
                    unset($insert_tx_data["id"]);
                    $insert_tx_data["amount"] = $amount_decimal;
                    $insert_tx_data["status"] = $status;
                    $insert_tx_data["created_at"] = $date;
                    $insert_tx_data["updated_at"] = $date;
                    $insert_tx_data["crypto_history_id"] = $crypto_history_id;
                    
                    $row_id = $db->insert("xun_payment_gateway_payment_transaction", $insert_tx_data);

                    if(!$row_id){
                        $log->write("\n " . $date . " function:merchant_pg_callback_handler. Error:" . $db->getLastError());
                    }
                }
            }
        }

        public function validate_crypto_api_key($apikey, $business_id){

            $db = $this->db;

            $db->where("apikey", $apikey);
            $db->where("status", "1");
            $apikey_result = $db->getOne("xun_crypto_apikey");
            
            if(!$apikey_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00148') /*Invalid Apikey.*/);
            }
            if($business_id != $apikey_result["business_id"]){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00148') /*Invalid Apikey.*/); 
            }
            
            if(time() > strtotime($apikey_result["expired_at"])){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00149') /*Apikey has expired*/);
            }

            return true;
        }

        public function process_business_reward_redemption($wallet_transaction, $transaction_type = 'redemption'){
            global $xunReward;

            $wallet_transaction_id = $wallet_transaction["id"];
            $wallet_transaction_status = $wallet_transaction["status"];

            $new_params = [];
            $new_params["sender_user_id"] = $wallet_transaction["sender_user_id"];
            $new_params["receiver_user_id"] = $wallet_transaction["recipient_user_id"];
            $new_params["wallet_type"] = $wallet_transaction["wallet_type"];
            $new_params["amount"] = $wallet_transaction["amount"];
            $new_params["status"] = $wallet_transaction["status"];

            if($transaction_type == 'reward'){
                $xunReward->process_send_reward($new_params, $wallet_transaction_id);
            }elseif($transaction_type == 'redemption'){
                $xunReward->process_redemmption($new_params, $wallet_transaction_id);
            }
            
        }
        
        public function add_reward_token($params){
            /**
             * @param array $params
             *      name 
             *      symbol
             *      decimalPlaces
             *      totalSupply
             *      totalSupplyHolder
             *      exchangeRate: {usd: value, myr: ratio}
             *      referenceID
             */
            $post = $this->post;
            $command = "newTokenCreation";
            
            $crypto_result = $post->curl_crypto($command, $params, 2);
            return $crypto_result;
        }

        public function check_token_name_availability($params){
            $post = $this->post;

            $command = "checkTokenNameAvailability";
            /**
             * params: {
             *  name:
             *  symbol
             * }
             */
            
            $crypto_result = $post->curl_crypto($command, $params, 2);
            return $crypto_result;
        }

        public function new_token_creation_callback($params){
            global $config, $setting, $xun_numbers, $xunXmpp, $xunAdmin;
            $db = $this->db;
            $general = $this->general;

            $reference_id = trim($params["reference_id"]);
            $status = trim($params["status"]);
            $data = $params["data"];
            $date = date("Y-m-d H:i:s");

            if(!$reference_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Reference id is required.");
            }
            if(!$status){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "status is required.");
            }
            if(!$data){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "data is required.");
            }

            $db->where("id", $reference_id);
            $business_coin = $db->getOne("xun_business_coin");

            if(!$business_coin){
                return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid reference id.");
            }

            $business_id = $business_coin["business_id"];
            $coin_name = $business_coin["business_name"];
            $type_checking = $business_coin["type"];

            $status = strtolower($status);
            if($status == "success"){
                $name = trim($data["name"]);
                $wallet_type = trim($data["wallet_type"]);
                $unit_conversion = trim($data["unit_conversion"]);
                $unit = trim($data["unit"]);
                
                if($name == ''){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "name is required.");
                }
                if($wallet_type == ''){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "wallet_type is required.");
                }
                if($unit_conversion == ''){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "unit_conversion is required.");
                }
                if($unit == ''){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "unit is required.");
                }

                $wallet_type_lower = strtolower($wallet_type);
                $update_data = [];
                $update_data["wallet_type"] = $wallet_type_lower;
                $update_data["unit_conversion"] = $unit_conversion;
                $update_data["status"] = $status;
                $update_data["updated_at"] = $date;

                $db->where("id", $business_coin["id"]);
                $db->update("xun_business_coin", $update_data);

                $currency_type = "reward";
                $is_custom_coin = 1;
                $fiat_currency_id = $business_coin["fiat_currency_id"];
                $reference_price = $business_coin["reference_price"];
                $card_image_url = $business_coin["card_image_url"];
                $font_color = $business_coin["font_color"];
                $total_supply = $business_coin["total_supply"];
                // $coin_name = $business_coin["business_name"];

                $db->where("user_id", $business_id);
                $business = $db->getOne("xun_business", "id, user_id, name, profile_picture_url");
                $image_url = $business["profile_picture_url"];

                $businessDefaultImageUrl = $setting->systemSetting["businessDefaultImageUrl"];
                $image_url = $image_url ? $image_url : "";

                $image_md5 = $general->generateAlpaNumeric(16);

                // if ($type_checking == 'cash_token'){
                //     $currency_type = "cash_token";
                // }
                $currency_type = $type_checking;


                $this->handle_add_new_coin($coin_name, $wallet_type_lower, $unit, $unit_conversion, $currency_type, $is_custom_coin, $fiat_currency_id, $reference_price, $total_supply, $card_image_url, $image_url, $image_md5, $font_color);

                // $pricing_params["name"] = $coin_name;
                // $pricing_params["symbol"] = $unit;
                // $pricing_params["wallet_type"] = $wallet_type;
                // $pricing_params["fiat_currency_id"] = $fiat_currency_id;
                // $pricing_params["reference_price"] = $reference_price;
                // $pricing_params["unit_conversion"] = $unit_conversion;
                // $pricing_params["image_url"] = $image_url ? $image_md5 : $businessDefaultImageUrl;
                // $pricing_params["image_md5"] = $image_md5;
                // $this->add_new_coin_pricing_server($pricing_params);

                $tag = "NewTokenCreation";
                $content = "Name: " . $coin_name;
                $content .= "\n Business ID: " . $business_id;
                $content .= "\n Wallet Type: " . $wallet_type;
                $content .= "\n Unit conversion: " . $unit_conversion;
                $content .= "\n Status: " . $status;
                $content .= "\n Date: " . $date;
            }else{
                $update_data = [];
                $update_data["status"] = $status;
                $update_data["updated_at"] = $date;

                $db->where("id", $business_coin["id"]);
                $db->update("xun_business_coin", $update_data);

                //  send notification
                $error_message = $data["message"];

                $tag = "NewTokenCreation Failed";
                $content = "Name: " . $coin_name;
                $content .= "\n Business ID: " . $business_id;
                $content .= "\n Status: " . $status;
                $content .= "\n Message: " . $message;
                $content .= "\n Date: " . $date;
            }

            if ($currency_type == 'cash_token'){

                $db->where('business_id', $business_id);
                $db->where('status', 'success');
                $db->orderBy('created_at', 'ASC');
                $cashpool = $db->getOne('xun_cashpool_topup');
                $cashpool_topup_id = $cashpool['id'];
                $topup_amount = $cashpool['amount'];
                //Notification after Credit transfer
                $notification_params = array(
                    "business_id" => $business_id,
                    "business_name" => $coin_name,
                    "wallet_type" => $wallet_type,
                    "cashpool_topup_id" => $cashpool_topup_id,
                    "topup_amount" => $topup_amount,
                    "status" => $status,
                    "tag" => "Credit Transfer Result"
                );
                $xunAdmin->credit_transfer_send_notification($notification_params);
            }

            // $json_params = array(
            //     "business_id" => "1",
            //     "tag" => $tag,
            //     "message" => $content,
            //     "mobile_list" => $xun_numbers,
            // );

            // $insert_data = array(
            //     "data" => json_encode($json_params),
            //     "message_type" => "business",
            //     "created_at" => date("Y-m-d H:i:s"),
            //     "updated_at" => date("Y-m-d H:i:s"),
            // );

            // $ids = $db->insert('xun_business_sending_queue', $insert_data);

            if ($tag == "NewTokenCreation"){
                $tag = "New Token Creation";
            }else if ($tag == "NewTokenCreation Failed"){
                $tag = "Failed Token Creation";
            }
            $content .= "\n Currency Type: " . $currency_type;

            $thenux_params["tag"] = $tag;
            $thenux_params["message"] = $content;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
        }

        public function add_new_coin_pricing_server($params){
            global $config, $setting;
            $post = $this->post;

            $pricing_url = $config["pricingUrl"];
            $command = "addRewardToken";
            $pricingAccessToken = $setting->systemSetting["pricingAccessToken"];

            $name = $params["name"];
            $symbol = $params["symbol"];
            $wallet_type = $params["wallet_type"];
            $fiat_currency_id = $params["fiat_currency_id"];
            $reference_price = $params["reference_price"];
            $unit_conversion = $params["unit_conversion"];
            $image_url = $params["image_url"];
            $image_md5 = $params["image_md5"];

            $pricing_params = array(
                "partner_name" => "thenux",
                "access_token" => $pricingAccessToken,
                "name" => $name,
                "symbol" => $symbol,
                "wallet_type" => $wallet_type,
                "fiat_currency_id" => $fiat_currency_id,
                "reference_price" => $reference_price,
                "unit_conversion" => $unit_conversion,
                "image" => $image_url,
                "image_md5" => $image_md5
            );

            $post_params = array(
                "command" => $command,
                "params" => $pricing_params
            );

            $post_result = $post->curl_post($pricing_url, $post_params, 0);
        }

        public function request_credit_transfer_pool($params){
            $post = $this->post;
            $command = "mintTokens";
            
            $crypto_result = $post->curl_crypto($command, $params, 2);
            return $crypto_result;
        }
        
        public function custom_coin_verify_transaction_token ($params){
            $db = $this->db;

            $transaction_token = $params['transaction_token'];

            $db->where('transaction_token', $transaction_token);
            $verify_tx_token = $db->getOne('xun_custom_coin_supply_transaction');

            if(!$verify_tx_token){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00145') /*Invalid transaction token.*/);
            }
            
            if($verify_tx_token["is_verified"] === 1){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00146') /*Transaction token has been used.*/);
            }

            $update_is_verified = array(
                "is_verified" => 1,
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $db->where('transaction_token', $transaction_token);
            $updated = $db->update('xun_custom_coin_supply_transaction', $update_is_verified);

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00094') /*Transaction token is valid.*/);
            
        }

        public function process_marketer_commission($business_id, $service_charge_amount, $wallet_type, $service_charge_transaction_id, $company_pool_address){
            global $config, $setting, $xunCurrency, $xunServiceCharge, $xun_numbers, $xunMarketer, $xunMinerFee, $log, $xunPayment;
            $db = $this->db;
            $post = $this->post;
            $general = $this->general;

            $xun_business_service = new XunBusinessService($db);

            $externalTransferCompanyPoolURL = $config['externalTransferCompanyPoolURL'];
            $date = date("Y-m-d H:i:s");

            $business_marketer= $xunServiceCharge->getBusinessMarketerCommissionScheme($business_id, $wallet_type);

            $db->where("user_id", $business_id);
            $business_name = $db->getValue("xun_business", "name");

            //print_r($db);
            // $company_pool_address = $setting->systemSetting['marketplaceCompanyPoolWalletAddress'];
            $marketerMinThresholdWalletBalance = $setting->systemSetting['marketerMinThresholdWalletBalance'];
            $total_marketer_commission = '0';

            $wallet_info = $this->get_wallet_info($company_pool_address, $wallet_type);

            $lc_wallet_type = strtolower($wallet_type);
            $walletBalance = $wallet_info[$lc_wallet_type]['balance'];
            $unitConversion = $wallet_info[$lc_wallet_type]['unitConversion'];
            $minerFeeWalletType = strtolower($wallet_info[$lc_wallet_type]['feeType']);

            //Call get wallet info to get the miner fee balance if the miner fee is not charged in the same wallet type
            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($lc_wallet_type, true);
            $decimal_places = $decimal_place_setting["decimal_places"];

            $miner_decimal_place_setting = $xunCurrency->get_currency_decimal_places($minerFeeWalletType, true);
            $miner_fee_decimal_places = $miner_decimal_place_setting['decimal_places'];

            if($minerFeeWalletType != $wallet_type){
                $miner_fee_wallet_info = $this->get_wallet_info($company_pool_address, $minerFeeWalletType);
                // $minerFeeBalance = $miner_fee_wallet_info[$minerFeeWalletType]['balance'];
                $minerFeeUnitConversion = $miner_fee_wallet_info[$minerFeeWalletType]['unitConversion'];
                $minerFeeBalance = $xunMinerFee->getMinerFeeBalance($company_pool_address, $minerFeeWalletType);
                $converted_miner_fee_balance = $minerFeeBalance;

            }
            else{
                $minerFeeBalance = $walletBalance;
                $minerFeeUnitConversion = $unitConversion;
                $converted_miner_fee_balance = bcdiv($minerFeeBalance, $minerFeeUnitConversion, $miner_fee_decimal_places);
            }

            // if($minerFeeWalletType == 'ethereum'){
            //     $miner_fee_decimal_places = 18;
            // }

            $remaining_amount = $service_charge_amount;
            if($business_marketer){
                $marketer_id_arr = [];
                foreach($business_marketer as $key => $value)
                {  
                    $business_marketer_commission_id = $value['id'];
                    $destination_address = $value['destination_address'];
                    $transaction_type = $value['transaction_type'];
                    $marketer_id = $value['marketer_id'];
                    $commission_rate = $value['commission_rate'];

                    $db->where('wallet_type', $wallet_type);
                    $db->where('business_marketer_commission_id', $business_marketer_commission_id);
                    $db->orderBy('id', 'DESC');
                    $latest_commission_transaction = $db->getOne('xun_marketer_commission_transaction','SUM(credit) as sumCredit, SUM(debit) as sumDebit');
                    
                    $marketer_wallet_balance = '0.0000000';
                    if($latest_commission_transaction){
                        $sum_credit = $latest_commission_transaction['sumCredit'];
                        $sum_debit = $latest_commission_transaction['sumDebit'];

                        $marketer_wallet_balance = bcsub($sum_credit, $sum_debit, 8);

                        //$balance = $latest_commission_transaction['balance'];
                    }

                    $marketer_wallet_balance = $xunMarketer->get_marketer_commission_balance($business_marketer_commission_id, $wallet_type);

                    // if($destination_address == ''){
                    //     unset($marketer_destination_result[$key]);
                    //     break;
                    // }


                    
                    $ret_val= $this->crypto_validate_address($destination_address, $wallet_type, 'external');
                    ///need to add send message when the destination  address is not valid
                    if($ret_val['code'] == 1){
                        $ret_val1 = $this->crypto_validate_address($destination_address, $wallet_type, 'internal');
         
                        if($ret_val1['code'] ==1){
                            unset($marketer_destination_result[$key]);
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

               
                    //marketer commission percentage
                    $percentage = bcdiv((string) $commission_rate, 100, 8);

                    $marketer_commission_amount = bcmul($service_charge_amount, $percentage, $decimal_places);
                    $initial_commission_satoshi =  bcmul($marketer_commission_amount, $unitConversion, 0);
                    //$satoshi_amount = bcmul($marketer_commission_amount, $unitConversion, 0);
                    $newBalance = $marketer_commission_amount;
                    $satoshi_amount = bcmul($newBalance, $unitConversion, 0);



                    // $total_marketer_balance = bcadd($marketer_wallet_balance, $newBalance, $decimal_places);
                    

                    // $marketer_commission_transaction_id = $this->insertMarketerCommissionTransaction($business_marketer_commission_id, $marketer_commission_amount, $initial_commission_satoshi, $wallet_type, $marketer_commission_amount, 0, $total_marketer_balance);
                    
                    // //Service charge amount after subtracting the marketer commission amount
                    // $remaining_amount = bcsub($remaining_amount, $marketer_commission_amount, 8);


                    // if($destination_address=="") {
                    //     continue;
                    // }

                    // $ret_val= $this->crypto_validate_address($destination_address, $wallet_type, $transaction_type);
                    // ///need to add send message when the destination  address is not valid
                    // if($ret_val['code'] == 1){
                    //     unset($marketer_destination_result[$key]);
                    //     break;
                    // }

                    //calculate miner fee only
                    if($transaction_type == 'external'){
                        $return = $this->calculate_miner_fee($company_pool_address, $destination_address, $satoshi_amount, $wallet_type, 1);

                        if($return['code']== '1'){

                            $tag = "Marketer Get Miner Fee Error";
                            $message = "Business Name:".$business_name."\n\n";
                            $message .= "Original Amount:" .$marketer_commission_amount."\n";
                            $message .= "Miner Fee:".$converted_miner_fee."\n";
                            $message .= "Wallet Type:".$wallet_type."\n";
                            $message .= "Error Msg: ".$return['statusMsg']."\n";
                            $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                            //$this->insertMarketerCommissionTransaction($business_marketer_commission_id, $marketer_commission_amount, $satoshi_amount, $wallet_type, 0, $amountAfterMinerFee, $balance, "New Total Balance less than $marketerMinThresholdWalletBalance", 'failed');
                            $thenux_params["tag"]         = $tag;
                            $thenux_params["message"]     = $message;
                            $thenux_params["mobile_list"] = $xun_numbers;
                            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_issues");
                        }

                        $miner_fee = $return['data']['txFee'];
                    }
                    else if($transaction_type == 'internal'){
                        $miner_fee = '0';

                    }


                    $converted_miner_fee = bcdiv($miner_fee, $minerFeeUnitConversion, 18);

                    //if miner is not charge in the same wallet type as the transaction
                    if($wallet_type != $minerFeeWalletType){
                        $lowercase_miner_wallet_type = strtolower($minerFeeWalletType);
                        // //miner fee usd amount
                        // $miner_fee_usd_amount = $xunCurrency->calculate_cryptocurrency_rate_by_wallet_type($minerFeeWalletType, $converted_miner_fee);
                        // //get the original wallet type price rate
                        // $wallet_type_cryptocurrency_rate = $xunCurrency->get_cryptocurrency_rate(array($lc_wallet_type));
                        // $wallet_rate = $wallet_type_cryptocurrency_rate[$lc_wallet_type];
                     
                        // //Miner fee amount in its original wallet type
                        // $converted_miner_fee = bcdiv($miner_fee_usd_amount, $wallet_rate, $decimal_places);
                    
                        $original_miner_fee_amount = $converted_miner_fee;
                        $converted_miner_fee=  $xunCurrency->get_conversion_amount($lc_wallet_type, $lowercase_miner_wallet_type, $converted_miner_fee, true);
                        
                        $convertedSatoshiMinerFee = bcmul($converted_miner_fee, $unitConversion);
     

                    }else{
                        $convertedSatoshiMinerFee = $miner_fee;
                        $converted_miner_fee = $xunCurrency->round_miner_fee($minerFeeWalletType, $converted_miner_fee);
                    }

                    $amountAfterMinerFee = bcsub($newBalance, $converted_miner_fee, $decimal_places);
                    $satoshiAmountAfterMinerFee = $this->get_satoshi_amount($lc_wallet_type, $amountAfterMinerFee, $unitConversion);

                    $original_marketer_usd_rate = $xunCurrency->calculate_cryptocurrency_rate_by_wallet_type($wallet_type, $marketer_commission_amount);
                    $crypto_usd_rate = $xunCurrency->calculate_cryptocurrency_rate_by_wallet_type($wallet_type, $amountAfterMinerFee);
                    $total_marketer_balance = bcadd($marketer_wallet_balance, $newBalance, $decimal_places);
                    $marketer_commission_transaction_id = $this->insertMarketerCommissionTransaction($business_marketer_commission_id, $marketer_commission_amount, $initial_commission_satoshi, $wallet_type, $marketer_commission_amount, 0, $total_marketer_balance);
                    
                    //Service charge amount after subtracting the marketer commission amount
                    $remaining_amount = bcsub($remaining_amount, $marketer_commission_amount, 8);
                    
                    $db->where('marketer_id', $marketer_id);
                    $reseller_details = $db->getOne('reseller', 'id, user_id');

                    $reseller_user_id = $reseller_details['user_id'];
                    $db->where('address_type', 'nuxpay_wallet');
                    $db->where('active', 1);
                    $db->where('user_id', $reseller_user_id);
                    $crypto_user_address = $db->getOne('xun_crypto_user_address', 'user_id, address');
                    $internal_address = $crypto_user_address['address'];


                     //crypto usd rate is the balance + new commission amount and convert to usd
                    // if(($minerFeeWalletType != $wallet_type) && ($converted_miner_fee_balance < $original_miner_fee_amount)){
                    //     $tag = "Insufficient Miner Fee Balance";
                         
                    //     $message = "Business Name:".$business_name."\n";
                    //     $message .= "Miner Fee:".$original_miner_fee_amount."\n";
                    //     $message .= "Miner Fee Wallet Balance: ".$converted_miner_fee_balance."\n";
                    //     $message .= "Wallet Type:".$minerFeeWalletType."\n";
                    //     $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                    //     $thenux_params["tag"]         = $tag;
                    //     $thenux_params["message"]     = $message;
                    //     $thenux_params["mobile_list"] = $xun_numbers;
                    //     $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                    //     continue;
                    // }
                    if($internal_address != $destination_address){
                        if($crypto_usd_rate < $marketerMinThresholdWalletBalance){
                            $displayAmount = $amountAfterMinerFee > 0 ? $amountAfterMinerFee : 0;
                            $displayAmountUSD = $crypto_usd_rate > 0 ? $crypto_usd_rate : 0 ;
                            
                            $tag = "Below Marketer Threshold";
                            $message = "Business Name:".$business_name."\n\n";
                            $message .= "Original Amount:" .$marketer_commission_amount."\n";
                            $message .= "Miner Fee:".$converted_miner_fee."\n";
                            $message .= "Remaining Amount:". $displayAmount ."\n";
                            $message .= "Remaining Amount(USD):".$displayAmountUSD."\n\n";
                            $message .= "Wallet Type:".$wallet_type."\n";
                            $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                            //$this->insertMarketerCommissionTransaction($business_marketer_commission_id, $marketer_commission_amount, $satoshi_amount, $wallet_type, 0, $amountAfterMinerFee, $balance, "New Total Balance less than $marketerMinThresholdWalletBalance", 'failed');
                            $thenux_params["tag"]         = $tag;
                            $thenux_params["message"]     = $message;
                            $thenux_params["mobile_list"] = $xun_numbers;
                            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");


                            // if($transaction_type == 'external'){
                                $destination_address = $internal_address;
                                $transaction_type = 'internal';
                                $amountAfterMinerFee = $newBalance;
                                $satoshiAmountAfterMinerFee = $satoshi_amount;
                                $belowThreshold = 1;
                            // }
                            // continue;
                        }
                    }
                   
                    // if($satoshiAmountAfterMinerFee < 0){
                    //     $tag = "Insufficient Marketer Miner Fee";
                    //     $message = "Business Name:".$business_name."\n";
                    //     $message .= "Amount:" .$newBalance."\n";
                    //     $message .= "Miner Fee:".$converted_miner_fee."\n";
                    //     $message .= "Wallet Type:".$wallet_type."\n";
                    //     $message .= "Time: ".date("Y-m-d H:i:s")."\n";
                       
                    //     $thenux_params["tag"] = $tag;
                    //     $thenux_params["message"]     = $message;
                    //     $thenux_params["mobile_list"] = $xun_numbers;
                    //     $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                    //     continue;
                    // }
                    $miner_fee_balance_usd = $xunCurrency->calculate_cryptocurrency_rate_by_wallet_type($minerFeeWalletType, $converted_miner_fee_balance);

                    if($minerFeeWalletType != $wallet_type){
                        if($miner_fee_balance_usd <= 10){
                            $tag = "Low Miner Fee Balance";
                            $message = "Type: Company Pool Address\n";
                            $message .= "Address: ".$company_pool_address."\n";
                            // $message .= "Business Name:".$business_name."\n";
                            $message .= "Miner Fee:".$converted_miner_fee."\n";
                            $message .= "Miner Fee Wallet Balance: ".$converted_miner_fee_balance."\n";
                            $message .= "Wallet Type:".$minerFeeWalletType."\n";
                            $message .= "Time: ".date("Y-m-d H:i:s")."\n";
    
                            $thenux_params["tag"]         = $tag;
                            $thenux_params["message"]     = $message;
                            $thenux_params["mobile_list"] = $xun_numbers;
                            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_issues");
                        }
                    }

                    $crypto_arr = array("tetherusd",  "ethereum");
                    $priceArr = $xunCurrency->get_cryptocurrency_rate($crypto_arr);
                    //When fund out the current marketer commission only
                    
                    if($internal_address != $destination_address){
                        $fund_out_marketer_transaction_id = $this->insertMarketerCommissionTransaction($business_marketer_commission_id, $amountAfterMinerFee, $satoshiAmountAfterMinerFee, $wallet_type, 0, $amountAfterMinerFee, $marketer_wallet_balance, '', 'Fund Out');
                             
                        if($minerFeeWalletType != $wallet_type){

                            if($original_miner_fee_amount > 0){
                                $miner_fee_transaction_id = $this->insertMarketerCommissionTransaction($business_marketer_commission_id, $converted_miner_fee, $convertedSatoshiMinerFee, $wallet_type, 0, $converted_miner_fee, 0, "Original Miner Fee Amount: ".$original_miner_fee_amount, 'Miner Fee Fund Out', $fund_out_marketer_transaction_id);

                                $miner_fee_pool_address = $setting->systemSetting['minerFeePoolAddress'];

                                $tx_obj = new stdClass();
                                $tx_obj->userID = '0';
                                $tx_obj->address = $company_pool_address;
                    
                                $miner_fee_transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);
                                $xunWallet = new XunWallet($db);
                                $minerTransactionObj->status = 'pending';
                                $minerTransactionObj->transactionHash = '';
                                $minerTransactionObj->transactionToken = $miner_fee_transaction_token;
                                $minerTransactionObj->senderAddress = $company_pool_address;
                                $minerTransactionObj->recipientAddress = $miner_fee_pool_address;
                                $minerTransactionObj->userID = 0;
                                $minerTransactionObj->senderUserID = 'company_pool';
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

                                $miner_transaction_id = $xunWallet->insertUserWalletTransaction($minerTransactionObj);  

                                $txHistoryObj->paymentDetailsID = '';
                                $txHistoryObj->status = "pending";
                                $txHistoryObj->transactionID = "";
                                $txHistoryObj->transactionToken = $miner_fee_transaction_token;
                                $txHistoryObj->senderAddress = $company_pool_address;
                                $txHistoryObj->recipientAddress = $miner_fee_pool_address;
                                $txHistoryObj->senderUserID = 'company_pool';
                                $txHistoryObj->recipientUserID = 'miner_pool';
                                $txHistoryObj->walletType = $wallet_type;
                                $txHistoryObj->amount = $converted_miner_fee;
                                $txHistoryObj->transactionType = "miner_pool";
                                $txHistoryObj->referenceID = $miner_fee_transaction_id;
                                $txHistoryObj->createdAt = $date;
                                $txHistoryObj->updatedAt = $date;
                                // $transactionObj->fee = $final_miner_fee;
                                // $transactionObj->feeWalletType = $miner_fee_wallet_type;
                                $txHistoryObj->exchangeRate = $exchange_rate;
                                // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                                $txHistoryObj->type = 'in';
                                $txHistoryObj->gatewayType = "BC";
                                $txHistoryObj->isInternal = 1;
                    
                                $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                                $fund_out_id = $transaction_history_result['transaction_history_id'];
                                $fund_out_table = $transaction_history_result['table_name'];

                                $updateWalletTx = array(
                                    "transaction_history_id" => $fund_out_id,
                                    "transaction_history_table" => $fund_out_table
                                );
                                $xunWallet->updateWalletTransaction($miner_transaction_id, $updateWalletTx);

                                $company_pool_params = array(
                                    "receiverAddress" => $miner_fee_pool_address,
                                    "amount" => $converted_miner_fee,
                                    "walletType" => $wallet_type,
                                    "walletTransactionID" => $miner_transaction_id,
                                    "transactionToken" => $miner_fee_transaction_token,
                                    "senderAddress" => $company_pool_address,
                                    "transactionHistoryTable" => $fund_out_table,
                                    "transactionHistoryID" => $fund_out_id,

                                );

                                // $company_pool_result = $post->curl_post($internalTransferCompanyPoolURL, $company_pool_params, 0, 0, array(), 1, 1);
                                $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
                                $miner_company_pool_result = $xunCompanyWallet->fundOut('company_pool', $company_pool_params);

                                $message = "Miner Fund Out\n";
                                $message .= "Business Name:".$business_name."\n";
                                $message .= "Amount:" .$converted_miner_fee."\n";
                                $message .= "Wallet Type:".$wallet_type."\n";
            
                                
                                if ($miner_company_pool_result['code'] == 1) {
                                    $tag = "Marketer Miner Fund Out";   
            
                                } else {
     
                                    $update_wallet_transaction_arr = array(
                                        "status" => 'failed',
                                        "updated_at" => date("Y-m-d H:i:s"),
                                    );
                                    $db->where('id', $miner_transaction_id);
                                    $db->update('xun_wallet_transaction', $update_wallet_transaction_arr);
                                   
                                    $tag = "Failed Marketer Miner Fund Out";
                                    $additional_message = "Error Message: " . $miner_company_pool_result["message_d"] . "\n";
                                    $additional_message .= "Input: " . json_encode($company_pool_params) . "\n";
                                }

                                $thenux_params["tag"]         = $tag;
                                $thenux_params["message"]     = $message;
                                $thenux_params["mobile_list"] = $xun_numbers;
                                $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

                                //  insert to miner fee table
                                $miner_fee_tx_data = array(
                                    "address" => $company_pool_address,
                                    "reference_id" => $miner_fee_transaction_id,
                                    "reference_table" => "xun_marketer_commission_transaction",
                                    "type" => 'miner_fee_payment',
                                    "wallet_type" => $minerFeeWalletType,
                                    "debit" => $original_miner_fee_amount,
                                );
                                $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);

                                $miner_fee_tx_data = array(
                                    "address" => $internal_address,
                                    "reference_id" => $miner_fee_transaction_id,
                                    "reference_table" => "xun_marketer_commission_transaction",
                                    "type" => 'fund_in',
                                    "wallet_type" => $minerFeeWalletType,
                                    "credit" => $original_miner_fee_amount,
                                );
                                $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);
                            }
                        }
                        else{
                            $miner_fee_transaction_id = $this->insertMarketerCommissionTransaction($business_marketer_commission_id, $converted_miner_fee, $convertedSatoshiMinerFee, $wallet_type, 0, $converted_miner_fee, 0, '', 'Miner Fee Fund Out', $fund_out_marketer_transaction_id);
                        }
                    }



                    // Pass an the satoshi amount after minus miner fee
                    $marketer_fund_out_arr = array(
                        "destination_address" => $destination_address,
                        "satoshi_amount" => $satoshi_amount,
                        "satoshiAmountAfterMinerFee" => $satoshiAmountAfterMinerFee,
                        "marketer_commission_transaction_id" => $fund_out_marketer_transaction_id ? $fund_out_marketer_transaction_id : $marketer_commission_transaction_id,
                        "marketer_commission_amount" => $marketer_commission_amount,
                        "amountAfterMinerFee" => $amountAfterMinerFee,
                        "transaction_type" => $transaction_type,
                        "internal_address" => $internal_address,
                        "address_type" => $internal_address == $destination_address ? 'internal' : 'destination',
                        "below_threshold" => $belowThreshold
                    );
                    $marketer_fund_out_list[] =  $marketer_fund_out_arr;

                
                }
            }

            if($marketer_fund_out_list){
                foreach($marketer_fund_out_list as $fund_out_key => $fund_out_value){
                    $fund_out_destination_address = $fund_out_value['destination_address'];
                    $marketer_satoshi_amount = $fund_out_value['satoshi_amount'];
                    $marketer_commission_transaction_id = $fund_out_value['marketer_commission_transaction_id'];
                    $satoshiWithoutMinerFee = $fund_out_value['satoshiAmountAfterMinerFee'];
                    $amountAfterMinerFee = $fund_out_value['amountAfterMinerFee'];
                    $marketerTransactionType = $fund_out_value['transaction_type'];
                    $internal_address = $fund_out_value['internal_address'];
                    $below_threshold = $fund_out_value['below_threshold'];
                  
                    $new_marketer_commission_amount = bcdiv($satoshiWithoutMinerFee, $unitConversion, $decimal_places);
                    // $satoshiWithoutMinerFee = bcmul($satoshiWithoutMinerFee, $unitConversion);
                    $total_marketer_commission = bcadd($total_marketer_commission, $new_marketer_commission_amount, $decimal_places);

                    $tx_obj = new stdClass();
                    $tx_obj->userID = '0';
                    $tx_obj->address = $company_pool_address;
        
                   $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);
                   $xunWallet = new XunWallet($db);
                    $transactionObj->status = 'pending';
                    $transactionObj->transactionHash = '';
                    $transactionObj->transactionToken = $transaction_token;
                    $transactionObj->senderAddress = $company_pool_address;
                    $transactionObj->recipientAddress = $fund_out_destination_address;
                    $transactionObj->userID = $business_id;
                    $transactionObj->senderUserID = 'company_pool';
                    $transactionObj->recipientUserID = '';
                    $transactionObj->walletType = $wallet_type;
                    $transactionObj->amount = $new_marketer_commission_amount;
                    $transactionObj->addressType = 'marketer';
                    $transactionObj->transactionType = 'send';
                    $transactionObj->escrow = 0;
                    $transactionObj->referenceID = $service_charge_transaction_id;
                    $transactionObj->escrowContractAddress = '';
                    $transactionObj->createdAt = $date;
                    $transactionObj->updatedAt = $date;
                    $transactionObj->expiresAt = '';
                    $transactionObj->fee = '';
                    $transactionObj->feeUnit = '';


                    $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);  

                    $txHistoryObj->paymentDetailsID = '';
                    $txHistoryObj->status = "pending";
                    $txHistoryObj->transactionID = "";
                    $txHistoryObj->transactionToken = $transaction_token;
                    $txHistoryObj->senderAddress = $company_pool_address;
                    $txHistoryObj->recipientAddress = $fund_out_destination_address;
                    $txHistoryObj->senderUserID = 'company_pool';
                    $txHistoryObj->recipientUserID = $business_id;
                    $txHistoryObj->walletType = $wallet_type;
                    $txHistoryObj->amount = $new_marketer_commission_amount;
                    $txHistoryObj->transactionType = "marketer";
                    $txHistoryObj->referenceID = $service_charge_transaction_id;
                    $txHistoryObj->createdAt = $date;
                    $txHistoryObj->updatedAt = $date;
                    // $transactionObj->fee = $final_miner_fee;
                    // $transactionObj->feeWalletType = $miner_fee_wallet_type;
                    $txHistoryObj->exchangeRate = $exchange_rate;
                    // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                    $txHistoryObj->type = 'in';
                    $txHistoryObj->gatewayType = "BC";
        
                    $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                    $marketer_fund_out_id = $transaction_history_result['transaction_history_id'];
                    $marketer_fund_out_table = $transaction_history_result['table_name'];

                    $updateWalletTx = array(
                        "transaction_history_id" => $marketer_fund_out_id,
                        "transaction_history_table" => $marketer_fund_out_table
                    );
                    $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);

                    if($marketerTransactionType == 'external'){
                        $company_pool_params = array(
                            "receiverAddress" => $fund_out_destination_address,
                            "amount" => $satoshiWithoutMinerFee,
                            "walletType" => $wallet_type,
                            "walletTransactionID" => $transaction_id,
                            "transactionToken" => $transaction_token,
                            "senderAddress" => $company_pool_address,
                            "transactionHistoryTable" => $marketer_fund_out_table,
                            "transactionHistoryID" => $marketer_fund_out_id,

                        );
                        $company_pool_result = $post->curl_post($externalTransferCompanyPoolURL, $company_pool_params, 0, 1, array(), 1, 1);
                    }
                    else if($marketerTransactionType == 'internal'){
                        $company_pool_params = array(
                            "receiverAddress" => $fund_out_destination_address,
                            "amount" => $amountAfterMinerFee,
                            "walletType" => $wallet_type,
                            "walletTransactionID" => $transaction_id,
                            "transactionToken" => $transaction_token,
                            "senderAddress" => $company_pool_address,
                            "transactionHistoryTable" => $marketer_fund_out_table,
                            "transactionHistoryID" => $marketer_fund_out_id,
    
                        );
                        // $company_pool_result = $post->curl_post($internalTransferCompanyPoolURL, $company_pool_params, 0, 0, array(), 1, 1);
                        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
                        $company_pool_result = $xunCompanyWallet->fundOut('company_pool', $company_pool_params);
                    }

                    if ($company_pool_result['code'] == 1) {
                        $update_wallet_transaction_id = array(
                            "reference_id" => $transaction_id,
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('id', $marketer_commission_transaction_id);
                        $db->update('xun_marketer_commission_transaction', $update_wallet_transaction_id);
                        $tag = "Marketer Fund Out";

                    } else {
                        //  full marketer commission
                        $marketer_commission_amount = $fund_out_value["marketer_commission_amount"];

                        $new_marketer_wallet_balance = $xunMarketer->get_marketer_commission_balance($business_marketer_commission_id, $wallet_type);
                        $total_new_marketer_wallet_balance = bcadd($new_marketer_wallet_balance, $marketer_commission_amount, $decimal_places);
                        $fund_out_failed_id = $this->insertMarketerCommissionTransaction($business_marketer_commission_id, $marketer_commission_amount, $satoshi_amount, $wallet_type, $marketer_commission_amount, 0, $total_new_marketer_wallet_balance, '', 'Fund Out Failed');
                        
                        $update_wallet_transaction_arr = array(
                            "status" => 'failed',
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('id', $transaction_id);
                        $db->update('xun_wallet_transaction', $update_wallet_transaction_arr);
                       
                        $tag = "Failed Marketer Fund Out";
                        $additional_message = "Error Message: " . $company_pool_result["message_d"] . "\n";
                        $additional_message .= "Input: " . json_encode($company_pool_params) . "\n";
                    }
                    if($below_threshold != 1){
                        $message = "Instant Fund Out\n";
                        $message .= "Business Name:".$business_name."\n";
                        $message .= "Amount:" .$amountAfterMinerFee."\n";
                        $message .= "Wallet Type:".$wallet_type."\n";
    
                        if($additional_message){
                            $message .= $additional_message;
                        }
                        $message .= "Time: ".date("Y-m-d H:i:s")."\n";
    
                        $thenux_params["tag"]         = $tag;
                        $thenux_params["message"]     = $message;
                        $thenux_params["mobile_list"] = $xun_numbers;
                        $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                    }
                   
                }

            }
               
            return $remaining_amount;

        }

        public function calculate_miner_fee($sender_address, $receiver_address, $amount, $wallet_type, $miner_calculation){
            $post = $this->post;    
        
            // get noce and sign
            $post_params = [];
            $post_params["senderAddress"] = $sender_address;
            $post_params["receiverAddress"] = $receiver_address;
            $post_params["amount"] = $amount;
            $post_params["walletType"] = $wallet_type;
            $post_params["minerCalculation"] = $miner_calculation; // 1 is calculate miner fees only, other fully verify

            $veri_res = $post->curl_crypto("calculateMinerFee", $post_params, 2);

            return $veri_res;
        }

        public function calculate_miner_fee_external($sender_address, $amount, $wallet_type){
            $post = $this->post;    
        
            // get noce and sign
            $post_params = [];
            $post_params["senderAddress"] = $sender_address;
            $post_params["amount"] = $amount;
            $post_params["walletType"] = $wallet_type;

            $veri_res = $post->curl_crypto("calculateMinerFeeExternal", $post_params, 2);

            return $veri_res;
        }

        public function pg_calculate_miner_fee_external($sender_address, $amount, $wallet_type){
            $post = $this->post;    
        
            // get noce and sign
            $post_params = [];
            $post_params["senderAddress"] = $sender_address;
            $post_params["amount"] = $amount;
            $post_params["walletType"] = $wallet_type;

            $veri_res = $post->curl_crypto("calculateMinerFeeExternal", $post_params);

            return $veri_res;
        }

        public function insertMarketerCommissionTransaction($business_marketer_commission_id, $marketer_commission_amount, $marketer_satoshi_amount, $wallet_type, $credit, $debit, $balance , $message = '' , $type ='Transfer In', $reference_id = 0, $reseller_id = 0){
            $db= $this->db;

            if($reseller_id == 0){
                $db->where('b.id', $business_marketer_commission_id);
                $db->join('xun_business_marketer_commission_scheme b', 'b.marketer_id = a.marketer_id', 'INNER');
                $reseller = $db->getOne('reseller a', 'b.marketer_id, a.id');
        
                $reseller_id = $reseller['id'];
    
            }
          
            $date = date("Y-m-d H:i:s");
            $insert_marketer_transaction = array(
                "business_marketer_commission_id" => $business_marketer_commission_id,
                "reseller_id" => $reseller_id,
                "amount" => $marketer_commission_amount,
                "amount_satoshi" => $marketer_satoshi_amount,
                "wallet_type" => $wallet_type,
                "type" => $type,
                "reference_id" => $reference_id,
                "message" => $message,
                "credit" => $credit,
                "debit" => $debit,
                "balance" => $balance,
                "created_at" => $date,
                "updated_at" => $date,

            );
  
            $marketer_commission_transaction_id = $db->insert('xun_marketer_commission_transaction', $insert_marketer_transaction);  
            return $marketer_commission_transaction_id;


        }

        public function get_user_service_charge($params){
            global $setting, $xunErlang, $xunServiceCharge;
            global $xunCurrency;
            $db = $this->db;
            $general = $this->general;
            $date = date("Y-m-d H:i:s");

            $user_id = trim($params["user_id"]);
            $amount = trim($params["amount"]);
            $wallet_type = trim($params["wallet_type"]);
            $service_charge_rate = $params["service_charge_rate"];

            if(!isset($amount)){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "amount is required.");
            }
            if(!isset($wallet_type)){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "wallet_type is required.");
            }

            if($amount != '' && ($service_charge_rate > 0 || $service_charge_rate == null)){

                //  convert to ethereum for custom erc20 tokens
                $db->where("currency_id", $wallet_type);
                $xun_coin = $db->getOne("xun_coins", "currency_id, pg_fee_wallet_type");
                $service_charge_wallet_type = $xun_coin["pg_fee_wallet_type"];

                $service_charge_calculation_amount = $amount;
                
                if($service_charge_wallet_type != $wallet_type){
                    $service_charge_calculation_amount = $xunCurrency->get_conversion_amount($service_charge_wallet_type, $wallet_type, $amount);
                }

                $xun_commission = new XunCommission($db, $setting, $general);
                $commission_details = $xun_commission->get_commission_details($service_charge_calculation_amount, $service_charge_wallet_type, $service_charge_rate);
    
                $result["service_charge"] = $commission_details;
                $service_charge_amount = $commission_details["amount"];
                $recipient_address = $commission_details["address"];
                return $commission_details;
            }

            return false;
        }

        public function miner_fee_low_balance_notification($params){
            global $setting, $xunXmpp, $xun_numbers, $xunCurrency;
            $db = $this->db;
            $general - $this->general;

            $miner_fee_balance = $params['balance'];
            $wallet_type = $params['wallet_type'];
            $business_id = $params['business_id'];
            $business_name = $params['business_name'];
            $address = $params['address'];
            $transaction_type = $params['transaction_type'];

            $miner_fee_usd_value = $xunCurrency->get_conversion_amount('usd', $wallet_type, $miner_fee_balance);
            $bc_miner_fee_low_threshold = $setting->systemSetting['bcMinerFeeLowThreshold'];

            if(!empty($bc_miner_fee_low_threshold) && ($miner_fee_usd_value < $bc_miner_fee_low_threshold)){
                $tag = 'Low Miner Fee Balance';
                $message = "Business ID: $business_id\n";
                $message .= "Business Name: $business_name\n";
                $message .= "Address: $address\n";
                $message .= "Transaction Type: $transaction_type\n";
                $message .= "Wallet Type: $wallet_type \n";
                $message .= "Balance: $miner_fee_balance \n";
                $message .= "USD Balance: $miner_fee_usd_value \n";
                $message .= "\n";
                $message .= "Time: " . date('Y-m-d H:i:s');
                $thenux_params["tag"] = $tag;
                $thenux_params["message"] = $message;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");       
            }
        }

        public function check_wallet_transaction_status($params){
            global $log, $xunXmpp, $xun_numbers;
            $db = $this->db;
            $general = $this->general;

            $end_dt = $params["end_dt"];
            $start_dt = $params["start_dt"];

            $db->where("status", array("wallet_success", "pending"), "IN");
            $db->where("created_at", $end_dt, "<");
            if($start_dt){
                $db->where("created_at", $start_dt, ">=");
            }

            $wallet_transaction_arr = $db->get("xun_wallet_transaction");

            if(empty($wallet_transaction_arr)){
                $log->write(date("Y-m-d H:i:s") . " No wallet transaction to process.\n");
                return;
            }

            foreach($wallet_transaction_arr as $wallet_transaction){
                //  check crypto_transaction_hash
                $wallet_transaction_id = $wallet_transaction['id'];
                $transaction_hash = $wallet_transaction["transaction_hash"];
                $recipient_address = $wallet_transaction["recipient_address"];
                $sender_address = $wallet_transaction["sender_address"];
                $address_type = $wallet_transaction["address_type"];
                $wallet_transaction_type = $wallet_transaction["transaction_type"];
                $sender_user_id = $wallet_transaction["sender_user_id"];
                $recipient_user_id = $wallet_transaction["recipient_user_id"];
                $wallet_type = $wallet_transaction["wallet_type"];
                $amount = $wallet_transaction["amount"];
                $tx_time = $wallet_transaction["created_at"];

                $db->where ("(transaction_hash = ? or ex_transaction_hash = ?)", Array($transaction_hash, $transaction_hash));
                $db->where("recipient_address", $recipient_address);
                $crypto_transaction_hash = $db->getOne("xun_crypto_transaction_hash");
                if(!$crypto_transaction_hash){
                    $db->where ("(transaction_hash = ? or ex_transaction_hash = ?)", Array($transaction_hash, $transaction_hash));
                    $db->where("recipient", $recipient_address);
                    $db->where("type", $wallet_transaction_type);
                    $crypto_callback = $db->getOne("xun_crypto_callback");

                    if(!$crypto_callback){
                        // echo "\nUnable to find crypto callback for Transaction Hash: $transaction_hash .\n";
                        $tag = 'Tx Pending Confirmation';
                        $message = "Sender ID: $sender_user_id\n";
                        $message .= "Recipient User ID: $recipient_user_id\n";
                        $message .= "Sender Address: $sender_address\n";
                        $message .= "Recipient Address: $recipient_address\n";
                        $message .= "Wallet Type: $wallet_type \n";
                        $message .= "Amount: $amount \n";
                        $message .= "Wallet Tx ID: $wallet_transaction_id\n";
                        $message .= "Transaction Type: $address_type\n";
                        $message .= "Transaction Hash: $transaction_hash\n";
                        $message .= "Transaction Time: $tx_time\n";
                        $message .= "\n";
                        $message .= "Time: " . date('Y-m-d H:i:s');
                        $thenux_params["tag"] = $tag;
                        $thenux_params["message"] = $message;
                        $thenux_params["mobile_list"] = $xun_numbers;
                        $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");  
                        $log->write(date("Y-m-d H:i:s") . " Unable to find crypto callback for Transaction Hash: $transaction_hash .\n");
                        continue;
                    }
                }

                if(!in_array($crypto_transaction_hash["status"], ["confirmed", "completed"])){
                    if(!(isset($crypto_callback) && in_array($crypto_callback["status"], ["confirmed", "completed"]))){
                        // echo "\n  No confirmed status for Transaction Hash: $transaction_hash .\n";
                        $tag = 'Tx Pending Confirmation';
                        $message = "Sender ID: $sender_user_id\n";
                        $message .= "Recipient User ID: $recipient_user_id\n";
                        $message .= "Sender Address: $sender_address\n";
                        $message .= "Recipient Address: $recipient_address\n";
                        $message .= "Wallet Type: $wallet_type \n";
                        $message .= "Amount: $amount \n";
                        $message .= "Wallet Tx ID: $wallet_transaction_id\n";
                        $message .= "Transaction Type: $address_type\n";
                        $message .= "Transaction Hash: $transaction_hash\n";
                        $message .= "Transaction Time: $tx_time\n";
                        $message .= "\n";
                        $message .= "Time: " . date('Y-m-d H:i:s');
                        $thenux_params["tag"] = $tag;
                        $thenux_params["message"] = $message;
                        $thenux_params["mobile_list"] = $xun_numbers;
                        $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");  
                        $log->write(date("Y-m-d H:i:s") . " No confirmed status for Transaction Hash: $transaction_hash .\n");
                        continue;
                    }
                }

                //  retrigger crypto callback function
                $xun_recipient = $this->get_xun_user_by_crypto_address($recipient_address);
                if($xun_recipient["code"] == 0){
                    $xun_recipient_username = $xun_recipient["name"];
                    $target = $xun_recipient["type"] ? $xun_recipient["type"] : $target;
                    $direction = 'receive';
                }
                else {
                    $xun_recipient_user = $xun_recipient["xun_user"];
                    $xun_recipient_username = $xun_recipient_user["username"];
                    $xun_recipient_address_data = $xun_recipient["user_address_data"];
                    $this->recipient_address_data = $xun_recipient_address_data;
                }
                
                $xun_sender = $this->get_xun_user_by_crypto_address($sender_address);
    
                if($xun_sender["code"] == 0){
                    $xun_sender_username = $xun_sender["name"];
                    $target = (in_array($target, ["internal", "external"]) && $xun_sender["type"]) ? $xun_sender["type"] : $target;
                    $direction = 'send';
    
                    if($xun_sender['type']== 'payment_gateway'){
                        $this->sender_address_data = $xun_sender;
                    }
                }
                else{
                    $xun_sender_user = $xun_sender["xun_user"];
                    $xun_sender_username = $xun_sender_user["username"];
                    $xun_sender_address_data = $xun_sender["user_address_data"];
                    $this->sender_address_data = $xun_sender_address_data;
                    $this->sender_address_data["xun_user"] = $xun_sender_user;
                }
    
                if($address_type == "service_charge"){
                    $transaction_type = "receive";
                }else if($address_type == "company_pool"){
                    //  recipient = company_pool
                    $transaction_type = "send";
                }else if($address_type == "company_acc"){
                    $transaction_type = "send";
                }else if($address_type == "reward" && $xun_recipient_address_data["address_type"] == "reward"){
                    $transaction_type = "receive";
                }else if($address_type == "reward" && $xun_sender_address_data["address_type"] == "reward"){
                    $transaction_type = "send";
                }
                // else if($address_type == "external_transfer"){
                //     $transaction_type = $wallet_transaction_type;
                // }
                else{
                    continue;
                }

                $bc_recipient_address = $crypto_transaction_hash["recipient_address"] ?: $crypto_callback["recipient"];
                $bc_sender_address = $crypto_transaction_hash["sender_address"] ?: $crypto_callback["account_address"];
                $bc_amount = $crypto_transaction_hash["amount"] ?: $crypto_callback["amount"];
                $bc_transaction_hash = $crypto_transaction_hash["transaction_hash"]?: $crypto_callback["transaction_hash"];
                $bc_ex_transaction_hash = $crypto_transaction_hash["ex_transaction_hash"]?: $crypto_callback["ex_transaction_hash"];
                $bc_type = $crypto_transaction_hash["type"]?: $crypto_callback["target"];

                $crypto_callback_params = array(
                    "target" => $bc_type,
                    "type" => $transaction_type,
                    "status" => "confirmed",
                    "recipient" => $bc_recipient_address,
                    "sender" => $bc_sender_address,
                    "amount" => $bc_amount,
                    "transactionHash" => $bc_transaction_hash,
                    "exTransactionHash" => $bc_ex_transaction_hash,
                    "wallet_type" => $wallet_type
                );

                $transaction_target = $bc_type;
                $amount_decimal = $bc_amount;
                if($transaction_target == "internal"){
                    // check escrow transaction
                    $res = $this->check_crypto_internal_transaction($crypto_callback_params, $amount_decimal);
                }
                elseif($transaction_target == "external"){
                    $res = $this->check_crypto_external_transaction($crypto_callback_params, $amount_decimal);
                }
            }
        }

        /**
         * Used to check token name and symbol at blockchain before assigning
         * to user
         */
        public function check_crypto_token_name($token_name, $token_symbol){
            global $log;
            $db = $this->db;
            $general = $this->general;

            $name_checking_params = array(
                "name" => $token_name,
                "symbol" => $token_symbol
            );

            while(true){
                $check_token = $this->check_token_name_availability($name_checking_params);

                $name_checking_data = $check_token["data"];
                
                if($check_token['status'] == 'ok'){
                    break;
                    // return $name_checking_params;
                }
                //name
                if($name_checking_data["errorCode"] == "E10004"){
                    $rand_string = $general->generateAlpaNumeric(3);
                    $new_token_name = $token_name . $rand_string;
                }
                //symbol
                if($name_checking_data["errorCode"] == "E10005"){
                    $rand_char = $general->generateAlpaNumeric(1);
                    $new_token_symbol = $token_symbol . $rand_char;
                }

                if($name_checking_data["errorCode"] == "E10008"){
                    $rand_char = $general->generateAlpaNumeric(1);
                    $new_token_symbol = substr($token_symbol, 0, 3);
                    $new_token_symbol .= $rand_char;
                }

                $name_checking_params = array(
                    "name" => $new_token_name ?: $token_name,
                    "symbol" => $new_token_symbol ?: $token_symbol
                );
            }

            return $name_checking_params;
        }

        function set_destination_address_v2($params, $source = "business"){
            global $config, $xunPaymentGateway;
            
            $db = $this->db;
            $post = $this->post;
            $general = $this->general;
            
            $business_id            = $params["business_id"];
            $wallet_type            = $params["wallet_type"];
            $destination_address    = $params["destination_address"];
            $wallet_name            = $params["wallet_name"];
            $status                 = strlen($params["status"]) > 0 ? $params["status"] : "1";
            
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }
            
            if (!$wallet_type) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
            }

            if (!$wallet_name) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00539') /*Wallet Name cannot be empty*/);
            }
            
            if (!$destination_address) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00153') /*Destination Address cannot be empty*/);
            }
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }
            
            $wallet_type = strtolower($wallet_type);
            $currentDate = date("Y-m-d H:i:s");
            $db->where("currency_id", $wallet_type);
            $db->where("is_payment_gateway", 1);
            $pg_wallet_type = $db->getOne("xun_coins", "id, currency_id, pg_fee_wallet_type");
            if(!$pg_wallet_type){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid wallet type.");
            }
            $db->where("business_id", $business_id);
            $db->where("type", $wallet_type);
            $wallet_result = $db->getOne("xun_crypto_wallet");

            if(!$wallet_result){
                $fields = array("business_id", "type", "status", "created_at", "updated_at");
                $values = array($business_id, $wallet_type, $status, $currentDate, $currentDate);
                $insertData = array_combine($fields, $values);

                $wallet_id = $db->insert("xun_crypto_wallet", $insertData);
            }else{
                $wallet_id = $wallet_result["id"];

                if($wallet_result["status"] != $status) {
                    $updateData["updated_at"] = $currentDate;
                    $updateData["status"] = $status;
        
                    $db->where("id", $wallet_id);
                    $db->update("xun_crypto_wallet", $updateData);
                }

            }

            $db->where("wallet_id", $wallet_id);
            $db->where("type", "in");

            $address_result = $db->get("xun_crypto_address");

            if(!$address_result){

                $db->where('id', $business_id);
                $xun_user = $db->getOne('xun_user', 'nickname');
                $cryptoParams["type"] = $wallet_type;
                $cryptoParams['businessID'] = $business_id;
                $cryptoParams['businessName']= $xun_user['nickname'];

                $cryptoResult = $post->curl_crypto("getNewAddress", $cryptoParams);

                if($cryptoResult["code"] != 0){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $cryptoResult["message"]);
                }

                $new_address = $cryptoResult["data"]["address"];

                if(!$new_address){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00155') /*Address not generated.*/);
                }

                //	id	user_id	crypto_address	wallet_type	type	status	created_at	updated_at
                $fields = array("wallet_id", "crypto_address", "type", "status", "created_at", "updated_at");
                $values = array($wallet_id, $new_address, "in", "1", $currentDate, $currentDate);
                $insertData = array_combine($fields, $values);

                $db->insert("xun_crypto_address", $insertData); 

            }

            //validate destination address
            $cryptoParams["type"] = $wallet_type;
            $cryptoParams["address"] = $destination_address;

            $cryptoResult = $post->curl_crypto("validateAddress", $cryptoParams);

            if($cryptoResult["code"] != 0){
                
                $translations_message = $this->get_translation_message('E00161') /*Invalid %%wallet_type%% destination address.*/;
                $return_message = str_replace("%%wallet_type%%", ucfirst($wallet_type), $translations_message);

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $return_message);
            }

            $db->where("wallet_id", $wallet_id);
            $db->where('status', 1);
            $crypto_dest_result = $db->get('xun_crypto_destination_address');

            if($crypto_dest_result){
                foreach($crypto_dest_result as $key => $value){
                    $updateData = array(
                        "status" => 0,
                        "updated_at" => date("Y-m-d H:i:s")
                    );
                    $db->where('id', $value['id']);
                    $db->update('xun_crypto_destination_address', $updateData);
                }
            }
           
            $db->where("wallet_id", $wallet_id);
            $db->where('destination_address', $destination_address);
            $dest_result = $db->getOne("xun_crypto_destination_address");

            if(!$dest_result){
                $fields = array("wallet_id", "type", "destination_address", "status", "created_at", "updated_at", "wallet_name");
                $values = array($wallet_id, $wallet_type, $destination_address, "1", $currentDate, $currentDate, $wallet_name);
                $insertData = array_combine($fields, $values);

                $db->insert("xun_crypto_destination_address", $insertData); 
                $tag = "Set Destination Address";
            }else{
                $updateData["destination_address"] = $destination_address;
                $updateData["updated_at"] = $currentDate;
                $updateData["status"] = 1;
                $updateData["wallet_name"] = $wallet_name;

                $db->where("id", $dest_result['id']);
                $db->update("xun_crypto_destination_address", $updateData);
                $tag = "Change Destination Address";
            }

            if($status == 1 && $wallet_type != $pg_wallet_type["pg_fee_wallet_type"]){
                $save_delegate_address_result = $this->save_delegate_address($business_id);
                if(isset($save_delegate_address_result["code"]) && $save_delegate_address_result["code"] == 0){
                    return $save_delegate_address_result;
                }
            }
            $db->where('id', $business_id);
            $db->where('register_site', $source);
            $xun_user = $db->getOne('xun_user');
            $phone_number = $xun_user["username"];
            $nickname = $xun_user["nickname"];
            $email = $xun_user["email"];

            $message .= "Username: ".$nickname."\n";
            $message .= "Email: ".$email."\n";
            $message .= "Phone number: " .$phone_number."\n";
            $message .= "Cryptocurrency type: ".$wallet_type."\n";
            $message .= "Destination Address: ".$destination_address."\n";
            $message .= "Time: ".date("Y-m-d H:i:s")."\n";
            $message .= "Source: ".$source."\n";

            $thenux_params["tag"]         = $tag;
            $thenux_params["message"]     = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $xmpp_result                  = $general->send_thenux_notification($thenux_params, "thenux_pay");
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00096') /*Addresses successfully set*/, "code" => 1, "result" => $result);
            
        }

        public function get_escrow_transaction($params){
            $db     = $this->db;
            $post   = $this->post;

            $business_id = $params['business_id'];
            $escrow_id = $params['escrow_id'];            
            $type = $params['type'];            
            $whitelistType = array('send','receive');
            
            if (empty($business_id)) {
                // TODO Language plugin: Business ID cannot be empty!
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business id cannot be empty!" /* Business id cannot be empty */);
            }
            if (empty($escrow_id)) {
                // TODO Language plugin: Escrow id cannot be empty!
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Escrow id cannot be empty!" /* Escrow id cannot be empty */);
            }
            if (empty($type)) {
                // TODO Language plugin: Type cannot be empty!
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Type cannot be empty!" /* Type cannot be empty */);
            }

            if(!(in_array($type,$whitelistType))){
                // TODO Language plugin: Invalid type!
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid type!" /* Invalid type */);
            }

            $db->where('id', $escrow_id);
            $escrow_table = $db->getOne('xun_escrow');

            if (empty($escrow_table)) {
                // TODO Language plugin: Invalid escrow id!
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid escrow id!" /* Escrow id cannot be empty */);
            }

            $db->where('id', $escrow_table['reference_id']);
            $send_fund_table = $db->getOne('xun_payment_gateway_send_fund');
            // this shouldn't happen 
            if (empty($send_fund_table)) {                
                return array("code" => "0", "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/ , "developer_msg"=>$curlResponse);
            }

            
            $release_status = 0;
            $db->where('escrow_id', $escrow_id);
            $fund_in_table = $db->getOne('xun_payment_gateway_fund_in');

            $db->where('escrow_id', $escrow_id);
            $withdrawal_table = $db->getOne('xun_payment_gateway_withdrawal');

            $data['business_id'] = $send_fund_table['business_id'];
            $data['created_at'] = $send_fund_table['created_at'];
            $data['sender_address'] = $fund_in_table['sender_address'];        
            $data['destination_address'] = $withdrawal_table['recipient_address'];

            if ($type == 'send'){
                $db->where('id', $fund_in_table['business_id']);
                $user_table = $db->getOne('xun_user');
                $data['receiver_name'] = $user_table['nickname'];
                $data['receiver_mobile_number'] = $user_table['username'];
                $data['receiver_email_address'] = $user_table['email'];
                if ( (strtolower($send_fund_table['status'] == 'ready') ) && $business_id==$send_fund_table['business_id']) {
                    $release_status = 1;
                }
            }

            if ($type == 'receive'){
                $db->where('id', $send_fund_table['business_id']);
                $sender_user_table = $db->getOne('xun_user');
                $data['sender_name'] = $sender_user_table['nickname'];
                $data['sender_mobile_number'] = $sender_user_table['username'];
                $data['sender_email_address'] = $sender_user_table['email'];
            }
            
            
            $data['transaction_hash'] = $escrow_table['receive_tx_hash'];
            $data['amount'] = $withdrawal_table['amount'];
            $data['amount_final'] = $withdrawal_table['amount_receive'];
            $data['status'] = $send_fund_table['status'];
            $data['wallet_type'] = $send_fund_table['wallet_type'];

            $db->where('currency_id', $send_fund_table['wallet_type']);
            $currency_table = $db->getOne('xun_marketplace_currencies');
            $data['image'] = $currency_table['image'];
            $data['release_escrow'] = $release_status;
            

            // TODO Language plugin: Escrow Transaction Information
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => "Escrow Transaction Information" /* Escrow Transaction Information */, "code" => 1, "result" => $data);                

            
        }

        public function get_transaction_list_v1($params){
            global $setting, $excel;

            $db     = $this->db;
            $post   = $this->post;
            
            $member_page_limit  = $setting->getMemberPageLimit();
            $page_number        = $params["page"];
            $page_size          = $params["page_size"] ? $params["page_size"] : $member_page_limit;
            $business_id        = $params["business_id"];
            $wallet_type        = $params["wallet_type"];
            $status             = $params["status"];
            $from               = $params["from"];
            $to                 = $params["to"];
            $address            = $params['address'];
            $search_param       = $params["search_param"];
            $is_export          = $params["is_export"];
            $name               = $params['name'];
            $mobile             = $params['mobile'];
            $email              = $params['email'];
            
            if (!$business_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            }
            
            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");
            
            if(!$business_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }

            $db->where('id', $business_id);
            $user_result = $db->getOne('xun_user');

            if(!$user_result){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/, 'developer_msg' => 'Invalid User');
            }

            $user_email = $user_result['email'];
            $user_phone_number = $user_result['username'];

            if($search_param || $name || $mobile || $email){
                
                if($search_param){
                    $db->where("(payer_mobile_phone LIKE ? OR payer_name LIKE ? OR payer_email_address LIKE ?)", array("%$search_param%","%$search_param%","%$search_param%"));
                }
                
                if($name){
                    $db->where('payer_name', "%$name%" , 'LIKE');
                }

                if($email){
                    $db->where('payer_email_address', "%$email%" , 'LIKE');
                }

                if($mobile){
                    $db->where('payer_mobile_phone', "%$mobile%" , 'LIKE');
                }

                if($user_email && $user_phone_number){
                    $db->where('(payee_email_address LIKE ? or payee_mobile_phone LIKE ?)', array("%$user_email%", "%$user_phone_number%"));
                }
                else if($user_phone_number && !$user_email){
                    $db->where('payee_mobile_phone', "%$user_phone_number%", 'LIKE');
                }
                else if(!$user_phone_number && $user_email){
                    $db->where('payee_email_address', "%$user_email%", 'LIKE');
                }

                $pg_invoice_detail = $db->map('payment_address')->ArrayBuilder()->get('xun_payment_gateway_invoice_detail', null, 'payer_name, payer_mobile_phone, payer_email_address,payment_address');
          
                if($search_param){
                    $db->where("(sender_mobile_number LIKE ? OR sender_name LIKE ? OR sender_email_address LIKE ?)", array("%$search_param%","%$search_param%","%$search_param%"));
                }

                if($name){
                    $db->where('sender_name', "%$name%" , 'LIKE');
                }

                if($email){
                    $db->where('sender_email_address', "%$email%" , 'LIKE');
                }

                if($mobile){
                    $db->where('sender_mobile_number', "%$mobile%" , 'LIKE');
                }

                if($user_email && $user_phone_number){
                    $db->where('(recipient_email_address LIKE ? or recipient_mobile_number LIKE ?)', array("%$user_email%", "%$user_phone_number%"));
                }

                else if($user_phone_number && !$user_email){
                    $db->where('recipient_mobile_number', "%$user_phone_number%", 'LIKE');
                }

                else if(!$user_phone_number && $user_email){
                    $db->where('recipient_mobile_number', "%$user_phone_number%", 'LIKE');
                }
                // $db->where('business_id', $business_id);
               
                $send_fund_detail = $db->map('id')->ArrayBuilder()->get('xun_payment_gateway_send_fund', null, 'id');
                if(!$pg_invoice_detail && !$send_fund_detail){
                    return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
                }
                $pg_address_list = array_keys($pg_invoice_detail);
                if($pg_address_list){
                    $db->where('a.address', $pg_address_list, 'IN');
                    $db->where('a.business_id', $business_id);
                    $db->join('xun_payment_gateway_payment_transaction b', 'a.id = b.crypto_history_id', 'LEFT');
                    $crypto_history_list = $db->map('address')->ArrayBuilder()->get('xun_crypto_history a', null, 'a.id, a.received_transaction_id, a.address, b.reference_id');
                }
                if($send_fund_detail){
                    $send_fund_ids = array_keys($send_fund_detail);
                    $db->where('recipient_user_id', $business_id);
                    $db->where('reference_id', $send_fund_ids, 'IN');
                    $db->where('message', array('send_fund', 'redeem_code'), 'IN');
                    $send_fund_transaction = $db->map('transaction_hash')->ArrayBuilder()->get('xun_wallet_transaction', null, 'id, transaction_hash');

                }
               
            }
            
            $db->where("a.business_id", $business_id);
            
            if ($wallet_type) {
                $db->where("a.wallet_type", $wallet_type);
            }
            
            if ($from) {
                $from = date("Y-m-d H:i:s", $from);
                $db->where("a.created_at", $from, ">=");
            }
            if ($to) {
                $to = date("Y-m-d H:i:s", $to);
                $db->where("a.created_at", $to, "<=");
            }
            
            // Column removed

            

            if($status == 'Transferred'){
                $status = 'success';
            }else if ($status == 'Received'){
                 $status = 'received';
            }
            
            if($status){
                $db->where('b.status', $status);
                // $db->orWhere('a.status', $status);
            }


            if ($page_number < 1){
                $page_number = 1;
            }

            $start_limit = ($page_number - 1) * $page_size;
            $limit       = array($start_limit, $page_size);

            $consolidate_wallet_address = $setting->systemSetting['requestFundConsolidateWalletAddress'];
            // $db->where('recipient_internal', $consolidate_wallet_address, '!=');
            $db->orderBy("a.created_at", "DESC");
            if($pg_address_list){
                if($send_fund_transaction){
                    $send_fund_tx_list = array_keys($send_fund_transaction);
                    // $db->where ("(transaction_id IN ? OR receiver_address IN ?)", Array($send_fund_tx_list,$pg_address_list));
                    $db->where('a.transaction_id', $send_fund_tx_list, 'IN');
                    $db->where('a.receiver_address', $pg_address_list, 'IN');
                    
                }
                else{
                    $db->where('a.receiver_address', $pg_address_list, 'IN');
    
                }
            }
      
            $copyDb = $db->copy();
            if($is_export == 1){
                $limit = null;
            }

            // $db->where('b.received_transaction_id', '' ,'!=');
            $db->join('xun_crypto_history b','b.received_transaction_id = a.transaction_id', 'LEFT');
            $db->groupBy('a.escrow_id');
            $db->groupBy('a.transaction_id');
            $db->groupBy('a.reference_id');
            $history_result = $db->get('xun_payment_gateway_fund_in a', $limit, 'b.status, a.status as fund_in_status, a.transaction_id, a.sender_address, a.receiver_address, a.wallet_type, a.amount, a.amount_receive, a.transaction_fee, a.miner_fee, a.created_at, a.transaction_type, a.escrow_id, b.business_id, a.type, a.escrow_id, a.transaction_target'); // wentin // get from the database
            if (!$history_result) {
                return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
            }


            // $totalRecord = $copyDb->getValue('xun_payment_gateway_fund_in a', 'count(b.id)');
            $copyDb->join('xun_crypto_history b','b.received_transaction_id = a.transaction_id', 'LEFT');
            $copyDb->groupBy('a.escrow_id');
            $copyDb->groupBy('a.transaction_id');
            $totalRecordData = $copyDb->get('xun_payment_gateway_fund_in a', null, 'a.id');
            $totalRecord = count($totalRecordData);
            $marketplace_currencies = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies', null, 'currency_id, name, symbol, image');
          
            if(!$search_param){
                foreach($history_result as $key => $value){
                    $pg_address = $value['receiver_address'];
                    $address_list[]= $pg_address;
                    $transaction_hash = $value['transaction_id'];
                    $tx_hash_list[] = $transaction_hash;
                }
    
                $db->where('payment_address', $address_list, 'IN');
                $pg_invoice_detail = $db->map('payment_address')->ArrayBuilder()->get('xun_payment_gateway_invoice_detail', null, 'payer_name, payer_mobile_phone, payer_email_address,payment_address, payment_description');

                $db->where('a.address', $address_list, 'IN');
                $db->join('xun_payment_gateway_payment_transaction b', 'a.id = b.crypto_history_id', 'LEFT');
                $crypto_history_list = $db->map('address')->ArrayBuilder()->get('xun_crypto_history a', null, 'a.id, a.received_transaction_id, a.address, a.status,  b.reference_id');
               
            }

            foreach($history_result as $key => $value){
                if($value['escrow_id'] != '0'){
                    $escrow_id = $value['escrow_id'];
                    $escrow_id_list[] = $escrow_id;

                    $db->where('id', $escrow_id_list, 'IN');
                    $escrow = $db->getValue('xun_escrow', 'receive_tx_hash');
                    $transaction_hash = $escrow;
                    $tx_hash_list[] = $transaction_hash;
                }
            }

            $db->where('transaction_hash', $tx_hash_list, 'IN');
            $wallet_tx_data = $db->map('transaction_hash')->ArrayBuilder()->get('xun_wallet_transaction', null, 'id, transaction_hash, status, reference_id, message');

            foreach($wallet_tx_data as $key => $value){
                $message = $value['message'];

                if($message == 'send_fund' || $message == 'redeem_code' || $message == 'send_ecrrow'){
                    $send_fund_ids[] = $value['reference_id'];
                }
            }

            if($send_fund_ids){
                $db->where('id', $send_fund_ids, 'IN');
                $send_fund_detail = $db->map('id')->ArrayBuilder()->get('xun_payment_gateway_send_fund');
            }
          
            $db->where('received_transaction_id', $tx_hash_list, 'IN');
            $pg_tx_data = $db->map('received_transaction_id')->ArrayBuilder()->get('xun_crypto_history', null, 'id, received_transaction_id, status');
            
            foreach($history_result as $history){
                if($history['escrow_id'] != '0'){
                    $escrow_id = $history['escrow_id'];
                    $escrow_id_list[] = $escrow_id;

                
                    $db->where('id', $escrow_id_list, 'IN');
                    $escrow = $db->getValue('xun_escrow', 'receive_tx_hash');

                    $transaction_hash = $escrow;
                }else{
                    $transaction_hash = $history['transaction_id'];
                }
            
                $pg_address = $history['receiver_address'];
                $wallet_type = $history['wallet_type'];
                $wallet_type = strtolower($wallet_type);
                $transaction_type = $history['transaction_type'];
                $type = $history['type'];
                $crypto_history_id = $crypto_history_id[$pg_address]['id'];

                if ($history['status'] ) {

                    if($history['status'] == 'pending'){
                        $status = 'Received';
                    }else{
                        $status = $history['status'];
                    }
                } else if ($history['fund_in_status'] ) {

                    $status = $history['fund_in_status'] == 'success' ? 'received' : $history['fund_in_status'];
                } else {
                    $status = $pg_tx_data[$transaction_hash]['status'] ? $pg_tx_data[$transaction_hash]['status'] : $wallet_tx_data[$transaction_hash]['status'];
                }
                
                
                $wallet_tx_message = $wallet_tx_data[$transaction_hash]['message'] ?  $wallet_tx_data[$transaction_hash]['message'] : '';
                $reference_id = 0;
                unset($send_fund_detail);
                if($wallet_tx_message =='send_fund' || $wallet_tx_message == 'redeem_code' || $wallet_tx_message == 'release_escrow' || $wallet_tx_message == 'send_escrow'){
                    $reference_id = $wallet_tx_data[$transaction_hash]['reference_id'];

                    $db->where('id', $reference_id);
                    $send_fund_detail = $db->getOne('xun_payment_gateway_send_fund', 'sender_name, sender_mobile_number, sender_email_address');
                }
                
                $image = $marketplace_currencies[$wallet_type]['image'];
                $unit = $marketplace_currencies[$wallet_type]['symbol'];
                $uc_unit = strtoupper($unit);
                if($transaction_type == "blockchain" && $type == "fund_in"){
                    $history['payer_name'] = '-';
                    $history['payer_mobile_phone'] = '-';
                    $history['payer_email_address'] = '-';
                }else{
                    $history['payer_name'] = $pg_invoice_detail[$pg_address]['payer_name'] ? $pg_invoice_detail[$pg_address]['payer_name'] : ($send_fund_detail['sender_name'] ? $send_fund_detail['sender_name'] :  '-') ;
                    $history['payer_mobile_phone']= $pg_invoice_detail[$pg_address]['payer_mobile_phone'] ? $pg_invoice_detail[$pg_address]['payer_mobile_phone'] : ($send_fund_detail['sender_mobile_number'] ? $send_fund_detail['sender_mobile_number'] : '-');
                    $history['payer_email_address'] = $pg_invoice_detail[$pg_address]['payer_email_address'] ? $pg_invoice_detail[$pg_address]['payer_email_address'] : ($send_fund_detail['sender_email_address'] ? $send_fund_detail['sender_email_address'] : '-');
                }
                $history['image']= $image;
                $history['currency_unit'] = $uc_unit;
                $history['status'] = $status == 'received' ? 'Received' : ($status == 'success' ? 'Transferred' : ucfirst($status));
                // $history['status'] = $status == 'received' || $status == 'success' ? 'Received' : 'Transferred';
                //$history['status'] = $status == 'completed' || $status == 'received' ? 'Success' : ucfirst($status);
                $history['description'] = $pg_invoice_detail[$pg_address]['payment_description'] ? $pg_invoice_detail[$pg_address]['payment_description'] : ($send_fund_detail[$reference_id]['description'] ? $send_fund_detail[$reference_id]['description'] :  '-');
                $history['merchant_reference_id'] = $crypto_history_list[$pg_address]['reference_id'] ? $crypto_history_list[$pg_address]['reference_id'] : 0;

 
                if ($history['escrow_id'] != 0 ) {
                    $db->where('id', $history['escrow_id']);
                    $escrow_table = $db->getOne('xun_escrow');

                    $db->where('reference_id', $escrow_table['id']);
                    $escrow_chat_count = $db->getValue('xun_escrow_chat', 'count(*)');

                } else {
                    $escrow_chat_count = 0;
                }
                $history['escrow_chat_count'] = $escrow_chat_count;
                $return[] = $history;
            }
            
            if (!$return) {
                return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
            }

            if($is_export){
                $header = array(
                    "Date, Time",
                    "Paid To",
                    "Paid From",
                    "Name",
                    "Mobile Number",
                    "Amount",
                    "Processing Fee",
                    "Transaction Hash",
                    "Status",
                    "Merchant Trx ID",
                );
                $dataKeyArr = array(
                    "created_at",
                    "receiver_address",
                    "sender_address",
                    "payer_name",
                    "payer_mobile_phone",
                    "amount",
                    "transaction_fee",
                    "transaction_id",
                    "status",
                    "merchant_reference_id"
                );
                $data["base64"] = $excel->exportExcelBase64($return,$header,$dataKeyArr);
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' =>$this->get_translation_message('E00154') /*Transaction History.*/, 'data' => $data);
            }

            $returnData["transaction_list"] = $return;
            $returnData["totalRecord"] = $totalRecord;
            $returnData["numRecord"] = $page_size;
            $returnData["totalPage"] = ceil($totalRecord/$page_size);
            $returnData["pageNumber"] = $page_number;
            
            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00154') /*Transaction History.*/, "code" => 1, "result" => $returnData);                
        }

        public function get_miner_fee_balance($params){
            $db = $this->db;

            $address = $params['address'];
            $wallet_type = $params['wallet_type'];

            $db->where('address', $address);
            $db->where('wallet_type', $wallet_type);
            $db->orderBy('id', 'Desc');
            
            $cryptoInfo = $db->getOne('xun_miner_fee_transaction');
            $returnData["balance"] = $cryptoInfo["balance"];

            if ($cryptoInfo){
                return array('code' => 1, "status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00319'), "result" => $returnData);
            } else {
                return array('code' => 0, 'message' => "FAILED", "message_d" => $this->get_translation_message('E00540'));
            }
        }

        public function replace_transaction_id($params){
            $db = $this->db;

            $oldTxId = $params['old_transaction_id'];
            $newTxId = $params['new_transaction_id'];

            $db->where('transaction_hash', $oldTxId);
            $copyDb = $db->copy();
            $oldEntry = $db->getOne('xun_wallet_transaction');
            

            if(!$oldTxId){
                return array('code' => 0, 'message' => "FAILED", "message_d" => "old_transaction_id is empty.");
            }

            if(!$newTxId){
                return array('code' => 0, 'message' => "FAILED", "message_d" => "new_transaction_id is empty.");
            }

            if(!$oldEntry){
                return array('code' => 0, 'message' => "FAILED", "message_d" => "Transaction ID not found.");
            } 

            if($oldEntry['status'] == 'completed'){
                return array('code' => 0, 'message' => "FAILED", "message_d" => "Transaction already completed.");
            }

            $updateData = array(
                'transaction_hash' => $newTxId
            );

            $copyDb->update('xun_wallet_transaction', $updateData);

            return array('code' => 1, "status" => "ok", "message" => "SUCCESS", "message_d" => "Transaction ID successfully updated.");
        }

        public function check_pg_address_fund_out($params){
            $db= $this->db;
            
            $pg_address = $params['address'];
            $wallet_type = $params['wallet_type'];

            $db->where('a.crypto_address', $pg_address);
            $db->where('b.type', $wallet_type);
            $db->join('xun_crypto_wallet b', 'a.wallet_id = b.id', 'LEFT');
            $crypto_address = $db->getOne('xun_crypto_address a', 'b.business_id, a.crypto_address');

            if(!$crypto_address){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00144') /*Address Not Found.*/);
            }

            $business_id = $crypto_address['business_id'];

            $db->where('user_id', $business_id);
            $db->where('name', $wallet_type."Threshold");
            $thresholdAmount = $db->getValue('xun_user_setting', 'value');

            $db->where('user_id', $business_id);
            $db->where('name', "isDailyFundOut");
            $isDailyFundOut = $db->getValue('xun_user_setting', 'value');

            $data['allowFundOut'] = $isDailyFundOut ? 1 : 0;

            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00338') /*Check PG Address Successful.*/, 'data' => $data);

        }

        public function process_withdrawal($tx_data){
            global $config, $xunCurrency, $xunMarketer, $xunMinerFee, $xunPayment, $xunCrypto, $setting;
            $db= $this->db;
            $post = $this->post;
            $general = $this->general;

            $xun_business_service = new XunBusinessService($db);
            $xunWallet = new XunWallet($db);

            $prepaidWalletServerURL =  $config["giftCodeUrl"];

            $miner_fee_wallet_tx_id = $tx_data['miner_fee_wallet_transaction_id'];
            $reference_id = $tx_data['miner_fee_reference_id'];
            $internal_address = $tx_data['internal_address'];
            $user_id = $tx_data['user_id'];

            $db->where('id', $user_id);
            $xun_user = $db->getOne('xun_user');

            $user_type = $xun_user['type'];
            $business_name = $xun_user['nickname'];
            
            if($user_type != "business"){
                $db->where('user_id', $user_id);
                $reseller = $db->getOne('reseller', 'id');
    
                $reseller_id = $reseller['id'];

                $db->where('id', $reference_id);
                $miner_fee_transaction = $db->getOne('xun_marketer_commission_transaction');
    
                $fund_out_transaction_id = $miner_fee_transaction['reference_id'];
                $miner_fee_details = $miner_fee_transaction['message'];

                $fund_out_transaction_id = $miner_fee_transaction['reference_id'];
                $miner_fee_details = $miner_fee_transaction['message'];
    
                $miner_fee_details_arr  = explode(":" ,$miner_fee_details);
                $ori_miner_fee_amount = trim($miner_fee_details_arr[1]);
    
                $db->where('id', $fund_out_transaction_id);
                $marketer_transaction = $db->getOne('xun_marketer_commission_transaction');
    
                $business_marketer_commission_id = $marketer_transaction['business_marketer_commission_id'];
                $amount = $marketer_transaction['amount'];
                $amount_satoshi = $marketer_transaction['amount_satoshi'];
                $wallet_type = $marketer_transaction['wallet_type'];
                $destination_address = $marketer_transaction['message'];
                $type = $marketer_transaction['type'];
    
                $tx_obj = new stdClass();
                $tx_obj->userID = $user_id;
                $tx_obj->address = $internal_address;
    
                $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);        
    
                $address_type = "nuxpay_wallet";
                $transaction_type = "send";
                $transactionObj->status = 'pending';
                $transactionObj->transactionHash = '';
                $transactionObj->transactionToken = $transaction_token;
                $transactionObj->senderAddress = $internal_address;
                $transactionObj->recipientAddress = $destination_address;
                $transactionObj->userID = $user_id ? $user_id : '';
                $transactionObj->senderUserID = $user_id;
                $transactionObj->recipientUserID = "";
                $transactionObj->walletType = $wallet_type;
                $transactionObj->amount = $amount;
                $transactionObj->addressType = $address_type;
                $transactionObj->transactionType = $transaction_type;
                $transactionObj->escrow = 0;
                $transactionObj->referenceID = '';
                $transactionObj->escrowContractAddress = '';
                $transactionObj->createdAt = $date;
                $transactionObj->updatedAt = $date;
                $transactionObj->expiresAt = '';
                $transactionObj->fee = $padded_fee;
                $transactionObj->feeUnit = $feeUnit;
    
                $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

                $txHistoryObj->paymentDetailsID = $payment_details_id;
                $txHistoryObj->status = "pending";
                $txHistoryObj->transactionID = "";
                $txHistoryObj->transactionToken = $transaction_token;
                $txHistoryObj->senderAddress = $internal_address;
                $txHistoryObj->recipientAddress = $destination_address;
                $txHistoryObj->senderUserID = $user_id;
                $txHistoryObj->recipientUserID = "";
                $txHistoryObj->walletType = $wallet_type;
                $txHistoryObj->amount = $amount;
                $txHistoryObj->transactionType = "marketer";
                $txHistoryObj->referenceID = '';
                $txHistoryObj->createdAt = $date;
                $txHistoryObj->updatedAt = $date;
                $txHistoryObj->fee = $padded_fee;
                $txHistoryObj->feeUnit = $feeUnit;
                // $transactionObj->fee = $final_miner_fee;
                // $transactionObj->feeWalletType = $miner_fee_wallet_type;
                // $txHistoryObj->exchangeRate = $exchange_rate;
                // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
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
       
                $ret_val= $this->crypto_validate_address($destination_address, $wallet_type, 'external');
                ///need to add send message when the destination  address is not valid
                if($ret_val['code'] == 1){
                    $ret_val1 = $this->crypto_validate_address($destination_address, $wallet_type, 'internal');
        
                    if($ret_val1['code'] ==0){
                        $destination_address = $ret_val1['data']['address'];
                        $transaction_type = $ret_val1['data']['addressType'];
                    }
                    else{
                        $update_wallet_transaction_arr = array(
                            "status" => 'failed',
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('id', $transaction_id);
                        $db->update('xun_wallet_transaction', $update_wallet_transaction_arr);
                    }
                }
                else{
                    $destination_address = $ret_val['data']['address'];
                    $transaction_type = $ret_val['data']['addressType'];
                }
    
                if($transaction_type == 'external'){
                    $miner_fee_split = explode(':', $miner_fee_details);
                    $original_miner_fee_amount = trim($miner_fee_split[1]);
    
                    $wallet_info = $this->get_wallet_info($internal_address, $wallet_type);
                    $minerFeeWalletType = strtolower($wallet_info[strtolower($wallet_type)]['feeType']);

                    $miner_fee_tx_data = array(
                        "address" => $internal_address,
                        "reference_id" => $transaction_id,
                        "reference_table" => "xun_wallet_transaction",
                        "type" => 'miner_fee_payment',
                        "wallet_type" => $minerFeeWalletType,
                        "debit" => $original_miner_fee_amount,
                    );
                    $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);
                
                    $curlParams = array(
                        "command" => "fundOutExternal",
                        "params" => array(
                            "senderAddress" => $internal_address,
                            "receiverAddress" => $destination_address,
                            "amount" => $amount,
                            "walletType" => strtolower($wallet_type),
                            "transactionToken" => $transaction_token,
                            "walletTransactionID" => $transaction_id
                        )
                    );
                    
                }
                else{
                    $curlParams = array(
                        "command" => "fundOutCompanyWallet",
                        "params" => array(
                            "senderAddress" => $internal_address,
                            "receiverAddress" => $destination_address,
                            "amount" => $amount,
                            "satoshiAmount" => $amount_satoshi,
                            "walletType" => strtolower($wallet_type),
                            "id" => $transaction_id,
                            "transactionToken" => $transaction_token,
                            "addressType" => "nuxpay_wallet",
                        ),
                    );
                }
                
                $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);
    
                if ($curlResponse['code'] == 1) {
                    $updateTx = array(
                        "reference_id" => $transaction_id,
                        "updated_at" => date("Y-m-d H:i:s")
                    );
    
                    $db->where('id', $fund_out_transaction_id);
                    $db->update('xun_marketer_commission_transaction', $updateTx);
                    $tag = "Marketer Fund Out";
    
                } else {
                    //  full marketer commission
                    $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
                    $decimal_places = $decimal_place_setting["decimal_places"];
    
                    $new_marketer_wallet_balance = $xunMarketer->get_marketer_commission_balance($business_marketer_commission_id, $wallet_type);
                    $total_new_marketer_wallet_balance = bcadd($new_marketer_wallet_balance, $amount, $decimal_places);
                    $fund_out_failed_id = $this->insertMarketerCommissionTransaction($business_marketer_commission_id, $amount, $amount_satoshi, $wallet_type, $amount, 0, $total_new_marketer_wallet_balance, '', 'Fund Out Failed');
                    
                    $update_wallet_transaction_arr = array(
                        "status" => 'failed',
                        "updated_at" => date("Y-m-d H:i:s"),
                    );
                    $db->where('id', $transaction_id);
                    $db->update('xun_wallet_transaction', $update_wallet_transaction_arr);

                    $db->where('id', $transaction_history_id);
                    $db->update($transaction_history_table, $update_wallet_transaction_arr);
                    
                    $tag = "Failed Marketer Fund Out";
                    $additional_message = "Error Message: " . $curlResponse["message_d"] . "\n";
                    $additional_message .= "Input: " . json_encode($curlParams) . "\n";
                }
    
                $message = "$type\n";
                $message .= "Business Name:".$business_name."\n";
                $message .= "Amount:" .$amount."\n";
                $message .= "Wallet Type:".$wallet_type."\n";
    
                if($additional_message){
                    $message .= $additional_message;
                }
                $message .= "Time: ".date("Y-m-d H:i:s")."\n";
    
                $thenux_params["tag"]         = $tag;
                $thenux_params["message"]     = $message;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                
            }
            else{
                $date = date("Y-m-d H:i:s");

                $db->where('reference_id', $reference_id);
                $payment_gateway_withdrawal = $db->getOne('xun_payment_gateway_withdrawal');

                $amount = $payment_gateway_withdrawal['amount'];
                $destination_address = $payment_gateway_withdrawal['recipient_address'];
                $wallet_type = $payment_gateway_withdrawal['wallet_type'];
                $miner_fee = $payment_gateway_withdrawal['miner_fee'];
                $actual_miner_fee = $payment_gateway_withdrawal['actual_miner_fee'];
                $actual_miner_fee_wallet_type = $payment_gateway_withdrawal['actual_miner_fee_wallet_type'];

                $ret_val= $this->crypto_validate_address($destination_address, $wallet_type, 'external');
                ///need to add send message when the destination  address is not valid
                if($ret_val['code'] == 1){
                    $ret_val1 = $this->crypto_validate_address($destination_address, $wallet_type, 'internal');
        
                    if($ret_val1['code'] ==0){
                        $destination_address = $ret_val1['data']['address'];
                        $transaction_type = $ret_val1['data']['addressType'];
                    }
                    else{
                        $update_wallet_transaction_arr = array(
                            "status" => 'failed',
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('id', $transaction_id);
                        $db->update('xun_wallet_transaction', $update_wallet_transaction_arr);
                    }
                }
                else{
                    $destination_address = $ret_val['data']['address'];
                    $transaction_type = $ret_val['data']['addressType'];
                }
                
                $xunWallet = new XunWallet($db);

                $txObj = new stdClass();
                $txObj->userID = $user_id;
                $txObj->address = $internal_address;
                $txObj->referenceID = '';
        
                $xunUserService = new XunUserService($db);
                $transactionToken = $xunUserService->insertCryptoTransactionToken($txObj);
        
                $transactionObj = new stdClass();
                $transactionObj->status = "pending";
                $transactionObj->transactionHash = "";
                $transactionObj->transactionToken = $transactionToken;
                $transactionObj->senderAddress = $internal_address;
                $transactionObj->recipientAddress = $destination_address;
                $transactionObj->userID = $user_id;
                $transactionObj->senderUserID = $user_id;   
                $transactionObj->recipientUserID = "";
                $transactionObj->walletType = $wallet_type;
                $transactionObj->amount = $amount;
                $transactionObj->addressType = "withdrawal";
                $transactionObj->transactionType = "send";
                $transactionObj->escrow = 0;
                $transactionObj->referenceID = $reference_id;
                $transactionObj->escrowContractAddress = '';
                $transactionObj->createdAt = $date;
                $transactionObj->updatedAt = $date;
                $transactionObj->fee = $padded_fee;
                $transactionObj->feeUnit = $feeUnit;
                $transactionObj->expiresAt = '';

                $wallet_transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

                $txHistoryObj->paymentDetailsID = $payment_details_id;
                $txHistoryObj->status = "pending";
                $txHistoryObj->transactionID = "";
                $txHistoryObj->transactionToken = $transaction_token;
                $txHistoryObj->senderAddress = $internal_address;
                $txHistoryObj->recipientAddress = $destination_address;
                $txHistoryObj->senderUserID = $user_id;
                $txHistoryObj->recipientUserID = "";
                $txHistoryObj->walletType = $wallet_type;
                $txHistoryObj->amount = $amount;
                $txHistoryObj->transactionType = "withdrawal";
                $txHistoryObj->referenceID = $reference_id;
                $txHistoryObj->createdAt = $date;
                $txHistoryObj->updatedAt = $date;
                $txHistoryObj->fee = $actual_miner_fee;
                $txHistoryObj->feeWalletType = $actual_miner_fee_wallet_type;
                // $transactionObj->fee = $final_miner_fee;
                // $transactionObj->feeWalletType = $miner_fee_wallet_type;
                // $txHistoryObj->exchangeRate = $exchange_rate;
                // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
                $txHistoryObj->type = 'in';
                $txHistoryObj->gatewayType = "BC";
    
                $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                $transaction_history_id = $transaction_history_result['transaction_history_id'];
                $transaction_history_table = $transaction_history_result['table_name'];

                $updateWalletTx = array(
                    "transaction_history_id" => $transaction_history_id,
                    "transaction_history_table" => $transaction_history_table
                );
                $xunWallet->updateWalletTransaction($wallet_transaction_id, $updateWalletTx);
        
                if($transaction_type == "external"){
                    $curlParams = array(
                        "command" => "fundOutExternal",
                        "params" => array(
                            "senderAddress" => $internal_address,
                            "receiverAddress" => $destination_address,
                            "amount" => $amount,
                            "walletType" => $wallet_type,
                            "transactionToken" => $transactionToken,
                            "walletTransactionID" => $wallet_transaction_id
                        )
                    );
                }
                else if($transaction_type == "internal"){
        
                    $curlParams = array(
                        "command" => "fundOutCompanyWallet",
                        "params" => array(
                            "senderAddress" => $internal_address,
                            "receiverAddress" => $destination_address,
                            "amount" => $amount,
                            "satoshiAmount" => $satoshi_amount,
                            "walletType" => $wallet_type,
                            "id" => $wallet_transaction_id,
                            "transactionToken" => $transactionToken,
                            "addressType" => "nuxpay_wallet",
                        ),
                    );
                }
               
                $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);
        
                $update_invoice_tx = array(
                    "reference_id" => $wallet_transaction_id
                );

                $db->where('reference_id', $miner_fee_wallet_tx_id);
                $db->where('transaction_type', 'withdrawal');
                $db->update('xun_payment_gateway_invoice_transaction', $update_invoice_tx);               
                if($curlResponse["code"] == 0){
                    $update_status = array(
                        "status" => 'failed',
                        "updated_at" => date("Y-m-d H:i:s")
                    );
        
                    $db->where('id', $reference_id);
                    $db->update('xun_request_fund_withdrawal', $update_status);
        
                    $db->where('id', $wallet_transaction_id);
                    $db->update('xun_wallet_transaction', $update_status);

                    $db->where('id', $transaction_history_id);
                    $db->update($transaction_history_table, $update_status);
        
                    $update_withdrawal_data = array(
                        "status" => 'failed',
                        "updated_at" => date("Y-m-d H:i:s")
                    );
                    $db->where('reference_id', $reference_id);
                    $db->where('transaction_type', 'manual_withdrawal');
                    $db->update('xun_payment_gateway_withdrawal', $update_withdrawal_data);

                    $update_failed_invoice_tx = array(
                        "deleted" => 1
                    );
                    $db->where('reference_id', $wallet_transaction_id);
                    $db->update('xun_payment_gateway_invoice_transaction', $update_failed_invoice_tx);
        
                    return array("code" => "0", "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/ , "developer_msg"=>$curlResponse);
                }

                $miner_fee_pool_address = $setting->systemSetting['minerFeePoolAddress'];

                $tx_obj = new stdClass();
                $tx_obj->userID = $user_id;
                $tx_obj->address = $internal_address;
    
                $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);
                $xunWallet = new XunWallet($db);
                $minerTransactionObj->status = 'pending';
                $minerTransactionObj->transactionHash = '';
                $minerTransactionObj->transactionToken = $transaction_token;
                $minerTransactionObj->senderAddress = $internal_address;
                $minerTransactionObj->recipientAddress = $miner_fee_pool_address;
                $minerTransactionObj->userID = $user_id;
                $minerTransactionObj->senderUserID = $user_id;
                $minerTransactionObj->recipientUserID = 'miner_pool';
                $minerTransactionObj->walletType = $wallet_type;
                $minerTransactionObj->amount = $miner_fee;
                $minerTransactionObj->addressType = 'miner_pool';
                $minerTransactionObj->transactionType = 'send';
                $minerTransactionObj->escrow = 0;
                $minerTransactionObj->referenceID = '';
                $minerTransactionObj->escrowContractAddress = '';
                $minerTransactionObj->createdAt = $date;
                $minerTransactionObj->updatedAt = $date;
                $minerTransactionObj->expiresAt = '';
                $minerTransactionObj->fee = '';
                $minerTransactionObj->feeUnit = '';

                $txHistoryObj->paymentDetailsID = '';
                $txHistoryObj->status = 'pending';
                $txHistoryObj->transactionID = "";
                $txHistoryObj->transactionToken = $transaction_token;
                $txHistoryObj->senderAddress = $internal_address;
                $txHistoryObj->recipientAddress = $miner_fee_pool_address;
                $txHistoryObj->senderUserID = $user_id;
                $txHistoryObj->recipientUserID = "miner_pool";
                $txHistoryObj->walletType = $wallet_type;
                $txHistoryObj->amount = $miner_fee;
                $txHistoryObj->transactionType = 'miner_pool';
                $txHistoryObj->referenceID = '';
                $txHistoryObj->createdAt = $date;
                $txHistoryObj->updatedAt = $date;
                $txHistoryObj->type = 'in';
                $txHistoryObj->gatewayType = "BC";
                $txHistoryObj->isInternal = 1;
                $txHistoryObj->fee = '';
                $txHistoryObj->feeWalletType = '';

                $miner_transaction_id = $xunWallet->insertUserWalletTransaction($minerTransactionObj);

                $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
                $miner_transaction_history_id = $transaction_history_result['transaction_history_id'];
                $miner_transaction_history_table = $transaction_history_result['table_name'];

                $updateWalletTx = array(
                    "transaction_history_id" => $miner_transaction_history_id,
                    "transaction_history_table" => $miner_transaction_history_table
                );
                $xunWallet->updateWalletTransaction($miner_transaction_id, $updateWalletTx);

                $miner_fee_satoshi = $xunCrypto->get_satoshi_amount($wallet_type, $miner_fee);
                $curlParams = array(
                    "command" => "fundOutCompanyWallet",
                    "params" => array(
                        "senderAddress" => $internal_address,
                        "receiverAddress" => $miner_fee_pool_address,
                        "amount" => $miner_fee,
                        "satoshiAmount" => $miner_fee_satoshi,
                        "walletType" => $wallet_type,
                        "id" => $miner_transaction_id,
                        "transactionToken" => $transaction_token,
                        "addressType" => "nuxpay_wallet",
                    ),
                );
           
                $minerCurlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);

                if($minerCurlResponse["code"] == 0){
                    $update_status = array(
                        "status" => 'failed',
                        "updated_at" => date("Y-m-d H:i:s")
                    );
        
                    $db->where('id', $wallet_transaction_id);
                    $db->update('xun_wallet_transaction', $update_status);

                    $db->where('id', $miner_transaction_history_id);
                    $db->update($miner_transaction_history_table, $update_status);
        
                    return array("code" => "0", "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/ , "developer_msg"=>$curlResponse);
                }

            }
               
        }
 
        public function get_live_internal_balance($address, $wallet_type, $date_from = '', $date_to = ''){

            global $config;
            $post = $this->post;

            $crypto_url = $config["cryptoWalletUrl"];

            $params = [];
            $params["address"] = $address;
            $params["languageType"] = "en";
            $params["creditSort"] = "asc";
            if($wallet_type){
                $params["walletType"] = $wallet_type; 
            }

            if($date_from){
                $params['dateFrom'] = $date_from;
            }

            if($date_to){
                $params['dateTo'] = $date_to;
            }
            
            $command = "getLiveInternalBalance";
            $crypto_params = [];
            $crypto_params["command"] = $command;
            $crypto_params["partnerSite"] = $config["cryptoBCPartnerSite"];//$config["cryptoPartnerName"];
            $crypto_params["params"] = $params;

            $crypto_result = $post->curl_post($crypto_url, $crypto_params, 0);

            if($crypto_result["code"] === 0){
                $result = $crypto_result['data'];
            }else{
                $crypto_status_msg = $crypto_result["statusMsg"];
                throw new Exception($crypto_status_msg);
            }

            return $result;
        }

        public function get_market_price($params){
            $post = $this->post;

            $command = "getExchangeMarketPrice";

            $result = $post->curl_crypto($command, $params, 2);

            return $result;
        }

        public function crypto_get_price_24h($params){
            $post = $this->post;

            $command = "getPrice24hr";
            
            $result = $post->curl_crypto($command, $params, 2);

            return $result;
        }

        public function crypto_update_order_status($params){
            $db = $this->db;
    
            $swap_id = $params['swap_id'];
            $order_id = $params['order_id'];
            $order_status = $params['order_status'];
            $transaction_hash = $params['transaction_id'];
            $swap_wallet_transaction_id = $params['swap_wallet_transaction_id'];

            if($swap_id == ''){
                return array('code' => 0, 'message' => "FAILED", "message_d" => $this->get_translation_message('E00638') /* Swap ID cannot be empty.*/);
            }

            // if($order_id == ''){
            //     return array('code' => 0, 'message' => "FAILED", "message_d" => $this->get_translation_message('E00639') /* Order ID cannot be empty.*/);
            // }

            if($order_status == ''){
                return array('code' => 0, 'message' => "FAILED", "message_d" => $this->get_translation_message('E00640') /* Order Status cannot be empty.*/);
            }
            
            if($transaction_hash == ''){
                return array('code' => 0, 'message' => 'FAILED', 'message_d' => $this->get_translation_message('E00373') /*Transaction hash cannot be empty.*/);
            }
    
            $db->where('reference_id', $swap_id);
            $db->where('status', 'processing');
            $swap_history = $db->getOne('xun_swap_history');

            if(!$swap_history){
                return array('code' => 0, 'message' => "FAILED", "message_d" => $this->get_translation_message('E00637') /* Swap Order not found.*/);
            }
    
            $updateData= array(
                "order_id" => $order_id ? $order_id : '',
                "order_status" => $order_status,
                "to_tx_id" => $swap_wallet_transaction_id,
            );

            $db->where('reference_id', $swap_id);
            $updated = $db->update('xun_swap_history', $updateData);

            if(!$updated){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }

            $db->where('id', $swap_wallet_transaction_id);
            $wallet_tx_data = $db->getOne('xun_wallet_transaction','transaction_history_table, transaction_history_id');

            if(!$wallet_tx_data){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Wallet Transaction not found.', "developer_msg" => $db->getLastQuery());
            }

            $transaction_history_id = $wallet_tx_data['transaction_history_id'];
            $transaction_history_table = $wallet_tx_data['transaction_history_table'];

            $updateData = array(
                "transaction_hash" => $transaction_hash,
                "status" => 'wallet_success'
            );

            $db->where('id', $swap_wallet_transaction_id);
            $db->where('status', 'pending');
            $updated = $db->update('xun_wallet_transaction', $updateData);

            if(!$updated){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }

            $updateTransactionData = array(
                "transaction_id" => $transaction_hash,
                "status" => 'wallet_success',
            );

            $db->where('id', $transaction_history_id);
            $db->update($transaction_history_table, $updateTransactionData);

            return array('code' => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00376') /* Update Order Status Successful. */);
        }

        public function get_miner_fee_estimation($params) {
            $post = $this->post;

            $command = "estimateMinerFee";

            $result = $post->curl_crypto($command, $params);

            return $result;
        }

    }

?>
