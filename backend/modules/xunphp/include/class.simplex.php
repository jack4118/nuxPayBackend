<?php

class Simplex
{
    public function __construct($db, $general, $setting, $post)
    {
        $this->db = $db;
        $this->general = $general;
        $this->setting = $setting;
        $this->post = $post;

    }

    public function get_quote($params, $business_id, $ip)
    {
        global $config, $xunCurrency;
        $db = $this->db;
        $post = $this->post;

        $wallet_type = $params['wallet_type'];
        $fiat_currency = strtoupper($params['fiat_currency']);
        $fiat_amount = $params['fiat_amount'] ? (float) $params['fiat_amount'] : '';
        $crypto_amount = $params['crypto_amount'] ? (float) $params['crypto_amount']  : '' ;

        $requested_currency = strtoupper($params['requested_currency']);
        $requested_amount = (float) $params['requested_amount'];
        $payment_method_type = $params['payment_method_type'];
        $transaction_type = $params['transaction_type'];
        $destination_address = $params['destination_address'];

        if($params['business_id'] != "") {
            $business_id = $params['business_id'];
        } 

        // if ($business_id == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        // }

        if ($fiat_currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Fiat Currency cannot be empty.");
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
        }

        // if($crypto_amount == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00417') /*Amount cannot be empty.*/, 'developer_msg' => 'Amount cannot be empty.');
        // }

        if ($transaction_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00369') /*Transaction type cannot be empty.*/);
        }

        if (!$payment_method_type) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Payment Method Type cannot be empty.");
        }

        // if ($requested_amount == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Requested Amount cannot be empty.");
        // }

        // if ($requested_currency == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Request Currency cannot be empty.");

        // }

        if($fiat_amount == '' && $crypto_amount == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Crypto and fiat amount cannot be empty.");

        }

        // if($destination_address == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00153') /*Destination Address cannot be empty*/);
        // }
        if (!$business_id) {
            $db->where('a.crypto_address', $destination_address);
            $db->join('xun_crypto_wallet b', 'a.wallet_id = b.id', 'LEFT');
            $crypto_address_data = $db->getOne('xun_crypto_address a');

            // if (!$crypto_address_data) {
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00158') /*Destination Address not fonund.*/);

            // }

            $business_id = (string) $crypto_address_data['business_id'] ? (string) $crypto_address_data['business_id'] : "";

        }
        $currencyDetails = $xunCurrency->marketplaceCurrencies[$wallet_type];

        $db->where('name', 'simplex');
        $provider_id = $db->getValue('provider', 'id');

        $db->where('provider_id', $provider_id);
        $db->where('name', array('dailyLimit', 'monthlyLimit'), 'IN');
        $provider_setting = $db->map('name')->ArrayBuilder()->get('provider_setting',null, 'name, value');

        if($provider_setting){
            $daily_limit = $provider_setting['dailyLimit'];
            $monthly_limit = $provider_setting['monthlyLimit'];
        }

        $monthly = date("Y-m-d 00:00:00", strtotime(date("Y-m-01 00:00:00")));
        $db->where('business_id', $business_id);
        $db->where('provider_id', $provider_id);
        $db->where('created_at', $monthly, '>=');
        $monthly_payment_transaction = $db->get('xun_crypto_payment_transaction', null, 'fiat_amount, fiat_currency');

        $total_monthly_amount = 0;
        if($monthly_payment_transaction){
            foreach($monthly_payment_transaction as $month_key => $month_value){
                $payment_amount = $month_value['fiat_amount'];
                $fiat_currency_id = strtolower($month_value['fiat_currency']);
    
                $converted_amount = $xunCurrency->get_conversion_amount('usd', $fiat_currency_id, $payment_amount);
                $total_monthly_amount += $converted_amount;
            }
        }       

        if($total_monthly_amount > $monthly_limit){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You have exceeded the monthly transaction amount.");

        }

        $today = date("Y-m-d 00:00:00", strtotime(date("Y-m-d H:i:s")));
        $db->where('business_id', $business_id);
        $db->where('provider_id', $provider_id);
        $db->where('created_at', $today, '>=');
        $payment_transaction = $db->get('xun_crypto_payment_transaction', null, 'fiat_amount, fiat_currency');

        $total_daily_amount = 0;
        if($payment_transaction){
            foreach($payment_transaction as $key => $value){
                $payment_amount = $value['fiat_amount'];
                $fiat_currency_id = strtolower($value['fiat_currency']);
    
                $converted_amount = $xunCurrency->get_conversion_amount('usd', $fiat_currency_id, $payment_amount);
                $total_daily_amount += $converted_amount;
    
            }
        }

