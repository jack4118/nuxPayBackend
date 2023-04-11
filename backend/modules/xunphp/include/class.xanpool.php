<?php 

    class Xanpool{
        public function __construct($db, $general, $setting, $post)
        {
            $this->db = $db;
            $this->general = $general;
            $this->setting = $setting;
            $this->post = $post;

        }

        public function estimate_transaction_cost($params, $business_id){
            global $config, $xunCurrency, $xunPay;
            $db= $this->db;
            $post = $this->post;
            $general = $this->general;
            
            $wallet_type = $params['wallet_type'];
            $crypto_amount = $params['crypto_amount'];
            $fiat_amount = $params['fiat_amount'];
            $fiat_currency= $params['fiat_currency'];
            $transaction_type = $params['transaction_type'];  
            // $currency = $params['currency'];
            $payment_method = $params['payment_method'];
            $destination_address = $params['destination_address'];
            $actual_amount = $params['actual_amount'];
            $actual_crypto_amount = $params['actual_crypto_amount'];

            // if ($business_id == '') {
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            // }

            if ($wallet_type == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
            }

            // if($crypto_amount == ''){
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00417') /*Amount cannot be empty.*/, 'developer_msg' => 'Amount cannot be empty.');
            // }

            if($transaction_type == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00369') /*Transaction type cannot be empty.*/);
            }

            // if($fiat_amount == ''){
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Fiat Amount cannot be empty.');
            // }

            if($fiat_currency == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Fiat Currency cannot be empty.');
            }

            // if($destination_address == ''){
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00153') /*Destination Address cannot be empty*/);
            // }

            if($fiat_amount == '' && $crypto_amount == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Amount cannot be empty.');
            }

            $currencyDetails = $xunCurrency->marketplaceCurrencies[$wallet_type];
            if($currencyDetails['symbol'] == "trx-usdt")
            {
                $currencyDetails['symbol'] = "usdt";
            }
            $symbol = strtoupper($currencyDetails['symbol']);

            $payment_method_result = $this->get_payment_method();

            $payment_method = $payment_method_result[$transaction_type];

            foreach($payment_method as $key => $value){
                $currency = strtolower($value['currency']);
                $lc_fiat_currency = strtolower($fiat_currency);
                if($lc_fiat_currency == $currency){
                    // $min_amount = $value['methods'][0]['min'];
                    $max_amount = $value['methods'][0]['max'];
                }
            }
            //
            $api_url = $config['xanpool_api_url'].'/api/prices?currencies='.strtoupper($fiat_currency).'&cryptoCurrencies='.strtoupper($symbol).'&type='.$transaction_type;
            
            $curl_params = array(
            );

            $result = $post->curl_xanpool($api_url, $curl_params, 'GET');

            $exchange_rate = $result[0]['cryptoPrice'];
            $usd_exchange_rate = $result[0]['cryptoPriceUsd'];
            //
            $db->where('name', 'xanpool');
            $provider_id = $db->getValue('provider', 'id');

            $db->where('provider_id', $provider_id);
            $db->where('name', array('minCryptoAmount'), 'IN');
            $db->where('type', $symbol);
            $provider_setting_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

            $db->where('provider_id', $provider_id);
            $db->where('name', 'markupPercentage');
            $markup_percentage_data = $db->getOne('provider_setting', 'value');
            $markup_percentage = $markup_percentage_data['value'];
 
            $exchange_rate_params = array(
                "product_currency" => $fiat_currency,
                "system_currency" => $wallet_type,
            );
    
           $exchange_rate_return = $xunPay->get_product_exchange_rate($exchange_rate_params);
           $exchange_rate_arr = $exchange_rate_return["exchange_rate_arr"];
           $exchange_rate = $exchange_rate_arr[$wallet_type."/".$fiat_currency];

            if($provider_setting_data){
                $min_crypto_amount = $provider_setting_data['minCryptoAmount'];
                $max_crypto_amount = bcdiv($max_amount, $exchange_rate, 8);

                $min_amount =  bcmul($min_crypto_amount, $exchange_rate, 8);
                // $min_amount = ((100+$markup_percentage)/100) * $min_fiat_amount;

            }  

            if($crypto_amount){
                if($crypto_amount < $min_crypto_amount){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Requires minimum purchase of ".$min_crypto_amount." ". strtoupper($symbol));
                }

                else if($crypto_amount > $max_crypto_amount){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Limit exceeded! Maximum limit per transaction is ".$max_crypto_amount." ".strtoupper($symbol));
                }
                // $fiat_amount = $xunCurrency->get_conversion_amount($fiat_currency, $wallet_type, $crypto_amount);
                $api_url = $config['xanpool_api_url'].'/api/prices?currencies='.strtoupper($fiat_currency).'&cryptoCurrencies='.$symbol.'&type='.$transaction_type;

                $curl_params = array(
                );

                $result = $post->curl_xanpool($api_url, $curl_params, 'GET');

                $fiat_crypto_price = $result[0]['cryptoPrice'];
                $crypto_price_usd = $result[0]['cryptoPriceUsd'];

                $fiat_amount = bcmul($crypto_amount, $fiat_crypto_price, 8);
                $fiat_amount = $general->ceilp($fiat_amount, 2);
                
            }

            if($fiat_amount < $min_amount){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Requires minimum purchase of ".$min_amount." ". strtoupper($fiat_currency));

            }
            else if($fiat_amount > $max_amount){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Limit exceeded! Maximum limit per transaction is ".$max_amount." ".strtoupper($fiat_currency));
            }

            if (!$business_id) {
                $db->where('a.crypto_address', $destination_address);
                $db->join('xun_crypto_wallet b', 'a.wallet_id = b.id', 'LEFT');
                $crypto_address_data = $db->getOne('xun_crypto_address a');
    
                // if (!$crypto_address_data) {
                //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00158') /*Destination Address not fonund.*/);
    
                // }
    
                $business_id = (string) $crypto_address_data['business_id'] ? (string) $crypto_address_data['business_id'] :"";
    
            }

            
            $xun_business_service = new XunBusinessService($db);

            if($business_id){
                $business_user_data = $xun_business_service->getUserByID($business_id);

                if(!$business_user_data){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
                }
    
            }
           
            $today = date("Y-m-d 00:00:00", strtotime(date("Y-m-d H:i:s")));
            $db->where('business_id', $business_id);
            $db->where('provider_id', $provider_id);
            $db->where('created_at', $today, '>=');
            $total_transaction = $db->getValue('xun_crypto_payment_transaction', 'count(id)');

            $db->where('provider_id', $provider_id);
            $db->where('name', 'maxDailyTransaction');
            $max_daily_transaction = $db->getValue('provider_setting', 'value');
            if($total_transaction > $max_daily_transaction){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "You have exceeded the maximum daily transaction. Please try again tomorrow.", 'total'=> $total_transaction, 'daily' => $max_daily_transaction);
            }

            $api_url = $config['xanpool_api_url'].'/api/transactions/estimate';

            $curl_params = array(
                "type" => $transaction_type,
                "cryptoCurrency" => $symbol,
                "currency" => $fiat_currency,
            );

            if($crypto_amount){
                $curl_params['crypto'] = $crypto_amount;

            }
            
            if($fiat_amount){
                $curl_params['fiat'] = $fiat_amount;
            }

            $result = $post->curl_xanpool($api_url, $curl_params);

            //to check whether the return amount from xanpool is larger than the actual amount
            $decimal_place = $xunCurrency->get_currency_decimal_places($wallet_type);
            $currency = $result['cryptoPrice'];
            if($result['crypto'] < $actual_amount){
                
                $fiat_amount = bcmul($actual_crypto_amount, $currency, $decimal);
                $converted_amount = bcdiv($fiat_amount, $currency, $decimal_place);
                $result['crypto'] = $converted_amount;
                $result['fiat'] = $fiat_amount;
  
            }

            if($result['error']){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $result['message']);
            }
            $test = $result['serviceCharge'];
            $service_charge = strval($test);

            $data['crypto_amount'] = $result['crypto'];
            $data['fiat_amount'] = $result['fiat'];
            $data['crypto_price'] = $result['cryptoPrice'];
            $data['crypto_price_usd'] = $result['cryptoPriceUsd'];
            $data['amount_receive'] = $result['total'];
            $data['service_charge'] = $result['serviceCharge'];
            $data['test'] = $service_charge;
            $data['destination_address'] = $destination_address;
            $data['crypto_symbol'] = $symbol;
            $data['currency'] = $result['currency'];

            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'Request Quote Successful.', 'data' => $data);

        }

        public function create_payment_request($params, $business_id){
            global $xunCurrency, $config, $xunPaymentGateway, $xunCrypto, $account;
            $db= $this->db;
            $post = $this->post;
            $general = $this->general;

            $wallet_type = $params['wallet_type'];
            $fiat_amount = $params['fiat_amount'];
            $fiat_currency = $params['fiat_currency'];
            $crypto_amount = $params['crypto_amount'];
            $destination_address = $params['destination_address'];
            $transaction_type = $params['transaction_type'];
            $transaction_token = $params['transaction_token'];
            $custom_redirect_url = $params['custom_redirect_url'];

            // if ($business_id == '') {
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
            // }

            if ($wallet_type == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
            }

            if($crypto_amount == ''){
                return array('code' => 1, 'status' => "error", 'statusMsg' => $this->get_translation_message('E00417') /*Amount cannot be empty.*/, 'developer_msg' => 'Amount cannot be empty.');
            }

            if($transaction_type == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00369') /*Transaction type cannot be empty.*/);
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
                $crypto_payment_request = $db->getOne('xun_crypto_payment_request', 'id, business_id, reference_id, pg_address');
    
                $request_id = $crypto_payment_request['id'];
                $business_id = (string) $crypto_payment_request['business_id'];

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
            $xun_business = $db->getOne('xun_business', 'id, buysell_crypto_redirect_url, name');
   
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
            } else if (!$crypto_address_data && $destination_address != "") {
            // return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00158') /*Destination Address not fonund.*/);
                $redirect_url = $config['nuxpayUrl']."/buyCryptoHistory.php?redirectUrl=$merchant_redirect_url";

            } else if($xun_business['name']=="Huat Official") {
                               
                $redirect_url = $config['nuxpayUrl']."/buyCryptoHistory.php?redirectUrl=$merchant_redirect_url";
            } else{
                $redirect_url = $config['nuxpayUrl']."/buyCryptoHistory.php";
            }

            $xun_business_service = new XunBusinessService($db);

            if($business_id)
            {
                $business_user_data = $xun_business_service->getUserByID($business_id);

                if(!$business_user_data){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
                }
                $business_name = $business_user_data['nickname'];
    
            }

            
            if($transaction_type == 'sell' && !$transaction_token){
                $balance = $account->getBalance($business_id, $wallet_type);
                $autoSelling = true;

                if($balance < $crypto_amount){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insufficient Balance.");
                }
            }else if($transaction_type == 'sell' && $transaction_token){
                $autoSelling = false;
            }else{
                $autoSelling = false;
            }
            
            if(!$destination_address && !$transaction_token){
                $data_return = $xunPaymentGateway->get_nuxpay_user_internal_address($business_id);
                if($data_return['code'] == 0){
                    return array("code" => 0, "message" => "FAILED", "message_d" => $data_return['message_d']);
                }
                $internal_address = $data_return['data']['internal_address'];
                $destination_address = $xunCrypto->get_external_address($internal_address, strtolower($wallet_type));

                // if($crypto_result["status"] == "ok"){
                //     $crypto_data = $crypto_result["data"];
                //     $destination_address = $crypto_data["address"];
                // }else{
                //     $status_msg = $crypto_result["statusMsg"];
                //     return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
                // }               
    
            }

            $currencyDetails = $xunCurrency->marketplaceCurrencies[$wallet_type];
            if($currencyDetails['symbol'] == 'trx-usdt')
            {
                $currencyDetails['symbol'] = 'usdt';
            }
            $symbol = strtoupper($currencyDetails['symbol']);


            $db->where('name', 'xanpool');
            $provider_id = $db->getValue('provider', 'id');


            $today = date("Y-m-d 00:00:00", strtotime(date("Y-m-d H:i:s")));
            $db->where('business_id', $business_id);
            $db->where('provider_id', $provider_id);
            $db->where('created_at', $today, '>=');
            $total_transaction = $db->getValue('xun_crypto_payment_transaction', 'count(id)');

            $db->where('provider_id', $provider_id);
            $db->where('name', 'maxDailyTransaction');
            $max_daily_transaction = $db->getValue('provider_setting', 'value');
            if($total_transaction > $max_daily_transaction){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "You have exceeded the maximum daily transaction. Please try again tomorrow.", 'total'=> $total_transaction, 'daily' => $max_daily_transaction);
            }

            $api_url = $config['xanpool_api_url'].'/api/requests';

            $curl_params = array(
                "type" => $transaction_type,
                "cryptoCurrency" => $symbol,
                "currency" => $fiat_currency,

            );

            if($type == 'buy'){
                $curl_params["wallet"] = $destination_address ? $destination_address : '';
            }

            $result = $post->curl_xanpool($api_url, $curl_params);
            if($result['error']){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $result['message']);
            }

            while(1){
                $payment_id = $this->generate_payment_id();

                $db->where('payment_id', $payment_id);
                $payment_id_data = $db->getOne('xun_crypto_payment_transaction', 'id, payment_id');

                if(!$payment_id_data){
                    break;
                }
            }

            $insertData = array(
                "business_id" => $business_id,
                "fiat_amount" => $fiat_amount,
                "fiat_currency" => $fiat_currency,
                "crypto_amount" => $crypto_amount,
                "wallet_type" => $wallet_type,
                "type" => $transaction_type,
                "auto_selling" => $autoSelling,
                "payment_id" => $payment_id,
                "provider_id" => $provider_id,
                "transaction_transfer_id" => '0',
                "destination_address" => $destination_address ? $destination_address : '',
                "reference_id" => $result['id'],
                "status" => "pending",
                "created_at" => date("Y-m-d H:i:s")
            );

            $row_id = $db->insert('xun_crypto_payment_transaction', $insertData);
            if(!$row_id){
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
            if($transaction_type == 'buy'){
                $tag = "Xanpool Buy Request";
            }
            else if($tranaction_type == 'sell'){
                $tag = "Xanpool Sell Request";
            }
            $message = "Business Name:".$business_name."\n\n";
            $message .= "Amount: ".$crypto_amount."\n";
            $message .= "Wallet Type:".$wallet_type."\n";
            $message .= "Time: ".date("Y-m-d H:i:s")."\n";

            $thenux_params["tag"]         = $tag;
            $thenux_params["message"]     = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_pay");

            $data['id'] = $row_id;
            $data['destination_address'] = $destination_address;
            $data['symbol'] = $symbol;
            $data['transaction_type'] = $transaction_type;
            $data['crypto_amount'] = $crypto_amount;
            $data['payment_id'] = $payment_id;
            $data['fiat_currency'] = $fiat_currency;
            $data['api_key'] = $config['xanpool_api_key'];
            $data['fiat_amount'] = $fiat_amount;
            $data['request_id'] = $result['id'];
            $data['xanpool_checkout_page'] = $config['xanpool_checkout_page'];
            $data['redirect_url'] = $redirect_url;
            $data['auto_selling'] = $autoSelling;


            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'Create Payment Successful.', 'data' => $data);

            // $xunWallet = new XunWallet($db);
            
            // $transactionObj = new stdClass();
            // $transactionObj->status = $transaction_status;
            // $transactionObj->transactionHash = "";
            // $transactionObj->transactionToken = "";
            // $transactionObj->senderAddress = $sender_address;
            // $transactionObj->recipientAddress = $destination_address;
            // $transactionObj->userID = $user_id ? $user_id : '';
            // $transactionObj->senderUserID = $sender_user_id;
            // $transactionObj->recipientUserID = $recipient_user_id;
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
        }

        public function get_payment_method(){
            global $config;
            $post = $this->post;
            $api_url = $config['xanpool_api_url'].'/api/methods';
          
            $curl_params = [];
            $payment_method_result = $post->curl_xanpool($api_url, $curl_params, 'GET');
            
            return $payment_method_result;
            
        }

        public function xanpool_crypto_callback($params){

            global $webservice;
            $db= $this->db;
            $post = $this->post;

            $message = $params['message'];
            $payload = $params['payload'];

            $id = $payload['id'];
            $type = $payload['type'];
            $status = $payload['status'];
            $method = $payload['method'];
            $crypto = $payload['crypto'];
            $fiat = $payload['fiat'];
            $total = $payload['total'];
            $currency = $payload['currency'];
            $crypto_currency = $payload['cryptoCurrency'];
            $service_charge = $payload['serviceCharge'];
            $crypto_price = $payload['cryptoPrice'];
            $crypto_price_usd = $payload['cryptoPriceUsd'];
            $user_id = $payload['userId'];
            $user_country = $payload['userCountry'];
            $deposit_wallets = $payload['depositWallets'];
            $partner_data = $payload['partnerData'];

            if($status == 'expired_fiat_not_received' || $status == 'expired_btc_not_in_mempool'){
                $status = 'cancelled';
            }
            else if($status == 'payout_failed'){
                $status = 'failed';
            }
            else if($status == 'fiat_received' || $status == 'btc_in_mempool' || $status == 'btc_received'){
                $status = 'pending';
            }

            $db->where('payment_id', $partner_data);
            $crypto_payment_tx = $db->getOne('xun_crypto_payment_transaction', 'id, business_id, payment_id, status');
      
            if(!$crypto_payment_tx){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00646') /* Payment Transaction not found.*/);
            }

            $payment_transaction_id = $crypto_payment_tx['id'];
            $crypto_payment_tx_status = $crypto_payment_tx_status['status'];

            if($crypto_payment_tx_status == 'pending'){
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'Webhook Saved.');
            }

            $db->where('payment_tx_id', $payment_transaction_id);
            $payment_request_data = $db->getOne('xun_crypto_payment_request', 'id,transaction_token, reference_id');

            $payment_request_id = $payment_request_data['id'];
            $transaction_token = $payment_request_data['transaction_token'];
            $reference_id = $payment_request_data['reference_id'];
     
            $updateArray = array(
                "fiat_amount" => $fiat,
                "crypto_amount" => $crypto,
                "fee_amount" => $service_charge,
                "fee_currency" => $crypto_currency,
                "provider_response_string" => json_encode($params),
                "status" => $status,
                "reference_id" => $id,
                "updated_at" => date("Y-m-d H:i:s")
            );
            $db->where('payment_id', $partner_data);
            $updated = $db->update('xun_crypto_payment_transaction', $updateArray);

            if(!$updated){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }

            $updatedRequestArr = array(
                "status" => $status
            );

            $db->where('id', $payment_request_id);
            $updated_payment_request = $db->update('xun_crypto_payment_request', $updatedRequestArr);

            if(!$updated_payment_request){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => "Update Payment Request: ".$db->getLastError());
            }

            if($crypto_payment_tx){
                $payment_id = $crypto_payment_tx['payment_id'];
                $business_id = $crypto_payment_tx['business_id'];

                $callback_params = array(
                    "transaction_token" => $transaction_token,
                    "fiat_amount" => $fiat,
                    "fiat_symbol" => $currency,
                    "crypto_amount" => $crypto,
                    "crypto_symbol" => $crypto_currency,
                    "payment_id" => $payment_id,
                    "type" => $type,
                    "status" => $status,
                    "reference_id" => $reference_id,
                );

                $business_id = $crypto_payment_tx['business_id'];
                $db->where('user_id', $business_id);
                $business_result = $db->getOne('xun_business', 'id, user_id, buysell_crypto_callback_url');
      
                if($business_result['buysell_crypto_callback_url']){
                    $callback_url = $business_result['buysell_crypto_callback_url'];
                    $curl_params = array(              
                        'command'=>'buySellCryptoCallback',
                        'params'=>$callback_params
                    );  
    
                    $curl_header[] = "Content-Type: application/json";
                    $cryptoResult = $post->curl_post($callback_url, $curl_params, 0, 1, $curl_header);
    

                    
                    $webservice->developerOutgoingWebService($business_id, "buySellCryptoCallback", $callback_url, json_encode($curl_params), json_encode($cryptoResult) );


                    $jsonResult = json_decode($cryptoResult, true);
                    if ($jsonResult !== NULL || !is_array($cryptoResult) ){
                        $cryptoResult = $jsonResult;
                    }
        
                }
                
            }
            
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'Webhook Saved.');
            
        }

        public function update_transaction_data($params){
            $db= $this->db;

            $reference_id = $params['reference_id'];
            $id = $params['id'];
            $type = $params['type'];
            $crypto = $params['crypto'];
            $fiat = $params['fiat'];
            $service_charge = $params['serviceCharge'];
            $total = $params['total'];
            $crypto_price = $params['cryptoPrice'];
            $crypto_price_usd = $params['cryptoPriceUsd'];
            $currency = $params['currency'];
            $deposit_wallet = $params['depositWallet'];

            $updateArray = array(
                "provider_response_string" => json_encode($params),
                "updated_at" => date("Y-m-d H:i:s")
            );

            $db->where('id', $reference_id);
            $updated = $db->update('xun_crypto_payment_transaction', $updateArray);

            if(!$updated){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastQuery());
            }

            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'Update Transaction Successful.');

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

        public function generate_payment_id(){
            $key = '';
            $keys = array_merge(range(0, 9), range('a', 'z'));
        
            for ($i = 0; $i < 32; $i++) {
                $key .= $keys[array_rand($keys)];
                
                //add dash in between the string
                if($i == '7' || $i == '11' || $i == '15' || $i == '19'){
                    $key .= '-';
                }
            }
            
            return $key;
        }

        public function transfer_sell_crypto ($params){
            global $xunPayment, $xunPaymentGateway, $xunCrypto, $config, $account;
            
            $db= $this->db;
            $post = $this->post;
            $general = $this->general;

            $payment_id = $params['payment_id'];
            $reference_id = $params['reference_id'];
            $destination_address = $params['destination_address'];
            $crypto_amount = $params['crypto_amount'];
            $symbol = $params['symbol'];
            $fiat_amount = $params['fiat_amount'];
            $fiat_currency = $params['fiat_currency'];
            $transaction_token = $params['transaction_token'];

            $date = date("Y-m-d H:i:s");

            if($payment_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00642') /* Payment ID cannot be empty.*/);
            }

            if($reference_id == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00643') /* Reference ID cannot be empty.*/);
            }

            if($destination_address == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00153') /*Destination Address cannot be empty*/);
            }

            if($crypto_amount == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00644') /* Crypto Amount cannot be empty.*/);
            }

            if($symbol == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00645') /* Symbol cannot be empty.*/);
            }

            
            // $db->where('status', $status);
            $db->where('payment_id', $payment_id);
            $crypto_payment_transaction = $db->getOne('xun_crypto_payment_transaction');

            if(!$crypto_payment_transaction){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00646') /* Payment Transaction not found.*/);
            }

            $business_id = $crypto_payment_transaction['business_id'];

            $db->where('currency_id',$symbol);
            $marketplace_currencies = $db->getOne('xun_marketplace_currencies', 'id, currency_id');

            $wallet_type = $marketplace_currencies['currency_id'];

            $ret_val= $xunCrypto->crypto_validate_address($destination_address, $wallet_type, 'external');

            if($ret_val['code'] == 1){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $ret_val['statusMsg'], 'developer_msg' => $ret_val); 
            }

            $data_return = $xunPaymentGateway->get_nuxpay_user_internal_address($business_id);
            if($data_return['code'] == 0){
                return array("code" => 0, "message" => "FAILED", "message_d" => $data_return['message_d']);
            }
    
            $internal_address = $data_return['data']['internal_address'];
            // $crypto_result = $xunCrypto->crypto_get_external_address($internal_address, strtolower($wallet_type));
            // if($crypto_result["status"] == "ok"){
            //     $crypto_data = $crypto_result["data"];
            //     $destination_address = $crypto_data["address"];
            // }else{
            //     $status_msg = $crypto_result["statusMsg"];
            //     return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
            // }            

            $xun_business_service = new XunBusinessService($db);

            $business_user_data = $xun_business_service->getUserByID($business_id);

            if(!$business_user_data){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
            }

            $balance = $account->getBalance($business_id, $wallet_type);

            if($balance < $crypto_amount){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insufficient Balance.");
            }
        
            $business_name = $business_user_data['nickname'];

            $tx_obj = new stdClass();
            $tx_obj->userID = $business_id;
            $tx_obj->address = $internal_address;

            $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);

            $xunWallet = new XunWallet($db);
            
            $transactionObj = new stdClass();
            $transactionObj->status = 'pending';
            $transactionObj->transactionHash = "";
            $transactionObj->transactionToken = $transaction_token;
            $transactionObj->senderAddress = $internal_address;
            $transactionObj->recipientAddress = $destination_address;
            $transactionObj->userID = $business_id ? $business_id : '';
            $transactionObj->senderUserID = $business_id;
            $transactionObj->recipientUserID = "";
            $transactionObj->walletType = $wallet_type;
            $transactionObj->amount = $crypto_amount;
            $transactionObj->addressType = "external_transfer";
            $transactionObj->transactionType = "send";
            $transactionObj->escrow = 0;
            $transactionObj->referenceID = "";
            $transactionObj->escrowContractAddress = '';
            $transactionObj->createdAt = $date;
            $transactionObj->updatedAt = $date;
            $transactionObj->expiresAt = '';

            $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

            $address_type = "external_transfer";

            $txHistoryObj->paymentDetailsID = '';
            $txHistoryObj->status = "pending";
            $txHistoryObj->transactionID = "";
            $txHistoryObj->transactionToken = $transaction_token;
            $txHistoryObj->senderAddress = $internal_address;
            $txHistoryObj->recipientAddress = $destination_address;
            $txHistoryObj->senderUserID = $business_id;
            $txHistoryObj->recipientUserID =  '';
            $txHistoryObj->walletType = $wallet_type;
            $txHistoryObj->amount = $crypto_amount;
            $txHistoryObj->transactionType = $address_type;
            $txHistoryObj->referenceID = '';
            $txHistoryObj->createdAt = $date;
            $txHistoryObj->updatedAt = $date;
            // $transactionObj->fee = $final_miner_fee;
            // $transactionObj->feeWalletType = $miner_fee_wallet_type;
            // $txHistoryObj->exchangeRate = $exchange_rate;
            // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
            $txHistoryObj->type = 'out';
            $txHistoryObj->gatewayType = "BC";

            $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);
            $transaction_history_id = $transaction_history_result['transaction_history_id'];
            $transaction_history_table = $transaction_history_result['table_name'];

            $updateWalletTx = array(
                "transaction_history_id" => $transaction_history_id,
                "transaction_history_table" => $transaction_history_table
            );
            $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);

            $satoshi_amount = $xunCrypto->get_satoshi_amount($wallet_type, $crypto_amount);

            $post_params = array(
                "command" => "fundOutExternal",
                "params" => array(
                    "senderAddress" => $internal_address,
                    "receiverAddress" => $destination_address,
                    "amount" => $crypto_amount,
                    "satoshiAmount" => $satoshi_amount,
                    "walletType" => $wallet_type,
                    "transactionToken" => $transaction_token,
                    "addressType" => "nuxpay_wallet",
                    "walletTransactionID" => $transaction_id,
                ),
            );
        
            $url_string = $config["giftCodeUrl"];
            //$post_return = $post->curl_post($url_stricreateXanpoolPaymentResultng, $post_params, 0, 1);//comment this line first
            
            if($post_return['code'] == 0){
                $update_status = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s")
                );
    
                $db->where('id', $transaction_id);
                $db->update('xun_wallet_transaction', $update_status);
                
                $db->where('id', $transaction_history_id);
                $db->update($transaction_history_table, $update_status);

                $tag = "Failed Transfer Sell Crypto";
                $message = "Business Name:".$business_name."\n\n";
                $message .= "Amount: ".$crypto_amount."\n";
                $message .= "Wallet Type:".$wallet_type."\n";
                $message .= "Error Msg:".$post_return['message_d']."\n";
                $message .= "Time: ".date("Y-m-d H:i:s")."\n";

                $thenux_params["tag"]         = $tag;
                $thenux_params["message"]     = $message;
                $thenux_params["mobile_list"] = $xun_numbers;
                $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_issues");
    
                return array("code" => 0, "message" => "FAILED", "message_d" => $post_return['message_d'], "developer_msg" => $post_return);
            }

            $db->where("payment_id", $payment_id);
            $update_transaction = array(
                "status" => 'approved',
                "transaction_transfer_id" => "$transaction_id"
            );
            $db->update('xun_crypto_payment_transaction', $update_transaction);

            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Transfer Sell Crypto Successful.");

        }

        public function get_buy_crypto_supported_currency_wallet($params){
            global $config;
            $db= $this->db;
            $post = $this->post;

            $setting_type = $params['setting_type'];
            $usergetID  = $params['user_id'];
            $provider = strtolower($params['provider']);

            $transaction_type = $params['transaction_type'] ? $params['transaction_type'] : 'buy'; //sell/buy type
            
            if($provider == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Provider cannot be empty.");
            }

            $db->where('name', $provider);
            $provider_id = $db->getValue('provider', 'id');

            if(!$provider_id){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Provider not found.");
            }

            if($provider == 'xanpool'){
                // $fiat_currency_list = array();
                // $method_api_url = $config['xanpool_api_url'].'/api/methods';

                // $payment_method_result = $post->curl_xanpool($method_api_url, $curl_params, 'GET');

                // if($transaction_type == 'buy'){
                //     $buy_data =  $payment_method_result['buy'];

                //     foreach($buy_data as $key => $value){
                //         $fiat_currency_list[] = $value['currency'];
                //     }
                // }
                // else if($transaction_type == 'sell'){
                //     $sell_data =  $payment_method_result['sell'];

                //     foreach($sell_data as $key => $value){
                //         $fiat_currency_list[] = $value['currency'];
                //     }
                // }

                $db->where('provider_id', $provider_id);
                $db->where('name', 'fiatCurrencyList');
                $provider_setting_data = $db->getValue('provider_setting','value');
                $fiat_currency_list = explode(",", $provider_setting_data);

            }
            else{
                $db->where('provider_id', $provider_id);
                $db->where('name', 'fiatCurrencyList');
                $provider_setting_data = $db->getValue('provider_setting','value');
        
                $fiat_currency_list = explode(",", $provider_setting_data);
            }

            $db->where('currency_id', $fiat_currency_list, 'IN');
            $db->orderBy('name', 'ASC');
            $marketplace_currencies = $db->get('xun_marketplace_currencies', null, 'name, currency_id, image');      

            $data['country_list'] = $marketplace_currencies;

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
            // return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00090') /*Wallet Types.*/, "code" => 1, "result" => $returnData, 'data' => $data);

            return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00375') /*Get Receipt Successful.*/, 'data' => $data);
            
        }
    }
    

?>