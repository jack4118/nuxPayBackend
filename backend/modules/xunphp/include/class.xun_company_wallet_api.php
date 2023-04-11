<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file contains the functions for wallet fund out
 * Date  13/07/2019.
 **/
class XunCompanyWalletAPI
{

    public function __construct($db, $setting, $general, $post)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
        $this->post = $post;
    }

    public function freecoinWalletServerCallback($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $recordID = trim($params["record_id"]);
        $transactionHash = trim($params["transaction_hash"]);

        if ($transactionHash == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction hash cannot be empty");
        }

        if ($recordID == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Record ID cannot be empty");
        }

        $transactionStatus = "wallet_success";

        $db->where("transaction_hash", $transactionHash);
        $cryptoTransactionRecord = $db->getOne("xun_crypto_transaction_hash");

        if ($cryptoTransactionRecord) {
            $freecoinWalletAddress = $setting->systemSetting["freecoinWalletAddress"];

            $senderAddress = $cryptoTransactionRecord["sender_address"];

            if ($senderAddress == $freecoinWalletAddress) {
                $transactionStatus = "completed";
                $amount = $cryptoTransactionRecord["amount"];
                $walletType = $cryptoTransactionRecord["wallet_type"];
            }
        }

        $xunFreecoinPayout = new XunFreecoinPayout($db, $setting, $general);
        $returnVal = $xunFreecoinPayout->walletServerCallbackUpdate($recordID, $transactionHash, $transactionStatus, $amount, $walletType);

        if ($returnVal) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
        } else {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid record ID");
        }

    }

    public function createPrepaidWallet($params)
    {
        global $xunBusiness, $xunXmpp, $post, $xunCrypto;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $businessID = trim($params["business_id"]);
        $walletType = trim($params["wallet_type"]);
        $apiKey = trim($params["api_key"]);

        if ($businessID == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00002'][$language]);
        }

        if ($apiKey == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00086'][$language]/** Api key cannot be empty. */);
        }

        $xunBusinessService = new XunBusinessService($db);
        $business = $xunBusinessService->getBusinessByBusinessID($businessID);

        if (!$business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00032'][$language]/*Invalid business id.*/);
        }

        if (!$xunBusiness->validate_api_key($businessID, $apiKey)) {
            $crypto_api_key_validation = $xunCrypto->validate_crypto_api_key($apiKey, $businessID);

            if(isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0){
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $translations['E00148'][$language]/*Invalid Apikey.*/);
            }
        }
        return $this->get_business_prepaid_address($params);
    }

    public function get_business_prepaid_address($params){
        global $xunBusiness, $xunXmpp, $post, $xunCrypto;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $businessID = trim($params["business_id"]);
        $walletType = trim($params["wallet_type"]);
        $addressType = trim($params["address_type"]);
        $addressType = $addressType ?: "prepaid";

        if ($businessID == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00002'][$language]);
        }

        if ($walletType == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00150']/*Wallet Type cannot be empty*/);
        }

        if (!in_array($addressType, ['prepaid', 'prepaid_payment_gateway'])){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid address type.");
        }

        $xunBusinessService = new XunBusinessService($db);
        // $business = $xunBusinessService->getBusinessByBusinessID($businessID);

        // if (!$business) {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00032'][$language]/*Invalid business id.*/);
        // }

        $this->xunBusinessService = $xunBusinessService;
        $businessPrepaidWallet = $xunBusinessService->getActiveAddressByUserIDandType($businessID, $addressType);

        if ($businessPrepaidWallet) {
            $internalAddress = $businessPrepaidWallet["address"];

            $addressObj = new stdClass();
            $addressObj->walletType = $walletType;
            $addressObj->internalAddress = $internalAddress;
            try {
                $externalAddress = $this->getPrepaidWalletExternalAddress($addressObj);
            } catch (exception $e) {
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $e->getMessage(),
                );
            }
        } else {
            $walletResponse = $xunCompanyWallet->createUserServerWallet($businessID, $addressType, $walletType);
            if ($walletResponse["code"] == 1) {
                $walletData = $walletResponse["data"];
                $walletData = $walletData;
                $address = $walletData["address"];
                $externalAddress = $walletData["externalAddress"];

                $userObj = new stdClass();
                $userObj->userID = $businessID;
                $userObj->addressType = $addressType;
                $userObj->internalAddress = $address;
                $userObj->externalAddress = $externalAddress;
                $userObj->walletType = $walletType;

                if ($externalAddress) {
                    $res = $xunBusinessService->insertUserAddressAndExternalAddress($userObj);
                } else {
                    $xunBusinessService->setActiveWalletAddress($userObj);
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => $translations['E00141'][$language], /**  Internal server error. Please try again later. */
                    );
                }
            } else {
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $translations['E00141'][$language], /**  Internal server error. Please try again later. */
                );
            }
        }

        $returnData = array(
            "address" => $externalAddress,
            "wallet_type" => $walletType,
        );
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "data" => $returnData);
    }

    //  similar as prepaid address
    public function getUserCompanyAddress($params){
        global $xunBusiness, $xunXmpp, $post, $xunCrypto;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);

        $userID = trim($params["user_id"]);
        $walletType = trim($params["wallet_type"]); // optional; if no walletType, wont generate external address
        $addressType = trim($params["address_type"]);
        $addressType = $addressType ?: "prepaid";

        if ($userID == '') {
            return $general->getResponseArr(0, 'E00006'/*ID cannot be empty.*/);
        }

        // if ($walletType == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00150']/*Wallet Type cannot be empty*/);
        // }

        if (!in_array($addressType, ['prepaid', 'prepaid_payment_gateway', 'credit'])){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid address type.");
        }

        $xunUserService = new XunUserService($db);
        $this->xunUserService = $xunUserService;
        $userCompanyWallet = $xunUserService->getActiveAddressByUserIDandType($userID, $addressType);

        $isNewAddress = 1;

        //  generate if already exist, else get from db
        if ($userCompanyWallet) {
            $internalAddress = $userCompanyWallet["address"];
            $addressId = $userCompanyWallet["id"];
            $isNewAddress = 0;

            if(!empty($walletType)){
                $addressObj = new stdClass();
                $addressObj->walletType = $walletType;
                $addressObj->internalAddress = $internalAddress;
                try {
                    $externalAddress = $this->getPrepaidWalletExternalAddress($addressObj);
                } catch (exception $e) {
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => $e->getMessage(),
                    );
                }
            }
        } else {
            $walletResponse = $xunCompanyWallet->createUserServerWallet($userID, $addressType, $walletType);
            if ($walletResponse["code"] == 1) {
                $walletData = $walletResponse["data"];
                $walletData = $walletData;
                $internalAddress = $walletData["address"];
                $externalAddress = $walletData["externalAddress"];

                $userObj = new stdClass();
                $userObj->userID = $userID;
                $userObj->addressType = $addressType;
                $userObj->internalAddress = $internalAddress;
                $userObj->externalAddress = $externalAddress;
                $userObj->walletType = $walletType;

                if ($externalAddress) {
                    $res = $xunUserService->insertUserAddressAndExternalAddress($userObj);
                } else {
                    $setAddressRes = $xunUserService->setActiveWalletAddress($userObj);
                    $addressId = $setAddressRes['id'];

                    if(!empty($walletType)){
                        return $general->getResponseArr(0, 'E00141' /**  Internal server error. Please try again later. */);
                    }
                }
            } else {
                return $general->getResponseArr(0, 'E00141' /**  Internal server error. Please try again later. */);
            }
        }

        $returnData = array(
            "internal_address" => $internalAddress,
            "is_new_address" => $isNewAddress,
            "address_id" => $addressId
        );

        if(!empty($walletType)){
            $returnData["external_address"] = $externalAddress;
            $returnData["wallet_type"] = $walletType;
        }

        return $general->getResponseArr(1, '', "SUCCESS", $returnData);
    }

    public function createPrepaidWalletCallback($params)
    {
        $db = $this->db;

        $userID = trim($params["user_id"]);
        $address = trim($params["address"]);

        if ($userID == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => "User ID is required.");
        }

        if ($address == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Address is required.");
        }

        $userObj = new stdClass();
        $userObj->userID = $userID;
        $userObj->addressType = "prepaid";
        $userObj->internalAddress = $address;

        $xunUserService = new XunUserService($db);

        $userData = $xunUserService->getUserByID($userID);

        if (!$userData) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid id.");
        }

        $res = $xunUserService->setActiveWalletAddress($userObj);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success.");
    }

    public function prepaidWalletServerCallback($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        /**
         * check if transaction hash table has record -> insert if no -> status: pending
         * if transaction hash has record, update to success
         */

        $transactionHash = trim($params["transaction_hash"]);
        $transactionToken = trim($params["transaction_token"]);

        if ($transactionHash == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction hash cannot be empty");
        }

        if ($transactionToken == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction token cannot be empty");
        }

        $transactionStatus = "wallet_success";

        $db->where("transaction_hash", $transactionHash);
        $cryptoTransactionRecord = $db->getOne("xun_crypto_transaction_hash");

        if ($cryptoTransactionRecord) {
            //  TODO::
            //  check if sender address is prepaid address

            $transactionStatus = "completed";
            $amount = $cryptoTransactionRecord["amount"];
            $walletType = $cryptoTransactionRecord["wallet_type"];
            $senderAddress = $cryptoTransactionRecord["sender_address"];
            $recipientAddress = $cryptoTransactionRecord["recipient_address"];
        }

        $addressType = "prepaid";
        $transactionType = "send";

        $transactionObj = new stdClass();
        $transactionObj->status = $transactionStatus;
        $transactionObj->transactionHash = $transactionHash;
        $transactionObj->transactionToken = $transactionToken;
        $transactionObj->senderAddress = $senderAddress ? $senderAddress : '';
        $transactionObj->recipientAddress = $recipientAddress ? $recipientAddress : '';
        $transactionObj->userID = $userID ? $userID : '';
        $transactionObj->walletType = $walletType ? $walletType : '';
        $transactionObj->amount = $amount ? $amount : '';
        $transactionObj->addressType = $addressType;
        $transactionObj->transactionType = $transactionType;

        $xunWallet = new XunWallet($db);
        $res = $xunWallet->walletServerCallbackUpdate($transactionObj);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success.");
    }

    public function cryptoCallbackAndUpdateBCStage($sender, $recipient, $hash, $type) {

        //TRANSACTION SUCCESS OR FAILED
        $db = $this->db;
        $post = $this->post;

        $db->where("transaction_type", $type);
        $db->where("transaction_hash", $hash);
        $db->where("sender_address", $sender);
        $db->where("recipient_address", $recipient);
        $detail = $db->getOne("xun_wallet_transaction");

        if($detail) {
            $address_type = $detail['address_type'];
            $reference_id = $detail['reference_id'];

            if( ($address_type=="marketer" || $address_type=="company_acc") && $reference_id > 0 ) {

                $db->where("a.id", $reference_id);
                $db->join("xun_wallet_transaction t", "a.wallet_transaction_id=t.id", "INNER");
                $db->join("xun_marketplace_currencies m", "m.currency_id=t.wallet_type", "INNER");
                $transactionDetail = $db->getOne("xun_service_charge_audit a", "t.transaction_hash, m.symbol");

                if($transactionDetail) {

                    $symbol = strtoupper($transactionDetail['symbol']);
                    $transaction_hash = $transactionDetail['transaction_hash'];

                    //CROSS CHECK RECORD
                    $db->where("reference_id", $reference_id);
                    $walletDetail = $db->get("xun_wallet_transaction");

                    if($walletDetail) {
                        $failedFlag = false;
                        $completedFlag = false;
                        $pendingFlag = false;
                        $failedReason = "";
                        foreach($walletDetail as $wDetail) {
                            $wStatus = $wDetail['status'];
                            if($wStatus=="failed") {
                                $failedFlag = true;
                                $failedReason .= " ".$wDetail['address_type']."(".$wDetail['id'].")";
                            } else if($wStatus=="completed") {
                                $completedFlag = true;
                            } else {
                                $pendingFlag = true;
                            }
                        }

                        if(!$pendingFlag && $failedFlag) {

                            //stage 6 NuxPay Fund out to marketer
                            $paramsArray = array('transactionId' => $transaction_hash, 
                                                    'creditUnit' => $symbol, 
                                                    'stage' => "6",
                                                    'status' => "failed",
                                                    'reason' => $failedReason
                                                );

                            $post->curl_crypto("insertStageNuxPay", $paramsArray);


                        } else if(!$pendingFlag && $completedFlag) {

                            //stage 6 NuxPay Fund out to marketer
                            $paramsArray = array('transactionId' => $transaction_hash, 
                                                    'creditUnit' => $symbol, 
                                                    'stage' => "6",
                                                    'status' => "success",
                                                    'reason' => $failedReason
                                                );

                            $post->curl_crypto("insertStageNuxPay", $paramsArray);

                        }
                    }


                }


            }
        }
    }

    public function updateWalletTransaction($params)
    {
        global $general, $xunCrypto, $setting, $xun_numbers, $xunPaymentGateway;
        $db = $this->db;

        $senderAddress = trim($params["address"]);
        $walletTransactionID = trim($params["walletTransactionID"]);
        $transactionHash = trim($params["transactionHash"]); //internal
        $transactionHistoryTable = $params['transactionHistoryTable']  ? trim($params['transactionHistoryTable']) : '' ;
        $transactionHistoryID = $params['transactionHistoryID'] ? trim($params['transactionHistoryID']) : '';

        if ($transactionHash == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction hash cannot be empty");
        }

        if ($walletTransactionID == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet transaction ID cannot be empty");
        }

        $transactionStatus = "wallet_success";

        $db->where("transaction_hash", $transactionHash);
        $cryptoTransactionRecord = $db->getOne("xun_crypto_transaction_hash");

        // wentin test // 
        $ex_transaction = $cryptoTransactionRecord["ex_transaction_hash"];
        $ex_transaction != "";
        if ($ex_transaction) {
            $target = "external";
        } else {
            $target = "internal";
        }

        $cryptoSenderAddress = $cryptoTransactionRecord["sender_address"];
        $exchangeRate = $cryptoTransactionRecord['exchange_rate'] ? $cryptoTransactionRecord['exchange_rate'] : '0.00000000';
        $bcReferenceID = $cryptoTransactionRecord['bc_reference_id'];
        if ($cryptoTransactionRecord) {
            $status = $cryptoTransactionRecord['status'];

            $transactionStatus = $status == "confirmed" ? "completed" : $status;

        }

        $xunWallet = new XunWallet($db);

        $transactionRecord = $xunWallet->getWalletTransactionByID($walletTransactionID);

        if (!$transactionRecord) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid record ID");
        }
        if ($transactionRecord["status"] == "completed") {
        
            $exchange_rate = $transactionRecord['exchange_rate'];
            $miner_fee_usd_amount = bcmul($transactionRecord['fee'], $transactionRecord['miner_fee_exchange_rate'], 18);
            $miner_fee = bcmul($miner_fee_usd_amount, $exchange_rate, 18);
            $update_withdrawal = array(
                "amount" => $transactionRecord['amount'],
                "miner_fee" => $miner_fee,
                "status" => $transactionRecord["status"] == 'completed' ? 'success' : $transactionRecord["status"],
                "transaction_hash" => $transactionHash,
                "updated_at" => date("Y-m-d H:i:s"),
            );
            $db->where('reference_id', $transactionRecord['reference_id']);
            $db->where('transaction_type', 'manual_withdrawal');
            $updated_pg_withdrawal = $db->update('xun_payment_gateway_withdrawal', $update_withdrawal);
            
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
        }

        $address_type = $transactionRecord['address_type'];
        $wallet_transaction_id = $transactionRecord["id"];
        $reference_id = $transactionRecord['reference_id'];
        $message = $transactionRecord['message'];
        $recipient_address = $transactionRecord['recipient_address'];
        $transaction_history_table = $transactionRecord['transaction_history_table'];
        $transaction_history_id = $transactionRecord['transaction_history_id'];

        $transactionObj = new stdClass();
        $transactionObj->id = $walletTransactionID;
        $transactionObj->status = $transactionStatus;
        $transactionObj->transactionHash = $transactionHash;
        $transactionObj->exchangeRate = $exchangeRate;
        $transactionObj->bcReferenceID = $bcReferenceID;
        if($transaction_history_table && $transaction_history_id){
            $transactionObj->transactionHistoryTable = $transaction_history_table;
            $transactionObj->transactionHistoryID = $transaction_history_id;
        }
       

        $res = $xunWallet->walletServerCallbackUpdateTxHashAndStatus($transactionObj);

        if ($res) {
            $padded_fee = $transactionRecord['fee'];
            $feeUnit = $transactionRecord['fee_unit'];
            $amount = $transactionRecord['amount'];
            $walletType = $transactionRecord['wallet_type'];

            $db->where('reference_id', $walletTransactionID);
            $marketer_transaction_commission = $db->getOne('xun_marketer_commission_transaction');

            $business_marketer_commission_id = $marketer_transaction_commission['id'];

            if ($transactionStatus == "completed") {
                $this->handleWalletServerCallback($transactionRecord);

                if ($address_type == 'marketer') {

                    $tag = "Marketer Fund Out";
                    $message = "Business Marketer Commission ID: " . $business_marketer_commission_id . "\n";
                    $message .= "Amount:" . $amount . "\n";
                    $message .= "Wallet Type:" . $walletType . "\n";
                    $message .= "Miner Fee: " . $padded_fee . " " . $feeUnit . "\n";
                    $message .= "Status: " . $transactionStatus . "\n";
                    $message .= "Time: " . date("Y-m-d H:i:s") . "\n";

                    $thenux_params["tag"] = $tag;
                    $thenux_params["message"] = $message;
                    $thenux_params["mobile_list"] = $xun_numbers;
                    $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

                }

                if($address_type == 'nuxpay_wallet'){
                    $balance = $xunPaymentGateway->get_user_balance($business_id, $walletType);
                    $new_balance = bcadd($balance, $amount,8);
                    $satoshi_amount = $xunCrypto->get_satoshi_amount($walletType, $amount);
                    if($message = "redeem_code"){
                        $db->where('id', $reference_id);
                        $db->where('status', 'activated');
                        $send_fund = $db->getOne('xun_payment_gateway_send_fund');
                        
                        if(!$send_fund){
                            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
                        }

                        $recipient_mobile_number = $send_fund['recipient_mobile_number'];
                        $recipient_email_address = $send_fund['recipient_email_address'];
                        $business_id = $send_fund['business_id'];
                        $send_fund_id = $send_fund['id'];

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
                            "status" => $status == 'confirmed' ? 'success' : $status,
                            "redeemed_at" => date("Y-m-d H:i:s"),
                            "updated_at" => date("Y-m-d H:i:s")
                        );

                        $db->where('id', $reference_id);
                        $updated = $db->update('xun_payment_gateway_send_fund', $update_status);

         
                        $insertFundIn = array(
                            "business_id" => $receiver_user_id,

                            // //wentin// test
                            "transaction_id" => $transactionHash ?: $ex_transaction,
                            "transaction_target" => $target,
                            //
                            "sender_address" => $senderAddress,
                            "receiver_address" => $recipient_address,
                            "amount" => $amount,
                            "amount_receive" => $amount,
                            "transaction_fee" => '0',
                            "miner_fee" => '0',
                            "wallet_type" => strtolower($walletType),
                            "exchange_rate" => $exchangeRate,
                            "type" => "redeem_code",
                            "transaction_type" => "blockchain",
                            "status" => $transactionStatus,
                            "created_at" => date("Y-m-d H:i:s")
                        );
                        
                        $fund_in_id = $db->insert('xun_payment_gateway_fund_in', $insertFundIn);

                      
                        $insertData = array(
                            "business_id" => $receiver_user_id,
                            "sender_address" => $senderAddress,
                            "recipient_address" => $recipient_address,
                            "amount" => $amount,
                            "amount_satoshi" => $satoshi_amount,
                            "wallet_type" => strtolower($walletType),
                            "credit" => $amount,
                            "debit" => '0',
                            "balance" => $new_balance,
                            "reference_id" => $send_fund_id,
                            "transaction_type" => "redeem_code",
                            "created_at" => date("Y-m-d H:i:s"),
                            "gw_type" => "BC",
                            "transaction_hash" => $transactionHash
                        );

                     
                        $invoice_tx_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertData);

                        $receiver_name = $receiver_user_data['nickname'];
                        $sender_name = $sender_user_data['nickname'];
                        $tag = "Redeem PIN";
                        $message = "Business Name: ".$sender_name."\n";
                        $message .= "Redeemed By: ".$receiver_name."\n";
                        $message .= "Tx Hash: ".$transaction_hash."\n";
                        $message .= "Amount:" .$amount."\n";
                        $message .= "Wallet Type:".$walletType."\n";
                        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                        $thenux_params["tag"]         = $tag;
                        $thenux_params["message"]     = $message;
                        $thenux_params["mobile_list"] = $xun_numbers;
                        $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

                    } else if($message == 'send_fund'){
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


                                    // //wentin// test
                                    "transaction_target" => $target,
                                    "transaction_id" => $transactionHash ?: $ex_transaction,
                                    //
                                    "sender_address" => $senderAddress,
                                    "receiver_address" => $recipient_address,
                                    "amount" => $amount,
                                    "amount_receive" => $amount,
                                    "transaction_fee" => '0',
                                    "miner_fee" => '0',
                                    "wallet_type" => strtolower($walletType),
                                    "exchange_rate" => $exchange_rate,
                                    "type" => "receive_fund",
                                    "transaction_type" => "blockchain",
                                    "status" => $transactionStatus,
                                    "created_at" => date("Y-m-d H:i:s")
                                );
                                
                                $fund_in_id = $db->insert('xun_payment_gateway_fund_in', $insertFundIn);

                                $insertData = array(
                                    "business_id" => $user_id,
                                    "sender_address" => $senderAddress,
                                    "recipient_address" => $recipient_address,
                                    "amount" => $amount,
                                    "amount_satoshi" => $satoshi_amount,
                                    "wallet_type" => strtolower($walletType),
                                    "credit" => $padded_amount,
                                    "debit" => '0',
                                    "balance" => $new_balance,
                                    "reference_id" => $reference_id,
                                    "transaction_type" => "receive_fund",
                                    "created_at" => date("Y-m-d H:i:s"),
                                    "gw_type" => "BC",
                                    "transaction_hash" => $transactionHash
                                );
            
                                $request_fund_tx_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertData);
                            }
                        }
   
                        
                    }
                    else{
                        $recipient_address = $transactionRecord['recipient_address'];
                        $db->where('address', $recipient_address);
                        $db->where('active', 1);
                        $user_internal_address = $db->getOne('xun_crypto_user_address');
    
                        $internal_address = $user_internal_address['address'];
                        $user_id = $user_internal_address['user_id'];
    
                        $db->where('id', $user_id);
                        $xun_user = $db->getOne('xun_user');
    
                        $user_type = $xun_user['type'];
                        
                        
                        $wallet_transaction_id = $transactionRecord["id"];
                        $reference_id = $transactionRecord['reference_id'];
    
                        $db->where('reference_id', $wallet_transaction_id);
                        $db->where('transaction_type', 'refund_fee');
                        $db->where('deleted', 0);
                        $refund_fee_transaction = $db->getOne('xun_payment_gateway_invoice_transaction');
    
                        if($refund_fee_transaction){
                          
                            $refund_fee_params = array(
                                "pg_fund_in_id"=> $reference_id,
                                "wallet_transaction_id" => $wallet_transaction_id,
                                "transactionHash" => $transactionHash,
                                'referenceID' => '',
                                'status'  => $transactionStatus,
                                
                            );
                            $xunFreecoinPayout = new XunFreecoinPayout($db, $setting, $general);
                            $returnVal = $xunFreecoinPayout->update_fund_in_transaction($refund_fee_params);
                        
                        }
                        else{
                            $db->where('reference_id', $wallet_transaction_id);
                            $db->where('type', 'fund_in');
                            $db->where('reference_table', 'xun_wallet_transaction');
                            $miner_fee_transaction = $db->getOne('xun_miner_fee_transaction');
    
                            if(!$miner_fee_transaction){
                                return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
                            }
    
                            $miner_fee_transaction_id = $miner_fee_transaction['id'];
    
                            $tx_data = array(
                                "miner_fee_transaction_id" => $miner_fee_transaction_id,
                                "miner_fee_reference_id" => $reference_id,
                                "internal_address" => $internal_address,
                                "user_id" => $user_id
                            );
                            $xunCrypto->process_withdrawal($tx_data);
                        }
    
                    }
                    
                }

                if($address_type == 'redeem_code'){
                   
                    $wallet_transaction_id = $transactionRecord["id"];
                    $reference_id = $transactionRecord['reference_id'];
                    $message = $transactionRecord['message'];

                    if($message == 'send_fund'){
                        $db->where('id', $reference_id);
                        $send_fund = $db->getOne('xun_payment_gateway_send_fund');
                        if(!$send_fund){
                            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
                        }

                        if($send_fund['status'] != 'pending' || $send_fund['status'] == 'activated'){
                            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
                        }

                        $recipient_mobile_number = $send_fund['recipient_mobile_number'];
                        $recipient_email_address = $send_fund['recipient_email_address'];
                        $user_id = $send_fund['business_id'];

                        $db->where('id', $user_id);
                        $sender_user_data = $db->getOne('xun_user');

                        $source = $sender_user_data['register_site'];

                        if($recipient_email_address){
                            $db->where('username', $recipient_email_address);
                        }
                        
                        if($recipient_mobile_number){
                            $db->where('email', $recipient_mobile_number);
                        }
                        $db->where('register_site', $source);
                        $receiver_user_data = $db->getOne('xun_user');

                        $receiver_user_id = $receiver_user_data['id'];
                        $update_status = array(
                            "status" => $status == 'confirmed' ? 'activated' : 'failed',
                            "updated_at" => date("Y-m-d H:i:s")
                        );

                        $db->where('id', $reference_id);
                        $updated = $db->update('xun_payment_gateway_send_fund', $update_status);


                        $insertWithdrawal = array(
                            "reference_id" => '0',
                            "business_id" => $user_id,
                            "sender_address" => $senderAddress,
                            "recipient_address" => $recipient_address,
                            "amount" => $amount,
                            "amount_receive" => $amount,
                            "transaction_fee" => '0',
                            "miner_fee" => '0',
                            "transaction_hash" => $transactionHash,
                            "wallet_type" => strtolower($walletType),
                            "status" => $status == 'confirmed' ? 'success' : $status,
                            "transaction_type" => 'send_fund',
                            "created_at" => date("Y-m-d H:i:s"),
                            "updated_at" => date("Y-m-d H:i:s")
                        );

                        $db->insert('xun_payment_gateway_withdrawal', $insertWithdrawal);


                    }
                }
            }

            if ($transactionStatus == 'failed' && $address_type == 'marketer') {
                $thenux_params["tag"] = $tag;
                $thenux_params["message"] = $message;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
            }

            // if ($transactionRecord["address_type"] == "reward") {
            //     $this->handleRewardCallback($transactionRecord);
            // }
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
        } else {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid record ID");
        }
    }

    public function getEscrowAgentAddress($params)
    {
        $setting = $this->setting;
        $db = $this->db;

        $escrowAgentAddress = $setting->systemSetting["walletEscrowAgentAddress"];

        $returnData = array("escrow_agent_address" => $escrowAgentAddress);
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Escrow agent address.", "data" => $returnData);
    }

    private function getPrepaidWalletExternalAddressPost($obj)
    {
        global $config;
        $post = $this->post;

        $command = "getWalletExternalAddress";

        $url_string = $config["giftCodeUrl"];
        $params = array(
            "address" => $obj->internalAddress,
            "walletType" => $obj->walletType,
        );

        $cryptoParams = array(
            "command" => $command,
            "params" => $params,
        );

        $postReturn = $post->curl_post($url_string, $cryptoParams, 0, 1);

        return $postReturn;
    }

    private function getPrepaidWalletExternalAddress($obj)
    {
        $xunBusinessService = $this->xunBusinessService;

        $externalAddressData = $xunBusinessService->getCryptoExternalAddressByInternalAddressAndWalletType($obj);

        if ($externalAddressData) {
            $externalAddress = $externalAddressData["external_address"];
            return $externalAddress;
        } else {
            $postResponse = $this->getPrepaidWalletExternalAddressPost($obj);
            if ($postResponse["code"] == 1) {
                $postData = $postResponse["data"];
                $externalAddress = $postData["externalAddress"];

                if (!empty($externalAddress)) {
                    $obj->externalAddress = $externalAddress;
                    $xunBusinessService->insertCryptoExternalAddress($obj);
                }
                return $externalAddress;
            } else {
                $statusMsg = $postResponse["cryptoResponse"]["statusMsg"];
                throw new Exception($statusMsg);
            }
        }
    }

    public function handleWalletServerCallback($walletTransactionRecord)
    {
        global $xunCrypto, $xunServiceCharge;
        $transactionAddressType = $walletTransactionRecord["address_type"];

        switch ($transactionAddressType) {
            case "company_pool":
                // * params: [sender_address, wallet_type, amount, user_id]
                $service_charge_transaction_id = $walletTransactionRecord["reference_id"];
                $service_charge_record = $xunServiceCharge->get_service_charge_by_id($service_charge_transaction_id);
                $service_charge_user_id = $service_charge_record["user_id"];
                $newParams = [];
                $newParams["sender_address"] = $walletTransactionRecord["recipient_address"];
                $newParams["wallet_type"] = $walletTransactionRecord["wallet_type"];
                $newParams["amount"] = $walletTransactionRecord["amount"];
                $newParams["user_id"] = $service_charge_user_id;

                $xunCrypto->process_fund_in_to_company_pool_wallet($newParams, $service_charge_transaction_id);
                break;

            case "company_acc":
                break;

            // case "pay":
            //     $xunCrypto->process_pay_refund_transction($walletTransactionRecord);
            //     break;
            // case "story":
            //     $xunCrypto->process_story_refund_transaction($walletTransactionRecord);
            //     break;

            default:
                break;
        }

    }

    public function handleRewardCallback($walletTransactionRecord)
    {
        global $xunPhoneApprove;

        $db = $this->db;

        $walletTransactionID = $walletTransactionRecord["id"];

        $db->where("b.id", $walletTransactionID);
        $db->join("xun_wallet_transaction b", "a.wallet_transaction_id=b.id");
        $db->join("xun_business_reward_transaction c", "a.reward_transaction_id=c.id");
        $businessRewardTransaction = $db->getOne("xun_business_reward_transaction_details a", "b.transaction_hash, b.recipient_user_id, c.*");

        if (!$businessRewardTransaction) {
            return;
        }

        if ($businessRewardTransaction["batch_id"] != '') {
            $recipientUserId = $businessRewardTransaction["recipient_user_id"];
            $db->where("id", $recipientUserId);
            $user = $db->getOne("xun_user");
            $receiverUsername = $user["username"];
            $callbackParams = array(
                "batch_id" => $businessRewardTransaction["batch_id"],
                "transaction_hash" => $businessRewardTransaction["transaction_hash"],
                "status" => "success",
                "mobile" => $receiverUsername,
                "message" => "",
            );
            $business_id = $businessRewardTransaction["business_id"];

            $xunPhoneApprove->request_transaction_callback($business_id, $callbackParams);
        }

    }
}