        if($total_daily_amount > $daily_limit){
            // return array('code' => 0, 'message' => "FAILED", 'message_d' => "You have exceeded the daily amount.");

        }

        $symbol = strtoupper($currencyDetails['symbol']);
        $requested_amount = $crypto_amount ? $crypto_amount : $fiat_amount;
        $requested_currency = $crypto_amount ? $symbol : $fiat_currency;
        
        if($request_currency == 'TRX-USDT'){
            $requested_currency == 'USDT-TRC20';
        }

        if($symbol == 'TRX-USDT'){
            $symbol = 'USDT-TRC20';
        }
        $api_url = $config['simplex_api_url'] . '/wallet/merchant/v2/quote';

        $end_user_id = $params['end_user_id'] ? $params['end_user_id']."_".$business_id : $business_id."_".$business_id;

        $curl_params = array(
            "end_user_id" => $end_user_id,
            "digital_currency" => $symbol,
            "fiat_currency" => $fiat_currency,
            "requested_currency" => $requested_currency,
            "requested_amount" => $requested_amount,
            "wallet_id" => $config['simplex_partner_name'],
            "client_ip" => $ip,
            "payment_methods" => $payment_method_type,
        );

        $simplex_header = array(
            "Content-Type: application/json",
            "Authorization: ApiKey " . $config['simplex_api_key'],
        );

        $result = $post->curl_simplex($api_url, $curl_params);

