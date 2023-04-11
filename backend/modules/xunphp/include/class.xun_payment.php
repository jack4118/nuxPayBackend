<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  18/02/2021.
 **/

class XunPayment
{

    public function __construct($db, $post, $general, $setting, $xunCrypto, $xunCoins)
    {
        $this->db = $db;
        $this->post = $post;
        $this->general = $general;
        $this->setting = $setting;
        $this->xunCrypto = $xunCrypto;
        $this->xunCoins = $xunCoins;
    }

    public function insert_payment_transaction_history($transactionObj){
        $db= $this->db;

        $date = date("Y-m-d H:i:s");
        $tblDate = date("Ymd");

        $tableName = "xun_payment_transaction_history_".$tblDate;

        $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS xun_payment_transaction_history_".$db->escape($tblDate)." LIKE xun_payment_transaction_history");

        $transactionID = $transactionObj->transactionID ? $transactionObj->transactionID : '';
        $gatewayType = $transactionObj->gatewayType ? $transactionObj->gatewayType : '';
        if($transactionID){
            $db->where('gateway_type', $gatewayType);
            $db->where('transaction_id', $transactionID);
            $transaction_history_data = $db->getOne($tableName); 
        }

        if(!$transaction_history_data){ 
            if($transactionObj instanceof stdClass){
                $paymentDetailsID = $transactionObj->paymentDetailsID ? $transactionObj->paymentDetailsID : '';
                $withdrawalID = $transactionObj->withdrawalID ? $transactionObj->withdrawalID : '';
                $senderAddress = $transactionObj->senderAddress ? $transactionObj->senderAddress : '';
                $recipientAddress = $transactionObj->recipientAddress ? $transactionObj->recipientAddress : '';
                $senderUserID = $transactionObj->senderUserID ? $transactionObj->senderUserID : '';
                $recipientUserID = $transactionObj->recipientUserID ? $transactionObj->recipientUserID : '';
                $amount = $transactionObj->amount ? $transactionObj->amount : '';
                $walletType = $transactionObj->walletType ? strtolower($transactionObj->walletType) : '';
                $transactionID = $transactionObj->transactionID ? $transactionObj->transactionID : '';
                $transactionToken = $transactionObj->transactionToken ? $transactionObj->transactionToken : '';
                $transactionType = $transactionObj->transactionType ? $transactionObj->transactionType : '';
                $status = $transactionObj->status == 'completed' || $transactionObj->status == 'confirmed' ? 'success' : $transactionObj->status != '' ? $transactionObj->status : '';
                $referenceID = $transactionObj->referenceID ? $transactionObj->referenceID : '';
                $createdAt = $transactionObj->createdAt ? $transactionObj->createdAt : $date;
                $updatedAt = $transactionObj->updatedAt ? $transactionObj->updatedAt : $date;
                $feeAmount = $transactionObj->fee ? $transactionObj->fee : '';
                if($transactionObj->feeUnit){
                    $feeUnit = $transactionObj->feeUnit;
                    $db->where('symbol', $feeUnit);
                    $fee_wallet_type = $db->getValue('xun_marketplace_currencies', 'currency_id');
                    $feeWalletType = $fee_wallet_type ? $fee_wallet_type : '';  
                }
                else{
                    $feeWalletType = $transactionObj->feeWalletType ? strtolower($transactionObj->feeWalletType) : '';
                }
                $exchangeRate = $transactionObj->exchangeRate ? $transactionObj->exchangeRate : '0';
                $minerFeeExchangeRate = $transactionObj->minerFeeExchangeRate ?  $transactionObj->minerFeeExchangeRate : '0';
                $type = $transactionObj->type ?  $transactionObj->type : '';
                $gatewayType = $transactionObj->gatewayType ?  $transactionObj->gatewayType : '';
                $isInternal = $transactionObj->isInternal ?  $transactionObj->isInternal : 0;
    
                $insertData = array(
                    "payment_details_id" => $paymentDetailsID,
                    "withdrawal_id" => $withdrawalID,
                    "sender_address" => $senderAddress,
                    "recipient_address" => $recipientAddress,
                    "sender_user_id" => $senderUserID,
                    "recipient_user_id" => $recipientUserID,
                    "amount" => $amount,
                    "wallet_type" => $walletType,
                    "fee_amount" => $feeAmount,
                    "fee_wallet_type" => $feeWalletType,
                    "transaction_id" => $transactionID,
                    "transaction_token" => $transactionToken,
                    "transaction_type" => $transactionType,
                    "status" => $status,
                    "reference_id" => $referenceID,
                    "exchange_rate" => $exchangeRate,
                    "miner_fee_exchange_rate" => $minerFeeExchangeRate,
                    "type" => $type,
                    "gateway_type" => $gatewayType,
                    "is_internal" => $isInternal,
                    "created_at" => $createdAt,
                    "updated_at" => $updatedAt,
                );
            }else{
                $insertData = $transactionObj;
                unset($insertData["id"]);
            }
    
            $rowID = $db->insert("xun_payment_transaction_history_".$db->escape($tblDate)."", $insertData);
        }
        else{
            $rowID = $transaction_history_data['id'];
        }

        if(!$rowID){
            $message = "Insert Xun Payment Transaction History\n";
            $message .= $db->getLastQuery();
            $sendNotificationParams = array(
                "message" => $message
            );
            $this->send_notification($sendNotificationParams);
        }
        $data['transaction_history_id'] = $rowID;
        $data['table_name'] = $tableName;
        return $data;
    }

