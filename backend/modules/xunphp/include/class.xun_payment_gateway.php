<?php

/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/

class XunPaymentGateway
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

    public function merchant_register($params)
    {
        global $config;
        $db = $this->db;

        $name = trim($params["name"]);
        $phone_num = trim($params["phone_num"]);
        $email = trim($params["email"]);
        $remark = $params["remark"];

        if ($name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00404') /*Username cannot be empty*/);
        }

        if ($phone_num == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00405') /*Phone number cannot be empty*/);
        }

        if ($email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00211') /*Email cannot be empty*/);
        }

        $db->where('email', $email);
        $business_account = $db->getOne('xun_business_account');

        if (!$business_account) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00406') /*Business account does not exist.*/);
        }

        $business_id = $business_account["user_id"];

        $db->where('user_id', $business_id);
        $merchant_account = $db->getOne('xun_merchant_account');

        if ($merchant_account) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00407') /*Merchant account already exist.*/);
        }

        $date = date("Y-m-d H:i:s");
        $erlang_server = $config["erlang_server"];
        // $insert_merchant = array(
        //     "server_host" => $erlang_server,
        //     "nickname" => $name,
        //     "username" => $phone_num,
        //     "type" => "merchant",
        //     "created_at" => $date,
        //     "updated_at" => $date

        // );

        // $merchant_id = $db->insert('xun_user', $insert_merchant);

        // if(!$merchant_id){
        //     return array("code" => 0, "message" => "SUCCESS", "message_d" => "Something went wrong. Please try again.", "developer_msg" => $db->getLastError());
        // }

        $insert_merchant_account = array(
            "user_id" => $business_id,
            "name" => $name,
            "email" => $email,
            "phone_number" => $phone_num,
            "remark" => $remark,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $merchant_account_id = $db->insert('xun_merchant_account', $insert_merchant_account);

        if (!$merchant_account_id) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00225') /*Merchant Registered.*/);
    }

    public function merchant_request_transaction($params, $source, $payment_mode = "", $return_gw_type = false, $ip)
    {
        global $xunCrypto, $xunCoins, $config, $xunCurrency, $xunPayment;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;

        $business_id = trim($params["business_id"]) ? trim($params["business_id"]) :  trim($params["account_id"]);
        $api_key = trim($params["api_key"]);
        $address = trim($params["address"]);
        $amount = trim($params["amount"]);
        $wallet_type = trim(strtolower($params["currency"]));
        $reference_id = trim($params["reference_id"]);
        $redirect_url = trim($params["redirect_url"]);
        $toggleNewAddress = trim($params["toggle_new_address"]);
        $fiat_currency_id = trim(strtolower($params['fiat_currency_id']));
        $payment_type = trim($params['payment_type']) ? trim($params['payment_type']) : 'payment_gateway';
        $destination_address = trim($params['destination_address']) ? trim($params['destination_address']) : '';

        //crypto/bankin/creditcard
        $payment_channel = $params['payment_channel'] ? $params['payment_channel'] : array('credit_card', 'bank_in', 'crypto_wallet');

        $is_direct = $params['is_direct'] ? $params['is_direct'] : 0;

        //validate min amount
        // if($amount != '')
        // {
        //     $post = array(
        //         "command" => $getCreditSetting,
        //         "site" => 'Monitor',
        //         "params" => $amount
        //     );
        //     // devbackend.blockchainProject.testback
        //     $ch = curl_init();
        //     curl_setopt($ch, CURLOPT_URL, 'http://devbackend.blockchainProject.testback');
        //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //     curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        //     $response = curl_exec($ch);

        //     if($amount >= $response)
        //     {
        //         $amount = $amount;
        //     }
        //     else
        //     {
        //         return array('code' => 0, 'message' => "FAILED", 'message_d' => "Amount too low");
        //     }
        // }

        if (empty($toggleNewAddress)) {
            $toggleNewAddress = 1;
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00641') /*Account ID cannot be empty*/);
        }

        if ($api_key == '' && $payment_mode == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00086') /*API Key cannot be empty*/);
        }

        // if ($amount == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Amount cannot be empty");
        // }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00408') /*Currency cannot be empty.*/);
        }

        if ($reference_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00409') /*Reference Id cannot be empty*/);
        }

        if ($redirect_url == '' && $payment_mode == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00410') /*Redirect URL cannot be empty*/);
        }

        if ($payment_mode == "") {
            $validate_api_key = $xunCrypto->validate_crypto_api_key($api_key, $business_id);

            if ($validate_api_key !== true) {
                return $validate_api_key;
            }
        }

        if (is_array($payment_channel) == false || count($payment_channel) == 0) {

            return array("code" => 0, "message" => "FAILED", "message_d" => "Payment channel cannot be empty.");
        }

        if ($is_direct && count($payment_channel) > 1) {

            return array("code" => 0, "message" => "FAILED", "message_d" => "Only one payment channel is allows when is_direct flag is ON.");
        }

        if ($is_direct && count($payment_channel) == 1 && $payment_channel[0] != "bank_in" && $payment_channel[0] != "credit_card" && $payment_channel[0] != "crypto_wallet") {

            return array("code" => 0, "message" => "FAILED", "message_d" => "Direct is not supported for this payment channel.");
        }

        if ($is_direct && count($payment_channel) == 1) {
            $direct_payment_channel = $payment_channel[0];
        }

        foreach ($payment_channel as $pc) {
            if ($pc != "bank_in" && $pc != "credit_card" && $pc != "crypto_wallet") {
                return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid payment channel.");
            }
        }

        //if amount passed is fiat
        if ($fiat_currency_id) {
            $db->where('currency_id', $fiat_currency_id);
            $db->where('type', 'currency');
            $db->where('status', 1);
            $fiat_currency_data = $db->getOne('xun_marketplace_currencies');

            if (!$fiat_currency_data) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Fiat Currency Type");
            }

            $fiat_amount = $amount;
            $amount = $xunCurrency->get_conversion_amount($wallet_type, $fiat_currency_id, $amount);
            // $amount = bcmul($amount, 1.05, 8);
            // $checking = $xunCurrency->checking($wallet_type, $fiat_currency_id, $amount);
        }

        $db->where('id', $business_id);
        $service_charge_rate = $db->getOne('xun_user', 'service_charge_rate');
        if(empty($service_charge_rate)){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "service charge rate is null");
        }
        $db->where('user_id', $business_id);
        $db->where('name','allowSwitchCurrency');
        $allow_switch_currency = $db->getOne('xun_user_setting','value');
        if(empty($allow_switch_currency)){
            // return array('code' => 0, 'message' => "FAILED", 'message_d' => "allow_switch_currency is null");
        }
        if($allow_switch_currency['value'] == 1){
            $params_array=array('creditType' => $wallet_type,'address' => $destination_address,'includeService' => 1);
            $estimate_miner_fee_result=$post->curl_crypto("estimateMinerFee", $params_array);
            $miner_fee=$estimate_miner_fee_result['data']['minerFee'];
            $parent_miner_fee=$estimate_miner_fee_result['data']['parentMinerFee'];
            $db->where('name','switchCurrencyMarkup');
            $switch_currency_markup = $db->getOne('system_settings','value');
            if(empty($switch_currency_markup)){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Please set switchCurrencyMarkup in system_settings");
            }
            // return $amount;
            // $amount = $amount + bcmul($amount, bcdiv($service_charge_rate['service_charge_rate'],100,8), 8) + $miner_fee + bcmul($amount,bcdiv($switch_currency_markup['value'],100,8),8);
            $rate = bcmul(bcdiv($service_charge_rate['service_charge_rate'],100,8),$amount,8) + bcmul($miner_fee,2,8) + $switch_currency_markup['value'];
            $actual_amount = $amount;
            $amount = $amount+ $rate + bcmul($rate,bcdiv($service_charge_rate['service_charge_rate'],100,8),8);
            // return $amount;
        }
        if (!filter_var($redirect_url, FILTER_VALIDATE_URL) && $payment_mode == "") {

            if (strpos($redirect_url, "speed101.pw") !== false) {
                //ignore
            } else {
                return array('message' => "FAILED", 'code' => 0, 'message_d' => $this->get_translation_message('E00411') /*Please enter a valid URL.*/);
            }

        }
        //  ignore reference id checking for now
        // $db->where("business_id", $business_id);
        // $db->where("reference_id", $reference_id);
        // $pg_transaction = $db->getOne("xun_payment_gateway_payment_transaction", "id, status, address, transaction_token");

        // if ($pg_transaction) {
        //     if ($pg_transaction["address"] == $address && $pg_transaction["status"] == "pending") {
        //         $return_data = [];
        //         $return_data["transaction_token"] = $pg_transaction["transaction_token"];

        //         return array(
        //             "code" => 1,
        //             "message" => "SUCCESS",
        //             "message_d" => "Success",
        //             "data" => $return_data,
        //         );

        //     } else if ($pg_transaction["address"] != $address) {
        //         return array("code" => 0,
        //             "message" => "FAILED",
        //             "message_d" => "Reference ID must be unique.");
        //     }

        // }

        // validate wallet_type
        $coin_settings = $xunCoins->checkCoinSetting("is_payment_gateway", $wallet_type);
        if (!$coin_settings) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00433') /*Invalid currency.*/
            );
        }

        if ($amount != '' && !is_numeric($amount)) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00320') /*Invalid amount.*/
            );
        }

        $wallet_type = strtolower($wallet_type);
        $date = date("Y-m-d H:i:s");

        $db->where('id', $business_id);
        $user_data = $db->getOne('xun_user', 'id, username, register_site');
        if ($payment_mode == "invoice" && $address == "") {

            $db->where('id', $business_id);
            $xun_user = $db->getOne('xun_user', 'nickname');
            $crypto_params["type"] = $wallet_type;
            $crypto_params['businessID'] = $business_id;
            $crypto_params['businessName'] = $xun_user['nickname'];

            $db->where('user_id', $business_id);
            $business_account = $db->getOne('xun_business_account', 'account_type');

            $account_type = $business_account['account_type'];

            if ($account_type == 'premium' && $toggleNewAddress == 1) {

                $crypto_results = $post->curl_crypto("getNewAddress", $crypto_params);
                if ($crypto_results["code"] != 0) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_results["message"]);
                } else {
                    $address = $crypto_results["data"]["address"];
                    if (!$address) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00151') /*Address not generated.*/);
                    }
                }

                $gw_type = "PG";
            } else {

                $db->where('user_id', $business_id);
                $db->where('address_type', 'nuxpay_wallet');
                $crypto_user_address = $db->getOne('xun_crypto_user_address ', 'address');

                $internal_address = $crypto_user_address['address'];
                $db->where('internal_address', $internal_address);
                $db->where('wallet_type', $wallet_type);
                $crypto_external_address = $db->getOne('xun_crypto_external_address');

                if (!$crypto_external_address) {
                    $cryptoParams['walletType'] = $wallet_type;
                    $cryptoParams['address'] = $internal_address;
                    $result = $post->curl_crypto("getWalletAddress", $cryptoParams, 2);
                    if ($result["code"] != 0) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_results["message"]);
                    } else {
                        $address = $result["data"]["address"];
                        if (!$address) {
                            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00151') /*Address not generated.*/);
                        }

                        $insertData = array(
                            "internal_address" => $internal_address,
                            "external_address" => $address,
                            "wallet_type" => $wallet_type,
                            "created_at" => date("Y-m-d H:i:s"),
                            "updated_at" => date("Y-m-d H:i:s")
                        );

                        $inserted = $db->insert('xun_crypto_external_address', $insertData);
                    }
                } else {
                    $address = $crypto_external_address['external_address'];
                }

                $gw_type = "BC";
            }
        } else {

            if ($payment_type == 'payment_gateway') {
                //  check if business has activated pg
                $db->where("a.business_id", $business_id);
                $db->where("a.type", $wallet_type);
                $db->where("a.status", 1);
                $db->join("xun_crypto_destination_address b", "a.id=b.wallet_id");
                $crypto_wallet = $db->getOne("xun_crypto_wallet a", "a.id");

                // if (!$crypto_wallet) {
                //     return array("code" => 0,
                //         "message" => "FAILED",
                //         "message_d" => $this->get_translation_message('E00412') /*Please set up your payment gateway before proceeding.*/);
                // }
                if ($address == '') {
                    // generate new address
                    if (!$crypto_wallet) {
                        $db->where('business_id', $business_id);
                        $db->where('type', $wallet_type);
                        $db->where('status', 1);
                        $crypto_wallet = $db->getOne('xun_crypto_wallet');

                        if (!$crypto_wallet) {
                            $insertWallet = array(
                                "business_id" => $business_id,
                                "type" => $wallet_type,
                                "status" => 1,
                                "created_at" => date("Y-m-d H:i:s"),
                                "updated_at" => date("Y-m-d H:i:s")
                            );

                            $wallet_id = $db->insert('xun_crypto_wallet', $insertWallet);
                            if (!$wallet_id) {
                                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "error_message" => $db->getLastError());
                            }
                        } else {
                            $wallet_id = $crypto_wallet["id"];
                        }
                    } else {
                        $wallet_id = $crypto_wallet["id"];
                    }


                    $db->where('id', $business_id);
                    $xun_user = $db->getOne('xun_user', 'nickname');
                    $crypto_params["type"] = $wallet_type;
                    $crypto_params['businessID'] = $business_id;
                    $crypto_params['businessName'] = $xun_user['nickname'];
 
                    $crypto_results = $post->curl_crypto("getNewAddress", $crypto_params);

                    if ($crypto_results["code"] != 0) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_results["message"]);
                    }

                    $address = $crypto_results["data"]["address"];

                    if (!$address) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00151') /*Address not generated.*/);
                    }

                    $insert_data = array(
                        "wallet_id" => $wallet_id,
                        "crypto_address" => $address,
                        "destination_address" => $destination_address,
                        "type" => "in",
                        "status" => 1,
                        "created_at" => $date,
                        "updated_at" => $date,
                    );

                    $address_id = $db->insert("xun_crypto_address", $insert_data);
                    if (!$address_id) {
                        return array(
                            "code" => 0,
                            "message" => "FAILED",
                            "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/,
                            "error_message" => $db->getLastError()
                        );
                    }
                } else {
                    //  check if address is valid
                    $db->where("a.crypto_address", $address);
                    $db->where("a.type", "in");
                    $db->where("a.status", 1);
                    $db->where("b.business_id", $business_id);
                    $db->where("b.status", 1);
                    $db->join("xun_crypto_wallet b", "a.wallet_id=b.id");
                    $crypto_address = $db->getOne("xun_crypto_address a", "a.id");
                    if (!$crypto_address) {
                        return array(
                            "code" => 0,
                            "message" => "FAILED",
                            "message_d" => $this->get_translation_message('E00413') /* Invalid address.*/
                        );
                    }
                }

                $gw_type = "PG";
            } else {
                $db->where('user_id', $business_id);
                $db->where('address_type', 'nuxpay_wallet');
                $internal_address = $db->getValue('xun_crypto_user_address', 'address');

                if ($internal_address) {
                    $external_address = $xunCrypto->get_external_address($internal_address, $wallet_type);
                } else {
                    return array("code" => 0, "message" => "FAILED", "message_d" => "User Address not found.", "error_message" => "User Address not found.");
                }
                $gw_type = "BC";
            }
        }
  
        while (true) {
            $transaction_token = $general->generateAlpaNumeric(16);

            $db->where("transaction_token", $transaction_token);
            $pg_transaction = $db->getOne("xun_payment_gateway_payment_transaction", "id");
            if (!$pg_transaction) {
                break;
            }
        }

        while (true) {
            $payment_id = time();
            $db->where("payment_id", $payment_id);
            $pg_transaction = $db->getOne("xun_payment_gateway_payment_transaction", "id");
            if (!$pg_transaction) {
                break;
            }
        }

        $timeoutDuration = $setting->systemSetting['nuxpayPaymentTimeout'];
        $expires_at = date("Y-m-d H:i:s", strtotime('+' . $timeoutDuration . ' seconds', strtotime($date)));

        // $nuxpayUrl = $config["nuxpayUrl"];
        if (!$redirect_url && $payment_mode == "invoice") {
            $siteURL = $source;
            $db->where('source', $siteURL);
            $callbackUrl = $db->getValue('site', 'domain');
            $redirect_url = $callbackUrl . "/inv/" . $transaction_token;
        }


        $db->where('currency_id', $wallet_type);
        $cryptocurrency_data = $db->getOne('xun_marketplace_currencies', 'id, currency_id, symbol');
        $symbol = $cryptocurrency_data['symbol'];

        // get currency_rate
        $db->where('currency', $fiat_currency_id);
        $currencyRate = $db->getValue('xun_currency_rate', 'exchange_rate');

        $payment_transaction_params = array(
            "transaction_token" => $transaction_token,
            "business_id" => $business_id,
            "crypto_amount" => $amount,
            "wallet_type" => $wallet_type,
            "fiat_amount" => $fiat_amount,
            "fiat_currency_id" => $fiat_currency_id,
            "fiat_currency_exchange_rate" => $currencyRate,
            "transaction_type" => $payment_type
        );
        $payment_tx_id = $xunPayment->insert_payment_transaction($payment_transaction_params);

        //PLUG IN HERE
        $directDetail = "";
        $isDirect = 0;

        if($is_direct == 1 && $direct_payment_channel=="crypto_wallet"){
            $isDirect=1;
        }
        if($is_direct == 1 && ($direct_payment_channel=="credit_card" || $direct_payment_channel=="bank_in") ) {
            // check if credit type supported & allowSwitchCurrency is set
            // generate new USDT pg_address if not supported, to trigger autoswap
            $db->where('user_id', $business_id);
            $db->where('name', 'allowSwitchCurrency');
            $isAllowSwitchCurrency = $db->getValue('xun_user_setting', 'value');

            if ($direct_payment_channel == "credit_card") {
                $provider = "Simplex";
            } else {
                $provider = "Xanpool";
            }
            
            $providerData["provider"] = $provider;
            $providerOutputArray = $xunCrypto->get_wallet_type($providerData, $business_id);

            $supported = false;
            if ($providerOutputArray['code'] == 1) {
                foreach ($providerOutputArray['data']['currency_list'] as $key => $value) {
                    if (strtolower($value['currency_id']) == strtolower($wallet_type)) {
                        $supported = true;
                        break;
                    }
                }
            }

            if (!$supported && $isAllowSwitchCurrency) {

                // generate new pg address (USDT)
                if ($provider == "Simplex" && $provider) {
                    // Simplex = id: 26

                    //get wallet type
                    $db->where('name', 'defaultWalletType');
                    $db->where('provider_id', '26');
                    $d = $db->getValue('provider_setting', 'value');
                    $default_wallet_type = $d;

                    //get symbol
                    $db->where('name', 'defaultSymbol');
                    $db->where('provider_id', '26');
                    $d1 = $db->getValue('provider_setting', 'value');
                    $default_symbol = $d1;
                } else if ($provider == "Xanpool" && $provider) {
                    // Xanpool = id: 27

                    //get wallet type
                    $db->where('name', 'defaultWalletType');
                    $db->where('provider_id', '27');
                    $d2 = $db->getValue('provider_setting', 'value');
                    $default_wallet_type = $d2;

                    //get symbol
                    $db->where('name', 'defaultSymbol');
                    $db->where('provider_id', '27');
                    $d3 = $db->getValue('provider_setting', 'value');
                    $default_symbol = $d3;

                } else {
                    return array("code" => 0, "message" => "FAILED", "message_d" => "Wallet type missing.", "error_message" => "Missing wallet type(Simplex||Xanpool)");
                }
                $newRequestParams = array(
                    'transaction_token' => $transaction_token,
                    'wallet_type' => $default_wallet_type
                );

                $newRequestData = $this->get_payment_gateway_address_details($newRequestParams);
                if (!$newRequestData || $newRequestData['code'] != 1) {
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/,
                        "error_message" => json_encode($newRequestData),
                    );
                }

                //read currency rate from db
                $db->where('name', 'defaultCurrencyRate');
                $d4 = $db->getValue('provider_setting', 'value');
                $default_currency_type = $d4;

                // get currency rate
                $db->where('cryptocurrency_id', $default_currency_type);
                $request_currency_rate = $db->getValue('xun_cryptocurrency_rate', 'value');
                $request_fiat_id = $direct_payment_channel == "credit_card" ? $fiat_currency_id : 'myr';

                $db->where('currency', $request_fiat_id);
                $request_fiat_currency_rate = $db->getValue('xun_currency_rate', 'exchange_rate');
                $request_wallet_type = $default_wallet_type;
                // $request_wallet_type = $default_currency_type;
                $request_address = $newRequestData['data']['address'];
                //$request_address = $address;
                $request_symbol = $default_symbol;

                $request_amount = $newRequestData['data']['amount'];
                $requested_fiat_amount = bcmul((string)$request_amount, $request_currency_rate, 2);
                $requested_fiat_amount = bcmul($requested_fiat_amount, $request_fiat_currency_rate, 2);
                // $request_amount = $amount;
                // $requested_fiat_amount = $fiat_amount;
            } else {
                $request_wallet_type = $wallet_type;
                $request_symbol = $symbol;
                $request_amount = $amount;
                $request_fiat_id = $fiat_currency_id;
                $requested_fiat_amount = $fiat_amount;
                $request_address = $address;
            }
            $directBuySellResult = $this->direct_buy_request($direct_payment_channel, $request_wallet_type, $request_symbol, $request_amount, $business_id, $ip, $request_fiat_id, $requested_fiat_amount, $request_address, $redirect_url, $actual_amount);

            if ($directBuySellResult['code'] == 1) {
                $filter='usdt';
                if(strpos($request_symbol, $filter)){
                    // first round is using the code below to get actual cypto amount.
                    $first_round_amount = $directBuySellResult['provider_amount']['crypto_amount'];
                    // then second round is get the exchange rate based on first_round_amount divide the request_amount, then multiply with request_amount, and quote again
                    $amount=bcmul(bcdiv($request_amount,$first_round_amount,5),$request_amount,5);
                    
                    // $fiat_amount = $directBuySellResult['provider_amount']['fiat_amount'];
                    // $directDetail = json_encode($directBuySellResult['data']);
                    
                    $directBuySellResult1 = $this->direct_buy_request($direct_payment_channel, $request_wallet_type, $request_symbol, $amount, $business_id, $ip, $request_fiat_id, $requested_fiat_amount, $request_address, $redirect_url, $actual_amount);
                    if ($directBuySellResult1['code'] == 1) {
                        $amount = $directBuySellResult1['provider_amount']['crypto_amount'];
                        $fiat_amount = $directBuySellResult1['provider_amount']['fiat_amount'];
                        $directDetail = json_encode($directBuySellResult1['data']);
                    }else{
                        return array(
                            "code" => 0,
                            "message" => "FAILED",
                            "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/,
                            "error_message" => "Second round: ".json_encode($directBuySellResult1)
                        );
                    }
                }else{
                    $amount = $directBuySellResult['provider_amount']['crypto_amount'];
                    $fiat_amount = $directBuySellResult['provider_amount']['fiat_amount'];
                    $directDetail = json_encode($directBuySellResult['data']);
                }
                $isDirect = 1;
            } else {
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/,
                    "error_message" => "First round: ".json_encode($directBuySellResult)
                );
                // return $directBuySellResult;
            }
        }


        $insert_pg_transaction_data = array(
            "transaction_token" => $transaction_token,
            "payment_id" => $payment_id,
            "business_id" => $business_id,
            "access_token" => $api_key,
            "amount" => $amount,
            "wallet_type" => $wallet_type,
            "address" => $address,
            "reference_id" => $reference_id,
            "status" => "pending",
            "redirect_url" => $redirect_url,
            "expires_at" => $expires_at,
            "created_at" => $date,
            "updated_at" => $date,
            "payment_type" => $payment_type,
            "gw_type" => $gw_type,
            "is_direct" => $isDirect,
            "direct_detail" => $directDetail,
            "payment_channel" => json_encode($payment_channel)
        );

        $pg_id = $db->insert("xun_payment_gateway_payment_transaction", $insert_pg_transaction_data);
        if (!$pg_id) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/,
                "error_message" => $db->getLastError()
            );
        }


        $insert_payment_method_arr = array(
            "address" => $payment_type == 'payment_gateway' ? $address : $external_address,
            "wallet_type" => $wallet_type,
            "payment_tx_id" => $payment_tx_id,
            "type" => $payment_type,
            "created_at" => date("Y-m-d H:i:s")
        );

        $pg_method_id = $db->insert('xun_payment_method', $insert_payment_method_arr);

        if (!$pg_method_id) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/,
                "error_message" => $db->getLastError()
            );
        }

        $source = $user_data['register_site'];
        $db->where('source', $source);
        $callbackUrl = $db->getValue('site', 'domain');
        if ($payment_type == 'zero_fee') {
            $payment_url = $callbackUrl . "/login.php?transaction_token=" . $transaction_token;
        } else {
            $payment_url = $callbackUrl . "/qrPayment.php?transaction_token=" . $transaction_token;
        }


        $return_data = [];
        $return_data["transaction_token"] = $transaction_token;
        $return_data["reference_id"] = $reference_id;
        $return_data["address"] = $payment_type == 'payment_gateway' ? $address : $external_address;
        $return_data['pg_id'] = $pg_id;
        $return_data['payment_id'] = $payment_id;
        if ($return_gw_type) {
            $return_data['gw_type'] = $gw_type;
        }
        $return_data['payment_url'] = $payment_url;
        $return_data['cryptocurrency_amount'] = $amount;
        $return_data['crypto_symbol'] = strtoupper($symbol);


        return array(
            "code" => 1,
            "message" => "SUCCESS",
            "message_d" => $this->get_translation_message('B00226') /*Success*/,
            "data" => $return_data,
        );
    }

    public function direct_buy_request($payment_channel, $wallet_type, $symbol, $crypto_amount, $business_id, $ip, $fiat_currency_id, $fiat_amount, $address, $redirect_url, $actual_amount)
    {

        global $xunCrypto;
        global $simplex;
        global $xanpool;
        global $xunPaymentGateway;
        global $general;
        global $db;

        if ($payment_channel == "credit_card") {
            $provider = "Simplex";
        } else {
            $provider = "Xanpool";
        }
        if($symbol == "trx-usdt")
        {
            $symbol = "usdt";
        }

        //$data["provider"] = $provider;
        $data["setting_type"] = $payment_channel;
        $outputArray = $xunCrypto->get_wallet_type($data, $business_id);

        $supported = false;
        if ($outputArray['code'] == 1) {
            foreach ($outputArray['data']['currency_list'] as $key => $value) {
                if (strtolower($value['currency_id']) == strtolower($wallet_type)) {
                    $supported = true;
                    break;
                }
            }
        }

        if ($supported) {

            if ($provider == "Simplex") {

                if ($fiat_currency_id == "") {

                    $simplexData['fiat_currency'] = "usd";
                    $simplexData['fiat_amount'] = "";
                    $simplexData['crypto_amount'] = $crypto_amount;
                } else {

                    $simplexData['fiat_currency'] = $fiat_currency_id;
                    $simplexData['fiat_amount'] = $fiat_amount;
                    $simplexData['crypto_amount'] = "";
                }

                $simplexData['payment_method_type'] = array("credit_card");
                $simplexData['wallet_type'] = $wallet_type;
                $simplexData['transaction_type'] = "buy";
                $simplexData['destination_address'] = $address;


                //
                $conversionData['amount'] = 1;
                $conversionData['fiat_currency_id'] = $simplexData['fiat_currency'];
                $conversionData['wallet_type'] = $wallet_type;
                $conversionData['provider'] = $provider;
                $conversionData['type'] = "buy";

                $conversionResult = $this->get_crypto_conversion_rate($conversionData);

                if ($conversionResult['code'] == 1) {

                    $arrRate = $conversionResult['data']['currency_setting_data'];
                    $rateData = $arrRate[strtoupper($symbol)][strtoupper($simplexData['fiat_currency'])];

                    if ($rateData) {

                        $min_fiat_amount = $general->ceilp(($rateData['min_crypto_amount'] * $rateData['crypto_converted_amount']), 2);
                        $max_fiat_amount = $general->floorp($rateData['max_amount'], 2);
                        $min_crypto_amount = $general->ceilp($rateData['min_crypto_amount'], 8);
                        $max_crypto_amount = $general->floorp($rateData['max_crypto_amount'], 8);


                        $amount_limit = array("min_fiat" => $min_fiat_amount, "max_fiat" => $max_fiat_amount, "min_crypto" => $min_crypto_amount, "max_crypto" => $max_crypto_amount);


                        if ($fiat_currency_id == "") {
                            //check crypto amount

                            if ($crypto_amount < $min_crypto_amount) {

                                return array("code" => 0, "message" => "FAILED", "message_d" => "Transaction amount too low. Please enter a value of " . $min_crypto_amount . " " . strtoupper($symbol) . " or more.", "amount_limit" => $amount_limit);
                            }

                            if ($crypto_amount > $max_crypto_amount) {

                                return array("code" => 0, "message" => "FAILED", "message_d" => "Transaction amount too high. Please enter a value of " . $max_crypto_amount . " " . strtoupper($symbol) . " or less.", "amount_limit" => $amount_limit);
                            }
                        } else {
                            //check fiat amount

                            if ($fiat_amount < $min_fiat_amount) {

                                return array("code" => 0, "message" => "FAILED", "message_d" => "Transaction amount too low. Please enter a value of " . $min_fiat_amount . " " . strtoupper($fiat_currency_id) . " or more.", "amount_limit" => $amount_limit);
                            }

                            if ($fiat_amount > $max_fiat_amount) {

                                return array("code" => 0, "message" => "FAILED", "message_d" => "Transaction amount too high. Please enter a value of " . $max_fiat_amount . " " . strtoupper($fiat_currency_id) . " or less.", "amount_limit" => $amount_limit);
                            }
                        }
                    } else {

                        return array("code" => 0, "message" => "FAILED", "message_d" => "Unsupported fiat currency.");
                    }

                    $simplexResult = $simplex->get_quote($simplexData, $business_id, $ip);

                    if ($simplexResult['code'] == 1) {

                        $quote_id = $simplexResult['data']['quote_id'];

                        if ($quote_id != "") {

                            $simplexData['quote_id'] = $quote_id;
                            $simplexData["custom_redirect_url"] = $redirect_url;
                            $simplexCreatePaymentResult = $simplex->create_payment_transaction($simplexData, $business_id, $ip);

                            if ($simplexCreatePaymentResult['code'] == 1) {

                                return array("code" => 1, "message" => "SUCCESS", "message_d" => "Buy Crypto Simplex", "data" => $simplexCreatePaymentResult['data'], "provider_amount" => array("fiat_amount" => $simplexResult['data']['requested_amount'], "crypto_amount" => $simplexResult['data']['crypto_amount']));
                            } else {

                                return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong, please try again later.", "debug" => $simplexData);
                            }
                        } else {

                            return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong, please try again later.");
                        }
                    } else {

                        return $simplexResult;
                    }
                } else {

                    return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong, please try again later.");
                }
            } else {

                if ($fiat_currency_id == "") {
                    $fiat_currency = "sgd";
                } else {
                    $fiat_currency = $fiat_currency_id;
                }

                $conversionData['amount'] = 1;
                $conversionData['fiat_currency_id'] = $fiat_currency;
                $conversionData['wallet_type'] = $wallet_type;
                $conversionData['provider'] = $provider;
                $conversionData['type'] = "buy";

                $conversionResult = $this->get_crypto_conversion_rate($conversionData);

                if ($conversionResult['code'] == 1) {

                    $arrRate = $conversionResult['data']['currency_setting_data'];
                    $rateData = $arrRate[strtoupper($symbol)][strtoupper($fiat_currency)];

                    if ($rateData) {

                        $min_fiat_amount = $general->ceilp(($rateData['min_crypto_amount'] * $rateData['crypto_converted_amount']), 2);
                        $max_fiat_amount = $general->floorp($rateData['max_amount'], 2);
                        $min_crypto_amount = $general->ceilp($rateData['min_crypto_amount'], 8);
                        $max_crypto_amount = $general->floorp($rateData['max_crypto_amount'], 8);

                        $amount_limit = array("min_fiat" => $min_fiat_amount, "max_fiat" => $max_fiat_amount, "min_crypto" => $min_crypto_amount, "max_crypto" => $max_crypto_amount);


                        if ($fiat_currency_id == "") {
                            //check crypto amount

                            if ($crypto_amount < $min_crypto_amount) {

                                return array("code" => 0, "message" => "FAILED", "message_d" => "Transaction amount too low. Please enter a value of " . $min_crypto_amount . " " . strtoupper($symbol) . " or more.", "amount_limit" => $amount_limit);
                            }

                            if ($crypto_amount > $max_crypto_amount) {

                                return array("code" => 0, "message" => "FAILED", "message_d" => "Transaction amount too high. Please enter a value of " . $max_crypto_amount . " " . strtoupper($symbol) . " or less.", "amount_limit" => $amount_limit);
                            }
                        } else {
                            //check fiat amount

                            if ($fiat_amount < $min_fiat_amount) {

                                return array("code" => 0, "message" => "FAILED", "message_d" => "Transaction amount too low. Please enter a value of " . $min_fiat_amount . " " . strtoupper($fiat_currency) . " or more.", "amount_limit" => $amount_limit);
                            }

                            if ($fiat_amount > $max_fiat_amount) {

                                return array("code" => 0, "message" => "FAILED", "message_d" => "Transaction amount too high. Please enter a value of " . $max_fiat_amount . " " . strtoupper($fiat_currency) . " or less.", "amount_limit" => $amount_limit);
                            }
                        }
                    } else {

                        return array("code" => 0, "message" => "FAILED", "message_d" => "Unsupported fiat currency.", 'debug' => $conversionResult);
                    }

                    if ($fiat_currency_id == "") {

                        $xanpoolEstimateData["fiat_amount"] = "";
                        $xanpoolEstimateData["crypto_amount"] = $crypto_amount;
                    } else {

                        $xanpoolEstimateData["fiat_amount"] = $fiat_amount;
                        $xanpoolEstimateData["crypto_amount"] = "";
                    }
 
                    $xanpoolEstimateData["wallet_type"] = $wallet_type;
                    $xanpoolEstimateData["fiat_currency"] = $fiat_currency;
                    $xanpoolEstimateData["transaction_type"] = "buy";
                    $xanpoolEstimateData["actual_crypto_amount"] = $crypto_amount;
                    $xanpoolEstimateData["actual_amount"] = $actual_amount; //this actual amount is to check whether the crypto amount return from xanpool is positive or not

                    $xanpoolEstimateCostResut = $xanpool->estimate_transaction_cost($xanpoolEstimateData, $business_id);

                    if ($xanpoolEstimateCostResut['code'] == 1) {

                        $xanpoolCreatePaymentData["wallet_type"] = $wallet_type;
                        $xanpoolCreatePaymentData["fiat_amount"] = $xanpoolEstimateCostResut['data']['fiat_amount'];
                        $xanpoolCreatePaymentData["fiat_currency"] = $xanpoolEstimateCostResut['data']['currency'];
                        $xanpoolCreatePaymentData["crypto_amount"] = $xanpoolEstimateCostResut['data']['crypto_amount'];
                        $xanpoolCreatePaymentData["transaction_type"] = "buy";
                        $xanpoolCreatePaymentData["custom_redirect_url"] = $redirect_url;
                        $xanpoolCreatePaymentData["destination_address"] = $address;

                        $createXanpoolPaymentResult = $xanpool->create_payment_request($xanpoolCreatePaymentData, $business_id);
 
                        if ($createXanpoolPaymentResult['code'] == 1) {

                            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Buy Crypto Simplex", "data" => $createXanpoolPaymentResult['data'], "provider_amount" => array("fiat_amount" => $xanpoolEstimateCostResut['data']['fiat_amount'], "crypto_amount" => $xanpoolEstimateCostResut['data']['crypto_amount']));
                        } else {

                            return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong, please try again later.");
                        }
                    } else {

                        return $xanpoolEstimateCostResut;
                    }
                } else {

                    return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong, please try again later.");
                }
            }
        } else {
            //
            return array("code" => 0, "message" => "FAILED", "message_d" => "Currency is not supported by the payment channel." . $wallet_type);
        }
    }



    public function payment_gateway_get_transaction_details($params)
    {
        global $xunCrypto, $xunCurrency, $config;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;

        // $business_id = trim($params["business_id"]);
        // $api_key = trim($params["api_key"]);
        $transaction_token = trim($params["transaction_token"]);

        // if ($business_id == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business Id cannot be empty");
        // }

        // if ($api_key == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "API Key cannot be empty");
        // }

        if ($transaction_token == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00414') /*Transaction token cannot be empty*/);
        }

        // $validate_api_key = $xunCrypto->validate_crypto_api_key($api_key, $business_id);

        // if ($validate_api_key !== true) {
        //     return $validate_api_key;
        // }

        $db->where("transaction_token", $transaction_token);
        $pg_transaction = $db->getOne("xun_payment_gateway_payment_transaction");

        if (!$pg_transaction) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00434') /*Invalid transaction.*/
            );
        }
        if ($pg_transaction["status"] != "pending") {
            $db->where('pg_transaction_id', $pg_transaction['id']);
            $pg_invoice_details = $db->getOne('xun_payment_gateway_invoice_detail');
            if ($pg_invoice_details['status'] == 'success' || !$pg_transaction['status'] == 'success') {
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $this->get_translation_message('E00435') /*This transaction has ended.*/
                );
            }
        }

        $expires_at = $pg_transaction["expires_at"];
        $payment_type = $pg_transaction['payment_type'];
        $wallet_type = $pg_transaction["wallet_type"];
        $gateway_type = $pg_transaction["gw_type"];
        $business_id = $pg_transaction["business_id"];

        $is_direct = $pg_transaction["is_direct"];
        $direct_detail = json_decode($pg_transaction["direct_detail"]) ?? "{}";
        $payment_channel = json_decode($pg_transaction["payment_channel"]);

        $db->where("user_id", $business_id);
        $db->where("name", "showWallet");
        $pg_arr = $db->getOne("xun_user_setting");
        $pg_arr_val = json_decode($pg_arr["value"]);

        $db->where("user_id", $business_id);
        $db->where("name", "allowSwitchCurrency");
        $switch_currency_setting = $db->getOne("xun_user_setting");

        if (in_array($wallet_type, $pg_arr_val)) {
            if ($switch_currency_setting) {
                $switch_currency = $switch_currency_setting["value"];
            } else {
                $switch_currency = "0";
            }
        } else {
            $switch_currency = "0";
        }




        $date = date("Y-m-d H:i:s");

        // if($expires_at < $date){
        //     return array("code" => 0,
        //         "message" => "FAILED",
        //         "message_d" => $this->get_translation_message('E00436') /*This transaction has expired.*/);
        // }

        $business_id = $pg_transaction["business_id"];

        $xun_business_service = new XunBusinessService($db);
        $xun_business = $xun_business_service->getBusinessDetails($business_id);
        $business_name = $xun_business["name"];

        $db->where("user_id", $business_id);
        $business_data = $db->getOne("xun_business", "profile_picture_url");

        $profile_picture_url = $business_data["profile_picture_url"];
        if (!$business_data["profile_picture_url"]) {
            $profile_picture_url = "";
        }

        $db->where('transaction_token', $transaction_token);
        $payment_tx_data =  $db->getOne('xun_payment_transaction', 'id,fiat_currency_id, crypto_amount, fiat_amount');

        if (!$payment_tx_data) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00434') /*Invalid transaction.*/);
        }

        $payment_tx_id = $payment_tx_data['id'];
        $fiat_currency_id = $payment_tx_data['fiat_currency_id'] ? $payment_tx_data['fiat_currency_id'] : 'usd';

        if ($payment_type == 'zero_fee') {

            //check if pg address exist
            $db->where('payment_tx_id', $payment_tx_id);
            $db->where('type', 'payment_gateway');
            $db->where('wallet_type', $wallet_type);
            $payment_method_data = $db->getOne('xun_payment_method', 'id, address');

            if ($payment_method_data) {
                $address = $payment_method_data['address'];
            } else {

                $pg_addr_param['business_id'] = $business_id;
                $pg_addr_param['wallet_type'] = $wallet_type;
                $returnPgAddress = $this->generate_new_pg_address($pg_addr_param);

                if ($returnPgAddress['code'] == 0) {
                    return $returnPgAddress;
                }

                $address = $returnPgAddress['data']['address'];

                $insert_payment_method_arr = array(
                    "payment_tx_id" => $payment_tx_id,
                    "address" => $address,
                    "wallet_type" => $wallet_type,
                    "type" => "payment_gateway",
                    "created_at" => date("Y-m-d H:i:s")
                );

                $db->insert('xun_payment_method', $insert_payment_method_arr);
            }
        } else {
            $address = $pg_transaction["address"];
        }

        $amount = $pg_transaction["amount"];

        $currency_info = $xunCurrency->get_currency_info($wallet_type);
        $unit_conversion = $currency_info["unit_conversion"];

        $amount = $setting->setDecimalWithNoOfDP($amount, log10($unit_conversion));

        $exchange_rate = $xunCurrency->get_rate($wallet_type, $fiat_currency_id);
        $usd_amount = bcmul((string) $amount, (string) $exchange_rate, 2);

        $fiat_details = array(
            "currency" => $fiat_currency_id,
            "amount" => $usd_amount
        );

        $timeoutDuration = $setting->systemSetting['nuxpayPaymentTimeout'];

        $db->where('currency_id', $wallet_type);
        $symbol_data = $db->getOne('xun_marketplace_currencies', 'id, display_symbol');

        $currency_name = $symbol_data['display_symbol'];

        if ($is_direct) {
            $direct_url = $config['nuxpayUrl'] . "/direct_buy.php";
        } else {
            $direct_url = "";
        }

        $return_data = [];
        $return_data['timeout_duration'] = $timeoutDuration;
        $return_data['business_id'] = $business_id;
        $return_data["business_name"] = $business_name;
        $return_data["amount"] = $amount;
        $return_data["currency"] = $wallet_type;
        $return_data["currency_name"] = $currency_name;
        $return_data["payment_id"] = $pg_transaction["payment_id"];
        $return_data["address"] = $address;
        $return_data["redirect_url"] = $pg_transaction["redirect_url"];
        $return_data["fiat_details"] = $fiat_details;
        $return_data["profile_picture_url"] = $profile_picture_url;
        $return_data["gateway_type"] = $gateway_type;
        $return_data["switch_currency"] = $switch_currency;
        $return_data["is_direct"] = $is_direct;
        $return_data["direct_url"] = $direct_url;
        $return_data["direct_detail"] = $direct_detail;
        $return_data["payment_channel"] = $payment_channel;

        return array(
            "code" => 1,
            "message" => "SUCCESS",
            "message_d" => $this->get_translation_message('B00226') /*Success*/,
            "data" => $return_data,
        );
    }

    /*
    public function payment_gateway_get_transaction_status($params)
    {
        global $xunCrypto, $xunCurrency;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;

        $transaction_token = trim($params["transaction_token"]);

        if ($transaction_token == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00414'));
        }

        $db->where("transaction_token", $transaction_token);
        $pg_transaction = $db->getOne("xun_payment_gateway_payment_transaction", "id, status, redirect_url, crypto_history_id, amount, wallet_type");

        if(!$pg_transaction){
            return array("code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00434')
            );
        }

        $db->where("a.id", $pg_transaction["crypto_history_id"]);
        $db->join("xun_business b", "a.business_id=b.user_id", "LEFT");
        $xun_crypto_history = $db->getOne("xun_crypto_history a", "a.id, a.transaction_date, a.amount, a.transaction_id, a.business_id, b.name");

        $db->where("cryptocurrency_id", $pg_transaction["wallet_type"]);
        $unit = $db->getValue("xun_cryptocurrency_rate", "unit");

        $db->where("transaction_hash", $xun_crypto_history["transaction_id"]);
        $exchange_rate = $db->getValue("xun_crypto_callback", "exchange_rate");

        $return_data = [];
        $return_data["status"] = $pg_transaction["status"];
        $return_data["transaction_token"] = $transaction_token;
        $return_data["transaction_datetime"] = $xun_crypto_history["transaction_date"];
        $return_data["amount"] = $pg_transaction["amount"];
        $return_data["currency"] = $pg_transaction["wallet_type"];
        $return_data["transaction_id"] = $xun_crypto_history["transaction_id"];
        $return_data["unit"] = $unit;
        $return_data["exchange_rate"] = $exchange_rate;

        return array(
            "code" => 1,
            "message" => "SUCCESS",
            "message_d" => $this->get_translation_message('B00226'),
            "data" => $return_data,
            "redirect_url" => $pg_transaction["redirect_url"],
            "merchant_name" => $xun_crypto_history["name"]
        );
    }
*/

    public function payment_gateway_get_transaction_status($params)
    {
        global $xunCrypto, $xunCurrency;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;

        $transaction_token = trim($params["transaction_token"]);

        if ($transaction_token == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00414') /*Transaction token cannot be empty*/);
        }

        $db->where('transaction_token', $transaction_token);
        $transactiontype = $db->getOne("xun_payment_gateway_payment_transaction", "gw_type");

        if ($transactiontype['gw_type'] == 'PG') {
            $db->where('cc.status', 'received');
            $db->where("pt.transaction_token", $transaction_token);
            $db->join("xun_cryptocurrency_rate r", "r.cryptocurrency_id=pt.wallet_type", "INNER");
            $db->join("xun_business b", "b.user_id=pt.business_id", "INNER");
            $db->where('cc.transaction_date', array(date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s")) - 30), date("Y-m-d H:i:s")), 'BETWEEN');
            // $db->join("xun_crypto_callback cc", "pt.address=cc.reference_address AND pt.amount<=cc.amount AND pt.wallet_type=cc.wallet_type AND cc.created_at>=pt.created_at", "INNER");
            $db->join('xun_payment_transaction pt1', 'pt.transaction_token = pt1.transaction_token', 'LEFT');
            $db->join('xun_payment_method pm', 'pm.payment_tx_id = pt1.id', 'LEFT');
            $db->join("xun_crypto_history cc", "pm.address=cc.address AND pm.wallet_type=cc.wallet_type AND cc.created_at>=pt.created_at", "INNER");
            $pg_transaction = $db->getOne("xun_payment_gateway_payment_transaction pt", "pt.status, pt.transaction_token, pt.address, pt.created_at, cc.amount, pt.wallet_type, cc.transaction_id, cc.exchange_rate, pt.redirect_url, b.name, r.unit");
        } else {
            $db->where('cc.status', 'success');
            $db->where("pt.transaction_token", $transaction_token);
            $db->join("xun_cryptocurrency_rate r", "r.cryptocurrency_id=pt.wallet_type", "INNER");
            $db->join("xun_business b", "b.user_id=pt.business_id", "INNER");
            $db->where('cc.transaction_date', array(date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s")) - 30), date("Y-m-d H:i:s")), 'BETWEEN');
            // $db->join("xun_crypto_callback cc", "pt.address=cc.reference_address AND pt.amount<=cc.amount AND pt.wallet_type=cc.wallet_type AND cc.created_at>=pt.created_at", "INNER");
            $db->join('xun_payment_transaction pt1', 'pt.transaction_token = pt1.transaction_token', 'LEFT');
            $db->join('xun_payment_method pm', 'pm.payment_tx_id = pt1.id', 'LEFT');
            $db->join("xun_crypto_history cc", "pm.address=cc.recipient_external AND pm.wallet_type=cc.wallet_type AND cc.created_at>=pt.created_at", "INNER");
            $pg_transaction = $db->getOne("xun_payment_gateway_payment_transaction pt", "pt.status, pt.transaction_token, pt.address, pt.created_at, cc.amount, pt.wallet_type, cc.transaction_id, cc.exchange_rate, pt.redirect_url, b.name, r.unit");
        }

        // $db->where('cc.status', 'received');
        // $db->where("pt.transaction_token", $transaction_token);
        // $db->join("xun_cryptocurrency_rate r", "r.cryptocurrency_id=pt.wallet_type", "INNER");
        // $db->join("xun_business b", "b.user_id=pt.business_id", "INNER");
        // $db->where('cc.transaction_date', array(date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s")) -30), date("Y-m-d H:i:s") ), 'BETWEEN' );
        // // $db->join("xun_crypto_callback cc", "pt.address=cc.reference_address AND pt.amount<=cc.amount AND pt.wallet_type=cc.wallet_type AND cc.created_at>=pt.created_at", "INNER");
        // $db->join('xun_payment_transaction pt1', 'pt.transaction_token = pt1.transaction_token', 'LEFT');
        // $db->join('xun_payment_method pm', 'pm.payment_tx_id = pt1.id', 'LEFT');
        // $db->join("xun_crypto_history cc", "pm.address=cc.address AND pm.wallet_type=cc.wallet_type AND cc.created_at>=pt.created_at", "INNER");
        // $pg_transaction = $db->getOne("xun_payment_gateway_payment_transaction pt", "pt.status, pt.transaction_token, pt.address, pt.created_at, cc.amount, pt.wallet_type, cc.transaction_id, cc.exchange_rate, pt.redirect_url, b.name, r.unit");

        if (!$pg_transaction) {
            $db->where('transaction_token', $transaction_token);
            $pg_payment_transaction = $db->getOne('xun_payment_gateway_payment_transaction', 'status, redirect_url');

            $data = array(
                "status" => 'cancelled',
            );
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00434'), /*Invalid transaction.*/
                "data" => $data,
                "redirect_url" => $pg_payment_transaction['redirect_url'],
            );
        }

        $return_data = [];
        $return_data["status"] = $pg_transaction["status"];
        $return_data["transaction_token"] = $pg_transaction["transaction_token"];
        $return_data["transaction_datetime"] = $pg_transaction["created_at"];
        $return_data["address"] = $pg_transaction["address"];
        $return_data["amount"] = $pg_transaction["amount"];
        $return_data["currency"] = $pg_transaction["wallet_type"];
        $return_data["transaction_id"] = $pg_transaction["transaction_id"];
        $return_data["unit"] = $pg_transaction["unit"];
        $return_data["exchange_rate"] = $pg_transaction["exchange_rate"];

        return array(
            "code" => 1,
            "message" => "SUCCESS",
            "message_d" => $this->get_translation_message('B00226') /*Success*/,
            "data" => $return_data,
            "redirect_url" => $pg_transaction["redirect_url"],
            "merchant_name" => $pg_transaction["name"]
        );
    }

    public function nux_pay_homepage($params)
    {
        $db = $this->db;

        $crypto_history = $this->get_crypto_history();

        foreach ($crypto_history as &$data) {
            $data['transaction_type'] = "external";
        }

        $return_data = [];
        $return_data["transaction_listing"] = $crypto_history ? $crypto_history : [];
        return array(
            "code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00234') /*"Homepage"*/,
            "data" => $return_data
        );
    }

    public function get_crypto_pricing($params) {
        $db=$this->db;
        $post = $this->post;
        $url = 'http://thenuxpricing.com/modules/thenuxphp/thenux_webservices.php';
        $returnResult = array();
        $cryptoData = array();
        $cryptoImages = array();

        $params = array(
            "command" => "partnerGetPricing",
            "params" => array(
                "page" => 1,
                "page_size" => "1000",
                "order" => "ASC",
                "partner_name" => "crypto",
                "access_token" => "LNg7p5baBeMtsvZYVK75BkUj7zA2LSNR"
            )
        );
        $result = $post->curl_post($url, $params, 0);

        if ($result['code'] != 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        if (count($result['data']['pricingData']) != 0) {
            $count = 1;
            foreach ($result['data']['pricingData'] as $crypto) {
                if (
                    $crypto['cryptocurrency_id'] == 'bitcoin' ||
                    $crypto['cryptocurrency_id'] == 'ethereum' ||
                    $crypto['cryptocurrency_id'] == 'bitcoincash' ||
                    $crypto['cryptocurrency_id'] == 'litecoin' ||
                    $crypto['cryptocurrency_id'] == 'tetherUSD' ||
                    $crypto['cryptocurrency_id'] == 'dogecoin' ||
                    $crypto['cryptocurrency_id'] == 'tron' ||
                    $crypto['cryptocurrency_id'] == 'filecoin' ||
                    $crypto['cryptocurrency_id'] == 'cardano' ||
                    $crypto['cryptocurrency_id'] == 'bnb' ||
                    $crypto['cryptocurrency_id'] == 'ripple' ||
                    $crypto['cryptocurrency_id'] == 'sola' ||
                    $crypto['cryptocurrency_id'] == 'polkadot' ||
                    $crypto['cryptocurrency_id'] == 'usd-coin' ||
                    $crypto['cryptocurrency_id'] == 'luna' ||
                    $crypto['cryptocurrency_id'] == 'uniswap' ||
                    $crypto['cryptocurrency_id'] == 'binanceusd-erc20' ||
                    $crypto['cryptocurrency_id'] == 'chainlinktoken' ||
                    $crypto['cryptocurrency_id'] == 'avalanche' ||
                    $crypto['cryptocurrency_id'] == 'algorand' ||
                    $crypto['cryptocurrency_id'] == 'wrappedbtc' ||
                    $crypto['cryptocurrency_id'] == 'matic-erc20' ||
                    $crypto['cryptocurrency_id'] == 'ftx-token' ||
                    $crypto['cryptocurrency_id'] == 'cosmos' ||
                    $crypto['cryptocurrency_id'] == 'stellar' ||
                    $crypto['cryptocurrency_id'] == 'vechain-erc20' ||
                    $crypto['cryptocurrency_id'] == 'ethereum-classic' ||
                    $crypto['cryptocurrency_id'] == 'dai' ||
                    $crypto['cryptocurrency_id'] == 'pancakeswap-token' ||
                    $crypto['cryptocurrency_id'] == 'crypto-com-chain'
                ) {
                    $cryptoName = '';
                    if ($crypto['unit'] == 'FIL') {
                        $cryptoName = 'Filecoin';
                    } else {
                        $cryptoName = $crypto['name'];
                    }

                    $cryptoData[] = array(
                        'no' => $count,
                        'name' => $cryptoName . ' (' . strtoupper($crypto['unit']) . ')',
                        'price' => number_format($crypto['value'], 2),
                        'price_change' => number_format($crypto['price_change_percentage_24h'], 3) . '%',
                        'trade' => $crypto['unit']
                    );
                    if ($crypto['unit'] == 'eth') {
                        $cryptoImages[] = 'https://s3-ap-southeast-1.amazonaws.com/com.thenux.image/assets/xchange/currency/cryptocurrency/ethereum.png';
                    } else if ($crypto['unit'] == 'bch') {
                        $cryptoImages[] = 'https://s3-ap-southeast-1.amazonaws.com/com.thenux.image/assets/xchange/currency/cryptocurrency/bitcoincash.png';
                    } else {
                        $cryptoImages[] = $crypto['image'];
                    }
                    $count++;
                }
            }
        }
        $returnResult = array(
            'crypto' => $cryptoData,
            'image' => $cryptoImages
        );
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00234') /*"Homepage"*/, "data" => $returnResult);
    }

    private function get_crypto_history()
    {
        $db = $this->db;
        $payment_gateway_service = new XunPaymentGatewayService($db);

        $query_obj = new stdClass();
        $query_obj->walletType = "tetherusd";
        $query_obj->type = "in";
        $query_obj->status = "success";
        $query_obj->orderBy = "DESC";

        $columns = "id, transaction_id, amount, wallet_type, created_at";
        $limit = 10;
        $crypto_history = $payment_gateway_service->getPaymentGatewayHistory($query_obj, $limit, $columns);
        return $crypto_history;
    }

    public function send_nuxpay_notification($tag, $message)
    {
        $general = $this->general;
        $params["tag"] = $tag;
        $params["message"] = $message;

        $general->send_thenux_notification($params, "thenux_pay");

        //return $xunXmpp;
    }

    public function get_nuxpay_user_details($user_id, $col = null)
    {
        $db = $this->db;

        $db->where('id',  $user_id);
        //$db->where('register_site', 'nuxpay');
        $xun_user = $db->getOne('xun_user', $col);

        return $xun_user;
    }

    public function nuxpay_forgot_password($params, $source, $xunEmail)
    {
        global $xunSms;

        $db = $this->db;
        $general = $this->general;

        $emailMobile = $params['emailMobile'];

        $db->where(" (username='" . $emailMobile . "' OR (email='" . $emailMobile . "' AND email_verified=1)) ");
        $db->where('register_site', $source);
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/, 'source' => $source);
        }
        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];

        $db->where('user_id', $user_id);
        $db->where(" ((main_mobile='" . $emailMobile . "' AND main_mobile_verified=1) OR (email='" . $emailMobile . "' AND email_verified=1)) ");
        $business_account = $db->getOne('xun_business_account');

        // $new_password = $general->generateAlpaNumeric(8);
        $new_password = $general->generateRandomNumber(5);
        $hash_password = password_hash($new_password, PASSWORD_BCRYPT);

        $reset_pass = array(
            "password" => $hash_password,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('user_id', $user_id);
        $updated = $db->update('xun_business_account', $reset_pass);

        if (!$updated) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        $translations_message = $this->get_translation_message('B00312') /*%%companyName%%: Your temporary password is %%newPassword%% */;

        if (strtolower($source) == "ppay") {
            $source = "PPAY";
        }

        $db->where('source', $source);
        $site = $db->getOne('site');
        $Prefix = $site['otp_prefix'];

        if ($Prefix != ""){
            $source = $Prefix;
        }

        if ($business_account['main_mobile_verified'] && $business_account['main_mobile'] == $emailMobile) {

            $return_message = str_replace("%%companyName%%", $source, $translations_message);
            $return_message2 = str_replace("%%newPassword%%", $new_password, $return_message);
            $newParams["message"] = $return_message2;
            $newParams["recipients"] = $emailMobile;
            $newParams["ip"] = $ip;
            $newParams["companyName"] = $source;
            $xunSms->send_sms($newParams);

            $message_d = $this->get_translation_message('B00227'); /*A temporary password has been sent to your registered phone number.*/
        }

        if ($business_account['email_verified'] && $business_account['email'] == $emailMobile) {

            $emailDetail = $xunEmail->getForgotPasswordEmail($source, $new_password);

            $emailParams["subject"] = $emailDetail['emailSubject'];
            $emailParams["body"] = $emailDetail['html'];
            $emailParams["recipients"] = array($emailMobile);
            $emailParams["emailFromName"] = $emailDetail['emailFromName'];
            $emailParams["emailAddress"] = $emailDetail['emailAddress'];
            $emailParams["emailPassword"] = $emailDetail['emailPassword'];
            $msg = $general->sendEmail($emailParams);

            $message_d = $this->get_translation_message('B00322'); /*A temporary password has been sent to your registered email.*/
        }

        $message = "Merchant Name: " . $nickname . "\n";
        $message .= "Time: " . date("Y-m-d H:i:s") . "\n";
        $tag = "Reset Password";
        $return = $this->send_nuxpay_notification($tag, $message);

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $message_d);
    }

    public function get_supported_fiat_currency($params)
    {
        global $xunCurrency;

        $columns = 'a.currency, b.name';
        $supported_currency = $xunCurrency->get_supported_fiat_currency_rate($columns);

        $return_data = [];
        $return_data["currencies"] = $supported_currency;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00228') /*TheNux supported fiat currencies.*/, "data" => $return_data);
    }

    public function get_crypto_rate($params)
    {
        global $xunMarketplace, $xunCurrency, $xunPay;
        $db = $this->db;

        $business_id = $params["business_id"];
        $api_key = $params["api_key"];
        $amount = $params["amount"];
        $fiat_currency_id = $params["fiat_currency_id"];
        $wallet_type = $params["wallet_type"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "Business ID cannot be empty");
        }

        if ($api_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00416') /*apikey cannot be empty*/, "developer_msg" => "apikey cannot be empty");
        }

        if ($amount == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00417') /*Amount cannot be empty*/, "developer_msg" => "Amount cannot be empty");
        }

        if ($fiat_currency_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00418') /*Fiat Currency ID cannot be empty*/, "developer_msg" => "Fiat Currency ID cannot be empty");
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "Wallet Type cannot be empty");
        }

        $db->where('apikey', $api_key);
        $db->where('expired_at', date("Y-m-d H:i:s"), ">=");
        $db->where('status', 1);
        $crypto_apikey = $db->getOne('xun_crypto_apikey');

        // if(!$crypto_apikey){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid apikey", "developer_msg" => "Invalid apikey");
        // }

        $db->where('user_id', $business_id);
        $xun_business = $db->getOne('xun_business_account');

        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business does not exist.");
        }

        $db->where('currency_id', $wallet_type);
        $xun_coins = $db->getOne('xun_coins');

        if (!$xun_coins) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00420') /*Invalid Wallet Type*/, "developer_msg" => "Wallet Type not in xun_coins table");
        }

        //        $currency_rate = $xunCurrency->get_currency_rate(array($fiat_currency_id));
        //    $currency_rate =  $xunMarketplace->get_currency_rate_in_usd($fiat_currency_id);
        //    $amount_in_usd = bcdiv($amount, $currency_rate, 2);

        //    $currency = $this->get_cryptocurrency_rate($wallet_type);

        $decimal_place = $xunCurrency->get_currency_decimal_places($wallet_type);
        $converted_amount = bcdiv($amount_in_usd, $currency, $decimal_place);

        $currency_info = $xunCurrency->get_currency_info($wallet_type);

        $currency_unit = $currency_info["symbol"];
        $uc_currency_unit = strtoupper($currency_unit);

        $exchange_rate_params = array(
            "product_currency" => $fiat_currency_id,
            "system_currency" => "usd",
        );

        $exchange_rate_return = $xunPay->get_product_exchange_rate($exchange_rate_params);
        $exchange_rate_arr = $exchange_rate_return["exchange_rate_arr"];
        $exchange_rate = $exchange_rate_arr[$wallet_type . "/" . $fiat_currency_id];

        $converted_amount = bcdiv($amount, $exchange_rate,  $decimal_place);

        $data = array(
            "crypto_converted_amount" => $converted_amount,
            "wallet_type" => $wallet_type,
            "currency_unit" => $uc_currency_unit,
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00229') /*Cryptocurrency Conversion Rate*/, "data" => $data);
    }

    public function get_crypto_conversion_rate($params)
    {
        global $xunMarketplace, $xunCurrency, $xunPay, $config;
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $amount = $params["amount"];
        $fiat_currency_id = strtolower($params["fiat_currency_id"]);
        $wallet_type = $params["wallet_type"];
        $provider = $params['provider'];
        $type = $params['type'];


        if ($amount == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00417') /*Amount cannot be empty*/, "developer_msg" => "Amount cannot be empty");
        }

        if ($fiat_currency_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00418') /*Fiat Currency ID cannot be empty*/, "developer_msg" => "Fiat Currency ID cannot be empty");
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "Wallet Type cannot be empty");
        }


        $db->where('currency_id', $wallet_type);
        $xun_coins = $db->getOne('xun_coins');

        if (!$xun_coins) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00420') /*Invalid Wallet Type*/, "developer_msg" => "Wallet Type not in xun_coins table");
        }

        $currency_info = $xunCurrency->get_currency_info($wallet_type);
        $currency_unit = $currency_info["symbol"];
        if($currency_unit == "trx-usdt")
        {
            $currency_unit = "usdt";
        }
        $uc_currency_unit = strtoupper($currency_unit);

        $exchange_rate_params = array(
            "product_currency" => $fiat_currency_id,
            "system_currency" => $wallet_type,
        );

        $exchange_rate_return = $xunPay->get_product_exchange_rate($exchange_rate_params);
        $exchange_rate_arr = $exchange_rate_return["exchange_rate_arr"];
        $exchange_rate = $exchange_rate_arr[$wallet_type . "/" . $fiat_currency_id];
        $decimal_place = $xunCurrency->get_currency_decimal_places($wallet_type);
        //$converted_amount = bcmul($amount, $exchange_rate,  $decimal_place);
        $converted_amount = $xunCurrency->get_conversion_amount($fiat_currency_id, $wallet_type, $amount);

        $fiat_converted_amount = $xunCurrency->get_conversion_amount($wallet_type, $fiat_currency_id, $amount);

        $uc_fiat_currency_id = strtoupper($fiat_currency_id);

        $db->where('company', $provider);
        $provider_id = $db->getValue('provider', 'id');


        if ($provider_id) {
            //GET Min and Max Amount in USD
            $db->where('provider_id', $provider_id);
            $db->where('name', array('minAmount', 'maxAmount', 'minCryptoAmount'), 'IN');
            $db->where('type', $uc_currency_unit);
            $provider_setting_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

            $db->where('provider_id', $provider_id);
            $db->where('name', array('supportedCurrencies', 'fiatCurrencyList'), 'IN');
            $global_provider_setting_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

            if ($provider_setting_data) {
                $min_amount_usd = $provider_setting_data['minAmount'];
                $max_amount_usd = $provider_setting_data['maxAmount'];

                $min_amount = $xunCurrency->get_conversion_amount($fiat_currency_id, 'usd', $min_amount_usd);
                $max_amount = $xunCurrency->get_conversion_amount($fiat_currency_id, 'usd', $max_amount_usd);

                $min_crypto_amount = bcdiv($min_amount, $exchange_rate, 8);
                $max_crypto_amount = bcdiv($max_amount, $exchange_rate, 8);
            }
        }


        if ($provider == 'Simplex') {
            $simplex_margin_percentage = $setting->systemSetting['simplexMarginPercentage'];

            // $markup_converted_amount = bcmul($converted_amount, (string)((100 + $simplex_margin_percentage) / 100), 8);
            $fiat_converted_amount = bcmul($fiat_converted_amount, (string)((100 + $simplex_margin_percentage) / 100), 8);
            $supported_currencies = strtoupper($global_provider_setting_data['supportedCurrencies']);
            $supported_fiat_list = strtoupper($global_provider_setting_data['fiatCurrencyList']);
            $supported_currencies_arr = explode(",", $supported_currencies);
            $supported_fiat_currency_arr = explode(",", $supported_fiat_list);

            $crypto_rate_arr = $xunCurrency->get_cryptocurrency_rate(array($wallet_type));

            $crypto_price_usd = $crypto_rate_arr[$wallet_type];

            $db->where('provider_id', $provider_id);
            $db->where('name', array('minAmount', 'maxAmount'), 'IN');
            $db->orderBy('type', 'ASC');
            $min_max_amount_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

            $db->where('provider_id', $provider_id);
            $db->where('name', 'markupPercentage');
            $markup_percentage_data = $db->getOne('provider_setting', 'value');
            $markup_percentage = $markup_percentage_data['value'];


            $db->where('provider_id', $provider_id);
            $db->where('name', 'markdownPercentage');
            $markdown_percentage_data = $db->getOne('provider_setting', 'value');
            $markdown_percentage = $markdown_percentage_data['value'];

            if ($provider_setting_data) {
                $min_amount_usd = $provider_setting_data['minAmount'];
                $max_amount_usd = $provider_setting_data['maxAmount'];

                $db->where('symbol', $supported_currencies_arr, 'IN');
                $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies', null, 'id, symbol, currency_id');

                foreach ($supported_currencies_arr as $cryptocurrency_value) {
                    $currency_id = $marketplace_currencies[strtolower($cryptocurrency_value)]['currency_id'];

                    foreach ($supported_fiat_currency_arr as $fiat_currency_value) {

                        $min_amount = $xunCurrency->get_conversion_amount(strtolower($fiat_currency_value), 'usd', $min_amount_usd);
                        $max_amount = $xunCurrency->get_conversion_amount(strtolower($fiat_currency_value), 'usd', $max_amount_usd);

                        //markup min amount & markdown max amount
                        $min_amount = ((100 + $markup_percentage) / 100) * $min_amount;
                        $max_amount = ($max_amount - (($markdown_percentage / 100) * $max_amount));
                        //$max_amount = $max_amount / ((100+$markdown_percentage)/100); 

                        $min_crypto_amount = $xunCurrency->get_conversion_amount($currency_id, strtolower($fiat_currency_value), $min_amount);
                        $max_crypto_amount = $xunCurrency->get_conversion_amount($currency_id, strtolower($fiat_currency_value), $max_amount);

                        $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['min_amount'] = $min_amount;
                        $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['max_amount'] = $max_amount;

                        $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['min_crypto_amount'] = $min_crypto_amount;
                        $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['max_crypto_amount'] = $max_crypto_amount;

                        $exchange_rate_params = array(
                            "product_currency" => strtolower($fiat_currency_value),
                            "system_currency" => strtolower($currency_id),
                        );

                        $exchange_rate_return = $xunPay->get_product_exchange_rate($exchange_rate_params);
                        $exchange_rate_arr = $exchange_rate_return["exchange_rate_arr"];
                        $exchange_rate = $exchange_rate_arr[strtolower($currency_id) . "/" . strtolower($fiat_currency_value)];

                        $fiat_exchange_rate = bcdiv(1, $exchange_rate, 18);

                        $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['crypto_converted_amount'] = $exchange_rate;
                        $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['fiat_converted_amount'] = $fiat_exchange_rate;
                    }
                }
            }
        } else if ($provider == 'Xanpool') {

            $db->where('provider_id', $provider_id);
            $db->where('name', array('supportedCurrencies', 'fiatCurrencyList'), 'IN');
            $global_provider_setting_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

            $db->where('provider_id', $provider_id);
            $db->where('name', 'minCryptoAmount');
            $min_crypto_amount_data = $db->map('type')->ArrayBuilder()->get('provider_setting', null, 'name, value, type');

            $db->where('provider_id', $provider_id);
            $db->where('name', 'markupPercentage');
            $markup_percentage_data = $db->getOne('provider_setting', 'value');
            $markup_percentage = $markup_percentage_data['value'];

            $db->where('provider_id', $provider_id);
            $db->where('name', 'markdownPercentage');
            $markdown_percentage_data = $db->getOne('provider_setting', 'value');
            $markdown_percentage = $markdown_percentage_data['value'];

            $supported_currencies = strtoupper($global_provider_setting_data['supportedCurrencies']);
            $supported_fiat_list = strtoupper($global_provider_setting_data['fiatCurrencyList']);
            $supported_currencies_arr = explode(",", $supported_currencies);
            $supported_fiat_currency_arr = explode(",", $supported_fiat_list);

            $api_url = $config['xanpool_api_url'] . '/api/prices?currencies=' . strtoupper($supported_fiat_list) . '&cryptoCurrencies=' . $supported_currencies . '&type=' . $type;

            $curl_params = array();

            $result = $post->curl_xanpool($api_url, $curl_params, 'GET');

            $method_api_url = $config['xanpool_api_url'] . '/api/methods';

            $payment_method_result = $post->curl_xanpool($method_api_url, $curl_params, 'GET');

            $buy_data =  $payment_method_result['buy'];

            // print_r($supported_currencies_arr);
            $db->where('symbol', $supported_currencies_arr, 'IN');
            $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies', null, 'id, symbol, currency_id');
            foreach ($result as $result_key => $result_value) {

                $fiat_currency = $result_value['currency'];
                $cryptocurrency_symbol = $result_value['cryptoCurrency'];
                $selected_wallet_type = $marketplace_currencies[$cryptocurrency];
                $exchange_rate = $result_value['cryptoPrice'];
                $usd_exchange_rate = $result_value['cryptoPriceUsd'];

                if ($fiat_currency == $uc_fiat_currency_id && $cryptocurrency_symbol == $uc_currency_unit) {
                    $crypto_price_usd = $result_value['cryptoPriceUsd'];
                    $fiat_crypto_price = $result_value['cryptoPrice'];

                    $converted_amount = $fiat_crypto_price;
                    //The Fiat amount is too small for certain currency
                    $fiat_converted_amount = bcdiv($amount, $fiat_crypto_price, 18);
                }

                foreach ($buy_data as $key => $value) {
                    $method_arr = $value['methods'];

                    if ($value['currency'] == $fiat_currency) {

                        $min_amount = $method_arr[0]['min'];
                        $max_amount = $method_arr[0]['max'];

                        //markup min amount
                        $min_amount = ((100 + $markup_percentage) / 100) * $min_amount;

                        //markdown max amount
                        $max_amount = ($max_amount - (($markdown_percentage / 100) * $max_amount));

                        $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['min_amount'] = $method_arr[0]['min'];
                        $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['max_amount'] = $method_arr[0]['max'];
                    }
                }

                $minCrypto = $min_crypto_amount_data[$cryptocurrency_symbol]['value'];
                $minCryptoMarkup = ((100 + $markup_percentage) / 100) * $minCrypto;
                // provider setting crypto amount 
                // $min_crypto_amount = $provider_setting_data['minCryptoAmount'];
                // $min_crypto_amount = bcdiv($min_amount, $exchange_rate, 8);
                // $min_crypto_amount = ceil($min_crypto_amount, 8);

                // $max_crypto_amount = bcdiv($max_amount, $exchange_rate, 8);
                // $max_crypto_amount = bcdiv($max_amount, $exchange_rate, 8);
                // $max_crypto_amount = ceil($max_crypto_amount, 8);

                $fiat_exchange_rate = bcdiv(1, $exchange_rate, 18);

                // $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['min_crypto_amount'] = $min_crypto_amount_data[$cryptocurrency_symbol]['value'];
                $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['min_crypto_amount'] = $minCryptoMarkup;
                $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['max_crypto_amount'] = bcdiv($max_amount, $exchange_rate, 8);
                $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['crypto_converted_amount'] = $exchange_rate;
                $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['fiat_converted_amount'] = $fiat_exchange_rate;
            }
        } else {
            $markup_fiat_amount = $fiat_converted_amount;
        }

        $data = array(
            "min_amount" => $min_amount ? $min_amount : '',
            "max_amount" => $max_amount ? $max_amount : '',
            "min_crypto_amount" => $min_crypto_amount ? $min_crypto_amount : '',
            "max_crypto_amount" => $max_crypto_amount ? $max_crypto_amount : '',
            "crypto_converted_amount" => $converted_amount,
            "fiat_converted_amount" => $fiat_converted_amount,
            "usd_converted_amount" => $crypto_price_usd,
            "exchange_rate" => $exchange_rate,
            "wallet_type" => $wallet_type,
            "currency_unit" => $uc_currency_unit,
            "currency_setting_data" => $currency_setting_data,
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00229') /*Cryptocurrency Conversion Rate*/, "data" => $data);
    }

    public function get_cryptocurrency_rate($wallet_type)
    {
        $db = $this->db;

        $db->where('cryptocurrency_id', $wallet_type);
        $cryptocurrency_rate = $db->getOne('xun_cryptocurrency_rate', 'value');

        return $cryptocurrency_rate["value"];
    }

    public function get_pg_destination_address_list($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];
        $api_key = $params["api_key"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "Business ID cannot be empty");
        }

        if ($api_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00416') /*apikey cannot be empty*/, "developer_msg" => "apikey cannot be empty");
        }

        $db->where('apikey', $api_key);
        $db->where('expired_at', date("Y-m-d H:i:s"), ">=");
        $db->where('status', 1);
        $crypto_apikey = $db->getOne('xun_crypto_apikey');

        if (!$crypto_apikey) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00421') /*Invalid apikey*/, "developer_msg" => "Invalid apikey");
        }

        $db->where('user_id', $business_id);
        $xun_business = $db->getOne('xun_business_account');

        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business does not exist.");
        }

        $db->where('business_id', $business_id);
        $db->where('status', 1);
        $crypto_wallet = $db->get('xun_crypto_wallet', null, 'id');

        foreach ($crypto_wallet as $value) {
            $wallet_id = $value["id"];

            $wallet_id_arr[] = $wallet_id;
        }

        $db->where('wallet_id', $wallet_id_arr, "IN");
        $dest_address = $db->get('xun_crypto_destination_address', null, 'type, destination_address');

        $wallet_type_arr = [];
        foreach ($dest_address as $dest_key => $dest_value) {
            $wallet_type = $dest_value["type"];

            $wallet_type_arr[] = $wallet_type;
        }

        $db->where('currency_id', $wallet_type_arr, 'IN');
        $marketplace_currencies = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies', null, 'name, symbol, currency_id');

        $address_list = [];
        foreach ($dest_address as $key => $value) {
            $wallet_type = $value["type"];
            $destination_address = $value["destination_address"];
            $wallet_name = $marketplace_currencies[$wallet_type]["name"];

            $address_arr = array(
                "wallet_type" => $wallet_type,
                "wallet_name" => $wallet_name,
                "destination_address" => $destination_address,
            );

            $address_list[] = $address_arr;
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00230') /*Destination Address List*/, "data" => $address_list);
    }

    public function get_transaction_gross_volume($params)
    {
        $db = $this->db;

        $from_datetime = $params["from_datetime"];
        $to_datetime = $params["to_datetime"];
        $wallet_type = $params["wallet_type"];
        $business_id = $params["business_id"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00390') /*Business ID is required.*/, "developer_msg" => "Business ID cannot be empty");
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00207') /*"Wallet type is required.*/, "developer_msg" => "Wallet type cannot be empty");
        }

        if ($from_datetime == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00422') /*From datetime is required.*/, "developer_msg" => "From datetime cannot be empty");
        }

        if ($to_datetime == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00423') /*To datetime is required.*/, "developer_msg" => "To datetime cannot be empty");
        }

        $db->where('id', $business_id);
        $db->where('type', 'business');
        $business_result = $db->getOne('xun_user');

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $db->where('currency_id', $wallet_type);
        $xun_coins = $db->getOne('xun_coins');

        if (!$xun_coins) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00424') /*Wallet type does not exist.*/, "developer_msg" => "Wallet type not found in xun_coins table.");
        }

        $dateFrom = date("Y-m-d H:00:00", $from_datetime);
        $dateTo = date("Y-m-d H:00:00", $to_datetime);

        $d1 = strtotime($dateFrom);
        $d2 = strtotime($dateTo);

        $diff = $d2 - $d1;
        $hours = $diff / (60 * 60); //get the difference in hours

        $chart_data = [];
        $gross_list = [];

        //loop the hours and push each hour into the date arr
        for ($i = 0; $i <= $hours; $i++) {
            if ($i == 0) {
                $date_time = $dateFrom;
            } else {
                $date_time = date('Y-m-d H:00:00', strtotime('+1 hour', strtotime($date_time)));
            }

            $pre_gross_arr = array(
                "date" => $date_time,
                "value" => strval(0)
            );

            $gross_list[$date_time] = $pre_gross_arr;
        }


        if ($from_datetime) {
            $db->where('created_at', date("Y-m-d H:i:s", $from_datetime), '>=');
        }

        if ($to_datetime) {
            $db->where('created_at', date("Y-m-d H:i:s", $to_datetime), "<=");
        }
        $db->where('business_id', $business_id);
        $db->where('wallet_type', $wallet_type);
        $db->orderBy('created_at', "ASC");
        $copyDb = $db->copy();
        $copyDb2 = $db->copy();
        $crypto_history = $db->get('xun_crypto_history');

        $total_transaction = $copyDb->getValue('xun_crypto_history', 'count(id)');

        $sum_amount = $copyDb2->get('xun_crypto_history', null, 'sum(amount) as payout, sum(amount_receive) as total_tx_volume, wallet_type');

        $payout = $sum_amount[0]['payout'];
        $total_tx_volume = $sum_amount[0]['total_tx_volume'];
        //$wallet_type = $sum_amount[0]['wallet_type'];

        $db->where('currency_id', $wallet_type);
        $marketplace_currencies = $db->getOne('xun_marketplace_currencies', 'symbol');

        $symbol = $marketplace_currencies['symbol'];
        $uc_symbol = strtoupper($marketplace_currencies['symbol']);

        if ($crypto_history) {

            // $gross_volume_list = [];
            foreach ($crypto_history as $key => $value) {
                $created_at = $value["created_at"];
                $amount = $value["amount"];
                $dateWithHour = date("Y-m-d H:00:00", strtotime($created_at));
                $amount = $value["amount"];

                if ($gross_list[$dateWithHour]) {

                    $gross_amount = $gross_list[$dateWithHour]['value'];
                    $total_amount = $gross_amount + $amount;
                    $gross_list[$dateWithHour]["value"] = strval($total_amount);
                }
            }
        }
        $chart_data = array_values($gross_list);


        $data['total_transaction'] = (string) $total_transaction ? (string) $total_transaction : '0';
        $data['total_transaction_volume'] = (string) $total_tx_volume ? (string) $total_tx_volume : '0';
        $data['payout_amount'] = (string) $payout ? (string) $payout : '0';
        $data['currency_unit'] = $uc_symbol ? $uc_symbol : '';
        $data['chart_data'] = $chart_data;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00235') /*Gross Volume.*/, "data" => $data);
    }

    public function get_transaction_sales_data($params)
    {
        $db = $this->db;
        $time = $params['time'];
        $businessID = $params['business_id'];
        $data = array();
        $debug = array();

        // get user registration date
        $db->where('id', $businessID);
        $registrationDate = $db->getValue('xun_user', 'created_at');
        $firstDate = explode(' ', $registrationDate)[0];
        $firstDay = new DateTime($firstDate);
        $lastDay = new DateTime(date('Y-m-d'));
        $timeDifference = $firstDay->diff($lastDay);
        $startDate = '';

        switch ($time) {
            case "daily":
                if ($timeDifference->y == 0 && $timeDifference->m == 0 && $timeDifference < 20) {
                    if ($timeDifference->d < 2) {
                        $startDate = date('Y-m-d', strtotime('-2 day'));
                    } else {
                        $startDate = $firstDate;
                    }
                } else {
                    $startDate = date('Y-m-d H:i:s', strtotime('-20 days'));
                }
                $period = new DatePeriod(
                    new DateTime($startDate),
                    new DateInterval('P1D'),
                    new DateTime(date('Y-m-d H:i:s', strtotime('yesterday 23:59:59')))
                );

                $dailyData = array();
                foreach ($period as $key => $interval) {
                    $startInterval = $interval->format('Y-m-d');

                    // for debug purpose
                    $debug[] = "start: " . $startInterval;

                    // query
                    $db->where('user_id', $businessID);
                    $db->where('date', $startInterval);
                    $db->orderBy('user_id');
                    $paymentData = $db->get('xun_user_payments_summary', null, 'SUM(net_profit_usd) AS net_total');

                    // build data
                    foreach ($paymentData as $paymentRecord) {
                        $dailyData[] = array(
                            'date' => $startInterval,
                            'value' => is_null($paymentRecord['net_total']) ? '0.00' : number_format($paymentRecord['net_total'], 2)
                        );
                    }
                }

                // query TODAY's data, starts from finalTimeFrame
                $db->where('business_id', $businessID);
                $db->where('gw_type', 'PG');
                $db->where('status', 'failed', '!=');
                $db->where('created_at', date('Y-m-d 00:00:00'), '>=');
                $todayData = $db->get('xun_crypto_history');

                $todayNet = "0";
                if (count($todayData) != 0) {
                    foreach ($todayData as $todayRecord) {
                        $amountUSD = bcmul($todayRecord['amount'], $todayRecord['exchange_rate'], 6);
                        $todayNet = bcadd($todayNet, $amountUSD, 6);
                    }
                }

                // for debug purpose
                $debug[] = "start: " . date('Y-m-d');

                // build data 
                $dailyData[] = array(
                    'date' => date('Y-m-d'),
                    'value' => number_format($todayNet, 2),
                );

                $data['debug'] = $debug;
                $data['report'] = $dailyData;
                break;

            case "weekly":
                if ($timeDifference->y == 0 && $timeDifference->m < 3) {
                    if ($timeDifference->m < 2) {
                        $startDate = date('Y-m-d', strtotime('-1 month'));
                    } else {
                        $startDate = $firstDate;
                    }
                } else {
                    $startDate = date('Y-m-d H:i:s', strtotime('-3 month'));
                }
                $period = new DatePeriod(
                    new DateTime($startDate),
                    new DateInterval('P7D'),
                    new DateTime(date('Y-m-d H:i:s', strtotime('yesterday 23:59:59')))
                );

                $weeklyData = array();
                $initialTimeFrame = '';
                $finalTimeFrame = '';
                foreach ($period as $key => $interval) {
                    if ($key == 0) {
                        $initialTimeFrame = $interval->format('Y-m-d');
                        continue;
                    }
                    $currentTimeFrame = $interval->format('Y-m-d');
                    $startInterval = $initialTimeFrame;
                    $endInterval = $currentTimeFrame;

                    // for debug purpose
                    $debug[] = "start: " . $startInterval . " end: " . $endInterval;

                    // query
                    $db->where('user_id', $businessID);
                    $db->where('date', $startInterval, '>=');
                    $db->where('date', $endInterval, '<');
                    $db->orderBy('user_id');
                    $paymentData = $db->get('xun_user_payments_summary', null, 'SUM(net_profit_usd) AS net_total');

                    // build data
                    foreach ($paymentData as $paymentRecord) {
                        $weeklyData[] = array(
                            'date' => $currentTimeFrame,
                            'value' => is_null($paymentRecord['net_total']) ? '0.00' : number_format($paymentRecord['net_total'], 2),
                        );
                    }

                    // update initial timeframe
                    $initialTimeFrame = $currentTimeFrame;
                    $finalTimeFrame = $endInterval;
                }

                // query TODAY's data, starts from finalTimeFrame
                $db->where('business_id', $businessID);
                $db->where('gw_type', 'PG');
                $db->where('status', 'failed', '!=');
                $db->where('created_at', $finalTimeFrame . " 00:00:00", '>=');
                $todayData = $db->get('xun_crypto_history');

                $todayNet = "0";
                if (count($todayData) != 0) {
                    foreach ($todayData as $todayRecord) {
                        $amountUSD = bcmul($todayRecord['amount'], $todayRecord['exchange_rate'], 6);
                        $todayNet = bcadd($todayNet, $amountUSD, 6);
                    }
                }

                // for debug purpose
                $debug[] = "start: " . $finalTimeFrame . " end: " . date('Y-m-d');

                // build data 
                $weeklyData[] = array(
                    'date' => date('Y-m-d'),
                    'value' => number_format($todayNet, 2),
                );

                $data['debug'] = $debug;
                $data['report'] = $weeklyData;

                break;

            case "monthly":
                if ($timeDifference->y == 0 && $timeDifference->m < 12) {
                    if ($timeDifference->m < 2) {
                        $startDate = date('Y-m-d', strtotime('-2 month'));
                    } else {
                        $startDate = $firstDate;
                    }
                } else {
                    $startDate = date('Y-m-d H:i:s', strtotime('-12 month'));
                }
                $period = new DatePeriod(
                    new DateTime($startDate),
                    new DateInterval('P1M'),
                    new DateTime(date('Y-m-d H:i:s', strtotime('yesterday 23:59:59')))
                );

                $monthlyData = array();
                $initialTimeFrame = '';
                $finalTimeFrame = '';
                foreach ($period as $key => $interval) {
                    if ($key == 0) {
                        $initialTimeFrame = $interval->format('Y-m-d');
                        continue;
                    }
                    $currentTimeFrame = $interval->format('Y-m-d');
                    $startInterval = $initialTimeFrame;
                    $endInterval = $currentTimeFrame;

                    // for debug purpose
                    $debug[] = "start: " . $startInterval . " end: " . $endInterval;

                    // query
                    $db->where('user_id', $businessID);
                    $db->where('date', $startInterval, '>=');
                    $db->where('date', $endInterval, '<');
                    $db->orderBy('user_id');
                    $paymentData = $db->get('xun_user_payments_summary', null, 'SUM(net_profit_usd) AS net_total');

                    // build data
                    foreach ($paymentData as $paymentRecord) {
                        $monthlyData[] = array(
                            'date' => $currentTimeFrame,
                            'value' => is_null($paymentRecord['net_total']) ? '0.00' : number_format($paymentRecord['net_total'], 2),
                        );
                    }

                    // update initial timeframe
                    $initialTimeFrame = $currentTimeFrame;
                    $finalTimeFrame = $endInterval;
                }

                // query TODAY's data, starts from finalTimeFrame
                $db->where('business_id', $businessID);
                $db->where('gw_type', 'PG');
                $db->where('status', 'failed', '!=');
                $db->where('created_at', $finalTimeFrame . " 00:00:00", '>=');
                $todayData = $db->get('xun_crypto_history');

                $todayNet = "0";
                if (count($todayData) != 0) {
                    foreach ($todayData as $todayRecord) {
                        $amountUSD = bcmul($todayRecord['amount'], $todayRecord['exchange_rate'], 6);
                        $todayNet = bcadd($todayNet, $amountUSD, 6);
                    }
                }

                // for debug purpose
                $debug[] = "start: " . $finalTimeFrame . " end: " . date('Y-m-d');

                // build data 
                $monthlyData[] = array(
                    'date' => date('Y-m-d'),
                    'value' => number_format($todayNet, 2),
                );

                $data['debug'] = $debug;
                $data['report'] = $monthlyData;
                break;

            case "yearly":
                if ($timeDifference->y < 6) {
                    if ($timeDifference->y < 2) {
                        $startDate = date('Y-m-d', strtotime('-2 year'));
                    } else {
                        $startDate = $firstDate;
                    }
                } else {
                    $startDate = date('Y-m-d H:i:s', strtotime('-6 year'));
                }
                $period = new DatePeriod(
                    new DateTime($startDate),
                    new DateInterval('P1Y'),
                    new DateTime(date('Y-m-d H:i:s', strtotime('yesterday 23:59:59')))
                );

                $yearlyData = array();
                $initialTimeFrame = '';
                $finalTimeFrame = '';

                foreach ($period as $key => $interval) {
                    if ($key == 0) {
                        $initialTimeFrame = $interval->format('Y-m-d');
                        continue;
                    }
                    $currentTimeFrame = $interval->format('Y-m-d');
                    $startInterval = $initialTimeFrame;
                    $endInterval = $currentTimeFrame;

                    // for debug purpose
                    $debug[] = "start: " . $startInterval . " end: " . $endInterval;

                    // query
                    $db->where('user_id', $businessID);
                    $db->where('date', $startInterval, '>=');
                    $db->where('date', $endInterval, '<');
                    $db->orderBy('user_id');
                    $paymentData = $db->get('xun_user_payments_summary', null, 'SUM(net_profit_usd) AS net_total');

                    // build data
                    foreach ($paymentData as $paymentRecord) {
                        $yearlyData[] = array(
                            'date' => $currentTimeFrame,
                            'value' => is_null($paymentRecord['net_total']) ? '0.00' : number_format($paymentRecord['net_total'], 2),
                        );
                    }

                    // update initial timeframe
                    $initialTimeFrame = $currentTimeFrame;
                    $finalTimeFrame = $endInterval;
                }

                // query TODAY's data, starts from finalTimeFrame
                $finalNet = "0.00";

                $db->where('user_id', $businessID);
                $db->where('date', $finalTimeFrame, '>=');
                $db->orderBy('user_id');
                $currentPaymentData = $db->get('xun_user_payments_summary', null, 'SUM(net_profit_usd) AS net_total');
                foreach ($currentPaymentData as $paymentRecord) {
                    $currentNet = is_null($paymentRecord['net_total']) ? "0.00" : $paymentRecord['net_total'];
                    $finalNet = bcadd($finalNet, $currentNet, 6);
                }

                $db->where('business_id', $businessID);
                $db->where('gw_type', 'PG');
                $db->where('status', 'failed', '!=');
                $db->where('created_at', date("Y-m-d 00:00:00"), '>=');
                $todayData = $db->get('xun_crypto_history');

                $todayNet = "0.00";
                if (count($todayData) != 0) {
                    foreach ($todayData as $todayRecord) {
                        $amountUSD = bcmul($todayRecord['amount'], $todayRecord['exchange_rate'], 6);
                        $todayNet = bcadd($todayNet, $amountUSD, 6);
                    }
                }

                $finalNet = bcadd($finalNet, $todayNet, 6);

                // build final data
                $debug[] = "start: " . $finalTimeFrame . " end: " . date('Y-m-d');
                $yearlyData[] = array(
                    'date' => date('Y-m-d'),
                    'value' => number_format($finalNet, 2),
                );

                $data['debug'] = $debug;
                $data['report'] = $yearlyData;
                break;
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00380') /* Get Sales Data Successful.*/, "data" => $data);
    }

    public function get_overall_sales_data($params)
    {
        $db = $this->db;
        $businessID = $params['business_id'];
        $data = array();
        $finalGross = "0.00";
        $finalNet = "0.00";
        $finalCount = "0";

        // query for all details from summary table
        $queryCol = array(
            'SUM(gross_profit_usd) AS gross_total',
            'SUM(net_profit_usd) AS net_total',
            'SUM(transaction_count) AS txn_total'
        );
        $db->where('user_id', $businessID);
        $db->orderBy('user_id');
        $paymentData = $db->get('xun_user_payments_summary', null, $queryCol);
        foreach ($paymentData as $paymentRecord) {
            $currentGross = is_null($paymentRecord['gross_total']) ? "0.00" : $paymentRecord['gross_total'];
            $currentNet = is_null($paymentRecord['net_total']) ? "0.00" : $paymentRecord['net_total'];
            $currentCount = is_null($paymentRecord['txn_total']) ? "0" : $paymentRecord['txn_total'];

            $finalGross = bcadd($finalGross, $currentGross, 6);
            $finalNet = bcadd($finalNet, $currentNet, 6);
            $finalCount = bcadd($finalCount, $currentCount);
        }

        // query for today's data from main table
        $db->where('business_id', $businessID);
        $db->where('gw_type', 'PG');
        $db->where('status', 'failed', '!=');
        $db->where('created_at', date("Y-m-d 00:00:00"), '>=');
        $todayData = $db->get('xun_crypto_history');
        $todayGross = "0.00";
        $todayNet = "0.00";
        if (count($todayData) != 0) {
            foreach ($todayData as $todayRecord) {
                $amountUSD = bcmul($todayRecord['amount'], $todayRecord['exchange_rate'], 6);
                $amountReceivedUSD = bcmul($todayRecord['amount_receive'], $todayRecord['exchange_rate'], 6);
                $todayNet = bcadd($todayNet, $amountUSD, 6);
                $todayGross = bcadd($todayGross, $amountReceivedUSD, 6);
            }
        }
        $todayCount = count($todayData);

        $finalGross = bcadd($finalGross, $todayGross, 6);
        $finalNet = bcadd($finalNet, $todayNet, 6);
        $finalCount = bcadd($finalCount, (string)$todayCount);

        $data = array(
            'gross' => number_format($finalGross, 2),
            'net' => number_format($finalNet, 2),
            'count' => number_format($finalCount)
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00380') /* Get Sales Data Successful.*/, "data" => $data);
    }

    public function get_pg_address_list($params)
    {
        $db = $this->db;

        $business_id = $params['business_id'];
        $wallet_type = $params['wallet_type'];
        $last_id = $params['last_id'] ? $params['last_id'] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 1;

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00390') /*Business ID is required.*/, "developer_msg" => "Business ID cannot be empty");
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00207') /*"Wallet type is required.*/, "developer_msg" => "Wallet type cannot be empty");
        }

        $db->where('id', $business_id);
        $db->where('type', 'business');
        $business_result = $db->getOne('xun_user');

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $db->where('currency_id', $wallet_type);
        $xun_coins = $db->getOne('xun_coins');

        if (!$xun_coins) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00424') /*Wallet type does not exist.*/, "developer_msg" => "Wallet type not found in xun_coins table.");
        }

        $limit = array($last_id, $page_size);

        $db->where('business_id', $business_id);
        $db->where('type', $wallet_type);
        $db->where('status', 1);
        $crypto_wallet = $db->getOne('xun_crypto_wallet');

        $wallet_id = $crypto_wallet['id'];
        $db->where('wallet_id', $wallet_id);
        $db->where('status', 1);
        $copyDb = $db->copy();
        $db->orderBy('id', 'DESC');
        $crypto_address = $db->get('xun_crypto_address', $limit);

        $totalRecord = $copyDb->getValue('xun_crypto_address', 'count(id)');

        $address_list = [];
        foreach ($crypto_address as $crypto_key => $crypto_value) {
            $address = $crypto_value["crypto_address"];
            $created_at = $crypto_value["created_at"];

            $deposit_address = $wallet_type . ":" . $address;
            $address_arr = array(
                "address" => $deposit_address,
                "created_at" => $created_at
            );
            $address_list[] = $address_arr;
        }
        $numRecord = count($address_list);
        $returnData["address_list"] = $address_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $numRecord;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        $returnData["last_id"] = $last_id + $numRecord;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00236') /*PG Address.*/, "data" => $returnData);
    }

    public function get_latest_transactions($params)
    {
        $db = $this->db;

        $business_id = $params['business_id'];
        $wallet_type = $params['wallet_type'];
        $last_id = $params['last_id'] ? $params['last_id'] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 5;

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00390') /*Business ID is required.*/, "developer_msg" => "Business ID cannot be empty");
        }

        $db->where('id', $business_id);
        $db->where('type', 'business');
        $business_result = $db->getOne('xun_user');

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        // $db->where('currency_id', $wallet_type);
        // $xun_coins = $db->getOne('xun_coins');

        // if(!$xun_coins){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet type does not exist.", "developer_msg" => "Wallet type not found in xun_coins table.");
        // }

        $limit = array($last_id, $page_size);

        if ($wallet_type) {
            $db->where('wallet_type', $wallet_type);
        }

        $db->where('business_id', $business_id);
        $copyDb = $db->copy();
        $db->orderBy('created_at', 'DESC');
        $crypto_history = $db->get('xun_crypto_history', $limit);

        $totalRecord = $copyDb->getValue('xun_crypto_history', 'count(id)');
        $db->where('a.is_payment_gateway', 1);
        $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'LEFT');
        $xun_coins = $db->get('xun_coins a', null, 'b.name, b.currency_id, b.symbol');

        foreach ($xun_coins as $coin_key => $coin_value) {
            $name = $coin_value['name'];
            $currency_id = $coin_value['currency_id'];
            $symbol = $coin_value['symbol'];

            $coin_arr = array(
                "name" => $name,
                "currency_id" => $currency_id,
                "symbol" => $symbol
            );
            $coin_list[$currency_id] = $coin_arr;
        }

        $tx_list = [];
        foreach ($crypto_history as $key => $value) {
            $wallet_type = $value["wallet_type"];
            $symbol = $coin_list[$wallet_type]["symbol"];
            $uc_symbol = strtoupper($symbol);
            $amount_receive = $value["amount_receive"];
            $created_at = $value["created_at"];
            $tx_hash = $value["transaction_id"];
            $status = $value["status"];

            $tx_arr = array(
                "tx_hash" => $tx_hash,
                "amount" => $amount_receive,
                "symbol" => $uc_symbol,
                "status" => $status,
                "created_at" => $created_at
            );

            $tx_list[] = $tx_arr;
        }

        $numRecord = count($tx_list);
        $returnData["tx_list"] = $tx_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $numRecord;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        $returnData["last_id"] = $last_id + $numRecord;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00231') /*NuxPay Latest Transaction List*/, "data" => $returnData);
    }

    public function get_transaction_details($params)
    {
        $db = $this->db;

        $business_id = $params['business_id'];
        $id = $params['id'];

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00425') /*Invalid Business ID*/);
        }

        $db->where('id', $id);
        $crypto_history = $db->getOne('xun_crypto_history');

        if (!$crypto_history) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00426') /*"Transaction not found.*/);
        }

        $wallet_type = $crypto_history['wallet_type'];

        $db->where('currency_id', $wallet_type);
        $marketplace_currencies = $db->getOne('xun_marketplace_currencies');

        $unit = $marketplace_currencies['symbol'];
        $uc_unit = strtoupper($unit);
        $image = $marketplace_currencies['image'];

        $status = $crypto_history['status'];
        $tx_hash = $crypto_history['transaction_id'];
        $sender_address = $crypto_history['sender_external'] ? $crypto_history['sender_external'] : $crypto_history['sender_internal'];
        $recipient_address = $crypto_history['recipient_external'] ? $crypto_history['recipient_external'] : $crypto_history['recipient_internal'];
        $amount_user_receive = $crypto_history['amount'];
        $total_amount = $crypto_history['amount_receive'];
        $transaction_fee = $crypto_history['transaction_fee'];
        $miner_fee = $crypto_history['miner_fee'];
        $created_at = $crypto_history['created_at'];

        $tx_details = array(
            "tx_hash" => $tx_hash,
            "sender_address" => $sender_address,
            "recipient_address" => $recipient_address,
            "total_amount" => $total_amount,
            "amount_user_receive" => $amount_user_receive,
            "processing_fee" => $transaction_fee,
            "miner_fee" => $miner_fee,
            "currency_unit" => $uc_unit,
            "image" => $image,
            "status" => $status,
            "created_at" => $created_at,
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00232') /*NuxPay Transaction Details.*/, "data" => $tx_details);
    }

    public function get_news_list($params)
    {
        $db = $this->db;

        $business_id = $params['business_id'];
        $last_id = $params['last_id'] ? $params['last_id'] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 3;

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00425') /*Invalid Business ID*/);
        }

        unset($news_list);
        // $news_details = array(
        //     "title" => "Welcome Onboard!",
        //     "description" => "Congratulations! Your account has been successfully created. Do you need help? Try starting with Support section in Settings or Contact...",
        //     "created_at" => $business_result['created_at'],
        // );

        $news_details = array(
            "title" => "Welcome Onboard!",
            "description" => "Well done! Your account has been created successfully. Move ahead with Support section in live chat if you do not know where to start from!",
            "created_at" => $business_result['created_at'],
        );


        $news_list[] = $news_details;

        // $news_details = array(
        //     "title" => "Security Notice",
        //     "description" => "Please make sure to have all the security features like 2fa, email confirmation, limits and PIN enabled tp enchance your account security...",
        //     "created_at" => "2020-01-15 15:00:00"
        // );
        // $news_list[] = $news_details;

        // $news_details = array(
        //     "title" => "System Upgrade on BTC wallet",
        //     "description" => "We are currently performing  a system upgrade on XDN wallet.",
        //     "created_at" => "2020-01-15 15:00:00"
        //);

        // $news_list[] = $news_details;

        $returnData["news_list"] = $news_list;
        $returnData["totalRecord"] = 3;
        $returnData["numRecord"] = 3;
        $returnData["totalPage"] = ceil(3 / 3);
        $returnData["last_id"] = 0 + 3;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00233') /*NuxPay News Listing*/, "data" => $returnData);
    }

    public function update_user_setting($user_id, $ip, $user_agent)
    {

        $db = $this->db;

        $xunIP = new XunIP($db);
        $ip_country = $xunIP->get_ip_country($ip);

        $db->where("user_id", $user_id);
        $db->where("name", ["ipCountry", "lastLoginIP", "device"], "in");
        $user_setting = $db->map('name')->ArrayBuilder()->get("xun_user_setting", null, "user_id, name, value");

        if ($user_setting['ipCountry']) {

            $update_country = array(
                "value" => $ip_country,
                "updated_at" => date("Y-m-d H:i:s"),
            );
            $db->where('user_id', $user_id);
            $db->where('name', 'ipCountry');
            $updated = $db->update('xun_user_setting', $update_country);

            if (!$updated) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00427') /*Update country failed.*/, 'developer_message' => $db->getLastError());
            }
        } else {
            $insert_country = array(
                "user_id" => $user_id,
                "name" => 'ipCountry',
                "value" => $ip_country,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            );

            $inserted = $db->insert('xun_user_setting', $insert_country);

            if (!$inserted) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00428') /*Insert country failed*/, 'developer_message' => $db->getLastError());
            }
        }

        if ($user_setting['lastLoginIP']) {
            $update_ip = array(
                "value" => $ip,
                "updated_at" => date("Y-m-d H:i:s"),
            );
            $db->where('user_id', $user_id);
            $db->where('name', 'lastLoginIP');
            $updated = $db->update('xun_user_setting', $update_ip);

            if (!$updated) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00429') /*Update Ip failed*/, 'developer_message' => $db->getLastError());
            }
        } else {
            $insert_ip = array(
                "user_id" => $user_id,
                "name" => 'lastLoginIP',
                "value" => $ip,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            );

            $inserted = $db->insert('xun_user_setting', $insert_ip);
            if (!$inserted) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00430') /*Insert IP failed*/, 'developer_message' => $db->getLastError());
            }
        }

        if ($user_setting['device']) {
            $update_device = array(
                "value" => $user_agent,
                "updated_at" => date("Y-m-d H:i:s"),
            );
            $db->where('user_id', $user_id);
            $db->where('name', 'device');
            $updated = $db->update('xun_user_setting', $update_device);

            if (!$updated) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00431') /*Update device failed*/, 'developer_message' => $db->getLastError());
            }
        } else {
            $insert_device = array(
                "user_id" => $user_id,
                "name" => 'device',
                "value" => $user_agent,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            );

            $inserted = $db->insert('xun_user_setting', $insert_device);

            if (!$inserted) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00432') /*Insert Device failed*/, 'developer_message' => $db->getLastError());
            }
        }
    }

    public function create_business_payment_gateway_fundout_address($params)
    {
        global $config, $xunCompanyWalletAPI;

        $address_type = "prepaid_payment_gateway";
        $params["address_type"] = $address_type;

        return $xunCompanyWalletAPI->get_business_prepaid_address($params);
    }

    public function nuxpay_business_payment_gateway_fundout($params)
    {
        global $log, $xunPayment;
        $post = $this->post;
        $db = $this->db;
        $general = $this->general;
        $xunCoins = $this->xunCoins;
        $xunCrypto = $this->xunCrypto;
        $language = $general->getCurrentLanguage();
        $translations = $general->getTranslations();
        $xun_payment_gateway_service = new XunPaymentGatewayService($db);
        $xun_business_service = new XunBusinessService($db);
        $address_type = "prepaid_payment_gateway";
        $date = date('Y-m-d H:i:s');

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

        if ($amount <= 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid amount.");
        }

        $crypto_api_key_validation = $xunCrypto->validate_crypto_api_key($api_key, $business_id);

        if (isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0) {
            return $crypto_api_key_validation;
        }

        //  validate wallet type
        $wallet_type = strtolower($wallet_type);

        $xun_coin_obj = new stdClass();
        $xun_coin_obj->currencyID = $wallet_type;
        $coin_data = $xunCoins->getCoin($xun_coin_obj);

        if (!$coin_data) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid wallet type.", "errorCode" => -100);
        }
        if ($coin_data["is_payment_gateway"] != 1) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Wallet type not supported for payment gateway", "errorCode" => -101);
        }

        $satoshi_amount = $xunCrypto->get_satoshi_amount($wallet_type, $amount);

        if ($satoshi_amount <= 0) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid amount.");
        }

        // $business_address_data = $xun_business_service->getActiveInternalAddressByUserID($business_id);
        $business_address_data = $xun_business_service->getActiveAddressByUserIDandType($business_id, $address_type);

        if (!$business_address_data) {
            //  create pg prepaid wallet for user
            $create_wallet_param = array(
                "business_id" => $business_id,
                "wallet_type" => $wallet_type,
            );
            $create_wallet_result = $this->create_business_payment_gateway_fundout_address($create_wallet_param);
            return array("code" => 0, "message" => "FAILED", "message_d" => "Please fund in to your wallet before proceeding.", "data" => $create_wallet_result["data"]);
        }

        $sender_address = $business_address_data["address"];

        $wallet_obj = new stdClass();
        $wallet_obj->businessID = $business_id;
        $wallet_obj->type = $wallet_type;
        $wallet_obj->status = 1;

        $wallet_result = $xun_payment_gateway_service->createWallet($wallet_obj);

        $wallet_id = $wallet_result["id"];

        $address_result = $xun_payment_gateway_service->getFundOutDestinationAddress($wallet_id, $destination_address);

        if (!$address_result) {

            $db->where('id', $business_id);
            $xun_user = $db->getOne('xun_user', 'nickname');
            $crypto_params["type"] = $wallet_type;
            $crypto_params['businessID'] = $business_id;
            $crypto_params['businessName'] = $xun_user['nickname'];

            $crypto_results = $post->curl_crypto("getNewAddress", $crypto_params);

            if ($crypto_results["code"] != 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_results["message"]);
            }

            $pg_address = $crypto_results["data"]["address"];

            if (!$pg_address) {
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

            if (empty($crypto_user_address)) {
                // destination address is external address
                $validate_destination_address_result = $xunCrypto->crypto_validate_address($destination_address, $wallet_type, "external");

                if ($validate_destination_address_result["code"] == 1) {
                    return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid address.", "errorCode" => -100);
                }
                $address_type = "external";
            } else {
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
        } else {
            $pg_address = $address_result["crypto_address"];
            $address_id = $address_result["address_id"];
        }

        // validate PG address to get PG internal address for signing
        $validate_address_result = $xunCrypto->crypto_validate_address($pg_address, $wallet_type, "external");

        if ($validate_address_result["status"] == "ok") {
            $crypto_data = $validate_address_result["data"];
            if ($crypto_data["addressType"] == "internal" && $crypto_data["status"] == "valid") {
                $pg_internal_address = $crypto_data["address"];
            } else {
                return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid payment gateway address.");
            }
        } else {
            $status_msg = $validate_address_result["statusMsg"];
            return array("code" => 0, "message" => "FAILED", "message_d" => $status_msg);
        }

        //  verify internal transfer
        $receiver_address = $pg_internal_address;

        //  get balance
        $wallet_balance = $xunCrypto->get_wallet_balance($sender_address, $wallet_type);
        if ($amount > $wallet_balance) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insufficient Balance.");
        }

        $tx_obj = new stdClass();
        $tx_obj->userID = $business_id;
        $tx_obj->address = $sender_address;

        $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);

        //  insert to xun_wallet_transaction
        $xunWallet = new XunWallet($db);

        $address_type = "prepaid_payment_gateway";
        $transaction_type = "send";

        $transaction_obj = new stdClass();
        $transaction_obj->status = "pending";
        $transaction_obj->transactionHash = "";
        $transaction_obj->transactionToken = $transaction_token;
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

        // $txHistoryObj->paymentDetailsID = $payment_details_id;
        $txHistoryObj->status = "pending";
        $txHistoryObj->transactionID = "";
        $txHistoryObj->transactionToken = $transaction_token;
        $txHistoryObj->senderAddress = $sender_address;
        $txHistoryObj->recipientAddress = $receiver_address;
        $txHistoryObj->senderUserID = "";
        $txHistoryObj->recipientUserID = "";
        $txHistoryObj->walletType = $wallet_type;
        $txHistoryObj->amount = $amount;
        $txHistoryObj->transactionType = "prepaid_payment_gateway";
        $txHistoryObj->referenceID = "";
        $txHistoryObj->createdAt = $date;
        $txHistoryObj->updatedAt = $date;
        $txHistoryObj->type = 'in';
        $txHistoryObj->gatewayType = "PG";

        $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);

        $transaction_history_id = $transaction_history_result['transaction_history_id'];
        $transaction_history_table = $transaction_history_result['table_name'];

        $updateWalletTx = array(
            "transaction_history_id" => $transaction_history_id,
            "transaction_history_table" => $transaction_history_table
        );
        $xunWallet->updateWalletTransaction($transaction_id, $updateWalletTx);


        if ($transaction_id) {
            $pg_transaction_obj = new stdClass();
            $pg_transaction_obj->userID = $business_id;
            $pg_transaction_obj->walletTransactionID = $transaction_id;
            $pg_transaction_obj->addressID = $address_id;
            $pg_transaction_id = $xun_payment_gateway_service->insertFundOutTransaction($pg_transaction_obj);
        }

        $insert_wallet_sending_queue = array(
            "sender_crypto_user_address_id" => $business_address_data['id'],
            "receiver_crypto_user_address_id" => '',
            "receiver_user_id" => '',
            "receiver_address" => $receiver_address,
            "pg_crypto_address_id" => $address_id,
            "amount" => $amount,
            "amount_satoshi" => $satoshi_amount,
            "wallet_type" => $wallet_type,
            "address_type" => $address_type,
            "status" => 'pending',
            "wallet_transaction_id" => $transaction_id,
            "transaction_token" => $transaction_token,
            "created_at" => $date,
            "updated_at" => $date,
        );

        if ($db->insert('wallet_server_sending_queue', $insert_wallet_sending_queue)) {
            $return_data = [];
            $return_data["transaction_token"] = $transaction_token;
            // $return_data["reference_id"] = $pg_transaction_id;
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "data" => $return_data);
        } else {
            $log->write(date('Y-m-d H:i:s') . " Error writing to wallet_server_sending_queue: " . $db->getLastError() . "\n");
            return array("code" => 0, "message" => "SUCCESS", "message_d" => "Something went wrong. Please try again.", "developer_msg" => $db->getLastError());
        }
    }

    public function get_translation_message($message_code)
    {
        // Language Translations.
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;
    }

    public function request_nuxpay_invoice_payment($params, $ip, $rid, $source, $user_agent, $type, $xunEmail)
    {

        global $xunCrypto, $xunCoins, $config, $xunPay, $xunUser, $xunCurrency, $xunSms;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        //$business_id = trim($params["business_id"]);
        $wallet_type = strtolower(trim($params["currency"]));

        $payee_type = trim($params['payee_type']);
        $payee_name = trim($params['payee_name']);
        $payee_email_address = trim($params['payee_email_address']);
        // $payee_dialing_area = trim($params['payee_dialing_area']);
        // $payee_mobile_number = trim($params['payee_mobile_phone']);
        // $payee_mobile_phone = $payee_dialing_area.$payee_mobile_number;
        $payer_type = trim($params["payer_type"]);
        $payee_mobile_phone = trim($params['payee_mobile_phone']);
        $payer_name = trim($params['payer_name']);
        $payer_email_address = trim($params['payer_email_address']);
        $payer_dialing_area = trim($params['payer_dialing_area']);
        $payer_mobile_number = trim($params['payer_mobile_phone']);
        $payer_mobile_number_full = trim($params['payer_mobile_phone_full']);
        $set_my_name = $params['set_my_name'];
        $toggle_new_address = $params['toggle_new_address'];

        if ($payer_mobile_number_full != "") {
            $mobileNumberInfo = $general->mobileNumberInfo($payer_mobile_number_full, null);
            if ($mobileNumberInfo['isValid'] == 1) {
                $payer_dialing_area = "+" . $mobileNumberInfo['countryCode'];
                $mobileNumberWithoutFormat = $mobileNumberInfo['mobileNumberWithoutFormat'];
                $payer_mobile_number = substr($mobileNumberWithoutFormat, strlen($mobileNumberInfo['countryCode']));
            }
        }

        $payer_mobile_phone = $payer_dialing_area . $payer_mobile_number;
        //$payment_address = trim($params['payment_address']);
        $payment_amount = 0; //trim($params['payment_amount']);
        // $payment_description = trim($params['payment_description']);
        $payment_item_list = $params['payment_item_list'];
        //$payment_amount;
        $payment_description = $params['payment_description'];
        // echo "payermobile".$payer_mobile_phone."\n";
        // echo(preg_match('/^[0-9.+]/', $payer_mobile_phone));
        $source = trim($params['source']);
        $newBusiness = 0;
        $referralCode = $params['referral_code'];
        $signup_type = $params['signup_type'];

        $db->where("currency_id", $wallet_type);
        $symbol = $db->getOne("xun_marketplace_currencies", "symbol");

        if ($payee_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00515') /*Payee name cannot be empty*/);
        }
        // else{
        //     if(preg_match('/^[a-zA-Z., ]/', $payee_name) == 0){
        //         return array('code' => 0, 'message' => "FAILED", 'message_d' => "Special character or number is not allowed for Payee Name");
        //     }
        // }

        if ($payee_type == "email") {

            if ($payee_email_address) {
                if (!filter_var($payee_email_address, FILTER_VALIDATE_EMAIL)) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00523') /*Invalid payee email address.*/);
                }
            }

            if (!$payee_email_address) {
                // TODO Language plugin: Payee email address cannot be empty!
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Payee email address cannot be empty!");
            }

            $payee_mobile_phone = "";
        } else {

            if ($payee_mobile_phone == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00517') /*Payee mobile phone cannot be empty*/);
            } else {
                if (preg_match('/^[0-9.+]/', $payee_mobile_phone) == 0) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Only numbers and symbols are allowed for mobile number");
                }
            }

            $payeeMobileNumberInfo = $general->mobileNumberInfo($payee_mobile_phone, null);
            // list($countryCode, $mobileNumber) = explode(" ", $payeeMobileNumberInfo['mobileNumberFormatted']);
            if ($payeeMobileNumberInfo["isValid"] == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00525') /*Invalid payee mobile phone.*/);
            } else {
                $payee_mobile_phone = "+" . $payeeMobileNumberInfo['mobileNumberWithoutFormat'];
            }

            $payee_email_address = "";
        }


        if ($payer_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00518') /*Payer name cannot be empty*/);
        }
        // else{
        //     if(preg_match('/^[a-zA-Z., ]/', $payer_name) == 0){
        //         return array('code' => 0, 'message' => "FAILED", 'message_d' => "Special character or number is not allowed for Payer Name");
        //     }
        // }


        if ($payer_type == "email") {

            if ($payer_email_address == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00519') /*Payer email address cannot be empty*/);
            }

            if (!filter_var($payer_email_address, FILTER_VALIDATE_EMAIL)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00524') /*Invalid payer email address.*/);
            }

            $payer_mobile_phone = "";
        } else {

            if ($payer_mobile_phone == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00520') /*Payer mobile phone cannot be empty*/);
            } else {
                if (preg_match('/^[0-9.+]/', $payer_mobile_phone) == 0) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Only numbers and symbols are allowed for mobile number");
                }
            }

            $payerMobileNumberInfo = $general->mobileNumberInfo($payer_mobile_phone, null);
            if ($payerMobileNumberInfo["isValid"] == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00526') /*Invalid payer mobile phone.*/);
            } else {
                $payer_mobile_phone = "+" . $payerMobileNumberInfo['mobileNumberWithoutFormat'];
            }

            $payer_email_address = "";
        }


        if (count($payment_item_list) == 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Payment item(s) cannot be empty' /*Payment item(s) cannot be empty*/);
        }

        foreach ($payment_item_list as $payment_item) {
            if ($payment_item['item_name'] == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Payment item name or payment description cannot be empty' /*Payment name or description cannot be empty*/);
            }

            if ($payment_item['unit_price'] == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Payment amount price cannot be empty' /*Payment amount or price cannot be empty*/);
            }

            if ($payment_item['unit_price'] != '' && !is_numeric($payment_item['unit_price'])) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Invalid payment amount or price' /*Invalid payment amount or price*/);
            }

            if ($payment_item['unit_quantity'] == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Payment item quantity cannot be empty' /*Payment amount or price cannot be empty*/);
            }

            if ($payment_item['unit_quantity'] != '' && !is_numeric($payment_item['unit_quantity'])) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Invalid payment item quantity' /*Invalid payment amount or price*/);
            }
        }

        // validate wallet_type
        $coin_settings = $xunCoins->checkCoinSetting("is_payment_gateway", $wallet_type);
        if (!$coin_settings) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00433') /*Invalid currency.*/
            );
        }

        $decimal_places = $xunCurrency->get_currency_decimal_places($wallet_type);
        foreach ($payment_item_list as $key => $field) {
            $current_item_total = bcmul($field['unit_price'], $field['unit_quantity'], $decimal_places);
            $payment_item_list[$key]['total_price'] = $current_item_total;
            $payment_amount = bcadd($payment_amount, $current_item_total, $decimal_places);
        }


        if ($referralCode != "") {

            $db->where('referral_code', $referralCode);
            $db->where('deleted', 0);
            $db->where('type', 'reseller');
            $reseller_info = $db->getOne('reseller');

            if (!$reseller_info) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00542') /*Reseller does not exist.*/);
            }
        }


        //===
        $db->where("register_site", $source);

        if ($payee_type == "email") {
            $db->where("u.email", $payee_email_address);
            $db->where("a.email_verified", 1);
        } else {
            $db->where("u.username", $payee_mobile_phone);
            $db->where("a.main_mobile_verified", 1);
        }

        $db->where("u.type", "business");
        $db->join("xun_business_account a", "u.id=a.user_id", "INNER");
        $business_id = $db->getValue("xun_user u", "u.id");

        if (!$business_id && $type == 'confirmation') {


            $verifyData['req_type'] = $payee_type;
            $verifyData['email'] = $payee_email_address;
            $verifyData['mobile'] = $payee_mobile_phone;
            $verifyData['source'] = $source;
            $verifyData['ip'] = $ip;
            $verifyData['request_type'] = 'request_fund';
            $verifyData['company_name'] = $source; //$setting->systemSetting['payCompanyName'];
            $resultGetOtp = $xunUser->register_verifycode_get($verifyData);

            if ($resultGetOtp['code'] == 1 || $resultGetOtp['errorCode'] == -101) {

                $db->where("is_valid", 1);
                $db->where("is_verified", 0);
                $db->where("expires_at", date("Y-m-d H:i:s"), ">");
                if ($payee_type == "email") {
                    $db->where("email", $payee_email_address);
                } else {
                    $db->where("mobile", $payee_mobile_phone);
                }
                $verification_code = $db->getValue("xun_user_verification", "verification_code");

                $registerData['req_type'] = $payee_type;
                $registerData['email'] = $payee_email_address;
                $registerData['mobile'] = $payee_mobile_phone;
                $registerData['pay_password'] = $verification_code;
                $registerData['pay_retype_password'] = $verification_code;
                $registerData['verify_code'] = $verification_code;
                $registerData['nickname'] = $payee_name;
                $registerData['type'] = $source;
                $registerData['rid'] = $rid;
                $registerData['source'] = $source;
                $registerData['reseller_code'] = $referralCode;
                $registerData['signup_type'] = $signup_type;
                $newBusiness = 1;

                $resultRegister = $xunPay->pay_register($registerData, $ip, $user_agent, $rid);
                if ($resultRegister['code'] == 1) {
                    $business_id = $resultRegister['data']['business_id'];
                } else {
                    return $resultRegister;
                }
            }
        }

        if ($business_id == '' && $newBusiness = 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00408') /*Currency cannot be empty.*/);
        }

        if ($type == 'verification') {
            $verification_data = array(
                "currency" => $wallet_type,
                "payee_name" => $payee_name,
                "payee_email_address" => $payee_email_address,
                // "payee_country_code" => $payee_dialing_area,
                // "payee_mobile_phone" => $payee_mobile_number,
                "payee_mobile_phone" => $payee_mobile_phone,
                "payer_name" => $payer_name,
                "payer_country_code" => $payer_dialing_area,
                "payer_mobile_phone" => $payer_mobile_number,
                "payer_mobile_number_full" => $payer_mobile_number_full,
                // "payer_mobile_phone" => $payer_mobile_phone,
                "payer_email_address" => $payer_email_address,
                "payment_amount" => trim($params['payment_amount']),
                "payment_description" => trim($params['payment_description']),
                "payment_item_list" => $payment_item_list,
                "toggle_new_address" => $toggle_new_address,
            );
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "", "data" => $verification_data);
        }

        //  check if business has activated pg
        // $db->where("a.business_id", $business_id);
        // $db->where("a.type", $wallet_type);
        // $db->where("a.status", 1);
        // $db->join("xun_crypto_destination_address b", "a.id=b.wallet_id");
        // $crypto_wallet = $db->getOne("xun_crypto_wallet a", "a.id");

        // if (!$crypto_wallet) {
        //     $crypto_wallet_data['business_id'] = $business_id;
        //     $crypto_wallet_data['type'] = $wallet_type;
        //     $crypto_wallet_data['status'] = "1";
        //     $crypto_wallet_data['created_at'] = date("Y-m-d H:i:s");
        //     $crypto_wallet_data['updated_at'] = date("Y-m-d H:i:s");
        //     $wallet_id = $db->insert("xun_crypto_wallet", $crypto_wallet_data);

        //     $crypto_destination_address['type'] = $wallet_type;
        //     $crypto_destination_address['destination_address'] = "";
        //     $crypto_destination_address['status'] = "1";
        //     $crypto_destination_address['created_at'] = date("Y-m-d H:i:s");
        //     $crypto_destination_address['updated_at'] = date("Y-m-d H:i:s");
        //     $crypto_destination_address['wallet_id'] = $wallet_id;
        //     $db->insert("xun_crypto_destination_address", $crypto_destination_address);
        // }


        //API
        // $db->where("business_id", $business_id);
        // $db->where("status", "1");
        // $db->where("expired_at", date("Y-m-d H:i:s"), ">");
        // $api_key = $db->getValue("xun_crypto_apikey", "apiKey");

        // if(!$api_key) {
        //     $xunCrypto->generate_apikey(array("business_id"=>$business_id));
        //     $db->where("business_id", $business_id);
        //     $db->where("status", "1");
        //     $db->where("expired_at", date("Y-m-d H:i:s"), ">");
        //     $api_key = $db->getValue("xun_crypto_apikey", "apiKey");
        // }

        if ($set_my_name) {
            $xunUser->update_user_first_time_business(array("business_id" => $business_id, "business_name" => $payee_name));
        }

        $payment_channel = array("crypto_wallet");
        $requestTransactionResult = $this->merchant_request_transaction(
            array("business_id" => $business_id, "api_key" => "", "address" => "", "amount" => $payment_amount, "currency" => $wallet_type, "reference_id" => time(), "redirect_url" => "", "toggle_new_address" => $toggle_new_address, "is_direct" => "1", "payment_channel" => $payment_channel),
            $source,
            "invoice",
            true
        );


        if ($requestTransactionResult['code'] == 1 && $requestTransactionResult['data']['address'] != "" && $requestTransactionResult['data']['pg_id'] != "" && $requestTransactionResult['data']['transaction_token'] != "") {

            // $crypto_amount = $xunCurrency->get_usd_to_crypto_rate($wallet_type, $payment_amount);
            //now payment_amount is in cryptocurrency value not usd value anymore
            // $serviceChargeData = array(
            //     "wallet_type" => $wallet_type,
            //     "address" => $requestTransactionResult['data']['address'],
            //     "amount" => $payment_amount,
            //     "check_service_charge" => 1,
            // );

            // $service_charge_amount = $xunCrypto->get_service_charge($serviceChargeData);

            $insertData = array(
                "pg_transaction_id" => $requestTransactionResult['data']['pg_id'],
                "payee_name" => $payee_name,
                "payee_email_address" => $payee_email_address,
                "payee_mobile_phone" => $payee_mobile_phone,
                "payer_name" => $payer_name,
                "payer_email_address" => $payer_email_address,
                "payer_mobile_phone" => $payer_mobile_phone,
                "payment_address" => $requestTransactionResult['data']['address'],
                "payment_amount" => $payment_amount,
                "crypto_amount" => $payment_amount,
                "payment_currency" => $wallet_type,
                "payment_description" => $payment_description,
                "status" => 'pending',
                "created_at" => date("Y-m-d H:i:s"),
                "gw_type" => $requestTransactionResult['data']['gw_type']
            );

            $detail_id = $db->insert("xun_payment_gateway_invoice_detail", $insertData);

            $insertData = array();
            foreach ($payment_item_list as $payment_item) {
                array_push($insertData, array(
                    "invoice_detail_id" => $detail_id,
                    "item_name" => $payment_item["item_name"],
                    "unit_price" => $payment_item["unit_price"],
                    "quantity" => $payment_item["unit_quantity"],
                    "total_price" => $payment_item["total_price"]
                ));
            }
            $db->insertMulti("xun_request_fund_item_detail", $insertData);

            $db->where('source', $source);
            $redirectUrl = $db->getValue('site', 'domain');

            $shorten_url = $redirectUrl . "/inv/" . $requestTransactionResult['data']['transaction_token'];

            $companyName = $source; //$setting->systemSetting['payCompanyName'];
            $translations_message = $this->get_translation_message('B00309'); /*"%%companyName%%: %%payee%% has send you a payment request at %%shortenUrl%%";*/

            if (strtolower($companyName) == "ppay") {
                $companyName = "PPAY";
            }

            $db->where('source', $companyName);
            $site = $db->getOne('site');
            $Prefix = $site['otp_prefix'];

            if ($Prefix != ""){
                $companyName = $Prefix;
            }

            if ($payer_type == "email") {

                $db->where("currency_id", $wallet_type);
                $symbol = $db->getOne("xun_marketplace_currencies", "symbol");

                //SEND EMAIL
                $requestAmount = $payment_amount . " " . strtoupper($symbol['symbol']);
                $emailDetail = $xunEmail->getRequestFundEmail($companyName, $payer_name, $payee_name, $requestAmount, $shorten_url);

                $emailParams["subject"] = $emailDetail['emailSubject'];
                $emailParams["body"] = $emailDetail['html'];
                $emailParams["recipients"] = array($payer_email_address);
                $emailParams["emailFromName"] = $emailDetail['emailFromName'];
                $emailParams["emailAddress"] = $emailDetail['emailAddress'];
                $emailParams["emailPassword"] = $emailDetail['emailPassword'];
                $msg = $general->sendEmail($emailParams);
            } else {

                //SEND SMS
                $return_message = str_replace("%%companyName%%", $companyName, $translations_message);
                $return_message2 = str_replace("%%payee%%", $payee_name, $return_message);
                $newParams["message"] = str_replace("%%shortenUrl%%", $shorten_url, $return_message2);
                $newParams["recipients"] = $payer_mobile_phone;
                $newParams["ip"] = $ip;
                $newParams["companyName"] = $companyName;
                $xunSms->send_sms($newParams);
            }

            $returnData["symbol"] = $symbol['symbol'];
            $returnData["shorten_url"] = $shorten_url;
            $returnData["payment_id"] = $requestTransactionResult['data']['payment_id'];
            $returnData["payer_name"] = $payer_name;
            $returnData["payer_email_address"] = $payer_email_address;
            $returnData["payer_mobile_phone"] = $payer_mobile_phone;
            $returnData["total_amount"] = $payment_amount;
            $returnData["new_business"] = $newBusiness;

            return array("code" => 1, "message" => "SUCCESS", "message_d" => "", "data" => $returnData);
        } else {

            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00527') /*Something went wrong, please try again later.*/, 'requestTransactionResult' => $requestTransactionResult);
        }
    }

    public function get_nuxpay_invoice_details($params)
    {
        $db = $this->db;

        $transaction_token = trim($params['transaction_token']);

        if ($transaction_token == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00530') /*Transaction token cannot be empty*/);
        }

        $db->where('transaction_token', $transaction_token);
        $pg_payment_transaction_record = $db->getOne('xun_payment_gateway_payment_transaction', 'id, redirect_url, payment_id, wallet_type, gw_type');
        if (!$pg_payment_transaction_record) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00528') /*Something went wrong, please try again later.*/);
        }

        $db->where('currency_id', $pg_payment_transaction_record['wallet_type']);
        $paymentSymbol = $db->getOne('xun_marketplace_currencies', 'symbol');

        $db->where('pg_transaction_id', $pg_payment_transaction_record['id']);
        $invoice_detail = $db->getOne('xun_payment_gateway_invoice_detail', 'id, payee_name, payee_email_address, payee_mobile_phone, payer_name, payer_email_address, payer_mobile_phone, payment_address, payment_amount, payment_currency, payment_description, status');

        if (!$invoice_detail) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00529') /*Invoice not found.*/);
        }

        if ($invoice_detail['payment_description'] == '') {
            $db->where('invoice_detail_id', $invoice_detail['id']);
            $payment_item_list =  $db->get('xun_request_fund_item_detail', null, 'item_name, unit_price, quantity, total_price');
        } else {
            $payment_item_list = array(array(
                'item_name' => $invoice_detail['payment_description'],
                'unit_price' => $invoice_detail['payment_amount'],
                'quantity' => '1',
                'total_price' => $invoice_detail['payment_amount']
            ));
        }


        if ($pg_payment_transaction_record['gw_type'] == "BC") {

            $db->where('transaction_token', $transaction_token);
            $transaction_row_detail = $db->getOne('xun_payment_gateway_payment_transaction', 'crypto_history_id');
            $crypto_hist_id = $transaction_row_detail['crypto_history_id'];

            $db->where('id', $crypto_hist_id);
            $transaction_hist_detail = $db->getOne('xun_crypto_history', 'transaction_id');
            $transaction_hash_id = $transaction_hist_detail['transaction_id'];

            $db->where("transaction_type", "fund_in");
            $db->where("transaction_hash", $transaction_hash_id);
            //$db->where("invoice_detail_id", $invoice_detail['id']);
            $invoice_transaction_detail = $db->get("xun_payment_gateway_invoice_transaction");

            $wallet_type_arr = array();
            $received_trx_id_arr = array();

            $totalPaid = '0.00000000';
            foreach ($invoice_transaction_detail as $inv_trx_detail) {
                $amount = $inv_trx_detail['amount'];
                $wallet_type = $inv_trx_detail['wallet_type'];
                $received_trx_id = $inv_trx_detail['transaction_hash'];

                if (!in_array($wallet_type, $wallet_type_arr)) {
                    array_push($wallet_type_arr, $wallet_type);
                }

                if (!in_array($received_trx_id, $received_trx_id_arr)) {
                    array_push($received_trx_id_arr, $received_trx_id);
                }

                $totalPaid = bcadd($totalPaid, $amount, 8);
            }


            $crypto_history = array();
            if (count($received_trx_id_arr) > 0) {

                $db->where("received_transaction_id", $received_trx_id_arr, "IN");
                $crypto_history_detail = $db->get("xun_crypto_history");

                foreach ($crypto_history_detail as $history_detail) {

                    $db->where("currency_id", $history_detail['wallet_type']);
                    $symbol = $db->getValue("xun_marketplace_currencies", "symbol");

                    $history_data['transaction_hash'] = $history_detail['received_transaction_id'];
                    $history_data['amount'] = $history_detail['amount_receive'];
                    $history_data['wallet_type'] = $history_detail['wallet_type'];
                    $history_data['created_at'] = $history_detail['created_at'];
                    $history_data['status'] = $history_detail['status'];
                    $history_data['symbol'] = strtoupper($symbol);
                    $history_data['payment_address'] = $invoice_detail['payment_address'];
                    $history_data['sender_address'] = $history_detail['sender_internal'] ? $history_detail['sender_internal'] : $history_detail['sender_external'];
                    $history_data['recipient_address'] = $history_detail['recipient_internal'] ? $history_detail['recipient_internal'] : $history_detail['recipient_external'];

                    $crypto_history[] = $history_data;
                }
            }
        } else {

            $db->where('address', $invoice_detail['payment_address']);
            $crypto_history = $db->get('xun_crypto_history', null, 'sender_internal, sender_external, recipient_internal, recipient_external, transaction_id as transaction_hash, amount_receive as amount, wallet_type, created_at, status ');

            $wallet_type_arr = array();
            $totalPaid = '0.00000000';

            if ($crypto_history) {
                foreach ($crypto_history as $key => $value) {
                    $amount = $value['amount'];
                    $wallet_type = $value['wallet_type'];

                    if (!in_array($wallet_type, $wallet_type_arr)) {
                        array_push($wallet_type_arr, $wallet_type);
                    }

                    $totalPaid = bcadd($totalPaid, $amount, 8);
                }

                $db->where('currency_id', $wallet_type_arr, 'IN');
                $marketplaceCurrencies = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies', null, 'currency_id, symbol');

                foreach ($crypto_history as $k => $v) {
                    $symbol = strtoupper($marketplaceCurrencies[$v['wallet_type']]);
                    $crypto_history[$k]['symbol'] = $symbol;
                    $crypto_history[$k]['payment_address'] = $invoice_detail['payment_address'];

                    $crypto_history[$k]['sender_address'] = $crypto_history[$k]['sender_internal'] ?  $crypto_history[$k]['sender_internal'] :  $crypto_history[$k]['sender_external'];
                    $crypto_history[$k]['recipient_address'] = $crypto_history[$k]['recipient_internal'] ?  $crypto_history[$k]['recipient_internal'] :  $crypto_history[$k]['recipient_external'];

                    unset($crypto_history[$k]['sender_internal']);
                    unset($crypto_history[$k]['sender_external']);
                    unset($crypto_history[$k]['recipient_internal']);
                    unset($crypto_history[$k]['recipient_external']);
                }
            }
        }


        $invoice_detail['redirect_url'] = $pg_payment_transaction_record['redirect_url'];
        $invoice_detail['payment_id'] = $pg_payment_transaction_record['payment_id'];
        $invoice_detail['symbol'] = $paymentSymbol['symbol'];
        $invoice_detail['total_paid'] = $totalPaid;

        if ($invoice_detail['payment_amount'] <= $totalPaid) {
            $invoice_detail['status'] = 'success';
            $update_transaction_status = array(
                "status" => $invoice_detail['status'],
            );
            $db->where('transaction_token', $transaction_token);
            $db->update("xun_payment_gateway_payment_transaction", $update_transaction_status);
        } else if ($invoice_detail['payment_amount'] > $totalPaid && $totalPaid != '0') {
            $invoice_detail['status'] = 'short_paid';
        }

        $data['payment_item_list'] = $payment_item_list;
        $data['invoice_detail'] = $invoice_detail;
        $data['transaction_list'] = $crypto_history;

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00295') /* Nuxpay Invoice Details Successful.*/, "data" => $data);
    }


    public function get_nuxpay_invoice_listing($params)
    {
        global $post;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        // $business_id = trim($params['business_id']);
        $payee_mobile_phone = trim($params['payee_mobile_phone']);
        $payee_email_address = trim($params['payee_email_address']);
        $status = trim($params['status']);
        $date_from = $params["date_from"];
        $date_to = $params["date_to"];
        $payer_name = $params["payer_name"];
        $payer_mobile_phone = $params['payer_mobile_phone'];
        $payer_email_address = $params['payer_email_address'];
        $see_all = trim($params["see_all"]);
        $source = trim($params['source']);

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"] ? $params["page"] : 1;
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";

        // if ($page_number < 1) {
        //     $page_number = 1;
        // }
        //Get the limit.
        if (!$see_all) {
            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        }

        if ($payee_mobile_phone == '' && $payee_email_address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00550') /*Payee mobile phone or email address cannot be empty*/);
        }

        if ($payee_mobile_phone != "") {
            $payeeMobileNumberInfo = $general->mobileNumberInfo($payee_mobile_phone, null);
            if ($payeeMobileNumberInfo["isValid"] == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00525') /*Invalid payee mobile phone.*/);
            } else {
                $payee_mobile_phone = "+" . $payeeMobileNumberInfo['mobileNumberWithoutFormat'];
            }
        }


        if ($date_from) {
            $date_from = date("Y-m-d H:i:s", $date_from);
            $db->where("a.created_at", $date_from, ">=");
        }
        if ($date_to) {
            $date_to = date("Y-m-d H:i:s", $date_to);
            $db->where("a.created_at", $date_to, "<=");
        }

        if ($payer_name) {
            $db->where('a.payer_name', "%$payer_name%", 'LIKE');
        }

        if ($payer_mobile_phone) {
            $payerMobileNumberInfo = $general->mobileNumberInfo($payer_mobile_phone, null);
            if ($payerMobileNumberInfo["isValid"] == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00526') /*Invalid payer mobile phone.*/);
            } else {
                $payer_mobile_phone = "+" . $payerMobileNumberInfo['mobileNumberWithoutFormat'];
            }

            $db->where('a.payer_mobile_phone', "%$payer_mobile_phone%", 'LIKE');
        }

        if ($payer_email_address) {
            $db->where('a.payer_email_address', "%$payer_email_address%", 'LIKE');
        }

        if ($status) {
            $db->where('a.status', $status);
        }

        if ($payee_mobile_phone != "" && $payee_email_address == "") {
            $db->where('a.payee_mobile_phone', $payee_mobile_phone, 'LIKE');
        } else if ($payee_email_address != "" && $payee_mobile_phone == "") {
            $db->where('a.payee_email_address', $payee_email_address, 'LIKE');
        } else if ($payee_mobile_phone != "" && $payee_email_address != "") {
            $db->where("(a.payee_mobile_phone='" . $payee_mobile_phone . "' OR a.payee_email_address='" . $payee_email_address . "')");
        }


        $db->where('u.register_site', $source);
        $db->join('xun_marketplace_currencies d', 'a.payment_currency = d.currency_id', 'LEFT');
        $db->join('xun_payment_gateway_payment_transaction b', 'a.pg_transaction_id = b.id', 'LEFT');
        $db->join('xun_crypto_history c', 'b.crypto_history_id = c.id', 'LEFT');
        $db->join('xun_payment_gateway_payment_transaction p', 'a.pg_transaction_id=p.id', 'INNER');
        $db->join('xun_user u', 'u.id=p.business_id', 'INNER');
        $copyDb = $db->copy();
        $db->orderBy('a.id', $order);
        $invoice_listing = $db->get('xun_payment_gateway_invoice_detail a', $limit, 'b.transaction_token, a.payer_name, a.payer_email_address, a.payer_mobile_phone, a.payment_amount, a.payment_currency, a.payment_address, UPPER(d.symbol) as currency_unit, d.image, a.status, a.created_at, a.gw_type, a.pg_transaction_id, a.id');

        $totalRecord = $copyDb->getValue('xun_payment_gateway_invoice_detail a', 'count(a.id)');

        $arr_invoice_listing = array();
        foreach ($invoice_listing as $inv_detail) {

            if ($inv_detail['gw_type'] == "BC") {

                $db->where("d.pg_transaction_id", $inv_detail['pg_transaction_id']);
                $db->where("d.status", "success");
                $db->join("xun_payment_gateway_invoice_transaction t", "d.id=t.invoice_detail_id", "INNER");
                $total_paid = $db->getValue("xun_payment_gateway_invoice_detail d", "SUM(t.amount)");
            } else {

                $db->where("address", $inv_detail['payment_address']);
                $db->where("status", "success");
                $db->where("wallet_type", $inv_detail['payment_currency']);
                $total_paid = $db->getValue("xun_crypto_history", "SUM(amount_receive)");
            }

            $inv_data['pg_transaction_id'] = $inv_detail['pg_transaction_id'];
            $inv_data['gw_type'] = $inv_detail['gw_type'];
            $inv_data['transaction_token'] = $inv_detail['transaction_token'];
            $inv_data['payer_name'] = $inv_detail['payer_name'];
            $inv_data['payer_email_address'] = $inv_detail['payer_email_address'];
            $inv_data['payer_mobile_phone'] = $inv_detail['payer_mobile_phone'];
            $inv_data['payment_amount'] = $inv_detail['payment_amount'];
            $inv_data['payment_currency'] = $inv_detail['payment_currency'];
            $inv_data['payment_address'] = $inv_detail['payment_address'];
            $inv_data['currency_unit'] = $inv_detail['currency_unit'];
            $inv_data['image'] = $inv_detail['image'];
            $inv_data['status'] = $inv_detail['status'];
            $inv_data['created_at'] = $inv_detail['created_at'];
            $inv_data['id'] = $inv_detail['id'];
            $inv_data['total_paid'] = number_format(($total_paid == "" ? 0 : $total_paid), 8);

            $arr_invoice_listing[] = $inv_data;
        }


        $invoice_listing = array_values($invoice_listing);
        $num_record = !$see_all ? $page_size : $totalRecord;
        $total_page = ceil($totalRecord / $num_record);

        $data['invoice_listing'] = $arr_invoice_listing; // $invoice_listing;
        $data['totalPage']    = $total_page;
        $data['pageNumber']   = $page_number;
        $data['totalRecord']  = $totalRecord;
        $data['numRecord']    = $page_size;

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00296') /* Nuxpay Invoice Details Successful.*/, "data" => $data);
    }


    public function set_nuxpay_invoice_listing_payer($params)
    {
        $db = $this->db;
        $general = $this->general;

        $invoice_detail_id = $params['invoice_detail_id'];
        $new_payer_name = $params['new_payer_name'];
        $new_payer_email = $params['new_payer_email'];
        $new_payer_mobile = $params['new_payer_mobile'];

        $db->where('id', $invoice_detail_id);
        $invoice_detail = $db->getOne('xun_payment_gateway_invoice_detail');

        if ($new_payer_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00518') /*Payer name cannot be empty*/);
        } else {
            if (preg_match('/^[a-zA-Z., ]/', $new_payer_name) == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Special character or number is not allowed for Payer Name");
            }
        }

        if ($new_payer_email == "" && $new_payer_mobile == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00550') /*Payee mobile phone or email address cannot be empty*/);
        }

        // if ($new_payer_email == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00519') /*Payer email address cannot be empty*/);
        // }

        if ($new_payer_mobile != '') {
            if (preg_match('/^[0-9.+]/', $new_payer_mobile) == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Only numbers and symbols are allowed for mobile number");
            }

            $payerMobileNumberInfo = $general->mobileNumberInfo($new_payer_mobile, null);
            if ($payerMobileNumberInfo["isValid"] == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00526') /*Invalid payer mobile phone.*/);
            } else {
                $new_payer_mobile = "+" . $payerMobileNumberInfo['mobileNumberWithoutFormat'];
            }
        }

        if ($new_payer_email != '') {
            if (!filter_var($new_payer_email, FILTER_VALIDATE_EMAIL)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00524') /*Invalid payer email address.*/);
            }
        }

        if ($invoice_detail == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" =>  "Invoice detail cannot be found",   "developer_msg" => "Invoice detail cannot be found");
        }

        $updateData = array(
            "payer_name" => $new_payer_name,
            "payer_email_address" => $new_payer_email,
            "payer_mobile_phone" => $new_payer_mobile

        );

        $db->where('id', $invoice_detail_id);
        if ($db->update('xun_payment_gateway_invoice_detail', $updateData)) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Nuxpay Invoice Details updated");
        } else {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong");
        }
    }

    public function create_nuxpay_invoice_withdrawal($params)
    {
        global $config, $xunXmpp, $xunCurrency, $xunMinerFee, $xunPayment;
        $db = $this->db;
        $post = $this->post;
        $setting = $this->setting;
        $xunCrypto = $this->xunCrypto;
        $general = $this->general;

        $business_id = $params['business_id'];
        $wallet_type = $params['wallet_type'];
        $destination_address = $params['destination_address'];
        $source = $params['source'];

        $date = date("Y-m-d H:i:s");

        // $consolidate_address = $setting->systemSetting['requestFundConsolidateWalletAddress'];
        $miner_fee_delegate_wallet_address = $setting->systemSetting['minerFeeDelegateWalletAddress'];
        $prepaidWalletServerURL =  $config["giftCodeUrl"];

        if ($business_id == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" =>  $this->get_translation_message('E00002') /*Business ID cannot be empty*/,   "developer_msg" => "Business ID cannot be empty");
        }

        if ($wallet_type == '') {
            return array("code" => 0, "message" => "FAILED",  'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "Wallet Type cannot be empty");
        }

        if ($destination_address == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00153') /*Destination address cannot be empty*/,  "developer_msg" => "Destination Address cannot be empty");
        }

        $xunBusinessService = new XunBusinessService($db);
        $business_result = $xunBusinessService->getBusinessByBusinessID($business_id);

        if (!$business_result) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00082') /*Business not found.*/);
        }

        $data_return = $this->get_nuxpay_user_internal_address($business_id);

        if ($data_return['code'] == 0) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $data_return['message_d']);
        }

        $internal_address = $data_return['data']['internal_address'];

        $validate_address_result = $xunCrypto->crypto_validate_address($destination_address, $wallet_type, "external");

        if ($validate_address_result["code"] == 1) {
            // return array("code" => 0, "message" => "FAILED", "message_d" => $validate_address_result['statusMsg']);
            $validate_address_result1 = $xunCrypto->crypto_validate_address($destination_address, $wallet_type, "internal");
            if ($validate_address_result1["code"] == 1) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $validate_address_result1['statusMsg']);
            } else {
                $destiantion_address = $validate_address_result1['data']['address'];
                $destination_address_type = $validate_address_result1['data']['addressType'];
            }
        } else {
            $destination_address = $validate_address_result['data']['address'];
            $destination_address_type = $validate_address_result['data']['addressType'];
        }

        $destination_address_type = $validate_address_result['data']['addressType'];

        if (!$params['amount']) {
            $withdrawal_balance = $this->getUserRequestFundBalance($wallet_type, $business_id);
        } else {
            $withdrawal_balance = $params['amount'];
        }

        if ($withdrawal_balance <= 0) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00531') /*Withdrawal Amount cannot be less than 0.*/);
        }

        $satoshi_amount = $xunCrypto->get_satoshi_amount($wallet_type, $withdrawal_balance);

        $wallet_info = $xunCrypto->get_wallet_info($internal_address, $wallet_type);

        $lc_wallet_type = strtolower($wallet_type);
        $walletBalance = $wallet_info[$lc_wallet_type]['balance'];
        $unitConversion = $wallet_info[$lc_wallet_type]['unitConversion'];
        $minerFeeWalletType = strtolower($wallet_info[$lc_wallet_type]['feeType']);

        //Call get wallet info to get the miner fee balance if the miner fee is not charged in the same wallet type
        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($lc_wallet_type, true);
        $decimal_places = $decimal_place_setting["decimal_places"];

        $miner_decimal_place_setting = $xunCurrency->get_currency_decimal_places($minerFeeWalletType, true);
        $miner_fee_decimal_places = $miner_decimal_place_setting['decimal_places'];

        if ($minerFeeWalletType != $wallet_type) {
            $miner_fee_wallet_info = $xunCrypto->get_wallet_info($miner_fee_delegate_wallet_address, $minerFeeWalletType);
            // $minerFeeBalance = $miner_fee_wallet_info[$minerFeeWalletType]['balance'];
            $minerFeeUnitConversion = $miner_fee_wallet_info[$minerFeeWalletType]['unitConversion'];
            $minerFeeBalance = $xunMinerFee->getMinerFeeBalance($miner_fee_delegate_wallet_address, $minerFeeWalletType);
            $converted_miner_fee_balance = $minerFeeBalance;
        } else {
            $minerFeeBalance = $walletBalance;
            $minerFeeUnitConversion = $unitConversion;
            $converted_miner_fee_balance = bcdiv($minerFeeBalance, $minerFeeUnitConversion, $miner_fee_decimal_places);
        }

        //calculate miner fee only
        $return = $xunCrypto->calculate_miner_fee($internal_address, $destination_address, $satoshi_amount, $wallet_type, 1);

        $miner_fee = $return['data']['txFee'];

        $converted_miner_fee = bcdiv($miner_fee, $minerFeeUnitConversion, 18);

        //if miner is not charge in the same wallet type as the transaction
        if ($wallet_type != $minerFeeWalletType) {
            $lowercase_miner_wallet_type = strtolower($minerFeeWalletType);

            $original_miner_fee_amount = $converted_miner_fee;
            $converted_miner_fee =  $xunCurrency->get_conversion_amount($lc_wallet_type, $lowercase_miner_wallet_type, $converted_miner_fee, true);

            $convertedSatoshiMinerFee = bcmul($converted_miner_fee, $unitConversion);
        } else {
            $convertedSatoshiMinerFee = $miner_fee;
            $converted_miner_fee = $xunCurrency->round_miner_fee($minerFeeWalletType, $converted_miner_fee);

            $original_miner_fee_amount = $converted_miner_fee;
        }

        if ($converted_miner_fee > $withdrawal_balance) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insufficient Balance.");
        }

        $remainingWithdrawalSatoshi = bcsub($satoshi_amount, $convertedSatoshiMinerFee, 0);
        $remainingWithdrawalAmount = bcdiv($remainingWithdrawalSatoshi, $unitConversion, $decimal_places);

        $miner_fee_balance_usd = $xunCurrency->calculate_cryptocurrency_rate_by_wallet_type($minerFeeWalletType, $converted_miner_fee_balance);

        //Not enough miner to fund out
        // if(($minerFeeWalletType != $wallet_type) && ($converted_miner_fee_balance < $original_miner_fee_amount)){
        //     $tag = "Insufficient Miner Fee Balance";
        //     $message = "Type: Request Fund Internal Wallet\n";
        //     $message .= "Address:".$internal_address."\n";
        //     $message .= "Miner Fee Wallet Balance: ".$converted_miner_fee_balance."\n";
        //     $message .= "Wallet Type:".$minerFeeWalletType."\n";
        //     $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        //     $erlang_params["tag"]         = $tag;
        //     $erlang_params["message"]     = $message;
        //     $erlang_params["mobile_list"] = $xun_numbers;
        //     $xmpp_result                  = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_wallet_transaction");

        //     return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => 'Insufficient Miner Fee Amount to Fund Out', "error:"=>$message);

        // }

        if ($miner_fee_balance_usd <= 10) {
            $tag = "Low Miner Fee Balance";
            $message = "Type: Miner Fee Delegate Wallet\n";
            $message .= "Address:" . $miner_fee_delegate_wallet_address . "\n";
            $message .= "Miner Fee:" . $converted_miner_fee . "\n";
            $message .= "Miner Fee Wallet Balance: " . $converted_miner_fee_balance . "\n";
            $message .= "Wallet Type:" . $minerFeeWalletType . "\n";
            $message .= "Time: " . date("Y-m-d H:i:s") . "\n";

            $erlang_params["tag"]         = $tag;
            $erlang_params["message"]     = $message;
            $erlang_params["mobile_list"] = $xun_numbers;
            $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");
        }

        $insertData = array(
            "business_id" => $business_id,
            "wallet_type" => $wallet_type,
            "withdrawal_amount" => $withdrawal_balance,
            "destination_address" => $destination_address,
            "status" => 'pending',
            "created_at" => $date,
            "updated_at" => $date,

        );

        $withdrawal_id = $db->insert('xun_request_fund_withdrawal', $insertData);

        if (!$withdrawal_id) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }

        $insertWithdrawal = array(
            "business_id" => $business_id,
            "reference_id" => $withdrawal_id,
            "wallet_type" => $wallet_type,
            "sender_address" => $internal_address,
            "recipient_address" => $destination_address,
            "amount" => $remainingWithdrawalAmount,
            "amount_receive" => $withdrawal_balance,
            "miner_fee" => $converted_miner_fee,
            "actual_miner_fee" => $original_miner_fee_amount,
            "actual_miner_fee_wallet_type" => $minerFeeWalletType,
            "status" => 'pending',
            "transaction_type" => 'manual_withdrawal',
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $pg_withdrawal_id = $db->insert('xun_payment_gateway_withdrawal', $insertWithdrawal);

        if (!$pg_withdrawal_id) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }
        $xunWallet = new XunWallet($db);

        if ($minerFeeWalletType != $wallet_type && $destination_address_type == 'external') {

            $xunUserService = new XunUserService($db);
            $txObj = new stdClass();
            $txObj->userID = 0;
            $txObj->address = $miner_fee_delegate_wallet_address;
            $txObj->referenceID = '';
            $transactionToken = $xunUserService->insertCryptoTransactionToken($txObj);

            $transactionObj = new stdClass();
            $transactionObj->status = "pending";
            $transactionObj->transactionHash = "";
            $transactionObj->transactionToken = $transactionToken;
            $transactionObj->senderAddress = $miner_fee_delegate_wallet_address;
            $transactionObj->recipientAddress = $internal_address;
            $transactionObj->userID = $business_id;
            $transactionObj->senderUserID = $business_id;
            $transactionObj->recipientUserID = "";
            $transactionObj->walletType = $minerFeeWalletType;
            $transactionObj->amount = $original_miner_fee_amount;
            $transactionObj->addressType = "nuxpay_wallet";
            $transactionObj->transactionType = "send";
            $transactionObj->escrow = 0;
            $transactionObj->referenceID = $withdrawal_id;
            $transactionObj->escrowContractAddress = '';
            $transactionObj->createdAt = $date;
            $transactionObj->updatedAt = $date;
            $transactionObj->expiresAt = '';

            $wallet_transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

            // $txHistoryObj->paymentDetailsID = $payment_details_id;
            $txHistoryObj->status = "pending";
            $txHistoryObj->transactionID = "";
            $txHistoryObj->transactionToken = $transactionToken;
            $txHistoryObj->senderAddress = $miner_fee_delegate_wallet_address;
            $txHistoryObj->recipientAddress = $internal_address;
            $txHistoryObj->senderUserID = $user_id;
            $txHistoryObj->recipientUserID = "";
            $txHistoryObj->walletType = $minerFeeWalletType;
            $txHistoryObj->amount = $original_miner_fee_amount;
            $txHistoryObj->transactionType = "nuxpay_wallet";
            $txHistoryObj->createdAt = $date;
            $txHistoryObj->updatedAt = $date;
            $txHistoryObj->referenceID = $withdrawal_id;
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

            $miner_fee_tx_data = array(
                "address" => $miner_fee_delegate_wallet_address,
                "reference_id" => $wallet_transaction_id,
                "reference_table" => "xun_wallet_transaction",
                "type" => 'miner_fee_payment',
                "wallet_type" => $minerFeeWalletType,
                "debit" => $original_miner_fee_amount,
            );

            $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);

            $miner_fee_tx_data = array(
                "address" => $internal_address,
                "reference_id" => $wallet_transaction_id,
                "reference_table" => "xun_wallet_transaction",
                "type" => 'fund_in',
                "wallet_type" => $minerFeeWalletType,
                "credit" => $original_miner_fee_amount,
            );

            $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);

            $curlParams = array(
                "command" => "fundOutCompanyWallet",
                "params" => array(
                    "senderAddress" => $miner_fee_delegate_wallet_address,
                    "receiverAddress" => $internal_address,
                    "amount" => $original_miner_fee_amount,
                    "satoshiAmount" => $miner_fee,
                    "walletType" => $minerFeeWalletType,
                    "id" => $wallet_transaction_id,
                    "transactionToken" => $transactionToken,
                    "addressType" => "credit",
                ),
            );

            $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);

            if ($curlResponse["code"] == 0) {
                $update_status = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $db->where('id', $withdrawal_id);
                $db->update('xun_request_fund_withdrawal', $update_status);

                $db->where('id', $wallet_transaction_id);
                $db->update('xun_wallet_transaction', $update_status);

                $update_withdrawal_data = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s")
                );
                $db->where('reference_id', $withdrawal_id);
                $db->where('transaction_type', 'manual_withdrawal');
                $db->update('xun_payment_gateway_withdrawal', $update_withdrawal_data);

                return $curlResponse;
            }

            $insertTx = array(
                "business_id" => $business_id,
                "sender_address" => $internal_address,
                "recipient_address" => $destination_address,
                "amount" => $withdrawal_balance,
                "amount_satoshi" => $satoshi_amount,
                "wallet_type" => $wallet_type,
                "credit" => 0,
                "debit" => $withdrawal_balance,
                "transaction_type" => "withdrawal",
                "reference_id" => $wallet_transaction_id,
                "created_at" => $date,
            );

            $invoice_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertTx);

            $translations_message = $this->get_translation_message('B00310') /*%%companyName%% Withdrawal Successful. */;
            $return_message = str_replace("%%companyName%%", $source, $translations_message);

            $data['withdrawal_id'] = $withdrawal_id;

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $return_message, "data" => $data);
        }

        // if($destination_address_type == 'external'){
        //     $miner_fee_tx_data = array(
        //         "address" => $internal_address,
        //         "reference_id" => $withdrawal_id,
        //         "reference_table" => "xun_request_fund_withdrawal",
        //         "type" => 'fund_in',
        //         "wallet_type" => $minerFeeWalletType,
        //         "debit" => $original_miner_fee_amount, 
        //     );

        //     $xunMinerFee->insertMinerFeeTransaction($miner_fee_tx_data);
        // }

        $txObj = new stdClass();
        $txObj->userID = $business_id;
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
        $transactionObj->userID = $business_id;
        $transactionObj->senderUserID = $business_id;
        $transactionObj->recipientUserID = "";
        $transactionObj->walletType = $wallet_type;
        $transactionObj->amount = $remainingWithdrawalAmount;
        $transactionObj->addressType = "withdrawal";
        $transactionObj->transactionType = "send";
        $transactionObj->escrow = 0;
        $transactionObj->referenceID = $withdrawal_id;
        $transactionObj->escrowContractAddress = '';
        $transactionObj->createdAt = $date;
        $transactionObj->updatedAt = $date;
        $transactionObj->expiresAt = '';

        $wallet_transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

        $payment_transaction_params = array(
            "business_id" => $business_id,
            "crypto_amount" => $remainingWithdrawalAmount,
            "wallet_type" => $wallet_type,
            "transaction_type" => "withdrawal"
        );

        $payment_tx_id = $xunPayment->insert_payment_transaction($payment_transaction_params);

        if (!$payment_tx_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        $payment_method_params = array(
            "address" => $destination_address,
            "wallet_type" => $wallet_type,
            "payment_tx_id" => $payment_tx_id,
            "type" => $destination_address_type
        );


        $payment_method_id = $xunPayment->insert_payment_method($payment_method_params);

        if (!$payment_method_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        $external_address = $xunCrypto->get_external_address($internal_address, $wallet_type);

        // $transactionObj->paymentID = $payment_id;
        $transactionObj->paymentTxID = $payment_tx_id;
        $transactionObj->paymentMethodID = $payment_method_id;
        $transactionObj->status = "pending";
        $transactionObj->senderInternalAddress = $internal_address;
        $transactionObj->senderExternalAddress = $external_address;
        $transactionObj->recipientInternalAddress = '';
        $transactionObj->recipientExternalAddress = $recipient_address;
        $transactionObj->senderUserID = '';
        $transactionObj->recipientUserID = $business_id;
        $transactionObj->walletType = $wallet_type;
        $transactionObj->amount = $remainingWithdrawalAmount;
        // $transactionObj->serviceChargeAmount = $remainingWithdrawalAmount;
        // $transactionObj->serviceChargeWalletType = $service_charge_wallet_type;
        $transactionObj->referenceID = '';
        $transactionObj->createdAt = $date;

        $payment_details_id = $xunPayment->insert_payment_details($transactionObj);

        $txHistoryObj->paymentDetailsID = $payment_details_id;
        $txHistoryObj->status = "pending";
        $txHistoryObj->transactionID = "";
        $txHistoryObj->transactionToken = $transactionToken;
        $txHistoryObj->senderAddress = $internal_address;
        $txHistoryObj->recipientAddress = $destination_address;
        $txHistoryObj->senderUserID = $business_id;
        $txHistoryObj->recipientUserID = '';
        $txHistoryObj->walletType = $wallet_type;
        $txHistoryObj->amount = $withdrawal_balance;
        $txHistoryObj->transactionType = "withdrawal";
        $txHistoryObj->referenceID = $withdrawal_id;
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
        $xunWallet->updateWalletTransaction($wallet_transaction_id, $updateWalletTx);

        $prepaidWalletServerURL =  $config["giftCodeUrl"];

        if ($destination_address_type == "external") {
            $curlParams = array(
                "command" => "fundOutExternal",
                "params" => array(
                    "senderAddress" => $internal_address,
                    "receiverAddress" => $destination_address,
                    "amount" => $remainingWithdrawalAmount,
                    "walletType" => $wallet_type,
                    "transactionToken" => $transactionToken,
                    "walletTransactionID" => $wallet_transaction_id
                )
            );
        } else if ($destination_address_type == "internal") {

            $curlParams = array(
                "command" => "fundOutCompanyWallet",
                "params" => array(
                    "senderAddress" => $internal_address,
                    "receiverAddress" => $destination_address,
                    "amount" => $remainingWithdrawalAmount,
                    "satoshiAmount" => $remainingWithdrawalSatoshi,
                    "walletType" => $wallet_type,
                    "id" => $wallet_transaction_id,
                    "transactionToken" => $transactionToken,
                    "addressType" => "nuxpay_wallet",
                ),
            );
        }

        $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);

        if ($curlResponse["code"] == 0) {
            $update_status = array(
                "status" => 'failed',
                "updated_at" => date("Y-m-d H:i:s")
            );

            $db->where('id', $withdrawal_id);
            $db->update('xun_request_fund_withdrawal', $update_status);

            $db->where('id', $wallet_transaction_id);
            $db->update('xun_wallet_transaction', $update_status);

            $update_withdrawal_data = array(
                "status" => 'failed',
                "updated_at" => date("Y-m-d H:i:s")
            );
            $db->where('reference_id', $withdrawal_id);
            $db->where('transaction_type', 'manual_withdrawal');
            $db->update('xun_payment_gateway_withdrawal', $update_withdrawal_data);

            $update_invoice_tx = array(
                "deleted" => 1
            );
            $db->where('reference_id', $wallet_transaction_id);
            $db->update('xun_payment_gateway_invoice_transaction', $update_invoice_tx);

            $db->where('id', $payment_details_id);
            $db->update('xun_payment_details', $update_withdrawal_data);

            $db->where('id', $fund_out_id);
            $db->update($fund_out_table, $update_withdrawal_data);

            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $curlResponse);
        }

        $insertTx = array(
            "business_id" => $business_id,
            "sender_address" => $internal_address,
            "recipient_address" => $destination_address,
            "amount" => $withdrawal_balance,
            "amount_satoshi" => $satoshi_amount,
            "wallet_type" => $wallet_type,
            "credit" => 0,
            "debit" => $withdrawal_balance,
            "transaction_type" => "withdrawal",
            "reference_id" => $wallet_transaction_id,
            "created_at" => $date,
        );

        $invoice_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertTx);

        $translations_message = $this->get_translation_message('B00310') /*%%companyName%% Withdrawal Successful. */;
        $return_message = str_replace("%%companyName%%", $source, $translations_message);

        $data['withdrawal_id'] = $withdrawal_id;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $return_message, "data" => $data);
    }

    public function getInvoiceDetailsByAddress($address)
    {
        $db = $this->db;

        $db->where('payment_address', $address);
        $db->join('xun_payment_gateway_payment_transaction b', 'a.pg_transaction_id = b.id');
        $invoice_details = $db->getOne('xun_payment_gateway_invoice_detail a');

        return $invoice_details;
    }

    public function getUserRequestFundBalance($wallet_type, $business_id)
    {
        global $xunCurrency, $account, $config;

        $db = $this->db;

        if ($config['isNewAccounting'] == 1) {
            $balance = $account->getBalance($business_id, $wallet_type);
        } else {
            $db->where('deleted', 0);
            $db->where('transaction_type', array('fund_in_to_destination', 'withhold', 'release_withhold'), 'NOT IN');
            $db->where('wallet_type', $wallet_type);
            $db->where('business_id', $business_id);
            $invoice_transaction = $db->getOne('xun_payment_gateway_invoice_transaction', 'SUM(credit) as totalCredit, SUM(debit) as totalDebit');

            $totalCredit = $invoice_transaction['totalCredit'];
            $totalDebit = $invoice_transaction['totalDebit'];


            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
            $decimal_places = $decimal_place_setting["decimal_places"];

            $balance = bcsub($totalCredit, $totalDebit, $decimal_places);
        }

        $internal_address_data = $this->get_nuxpay_user_internal_address($business_id);

        if ($internal_address_data['code'] != 1) {
            return $internal_address_data;
        }
        $internal_address = $internal_address_data['data']['internal_address'];

        $offset_amount = $this->get_offset_balance($internal_address, $wallet_type);

        if ($offset_amount != 0) {
            $db->where('currency_id', $wallet_type);
            $unit_conversion = $db->getValue('xun_marketplace_currencies', 'unit_conversion');

            $balance_satoshi = bcmul($balance, $unit_conversion);
            $remaining_balance = bcadd($balance_satoshi, $offset_amount);

            $balance = bcdiv($remaining_balance, $unit_conversion, 8);
        }

        return $balance;
    }

    public function get_nuxpay_withdrawal_balance($params)
    {
        $db = $this->db;
        $general = $this->general;
        $xunCrypto = $this->xunCrypto;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $business_id         = $params["business_id"];
        $wallet_type        = $params["wallet_type"];

        if (!$business_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if (!$wallet_type) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        if ($wallet_type) {
            $db->where("wallet_type", $wallet_type);
        }

        $db->where("business_id", $business_id);


        $db->orderBy("created_at", "DESC");
        $withdrawal_balance = $db->getValue("xun_payment_gateway_invoice_transaction", "balance");

        // if needed some other value from a different database, you need to query the where clause again before the new database query input.
        $db->where("currency_id", $wallet_type);
        $marketplace_currencies = $db->getValue('xun_marketplace_currencies', 'symbol');
        $unit = strtoupper($marketplace_currencies);

        $data["withdrawal_balance"] = $withdrawal_balance;
        $data["currency_unit"] = $unit;

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00297') /* Nuxpay Withdrawal Listing Successful.*/, "data" => $data);
    }

    public function get_nuxpay_withdrawal_listing($params)
    {
        global $xunCrypto, $excel;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        // // $business_id = trim($params['business_id']);
        // $payee_mobile_phone = trim($params['payee_mobile_phone']);
        // $status = trim($params['status']);
        // $from_datetime = $params["date_from"];
        // $to_datetime = $params["date_to"];
        // $payer_mobile_phone = $params['payer_mobile_phone'];
        // $payer_email_address = $params['payer_email_address'];
        // $status = $params['status'];
        // $see_all = trim($params["see_all"]);

        $business_id         = $params["business_id"];
        $date_from           = $params["date_from"];
        $date_to             = $params["date_to"];
        $status              = $params["status"];
        $see_all             = trim($params["see_all"]);
        $wallet_type         = $params["wallet_type"];
        $search_param        = $params["search_param"];
        $name                = $params['name'];
        $mobile              = $params['mobile'];
        $email               = $params['email'];
        $type                = $params['type'];

        $page_limit          = $setting->systemSetting["memberBlogPageLimit"];
        $page_number         = $params["page"] ? $params["page"] : 1;
        $page_size           = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order               = $params["order"] ? $params["order"] : "DESC";

        // check if the user has a valid id
        if (!$business_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $db->where('user_id', $business_id);
        $db->where('address_type', 'nuxpay_wallet');
        $db->where('active', 1);
        $crypto_user_address_data = $db->getOne('xun_crypto_user_address');

        $internal_address = $crypto_user_address_data['address'];

        $db->where('is_payment_gateway', 1);
        $xun_coins = $db->get('xun_coins', null, 'id, currency_id');

        if ($xun_coins) {
            $wallet_type_list = array_column($xun_coins, 'currency_id');

            $db->where('internal_address', $internal_address);
            $db->where('wallet_type', $wallet_type_list, 'IN');
            $external_address_list = $db->map('wallet_type')->ArrayBuilder()->get('xun_crypto_external_address', null, "wallet_type, external_address");

            foreach ($wallet_type_list as $v) {

                if (!$external_address_list[$v]) {
                    $external_address = $xunCrypto->get_external_address($internal_address, $v);
                    if ($external_address['code'] != 0) {
                        $address_list[$v][] = $external_address;
                    }
                } else {
                    $address_list[$v] = $external_address_list[$v];
                }
            }
        }

        $address_arr = array_values($address_list);

        if ($name || $mobile || $email) {
            if ($name) {
                $db->where('b.recipient_name', "%$name%", 'LIKE');
            }

            if ($mobile) {
                $db->where('b.recipient_mobile_number', "%$mobile%", 'LIKE');
            }

            if ($email) {
                $db->where('b.recipient_email_address', "%$email%", 'LIKE');
            }
            $db->where('b.business_id', $business_id);
            $db->where('a.message', 'send_fund');
            $db->join('xun_payment_gateway_send_fund b', 'a.reference_id = b.id', 'LEFT');
            $send_fund_filter_data = $db->map('transaction_hash')->ArrayBuilder()->get('xun_wallet_transaction a', null, 'a.transaction_hash, a.reference_id, a.id');
            $send_fund_tx_list = array_keys($send_fund_filter_data);
            if ($send_fund_tx_list) {
                $db->where('a.transaction_hash', $send_fund_tx_list,  'IN');
            } else {
                $db->where('a.transaction_hash', NULL);
            }
        }


        if ($search_param) {
            $db->where("(a.recipient_address LIKE ? OR a.transaction_hash LIKE ?)", array("%$search_param%", "%$search_param%"));
        }
        // select all data from the specific user id
        $db->where("a.business_id", $business_id);

        if ($date_from) {
            $date_from = date("Y-m-d H:i:s", $date_from);
            $db->where("a.created_at", $date_from, ">=");
        }

        if ($date_to) {
            $date_to = date("Y-m-d H:i:s", $date_to);
            $db->where("a.created_at", $date_to, "<=");
        }

        if ($status) {
            if ($status == 'success') {
                $db->where("a.status", array('success', 'escrow'), 'IN');
            } else {
                $db->where("a.status", $status);
            }
        }

        if ($wallet_type) {
            $db->where("a.wallet_type", $wallet_type);
        }

        if ($page_number < 1) {
            $page_number = 1;
        }
        // 
        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);
        // $db->where('recipient_address', $address_arr, 'NOT IN');
        // $db->where("(transaction_type = ? OR transaction_type = ?)", array("manual_withdrawal","request_fund"));
        $db->orderBy("a.created_at", $order);
        $db->join("xun_payment_gateway_fund_in b", "a.reference_id = b.reference_id", "LEFT");
        $copyDb = $db->copy();

        if ($type == 'export') {
            // no limits
            $withdrawal_listing = $db->get("xun_payment_gateway_withdrawal a", null, "a.business_id, a.created_at, a.sender_address, 
            a.recipient_address as destination_address, a.transaction_fee, a.transaction_hash, 
            CASE WHEN b.amount_receive IS NULL THEN a.amount_receive
            ELSE b.amount_receive END AS withdrawal_amount, a.miner_fee, 
            a.amount, a.status, a.wallet_type, a.transaction_type, a.escrow_id");
        } else {
            $withdrawal_listing = $db->get("xun_payment_gateway_withdrawal a", $limit, "a.business_id, a.created_at, a.sender_address, 
            a.recipient_address as destination_address, a.transaction_fee, a.transaction_hash, 
            CASE WHEN b.amount_receive IS NULL THEN a.amount_receive
            ELSE b.amount_receive END AS withdrawal_amount, a.miner_fee, 
            a.amount, a.status, a.wallet_type, a.transaction_type, a.escrow_id");
        }

        // $db->where('b.address_type', 'withdrawal');
        // $db->orderBy("a.created_at", $order);


        foreach ($withdrawal_listing as $key => $value) {
            $transaction_type = $value['transaction_type'];
            if ($value['transaction_hash']) {
                $transaction_hash = $value['transaction_hash'];
                // if($transaction_type == 'send_fund'){
                $tx_hash_list[] = $transaction_hash;
                // }
            }
        }

        if ($tx_hash_list) {
            $db->where("(a.message = ? or a.message = ?)", array("send_fund", "send_escrow"));
            // $db->where('a.message', 'send_fund');
            // $db->orWhere('a.message', 'send_escrow');
            $db->where('a.transaction_hash', $tx_hash_list, 'IN');
            $db->join('xun_payment_gateway_send_fund b', 'a.reference_id = b.id', 'LEFT');
            $wallet_tx_data = $db->map('transaction_hash')->ArrayBuilder()->get('xun_wallet_transaction a', null, 'a.transaction_hash, a.sender_address, a.recipient_address, b.tx_type, b.recipient_name, b.recipient_mobile_number, b.recipient_email_address, b.redeem_code, b.description');
        }

        foreach (array_values($withdrawal_listing) as $i => $withdrawal_list) {
            $transaction_hash = $withdrawal_list['transaction_hash'];

            $list[$i]['business_id'] = $withdrawal_list['business_id'];
            $list[$i]['created_at'] = $withdrawal_list['created_at'];
            $list[$i]['destination_address'] = $withdrawal_list['destination_address'];
            $list[$i]['sender_address'] = $withdrawal_list['sender_address'];
            $list[$i]['recipient_name'] = $wallet_tx_data[$transaction_hash]['recipient_name'] ? $wallet_tx_data[$transaction_hash]['recipient_name'] : '-';
            $list[$i]['recipient_mobile_number'] = $wallet_tx_data[$transaction_hash]['recipient_mobile_number'] ? $wallet_tx_data[$transaction_hash]['recipient_mobile_number'] : '-';
            $list[$i]['recipient_email_address'] = $wallet_tx_data[$transaction_hash]['recipient_email_address'] ? $wallet_tx_data[$transaction_hash]['recipient_email_address'] : '-';
            $list[$i]['transaction_hash'] = $withdrawal_list['transaction_hash'];
            $list[$i]['withdrawal_amount'] = $withdrawal_list['withdrawal_amount'];
            $list[$i]['miner_fee'] = $withdrawal_list['miner_fee'];
            $list[$i]['amount'] = $withdrawal_list['amount'];
            $list[$i]['status'] = $withdrawal_list['status'];
            $list[$i]['wallet_type'] = $withdrawal_list['wallet_type'];
            $list[$i]['escrow'] = $withdrawal_list['escrow_id'];
            $list[$i]['transaction_fee'] = $withdrawal_list['transaction_fee'];
            $list[$i]['transaction_type'] = ucwords(str_replace('_', ' ', $withdrawal_list['transaction_type']));

            if ($withdrawal_list['escrow_id'] != 0) {
                // $db->where('reference_id', $withdrawal_list['escrow_id']);
                // $escrow_table = $db->getOne('xun_escrow');

                $db->where('reference_id', $withdrawal_list['escrow_id']); // it uses send table id
                $escrow_chat_count = $db->getValue('xun_escrow_chat', 'count(*)');
            } else {
                $escrow_chat_count = 0;
            }
            $list[$i]['escrow_chat_count'] = $escrow_chat_count;
        }

        // $db->join('xun_wallet_transaction b', 'b.reference_id = a.id', 'INNER');

        // $withdrawal_result = $db->get("xun_request_fund_withdrawal a", $limit, 'a.id, a.business_id, a.wallet_type, a.withdrawal_amount, a.destination_address, a.status, a.created_at, b.fee, b.transaction_hash, b.exchange_rate, b.miner_fee_exchange_rate, b.address_type, b.amount');

        $totalRecord = $copyDb->getValue('xun_payment_gateway_withdrawal a', 'count(a.id)');

        $db->where('type', 'cryptocurrency');
        $img_list = $db->get('xun_marketplace_currencies', null, 'currency_id, image, symbol');

        foreach ($img_list as $img_obj) {
            $return_img_list[$img_obj['currency_id']] = $img_obj['image'];
            $return_symbol_list[$img_obj['currency_id']] = $img_obj['symbol'];
        }



        // foreach($withdrawal_result as $withdrawal_data){
        //     $fee = $withdrawal_data['fee'];
        //     $exchange_rate = $withdrawal_data['exchange_rate'];
        //     $miner_fee_exchange_rate = $withdrawal_data['miner_fee_exchange_rate'];
        //     if($fee > 0){
        //         $usd_amount = bcdiv($fee, $exchange_rate, 8);
        //         $miner_fee = bcmul($usd_amount, $miner_fee_exchange_rate, 8);
        //         $withdrawal_data['miner_fee'] = $miner_fee;
        //     }
        //     else{
        //         $withdrawal_data['miner_fee'] = $fee;
        //     }


        //     unset($withdrawal_data['fee']);
        //     unset($withdrawal_data['exchange_rate']);
        //     unset($withdrawal_data['miner_fee_exchange_rate']);
        //     $return[] = $withdrawal_data;
        // }

        // if (!$return) {
        //     return array('code' => 0, 'message' => "Failed", 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
        // }

        // export csv
        if ($type == 'export') {
            $header = array(
                "Date, Time",
                "Name",
                "Mobile",
                "Email",
                "Wallet Type",
                "From",
                "To",
                "Amount",
                "Status",
                "Processing Fee",
                "Miner Fee"
            );
            $dataKeyArr = array(
                'created_at',
                'recipient_name',
                'recipient_mobile_number',
                'recipient_email_address',
                'wallet_type',
                'sender_address',
                'destination_address',
                'amount',
                'status',
                'transaction_fee',
                'miner_fee'
            );
            $data["base64"] = $excel->exportExcelBase64($list, $header, $dataKeyArr);
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00297') /* Nuxpay Withdrawal Listing Successful.*/, 'data' => $data);
        }

        $num_record = !$see_all ? $page_size : $totalRecord;
        $total_page = ceil($totalRecord / $num_record);
        // $data['withdrawal_listing'] = $withdrawal_listing;
        $data['withdrawal_listing'] = $list;
        $data['crypto_symbol_list'] = $return_symbol_list;
        $data['crypto_img_list'] = $return_img_list;
        // $data['withdrawal_listing'] = $return;
        $data["totalPage"] = $total_page;
        $data['pageNumber']   = $page_number;
        $data['totalRecord']  = $totalRecord;
        $data['numRecord'] = $page_size;

        // $test = array("code" => 0, "status" => "ok", "statusMsg" => $translation['B00307'][$language] /*Admin Reseller Listing*/, 'data' => $data);

        // echo json_encode($test);        

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00297') /* Nuxpay Withdrawal Listing Successful.*/, "data" => $data);
    }

    public function nuxpay_escrow_get_messages($params)
    {
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $reference_id    = $params["reference_id"]; // escrow id
        // $tx_type         = $params["tx_type"];

        // if($tx_type == ''){
        //     // TODO LANGUAGE PLUGIN: Transaction type cannot be empty
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction type cannot be empty" /* Transaction type cannot be empty */, "developer_msg" => "Transaction type cannot be empty");
        // }

        if ($reference_id == '') {
            // TODO LANGUAGE PLUGIN: Transaction type cannot be empty
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Reference ID cannot be empty" /*Reference ID cannot be empty*/, "developer_msg" => "Reference ID cannot be empty");
        }

        $tx_table = "xun_escrow";

        // Is reference_id, tx_type valid escrow?
        // if($tx_type == 'send') {
        //     $tx_table = "xun_payment_gateway_send_fund";
        // } else {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid transaction type" /*Invalid transaction type*/, "developer_msg" => "Invalid transaction type");
        // }

        // $db->where('id', $reference_id);        
        // $fund_table = $db->getOne($tx_table);
        // if(!$fund_table) {
        //     return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => 'Fund table not found.');
        // }

        $db->where('id', $reference_id);
        // $db->where('tx_type', $tx_type);
        $escrow_table = $db->getOne('xun_escrow');
        if (!$escrow_table) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => 'Send request does not support escrow.');
        }

        // get data from escrow chat
        $db->where('reference_id', $reference_id);
        $escrow_chat_table = $db->get('xun_escrow_chat');

        if (empty($escrow_chat_table)) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => 'Message retrieved successfully', "data" => array());
        }

        $business_ids = array_column($escrow_chat_table, 'user_id');
        $db->where('id', $business_ids, 'IN');
        $user_records = $db->get('xun_user');

        foreach ($user_records as $user_record) {
            $user[$user_record['id']] = $user_record;
        }

        foreach (array_values($escrow_chat_table) as $i => $chat) {
            $data[$i] = $chat;
            $data[$i]['business_name'] = $user[$chat['user_id']]['nickname'];
        }



        return array("code" => 1, "message" => "SUCCESS", "message_d" => 'Message retrieved successfully', "data" => $data);
    }

    public function get_nuxpay_api_withdrawal_listing($params)
    {
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $business_id         = $params["business_id"];
        $date_from       = $params["date_from"];
        $date_to         = $params["date_to"];
        $status              = $params["status"];
        $wallet_type = $params["wallet_type"];
        $see_all             = trim($params["see_all"]);
        $search_param        = $params["search_param"];

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"] ? $params["page"] : 1;
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";

        // check if the user has a valid id
        if (!$business_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        if ($search_param) {
            $db->where("(recipient_address LIKE ? OR transaction_hash LIKE ?)", array("%$search_param%", "%$search_param%"));
        }
        // select all data from the specific user id
        $db->where("business_id", $business_id);

        if ($date_from) {
            $date_from = date("Y-m-d H:i:s", $date_from);
            $db->where("created_at", $date_from, ">=");
        }

        if ($date_to) {
            $date_to = date("Y-m-d H:i:s", $date_to);
            $db->where("created_at", $date_to, "<=");
        }

        if ($status) {
            $db->where("status", $status);
        }

        if ($wallet_type) {
            $db->where("wallet_type", $wallet_type);
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);
        $db->where('transaction_type', 'api_integration');
        $db->orderBy("created_at", $order);
        $copyDb = $db->copy();
        $withdrawal_result = $db->get("xun_payment_gateway_withdrawal", $limit, 'transaction_hash, business_id, wallet_type, recipient_address as destination_address, amount, amount_receive as withdrawal_amount, transaction_fee, miner_fee, status, created_at');

        $totalRecord = $copyDb->getValue('xun_payment_gateway_withdrawal', 'count(id)');

        $db->where('type', 'cryptocurrency');
        $img_list = $db->get('xun_marketplace_currencies', null, 'currency_id, image, symbol');

        foreach ($img_list as $img_obj) {
            $return_img_list[$img_obj['currency_id']] = $img_obj['image'];
            $return_symbol_list[$img_obj['currency_id']] = $img_obj['symbol'];
        }

        $num_record = !$see_all ? $page_size : $totalRecord;
        $total_page = ceil($totalRecord / $num_record);

        $data['crypto_symbol_list'] = $return_symbol_list;
        $data['crypto_img_list'] = $return_img_list;
        $data['withdrawal_listing'] = $withdrawal_result;
        $data["totalPage"] = $total_page;
        $data['pageNumber']   = $page_number;
        $data['totalRecord']  = $totalRecord;
        $data['numRecord'] = $page_size;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => 'Nuxpay Api Integration Withdrawal Listing Successful', "data" => $data);
    }

    public function get_wallet_balance($params, $user_id)
    {
        global $xunCurrency, $account, $config;
        $db = $this->db;
        $setting = $this->setting;

        $wallet_status         = $params["wallet_status"];
        $setting_type         = $params["setting_type"];

        if ($user_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        $db->where('id', $user_id);
        $xun_user =  $db->getOne('xun_user', 'id, nickname, reseller_id');

        $reseller_id = $xun_user['reseller_id'];

        $refund_fee_amount = '0.00';
        if ($reseller_id != '0') {
            $db->where('a.business_id', $user_id);
            $db->where('a.transaction_type', 'refund_fee');
            $db->where('a.deleted', '0');
            $db->join('xun_wallet_transaction b', 'a.reference_id = b.id', 'LEFT');
            $invoice_tx = $db->get('xun_payment_gateway_invoice_transaction a', null, 'a.id, a.amount, b.exchange_rate, a.transaction_hash');

            $refund_fee_amount = $setting->systemSetting['compensateFeeAmount'];
            $total_usd = "0.00";
            foreach ($invoice_tx as $key => $value) {
                $amount = $value['amount'];
                $exchange_rate = $value['exchange_rate'];

                $amount_usd = bcmul($amount, $exchange_rate, 2);
                $total_usd = bcadd($total_usd, $amount_usd, 2);
            }

            $refund_fee_amount = bcsub($refund_fee_amount, $total_usd, 2);
        }


        $refund_fee_amount = number_format((float)floor($refund_fee_amount), 2, '.', '');
        $data['refund_credit_balance'] = $refund_fee_amount < 0 ? "0.00" : $refund_fee_amount;

        $internal_address = '';
        if ($user_id) {
            $internal_address_data = $this->get_nuxpay_user_internal_address($user_id);

            if ($internal_address_data['code'] != 1) {
                return $internal_address_data;
            }
            $internal_address = $internal_address_data['data']['internal_address'];
        }

        if ($wallet_status == 1) {

            if ($setting_type == 'payment_gateway') {
                $db->where('user_id', $user_id);
                $db->where('name', 'showWallet');
                $user_setting = $db->getOne('xun_user_setting', 'id, name, value');

                if ($user_setting) {
                    $show_coin_arr = json_decode($user_setting['value']);
                } else {
                    $show_coin_arr = array();
                }
                $db->where("a.currency_id", $show_coin_arr, "IN");
            }
            //WALLET
            else if ($setting_type == 'nuxpay_wallet') {
                $db->where('user_id', $user_id);
                $db->where('name', 'showNuxpayWallet');
                $user_setting = $db->getOne('xun_user_setting', 'id, name, value');


                if ($user_setting) {
                    $show_coin_arr = json_decode($user_setting['value']);
                } else {
                    $show_coin_arr = array();
                }

                $db->where("a.currency_id", $show_coin_arr, "IN");
            } else if ($setting_type == 'both') {
                //BOTH pg and nuxpay wallet
                $db->where('user_id', $user_id);
                $db->where('name', array('showWallet', 'showNuxpayWallet'), 'IN');
                $user_setting_data = $db->map('name')->ArrayBuilder()->get('xun_user_setting', null, 'id, name, value');


                if ($user_setting_data['showNuxpayWallet']) {
                    $show_coin_arr_Nuxpay = json_decode($user_setting_data['showNuxpayWallet']['value']);
                } else {
                    $show_coin_arr_Nuxpay = array();
                }
                if ($user_setting_data['showWallet']) {
                    $show_coin_arr = json_decode($user_setting_data['showWallet']['value']);
                } else {
                    $show_coin_arr = array();
                }

                $combinedList = array_unique(array_merge_recursive($show_coin_arr_Nuxpay, $show_coin_arr));    //merge data
                if ($combinedList) {
                    $db->where("a.currency_id", $combinedList, "IN");
                }
            } else {
                $db->where('a.is_payment_gateway', '1');
            }
        } else {
            $db->where('a.is_payment_gateway', '1');
        }

        $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'LEFT');
        $db->orderBy("a.sequence", "ASC");
        $xun_coins = $db->get('xun_coins a', null, 'b.name, b.currency_id, b.symbol, b.display_symbol, b.image');

        $db->where('business_id', $user_id);
        $db->where('transaction_type', array('fund_in_to_destination', 'withhold', 'release_withhold'), 'NOT IN');
        $db->where('deleted', 0);
        $db->groupBy('wallet_type');
        $balance_list = $db->map('wallet_type')->ArrayBuilder()->get('xun_payment_gateway_invoice_transaction', null, 'wallet_type,SUM(credit) as totalCredit, SUM(debit) as totalDebit');

        $db->where('business_id', $user_id);
        $db->where('transaction_type', 'fund_in_to_destination', '!=');
        $db->where('deleted', 0);
        $db->groupBy('wallet_type');
        $receive_balance_list = $db->map('wallet_type')->ArrayBuilder()->get('xun_payment_gateway_invoice_transaction', null, 'wallet_type,SUM(credit) as totalCredit, SUM(debit) as totalDebit');

        $db->where('business_id', $user_id);
        $db->where('transaction_type',  array('withhold', 'release_withhold'), 'IN');
        $db->where('processed', 0);
        $db->where('deleted', 0);
        $db->groupBy('wallet_type');
        $withhold_balance_list = $db->map('wallet_type')->ArrayBuilder()->get('xun_payment_gateway_invoice_transaction', null, 'wallet_type,SUM(credit) as totalCredit, SUM(debit) as totalDebit');

        foreach ($xun_coins as $key => $value) {
            $wallet_type = $value['currency_id'];

            if ($config['isNewAccounting'] == 1) {
                $balance = $account->getBalance($user_id, $wallet_type);
                $withholding_wallet_type = $wallet_type . 'Withholding';
                $withhold_balance = $account->getWithholdBalance($user_id, $withholding_wallet_type);
            } else {
                $totalCredit = $balance_list[$wallet_type]['totalCredit'];
                $totalDebit = $balance_list[$wallet_type]['totalDebit'];

                $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
                $decimal_places = $decimal_place_setting["decimal_places"];

                $balance = bcsub($totalCredit, $totalDebit, $decimal_places);

                $totalWithholdCredit = $withhold_balance_list[$wallet_type]['totalCredit'];
                $totalWithholdDebit = $withhold_balance_list[$wallet_type]['totalDebit'];

                $withhold_balance = bcsub($totalWithholdCredit, $totalWithholdDebit, $decimal_places);
            }

            $offset_amount = $this->get_offset_balance($internal_address, $wallet_type);

            if ($offset_amount != 0) {
                $db->where('currency_id', $wallet_type);
                $unit_conversion = $db->getValue('xun_marketplace_currencies', 'unit_conversion');

                $balance_satoshi = bcmul($balance, $unit_conversion);
                $remaining_balance = bcadd($balance_satoshi, $offset_amount);

                $balance = bcdiv($remaining_balance, $unit_conversion, 8);
            }

            $totalReceivableCredit = $receive_balance_list[$wallet_type]['totalCredit'];
            $totalReceivableDebit = $receive_balance_list[$wallet_type]['totalDebit'];

            $receivable_balance = bcsub($totalReceivableCredit, $totalReceivableDebit, $decimal_places);

            // if (in_array($wallet_type, $show_coin_arr)) {
            //     $showWallet = 1;
            // }

            $xun_coins[$key]['balance'] = $balance <= 0 ? "0.00000000" : $balance;
            $xun_coins[$key]['receivable_balance'] = $receivable_balance <= 0 ? "0.00000000" : $receivable_balance;
            $xun_coins[$key]['withhold_balance'] = $withhold_balance <= 0 ? "0.00000000" : $withhold_balance;

            $symbol = strtoupper($value['symbol']);
            $xun_coins[$key]['symbol'] = $symbol;

            $display_symbol = strtoupper($value['display_symbol']);
            $xun_coins[$key]['display_symbol'] = $display_symbol;
            // $xun_coins[$key]['showNuxpayWallet'] = $showWallet = 1 ? $showWallet : 0;
        }

        $data['wallet_list'] = $xun_coins;

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00317') /* Asset Balance List */, "data" => $data);
    }

    public function get_withdrawal_details($params)
    {
        $db = $this->db;

        $tx_hash = $params['transaction_hash'];
        $id      = $params['id'];

        if ($tx_hash == '' && $id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00373') /*Transaction Hash cannot be empty*/, 'developer_msg' => 'Transaction Hash/ID cannot be empty');
        }

        if ($tx_hash) {
            $db->where('a.transaction_hash', $tx_hash);
        }

        if ($id) {
            $db->where('a.id', $id);
        }

        $db->join('xun_marketplace_currencies c', 'a.wallet_type = c.currency_id', 'LEFT');
        $db->join('xun_wallet_transaction b', 'a.id = b.reference_id', 'LEFT');
        $withdrawal_details = $db->getOne('xun_request_fund_withdrawal a', 'a.id, a.business_id, a.withdrawal_amount, a.destination_address, b.fee, b.exchange_rate, b.miner_fee_exchange_rate, b.transaction_hash, b.amount as amount_receive, a.wallet_type, c.symbol');

        $fee = $withdrawal_details['fee'];
        $exchange_rate = $withdrawal_details['exchange_rate'];
        $miner_fee_exchange_rate = $withdrawal_details['miner_fee_exchange_rate'];

        $usd_amount = bcdiv($fee, $exchange_rate, 8);
        $miner_fee = bcmul($usd_amount, $miner_fee_exchange_rate, 8);
        $withdrawal_details['miner_fee'] = $miner_fee;

        unset($withdrawal_details['fee']);
        unset($withdrawal_details['exchange_rate']);
        unset($withdrawal_details['miner_fee_exchange_rate']);

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00318') /* Withdrawal Details */, "data" => $withdrawal_details);
    }

    public function get_estimated_miner_fee($params, $user_id)
    {
        global $xunCrypto, $xunCurrency;
        $db = $this->db;

        $sender_address = $params['sender_address'];
        $transaction_type = $params['transaction_type']; //payment_gateway or blockchain
        $amount = $params['amount'];
        $wallet_type = $params['wallet_type'];

        if (!$sender_address && $user_id) {
            $db->where('user_id', $user_id);
            $db->where('address_type', 'nuxpay_wallet');
            $db->where('active', 1);
            $crypto_user_address = $db->getOne('xun_crypto_user_address');

            if (!$crypto_user_address) {
                return array("code" => 0, "message" => "FAILED", "message_d" => "Address not found.");
            }
            $sender_address = $crypto_user_address['address'];
        } else {
            $db->where('name', 'requestFundConsolidateWalletAddress');
            $sender_address = $db->getValue('system_settings', 'value');
        }

        $db->where('currency_id', $params['wallet_type']);
        $toSatoshiUnitConversion = $db->getValue('xun_marketplace_currencies', 'unit_conversion');
        $satoshiAmount = bcmul($params['amount'], $toSatoshiUnitConversion, 0);

        if ($transaction_type == 'available') {
            $minerFeeObj = $xunCrypto->calculate_miner_fee(
                $sender_address,
                $params['receiver_address'],
                $satoshiAmount,
                $params['wallet_type'],
                1
            );

            if ($minerFeeObj['status'] == "ok") {
                $txFee = $minerFeeObj['data']['txFee'];

                $walletInfo = $xunCrypto->get_wallet_info_by_wallet_type($sender_address, $params['wallet_type']);
                $db->where('currency_id', $walletInfo['feeType']);
                $toBaseUnitConversion = $db->getValue('xun_marketplace_currencies', 'unit_conversion');

                $decimal_place = $xunCurrency->get_currency_decimal_places($walletInfo['feeType']);
                $baseTxFee = bcdiv((string) $txFee, (string) $toBaseUnitConversion, $decimal_place);
                $finalTxFee = $baseTxFee;
                if ($params['wallet_type'] != $walletInfo['feeType']) {
                    $finalTxFee = $xunCurrency->get_conversion_amount($params['wallet_type'], $walletInfo['feeType'], $baseTxFee);
                }
                $returnData = array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00320') /* Estimated Miner Fee. */, 'txFee' => $finalTxFee);
                return $returnData;
            } else {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00541') /* Error retrieving estimated miner fee. */, "data" => $minerFeeObj);
            }
        } else if ($transaction_type == 'withholding') {
            // if sender address param is array
            $sender_address_arr = $params['sender_address'];
            if ($sender_address_arr) {
                foreach ($sender_address_arr as $key => $value) {
                    $sender_address = $value['address'];
                    $amount = $value['balance'];
                    $minerFeeObj = $xunCrypto->pg_calculate_miner_fee_external($sender_address, $amount, $wallet_type);
                    $minerFeeWalletType = strtolower($minerFeeObj['data']['minerFeeWalletType']);
                    $minerFee = $minerFeeObj['data']['additionalMinerFee'];


                    if (!$decimal_place) {
                        $decimal_place = $xunCurrency->get_currency_decimal_places($minerFeeWalletType);
                    }

                    if ($wallet_type != $minerFeeWalletType) {
                        $baseCurrencyMinerFee = $xunCurrency->get_conversion_amount($wallet_type, $minerFeeWalletType, $minerFee);
                        $totalMinerFee = bcadd($totalMinerFee, $baseCurrencyMinerFee, $decimal_place);
                    } else {
                        $minerFeeArr['miner_fee_amount'] = $minerFee;
                        $totalMinerFee = bcadd($totalMinerFee, $minerFee, $decimal_place);
                    }
                }
                return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00320') /* Estimated Miner Fee. */, 'txFee' => $totalMinerFee);
            } else {
                return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00320') /* Estimated Miner Fee. */, 'txFee' => '0.00000000');
            }
        } else {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00541') /* Error retrieving estimated miner fee. */, "developer_msg" => 'Invalid Transaction Type');
        }
    }

    public function get_reseller_details($params, $source)
    {
        $db = $this->db;

        $referral_code = $params['referral_code'];
        $username = $params['username'];
        $promo_code = $params['promo_code'];
        $type = $params['type'];

        if ($promo_code) {
            $db->where('referral_code', $promo_code);
            $db->orWhere('username', $promo_code);
        } else {
            if ($referral_code) {
                $db->where('referral_code', $referral_code);
            } else {
                $db->where('username', $username);
            }
        }

        $db->where('type', $type);
        $db->where('source', $source);
        $db->where('deleted', 0);
        $reseller_detail = $db->getOne('reseller', 'username, referral_code');

        $data['reseller_username'] = $reseller_detail ? $reseller_detail['username'] : '';
        $data['referral_code'] = $reseller_detail ? $reseller_detail['referral_code'] : '';

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00327') /* Reseller Username */, 'data' => $data);
    }

    public function get_fund_out_coin_listing($params)
    {
        global $xunCurrency;
        $db = $this->db;
        $post = $this->post;

        $business_id = $params['business_id'];
        $isListOnly = $params['listOnly'];
        $listOnlyLength = $params['listOnlyLength'];

        if (!$business_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00560') /*Business ID is empty.*/);
        }

        $db->where('c.is_auto_fund_out', '1');
        $db->join('xun_marketplace_currencies mc', 'c.currency_id = mc.currency_id', 'LEFT');
        $db->orderBy('c.id', 'Desc');
        $coins_arr = $db->get('xun_coins c', null, 'mc.symbol, mc.display_symbol, mc.image, mc.name, c.currency_id as wallet_type');

        $db->where('business_id', $business_id);
        $address_arr = $db->map('wallet_type')->ArrayBuilder()->get('blockchain_external_address', null, 'address, wallet_type, status');

        $db->where('business_id', $business_id);
        $dest_result = $db->get('blockchain_external_address', null, 'address, wallet_type, status');

        foreach ($coins_arr as $key => $coinDetails) {
            $coinWalletAddress = $address_arr[$coinDetails['wallet_type']]['address'];
            $coinWalletStatus = $address_arr[$coinDetails['wallet_type']]['status'];

            $coins_arr[$key]['address'] = !empty($coinWalletAddress) ? $coinWalletAddress : "";
            $coins_arr[$key]['status'] = !empty($coinWalletStatus) ?  $coinWalletStatus : 0;
        }
        
        foreach ($coins_arr as $coinDetails) {
            $wallet_type = $coinDetails['wallet_type'];
            $address_list = [];
            $address_list2 = [];
            foreach($dest_result as $key=> $value){
                if ($value['wallet_type'] == $wallet_type){
                    $address_list = array(
                        "address" => $value["address"],
                        "status" => $value['status'],
                        "wallet_type" => $value['wallet_type'],
                        "balance" => 0,
                        "check" => 0,
                    );
                    $address_list2 = array(
                        "address" => $value["address"],
                        "wallet_type" => $value['wallet_type'],
                        "balance" => 0,
                        "check" => 0,
                    );
                    $return[$wallet_type][] = $address_list;
                    $return2[] = $address_list2;
                }
            }
        }
        $returnData["destination_addresses"] = $return;
        $returnData["address"] = $return2;
       
        if ($isListOnly == "true") {
            $index = $listOnlyLength * 5 -5;
            $nums1 = 0;
            $intCount = 0;
            foreach($return2 as $key=> $value){
                $crypto_params['walletAddress'] = $value['address'];
                $crypto_params['walletType'] = $value['wallet_type'];

                if ($nums1 >= $index){
                    if ($intCount <5){
                        if (!empty($crypto_params['walletAddress'])) {
                            // return array('code' => 33333, 'message' => "FAILED", 'message_d' => $index /*Error retrieving wallet balance.*/);

                            $crypto_results = $post->curl_crypto("getExternalBalance", $crypto_params, 2);
    
                            if ($crypto_results['status'] == 'error') {
                                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00561') /*Error retrieving wallet balance.*/);
                            } else {
                                $balance = $crypto_results['data']['balance'];
                                $unit_conversion = $crypto_results['data']['unitConversion'];
                                $decimal_places = $xunCurrency->get_currency_decimal_places($value['wallet_type']);
    
                                $returnData["address"][$key]['balance'] = bcdiv($balance, $unit_conversion, $decimal_places);
                                $returnData["address"][$key]['check'] = 1;
                            }
                        } else {
                            $returnData["address"][$key]['balance'] = 0;
                        }
                    }
                    else{
                        continue;
                    }
                    $intCount = $intCount + 1;
                }
                $nums1 = $nums1 + 1;
            }
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00334') /*Fund out coin listing*/, 'data' => $coins_arr, 'result' => $returnData);
    }

    public function set_fund_out_external_address($params)
    {
        $db = $this->db;
        $post = $this->post;
        $general = $this->general;

        $business_id = $params['business_id'];
        $currency_id = $params['currency_id'];
        $status = $params['status'];

        if (!$business_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00560') /*Business ID is empty.*/);
        }

        if (!$currency_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00562')/*Currency ID is empty.*/);
        }

        if ($status == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00568')/*Status is empty.*/);
        }

        if ($status != 0 && $status != 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00569')/*Invalid status.*/);
        }

        // Business validation
        $db->where('id', $business_id);
        $db->where('type', 'business');
        $db->where('disabled', '0');
        $business_details = $db->getOne('xun_user');

        if (!$business_details) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00563')/*Business cannot be found.*/);
        }

        // Currency validation
        $db->where('xc.currency_id', $currency_id);
        $db->where('xc.is_auto_fund_out', '1');
        $db->join('xun_marketplace_currencies mc', 'xc.currency_id = mc.currency_id', 'LEFT');
        $db->where('mc.status', '1');
        $currency_exists = $db->getOne('xun_coins xc');

        if (!$currency_exists) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00564')/*Currency cannot be found.*/);
        }

        // Check if user already has fund out address
        $db->where('business_id', $business_id);
        $db->where('wallet_type', $currency_id);
        $address_details = $db->getOne('blockchain_external_address');

        if ($status == 1) {
            if ($address_details && $address_details['status'] == 0) {
                // Update status to 1
                $db->where('business_id', $business_id);
                $db->where('wallet_type', $currency_id);
                $db->where('status', '0');

                $updateData = array(
                    "status" => 1,
                );
                $updateSuccess = $db->update('blockchain_external_address', $updateData);

                if ($updateSuccess) {
                    $address_details['status'] = 1;
                } else {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00565')/*Failed to activate address.*/);
                }
            } else if (!$address_details) {
                // Call BC to create new address
                $crypto_params['walletType'] = $currency_id;
                $crypto_params['reference'] = "NuxPay auto fund out address";
                $crypto_params['businessID'] = $business_id;
                $crypto_params['businessName'] = $business_details['nickname'];

                $crypto_results = $post->curl_crypto("createNewAccountExternal", $crypto_params, 2);

                if ($crypto_results['status'] == 'ok') {
                    $insertData = array(
                        "business_id" => $business_id,
                        "address" => $crypto_results['data']['address'],
                        "wallet_type" => $currency_id,
                        "status" => '1',
                        "created_at" => date("Y-m-d H:i:s")
                    );

                    $insertSuccess = $db->insert('blockchain_external_address', $insertData);

                    if (!$insertSuccess) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00566')/*Failed to insert new address.*/);
                    } else {
                        $db->where('user_id', $business_id);
                        $db->where('name', 'bcExternalTransferServiceChargeBearer');
                        $setting1Exists = $db->getOne('xun_user_setting');

                        if (!$setting1Exists) {
                            // Insert into xun_user_setting
                            $insertData = array(
                                'user_id' => $business_id,
                                'name' => 'bcExternalTransferServiceChargeBearer',
                                'value' => 'sender',
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            );

                            $db->insert('xun_user_setting', $insertData);
                        }

                        $db->where('user_id', $business_id);
                        $db->where('name', 'bcExternalTransferMinerFeeBearer');
                        $setting2Exists = $db->getOne('xun_user_setting');

                        if (!$setting2Exists) {
                            $insertData = array(
                                'user_id' => $business_id,
                                'name' => 'bcExternalTransferMinerFeeBearer',
                                'value' => 'tokenWallet',
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            );

                            $db->insert('xun_user_setting', $insertData);
                        }

                        $address_details = $insertData;
                        $address_details['id'] = $insertSuccess;
                    }
                } else {
                    $tag = "Generate Auto Fund Out Address Failed";
                    $message = "Business ID: " . $business_id . "\n";
                    $message .= "Business Name: " . $business_details['nickname'] . "\n";
                    $message .= "Wallet Type: " . $currency_id . "\n";
                    $message .= "Error Message: " . $crypto_results['statusMsg'] . "\n";
                    $message .= "Time: " . date("Y-m-d H:i:s") . "\n";

                    $thenux_params["tag"]         = $tag;
                    $thenux_params["message"]     = $message;
                    $thenux_params["mobile_list"] = $xun_numbers;
                    $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00567')/*Failed to create new external account.*/);
                }
            }

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00335')/*Auto Fund out address.*/, 'data' => $address_details);
        } else if ($status == 0) {
            //disable address 
            if ($address_details && $address_details['status'] == 1) {
                $updateData = array(
                    'status' => '0'
                );

                $db->where('business_id', $business_id);
                $db->where('wallet_type', $currency_id);
                $updateSucess = $db->update('blockchain_external_address', $updateData);

                if (!$updateSucess) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00570')/*Failed to disable address.*/);
                } else {
                    $address_details['status'] = 0;
                }
            }
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00337')/*Auto Fund out address disabled.*/, 'data' => $address_details);
        }
    }

    public function set_fund_out_external_address_V2($params)
    {
        $db = $this->db;
        $post = $this->post;
        $general = $this->general;

        $business_id = $params['business_id'];
        $currency_id = $params['currency_id'];
        $status = $params['status'];
        $address = $params['address'];

        if (!$business_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00560') /*Business ID is empty.*/);
        }

        if (!$currency_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00562')/*Currency ID is empty.*/);
        }

        if ($status == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00568')/*Status is empty.*/);
        }

        if ($status != 0 && $status != 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00569')/*Invalid status.*/);
        }

        if (!$address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00314')/*address cannot be empty.*/);
        }

        // Business validation
        $db->where('id', $business_id);
        $db->where('type', 'business');
        $db->where('disabled', '0');
        $business_details = $db->getOne('xun_user');

        if (!$business_details) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00563')/*Business cannot be found.*/);
        }

        // Currency validation
        $db->where('xc.currency_id', $currency_id);
        $db->where('xc.is_auto_fund_out', '1');
        $db->join('xun_marketplace_currencies mc', 'xc.currency_id = mc.currency_id', 'LEFT');
        $db->where('mc.status', '1');
        $currency_exists = $db->getOne('xun_coins xc');

        if (!$currency_exists) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00564')/*Currency cannot be found.*/);
        }

        // Check if user already has fund out address
        $db->where('business_id', $business_id);
        $db->where('wallet_type', $currency_id);
        $db->where('address', $address);
        $address_details = $db->getOne('blockchain_external_address');

        if ($status == 1) {
            if ($address_details && $address_details['status'] == 0) {
                //Deactivate other sender address
                $db->where('business_id', $business_id);
                $db->where('wallet_type', $currency_id);
                $db->where('status', '1');
                $db->where("address", $address, "<>");
                $db->update("blockchain_external_address", array("status"=>0));

                // Update status to 1
                $db->where('business_id', $business_id);
                $db->where('wallet_type', $currency_id);
                $db->where('status', '0');
                $db->where('address', $address);

                $updateData = array(
                    "status" => 1,
                );
                $updateSuccess = $db->update('blockchain_external_address', $updateData);

                if ($updateSuccess) {
                    $address_details['status'] = 1;
                } else {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00565')/*Failed to activate address.*/);
                }
            } else if (!$address_details) {
                // Call BC to create new address
                $crypto_params['walletType'] = $currency_id;
                $crypto_params['reference'] = "NuxPay auto fund out address";
                $crypto_params['businessID'] = $business_id;
                $crypto_params['businessName'] = $business_details['nickname'];

                $crypto_results = $post->curl_crypto("createNewAccountExternal", $crypto_params, 2);

                if ($crypto_results['status'] == 'ok') {
                    $insertData = array(
                        "business_id" => $business_id,
                        "address" => $crypto_results['data']['address'],
                        "wallet_type" => $currency_id,
                        "status" => '1',
                        "created_at" => date("Y-m-d H:i:s")
                    );

                    $insertSuccess = $db->insert('blockchain_external_address', $insertData);

                    if (!$insertSuccess) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00566')/*Failed to insert new address.*/);
                    } else {
                        $db->where('user_id', $business_id);
                        $db->where('name', 'bcExternalTransferServiceChargeBearer');
                        $setting1Exists = $db->getOne('xun_user_setting');

                        if (!$setting1Exists) {
                            // Insert into xun_user_setting
                            $insertData = array(
                                'user_id' => $business_id,
                                'name' => 'bcExternalTransferServiceChargeBearer',
                                'value' => 'sender',
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            );

                            $db->insert('xun_user_setting', $insertData);
                        }

                        $db->where('user_id', $business_id);
                        $db->where('name', 'bcExternalTransferMinerFeeBearer');
                        $setting2Exists = $db->getOne('xun_user_setting');

                        if (!$setting2Exists) {
                            $insertData = array(
                                'user_id' => $business_id,
                                'name' => 'bcExternalTransferMinerFeeBearer',
                                'value' => 'tokenWallet',
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                            );

                            $db->insert('xun_user_setting', $insertData);
                        }

                        $address_details = $insertData;
                        $address_details['id'] = $insertSuccess;
                    }
                } else {
                    $tag = "Generate Auto Fund Out Address Failed";
                    $message = "Business ID: " . $business_id . "\n";
                    $message .= "Business Name: " . $business_details['nickname'] . "\n";
                    $message .= "Wallet Type: " . $currency_id . "\n";
                    $message .= "Error Message: " . $crypto_results['statusMsg'] . "\n";
                    $message .= "Time: " . date("Y-m-d H:i:s") . "\n";

                    $thenux_params["tag"]         = $tag;
                    $thenux_params["message"]     = $message;
                    $thenux_params["mobile_list"] = $xun_numbers;
                    $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00567')/*Failed to create new external account.*/);
                }
            }

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00335')/*Auto Fund out address.*/, 'data' => $address_details);
        } else if ($status == 0) {
            //disable address 
            if ($address_details && $address_details['status'] == 1) {
                $updateData = array(
                    'status' => '0'
                );

                $db->where('business_id', $business_id);
                $db->where('wallet_type', $currency_id);
                $db->where('address', $address);
                $updateSucess = $db->update('blockchain_external_address', $updateData);

                if (!$updateSucess) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00570')/*Failed to disable address.*/);
                } else {
                    $address_details['status'] = 0;
                }
            }
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00337')/*Auto Fund out address disabled.*/, 'data' => $address_details);
        }
    }

    public function generate_external_address($params)
    {
        $db = $this->db;
        $post = $this->post;
        $general = $this->general;

        $business_id = $params['business_id'];
        $currency_id = $params['currency_id'];

        if (!$business_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00560') /*Business ID is empty.*/);
        }

        if (!$currency_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00562')/*Currency ID is empty.*/);
        }

        // Business validation
        $db->where('id', $business_id);
        $db->where('type', 'business');
        $db->where('disabled', '0');
        $business_details = $db->getOne('xun_user');

        if (!$business_details) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00563')/*Business cannot be found.*/);
        }

        // Currency validation
        $db->where('xc.currency_id', $currency_id);
        $db->where('xc.is_auto_fund_out', '1');
        $db->join('xun_marketplace_currencies mc', 'xc.currency_id = mc.currency_id', 'LEFT');
        $db->where('mc.status', '1');
        $currency_exists = $db->getOne('xun_coins xc');

        if (!$currency_exists) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00564')/*Currency cannot be found.*/);
        }

        // Check if user already has fund out address
        $db->where('business_id', $business_id);
        $db->where('wallet_type', $currency_id);
        $address_details = $db->getOne('blockchain_external_address');

        // Call BC to create new address
        $crypto_params['walletType'] = $currency_id;
        $crypto_params['reference'] = "NuxPay auto fund out address";
        $crypto_params['businessID'] = $business_id;
        $crypto_params['businessName'] = $business_details['nickname'];

        $crypto_results = $post->curl_crypto("createNewAccountExternal", $crypto_params, 2);

        if ($crypto_results['status'] == 'ok') {
            $insertData = array(
                "business_id" => $business_id,
                "address" => $crypto_results['data']['address'],
                "wallet_type" => $currency_id,
                "status" => '0',
                "created_at" => date("Y-m-d H:i:s")
            );

            $insertSuccess = $db->insert('blockchain_external_address', $insertData);

            if (!$insertSuccess) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00566')/*Failed to insert new address.*/);
            } else {
                $db->where('user_id', $business_id);
                $db->where('name', 'bcExternalTransferServiceChargeBearer');
                $setting1Exists = $db->getOne('xun_user_setting');

                if (!$setting1Exists) {
                    // Insert into xun_user_setting
                    $insertData = array(
                        'user_id' => $business_id,
                        'name' => 'bcExternalTransferServiceChargeBearer',
                        'value' => 'sender',
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                    );

                    $db->insert('xun_user_setting', $insertData);
                }

                $db->where('user_id', $business_id);
                $db->where('name', 'bcExternalTransferMinerFeeBearer');
                $setting2Exists = $db->getOne('xun_user_setting');

                if (!$setting2Exists) {
                    $insertData = array(
                        'user_id' => $business_id,
                        'name' => 'bcExternalTransferMinerFeeBearer',
                        'value' => 'tokenWallet',
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                    );

                    $db->insert('xun_user_setting', $insertData);
                }

                $address_details = $insertData;
                $address_details['id'] = $insertSuccess;
            }
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00335')/*Auto Fund out address.*/, 'data' => $address_details);
        } else {
            $tag = "Generate Auto Fund Out Address Failed";
            $message = "Business ID: " . $business_id . "\n";
            $message .= "Business Name: " . $business_details['nickname'] . "\n";
            $message .= "Wallet Type: " . $currency_id . "\n";
            $message .= "Error Message: " . $crypto_results['statusMsg'] . "\n";
            $message .= "Time: " . date("Y-m-d H:i:s") . "\n";

            $thenux_params["tag"]         = $tag;
            $thenux_params["message"]     = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00567')/*Failed to create new external account.*/);
        }
    }

    public function get_fund_out_listing($params)
    {
        global $xunCurrency;
        global $excel;
        $db = $this->db;
        $general = $this->general;
        $pagingResult = array();

        $business_id = $params['business_id'];
        $currency_id = $params['currency_id'];
        $date_from = $params['date_from'];
        $date_to = $params['date_to'];
        $status = $params['status'];
        $type = $params['type'];
        // $header = $params['header'];

        $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
        $limit = $general->getLimit($pageNumber);

        if ($currency_id) {
            $db->where('is_auto_fund_out', '1');
            $db->where('currency_id', $currency_id);
            $is_auto_fund_out = $db->getOne('xun_coins');

            // Currency not auto fund out
            if (!$is_auto_fund_out) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00433')/*Invalid currency.*/);
            } else {
                $db->where('a.wallet_type', $currency_id);
            }
        }

        if (!$business_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00560') /*Business ID is empty.*/);
        } else {
            $db->where('a.business_id', $business_id);
        }

        if ($status) {
            $db->where('a.status', $status);
        }

        if ($date_from) {
            $date_from = date("Y-m-d H:i:s", $date_from);
            $db->where("a.created_at", $date_from, ">=");
        }

        if ($date_to) {
            $date_to = date("Y-m-d H:i:s", $date_to);
            $db->where("a.created_at", $date_to, "<=");
        }

        $db->orderBy('a.id', 'DESC');
        $copyDb = $db->copy();
        $exportDb = $db->copy();
        $fund_out_listing = $db->get('xun_crypto_fund_out_details a', $limit, 'a.sender_address, a.recipient_address, a.amount, a.wallet_type, a.remark, a.created_at, a.service_charge_amount, a.pool_amount, a.tx_hash, a.status');

        $db->where('type', 'cryptocurrency');
        $img_list = $db->get('xun_marketplace_currencies', null, 'currency_id, image, symbol');

        foreach ($img_list as $img_obj) {
            $return_img_list[$img_obj['currency_id']] = $img_obj['image'];
            $return_symbol_list[$img_obj['currency_id']] = $img_obj['symbol'];
        }

        $crypto['crypto_symbol_list'] = $return_symbol_list;
        $crypto['crypto_img_list'] = $return_img_list;

        if (!$fund_out_listing) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00001')/*No Results Found.*/, 'data' => $fund_out_listing, 'crypto' => $crypto);
        } else {
            // get coin img urls
            $db->where('a.is_auto_fund_out', '1');
            $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'LEFT');
            $coins_arr = $db->get('xun_coins a', null, 'a.currency_id, b.symbol, b.image as image_url');

            // Change key of array to currency_id
            foreach ($coins_arr as $key => $val) {
                $coins_img_arr[$val['currency_id']] = $val;
            }


            foreach ($fund_out_listing as $key => $val) {
                $decimal_places = $xunCurrency->get_currency_decimal_places($val['wallet_type']);
                $amount = $val['amount'];
                $service_charge_amount = $val['service_charge_amount'];
                $pool_amount = $val['pool_amount'];

                $result = bcadd($amount, $service_charge_amount, $decimal_places);
                $total = bcadd($result, $pool_amount, $decimal_places);

                $fund_out_listing[$key]['amount'] = bcdiv($amount, 1, $decimal_places);
                $fund_out_listing[$key]['service_charge_amount'] = bcdiv($val['service_charge_amount'], 1, $decimal_places);
                $fund_out_listing[$key]['pool_amount'] = bcdiv($val['pool_amount'], 1, $decimal_places);
                $fund_out_listing[$key]['total'] = $total;
                $fund_out_listing[$key]['remark'] = $fund_out_listing[$key]['remark'] ? $fund_out_listing[$key]['remark'] : '-';
                $fund_out_listing[$key]['coin_img_url'] = $coins_img_arr[$val['wallet_type']]['image_url'];
            }

            if ($type == "export" && $status == "confirmed" || $type == "export" && $status == "pending") {
                $header = array(
                    "Date, Time",
                    "Fund Out To",
                    "Wallet Type",
                    "Amount",
                    "Processing Fee",
                    "Miner Fee",
                    "Total"
                );
                $dataKeyArr = array(
                    "created_at",
                    "recipient_address",
                    "wallet_type",
                    "amount",
                    "service_charge_amount",
                    "pool_amount",
                    "total"
                );

                // Export CSV no limit
                $exportList = $exportDb->get('xun_crypto_fund_out_details a', null, 'a.sender_address, a.recipient_address, a.amount, a.wallet_type, a.remark, a.created_at, a.service_charge_amount, a.pool_amount, (a.amount + a.service_charge_amount + a.pool_amount) AS `total`');

                $data["base64"] = $excel->exportExcelBase64($exportList, $header, $dataKeyArr);
                return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00336')/*Fund out history.*/, 'data' => $data);
            } else if ($type == "export" && $status == "failed") {
                $header = array(
                    "Date, Time",
                    "Fund Out To",
                    "Wallet Type",
                    "Amount",
                    "Remark"
                );
                $dataKeyArr = array(
                    "created_at",
                    "recipient_address",
                    "wallet_type",
                    "amount",
                    "remark"
                );

                // Export CSV no limit
                $exportList = $exportDb->get('xun_crypto_fund_out_details a', null, 'a.sender_address, a.recipient_address, a.amount, a.wallet_type, a.remark, a.created_at, a.service_charge_amount, a.pool_amount');

                $data["base64"] = $excel->exportExcelBase64($exportList, $header, $dataKeyArr);
                return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00336')/*Fund out history.*/, 'data' => $data);
            }

            $totalRecord = $copyDb->getValue('xun_crypto_fund_out_details a', 'count(*)');
            $pagingResult['pageNumber'] = $pageNumber;
            $pagingResult['totalPage'] = ceil($totalRecord / (int)$limit[1]);
            $pagingResult['totalRecord'] = $totalRecord;
            $pagingResult['numRecord'] = count($fund_out_listing);

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00336')/*Fund out history.*/, 'data' => $fund_out_listing, 'pagination' => $pagingResult, 'crypto' => $crypto);
        }
    }

    public function crypto_miner_fee_get($params)
    {
        global $xunCrypto, $xunCurrency;

        $sender_addr = $params['sender_address'];
        $receiver_addr = $params['receiver_address'];
        $amount = $params['amount'];
        $wallet_type = strtolower($params['wallet_type']);

        if (!$sender_addr) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00573') /*Sender address cannot be empty.*/, 'developer_msg' => 'Sender address cannot be empty.');
        }

        // if (!$receiver_addr){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00574') /*Receiver address cannot be empty.*/, 'developer_msg' => 'Receiver address cannot be empty.');
        // }

        if (!$amount) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00575') /*Amount cannot be empty.*/, 'developer_msg' => 'Amount cannot be empty.');
        }

        if (!$wallet_type) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00576') /*Wallet type cannot be empty.*/, 'developer_msg' => 'Wallet type cannot be empty.');
        }

        // $wallet_info = $xunCrypto->get_wallet_info($sender_addr, $wallet_type);
        // $minerFeeWalletType = strtolower($wallet_info[$wallet_type]['feeType']);
        // $minerFeeDetails = $xunCrypto->calculate_miner_fee($sender_addr, $receiver_addr, $amount, $wallet_type, 1);
        $minerFeeDetails = $xunCrypto->calculate_miner_fee_external($sender_addr, $amount, $wallet_type);

        if ($minerFeeDetails['status'] == 'error') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00577') /*BC API return error*/, 'developer_msg' => 'BC API return error', 'error_msg' => $minerFeeDetails['statusMsg']);
        } else {
            $minerFeeWalletType = $minerFeeDetails['data']['minerFeeWalletType'];
            $minerFee = $minerFeeDetails['data']['minerFee'];

            // $conversionRate = $xunCurrency->get_crypto_conversion_rate($minerFeeWalletType);
            // $decimalPlaces = $xunCurrency->get_currency_decimal_places($minerFeeWalletType);
            // $convertedMinerFee = bcdiv($minerFee, $conversionRate, $decimalPlaces);

            if ($wallet_type != $minerFeeWalletType) {
                $baseCurrencyMinerFee = $xunCurrency->get_conversion_amount($wallet_type, $minerFeeWalletType, $minerFee);
                $minerFeeArr['miner_fee_amount'] = $baseCurrencyMinerFee;
                $minerFeeArr['ethereum_miner_fee_amount'] = $minerFee;
            } else {
                $minerFeeArr['miner_fee_amount'] = $minerFee;
            }

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00346') /*Miner Fee.*/, 'data' => $minerFeeArr);
        }
    }

    public function set_wallet_status($params)
    {
        $db = $this->db;

        $business_id = $params['business_id'];
        $wallet_type = $params['wallet_type'];
        $status = $params['status'] ? 1 : 0;

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "Wallet Type cannot be empty");
        }

        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $db->where('user_id', $business_id);
        $db->where('name', 'showWallet');
        $user_setting = $db->getOne('xun_user_setting');

        if ($user_setting) {
            $arr_val = json_decode($user_setting['value']);

            if ($status == 1 && !in_array($wallet_type, $arr_val)) {
                array_push($arr_val, $wallet_type);
            } elseif ($status == 0 && in_array($wallet_type, $arr_val)) {
                foreach ($arr_val as $key => $value) {
                    if ($value == $wallet_type) {
                        unset($arr_val[$key]);
                    }
                }
            }

            $arr_val = array_values($arr_val);

            if (count($arr_val) == "0") {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            } else {
                $updateArray = array(
                    "value" => json_encode($arr_val),
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $db->where('user_id', $business_id);
                $db->where('name', 'showWallet');
                $updated = $db->update('xun_user_setting', $updateArray);
            }

            // $updateArray = array(
            //     "value" => json_encode($arr_val),
            //     "updated_at" => date("Y-m-d H:i:s")
            // );

            // $db->where('user_id', $business_id);
            // $db->where('name', 'showWallet');
            // $updated = $db->update('xun_user_setting', $updateArray);

            if (!$updated) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }
        } else {
            $showWalletArr = array();
            array_push($showWalletArr, $wallet_type);

            $insertArray = array(
                "user_id" => $business_id,
                "name" => "showWallet",
                "value" => json_encode($showWalletArr),
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $inserted = $db->insert('xun_user_setting', $insertArray);

            if (!$inserted) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00354') /*Wallet Status Successfully Updated.*/);
    }

    public function set_switch_currency($params)
    {
        $db = $this->db;

        $business_id = $params['business_id'];
        $switch_currency_status = $params['switch_currency_status'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($switch_currency_status == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00647') /*switch currency status cannot be empty*/);
        }

        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');
        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }
        $db->where('user_id', $business_id);
        $db->where('name', 'allowSwitchCurrency');
        $user_setting = $db->getOne('xun_user_setting');
        $switch_status = $user_setting['value'];

        if ($switch_status == null) {
            $insertArray = array(
                "user_id" => $business_id,
                "name" => "allowSwitchCurrency",
                "value" => $switch_currency_status,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $inserted = $db->insert('xun_user_setting', $insertArray);
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00354') /*Wallet Status Successfully Updated.*/);
        } else if ($switch_status != $switch_currency_status) {
            $update = array(
                "value" => $switch_currency_status,
                "updated_at" => date("Y-m-d H:i:s")
            );

            $db->where('user_id', $business_id);
            $db->where('name', 'allowSwitchCurrency');
            $updated = $db->update('xun_user_setting', $update);

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00379') /*Switch Currency Status Successfully Updated.*/);
        } else {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/);
        }
    }

    public function get_wallet_status($params)
    {
        $db = $this->db;

        $business_id = $params['business_id'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $db->where('user_id', $business_id);
        $db->where('name', 'showWallet');
        $user_setting = $db->getOne('xun_user_setting');

        if ($user_setting) {
            $show_coin_arr = json_decode($user_setting['value']);
        } else {
            $show_coin_arr = array();
        }

        $db->where('a.is_payment_gateway', 1);
        $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'INNER');
        $db->orderBy("a.sequence", "ASC");
        $xun_coins = $db->get('xun_coins a', null, 'b.name, b.currency_id, b.image, b.symbol, b.display_symbol');

        foreach ($xun_coins as $key => $value) {
            $wallet_type = $value['currency_id'];
            if (in_array($wallet_type, $show_coin_arr)) {
                $status = 1;
            } else {
                $status = 0;
            }

            $xun_coins[$key]['status'] = $status;
        }
        $db->where('user_id', $business_id);
        $db->where('name', 'allowSwitchCurrency');
        $allowSwitchCurrency = $db->getOne('xun_user_setting');

        $data['wallet_list'] = $xun_coins;
        $data['allowSwitchCurrency'] = $allowSwitchCurrency['value'];
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00355') /*Get Wallet Status Successful.*/, 'data' => $data);
    }


    public function get_nuxpay_user_internal_address($business_id)
    {
        $db = $this->db;

        $db->where('user_id', $business_id);
        $db->where('address_type', 'nuxpay_wallet');
        $db->where('active', 1);
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        if (!$crypto_user_address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00144') /*Address not found.*/);
        }

        $internal_address = $crypto_user_address['address'];

        $data['internal_address'] = $internal_address;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00336')/*Fund out history.*/, 'data' => $data);
    }

    public function get_nuxpay_user_with_internal_address($param)
    {
        $db = $this->db;
        $internal_address = $params['internal_address'];

        $db->where('address', $internal_address);
        $db->where('address_type', 'nuxpay_wallet');
        $db->where('active', 1);
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        if (!$crypto_user_address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00144') /*Address not found.*/);
        }

        $user_id = $crypto_user_address['user_id'];

        $db->where('id', $user_id);
        $crypto_user_detail = $db->getOne('xun_user');

        $data['user_id'] = $user_id;
        $data['user_name'] = $crypto_user_detail['user_name'];
        $data['email'] = $crypto_user_detail['email'];
        $data['nickname'] = $crypto_user_detail['nickname'];

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00336')/*Fund out history.*/, 'data' => $data);
    }

    public function update_invoice_transaction($params)
    {
        $db = $this->db;

        $transaction_hash = $params['transaction_hash'];
        $invoice_detail_id = $params['invoice_detail_id'];

        if ($transaction_hash == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00373') /*Transaction Hash cannot be empty*/, 'developer_msg' => 'Transaction Hash cannot be empty');
        }

        if ($invoice_detail_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00594') /*Invoice Details ID cannot be empty*/, 'developer_msg' => 'Invoice Details ID cannot be empty');
        }

        $db->where('id', $invoice_detail_id);
        $invoice_detail = $db->getOne('xun_payment_gateway_invoice_detail');

        if (!$invoice_detail) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00529') /*Invoice not found.*/);
        }

        $amount = $invoice_detail['payment_amount'];
        $status = $invoice_detail['status'];

        $db->where('transaction_hash', $transaction_hash);
        $invoice_transaction = $db->getOne('xun_payment_gateway_invoice_transaction');

        if (!$invoice_transaction) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00593') /*Transaction not found.*/);
        }

        $updateTransaction = array(
            "invoice_detail_id" => $invoice_detail_id
        );

        $db->where('transaction_hash', $transaction_hash);
        $updated = $db->update('xun_payment_gateway_invoice_transaction', $updateTransaction);

        if (!$updated) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        $db->where('invoice_detail_id', $invoice_detail_id);
        $db->where('transaction_type', 'withdrawal', '!=');
        $invoice_transaction_list = $db->get('xun_payment_gateway_invoice_transaction', null, 'amount, credit');

        if ($invoice_transaction_list) {
            foreach ($invoice_transaction_list as $key => $value) {
                $tx_amount = $value['amount'];
                $total_amount = bcadd($total_amount, $tx_amount, 8);
            }

            if ($amount <= $total_amount && $status != 'success') {
                $updateStatus = array(
                    "status" => 'success',
                );

                $db->where('id', $invoice_detail_id);
                $updated = $db->update('xun_payment_gateway_invoice_detail', $updateStatus);
            } else if ($status == 'pending') {
                $updateStatus = array(
                    "status" => "short_paid",
                );
                $db->where('id', $invoice_detail_id);
                $updated = $db->update('xun_payment_gateway_invoice_detail', $updateStatus);
            }
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00358') /*Update Invoice Transaction Successful.*/);
    }
    public function set_nuxpay_wallet_status($params)
    {
        global $xunCrypto;
        $db = $this->db;

        $business_id = $params['business_id'];
        $user_id = $business_id;
        $wallet_type = $params['wallet_type'];
        $status = $params['status'] ? 1 : 0;

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "Wallet Type cannot be empty");
        }

        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $db->where('user_id', $business_id);
        $db->where('name', 'showNuxpayWallet');
        $user_setting = $db->getOne('xun_user_setting');

        if ($user_setting) {
            $arr_val = json_decode($user_setting['value']);

            if ($status == 1 && !in_array($wallet_type, $arr_val)) {
                array_push($arr_val, $wallet_type);
            } elseif ($status == 0 && in_array($wallet_type, $arr_val)) {
                foreach ($arr_val as $key => $value) {
                    if ($value == $wallet_type) {
                        unset($arr_val[$key]);
                    }
                }
            }

            $arr_val = array_values($arr_val);
            if (count($arr_val) == "0") {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            } else {
                $updateArray = array(
                    "value" => json_encode($arr_val),
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $db->where('user_id', $business_id);
                $db->where('name', 'showNuxpayWallet');
                $updated = $db->update('xun_user_setting', $updateArray);
            }
            if (!$updated) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }
        } else {
            $showWalletArr = array();
            array_push($showWalletArr, $wallet_type);

            $insertArray = array(
                "user_id" => $business_id,
                "name" => "showNuxpayWallet",
                "value" => json_encode($showWalletArr),
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $inserted = $db->insert('xun_user_setting', $insertArray);

            if (!$inserted) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }
        }


        $db->where('user_id', $user_id);
        $db->where('name', 'showNuxpayWallet');
        $user_setting = $db->getOne('xun_user_setting');

        $db->where('user_id', $user_id);
        $db->where('address_type', 'nuxpay_wallet');
        $db->where('active', 1);
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        $internal_address = $crypto_user_address['address'];

        if ($user_setting) {
            $show_coin_arr = json_decode($user_setting['value']);
        } else {
            $show_coin_arr = array();
        }
        if ($show_coin_arr) {
            $db->where("a.currency_id", $show_coin_arr, "IN");
            //     $db->orWhere("a.is_default", 1);
            // }else{
            //     $db->where("a.is_default", 1);

        }

        $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'INNER');
        $db->orderBy("a.sequence", "ASC");
        $xun_coins = $db->get('xun_coins a', null, 'b.name, b.currency_id, b.image, b.symbol');

        foreach ($xun_coins as $coin_key => $coin_value) {
            $wallet_type = $coin_value['currency_id'];
            $wallet_external_address = $xunCrypto->get_external_address($internal_address, $wallet_type);

            $fund_in_address_list[$wallet_type] = $wallet_external_address;
        }

        $returnData = array(
            "fund_in_address_list" => $fund_in_address_list
        );
        // $returnData = $xun_coins;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00354') /*Wallet Status Successfully Updated.*/, "data" => $returnData);
    }

    public function get_nuxpay_wallet_status($params)
    {
        $db = $this->db;

        $business_id = $params['business_id'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/, "developer_msg" => "Business not found.");
        }

        $db->where('user_id', $business_id);
        $db->where('name', 'showNuxpayWallet');
        $user_setting = $db->getOne('xun_user_setting');

        if ($user_setting) {
            $show_coin_arr = json_decode($user_setting['value']);
        } else {
            $show_coin_arr = array();
        }

        $db->where('a.is_payment_gateway', 1);
        $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'INNER');
        $db->orderBy("a.sequence", "ASC");
        $xun_coins = $db->get('xun_coins a', null, 'b.name, b.currency_id, b.image, b.symbol, b.display_symbol');

        foreach ($xun_coins as $key => $value) {
            $wallet_type = $value['currency_id'];
            if (in_array($wallet_type, $show_coin_arr)) {
                $status = 1;
            } else {
                $status = 0;
            }

            $xun_coins[$key]['status'] = $status;
        }

        $data['wallet_list'] = $xun_coins;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00355') /*Get Wallet Status Successful.*/, 'data' => $data);
    }
    public function get_invoice_transaction($params)
    {

        $db = $this->db;

        $transaction_hash = $params['transaction_hash'];

        if ($transaction_hash == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00373') /*Transaction Hash cannot be empty*/, 'developer_msg' => 'Transaction Hash cannot be empty');
        } else {

            $db->where("transaction_hash", $transaction_hash);
            $result = $db->get("xun_payment_gateway_invoice_transaction", null, "created_at, recipient_address, sender_address, amount, invoice_detail_id, transaction_hash");

            if ($result) {
                return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('M00334') /*Search Successful*/, 'data' => $result);
            } else {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('M00136') /*No Results Found*/, 'developer_msg' => 'No Results Found');
            }
        }
    }

    public function create_send_fund($params, $type, $source, $ip)
    {
        global $xunMinerFee, $xunCrypto, $xunCurrency, $config, $xun_numbers, $xunSms, $xunEmail, $xunUser, $xunPay, $xunPayment;
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $post = $this->post;

        $xun_business_service = new XunBusinessService($db);
        $xunWallet = new XunWallet($db);

        $date = date("Y-m-d H:i:s");

        $sender_name = trim($params['sender_name']);
        $sender_mobile_number = trim($params['sender_mobile_number']);
        $sender_email_address = trim($params['sender_email_address']);
        $sender_type = trim($params['sender_type']); //mobile /email
        $receiver_mobile_number = trim($params['receiver_mobile_number']);
        $receiver_email_address = trim($params['receiver_email_address']);
        $receiver_name = trim($params['receiver_name']);
        $receiver_type = trim($params['receiver_type']); //mobile /email
        $amount = trim($params['amount']);
        $wallet_type = $params['wallet_type'];
        $payment_description = $params['description'];
        $escrow = $params['escrow'];
        $promo_code = $params['promo_code'];
        $pg_transaction_token = trim($params['transaction_token']); // PG table transaction token
        //wentin//
        $transaction_target = $pg_transaction_token;
        $transaction_target != "";
        if ($transaction_target) {
            $target = "external";
        } else {
            $target = "internal";
        }


        $miner_fee_delegate_wallet_address = $setting->systemSetting['minerFeeDelegateWalletAddress'];
        $prepaidWalletServerURL =  $config["giftCodeUrl"];

        if ($sender_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00599') /*Sender Type cannot be empty.*/, "developer_msg" => "Sender Type cannot be empty.");
        }

        if ($sender_mobile_number  == '' && $sender_email_address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00605') /* Please fill up either Sender Mobile Number or Sender Email Address*/, "developer_msg" => " Please fill up either Sender Mobile Number or Sender Email Address.");
        }

        if ($sender_type == 'mobile' && $sender_mobile_number == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00601') /*Sender Mobile Number cannot be empty.*/, "developer_msg" => "Sender Mobile Number cannot be empty.");
        }

        if ($sender_type == 'email' && $sender_email_address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00600') /*Sender Email Address cannot be empty.*/, "developer_msg" => "Sender Email Address cannot be empty.");
        }

        if ($receiver_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00602') /*Receiver Type cannot be empty.*/, "developer_msg" => "Receiver Type cannot be empty.");
        }

        if ($receiver_type == 'mobile'  && $receiver_mobile_number == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00604') /*Receiver Mobile Number cannot be empty.*/, "developer_msg" => "Receiver Mobile Number cannot be empty.");
        }

        if ($receiver_type == 'email' && $receiver_email_address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00603') /*Receiver Email Address cannot be empty.*/, "developer_msg" => "Receiver Email Address cannot be empty.");
        }

        if ($receiver_mobile_number == '' && $receiver_email_address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00606') /* Please fill up either Receiver Mobile Number or Receiver Email Address*/, "developer_msg" => " Please fill up either Receiver Mobile Number or Receiver Email Address.");
        }

        if ($amount == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00417') /*Amount cannot be empty*/, "developer_msg" => "Amount cannot be empty");
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "Wallet Type cannot be empty");
        }

        if ($pg_transaction_token) {
            $db->where('transaction_token', $pg_transaction_token);
            $pg_payment_tx_data = $db->getOne('xun_payment_gateway_payment_transaction');

            if (!$pg_payment_tx_data) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction not found.", "developer_msg" => "Transaction not found.");
            }
        }

        if ($sender_type == "email") {

            if ($sender_email_address) {
                if (!filter_var($sender_email_address, FILTER_VALIDATE_EMAIL)) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00523') /*Invalid payee email address.*/);
                }
            }

            if (!$sender_email_address) {
                // TODO Language plugin: Payee email address cannot be empty!
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Payee email address cannot be empty!");
            }

            $sender_mobile_number = "";
        } else {

            if ($sender_mobile_number == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00517') /*Payee mobile phone cannot be empty*/);
            } else {
                if (preg_match('/^[0-9.+]/', $sender_mobile_number) == 0) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Only numbers and symbols are allowed for mobile number");
                }
            }

            $senderMobileNumberInfo = $general->mobileNumberInfo($sender_mobile_number, null);
            // list($countryCode, $mobileNumber) = explode(" ", $payeeMobileNumberInfo['mobileNumberFormatted']);
            if ($senderMobileNumberInfo["isValid"] == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00525') /*Invalid payee mobile phone.*/);
            } else {
                $sender_mobile_number = "+" . $senderMobileNumberInfo['mobileNumberWithoutFormat'];
            }

            $sender_email_address = "";
        }


        if ($receiver_type == "email") {

            if ($receiver_email_address == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00519') /*Payer email address cannot be empty*/);
            }

            if (!filter_var($receiver_email_address, FILTER_VALIDATE_EMAIL)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00524') /*Invalid payer email address.*/);
            }

            $receiver_mobile_number = "";
        } else {

            if ($receiver_mobile_number == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00520') /*Payer mobile phone cannot be empty*/);
            } else {
                if (preg_match('/^[0-9.+]/', $receiver_mobile_number) == 0) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Only numbers and symbols are allowed for mobile number");
                }
            }

            $receiverMobileNumberInfo = $general->mobileNumberInfo($receiver_mobile_number, null);
            if ($receiverMobileNumberInfo["isValid"] == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00526') /*Invalid payer mobile phone.*/);
            } else {
                $receiver_mobile_number = "+" . $receiverMobileNumberInfo['mobileNumberWithoutFormat'];
            }

            $receiver_email_address = "";
        }


        if ($escrow == '') {
            $escrow = 0;
        }

        if ($sender_type == 'mobile' && $sender_mobile_number) {
            if ($sender_mobile_number[0] != '+') {
                $sender_mobile_number = "+" . $sender_mobile_number;
            }
            $db->where('username', $sender_mobile_number);
        }

        if ($receiver_mobile_number) {
            if ($receiver_mobile_number[0] != '+') {
                $receiver_mobile_number = "+" . $receiver_mobile_number;
            }
        }

        if ($sender_type == 'email' && $sender_email_address) {
            $db->where('email', $sender_email_address);
        }


        $db->where('register_site', $source);
        $db->where('disabled', 0);
        $db->where('type', 'business');
        $xun_user = $db->getOne('xun_user');

        $user_exist = $xun_user ? 1 : 0;
        $verify_data['user_exist'] = $user_exist;

        if ($type == 'verification') {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00362') /*Send Fund Verification Success*/, 'data' => $verify_data);
        }

        $new_user = 0;
        if (!$xun_user) {
            // return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00251') /* Invalid User*/, 'developer_msg' => 'Invalid User.');

            if ($promo_code != "") {

                $db->where('referral_code', $promo_code);
                $db->where('deleted', 0);
                $db->where('type', 'reseller');
                $reseller_info = $db->getOne('reseller');

                if (!$reseller_info) {
                    return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00542') /*Reseller does not exist.*/);
                }
            }

            $verifyData['req_type'] = $sender_type;
            $verifyData['email'] = $sender_email_address;
            $verifyData['mobile'] = $sender_mobile_number;
            $verifyData['source'] = $source;
            $verifyData['ip'] = $ip;
            $verifyData['request_type'] = 'send_fund';
            $verifyData['company_name'] = $source; //$setting->systemSetting['payCompanyName'];
            $resultGetOtp = $xunUser->register_verifycode_get($verifyData);

            if ($resultGetOtp['code'] == 1 || $resultGetOtp['errorCode'] == -101) {

                $db->where("is_valid", 1);
                $db->where("is_verified", 0);
                $db->where("expires_at", date("Y-m-d H:i:s"), ">");
                if ($sender_type == "email") {
                    $db->where("email", $sender_email_address);
                } else {
                    $db->where("mobile", $sender_mobile_number);
                }
                $verification_code = $db->getValue("xun_user_verification", "verification_code");

                $registerData['req_type'] = $sender_type;
                $registerData['email'] = $sender_email_address;
                $registerData['mobile'] = $sender_mobile_number;
                $registerData['pay_password'] = $verification_code;
                $registerData['pay_retype_password'] = $verification_code;
                $registerData['verify_code'] = $verification_code;
                $registerData['nickname'] = $sender_name;
                $registerData['type'] = $source;
                $registerData['rid'] = $rid;
                $registerData['source'] = $source;
                $registerData['reseller_code'] = $promo_code;
                $registerData['signup_type'] = 'sendFund';
                $newBusiness = 1;

                $resultRegister = $xunPay->pay_register($registerData, $ip, $user_agent, $rid);
                if ($resultRegister['code'] == 1) {
                    $business_id = $resultRegister['data']['business_id'];
                } else {
                    return $resultRegister;
                }
            }
            $new_user = 1;
        } else {
            $business_id = $xun_user['id'];
        }


        // $sender_name = $xun_user['nickname'];

        $balance = $this->get_user_balance($business_id, $wallet_type);

        $internal_address_data = $this->get_nuxpay_user_internal_address($business_id, $wallet_type);

        if ($internal_address_data['code'] != 1) {
            return $internal_address_data;
        }
        $internal_address = $internal_address_data['data']['internal_address'];

        if ($balance < $amount){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('M01916') /*Insufficient Funds"*/);
        }

        if ($receiver_type == 'mobile' && $receiver_mobile_number) {
            $db->where('username', $receiver_mobile_number);
        }

        if ($receiver_type == 'email' && $receiver_email_address) {
            $db->where('email', $receiver_email_address);
        }

        $db->where('register_site', $source);
        $db->where('disabled', 0);
        $db->where('type', 'business');
        $recipient_user_data = $db->getOne('xun_user');

        if ($recipient_user_data) {
            $recipient_business_id = $recipient_user_data['id'];
            $db->where('user_id', $recipient_business_id);
            $db->where('address_type', 'nuxpay_wallet');
            $db->where('active', 1);
            $crypto_user_address = $db->getOne('xun_crypto_user_address', 'id, user_id, address');

            if (!$crypto_user_address) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00144') /*Address Not Found.*/);
            }


            if ($escrow) {
                $destination_address = $setting->systemSetting['escrowInternalAddress'];
            } else {
                $destination_address = $crypto_user_address['address'];
            }

            $fund_type = 'fund_in';
        } else {
            $destination_address = $setting->systemSetting['redeemCodeAgentAddress'];

            $fund_type = 'redeem_code';
        }

        if ($fund_type == 'redeem_code') {
            while (1) {
                $redeem_code = $general->generateAlpaNumeric(8);

                $db->where('redeem_code', $redeem_code);
                $redeem_code_transaction = $db->getOne('xun_payment_gateway_send_fund', 'id');

                if (!$redeem_code_transaction) {
                    break;
                }
            }
        }

        // Validate internal address
        $ret_val1 = $xunCrypto->crypto_validate_address($destination_address, $wallet_type, 'internal');
        if ($ret_val1['code'] == 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $ret_val1['statusMsg']);
        } else {
            $destination_address = $ret_val1['data']['address'];
            $transaction_type = $ret_val1['data']['addressType'];
        }

        $wallet_info = $xunCrypto->get_wallet_info($internal_address, $wallet_type);

        $lc_wallet_type = strtolower($wallet_type);
        $walletBalance = $wallet_info[$lc_wallet_type]['balance'];
        $unitConversion = $wallet_info[$lc_wallet_type]['unitConversion'];
        $minerFeeWalletType = strtolower($wallet_info[$lc_wallet_type]['feeType']);
        $symbol = strtolower($wallet_info[$lc_wallet_type]['unit']);


        $satoshi_amount = $xunCrypto->get_satoshi_amount($wallet_type, $amount);

        if ($fund_type == 'fund_in' && $transaction_type == 'external') {
            $address_type = 'external_transfer';
        }
        if ($fund_type == 'fund_in' && $transaction_type == 'internal') {
            $address_type = 'nuxpay_wallet';
        }
        if ($fund_type == 'redeem_code') {
            $address_type = 'redeem_code';
        }

        $remaining_balance = bcsub($balance, $amount, 8);

        if (!$pg_transaction_token) {
            $payment_transaction_params = array(
                "business_id" => $business_id,
                "crypto_amount" => $amount,
                "wallet_type" => $wallet_type,
                "transaction_type" => "send_fund",
            );
            $payment_tx_id = $xunPayment->insert_payment_transaction($payment_transaction_params);

            $insert_payment_method_arr = array(
                "address" => $destination_address,
                "wallet_type" => $wallet_type,
                "payment_tx_id" => $payment_tx_id,
                "type" => 'zero_fee',
                "created_at" => date("Y-m-d H:i:s")
            );

            $payment_method_id = $db->insert('xun_payment_method', $insert_payment_method_arr);

            if (!$payment_method_id) {
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/,
                    "error_message" => $db->getLastError()
                );
            }
        } else {
            $db->where('transaction_token', $pg_transaction_token);
            $payment_tx_data = $db->getOne('xun_payment_transaction');

            if (!$payment_tx_data) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction not found.", "developer_msg" => "xun_payment_transaction record not found.");
            }

            $payment_tx_id = $payment_tx_data['id'];
            $fiat_currency_id = $payment_tx_data['fiat_currency_id'];

            $db->where('payment_tx_id', $payment_tx_id);
            $db->where('type', 'zero_fee');
            $db->where('wallet_type', $wallet_type);
            $payment_method_id =   $db->getValue('xun_payment_method', 'id');
        }

        $insertData = array(
            "business_id" => $business_id,
            "sender_name" => $sender_name,
            "sender_mobile_number" => $sender_mobile_number  ? $sender_mobile_number : '',
            "sender_email_address" => $sender_email_address ? $sender_email_address : '',
            "recipient_name" => $receiver_name,
            "recipient_mobile_number" => $receiver_mobile_number,
            "recipient_email_address" => $receiver_email_address,
            "amount" => $amount,
            "wallet_type" => $wallet_type,
            "status" => $balance < $amount ? "low_balance" : "pending",
            "tx_type" => $fund_type,
            "redeem_code" => $fund_type == 'redeem_code' ? $redeem_code : '',
            "escrow"        => $escrow,
            "pg_transaction_token" => $pg_transaction_token ? $pg_transaction_token : '',
            "description" => $payment_description ? $payment_description : '',
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $send_fund_id = $db->insert('xun_payment_gateway_send_fund', $insertData);

        $update_payment_transaction_data = array(
            "reference_table" => "xun_payment_gateway_send_fund",
            "reference_id" => $send_fund_id
        );

        $db->where('id', $payment_tx_id);
        $db->update('xun_payment_transaction', $update_payment_transaction_data);

        // if escrow, insert into xun_escrow
        // insert record into xun_payment_gateway_fund_in so receiver will know
        if ($escrow) {
            $insertEscrowData = array(
                'reference_id'  => $send_fund_id,
                'tx_type'       => 'send',
                'amount'        =>  $amount,
                'status'        => "pending",
                'updated_at'    => date("Y-m-d H:i:s"),
                'created_at'    => date("Y-m-d H:i:s")
            );
            $escrow_id = $db->insert('xun_escrow', $insertEscrowData);

            // 
            $insertFundIn = array(
                "business_id" => $recipient_business_id, // receiver's business id
                // "transaction_id" => $transaction_hash, at callback only update
                // "reference_id" => $bc_reference_id, at callback only update
                "sender_address" => $internal_address,
                "receiver_address" => $crypto_user_address['address'],
                "status" => "hold", // new
                "amount" => $amount,
                "amount_receive" => $amount,
                "transaction_fee" => '0',
                "miner_fee" => '0',
                "wallet_type" => strtolower($wallet_type),
                // "exchange_rate" => $exchange_rate, not sure yet
                "type" => "hold_escrow", // at update it becomes release_escrow
                "transaction_type" => "blockchain",
                "escrow_id" => $escrow_id, // new
                "transaction_target" => $target, // wentin //
                // "transaction_id" => $pg_transaction_token ?: $target, // according to above
                "created_at" => date("Y-m-d H:i:s")
            );

            $fund_in_id = $db->insert('xun_payment_gateway_fund_in', $insertFundIn);
        }


        if ($balance < $amount) {
            $data = $params;
            $external_address = $xunCrypto->get_external_address($internal_address, $wallet_type);
            $data['external_address'] = $external_address;
            $data['error_code'] = -103;
            $data['new_user'] = $new_user;
            unset($data['command']);

            return array('code' => 1, 'message' => "FAILED", 'message_d' => $this->get_translation_message('M01916') /*Insufficient Funds"*/, 'data' => $data);
        } else {
            $insertTx = array(
                "business_id" => $business_id,
                "sender_address" => $internal_address,
                "recipient_address" => $destination_address,
                "amount" => $amount,
                "amount_satoshi" => $satoshi_amount,
                "wallet_type" => $wallet_type,
                "credit" => 0,
                "debit" => $amount,
                "balance" => $remaining_balance,
                "reference_id" => $send_fund_id,
                "transaction_type" => ($escrow &&  $fund_type == 'fund_in') ? "send_escrow" : "send_fund",
                "gw_type" => "BC",
                "created_at" => date("Y-m-d H:i:s"),
            );

            $invoice_id = $db->insert('xun_payment_gateway_invoice_transaction', $insertTx);

            if (!$invoice_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }

            $tx_obj = new stdClass();
            $tx_obj->userID = $business_id;
            $tx_obj->address = $internal_address;

            $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);
            $xunWallet = new XunWallet($db);
            $transactionObj->status = 'pending';
            $transactionObj->transactionHash = '';
            $transactionObj->transactionToken = $transaction_token;
            $transactionObj->senderAddress = $internal_address;
            $transactionObj->recipientAddress = $destination_address;
            $transactionObj->userID = $business_id;
            $transactionObj->senderUserID = $business_id;
            if ($fund_type == 'fund_in') {
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
            if ($fund_type == 'fund_in') {
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
            while (true) {
                $payment_id = "P" . time();
                $db->where('payment_id', $payment_id);
                $check_payment_details = $db->getOne('xun_payment_details');

                if (!$check_payment_details) {
                    break;
                }
            }
            $cryptocurrency_rate_arr = $xunCurrency->get_cryptocurrency_rate(array($wallet_type));

            $crypto_exchange_rate = $cryptocurrency_rate_arr[$wallet_type];
            if ($fiat_currency_id) {

                $currency_rate_data = $xunCurrency->get_currency_rate(array($fiat_currency_id));
                $fiat_currency_rate = $currency_rate_data[$fiat_currency_id];
                $txExchangeRate = bcmul($crypto_exchange_rate, $fiat_currency_rate, 8);
            } else {
                $txExchangeRate = $crypto_exchange_rate;
            }

            $paymentObj->txExchangeRate = $txExchangeRate;
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
            // $transactionObj->referenceID = $reference_id;
            $paymentObj->createdAt = $date;
            $paymentObj->updatedAt = $date;
            $paymentObj->transactionToken = $transaction_token;
            // $transactionObj->fee = $padded_fee;
            // $transactionObj->feeWalletType = $miner_fee_wallet_type;
            // $transactionObj->exchangeRate = $exchangeRate;
            // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;

            $paymentObj->txExchangeRate = $txExchangeRate;
            $paymentObj->fiatCurrencyID = $fiat_currency_id ? $fiat_currency_id : 'usd';

            $payment_details_id = $xunPayment->insert_payment_details($paymentObj);

            if (!$payment_details_id) {
                $log->write("\n " . $date . " function:create_send_fund - Insert Payment Details. Error:" . $db->getLastError());
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
            // $transactionObj->fee = $final_miner_fee;
            // $transactionObj->feeWalletType = $miner_fee_wallet_type;
            // $transactionObj->exchangeRate = $exchangeRate;
            // $transactionObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
            $txHistoryObj->type = 'out';
            $txHistoryObj->gatewayType = "BC";

            $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);

            if (!$transaction_history_result) {
                $log->write("\n " . $date . " function:create_send_fund - Insert Payment Transaction History. Error:" . $db->getLastError());
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

            //END OF NEW ACCOUNTING

            if ($pg_transaction_token) {
                $update_crypto_tx_token = array(
                    "crypto_transaction_token" => $transaction_token
                );

                $db->where('transaction_token', $pg_transaction_token);
                $db->update('xun_payment_gateway_payment_transaction', $update_crypto_tx_token);
            }

            $satoshi_amount = $xunCrypto->get_satoshi_amount($wallet_type, $amount);

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

            if ($curlResponse['code'] == 0) {
                $update_status = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $db->where('id', $send_fund_id);
                $db->update('xun_payment_gateway_send_fund', $update_status);

                $db->where('id', $transaction_id);
                $db->update('xun_wallet_transaction', $update_status);

                $db->where('id', $transaction_history_id);
                $db->update($transaction_history_table, $update_status);

                $db->where('id', $payment_details_id);
                $db->update('xun_payment_details', $update_status);

                $update_balance  = array(
                    "deleted" => 1,
                );

                $db->where('id', $invoice_id);
                $db->update('xun_payment_gateway_invoice_transaction', $update_balance);

                return array("code" => 0, "message" => "FAILED", "message_d" => $curlResponse['message_d'], "developer_msg" => $curlResponse);
            }

            $data['send_fund_id'] = $send_fund_id;
            if ($redeem_code) {
                if ($receiver_mobile_number) {

                    $db->where('source', $source);
                    $site = $db->getOne('site');
                    $Prefix = $site['otp_prefix'];

                    if ($Prefix != ""){
                        $source = $Prefix;
                    }
                    //YF1
                    $domain = $site['domain'];
                    $return_message = $this->get_translation_message('B00366'); /*"%%companyName%%: %%senderName%% sent funds to you via %%companyName%%. Your redemption pin is %%redeemCode%%. Redeem now from %%link%%.";*/
                    $return_message2 = str_replace("%%companyName%%", $source, $return_message);
                    $return_message3 = str_replace("%%senderName%%", $sender_name, $return_message2);
                    $return_message4 = str_replace("%%redeemCode%%", $redeem_code, $return_message3);
                    $newParams["message"] = str_replace("%%link%%", $domain, $return_message4);
                    $newParams["recipients"] = $receiver_mobile_number;
                    $newParams["ip"] = $ip;
                    $newParams["companyName"] = $source;
                    $xunSms->send_sms($newParams);
                }

                if ($receiver_email_address) {
                    $send_email_params = array(
                        "sender_name" => $sender_name,
                        "receiver_name" => $receiver_name,
                        "amount" => $amount,
                        "symbol" => $symbol,
                        "description" => $payment_description,
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
                $data['redeem_code'] = $redeem_code;
            }

            $tag = "Send Fund";
            $message = "Sender: " . $sender_name . "\n";
            $message .= "Receiver: " . $receiver_name . "\n";
            $message .= "Amount:" . $amount . "\n";
            $message .= "Wallet Type:" . $wallet_type . "\n";
            $message .= "Time: " . date("Y-m-d H:i:s") . "\n";

            $thenux_params["tag"]         = $tag;
            $thenux_params["message"]     = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

            $data['error_code'] = 1;
            $data['new_user'] = $new_user;

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00363') /*Your Send Fund is currently processing.*/, 'data' => $data, 'dberror' => $dbError);
        }
    }

    public function get_user_balance($user_id, $wallet_type)
    {
        global $account, $config;
        $db = $this->db;


        if ($config['isNewAccounting'] == 1) {
            $user_balance = $account->getBalance($user_id, $wallet_type);
        } else {
            $db->where('deleted', 0);
            $db->where('business_id', $user_id);
            $db->where('wallet_type', $wallet_type);
            $db->where('transaction_type', array('fund_in_to_destination', 'withhold', 'release_withhold'), 'NOT IN');
            $sum_result = $db->getOne('xun_payment_gateway_invoice_transaction', 'SUM(credit) as sumCredit, SUM(debit) as sumDebit');

            $user_balance = '0.0000000';
            if ($sum_result) {
                $sum_credit = $sum_result['sumCredit'];
                $sum_debit = $sum_result['sumDebit'];

                $user_balance = bcsub($sum_credit, $sum_debit, 8);
            }
        }


        return $user_balance;
    }

    public function get_redeem_code_details($params)
    {
        $db = $this->db;
        $setting = $this->setting;

        // $business_id = $params['redeem_code'];
        $redeem_code = $params['redeem_code'];

        // if($business_id == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "Business ID cannot be empty");
        // }

        if ($redeem_code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00597') /*Redeem Code cannot be empty*/, "developer_msg" => "Redeem Code cannot be empty");
        }

        $db->where('redeem_code', $redeem_code);
        $db->where('status', array('failed', 'pending'), 'NOT IN');
        $send_fund_data = $db->getOne('xun_payment_gateway_send_fund', 'business_id, redeem_code, status, amount, wallet_type, recipient_name, recipient_email_address, recipient_mobile_number, description');

        if (!$send_fund_data) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00598') /*Invalid Redeem Code.*/, 'developer_msg' => 'Invalid Redeem Code.');
        }

        if ($send_fund_data['status'] == 'success') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00607') /*This redeem code is fully redeemed.*/, 'developer_msg' => 'This redeem code is fully redeemed.');
        }

        $wallet_type = $send_fund_data['wallet_type'];
        $business_id = $send_fund_data['business_id'];

        $db->where('id', $business_id);
        $sender_data = $db->getOne('xun_user', null, 'id, username, email, nickname');

        $sender_name = $sender_data['nickname'];
        $sender_mobile_number = $sender_data['username'];
        $sender_email_address = $sender_data['email'];

        $send_fund_data['sender_name'] = $sender_name;
        $send_fund_data['sender_mobile_number'] = $sender_mobile_number;
        $send_fund_data['sender_email_address'] = $sender_email_address;

        $db->where('currency_id', $wallet_type);
        $marketplace_currencies = $db->getOne('xun_marketplace_currencies', 'id, symbol, display_symbol');

        $symbol = $marketplace_currencies['display_symbol'];

        $send_fund_data['symbol'] = strtoupper($symbol);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00365') /* Get Redeem Details Successful. */, 'data' => $send_fund_data);
    }

    public function nuxpay_escrow_release($params, $source, $ip = null)
    {
        global $xunCrypto, $config, $xunSms, $config, $xunEmail, $xunPayment;
        $db = $this->db;
        $post = $this->post;
        $setting = $this->setting;
        $general = $this->general;

        $prepaidWalletServerURL =  $config["giftCodeUrl"];
        $business_id = $params['business_id'];
        $transaction_hash = $params['transaction_hash'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "Business ID cannot be empty");
        }

        if ($transaction_hash == '') {
            // TODO LANGUAGE PLUGIN: Transaction hash cannot be empty
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction hash cannot be empty", "developer_msg" => "Transaction hash cannot be empty");
        }

        $db->where('register_site', $source);
        $db->where('type', 'business');
        $db->where('disabled', 0);
        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/, 'source' => $source);
        }

        $db->where('transaction_hash', $transaction_hash);
        $db->where('status', 'Escrow');
        $db->where('transaction_type', 'send_fund');
        $withdrawal_data = $db->getOne('xun_payment_gateway_withdrawal');
        if (!$withdrawal_data) {
            // TODO LANGUAGE PLUGIN: Invalid Transaction Hash
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Transaction Hash" /*Invalid Transaction Hash*/, 'developer_msg' => 'Invalid Transaction Hash');
        }

        $db->where('receive_tx_hash', $transaction_hash);
        $db->where('tx_type', 'send');
        $escrow_data = $db->getOne('xun_escrow');
        if (!$escrow_data) {
            // TODO LANGUAGE PLUGIN: Invalid Transaction Hash
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Transaction Hash" /*Invalid Redeem Code.*/, 'developer_msg' => 'Invalid Transaction Hash (2)');
        }

        $db->where('id', $escrow_data['reference_id']);
        $send_fund_data = $db->getOne('xun_payment_gateway_send_fund');
        if (!$send_fund_data) {
            // TODO LANGUAGE PLUGIN: Invalid Transaction Hash
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Transaction Hash" /*Invalid Redeem Code.*/, 'developer_msg' => 'Invalid Transaction Hash (3)');
        }

        if ($send_fund_data['status'] == 'release' ||  $send_fund_data['status'] == 'success') {
            // TODO LANGUAGE PLUGIN: Escrow has been released
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Escrow has been released" /*Escrow has been released*/, 'developer_msg' => 'Escrow has been released');
        } else if ($send_fund_data['status'] != 'ready') {
            // TODO LANGUAGE PLUGIN: Escrow transaction not ready
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Escrow transaction not ready" /*Escrow transaction not ready*/, 'developer_msg' => 'Escrow transaction not ready');
        } else if ($send_fund_data['business_id'] != $xun_user['id']) {
            // TODO LANGUAGE PLUGIN: You do not have the permission to release escrow
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You do not have the permission to release escrow" /*You do not have the permission to release escrow*/, 'developer_msg' => 'You do not have the permission to release escrow');
        }

        $send_fund_id = $send_fund_data['id'];
        $amount = $send_fund_data['amount'];
        $wallet_type = $send_fund_data['wallet_type'];

        if ($send_fund_data['tx_type'] == 'redeem_code') {
            $db->where('id', $send_fund_data['redeemed_by']);
            $xun_user_receiver = $db->getOne('xun_user');

            $db->where('user_id', $xun_user_receiver['id']);
            $db->where('address_type', 'nuxpay_wallet');
            $db->where('active', 1);
            $crypto_user_address = $db->getOne('xun_crypto_user_address');

            if (!$crypto_user_address) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => 'Internal Address not found.');
            }

            $destination_address = $crypto_user_address['address'];
        } else {
            if ($send_fund_data['recipient_email_address']) {
                $db->where('email', $send_fund_data['recipient_email_address']);
            } else if ($send_fund_data['recipient_mobile_number']) {
                $db->where('username', $send_fund_data['recipient_mobile_number']);
            } else {
                return array("code" => 0, "message" => "SUCCESS", "message_d" => "Something went wrong. Please try again.", "developer_msg" => 'missing mobile & email');
            }
            $db->where('register_site', $source);
            $db->where('type', 'business');
            $db->where('disabled', 0);
            $xun_user_receiver = $db->getOne('xun_user');

            $db->where('user_id', $xun_user_receiver['id']);
            $db->where('address_type', 'nuxpay_wallet');
            $db->where('active', 1);
            $crypto_user_address = $db->getOne('xun_crypto_user_address');

            if (!$crypto_user_address) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => 'Internal Address not found.');
            }

            $destination_address = $crypto_user_address['address'];
        }

        $escrow_wallet_address = $setting->systemSetting['escrowInternalAddress'];

        $xun_business_service = new XunBusinessService($db);
        $tx_obj = new stdClass();
        $tx_obj->userID = 0;
        $tx_obj->address = $escrow_wallet_address;

        $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);

        $xunWallet = new XunWallet($db);

        $date = date('Y-m-d H:i:s');

        $transactionObj = new stdClass();
        $transactionObj->status = "pending";
        $transactionObj->transactionHash = "";
        $transactionObj->transactionToken = $transaction_token;
        $transactionObj->senderAddress = $escrow_wallet_address;
        $transactionObj->recipientAddress = $destination_address;
        $transactionObj->userID = $business_id;
        $transactionObj->senderUserID = 'escrow_wallet';
        $transactionObj->recipientUserID = $xun_user_receiver['id'];
        $transactionObj->walletType = $wallet_type;
        $transactionObj->amount = $amount;
        $transactionObj->addressType = "nuxpay_wallet";
        $transactionObj->transactionType = "receive";
        $transactionObj->escrow = 0;
        $transactionObj->referenceID = $send_fund_id;
        $transactionObj->message = 'release_escrow';
        $transactionObj->escrowContractAddress = '';
        $transactionObj->createdAt = $date;
        $transactionObj->updatedAt = $date;
        $transactionObj->expiresAt = '';

        $wallet_transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

        // $txHistoryObj->paymentDetailsID = $payment_details_id;
        $txHistoryObj->status = "pending";
        $txHistoryObj->transactionID = "";
        $txHistoryObj->transactionToken = $transaction_token;
        $txHistoryObj->senderAddress = $escrow_wallet_address;
        $txHistoryObj->recipientAddress = $destination_address;
        $txHistoryObj->senderUserID = 'escrow_wallet';
        $txHistoryObj->recipientUserID = $xun_user_receiver['id'];
        $txHistoryObj->walletType = $wallet_type;
        $txHistoryObj->amount = $amount;
        $txHistoryObj->transactionType = "nuxpay_wallet";
        $txHistoryObj->referenceID = $send_fund_id;
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
        $xunWallet->updateWalletTransaction($wallet_transaction_id, $updateWalletTx);

        $satoshi_amount = $xunCrypto->get_satoshi_amount($wallet_type, $amount);

        $updateRedeem = array(
            "status" => 'release',
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('id', $send_fund_id);
        $updated = $db->update('xun_payment_gateway_send_fund', $updateRedeem);

        // update xun_escrow
        $updateEscrow = array(
            'status' => 'release',
            "updated_at" => date("Y-m-d H:i:s")
        );
        $db->where('id', $escrow_data['id']);
        $db->update('xun_escrow', $updateEscrow);


        $curlParams = array(
            "command" => "fundOutCompanyWallet",
            "params" => array(
                "senderAddress" => $escrow_wallet_address,
                "receiverAddress" => $destination_address,
                "amount" => $amount,
                "satoshiAmount" => $satoshi_amount,
                "walletType" => $wallet_type,
                "id" => $wallet_transaction_id,
                "transactionToken" => $transaction_token,
                "addressType" => "redeem_code_wallet",
            ),
        );

        $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);

        if ($curlResponse["code"] == 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $curlResponse);
        }

        // send notification that fund has been released
        $receiver_mobile_number = $send_fund_data['recipient_mobile_number'];
        $receiver_email_address = $send_fund_data['recipient_email_address'];

        $db->where('id', $send_fund_data['business_id']);
        $sender_user_table = $db->getOne('xun_user');

        $sender_name = $sender_user_table['nickname'];
        if ($receiver_mobile_number) {

            $id = $escrow_data['id'];

            $db->where('source', $source);
            $site = $db->getOne('site');
            $Prefix = $site['otp_prefix'];

            if ($Prefix != ""){
                $source = $Prefix;
            }
            
            $domain = $site['domain'];
            $return_message = "%%companyName%%: %%senderName%% has released your escrow fund (%%id%%) via %%companyName%%. Amount: %%amount%%. %%link%%";
            $return_message2 = str_replace("%%companyName%%", $source, $return_message);
            $return_message3 = str_replace("%%senderName%%", $sender_name, $return_message2);
            $return_message4 = str_replace("%%amount%%", $amount, $return_message3);
            $return_message5 = str_replace("%%id%%", $id, $return_message4);
            $newParams["message"] = str_replace("%%link%%", $domain, $return_message5);
            $newParams["recipients"] = $receiver_mobile_number;
            $newParams["ip"] = $ip;
            $newParams["companyName"] = $source;
            $xunSms->send_sms($newParams);
        }

        if ($receiver_email_address) {
            $receiver_name = $send_fund_data['recipient_name'];

            $db->where('currency_id', $send_fund_data['wallet_type']);
            $symbol = $db->getValue('xun_marketplace_currencies', 'symbol');

            $id = $escrow_data['id'];

            // $symbol
            $send_email_params = array(
                "sender_name" => $sender_name,
                "receiver_name" => $receiver_name,
                "amount" => $amount,
                "symbol" => $symbol,
                "id" => $id,
            );

            $emailDetail = $xunEmail->getSendReleaseEmail($source, $send_email_params);

            $emailParams["subject"] = $emailDetail['emailSubject'];
            $emailParams["body"] = $emailDetail['html'];
            $emailParams["recipients"] = array($receiver_email_address);
            $emailParams["emailFromName"] = $emailDetail['emailFromName'];
            $emailParams["emailAddress"] = $emailDetail['emailAddress'];
            $emailParams["emailPassword"] = $emailDetail['emailPassword'];

            $msg = $general->sendEmail($emailParams);
        }

        // TODO LANGUAGE PLUGIN: Escrow release successful
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'Escrow release successful.' /* Escrow release successful. */);
    }

    public function nuxpay_escrow_send_message($params, $source)
    {
        $db = $this->db;

        $business_id = $params['business_id'];
        // $tx_type = $params['tx_type'];
        $reference_id = $params['reference_id'];
        $message = $params['message'];


        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "Business ID cannot be empty");
        }
        // if($tx_type == ''){
        //     // TODO LANGUAGE PLUGIN: Transaction type cannot be empty
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction type cannot be empty" /* Transaction type cannot be empty */, "developer_msg" => "Transaction type cannot be empty");
        // }

        if ($reference_id == '') {
            // TODO LANGUAGE PLUGIN: Transaction type cannot be empty
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Reference ID cannot be empty" /*Reference ID cannot be empty*/, "developer_msg" => "Reference ID cannot be empty");
        }

        if ($message == '') {
            // TODO LANGUAGE PLUGIN: Transaction type cannot be empty
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Message cannot be empty" /*Message cannot be empty*/, "developer_msg" => "Message cannot be empty");
        }

        // Is business_id a valid user?
        $db->where('register_site', $source);
        $db->where('type', 'business');
        $db->where('disabled', 0);
        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/, 'source' => $source);
        }

        $tx_table = "xun_escrow";

        // Is reference_id, tx_type valid escrow?
        // if($tx_type == 'send') {

        // } else {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid transaction type" /*Invalid transaction type*/, "developer_msg" => "Invalid transaction type");
        // }

        $db->where('id', $reference_id);
        $fund_table = $db->getOne($tx_table);
        if (!$fund_table) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => 'Fund table not found.');
        }

        $db->where('id', $reference_id);
        $escrow_table = $db->getOne('xun_escrow');
        if (!$escrow_table) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => 'Escrow table not found.');
        }

        $insert_data = array(
            'reference_id' => $escrow_table['id'],
            'message'      => $message,
            'user_id'       => $business_id,
            'created_at'    => date("Y-m-d H:i:s")
        );
        $chat_id = $db->insert('xun_escrow_chat', $insert_data);

        // TODO LANGUAGE PLUGIN: Message was sent successfully
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Message was sent successfully" /* Message was sent successfully */);
    }

    public function nuxpay_redeem_redemption_pin($params, $source, $ip = null)
    {
        global $xunCrypto, $config, $xunSms, $xunEmail, $general, $xunPayment;
        $db = $this->db;
        $post = $this->post;
        $setting = $this->setting;

        $business_id = $params['business_id'];
        $redeem_code = $params['redeem_code'];

        $prepaidWalletServerURL =  $config["giftCodeUrl"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "Business ID cannot be empty");
        }

        if ($redeem_code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00597') /*Redeem Code cannot be empty*/, "developer_msg" => "Redeem Code cannot be empty");
        }

        $db->where('register_site', $source);
        $db->where('type', 'business');
        $db->where('disabled', 0);
        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/, 'source' => $source);
        }

        $business_name = $xun_user['nickname'];

        $db->where('redeem_code', $redeem_code);
        // $db->where('status', 'activated');
        $send_fund_data = $db->getOne('xun_payment_gateway_send_fund');

        if ($send_fund_data['status'] != 'activated') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00598') /*Invalid Redeem Code.*/, 'developer_msg' => 'Invalid Redeem Code.');
        }

        if ($send_fund_data['status'] == 'success') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('B00365') /*This redeem code is fully redeemed.*/, 'developer_msg' => 'This redeem code is fully redeemed.');
        }

        $db->where('id', $send_fund_data['business_id']);
        $xun_sender_user = $db->getOne('xun_user');

        $db->where('user_id', $send_fund_data['business_id']);
        $db->where('address_type', 'nuxpay_wallet');
        $db->where('active', 1);
        $sender_crypto_user_address = $db->getOne('xun_crypto_user_address');

        $send_fund_id = $send_fund_data['id'];
        $amount = $send_fund_data['amount'];
        $wallet_type = $send_fund_data['wallet_type'];
        $escrow = $send_fund_data['escrow'];

        $db->where('user_id', $business_id);
        $db->where('address_type', 'nuxpay_wallet');
        $db->where('active', 1);
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        if (!$crypto_user_address) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => 'Internal Address not found.');
        }

        if ($escrow) {
            $destination_address = $setting->systemSetting['escrowInternalAddress'];
        } else {
            $destination_address = $crypto_user_address['address'];
        }

        $redeem_code_wallet_address = $setting->systemSetting['redeemCodeAgentAddress'];
        $escrow_internal_address = $setting->systemSetting['escrowInternalAddress'];

        $xun_business_service = new XunBusinessService($db);
        $tx_obj = new stdClass();
        $tx_obj->userID = 0;
        $tx_obj->address = $redeem_code_wallet_address;

        $transaction_token = $xun_business_service->insertCryptoTransactionToken($tx_obj);

        $xunWallet = new XunWallet($db);

        $transactionObj = new stdClass();
        $transactionObj->status = "pending";
        $transactionObj->transactionHash = "";
        $transactionObj->transactionToken = $transaction_token;
        $transactionObj->senderAddress = $redeem_code_wallet_address;
        $transactionObj->recipientAddress = $destination_address;
        $transactionObj->userID = $business_id;
        $transactionObj->senderUserID = 'redeem_code';
        if ($escrow) {
            $transactionObj->recipientUserID = "escrow_wallet";
        } else {
            $transactionObj->recipientUserID = $business_id;
        }
        $transactionObj->walletType = $wallet_type;
        $transactionObj->amount = $amount;
        $transactionObj->addressType = "nuxpay_wallet";
        if ($escrow) {
            $transactionObj->transactionType = "send";
        } else {
            $transactionObj->transactionType = "receive";
        }
        $transactionObj->escrow = 0;
        $transactionObj->referenceID = $send_fund_id;
        $transactionObj->message = ($escrow) ? 'send_escrow' : 'redeem_code';
        $transactionObj->escrowContractAddress = '';
        $transactionObj->createdAt = $date;
        $transactionObj->updatedAt = $date;
        $transactionObj->expiresAt = '';

        $wallet_transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);

        $txHistoryObj->status = "pending";
        $txHistoryObj->transactionID = "";
        $txHistoryObj->transactionToken = $transaction_token;
        $txHistoryObj->senderAddress = $redeem_code_wallet_address;
        $txHistoryObj->recipientAddress = $destination_address;
        $txHistoryObj->senderUserID = 'redeem_code';
        if ($escrow) {
            $txHistoryObj->recipientUserID = "escrow_wallet";
        } else {
            $txHistoryObj->recipientUserID = $business_id;
        }
        $txHistoryObj->walletType = $wallet_type;
        $txHistoryObj->amount = $amount;
        $txHistoryObj->transactionType = "nuxpay_wallet";
        $txHistoryObj->referenceID = $send_fund_id;
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
        $xunWallet->updateWalletTransaction($wallet_transaction_id, $updateWalletTx);

        $satoshi_amount = $xunCrypto->get_satoshi_amount($wallet_type, $amount);

        $updateRedeem = array(
            "redeemed_by" => $business_id,
            "redeemed_at" => date("Y-m-d H:i:s"),
            "status"      => "redeemed"
        );

        $db->where('id', $send_fund_id);
        $updated = $db->update('xun_payment_gateway_send_fund', $updateRedeem);


        // need to notify sender     
        $sender_mobile_number = $xun_sender_user['username'];
        $sender_email_address = $xun_sender_user['email'];

        if ($escrow) {
            // send notification if escrow

            $db->where('reference_id', $send_fund_data['id']);
            $db->where('tx_type', 'send');
            $escrow_table = $db->getOne('xun_escrow');

            //wentin
            $target = 'internal';

            // 
            $insertFundIn = array(
                "business_id" => $business_id, // receiver's business id
                // "transaction_id" => $transaction_hash, at callback only update
                // "reference_id" => $bc_reference_id, at callback only update
                "sender_address" => $sender_crypto_user_address['address'],
                "receiver_address" => $crypto_user_address['address'],
                "status" => "hold", // new
                "amount" => $amount,
                "amount_receive" => $amount,
                "transaction_fee" => '0',
                "miner_fee" => '0',
                "wallet_type" => strtolower($wallet_type),
                // "exchange_rate" => $exchange_rate, not sure yet
                "type" => "hold_escrow", // at update it becomes release_escrow
                "transaction_type" => "blockchain",
                "escrow_id" => $escrow_table['id'], // new
                "transaction_target" => $target, // wentin //
                // "transaction_id" =>  //according to above

                "created_at" => date("Y-m-d H:i:s")
            );

            $fund_in_id = $db->insert('xun_payment_gateway_fund_in', $insertFundIn);

            // update sender's withdrawal, dont need updated at callback
            // $updateWithdrawal = array(                
            //     "updated_at" => date("Y-m-d H:i:s")
            // );
            // $db->where('escrow_id', $escrow_table['id']);
            // $db->update('xun_payment_gateway_withdrawal', $updateWithdrawal);


            // escrow        
            if ($sender_mobile_number) {

                $siteURL = $source;
                $db->where('source', $siteURL);
                $callbackUrl = $db->getValue('site', 'domain');

                $db->where('source', $source);
                $site = $db->getOne('site');
                $Prefix = $site['otp_prefix'];

                if ($Prefix != ""){
                    $source = $Prefix;
                }
                
                // $domain = $site['domain'];                                
                $return_message = "%%companyName%%: %%receiverName%% has used your escrow redeem code %%redeemCode%% via %%companyName%%. Please log back in and apply further actions. %%url%%";
                $return_message2 = str_replace("%%companyName%%", $source, $return_message);
                $return_message3 = str_replace("%%receiverName%%", $business_name, $return_message2);
                $return_message4 = str_replace("%%redeemCode%%", $redeem_code, $return_message3);
                $return_message5 = str_replace("%%url%%", $callbackUrl, $return_message4);
                // $return_message4 = str_replace("%%redeemCode%%", $redeem_code, $return_message3);
                $newParams["message"] = $return_message5;
                $newParams["recipients"] = $sender_mobile_number;
                $newParams["ip"] = $ip;
                $newParams["companyName"] = $source;
                $xunSms->send_sms($newParams);
            }

            if ($sender_email_address) {
                $db->where('currency_id', $send_fund_data['wallet_type']);
                $symbol = $db->getValue('xun_marketplace_currencies', 'symbol');

                $payment_description = $send_fund_data['description'];
                $send_email_params = array(
                    "sender_name" => $xun_sender_user['nickname'],
                    "receiver_name" => $business_name,
                    "amount" => $amount,
                    "symbol" => $symbol,
                    "description" => $payment_description,
                    "redeem_code" => $redeem_code,
                );

                $emailDetail = $xunEmail->getSendEscrowRedeemEmail($source, $send_email_params);

                $emailParams["subject"] = $emailDetail['emailSubject'];
                $emailParams["body"] = $emailDetail['html'];
                $emailParams["recipients"] = array($sender_email_address);
                $emailParams["emailFromName"] = $emailDetail['emailFromName'];
                $emailParams["emailAddress"] = $emailDetail['emailAddress'];
                $emailParams["emailPassword"] = $emailDetail['emailPassword'];

                $msg = $general->sendEmail($emailParams);
            }
        } else {
            // send notification for normal redeem

            if ($sender_mobile_number) {

                $siteURL = $source;
                $db->where('source', $siteURL);
                $callbackUrl = $db->getValue('site', 'domain');

                $db->where('source', $source);
                $site = $db->getOne('site');
                $Prefix = $site['otp_prefix'];

                if ($Prefix != ""){
                    $source = $Prefix;
                }
                
                $return_message = "%%companyName%%: %%receiverName%% has used your redeem code %%redeemCode%% via %%companyName%%. %%url%%";
                $return_message2 = str_replace("%%companyName%%", $source, $return_message);
                $return_message3 = str_replace("%%receiverName%%", $business_name, $return_message2);
                $return_message4 = str_replace("%%redeemCode%%", $redeem_code, $return_message3);
                $return_message5 = str_replace("%%url%%", $callbackUrl, $return_message4);
                $newParams["message"] = $return_message5;
                $newParams["recipients"] = $sender_mobile_number;
                $newParams["ip"] = $ip;
                $newParams["companyName"] = $source;
                $xunSms->send_sms($newParams);
            }

            if ($sender_email_address) {
                $db->where('currency_id', $send_fund_data['wallet_type']);
                $symbol = $db->getValue('xun_marketplace_currencies', 'symbol');

                $payment_description = $send_fund_data['description'];
                $send_email_params = array(
                    "sender_name" => $xun_sender_user['nickname'],
                    "receiver_name" => $business_name,
                    "amount" => $amount,
                    "symbol" => $symbol,
                    "description" => $payment_description,
                    "redeem_code" => $redeem_code,
                );

                $emailDetail = $xunEmail->getSendRedeemEmail($source, $send_email_params);

                $emailParams["subject"] = $emailDetail['emailSubject'];
                $emailParams["body"] = $emailDetail['html'];
                $emailParams["recipients"] = array($sender_email_address);
                $emailParams["emailFromName"] = $emailDetail['emailFromName'];
                $emailParams["emailAddress"] = $emailDetail['emailAddress'];
                $emailParams["emailPassword"] = $emailDetail['emailPassword'];

                $msg = $general->sendEmail($emailParams);
            }
        }


        $curlParams = array(
            "command" => "fundOutCompanyWallet",
            "params" => array(
                "senderAddress" => $redeem_code_wallet_address,
                "receiverAddress" => $destination_address,
                "amount" => $amount,
                "satoshiAmount" => $satoshi_amount,
                "walletType" => $wallet_type,
                "id" => $wallet_transaction_id,
                "transactionToken" => $transaction_token,
                "addressType" => "redeem_code_wallet",
            ),
        );

        $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);

        if ($curlResponse["code"] == 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $curlResponse);
        }

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00361') /* Redeem Code Successful. */);
    }

    public function get_user_info($params, $source, $ip)
    {
        global $config, $xun_numbers, $xunSms, $xunEmail;
        $db = $this->db;

        $user_search = $params['user_search'];

        if (empty($user_search)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => "user_search cannot be empty!");
        }

        $db->where('register_site', $source);
        $db2 = clone $db;

        $db->where('username', '+' . $user_search, 'LIKE');
        $user_record_username = $db->getOne('xun_user');

        $db2->where('email', $user_search);
        $user_record_email = $db2->getOne('xun_user');


        if (empty($user_record_username) && empty($user_record_email)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid param!", "developer_msg" => "Invalid param!");
        }

        if ($user_record_username) {
            $type = "mobile_number";
            $user_record = $user_record_username;
        } else if ($user_record_email) {
            $type = "email_address";
            $user_record = $user_record_email;
        }

        $data = $user_record;
        $data['type'] = $type;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Found user", "data" => $data);
    }

    public function resend_redeem_code($params, $source, $ip)
    {
        global $config, $xun_numbers, $xunSms, $xunEmail;
        $db = $this->db;
        $general = $this->general;

        $redeem_code = $params['redeem_code'];

        if ($redeem_code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00597') /*Redeem Code cannot be empty*/, "developer_msg" => "Redeem Code cannot be empty");
        }

        $db->where('redeem_code', $redeem_code);
        $db->where('status', 'activated');
        $redeem_code_detail = $db->getOne('xun_payment_gateway_send_fund');

        if (!$redeem_code_detail) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00598') /*Invalid Redeem Code.*/, 'developer_msg' => 'Invalid Redeem Code.');
        }

        $sender_name = $redeem_code_detail['sender_name'];
        $receiver_name = $redeem_code_detail['recipient_name'];
        $receiver_mobile_number = $redeem_code_detail['recipient_mobile_number'];
        $receiver_email_address = $redeem_code_detail['recipient_email_address'];
        $wallet_type = $redeem_code_detail['wallet_type'];
        $payment_description = $redeem_code_detail['description'];
        $amount = $redeem_code_detail['amount'];

        $db->where('currency_id', $wallet_type);
        $marketplace_currencies = $db->getOne('xun_marketplace_currencies', 'symbol, currency_id');

        $symbol = $marketplace_currencies['symbol'];
        if ($redeem_code) {
            if ($receiver_mobile_number) {

                $db->where('source', $source);
                $site = $db->getOne('site');
                $Prefix = $site['otp_prefix'];

                if ($Prefix != ""){
                    $source = $Prefix;
                }
                
                $domain = $site['domain'];
                $return_message = $this->get_translation_message('B00366'); /*%%companyName%%: %%senderName%% sent funds to you via %%companyName%%. Your redemption pin is %%redeemCode%%. Redeem now from %%link%%.*/
                $return_message2 = str_replace("%%companyName%%", $source, $return_message);
                $return_message3 = str_replace("%%senderName%%", $sender_name, $return_message2);
                $return_message4 = str_replace("%%redeemCode%%", $redeem_code, $return_message3);
                $newParams["message"] = str_replace("%%link%%", $domain, $return_message4);
                $newParams["recipients"] = $receiver_mobile_number;
                $newParams["ip"] = $ip;
                $newParams["companyName"] = $source;
                $xunSms->send_sms($newParams);
            }

            if ($receiver_email_address) {
                $send_email_params = array(
                    "sender_name" => $sender_name,
                    "receiver_name" => $receiver_name,
                    "amount" => $amount,
                    "symbol" => $symbol,
                    "description" => $payment_description,
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
            $data['redeem_code'] = $redeem_code;
        }

        $tag = "Resend Redeem PIN";
        $message = "Sender: " . $sender_name . "\n";
        $message .= "Receiver: " . $receiver_name . "\n";
        $message .= "Amount:" . $amount . "\n";
        $message .= "Wallet Type:" . $wallet_type . "\n";
        $message .= "Time: " . date("Y-m-d H:i:s") . "\n";

        $thenux_params["tag"]         = $tag;
        $thenux_params["message"]     = $message;
        $thenux_params["mobile_list"] = $xun_numbers;
        $thenux_result                  = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00367') /*Resend Redeem PIN Successful.*/);
    }

    public function pg_address_withdrawal($params, $user_id, $source, $type)
    {
        global $config, $xun_numbers, $xunCrypto, $account;
        $db = $this->db;
        $general = $this->general;
        $post = $this->post;

        $destination_address = $params['destination_address'];
        $wallet_type = $params['wallet_type'];

        if (!$user_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if (!$destination_address) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00153') /*Destination address cannot be empty*/,  "developer_msg" => "Destination Address cannot be empty");
        }

        if (!$wallet_type) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "Wallet Type cannot be empty");
        }

        $db->where('register_site', $source);
        $db->where('id', $user_id);
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/, 'source' => $source);
        }

        // $db->where('business_id', $user_id);
        // $db->where('transaction_type', array("withhold", "release_withhold"), "IN");
        // $db->where('deleted', 0);
        // $balance_data = $db->getOne('xun_payment_gateway_invoice_transaction', 'SUM(credit) as totalCredit, SUM(debit) as totalDebit');

        // $withhold_balance = bcsub($balance_data['totalCredit'], $balance_data['totalDebit'], 8);

        $withhold_balance = $account->getWithholdBalance($user_id, $wallet_type . 'Withholding');

        if ($withhold_balance <= 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insufficient Balance.");
        }

        $validate_result = $xunCrypto->crypto_validate_address($destination_address, $wallet_type, "external");

        if ($validate_result["code"] == 1) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $validate_result['statusMsg'], "errorCode" => -100);
        }

        $whitelistParams = array(
            "partner" => $config['whitelistPartner'],
            "command" => "whitelistAddressChecking",
            "params" => array(
                "user_id" => $user_id,
                "wallet_type" => $wallet_type,
                "address" => $destination_address
            )
        );

        $whitelistReturn = $post->curl_post($config['whitelistWebserviceURL'], $whitelistParams, 0);

        if ($whitelistReturn['code'] == 0) {
            $whitelistData['code'] = 1;
            $whitelistData['errorCode'] = '-100';
            $whitelistData['message_d'] = $whitelistReturn['message_d'];
            return $whitelistData;
        }

        $pg_balance_params = array(
            "walletType" => $wallet_type,
            "userID" => $user_id
        );

        $balance_crypto_results = $post->curl_crypto("pgAddressBalance", $pg_balance_params);

        if ($balance_crypto_results['code'] == 1) {
            return array("code" => 0, "message" => "SUCCESS", "message_d" => $balance_crypto_results['statusMsg']);
        }

        $pg_address_balance_list = $balance_crypto_results['data']['pg_address'];

        $balance_address_list = array_keys($pg_address_balance_list);

        // $db->where('recipient_address', '', '!=');
        // $db->where('business_id', $user_id);
        // $db->where('deleted', 0);
        // $db->where('wallet_type', $wallet_type);
        // $db->where('transaction_type', 'withhold');
        // $withhold_transaction_list = $db->map('recipient_address')->ArrayBuilder()->get('xun_payment_gateway_invoice_transaction', null, 'recipient_address');

        $db->where('business_id', $user_id);
        $db->where('gw_type', 'PG');
        $db->where('status', 'received');
        $db->where('wallet_type', $wallet_type);
        $withhold_transaction_list = $db->map('address')->ArrayBuilder()->get('xun_crypto_history', null, 'address');

        $withhold_pg_address = array_keys($withhold_transaction_list);

        $pg_address_list = array_intersect($balance_address_list, $withhold_pg_address);

        if (!$pg_address_list) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00632') /*You do not have address that is available to withdraw.*/, 'developer_msg' => "You do not have address that is available to withdraw.", 'errorCode' => -102);
        }

        if ($type == 'verification') {
            foreach ($pg_address_list as $pg_key => $pg_value) {
                if ($pg_address_balance_list[$pg_value]) {
                    $verify_pg_address_list[] = $pg_address_balance_list[$pg_value];
                }
            }
            $data['pg_address_list'] = $verify_pg_address_list;
            return array("code" => 1, "message" => "SUCCESS", "message_d" => 'Verification Successful', 'data' => $data);
        }

        $crypto_params = array(
            "pgAddress" => $pg_address_list,
            "walletType" => $wallet_type,
            "destinationAddress" => $destination_address,
            "userID" => $user_id
        );

        $crypto_results = $post->curl_crypto("pgAddressWithdraw", $crypto_params);

        if ($crypto_results['code'] == 1) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $crypto_results['message']);
        }

        $payment_gateway_data = $crypto_results['data'];

        foreach ($payment_gateway_data as $pg_key => $pg_value) {
            $pg_address = $pg_value['payment_gateway_address'];

            $pg_address_list[] = $pg_address;
        }

        if ($pg_address_list) {
            $db->where('payment_address', $pg_address_list, 'IN');
            $invoice_detail_data = $db->map('payment_address')->ArrayBuilder()->get('xun_payment_gateway_invoice_detail a', null, 'a.id, a.payment_address');
        }

        foreach ($payment_gateway_data as $pg_key => $pg_value) {
            $address = $pg_value['payment_gateway_address'];
            $amount = $pg_value['amount'];
            $destination_address = $pg_value['destination_address'];
            $reference_id = $pg_value['reference_id'];

            $db->where('reference_id', $reference_id);
            $payment_withdrawal_data = $db->getOne('xun_payment_gateway_withdrawal');

            if (!$payment_withdrawal_data) {
                $insertWithdrawal = array(
                    "reference_id" => $reference_id,
                    "business_id" => $user_id,
                    "amount" => $amount,
                    "sender_address" => $address,
                    "recipient_address" => $destination_address,
                    "amount_receive" => $amount,
                    "wallet_type" => $wallet_type,
                    "status" => "pending",
                    "transaction_type" => $invoice_detail_data['address'] ? "request_fund" : "api_integration",
                    "created_at" => date("Y-m-d H:i:s"),
                );

                $row_id = $db->insert('xun_payment_gateway_withdrawal', $insertWithdrawal);
            }

            $updateProcessed = array(
                "processed" => 1
            );
            $db->where('recipient_address', $address);
            $db->where('transaction_type', 'withhold');
            $db->update('xun_payment_gateway_invoice_transaction', $updateProcessed);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00370') /*Your Withdraw is Processing*/, 'developer_msg' => 'Your Withdraw is Processing.');
    }

    public function update_payment_gateway_transaction($params)
    {
        $db = $this->db;
        $post = $this->post;

        $transaction_token = $params['transaction_token'];
        $payment_type = $params['payment_type'];

        $date = date("Y-m-d H:i:s");

        $db->where('transaction_token', $transaction_token);
        $pg_payment_tx = $db->getOne('xun_payment_gateway_payment_transaction');

        if (!$pg_payment_tx) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Transaction token not found.");
        }

        $address = $pg_payment_tx['address'];
        $business_id = $pg_payment_tx['business_id'];
        $wallet_type = $pg_payment_tx['wallet_type'];
        if ($payment_type == 'payment_gateway') {
            if (!$address) {

                $db->where('id', $business_id);
                $xun_user = $db->getOne('xun_user', 'nickname');
                $crypto_params["type"] = $wallet_type;
                $crypto_params['businessID'] = $business_id;
                $crypto_params['businessName'] = $xun_user['nickname'];

                $crypto_results = $post->curl_crypto("getNewAddress", $crypto_params);

                if ($crypto_results["code"] != 0) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_results["message"]);
                }

                $address = $crypto_results["data"]["address"];

                if (!$address) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00151') /*Address not generated.*/);
                }

                $db->where('business_id', $business_id);
                $db->where('type', $wallet_type);
                $db->where('status', 1);
                $wallet_id = $db->getValue('xun_crypto_wallet', 'id');

                $insert_data = array(
                    "wallet_id" => $wallet_id,
                    "crypto_address" => $address,
                    "type" => "in",
                    "status" => 1,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $address_id = $db->insert("xun_crypto_address", $insert_data);
                if (!$address_id) {
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/,
                        "error_message" => $db->getLastError()
                    );
                }
            }
        }

        $update_address = array(
            "address" => $address,
            "payment_type" => $payment_type,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('transaction_token', $transaction_token);
        $db->update('xun_payment_gateway_payment_transaction', $update_address);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Payment Transaction Updated.", 'developer_msg' => 'Payment Transaction Updated.');
    }

    public function get_send_fund_details($params)
    {
        $db = $this->db;

        $transaction_token = $params['transaction_token'];

        if ($transaction_token == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00414') /*Transaction token cannot be empty*/);
        }

        // $db->where('pg_transaction_token', $transaction_token);
        // $send_fund_data = $db->getOne('xun_payment_gateway_send_fund');

        // if(!$send_fund_data){
        //     return array("code" => 0, "message" => "FAILED", "message_d" => "Send Fund not found.");

        // }

        $db->where('transaction_token', $transaction_token);
        $pg_payment_tx_data = $db->getOne('xun_payment_gateway_payment_transaction');

        $business_id = $pg_payment_tx_data['business_id'];
        $amount = $pg_payment_tx_data['amount'];
        $wallet_type = $pg_payment_tx_data['wallet_type'];

        $db->where('currency_id', $wallet_type);
        $symbol = $db->getValue('xun_marketplace_currencies', 'symbol');

        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user', 'id, nickname, username, email');

        $receiver_type = $xun_user['username'] ? 'mobile' : 'email';
        $receiver_email_address = $xun_user['email'] ? $xun_user['email'] : '';
        $receiver_mobile_number = $xun_user['username'] ? $xun_user['username'] : '';
        $receiver_name = $xun_user['nickname'];

        $data = array(
            "amount" => $amount,
            "wallet_type" => $wallet_type,
            "symbol" => strtoupper($symbol),
            "receiver_type" => $receiver_type,
            "receiver_mobile_number" => $receiver_mobile_number,
            "receiver_email_address" => $receiver_email_address,
            "receiver_name" => $receiver_name,
            "business_id" => $business_id
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Get Send Fund Details", "data" => $data, 'lq' => $db->getLastQuery());
    }

    public function get_send_fund_transaction_status($params)
    {
        global $xunCrypto, $xunCurrency;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        $post = $this->post;

        $transaction_token = trim($params["transaction_token"]);

        if ($transaction_token == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00414') /*Transaction token cannot be empty*/);
        }

        $db->where('pt.crypto_transaction_token', '', '!=');
        $db->where('pt.status', 'success');
        $db->where('pt.transaction_token', $transaction_token);
        $db->join("xun_cryptocurrency_rate r", "r.cryptocurrency_id=pt.wallet_type", "INNER");
        $db->join("xun_business b", "b.user_id=pt.business_id", "INNER");
        $db->join('xun_wallet_transaction wt', 'pt.crypto_transaction_token = wt.transaction_token', 'INNER');
        $pg_transaction = $db->getOne("xun_payment_gateway_payment_transaction pt", "pt.status, pt.transaction_token, pt.address, pt.created_at, wt.amount, pt.wallet_type, wt.transaction_hash as transaction_id, wt.exchange_rate, pt.redirect_url, b.name, r.unit");

        if (!$pg_transaction) {
            $db->where('transaction_token', $transaction_token);
            $pg_payment_transaction = $db->getOne('xun_payment_gateway_payment_transaction', 'status, redirect_url');

            $data = array(
                "status" => 'cancelled',
            );
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00434'), /*Invalid transaction.*/
                "data" => $data,
                "redirect_url" => $pg_payment_transaction['redirect_url'],
            );
        }

        $return_data = [];
        $return_data["status"] = $pg_transaction["status"];
        $return_data["transaction_token"] = $pg_transaction["transaction_token"];
        $return_data["transaction_datetime"] = $pg_transaction["created_at"];
        $return_data["address"] = $pg_transaction["address"];
        $return_data["amount"] = $pg_transaction["amount"];
        $return_data["currency"] = $pg_transaction["wallet_type"];
        $return_data["transaction_id"] = $pg_transaction["transaction_id"];
        $return_data["unit"] = $pg_transaction["unit"];
        $return_data["exchange_rate"] = $pg_transaction["exchange_rate"];

        return array(
            "code" => 1,
            "message" => "SUCCESS",
            "message_d" => $this->get_translation_message('B00226') /*Success*/,
            "data" => $return_data,
            "redirect_url" => $pg_transaction["redirect_url"],
            "merchant_name" => $pg_transaction["name"]
        );
    }

    public function thenuxCheckPGAddressBusiness($params)
    {

        $db = $this->db;

        $address = trim($params['address']);

        if ($address != "") {

            $db->where('a.crypto_address', $address);
            $db->join('xun_crypto_wallet w', 'w.id=a.wallet_id', 'INNER');
            $db->join('xun_business b', 'b.user_id=w.business_id', 'INNER');
            $addressDetail = $db->getOne('xun_crypto_address a', 'b.user_id, b.name');

            if ($addressDetail) {
                $business_name = $addressDetail['name'];
                $business_id = $addressDetail['user_id'];
            } else {
                $business_name = "";
                $business_id = "";
            }
        } else {
            $business_name = "";
            $business_id = "";
        }

        $data['address'] = $address;
        $data['businessId'] = $business_id;
        $data['businessName'] = $business_name;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => '', 'data' => $data);
    }

    public function get_payment_gateway_address_details($params)
    {
        global $xunCurrency, $xunSwapcoins, $xunPay, $config;
        $db = $this->db;
        $post = $this->post;
        $xunCrypto = $this->xunCrypto;

        $transaction_token = $params['transaction_token'];
        $wallet_type = $params['wallet_type'];

        if ($transaction_token == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00414') /*Transaction token cannot be empty*/);
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "Wallet Type cannot be empty");
        }

        $db->where('transaction_token', $transaction_token);
        $pg_payment_tx = $db->getOne('xun_payment_transaction', 'id, business_id, crypto_amount, wallet_type');

        if (!$pg_payment_tx) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Transaction not found.");
        }


        $crypto_amount = $pg_payment_tx['crypto_amount'];
        $tx_wallet_type = $pg_payment_tx['wallet_type'];
        $payment_tx_id = $pg_payment_tx['id'];
        $business_id = $pg_payment_tx['business_id'];
        $fiat_currency_id = $pg_payment_tx['fiat_currency_id'];

        if ($tx_wallet_type != $wallet_type) {
            $converted_amount = $xunCurrency->get_conversion_amount($wallet_type, $tx_wallet_type, $crypto_amount);
        } else {
            $converted_amount = $crypto_amount;
        }

        $db->where('payment_tx_id', $payment_tx_id);
        $db->where('wallet_type', $wallet_type);
        $db->where('type', 'payment_gateway');
        $address = $db->getValue('xun_payment_method', 'address');

        if (!$address) {

            $params['business_id'] = $business_id;
            $returnPgAddress = $this->generate_new_pg_address($params);

            if ($returnPgAddress['code'] == 0) {
                return $returnPgAddress;
            }

            $address = $returnPgAddress['data']['address'];

            $insert_payment_method_arr = array(
                "payment_tx_id" => $payment_tx_id,
                "address" => $address,
                "wallet_type" => $wallet_type,
                "type" => "payment_gateway",
                "created_at" => date("Y-m-d H:i:s")
            );

            $db->insert('xun_payment_method', $insert_payment_method_arr);
        }

        // get currency symbol and decimal places
        $currency_info = $xunCurrency->get_currency_info($wallet_type);
        $currency_unit = strtoupper($currency_info["symbol"]);
        $decimal_places  = $xunCurrency->get_currency_decimal_places($wallet_type);
        if($currency_unit == 'trx-usdt')
        {
            $currency_unit = 'usdt';
        }
        $uc_currency_unit = strtoupper($currency_unit);
        $uc_fiat_currency_id = strtoupper($fiat_currency_id);
        // check if user had set `isAllowCurrency=1` when requested type is different
        // markup converted amount for autoswap 
        $db->where('user_id', $business_id);
        $db->where('name', 'allowSwitchCurrency');
        $isAllowSwitchCurrency = $db->getValue('xun_user_setting', 'value');
        $markupAmount = 0;

        if (($tx_wallet_type != $wallet_type) && $isAllowSwitchCurrency) {
            // get swap exchange rate 
            $swapSetting = $xunSwapcoins->getSwapSetting($wallet_type, $tx_wallet_type);
            if (!$swapSetting || $swapSetting['code'] != 1) {
                return array("code" => 0, "message" => "FAILED", "message_d" => "Currency pair not supported.");
            }
            $marginPercentage = $swapSetting['data']['margin_percentage'];
            $crypto_params = array(
                "fromWalletType" => $wallet_type,
                "toWalletType" => $tx_wallet_type,
                "marginPercentage" => $marginPercentage
            );
            $crypto_result = $xunCrypto->get_market_price($crypto_params);
            if (!$crypto_result || $crypto_result['status'] != 'ok') {
                return array("code" => 0, "message" => "FAILED", "message_d" => "Failed to get exchange rate.");
            }
            $markupExchangeRate = $crypto_result['data']['markupExchangeRate'];
            $converted_amount = bcdiv($crypto_amount, $markupExchangeRate, $decimal_places);

            // get markup amount (miner fee + service charge)
            $getServiceChargeParams = array(
                'wallet_type' => $wallet_type,
                'address' => $address,
                'amount' => $converted_amount,
                'check_service_charge' => 1
            );
            $serviceChargeResult = $xunCrypto->get_service_charge($getServiceChargeParams);
            if (!$serviceChargeResult || $serviceChargeResult['status'] != 'ok') {
                return array("code" => 0, "message" => "FAILED", "message_d" => "Failed to get service charge amount.");
            }
            $serviceCharge = $serviceChargeResult['result']['service_charge']['amount'];

            $getMinerFeeEstimationParams = array(
                'creditType' => $wallet_type,
                'address' => $address,
                'includeService' => 1
            );
            $minerFeeEstimationResult = $xunCrypto->get_miner_fee_estimation($getMinerFeeEstimationParams);
            if ($minerFeeEstimationResult['status'] != 'ok' || !$minerFeeEstimationResult) {
                return array("code" => 0, "message" => "FAILED", "message_d" => "Failed to get miner fee estimation.");
            }
            $minerFee = $minerFeeEstimationResult['data']['minerFee'];

            $markupAmount = bcadd($serviceCharge, $minerFee, $decimal_places);
            $markupAmount = bcadd($markupAmount, $converted_amount, $decimal_places);
            $markupAmount = $markupAmount ? $markupAmount : 0;
        }

        $db->where('company', array('simplex', 'xanpool'), 'IN');
        $provider_data = $db->get('provider', null, 'id, company, name');

         foreach ($provider_data as $key => $value) {
            $provider_id = $value['id'];
            $provider = $value['company'];
  
            if ($provider == 'Simplex') {
                $simplex_margin_percentage = $setting->systemSetting['simplexMarginPercentage'];
                $db->where('provider_id', $provider_id);
                $db->where('name', array('minAmount', 'maxAmount', 'minCryptoAmount'), 'IN');
                $db->where('type', $uc_currency_unit);
                $provider_setting_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');
    
                $db->where('provider_id', $provider_id);
                $db->where('name', array('supportedCurrencies', 'fiatCurrencyList'), 'IN');
                $global_provider_setting_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

                // $markup_converted_amount = bcmul($converted_amount, (string)((100 + $simplex_margin_percentage) / 100), 8);
                $fiat_converted_amount = bcmul($fiat_converted_amount, (string)((100 + $simplex_margin_percentage) / 100), 8);
                $supported_currencies = strtoupper($global_provider_setting_data['supportedCurrencies']);
                $supported_fiat_list = strtoupper($global_provider_setting_data['fiatCurrencyList']);
                $supported_currencies_arr = explode(",", $supported_currencies);
                $supported_fiat_currency_arr = explode(",", $supported_fiat_list);

                $crypto_rate_arr = $xunCurrency->get_cryptocurrency_rate(array($wallet_type));

                $crypto_price_usd = $crypto_rate_arr[$wallet_type];

                $db->where('provider_id', $provider_id);
                $db->where('name', array('minAmount', 'maxAmount'), 'IN');
                $db->orderBy('type', 'ASC');
                $min_max_amount_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

                if ($provider_setting_data) {
                    $min_amount_usd = $provider_setting_data['minAmount'];
                    $max_amount_usd = $provider_setting_data['maxAmount'];

                    $db->where('symbol', $supported_currencies_arr, 'IN');
                    $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies', null, 'id, symbol, currency_id');

                    foreach ($supported_currencies_arr as $cryptocurrency_value) {
                        $tx_wallet_type = $marketplace_currencies[strtolower($cryptocurrency_value)]['currency_id'];

                        foreach ($supported_fiat_currency_arr as $fiat_currency_value) {

                            $min_amount = $xunCurrency->get_conversion_amount(strtolower($fiat_currency_value), 'usd', $min_amount_usd);
                            $max_amount = $xunCurrency->get_conversion_amount(strtolower($fiat_currency_value), 'usd', $max_amount_usd);

                            $min_crypto_amount = $xunCurrency->get_conversion_amount($tx_wallet_type, strtolower($fiat_currency_value), $min_amount);
                            $max_crypto_amount = $xunCurrency->get_conversion_amount($tx_wallet_type, strtolower($fiat_currency_value), $max_amount);

                            $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['min_amount'] = $min_amount;
                            $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['max_amount'] = $max_amount;

                            $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['min_crypto_amount'] = $min_crypto_amount;
                            $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['max_crypto_amount'] = $max_crypto_amount;
                            
                            $exchange_rate_params = array(
                                "product_currency" => strtolower($fiat_currency_value),
                                "system_currency" => strtolower($tx_wallet_type),
                            );

                            $exchange_rate_return = $xunPay->get_product_exchange_rate($exchange_rate_params);
                            $exchange_rate_arr = $exchange_rate_return["exchange_rate_arr"];
                            $exchange_rate = $exchange_rate_arr[strtolower($tx_wallet_type) . "/" . strtolower($fiat_currency_value)];
                            $fiat_exchange_rate = bcdiv(1, $exchange_rate, 18);
                    
                            $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['crypto_converted_amount'] = $exchange_rate;
                            $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['fiat_converted_amount'] = $fiat_exchange_rate;
   
                        }
                    }
                }
            } else if ($provider == 'Xanpool') {
                $db->where('provider_id', $provider_id);
                $db->where('name', array('supportedCurrencies', 'fiatCurrencyList'), 'IN');
                $global_provider_setting_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

                $db->where('provider_id', $provider_id);
                $db->where('name', 'minCryptoAmount');
                $min_crypto_amount_data = $db->map('type')->ArrayBuilder()->get('provider_setting', null, 'name, value, type');

                $supported_currencies = strtoupper($global_provider_setting_data['supportedCurrencies']);
                $supported_fiat_list = strtoupper($global_provider_setting_data['fiatCurrencyList']);
                $supported_currencies_arr = explode(",", $supported_currencies);
                $supported_fiat_currency_arr = explode(",", $supported_fiat_list);

                $api_url = $config['xanpool_api_url'] . '/api/prices?currencies=' . strtoupper($supported_fiat_list) . '&cryptoCurrencies=' . $supported_currencies . '&type=buy';

                $curl_params = array();

                $result = $post->curl_xanpool($api_url, $curl_params, 'GET');

                $method_api_url = $config['xanpool_api_url'] . '/api/methods';

                $payment_method_result = $post->curl_xanpool($method_api_url, $curl_params, 'GET');

                $buy_data =  $payment_method_result['buy'];

                // print_r($supported_currencies_arr);
                $db->where('symbol', $supported_currencies_arr, 'IN');
                $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies', null, 'id, symbol, currency_id');
                foreach ($result as $result_key => $result_value) {

                    $fiat_currency = $result_value['currency'];
                    $cryptocurrency_symbol = $result_value['cryptoCurrency'];
                    $selected_wallet_type = $marketplace_currencies[$cryptocurrency];
                    $exchange_rate = $result_value['cryptoPrice'];
                    $usd_exchange_rate = $result['cryptoPriceUsd'];

                    if ($fiat_currency == $uc_fiat_currency_id && $cryptocurrency_symbol == $uc_currency_unit) {
                        $crypto_price_usd = $result_value['cryptoPriceUsd'];
                        $fiat_crypto_price = $result_value['cryptoPrice'];

                        $converted_amount = $fiat_crypto_price;
                        //The Fiat amount is too small for certain currency
                        $fiat_converted_amount = bcdiv($amount, $fiat_crypto_price, 18);
                    }

                    foreach ($buy_data as $key => $value) {
                        $method_arr = $value['methods'];

                        if ($value['currency'] == $fiat_currency) {

                            $min_amount = $method_arr[0]['min'];
                            $max_amount = $method_arr[0]['max'];

                            $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['min_amount'] = $method_arr[0]['min'];
                            $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['max_amount'] = $method_arr[0]['max'];
                        }
                    }

                    $min_crypto_amount = $provider_setting_data['minCryptoAmount'];
                    $max_crypto_amount = bcdiv($max_amount, $exchange_rate, 8);

                    $fiat_exchange_rate = bcdiv(1, $exchange_rate, 18);

                    $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['min_crypto_amount'] = $min_crypto_amount_data[$cryptocurrency_symbol]['value'];
                    $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['max_crypto_amount'] = bcdiv($max_amount, $exchange_rate, 8);
                    $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['crypto_converted_amount'] = $exchange_rate;
                    $currency_setting_data[$cryptocurrency_symbol][$fiat_currency]['fiat_converted_amount'] = $fiat_exchange_rate;

                }
            }
        }


        $data['address'] = $address;
        $data['wallet_type'] = $wallet_type;
        $data['amount'] = $converted_amount;
        $data['currency_unit'] = $currency_unit;
        $data['markup_amount'] = $markupAmount;
        $data['debug']['xanPoolReturn'] = $result;
        $data['debug']['providerData'] = $provider_data;
        $data['debug']['currency_setting_data'] = $currency_setting_data;
        $data['debug']['xanPoolRequest'] = $api_url;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Get Payment Method Address", "data" => $data);
    }

    private function generate_new_pg_address($params)
    {
        $db = $this->db;
        $post = $this->post;

        $business_id = $params['business_id'];
        $wallet_type = $params['wallet_type'];

        $db->where('business_id', $business_id);
        $db->where('type', $wallet_type);
        $db->where('status', 1);
        $crypto_wallet = $db->getOne('xun_crypto_wallet');

        if (!$crypto_wallet) {
            $insertWallet = array(
                "business_id" => $business_id,
                "type" => $wallet_type,
                "status" => 1,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            );

            $wallet_id = $db->insert('xun_crypto_wallet', $insertWallet);
            if (!$wallet_id) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "error_message" => $db->getLastError());
            }
        } else {
            $wallet_id = $crypto_wallet["id"];
        }


        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user', 'nickname');
        $crypto_params["type"] = $wallet_type;
        $crypto_params['businessID'] = $business_id;
        $crypto_params['businessName'] = $xun_user['nickname'];

        $crypto_results = $post->curl_crypto("getNewAddress", $crypto_params);

        if ($crypto_results["code"] != 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_results["message"]);
        }

        $address = $crypto_results["data"]["address"];

        if (!$address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00151') /*Address not generated.*/);
        }

        $insert_data = array(
            "wallet_id" => $wallet_id,
            "crypto_address" => $address,
            "type" => "in",
            "status" => 1,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $address_id = $db->insert("xun_crypto_address", $insert_data);
        if (!$address_id) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/,
                "error_message" => $db->getLastError()
            );
        }

        $data['address'] = $address;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Generate New Address Successful.", "data" => $data, 'lq' => $db->getLastQuery());
    }


    public function get_conversion_amount($params)
    {
        global $xunMarketplace, $xunCurrency, $xunPay;
        $db = $this->db;

        $amount = $params["amount"];
        $wallet_type = $params["from_wallet_type"];
        $to_wallet_type = $params["to_wallet_type"];
        $conversion_type = $params['conversion_type'] ? $params['conversion_type'] : 'cryptocurrency';

        if ($amount == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00417') /*Amount cannot be empty*/, "developer_msg" => "Amount cannot be empty");
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "From Wallet Type cannot be empty");
        }

        if ($to_wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00635') /*To Wallet Type cannot be empty*/, "developer_msg" => "To Wallet Type cannot be empty");
        }


        if ($conversion_type == 'cryptocurrency') {
            $db->where('currency_id', $wallet_type);
            $xun_coins = $db->getOne('xun_coins', 'id');

            if (!$xun_coins) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00633') /*Invalid From Wallet Type*/, "developer_msg" => "Invalid From Wallet Type");
            }

            $db->where('currency_id', $to_wallet_type);
            $xun_coins1 = $db->getOne('xun_coins', 'id');

            if (!$xun_coins1) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00420') /*Invalid To Wallet Type*/, "developer_msg" => "Invalid To Wallet Type.");
            }
        }

        $currency_info = $xunCurrency->get_currency_info($wallet_type);

        $currency_unit = $currency_info["display_symbol"];
        $uc_currency_unit = strtoupper($currency_unit);

        $to_currency_info = $xunCurrency->get_currency_info($to_wallet_type);

        $to_currency_unit = $to_currency_info["display_symbol"];
        $to_uc_currency_unit = strtoupper($to_currency_unit);

        $converted_amount = $xunCurrency->get_conversion_amount($to_wallet_type, $wallet_type, $amount);

        $data = array(
            "converted_amount" => $converted_amount,
            "from_wallet_type"  => $wallet_type,
            "from_symbol" => $uc_currency_unit,
            "to_wallet_type" => $to_wallet_type,
            "to_symbol" => $to_uc_currency_unit,
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00229') /*Cryptocurrency Conversion Rate*/, "data" => $data);
    }

    public function get_wallet_address_list($params, $source)
    {
        global $xunCrypto;
        $db = $this->db;

        $business_id = $params['business_id'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where('id', $business_id);
        $db->where('register_site', $source);
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/, 'source' => $source);
        }

        $db->where('user_id', $business_id);
        $db->where('address_type', 'nuxpay_wallet');
        $db->where('active', 1);
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        if (!$crypto_user_address) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => 'Internal Address not found.');
        }

        $internal_address = $crypto_user_address['address'];

        $db->where('is_payment_gateway', 1);
        $xun_coins = $db->get('xun_coins', null, 'id, currency_id');

        if ($xun_coins) {

            $wallet_type_list = array_column($xun_coins, 'currency_id');

            $db->where('internal_address', $internal_address);
            $db->where('wallet_type', $wallet_type_list, 'IN');
            $external_address_list = $db->map('wallet_type')->ArrayBuilder()->get('xun_crypto_external_address', null, "wallet_type, external_address");

            foreach ($wallet_type_list as $v) {

                if (!$external_address_list[$v]) {
                    $external_address = $xunCrypto->get_external_address($internal_address, $v);
                    if ($external_address['code'] != 0) {
                        $address_list[$v][] = $external_address;
                    }
                } else {
                    $address_list[$v] = $external_address_list[$v];
                }
            }
        }

        $data['wallet_address_list'] = $address_list;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00373') /*Wallet Address Listing*/, "data" => $data);
    }


    public function nuxpay_validate_address($params)
    {
        global $xunCrypto;
        $db = $this->db;

        $business_id = $params['business_id'];
        $address = $params['address'];
        $wallet_type = $params['wallet_type'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($address == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00153') /*Destination address cannot be empty*/,  "developer_msg" => "Destination Address cannot be empty");
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "Wallet Type cannot be empty");
        }

        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/, 'source' => $source);
        }

        $validate_destination_address_result = $xunCrypto->crypto_validate_address($address, $wallet_type, "external");

        if ($validate_destination_address_result["code"] == 1) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid address.");
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00374') /*Address is valid.*/);
    }

    public function get_receipt_details($params)
    {
        global $xunCurrency;
        $db = $this->db;

        $transaction_token = $params['transaction_token'];

        if ($transaction_token == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00414') /*Transaction token cannot be empty*/);
        }

        $db->where('transaction_token', $transaction_token);
        $payment_tx_data = $db->getOne('xun_payment_transaction');

        if (!$payment_tx_data) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00636') /*Invalid Payment Transaction.*/);
        }

        $payment_tx_id = $payment_tx_data['id'];
        $business_id = $payment_tx_data['business_id'];

        $db->where('id', $business_id);
        $merchant_data = $db->getOne('xun_user', 'nickname, username, email');

        if (!$merchant_data) {
            return array("code" => 0, 'message' => "FAILED", "message_d" => $this->get_translation_message('E00157') /*Invalid user*/);
        }

        $merchant_name = $merchant_data['nickname'];
        $merchant_phone_number = $merchant_data['username'];
        $merchant_email = $merchant_data['email'];

        $db->where('status', 'failed', '!=');
        $db->where('payment_tx_id', $payment_tx_id);
        $payment_details = $db->get('xun_payment_details');

        if ($payment_details) {
            foreach ($payment_details as $key => $value) {
                $amount = number_format($value['amount'], 8);
                // $amount = $value['amount'];
                $wallet_type = $value['wallet_type'];
                $status = $value['status'];
                $payment_id = $value['payment_id'];
                $created_at = $value['created_at'];

                $db->where('id', $value['fund_in_id']);
                $fund_in_data = $db->getOne($db->escape($value['fund_in_table']), 'id, sender_address, recipient_address, transaction_id');

                $transaction_hash = $fund_in_data['transaction_id'];
                $currencyDetails = $xunCurrency->marketplaceCurrencies[$wallet_type];
                $sender_address = $fund_in_data['sender_address'];
                $recipient_address = $fund_in_data['recipient_address'];

                $display_symbol = strtoupper($currencyDetails['display_symbol']);
                $image = ($currencyDetails['image']);

                $payment_list[$wallet_type][] = array(
                    'amount' => $amount,
                    'wallet_type' => $wallet_type,
                    'symbol' => $display_symbol,
                    'image'  => $image,
                    'payment_id' => $payment_id,
                    'transaction_date' => $created_at,
                    'transaction_hash' => $transaction_hash,
                    'sender_address' => $sender_address,
                    'recipient_address' => $recipient_address,
                );
            }
        } else {
            $payment_list = [];
        }

        $data['payment_data_list'] = $payment_list;
        $data['merchant_name'] = $merchant_name ? $merchant_name : '-';
        $data['merchant_phone_number'] = $merchant_phone_number ? $merchant_phone_number : '-';
        $data['merchant_email_address'] = $merchant_email ? $merchant_email : '-';

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00375') /*Get Receipt Successful.*/, 'data' => $data);
    }

    public function get_offset_balance($address, $wallet_type)
    {
        $db = $this->db;

        $db->where('address', $address);
        $db->where('wallet_type', $wallet_type);
        $db->where('disabled', 0);
        $offset_amount = $db->getValue('xun_crypto_wallet_offset', 'amount');

        return $offset_amount ? $offset_amount : '0';
    }

    public function get_buy_crypto_supported_currency($params)
    {
        global $config;
        $db = $this->db;
        $post = $this->post;

        $provider = strtolower($params['provider']);
        $transaction_type = $params['transaction_type'] ? $params['transaction_type'] : 'buy'; //sell/buy type

        if ($provider == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Provider cannot be empty.");
        }

        $db->where('name', $provider);
        $provider_id = $db->getValue('provider', 'id');

        if (!$provider_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Provider not found.");
        }



        // $db->where('a.currency_code', $fiat_currency_list, 'IN');
        // $country_data = $db->get('country a', null, 'name, currency_code, image_url');

        if ($provider == 'xanpool') {
            $fiat_currency_list = array();
            $method_api_url = $config['xanpool_api_url'] . '/api/methods';

            $payment_method_result = $post->curl_xanpool($method_api_url, $curl_params, 'GET');

            if ($transaction_type == 'buy') {
                $buy_data =  $payment_method_result['buy'];

                foreach ($buy_data as $key => $value) {
                    $fiat_currency_list[] = $value['currency'];
                }
            } else if ($transaction_type == 'sell') {
                $sell_data =  $payment_method_result['sell'];

                foreach ($sell_data as $key => $value) {
                    $fiat_currency_list[] = $value['currency'];
                }
            }
        } else {
            $db->where('provider_id', $provider_id);
            $db->where('name', 'fiatCurrencyList');
            $provider_setting_data = $db->getValue('provider_setting', 'value');

            $fiat_currency_list = explode(",", $provider_setting_data);
        }

        $db->where('currency_id', $fiat_currency_list, 'IN');
        $db->orderBy('name', 'ASC');
        $marketplace_currencies = $db->get('xun_marketplace_currencies', null, 'name, currency_id, image');

        $data['country_list'] = $marketplace_currencies;

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00375') /*Get Receipt Successful.*/, 'data' => $data);
    }

    public function get_buy_crypto_history($params, $business_id)
    {
        $db = $this->db;
        $setting = $this->setting;

        $status = $params['status'];
        $date_from = $params['date_from'];
        $date_to = $params['date_to'];
        $symbol = $params['symbol'];
        $transaction_type = $params['transaction_type'];

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"] ? $params["page"] : 1;
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";

        $start_limit = ($page_number - 1) * $page_size;
        $limit = array($start_limit, $page_size);

        if ($status) {
            $db->where('a.status', $status);
        }

        if ($date_from) {
            $date_from = date("Y-m-d H:i:s", $date_from);
            $db->where("a.created_at", $date_from, ">=");
        }

        if ($date_to) {
            $date_to = date("Y-m-d H:i:s", $date_to);
            $db->where("a.created_at", $date_to, "<=");
        }

        if ($symbol) {
            $db->where('c.symbol', $symbol);
        }

        if ($transaction_type) {
            $db->where('a.type', $transaction_type);
        }

        $db->where('a.business_id', $business_id);

        $db->join('xun_marketplace_currencies c', 'a.wallet_type = c.currency_id', 'LEFT');
        $db->join('provider b', 'a.provider_id = b.id', 'LEFT');
        $db->orderBy('a.id', 'DESC');
        $copyDb = $db->copy();
        $crypto_payment_list = $db->get('xun_crypto_payment_transaction a', $limit, 'a.fiat_amount as payment_amount, a.fiat_currency as payment_currency, a.crypto_amount, a.wallet_type, a.type, c.symbol, b.company as provider_name, a.status, a.created_at');

        $totalRecord = $copyDb->getValue('xun_crypto_payment_transaction a', 'count(a.id)');

        foreach ($crypto_payment_list as $key => $value) {
            $payment_amount = $value['payment_amount'];
            $payment_amount = bcmul($payment_amount, "1", 2);

            $crypto_payment_list[$key]['payment_amount'] = $payment_amount;
        }

        $numRecord = count($crypto_payment_list);
        $returnData["buy_crypto_list"] = $crypto_payment_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $numRecord;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        $returnData["pageNumber"] = $page_number;

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00377') /*Get Buy Crypto History Successful.*/, 'data' => $returnData);
    }

    public function get_buy_crypto_setting($params, $user_id)
    {
        global $config;

        $db = $this->db;
        $general = $this->general;
        $post = $this->post;

        $provider_arr = $params['provider'];
        $type = $params['type'] ? $params['type'] : 'buy';
        $transactionToken = $params['transactionToken'];

        $db->where('b.name', $provider_arr, 'IN');
        $db->where('a.name', 'isEnabled');
        $db->join('provider b', 'a.provider_id = b.id', 'LEFT');
        $provider_setting_data = $db->get('provider_setting a', null, 'b.name as provider_name, a.id, a.name, a.value');

        foreach ($provider_setting_data as $key => $value) {
            $setting_value = $value['value'];
            $provider_name = $value['provider_name'];
            $setting = $value['name'];

            $setting_data[$provider_name][$setting] = $setting_value;
        }

        $db->where('b.name', $provider_arr, 'IN');
        $db->where('a.name', 'defaultCurrency');
        $db->join('provider b', 'a.provider_id = b.id', 'LEFT');
        $provider_setting_data = $db->get('provider_setting a', null, 'b.name as provider_name, a.id, a.name, a.value');

        foreach ($provider_setting_data as $key => $value) {
            $setting_value = $value['value'];
            $provider_name = $value['provider_name'];
            $setting = $value['name'];

            $setting_data[$provider_name][$setting] = $setting_value;
        }

        $db->where('a.id', $user_id);
        $db->join('xun_business b', 'a.id = b.user_id', 'LEFT');
        $xun_user = $db->getOne('xun_user a', 'a.id, b.country, a.username');

        $user_selected_country = $xun_user['country'];
        $phone_number = $xun_user['username'];

        $db->where('name', $provider_arr, 'IN');
        $provider_data  = $db->get('provider', null, 'id, company, name');

        $provider_id_arr = array_column($provider_data, "id");
        $db->where('name', 'fiatCurrencyList');
        $db->where('provider_id', $provider_id_arr, 'IN');
        $provider_setting_data = $db->map('provider_id')->ArrayBuilder()->get('provider_setting', null, 'provider_id, name, value');

        foreach ($provider_data as $key => $value) {
            $provider_id = $value['id'];
            $provider_name = $value['name'];

            $fiat_currency_data = [];
            $fiat_currency_list = [];
            if ($provider_name == 'xanpool') {
                $method_api_url = $config['xanpool_api_url'] . '/api/methods';

                $payment_method_result = $post->curl_xanpool($method_api_url, $curl_params, 'GET');

                $payment_method_data =  $payment_method_result[$type];

                foreach ($payment_method_data as $pm_key => $pm_value) {

                    $fiat_currency_list[] = strtolower($pm_value['currency']);
                    $fiat_currency_data = implode(",", $fiat_currency_list);
                }
            } else {
                $fiat_currency_data = $provider_setting_data[$provider_id]['value'];
                $fiat_currency_list = explode(",", $fiat_currency_data);
            }

            if ($transactionToken == '') {
                if ($user_selected_country) {
                    $db->where('name', $user_selected_country);
                    $country_data = $db->getOne('country', 'id, name, currency_code');

                    if ($country_data) {
                        $country_currency_code = strtolower($country_data['currency_code']);

                        if (in_array($country_currency_code, $fiat_currency_list)) {
                            $selected_currency = $country_currency_code;
                        } else if (in_array('usd', $fiat_currency_list)) {
                            $selected_currency = 'usd';
                        } else {
                            $selected_currency = 'myr';
                        }
                    } else if (in_array('usd', $fiat_currency_list)) {
                        $selected_currency = 'usd';
                    } else {
                        $selected_currency = 'myr';
                    }
                } else if ($phone_number) {
                    $mobileNumberInfo = $general->mobileNumberInfo($phone_number, null);

                    if ($mobileNumberInfo["isValid"] == 0) {
                        $selected_currency = 'myr';
                    } else {

                        if (in_array('usd', $fiat_currency_list)) {
                            $selected_currency = 'usd';
                        }
                        $country_code = $mobileNumberInfo['countryCode'];
                        $db->where('country_code', $country_code);
                        $country_data = $db->getOne('country', 'id, name, currency_code');

                        if ($country_data) {
                            $country_currency_code = strtolower($country_data['currency_code']);
                            if (in_array($country_currency_code, $fiat_currency_list)) {
                                $selected_currency = $country_currency_code;
                            } else if (in_array('usd', $fiat_currency_list)) {
                                $selected_currency = 'usd';
                            } else {
                                $selected_currency = 'myr';
                            }
                        } else if (in_array('usd', $fiat_currency_list)) {
                            $selected_currency = 'usd';
                        } else {
                            $selected_currency = 'myr';
                        }
                    }
                } else if (in_array('usd', $fiat_currency_list)) {
                    $selected_currency = 'usd';
                } else {
                    $selected_currency = 'myr';
                }
            } else {
                $db->where('transaction_token', $transactionToken);
                $country_data = $db->getOne('xun_crypto_payment_request');
                $selected_currency = $country_data['fiat_currency'];
            }


            $setting_data[$provider_name]['default_currency'] = strtolower($selected_currency);
        }


        $data['default_currency'] = strtolower($selected_currency);

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00377') /*Get Buy Crypto History Successful.*/, 'data' => $data, 'setting_data' => $setting_data);
    }


    public function get_provider_status($params)
    {
        $db = $this->db;

        $walletType = $params['walletType'];

        $db->where('currency_id', $walletType);
        $walletSymbol = $db->getValue('xun_marketplace_currencies', 'symbol');

        $db->where('company', 'Simplex');
        $simplexId = $db->getValue('provider', 'id');

        $db->where('provider_id', $simplexId);
        $db->where('name', 'supportedCurrencies');
        $simplexValue = $db->getValue('provider_setting', 'value');

        $simplexCoinList = explode(",", $simplexValue);


        if (in_array($walletSymbol, $simplexCoinList)) {
            $simplex = "1";
        } else {
            $simplex = "0";
        }


        $db->where('company', 'Xanpool');
        $xampoolId = $db->getValue('provider', 'id');

        $db->where('provider_id', $xampoolId);
        $db->where('name', 'supportedCurrencies');
        $xanpoolValue = $db->getValue('provider_setting', 'value');

        $xanpoolCoinList = explode(",", $xanpoolValue);

        if (in_array($walletSymbol, $xanpoolCoinList)) {
            $xanpool = "1";
        } else {
            $xanpool = "0";
        }

        $result['simplex'] = $simplex;
        $result['xanpool'] = $xanpool;

        return array("status" => "ok", "message" => "SUCCESS", "message_d" => "success", "code" => 1, "result" => $result);
    }

    public function create_crypto_payment_request($params, $source = "")
    {
        global $xunCurrency, $simplex, $xanpool, $config;
        $db = $this->db;
        $xunCrypto = $this->xunCrypto;
        $general = $this->general;

        $business_id = trim($params['account_id']);
        $end_user_id = trim($params['end_user_id']);
        $api_key = trim($params['api_key']);
        $crypto_amount = trim($params['crypto_amount']) ? trim($params['crypto_amount']) : '';
        $fiat_amount = trim($params['fiat_amount']) ? trim($params['fiat_amount']) : '';
        $fiat_currency = trim($params['fiat_currency']);
        $wallet_type = trim($params['wallet_type']);
        $type = trim($params['type']); //buy /sell
        $destination_address = trim($params['destination_address']);
        $provider = trim($params['provider']);
        $reference_id = $params['reference_id'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00641') /*Account ID cannot be empty*/);
        }

        // ! TEMPORARY SOLUTION
        if ($end_user_id == '') {
            $end_user_id = time();
        }

        if ($api_key == '' && $source != 'merchant') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00086') /*API Key cannot be empty*/);
        }

        if ($source != "merchant") {
            $crypto_api_key_validation = $xunCrypto->validate_crypto_api_key($api_key, $business_id);

            if (isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0) {
                return $crypto_api_key_validation;
            }
        }

        // if($provider == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Provider cannot be empty.");
        // }

        // if($crypto_symbol == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00645') /* Symbol cannot be empty.*/);
        // }

        if ($fiat_currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00418') /*Fiat Currency ID cannot be empty*/, "developer_msg" => "Fiat Currency ID cannot be empty");
        }

        if ($type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Type cannot be empty.", "developer_msg" => "Type cannot be empty.");
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "Wallet Type cannot be empty");
        }

        if ($type == 'sell' && $provider == 'simplex') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Sell Crypto is not available for Simplex.");
        }

        if ($destination_address == '' && $type == 'buy') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Destination address cannot be empty.");
        }

        if ($reference_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00409') /*Reference Id cannot be empty*/);
        }

        if ($provider) {
            $db->where('name', $provider);
            $provider_data = $db->getOne('provider', 'id, company, name');

            if (!$provider_data) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Provider not found.");
            }
        }

        if ($destination_address) {
            $validate_destination_address_result = $xunCrypto->crypto_validate_address($destination_address, $wallet_type, "external");

            if ($validate_destination_address_result["code"] == 1) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $validate_destination_address_result['statusMsg']);
            }
        }

        // check if user need to be charged when requesting buy/sell crypto
        $db->where('user_id', $business_id);
        $bypassService = $db->getValue('xun_business', 'bypass_buysell_service_charge');
        $pg_address = '';   // default is empty if this user is bypassed
        if (!$bypassService) {
            // check if destination_address is a pg address or nuxpay address
            // generate new merchant request for service charge
            $db->where('crypto_address', $destination_address);     // pg address
            $walletID = $db->getValue('xun_crypto_address', 'wallet_id');

            $db->where('external_address', $destination_address);   // nuxpay personal wallet
            $personalWalletID = $db->getValue('xun_crypto_external_address', 'id');

            $db->where('address', $destination_address);    // auto fundout address
            $autoFundOutID = $db->getValue('blockchain_external_address', 'id');

            if (!$walletID && !$personalWalletID && !$autoFundOutID) {
                $merchant_request_params = array(
                    'api_key' => $api_key,
                    'account_id' => $business_id,
                    'currency' => $wallet_type,
                    'amount' => ($fiat_amount == '') ? 0 : $fiat_amount,
                    'fiat_currency_id' => $fiat_currency,
                    'reference_id' => $reference_id,
                    'redirect_url' => 'https://www.nuxpay.io/',
                    'payment_type' => 'payment_gateway',
                );
                $merchant_request_result = $this->merchant_request_transaction($merchant_request_params, $source, 'payment_gateway', false, null);
                if ($merchant_request_result['code'] == 0 || !$merchant_request_result) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => "Failed to generate pg_address.");
                }
                $pg_address = $merchant_request_result['data']['address'];
            }
        }

        $provider_id = $provider_data['id'] ? $provider_data['id'] : '';

        $currency_info = $xunCurrency->get_currency_info($wallet_type);

        if (!$currency_info) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00420') /*Invalid Wallet Type*/, "developer_msg" => "Invalid Wallet Type.");
        }

        $crypto_symbol = $currency_info['symbol'];

        while (true) {
            $transaction_token = $general->generateAlpaNumeric(16);

            $db->where("transaction_token", $transaction_token);
            $crypto_tx = $db->getOne("xun_crypto_payment_request", "id");
            if (!$crypto_tx) {
                break;
            }
        }

        $insertData = array(
            "business_id" => $business_id,
            "end_user_id" => $end_user_id,
            "transaction_token" => $transaction_token,
            "crypto_amount" => $crypto_amount,
            "fiat_amount" => $fiat_amount,
            "fiat_currency" => $fiat_currency,
            "wallet_type" => $wallet_type,
            "destination_address" => $destination_address,
            "pg_address" => $pg_address,
            "type" => $type,
            "status" => "pending",
            "reference_id" => $reference_id ? $reference_id : '',
            "provider_id" => $provider_id,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $row_id = $db->insert('xun_crypto_payment_request', $insertData);

        if (!$row_id) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        if ($type == 'buy') {
            $redirect_url = $config['nuxpayUrl'] . "/buyCryptoRequest.php?transactionToken=" . $transaction_token . "&destinationAddress=" . $destination_address;
        } else if ($type == 'sell') {
            $redirect_url = $config['nuxpayUrl'] . "/sellCryptoRequest.php?transactionToken=" . $transaction_token;
        }

        $data['transaction_token'] = $transaction_token;
        $data['redirect_url'] = $redirect_url;

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00377') /*Get Buy Crypto History Successful.*/, 'data' => $data);
    }

    public function get_buy_sell_conversion_rate($params)
    {
        global $config, $xunCurrency, $xunPay;
        $db = $this->db;
        $post = $this->post;

        $provider = strtolower($params['provider']);
        $wallet_type = strtolower($params['wallet_type']);
        // $crypto_symbol = strtoupper($params['crypto_symbol']);
        $fiat_currency_id = strtoupper($params['fiat_currency']);
        $type = $params['type'];

        // $crypto_api_key_validation = $xunCrypto->validate_crypto_api_key($api_key, $business_id);

        // if(isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0){
        //     return $crypto_api_key_validation;
        // }

        if ($provider == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Provider cannot be empty.");
        }

        // if($crypto_symbol == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00645') /* Symbol cannot be empty.*/);
        // }

        if ($fiat_currency_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00418') /*Fiat Currency ID cannot be empty*/, "developer_msg" => "Fiat Currency ID cannot be empty");
        }

        if ($type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Type cannot be empty.", "developer_msg" => "Type cannot be empty.");
        }

        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00419') /*Wallet Type cannot be empty*/, "developer_msg" => "Wallet Type cannot be empty");
        }

        if ($type == 'sell' && $provider == 'simplex') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Sell Crypto is not available for Simplex.");
        }

        $db->where('name', $provider);
        $provider_data = $db->getOne('provider');

        if (!$provider_data) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Provider not found.");
        }

        $currency_info = $xunCurrency->get_currency_info($wallet_type);

        if (!$currency_info) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00420') /*Invalid Wallet Type*/, "developer_msg" => "Invalid Wallet Type.");
        }

        $crypto_symbol = strtoupper($currency_info["symbol"]);

        $uc_fiat_currency_id = strtolower($fiat_currency_id);
        $exchange_rate_params = array(
            "product_currency" => $uc_fiat_currency_id,
            "system_currency" => $wallet_type,
        );

        $exchange_rate_return = $xunPay->get_product_exchange_rate($exchange_rate_params);
        $exchange_rate_arr = $exchange_rate_return["exchange_rate_arr"];
        $exchange_rate = $exchange_rate_arr[$wallet_type . "/" . $uc_fiat_currency_id];

        $provider_id = $provider_data['id'];
        if ($provider_id) {
            //GET Min and Max Amount in USD
            $db->where('provider_id', $provider_id);
            $db->where('name', array('minAmount', 'maxAmount', 'minCryptoAmount'), 'IN');
            $db->where('type', $crypto_symbol);
            $provider_setting_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

            $db->where('provider_id', $provider_id);
            $db->where('name', array('supportedCurrencies', 'fiatCurrencyList'), 'IN');
            $global_provider_setting_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

            if ($provider_setting_data) {
                $min_amount_usd = $provider_setting_data['minAmount'];
                $max_amount_usd = $provider_setting_data['maxAmount'];

                $min_amount = $xunCurrency->get_conversion_amount($uc_fiat_currency_id, 'usd', $min_amount_usd);
                $max_amount = $xunCurrency->get_conversion_amount($uc_fiat_currency_id, 'usd', $max_amount_usd);

                $min_crypto_amount = bcdiv($min_amount, $exchange_rate, 8);
                $max_crypto_amount = bcdiv($max_amount, $exchange_rate, 8);
            }
        }

        if ($provider  == 'simplex') {

            $db->where('provider_id', $provider_id);
            $db->where('name', array('supportedCurrencies', 'fiatCurrencyList'), 'IN');
            $global_provider_setting_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

            $simplex_margin_percentage = $setting->systemSetting['simplexMarginPercentage'];

            // $markup_converted_amount = bcmul($converted_amount, (string)((100 + $simplex_margin_percentage) / 100), 8);
            $fiat_converted_amount = bcmul($fiat_converted_amount, (string)((100 + $simplex_margin_percentage) / 100), 8);
            $supported_currencies = strtoupper($global_provider_setting_data['supportedCurrencies']);
            $supported_fiat_list = strtoupper($global_provider_setting_data['fiatCurrencyList']);
            $supported_currencies_arr = explode(",", $supported_currencies);
            $supported_fiat_currency_arr = explode(",", $supported_fiat_list);

            $crypto_rate_arr = $xunCurrency->get_cryptocurrency_rate(array($wallet_type));

            $crypto_price_usd = $crypto_rate_arr[$wallet_type];

            $db->where('provider_id', $provider_id);
            $db->where('name', array('minAmount', 'maxAmount'), 'IN');
            $db->orderBy('type', 'ASC');
            $min_max_amount_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

            if ($provider_setting_data) {
                $min_amount_usd = $provider_setting_data['minAmount'];
                $max_amount_usd = $provider_setting_data['maxAmount'];

                $db->where('symbol', $supported_currencies_arr, 'IN');
                $marketplace_currencies = $db->map('symbol')->ArrayBuilder()->get('xun_marketplace_currencies', null, 'id, symbol, currency_id');

                // foreach($supported_currencies_arr as $cryptocurrency_value){
                $wallet_type = $marketplace_currencies[strtolower($crypto_symbol)]['currency_id'];

                // foreach($supported_fiat_currency_arr as $fiat_currency_value){

                $min_amount = $xunCurrency->get_conversion_amount(strtolower($fiat_currency_id), 'usd', $min_amount_usd);
                $max_amount = $xunCurrency->get_conversion_amount(strtolower($fiat_currency_id), 'usd', $max_amount_usd);

                $min_crypto_amount = $xunCurrency->get_conversion_amount($wallet_type, strtolower($fiat_currency_id), $min_amount);
                $max_crypto_amount = $xunCurrency->get_conversion_amount($wallet_type, strtolower($fiat_currency_id), $max_amount);

                $currency_setting_data[$cryptocurrency_value][$fiat_currency_id]['min_amount'] = $min_amount;
                $currency_setting_data[$cryptocurrency_value][$fiat_currency_id]['max_amount'] = $max_amount;

                $currency_setting_data[$cryptocurrency_value][$fiat_currency_id]['min_crypto_amount'] = $min_crypto_amount;
                $currency_setting_data[$cryptocurrency_value][$fiat_currency_id]['max_crypto_amount'] = $max_crypto_amount;

                $exchange_rate_params = array(
                    "product_currency" => strtolower($fiat_currency_id),
                    "system_currency" => strtolower($wallet_type),
                );

                $exchange_rate_return = $xunPay->get_product_exchange_rate($exchange_rate_params);
                $exchange_rate_arr = $exchange_rate_return["exchange_rate_arr"];
                $exchange_rate = $exchange_rate_arr[strtolower($wallet_type) . "/" . strtolower($fiat_currency_id)];

                $fiat_exchange_rate = bcdiv(1, $exchange_rate, 18);

                // $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['crypto_converted_amount'] = $exchange_rate;
                // $currency_setting_data[$cryptocurrency_value][$fiat_currency_value]['fiat_converted_amount'] = $fiat_exchange_rate;

                $crypto_converted_amount = (string) $exchange_rate;
                $fiat_converted_amount = (string) $fiat_exchange_rate;
                // }
                // }

            }
        } else if ($provider == 'xanpool') {

            $db->where('provider_id', $provider_id);
            $db->where('name', 'minCryptoAmount');
            $min_crypto_amount_data = $db->map('type')->ArrayBuilder()->get('provider_setting', null, 'name, value, type');

            $api_url = $config['xanpool_api_url'] . '/api/prices?currencies=' . $fiat_currency_id . '&cryptoCurrencies=' . $crypto_symbol . '&type=' . $type;


            $curl_params = array();

            $result = $post->curl_xanpool($api_url, $curl_params, 'GET');

            if (!$result) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Currency not supported.");
            }

            $method_api_url = $config['xanpool_api_url'] . '/api/methods';

            $payment_method_result = $post->curl_xanpool($method_api_url, $curl_params, 'GET');

            $payment_method_data =  $payment_method_result[$type];

            foreach ($result as $result_key => $result_value) {
                $fiat_currency = $result_value['currency'];
                $cryptocurrency_symbol = $result_value['cryptoCurrency'];
                $selected_wallet_type = $marketplace_currencies[$cryptocurrency];
                $exchange_rate = $result_value['cryptoPrice'];
                $usd_exchange_rate = $result['cryptoPriceUsd'];

                if ($fiat_currency == $uc_fiat_currency_id && $cryptocurrency_symbol == $uc_currency_unit) {
                    $crypto_price_usd = $result_value['cryptoPriceUsd'];
                    $fiat_crypto_price = $result_value['cryptoPrice'];

                    $converted_amount = $fiat_crypto_price;
                    //The Fiat amount is too small for certain currency
                    $fiat_converted_amount = bcdiv($amount, $fiat_crypto_price, 18);
                }

                foreach ($payment_method_data as $key => $value) {
                    $method_arr = $value['methods'];

                    if ($value['currency'] == $fiat_currency) {

                        $min_amount = (string) $method_arr[0]['min'];
                        $max_amount = (string) $method_arr[0]['max'];
                    }
                }

                $min_crypto_amount = $provider_setting_data['minCryptoAmount'];
                $max_crypto_amount = bcdiv($max_amount, $exchange_rate, 8);

                $fiat_exchange_rate = bcdiv(1, $exchange_rate, 18);

                $min_crypto_amount = (string) $min_crypto_amount_data[$cryptocurrency_symbol]['value'];
                $max_crypto_amount = bcdiv($max_amount, $exchange_rate, 8);
                $crypto_converted_amount = (string) $exchange_rate;
                $fiat_converted_amount = (string) $fiat_exchange_rate;
            }
        }

        $data['symbol'] = $crypto_symbol;
        $data['min_amount'] = $min_amount;
        $data['max_amount'] = $max_amount;
        $data['min_crypto_amount'] = $min_crypto_amount;
        $data['max_crypto_amount'] = $max_crypto_amount;
        $data['crypto_converted_amount'] = $crypto_converted_amount;
        $data['fiat_converted_amount'] = $fiat_converted_amount;


        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00377') /*Get Buy Crypto History Successful.*/, 'data' => $data);
    }

    public function merchant_get_buy_sell_supported_currencies($params)
    {
        $db = $this->db;

        // $business_id = $params['business_id'];
        // $api_key = $params['api_key'];

        // $crypto_api_key_validation = $xunCrypto->validate_crypto_api_key($api_key, $business_id);

        // if(isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0){
        //     return $crypto_api_key_validation;
        // }

        $db->where('name', array('simplex', 'xanpool'), 'IN');
        $provider_result = $db->get('provider', null, 'id, company, name');

        $db->where('a.is_payment_gateway', 1);
        $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'LEFT');
        $xun_coins = $db->map('symbol')->ArrayBuilder()->get('xun_coins a', null, 'b.name, b.currency_id, b.symbol');

        foreach ($provider_result as $provider_key => $provider_value) {
            $provider_name = $provider_value['name'];
            $provider_id = $provider_value['id'];

            $db->where('provider_id', $provider_id);
            $db->where('name', array('fiatCurrencyList', 'supportedCurrencies'), 'IN');
            $provider_setting_data = $db->map('name')->ArrayBuilder()->get('provider_setting', null, 'name, value');

            $supported_cryptocurrency_list = explode(",", $provider_setting_data['supportedCurrencies']);
            $crypto_list = array();
            foreach ($supported_cryptocurrency_list as $crypto_symbol) {
                $wallet_type = $xun_coins[$crypto_symbol]['currency_id'];

                $crypto_arr['name'] = $xun_coins[$crypto_symbol]['name'];
                $crypto_arr['wallet_type'] = $wallet_type;
                $crypto_arr['symbol'] = $crypto_symbol;

                $crypto_list[] = $crypto_arr;
            }

            $data[$provider_name]['crypto_list'] = $crypto_list;
            $data[$provider_name]['fiat_currency_list'] = explode(",", $provider_setting_data['fiatCurrencyList']);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  "Get Buy Crypto Supported Currencies.", 'data' => $data);
    }

    public function get_buysell_transaction_token_details($params, $ip)
    {
        global $xunCurrency;
        global $simplex;
        global $config;

        $post = $this->post;
        $db = $this->db;

        $custom_fiat_currency = $params['custom_fiat_currency'];
        $transaction_token = $params['transaction_token'];
        $type = $params['type'];

        if ($transaction_token == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00414') /*Transaction token cannot be empty*/);
        }

        if ($type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Type cannot be empty.", "developer_msg" => "Type cannot be empty.");
        }

        $db->where('type', $type);
        $db->where('transaction_token', $transaction_token);
        $payment_request_details = $db->getOne('xun_crypto_payment_request', 'crypto_amount, wallet_type, fiat_currency, fiat_amount, destination_address, type, provider_id, status, business_id, end_user_id');

        if (!$payment_request_details) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction request not found.");
        }

        if ($custom_fiat_currency != "") {
            $fiat_currency = $custom_fiat_currency;
        } else {
            $fiat_currency = $payment_request_details['fiat_currency'];
        }

        $provider_id = $payment_request_details['provider_id'];
        $wallet_type = $payment_request_details['wallet_type'];
        $status = $payment_request_details['status'];
        $end_user_id = $payment_request_details['end_user_id'];

        if ($status != 'pending') {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $this->get_translation_message('E00435') /*This transaction has ended.*/
            );
        }

        $currency_info = $xunCurrency->get_currency_info($wallet_type);
        $symbol = $currency_info["symbol"];

        $db->where('id', $provider_id);
        $provider_name = $db->getValue('provider', 'name');

        unset($payment_request_details['provider_id']);
        $payment_request_details['provider'] = $provider_name ? $provider_name : '';
        $payment_request_details['crypto_symbol'] = $symbol;

        // validation, check if amount is within the range of supported amount
        $isWithinRange = true;
        $db->where('provider_id', $provider_id);
        $db->where('type', strtoupper($symbol));
        $provider_setting = $db->get('provider_setting', null, 'name, value');
        $provider_setting = array_column($provider_setting, 'value', 'name');

        $db->where('unit', $symbol);
        $cryptocurrencyRate = $db->getValue('xun_cryptocurrency_rate', 'value');
        $db->where('currency', $fiat_currency);
        $currencyRate = $db->getValue('xun_currency_rate', 'exchange_rate');
        if ($cryptocurrencyRate) {
            $cryptoTotalFiatAmountUSD = bcmul($payment_request_details['crypto_amount'], $cryptocurrencyRate, 2);
            if ($currencyRate) {
                $cryptoTotalFiatAmountConverted = bcmul($cryptoTotalFiatAmountUSD, $currencyRate, 2);
            } else {
                $cryptoTotalFiatAmountConverted = '0.00';
            }
            $payment_request_details['debug'] = $provider_name;

            if ($cryptoTotalFiatAmountUSD < $provider_setting['minAmount']) {
                $payment_request_details['fiat_amount'] = $cryptoTotalFiatAmountConverted;
                $isWithinRange = false;
            }
            // only Simplex provider need to check for maxAmount
            else if ($cryptoTotalFiatAmountUSD > $provider_setting['maxAmount'] && $provider_name == 'simplex') {
                $payment_request_details['fiat_amount'] = $cryptoTotalFiatAmountConverted;
                $isWithinRange = false;
            }
        }


        if ($payment_request_details['provider'] == "simplex" && $payment_request_details['type'] == "buy" && $isWithinRange) {

            $newData = array(
                "wallet_type" => $payment_request_details['wallet_type'],
                "fiat_currency" => $fiat_currency,
                "fiat_amount" => "",
                "crypto_amount" => $payment_request_details['crypto_amount'],
                "transaction_type" => "buy",
                "payment_method_type" => array("credit_card"),
                "destination_address" => $payment_request_details['destination_address'],
                "end_user_id" => $end_user_id
            );

            $simplexReturn = $simplex->get_quote($newData, (string)$payment_request_details['business_id'], $ip);

            if ($simplexReturn['code'] == 1) {
                $payment_request_details['fiat_amount'] = $simplexReturn['data']['requested_amount'];
            } else {
                $payment_request_details['fiat_amount'] = "0.00";
            }
        } else if ($payment_request_details['provider'] == "xanpool" && $payment_request_details['type'] == "buy" && $isWithinRange) {

            $api_url = $config['xanpool_api_url'] . '/api/prices?currencies=' . strtoupper($fiat_currency) . '&cryptoCurrencies=' . strtoupper($symbol) . '&type=buy';

            $curl_params = array();

            $xanpoolReturn = $post->curl_xanpool($api_url, $curl_params, 'GET');
            $xanpooRate = $xanpoolReturn[0]['cryptoPrice'];
            $payment_request_details['fiat_amount'] = bcmul($payment_request_details['crypto_amount'], $xanpooRate, 2);
            //print_r($xanpoolReturn);exit;

        }

        //unset($payment_request_details['business_id']);   
        $payment_request_details['end_user_id'] = $end_user_id;

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  "Buy Sell Transaction Token Details.", 'data' => $payment_request_details);
    }
}
