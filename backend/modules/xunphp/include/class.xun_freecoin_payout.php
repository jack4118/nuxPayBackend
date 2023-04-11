<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/

class XunFreecoinPayout
{

    public function __construct($db, $setting, $general)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
    }

    public function getFreecoinTransactionByUser($userID)
    {
        $db = $this->db;

        $db->where("user_id", $userID);
        $record = $db->getOne("xun_freecoin_payout_transaction");

        return $record;
    }

    public function getFreecoinTransactionByID($recordID)
    {
        $db = $this->db;

        $db->where("id", $recordID);
        $record = $db->getOne("xun_freecoin_payout_transaction");

        return $record;
    }

    public function fundOutFreecoin($params)
    {
        $db = $this->db;
        $setting = $this->setting;

        $date = date("Y-m-d H:i:s");

        $userID = $params["user_id"];
        $address = $params["address"];

        $userTransactionRecord = $this->getFreecoinTransactionByUser($userID);

        if (!is_null($userTransactionRecord)) {
            return;
        }

        $freecoinAmount = $setting->systemSetting["freecoinAmount"];
        $freecoinWalletType = $setting->systemSetting["freecoinWalletType"];

        if (bccomp((string) $freecoinAmount, "0", 8) <= 0) {
            return;
        }

        $status = "pending";

        $insertData = array(
            "user_id" => $userID,
            "address" => $address,
            "amount" => $freecoinAmount,
            "wallet_type" => $freecoinWalletType,
            "status" => $status,
            "transaction_hash" => "",
            "created_at" => $date,
            "updated_at" => $date,
        );

        $row_id = $db->insert("xun_freecoin_payout_transaction", $insertData);
        if ($row_id) {
            //  fund out
            $walletResponse = $this->fundOutToWalletServer($address, $freecoinAmount, $freecoinWalletType, $row_id);
            // $this->updateWalletResponse($row_id, $walletResponse);
        } else {
            // print_r($db);
        }
        return $row_id;
    }

    private function fundOutToWalletServer($receiverAddress, $amount, $walletType, $recordID)
    {
        global $post;
        $db = $this->db;
        $setting = $this->setting;

        //  wallet server address, amount, wallettype, record id, destination address
        // $xunWallet
        $walletServer = "freecoin";
        $postParams = [];
        $postParams["recordID"] = $recordID;
        $postParams["receiverAddress"] = $receiverAddress;
        $postParams["amount"] = $amount;
        $postParams["walletType"] = $walletType;
        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
        $walletResponse = $xunCompanyWallet->fundOut($walletServer, $postParams);
        return $walletResponse;
    }
    private function updateWalletResponse($recordID, $response)
    {
        $db = $this->db;

        $updateData = [];
        if ($response["code"] == 1) {
            $status = "wallet_success";
        } else {
            $status = "wallet_error";
        }
        $updateData["status"] = $status;
        $updateData["updated_at"] = date("Y-m-d H:i:s");
        $db->where("id", $recordID);
        $db->update("xun_freecoin_payout_transaction", $updateData);
    }

    public function getFreecoinTransactionByTransactionHash($transactionHash)
    {
        $db = $this->db;

        $db->where("transaction_hash", $transactionHash);
        $freecoinTransaction = $db->getOne("xun_freecoin_payout_transaction");

        return $freecoinTransaction;
    }

    public function cryptoCallbackUpdate($transactionHash)
    {
        $db = $this->db;

        $freecoinTransaction = $this->getFreecoinTransactionByTransactionHash($transactionHash);
        if ($freecoinTransaction) {
            // update transaction status to complete
            $date = date("Y-m-d H:i:s");
            $updateData = [];
            $updateData["status"] = "completed";
            $updateData["updated_at"] = $date;

            $db->where("id", $freecoinTransaction["id"]);
            $db->update("xun_freecoin_payout_transaction", $updateData);
        }
    }

    public function walletServerCallbackUpdate($recordID, $transactionHash, $status, $amount, $walletType)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $userTransactionRecord = $this->getFreecoinTransactionByID($recordID);

        if ($userTransactionRecord) {
            if($userTransactionRecord["status"] !== "completed"){
                $recordAmount = $userTransactionRecord["amount"];
                $recordWalletType = $userTransactionRecord["wallet_type"];
                $updateData = [];
                $updateData["updated_at"] = $date;
                if ($recordAmount == $amount && $recordWalletType == $walletType) {
                    $updateStatus = "completed";
                } else {
                    $updateStatus = "wallet_completed";
                }
                $updateData["status"] = $updateStatus;
                $updateData["transaction_hash"] = $transactionHash;
    
                $db->where("id", $recordID);
                $db->update("xun_freecoin_payout_transaction", $updateData);
            }
        }

        return $userTransactionRecord["id"];
    }
 
    public function process_refund_fee_transaction($params){
        global $xunPaymentGateway, $setting, $config, $xun_numbers, $xunCurrency, $post, $xunCrypto, $xunPayment;
        $db= $this->db;
        $general = $this->general;

        $xunWallet = new XunWallet($db);
        $xun_business_service = new XunBusinessService($db);

        $business_id = $params['business_id'];
        $amount = $params['amount'];
        $wallet_type = $params['wallet_type'];
        // $sender_address = $params['sender_address'];
        $date = date("Y-m-d H:i:s");

        $internal_address_return = $xunPaymentGateway->get_nuxpay_user_internal_address($business_id);
    
        $internal_address = $internal_address_return['data']['internal_address'];
        $external_address_return = $xunCrypto->crypto_get_external_address($internal_address, $wallet_type);
        $external_address = $external_address_return['data']['address'];

        $prepaidWalletServerURL =  $config["giftCodeUrl"];

        $freecoin_address = $setting->systemSetting['freecoinWalletAddress'];

        $cryptocurrency_result = $xunCurrency->get_cryptocurrency_rate(array($wallet_type));
        $exchange_rate = $cryptocurrency_result[$wallet_type];


        //wentin
        // $miner_fee = '0';
        // $transaction_target = $miner_fee;
        //     if ($transaction_target > 0) {
        //         $target = "external";
        //     } else {
        //         $target = "internal";
        //     }


        $insertRefund = array(
            "business_id" => $business_id,
            "sender_address" => $freecoin_address,
            "receiver_address" => $external_address,
            "amount" => $amount,
            "amount_receive" => $amount,
            "transaction_fee" => '0',
            // wentin
            "miner_fee" => '0',
            "transaction_target" => 'internal',
            "wallet_type" => $wallet_type,
            "miner_fee_wallet_type" => $wallet_type,
            "type" => "refund_fee",
            "transaction_type" => 'blockchain',
            "exchange_rate" => $exchange_rate,
            "miner_fee_exchange_rate" => $exchange_rate,
            "created_at" => date("Y-m-d H:i:s"),
        );

        $pg_fund_in_id = $db->insert('xun_payment_gateway_fund_in', $insertRefund);

        $tx_obj = new stdClass();
        $tx_obj->userID = 0;
        $tx_obj->address = $freecoin_address;

        $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);
    
        $transactionObj = new stdClass();
        $transactionObj->status = "pending";
        $transactionObj->transactionHash = "";
        $transactionObj->transactionToken = $transaction_token;
        $transactionObj->senderAddress = $freecoin_address;
        $transactionObj->recipientAddress = $internal_address;
        $transactionObj->userID = $business_id;
        $transactionObj->senderUserID = "";
        $transactionObj->recipientUserID = $business_id;
        $transactionObj->walletType = $wallet_type;
        $transactionObj->amount = $amount;
        $transactionObj->addressType = "nuxpay_wallet";
        $transactionObj->transactionType = "receive";
        $transactionObj->escrow = 0;
        $transactionObj->referenceID = $pg_fund_in_id;
        $transactionObj->escrowContractAddress = '';
        $transactionObj->createdAt = $date;
        $transactionObj->updatedAt = $date;
        $transactionObj->exchangeRate = $exchange_rate;
        $transactionObj->expiresAt = '';

        $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

        $txHistoryObj->status = "pending";
        $txHistoryObj->transactionID = "";
        $txHistoryObj->transactionToken = $transaction_token;
        $txHistoryObj->senderAddress = $freecoin_address;
        $txHistoryObj->recipientAddress = $internal_address;
        $txHistoryObj->senderUserID = "";
        $txHistoryObj->recipientUserID = $business_id;
        $txHistoryObj->walletType = $wallet_type;
        $txHistoryObj->amount = $amount;
        $txHistoryObj->transactionType = "refund_fee";
        $txHistoryObj->referenceID = $pg_fund_in_id;
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
       
        $satoshi_amount = $xunCrypto->get_satoshi_amount($wallet_type, $amount);

        $insertTx = array(
            "business_id" => $business_id,
            "sender_address" => $freecoin_address,
            "recipient_address" => $external_address,
            "amount" => $amount,
            "amount_satoshi" => $satoshi_amount,
            "wallet_type" => $wallet_type,
            "credit" => $amount,
            "debit" => 0,
            "transaction_type" => "refund_fee",
            "gw_type" => "BC",
            "reference_id" => $transaction_id,
            "created_at" => $date,
        );

        $invoice_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertTx);

        $curlParams = array(
            "command" => "fundOutCompanyWallet",
            "params" => array(
                "senderAddress" => $freecoin_address,
                "receiverAddress" => $internal_address,
                "amount" => $amount,
                "satoshiAmount" => $satoshi_amount,
                "walletType" => strtolower($wallet_type),
                "id" => $transaction_id,
                "transactionToken" => $transaction_token,
                "addressType" => "freecoin_wallet",
            ),
        );
        
        $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);

        if($curlResponse['code'] == 0){
            $db->where('id', $business_id);
            $xun_user = $db->getOne('xun_user', 'nickname');

            $update_refund_fee = array(
                "deleted" => 1
            );

            $db->where('id', $invoice_id);
            $db->update('xun_payment_gateway_invoice_transaction', $update_refund_fee);

            $updataData = array(
                "status" => 'failed',
                "updated_at" => date("Y-m-d H:i:s")
            );

            $db->where('id', $transaction_id);
            $db->update('xun_wallet_transaction', $updataData);
            
            $db->where('id', $transaction_history_id);
            $db->update($transaction_history_table, $updataData);

            $tag = "Failed Freecoin Fund Out";
            $message = "Business Name:".$xun_user['nickname']."\n";
            $message .= "Amount:" .$amount."\n";
            $message .= "Wallet Type:".$wallet_type."\n";
            $message .= "Error: ".$curlResponse['message_d']."\n";
            $message .= "Time: ".date("Y-m-d H:i:s")."\n";

            $thenux_params["tag"]         = $tag;
            $thenux_params["message"]     = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $xmpp_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
        }
 

        $wallet_info = $xunCrypto->get_wallet_info($freecoin_address, $wallet_type);

        $satoshi_balance = $wallet_info[$wallet_type]['balance'];
        $unitConversion = $wallet_info[$wallet_type]['unitConversion'];

        $balance = bcdiv($satoshi_balance, $unitConversion, 8);

        $balance_usd = $xunCurrency->get_conversion_amount('usd', $wallet_type, $balance);
        
        if($balance_usd < 50){
            $tag = "Low Balance";
            $message = "Address: " .$freecoin_address."\n";
            $message .= "Address Type: freecoin\n"; 
            $message .= "Balance: " .$balance."\n";
            $message .= "Balance(USD): ".$balance_usd."\n";
            $message .= "Wallet Type: ".$wallet_type."\n";
            $message .= "Time: ".date("Y-m-d H:i:s")."\n";

            $thenux_params["tag"]         = $tag;
            $thenux_params["message"]     = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $xmpp_result                  = $general->send_thenux_notification($thenux_params, "thenux_issues");
        }

        $remaining_balance = bcsub($balance, $amount, 8);
        $tag = "Freecoin Send";
        $message = "Amount: " .$amount."\n";
        $message .= "Balance: " .$remaining_balance."\n";
        $message .= "Wallet Type: ".$wallet_type."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $thenux_params["tag"]         = $tag;
        $thenux_params["message"]     = $message;
        $thenux_params["mobile_list"] = $xun_numbers;
        $xmpp_result                  = $general->send_thenux_notification($thenux_params, "thenux_issues");

        return $amount;

    }

    public function update_fund_in_transaction($params){
        $db= $this->db;

        $transaction_hash = $params['transactionHash'];
        $reference_id = $params['referenceID'];
        $pg_fund_in_id = $params['pg_fund_in_id'];
        $wallet_transaction_id = $params['wallet_transaction_id'];
        $status = $params['status'];

        $update_fund_in = array(
            "transaction_id" => $transaction_hash,
            "reference_id" => $reference_id,
            "status" => $status == 'confirmed' || $status == 'completed' ? 'success' : $status,
        );

        $db->where('id', $pg_fund_in_id);
        $updated = $db->update('xun_payment_gateway_fund_in', $update_fund_in);

        $update_invoice_transaction = array(
            "transaction_hash" => $transaction_hash,

        );

        $db->where('reference_id', $wallet_transaction_id);
        $db->where('transaction_type', 'refund_fee');
        $updated = $db->update('xun_payment_gateway_invoice_transaction', $update_invoice_transaction);

        return $updated;
    }
}