    public function insert_payment_details($transactionObj){
        $db= $this->db;
        $date = date("Y-m-d H:i:s");

        if($transactionObj instanceof stdClass){
            $paymentID = $transactionObj->paymentID ? $transactionObj->paymentID : '';
            $paymentTxID = $transactionObj->paymentTxID ? $transactionObj->paymentTxID : '';
            $paymentMethodID = $transactionObj->paymentMethodID ? $transactionObj->paymentMethodID : '';
            $senderInternalAddress = $transactionObj->senderInternalAddress ? $transactionObj->senderInternalAddress : '';
            $senderExternalAddress = $transactionObj->senderExternalAddress ? $transactionObj->senderExternalAddress : '';
            $recipientInternalAddress = $transactionObj->recipientInternalAddress ? $transactionObj->recipientInternalAddress : '';
            $recipientExternalAddress = $transactionObj->recipientExternalAddress ? $transactionObj->recipientExternalAddress : '';
            $amount = $transactionObj->amount ? $transactionObj->amount : '';
            $walletType = $transactionObj->walletType ? strtolower($transactionObj->walletType) : '';
            $serviceChargeAmount = $transactionObj->serviceChargeAmount ? $transactionObj->serviceChargeAmount : '';
            $serviceChargeWalletType = $transactionObj->serviceChargeWalletType ? strtolower($transactionObj->serviceChargeWalletType) : '';
            $feeAmount = $transactionObj->feeAmount ? $transactionObj->feeAmount : '';
            $feeWalletType = $transactionObj->feeWalletType ? strtolower($transactionObj->feeWalletType) : '';
            $actualFeeAmount = $transactionObj->actualFeeAmount ? $transactionObj->actualFeeAmount : '';
            $actualFeeWalletType = $transactionObj->actualFeeWalletType ? strtolower($transactionObj->actualFeeWalletType) : '';
            $status = $transactionObj->status == 'completed' || $transactionObj->status == 'confirmed' ? 'success' : $transactionObj->status != '' ? $transactionObj->status : '';
            $createdAt = $transactionObj->createdAt ? $transactionObj->createdAt : $date;
            $updatedAt = $transactionObj->updatedAt ? $transactionObj->updatedAt : $date;
            $referenceID = $transactionObj->referenceID ? $transactionObj->referenceID : 0;
            $pgAddress = $transactionObj->pgAddress ? $transactionObj->pgAddress : '';
            $transactionToken = $transactionObj->transactionToken ? $transactionObj->transactionToken : '';
            $transactionID = $transactionObj->transactionID ? $transactionObj->transactionID : '';
            $txExchangeRate = $transactionObj->txExchangeRate ? $transactionObj->txExchangeRate : '';
            $fiatCurrencyID = $transactionObj->fiatCurrencyID ? $transactionObj->fiatCurrencyID : '';


            $insertData = array(
                "payment_id" => $paymentID,
                "payment_tx_id" => $paymentTxID,
                "payment_method_id" => $paymentMethodID,
                "sender_internal_address" => $senderInternalAddress,
                "sender_external_address" => $senderExternalAddress,
                "recipient_internal_address" => $recipientInternalAddress,
                "recipient_external_address" => $recipientExternalAddress,
                "pg_address" => $pgAddress,
                "amount" => $amount,
                "wallet_type" => $walletType,
                "service_charge_amount" => $serviceChargeAmount,
                "service_charge_wallet_type" => $serviceChargeWalletType,
                "fee_amount" => $feeAmount,
                "fee_wallet_type" => $feeWalletType,
                "actual_fee_amount" => $actualFeeAmount,
                "actual_fee_wallet_type" => $actualFeeWalletType,
                "status" => $status,
                "fund_out_transaction_id" => $transactionID,
                "transaction_token" => $transactionToken,
                "tx_exchange_rate" => $txExchangeRate,
                "fiat_currency_id" => $fiatCurrencyID,
                "reference_id" => $referenceID,
                "created_at" => $createdAt,
                "updated_at" => $updatedAt,
            );
        }else{
            $insertData = $transactionObj;
            unset($insertData["id"]);
        }

        $rowID = $db->insert("xun_payment_details", $insertData);

        if(!$rowID){
            $message = "Insert Xun Payment Method\n";
            $message .= $db->getLastQuery();
            $sendNotificationParams = array(
                "message" => $message
            );
            $this->send_notification($sendNotificationParams);
        }
        return $rowID;
    }