        if ($result['error']) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $result['error']);
        }

        $db->where('name', 'simplex');
        $provider_id = $db->getValue('provider', 'id');

        $quote_id = $result['quote_id'];
        $crypto_amount = $result['digital_money']['amount'];

        // $insertData = array(
        //     "business_id" => $business_id,
        //     "payment_amount" => $requested_amount,
        //     "payment_currency" => $requested_currency,
        //     "crypto_amount" => $crypto_amount,
        //     "wallet_type" => $wallet_type,
        //     "quote_id" => $quote_id,
        //     "type" => $transaction_type,
        //     "provider_id" => $provider_id,
        //     "status" => "pending",
        //     "created_at" => date("Y-m-d H:i:s"),
        // );

        // $inserted = $db->insert('xun_crypto_payment_transaction', $insertData);

        // if (!$inserted) {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastQuery());
        // }

        $data['quote_id'] = $quote_id;
        $data['symbol'] = $result['digital_money']['currency'];
        $data['requested_amount'] = $result['fiat_money']['total_amount'];
        $data['crypto_amount'] = $result['digital_money']['amount'];

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'Simplex Get Quote', 'data' => $data);

    }

    public function create_payment_transaction($params, $business_id, $ip)
    {
        global $config, $xunCrypto, $xunPaymentGateway, $xunCurrency;
        $post = $this->post;
        $db = $this->db;
        $general = $this->general;

        $quote_id = $params['quote_id'];
        $wallet_type = $params['wallet_type'];
        // $symbol = $params['symbol'];
        $fiat_currency = $params['fiat_currency'];
        // $requested_currency = $params['requested_currency'];
        // $requested_amount = $params['requested_amount'];
        $payment_method_type = $params['payment_method_type'];
        $destination_address = $params['destination_address'];
        $transaction_type = $params['transaction_type'];
        $crypto_amount = $params['crypto_amount'] ? $params['crypto_amount'] : '';
        $fiat_amount = $params['fiat_amount'] ?  $params['fiat_amount'] : '';
        $transaction_token = $params['transaction_token'];
        $custom_redirect_url = $params['custom_redirect_url'];

        if ($quote_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Quote ID cannot be empty.");

        }

        if ($fiat_currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Fiat Currency cannot be empty.");
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
        }

        // if($crypto_amount == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00417') /*Amount cannot be empty.*/, 'developer_msg' => 'Amount cannot be empty.');
        // }

        if ($transaction_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00369') /*Transaction type cannot be empty.*/);
        }

        if (!$payment_method_type) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Payment Method Type cannot be empty.");
        }

        // if ($requested_amount == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Requested Amount cannot be empty.");
        // }

        // if ($requested_currency == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Request Currency cannot be empty.");

        // }

        if($fiat_amount == '' && $crypto_amount == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Crypto and fiat amount cannot be empty.");

        }

        if (!$business_id) {
            $db->where('a.crypto_address', $destination_address);
            $db->join('xun_crypto_wallet b', 'a.wallet_id = b.id', 'LEFT');
            $crypto_address_data = $db->getOne('xun_crypto_address a');

            // if (!$crypto_address_data) {
            //     // return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00158') /*Destination Address not fonund.*/);
            // }

            $business_id = (string) $crypto_address_data['business_id'] ? (string) $crypto_address_data['business_id'] : "";

        }


        if($transaction_token){
            $db->where('transaction_token', $transaction_token);
            $crypto_payment_request = $db->getOne('xun_crypto_payment_request', 'id, business_id, reference_id, pg_address, end_user_id');

            $request_id = $crypto_payment_request['id'];
            $business_id = (string) $crypto_payment_request['business_id'];
            $end_user_id = $crypto_payment_request['end_user_id'] ? $crypto_payment_request['end_user_id'] : $business_id;
            $unique_user_id = $end_user_id."_".$business_id;

            $request_ref_id = $crypto_payment_request['reference_id'];

            if($request_ref_id) {

                $db->where('transaction_token', $request_ref_id);
                $pgDetail = $db->getOne('xun_payment_gateway_payment_transaction');

                if($pgDetail) {
                    $pgTransactionToken = $request_ref_id;
                }
            }

            // change the destination to pg_address if exist
            if (strlen($crypto_payment_request['pg_address']) != 0) {
                $destination_address = $crypto_payment_request['pg_address'];
            }
        }

        $db->where('user_id', $business_id);
        $xun_business = $db->getOne('xun_business', 'id, buysell_crypto_redirect_url');

        $merchant_redirect_url = $xun_business['buysell_crypto_redirect_url'];
        

        if(strpos($request_ref_id, 'thirdpartappbuysell##') !== false) {
            if($config['environment']=="prod") {
                $redirect_url = "https://huat.io/ret_techone.php";
            } else {
                $redirect_url = "https://dev.huat.io/ret_techone.php";
            }
        } else if($custom_redirect_url!="") {
            $redirect_url = $custom_redirect_url;
        } else if($pgTransactionToken) {
            $redirect_url = $config['nuxpayUrl']."/qrPayment.php?transaction_token=".$pgTransactionToken;
        } else if (!$crypto_address_data) {
            $redirect_url = $config['nuxpayUrl']."/buyCryptoHistory.php?redirectUrl=$merchant_redirect_url";
        } else{
            $redirect_url = $config['nuxpayUrl']."/buyCryptoHistory.php";
        }
        $xun_business_service = new XunBusinessService($db);

        $reference_id = $params["reference_id"];

        $db->where('name', 'simplex');
        $provider_id = $db->getValue('provider', 'id');

        $db->where('provider_id', $provider_id);
        $db->where('name', array('dailyLimit', 'monthlyLimit'), 'IN');
        $provider_setting = $db->map('name')->ArrayBuilder()->get('provider_setting',null, 'name, value');

        if($provider_setting){
            $daily_limit = $provider_setting['dailyLimit'];
            $monthly_limit = $provider_setting['monthlyLimit'];
        }

        $monthly = date("Y-m-d 00:00:00", strtotime(date("Y-m-01 00:00:00")));
        $db->where('business_id', $business_id);
        $db->where('provider_id', $provider_id);
        $db->where('created_at', $monthly, '>=');
        $monthly_payment_transaction = $db->get('xun_crypto_payment_transaction', null, 'fiat_amount, fiat_currency');

        $total_monthly_amount = 0;
        if($monthly_payment_transaction){
            foreach($monthly_payment_transaction as $month_key => $month_value){
                $payment_amount = $month_value['fiat_amount'];
                $fiat_currency_id = strtolower($month_value['fiat_currency']);
    
                $converted_amount = $xunCurrency->get_conversion_amount('usd', $fiat_currency_id, $payment_amount);
                $total_monthly_amount += $converted_amount;
            }
        }       

        if($total_monthly_amount > $monthly_limit){
            // return array('code' => 0, 'message' => "FAILED", 'message_d' => "You have exceeded the monthly transaction amount.");

        }

        $today = date("Y-m-d 00:00:00", strtotime(date("Y-m-d H:i:s")));
        $db->where('business_id', $business_id);
        $db->where('provider_id', $provider_id);
        $db->where('created_at', $today, '>=');
        $payment_transaction = $db->get('xun_crypto_payment_transaction', null, 'fiat_amount, fiat_currency');

        $total_daily_amount = 0;
        if($payment_transaction){
            foreach($payment_transaction as $key => $value){
                $payment_amount = $value['fiat_amount'];
                $fiat_currency_id = strtolower($value['fiat_currency']);
    
                $converted_amount = $xunCurrency->get_conversion_amount('usd', $fiat_currency_id, $payment_amount);
                $total_daily_amount += $converted_amount;
    
            }
        }

        if($total_daily_amount > $daily_limit){
            // return array('code' => 0, 'message' => "FAILED", 'message_d' => "You have exceeded the daily amount.");

        }
        // $db->where('quote_id', $quote_id);
        // $crypto_payment_tx = $db->getOne('xun_crypto_payment_transaction');

        // if (!$crypto_payment_tx) {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Quote Not found.');
        // }

        $currencyDetails = $xunCurrency->marketplaceCurrencies[$wallet_type];

        $symbol = strtoupper($currencyDetails['symbol']);
        $requested_amount = $crypto_amount ? $crypto_amount : $fiat_amount;
        $requested_currency = $crypto_amount ? $symbol : $fiat_currency;

        $business_user_data = $xun_business_service->getUserByID($business_id);

        $username = $business_user_data['username'];
        $email = $business_user_data['email'];
        $business_name = $business_user_data['nickname'];

        if (!$destination_address) {
            $data_return = $xunPaymentGateway->get_nuxpay_user_internal_address($business_id);
            if ($data_return['code'] == 0) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $data_return['message_d']);
            }

            $internal_address = $data_return['data']['internal_address'];
            $destination_address = $xunCrypto->get_external_address($internal_address, strtolower($wallet_type));
            // if ($crypto_result["status"] == "ok") {
            //     $crypto_data = $crypto_result["data"];
            //     $destination_address = $crypto_data["address"];
            // } else {
            //     $status_msg = $crypto_result["statusMsg"];
            //     return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
            // }

        }

        if ($username) {
            $verified_details[] = 'phone';
        }

        if ($crypto_payment_request){
            $email = "";
        }
        else{
            if ($email) {
                $verified_details[] = 'email';
            }
        }
        
        $api_url = $config['simplex_api_url'] . '/wallet/merchant/v2/payments/partner/data';

        while (1) {
            $payment_id = $this->generate_payment_id();

            $db->where('payment_id', $payment_id);
            $payment_id_data = $db->getOne('xun_crypto_payment_transaction', 'id, payment_id');

            if (!$payment_id_data) {
                break;
            }
        }

        $details = json_decode(file_get_contents("http://ipinfo.io/{$ip}/json"));
        $location_coord = $details->loc;

        if($symbol == 'TRX-USDT'){
            $symbol = 'USDT-TRC20';
        }

        $curl_params = array(
            "account_details" => array(
                "app_provider_id" => $config['simplex_partner_name'],
                "app_version_id" => "1.3.1",
                "app_end_user_id" => $unique_user_id ? $unique_user_id : "$business_id"."_".$business_id,
                "app_install_date" => $general->formatDateTimeToIsoFormat(date("Y-m-d H:i:s")),
                "email" => $email ? $email : "",
                "phone" => $username ? $username : "",
                "verified_details" => $verified_details ? $verified_details : array(),
                "signup_login" => array(
                    "ip" => $ip,
                    "location" => $location_coord,
                    "timestamp" => $general->formatDateTimeToIsoFormat(date("Y-m-d H:i:s")),

                ),
            ),
            "transaction_details" => array(
                "payment_details" => array(
                    "quote_id" => $quote_id,
                    "payment_id" => $payment_id,
                    "order_id" => "",
                    "destination_wallet" => array(
                        "currency" => $symbol,
                        "address" => $destination_address,
                        "tag" => "",
                    ),
                    "original_http_ref_url" => $config['nuxpayUrl'],
                ),
            ),
        );

        $result = $post->curl_simplex($api_url, $curl_params);

        if ($result['error']) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $result['error']);
        }

        $xunWallet = new XunWallet($db);

        // $transactionObj = new stdClass();
        // $transactionObj->status = 'pending';
        // $transactionObj->transactionHash = "";
        // $transactionObj->transactionToken = "";
        // $transactionObj->senderAddress = '';
        // $transactionObj->recipientAddress = $destination_address;
        // $transactionObj->userID = $user_id ? $user_id : '';
        // $transactionObj->senderUserID = '';
        // $transactionObj->recipientUserID = $user_id;
        // $transactionObj->walletType = $wallet_type;
        // $transactionObj->amount = $data_amount;
        // $transactionObj->addressType = $address_type;
        // $transactionObj->transactionType = $transaction_type;
        // $transactionObj->escrow = 0;
        // $transactionObj->referenceID = $service_charge_transaction_id;
        // $transactionObj->escrowContractAddress = '';
        // $transactionObj->createdAt = $date;
        // $transactionObj->updatedAt = $date;
        // $transactionObj->expiresAt = '';

        // $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

        // $transactionType = "internal_transfer";

        // $txHistoryObj->paymentDetailsID = '';
        // $txHistoryObj->status = $transaction_status;
        // $txHistoryObj->transactionID = "";
        // $txHistoryObj->transactionToken = "";
        // $txHistoryObj->senderAddress = $sender_address;
        // $txHistoryObj->recipientAddress = $destination_address;
        // $txHistoryObj->senderUserID = $sender_user_id;
        // $txHistoryObj->recipientUserID = $recipient_user_id ? $recipient_user_id : '';
        // $txHistoryObj->walletType = $wallet_type;
        // $txHistoryObj->amount = $data_amount;
        // $txHistoryObj->transactionType = $address_type;
        // $txHistoryObj->referenceID = '';
        // $txHistoryObj->createdAt = $date;
        // $txHistoryObj->updatedAt = $date;
        // // $transactionObj->fee = $final_miner_fee;
        // // $transactionObj->feeWalletType = $miner_fee_wallet_type;
        // $txHistoryObj->exchangeRate = $exchange_rate;
        // // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
        // $txHistoryObj->type = 'in';
        // $txHistoryObj->gatewayType = "BC";
        // $txHistoryObj->isInternal = 1;

        // $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
        // $transaction_history_id = $transaction_history_result['transaction_history_id'];
        // $transaction_history_table = $transaction_history_result['table_name'];

        // $updateWalletTx = array(
        //     "transaction_history_id" => $transaction_history_id,
        //     "transaction_history_table" => $transaction_history_table
        // );
        // $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);

        $insertData = array(
            "business_id" => $business_id,
            "fiat_amount" => $fiat_amount,
            "fiat_currency" => $fiat_currency,
            "crypto_amount" => $crypto_amount,
            "wallet_type" => $wallet_type,
            "quote_id" => $quote_id,
            "type" => $transaction_type,
            "provider_id" => $provider_id,
            "payment_id" => $payment_id,
            "destination_address" => $destination_address,
            "status" => "pending",
            "created_at" => date("Y-m-d H:i:s"),
        );

        $row_id = $db->insert('xun_crypto_payment_transaction', $insertData);

        if (!$row_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastQuery());
        }


        if($transaction_token){
            $db->where('transaction_token', $transaction_token);
            $crypto_payment_request = $db->getOne('xun_crypto_payment_request', 'id, business_id');

            $request_id = $crypto_payment_request['id'];
            $business_id = $crypto_payment_request['business_id'];
            if($request_id){
                $updateRequest = array(
                    "payment_tx_id" => $row_id,
                    "updated_at" => date("Y-m-d H:i:s")
                );
                $db->where('transaction_token', $transaction_token);
                $db->update('xun_crypto_payment_request',$updateRequest);

                $updateBusiness = array(
                    "business_id" => $business_id,
                );
                $db->where('id', $row_id);
                $db->update('xun_crypto_payment_transaction', $updateBusiness);
            }
           
        }
        $tag = "Simplex Buy Request";
        $message = "Business Name:".$business_name."\n\n";
        $message .= "Amount: ".$crypto_amount."\n";
        $message .= "Wallet Type:".$wallet_type."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $thenux_params["tag"]         = $tag;
        $thenux_params["message"]     = $message;
        $thenux_params["mobile_list"] = $xun_numbers;
        $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_pay");


        // $updateArray = array(
        //     "payment_id" => $payment_id,
        // );
        // $db->where('quote_id', $quote_id);
        // $db->update('xun_crypto_payment_transaction', $updateArray);

        $data['payment_id'] = $payment_id;
        $data['partner_name'] = $config['simplex_partner_name'];
        $data['payment_flow_type'] = 'wallet';
        $data['payment_success_url'] = $redirect_url;
        $data['payment_failed_url'] = $redirect_url;
        $data['payment_id'] = $payment_id;
        $data['payment_checkout_page'] = $config['simplex_checkout_page'];

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'Simplex Payment Request.', 'data' => $data);

    }

    public function generate_payment_id()
    {
        $key = '';
        $keys = array_merge(range(0, 9), range('a', 'f'));

        for ($i = 0; $i < 32; $i++) {
            $key .= $keys[array_rand($keys)];

            //add dash in between the string
            if ($i == '7' || $i == '11' || $i == '15' || $i == '19') {
                $key .= '-';
            }
        }

        return $key;
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

}