    public function getPaymentTxDetailsByAddress($address){
        $db= $this->db;

        $db->where('a.address', $address);
        $db->join('xun_payment_transaction b', 'a.payment_tx_id = b.id', 'LEFT');
        $payment_data = $db->getOne('xun_payment_method a', 'a.id as payment_method_id, b.id as payment_tx_id');

        return $payment_data;
    }

    public function insert_payment_transaction($params){
        $db= $this->db;

        $business_id = $params['business_id'];
        $crypto_amount = $params['crypto_amount'] ? $params['crypto_amount'] : '';
        $wallet_type = $params['wallet_type'] ? $params['wallet_type'] : '';
        $fiat_amount = $params['fiat_amount'] ? $params['fiat_amount'] : '';
        $fiat_currency_id = $params['fiat_currency_id'] ? $params['fiat_currency_id'] : '';
        $transaction_type = $params['transaction_type'];
        $transaction_token = $params['transaction_token'] ? $params['transaction_token'] : '';
        $fiat_currency_exchange_rate = $params['fiat_currency_exchange_rate'] ? $params['fiat_currency_exchange_rate'] : 1;

        $insert_payment_transaction_data = array(
            "transaction_token" => $transaction_token,
            "business_id" => $business_id,
            "crypto_amount" => $crypto_amount,
            "wallet_type" => $wallet_type,
            "fiat_amount" => $fiat_amount,
            "fiat_currency_id" => $fiat_currency_id,
            "fiat_currency_exchange_rate" => $fiat_currency_exchange_rate,
            "transaction_type" => $transaction_type,
            "created_at" => date("Y-m-d H:i:s"),
        );

        $payment_tx_id = $db->insert("xun_payment_transaction", $insert_payment_transaction_data);

        if (!$payment_tx_id) {
            $message = "Insert Xun Payment Transaction\n";
            $message .= $db->getLastQuery();
            $sendNotificationParams = array(
                "message" => $message
            );
            $this->send_notification($sendNotificationParams);
            return array("code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/,
                "error_message" => $db->getLastError());
        }

        return $payment_tx_id;
    }

    public function get_translation_message($message_code)
    {
        // Language Translations.
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;
    }

    function insert_payment_method($paymentMethodArr){
        $db= $this->db;

        $address = $paymentMethodArr['address'];
        $wallet_type = $paymentMethodArr['wallet_type'];
        $payment_tx_id = $paymentMethodArr['payment_tx_id'];
        $payment_type = $paymentMethodArr['type'];

        $insert_payment_method_arr = array(
            "address" => $address,
            "wallet_type" => $wallet_type,
            "payment_tx_id" => $payment_tx_id,
            "type" => $payment_type,
            "created_at" => date("Y-m-d H:i:s")
        );

        $pg_method_id = $db->insert('xun_payment_method', $insert_payment_method_arr);

        if(!$pg_method_id){
            $message = "Insert Xun Payment Method\n";
            $message .= $db->getLastQuery();
            $sendNotificationParams = array(
                "message" => $message
            );
            $this->send_notification($sendNotificationParams);
        }

        return $pg_method_id;
    }

    public function update_payment_transaction_history($tableName, $rowID, $updateData){
        $db= $this->db;

        $tableName = $db->escape($tableName);
        $rowID = $db->escape($rowID);

        $db->where('id', $rowID);
        $updated = $db->update($tableName, $updateData);

        return $updated;
    }

    function send_notification($params){
        $general = $this->general;

        $message = $params['message'];

        $tag = "Insert ERROR";

        $thenux_params = [];
        $thenux_params["tag"] = $tag;
        $thenux_params["message"] = $message;
        $thenux_params["mobile_list"] = array();
        $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_monitoring");
    }

}
?>