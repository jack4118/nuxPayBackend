<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/

class XunMarketplace
{

    public function __construct($db, $post, $general)
    {
        $this->db = $db;
        $this->post = $post;
        $this->general = $general;
    }

    public function place_buy_advertisement($params)
    {
        global $setting, $lang, $xunCurrency, $xunXmpp, $xunUser, $xunCrypto, $xun_numbers;
        $db = $this->db;

        $type = "buy";
        $status = "new";

        $username = trim($params["username"]);
        $crypto_currency = trim($params["crypto_currency"]);
        $currency_list = $params["currency"]; // array, remove trim
        $fix_price = trim($params["fix_price"]);
        $floating_ratio = trim($params["floating_ratio"]);
        $price = trim($params["price"]);
        $maximum_price = trim($params["maximum_price"]);
        $payment_method = $params["payment_method"]; // user's payment method id
        $volume = trim($params["volume"]); // total ad quantity - cryptocurrency
        $max = trim($params["max_purchase"]); // max per order - cryptocurrency
        $min = trim($params["min_purchase"]); // cryptocurrency
        $max_processing_orders = trim($params["max_processing_orders"]);
        $remarks = trim($params["remarks"]);
        $info = trim($params["info"]);
        // $tnc_trading_fee = trim($params["tnc_trading_fee"]);

        $date = date("Y-m-d H:i:s");
        $newParams = $params;

        //  send notification
        $user_country_info_arr = $xunUser->get_user_country_info([$username]);
        $owner_country_info = $user_country_info_arr[$username];
        $owner_country = $owner_country_info["name"];

        $device_os = $db->where("mobile_number", $username)->getValue("xun_user_device", "os");
        $device_os = $device_os == 1 ? $device_os = "Android" : $device_os = "iOS";

        $price ? $price : $price = $floating_ratio;

        $newParams["owner_country"] = $owner_country;
        $newParams["device_os"] = $device_os;
        $newParams["price"] = $price;

        //params checking
        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ((is_array($currency_list) && empty($currency_list)) || (!is_array($currency_list) && trim($currency_list) == '')) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Currency cannot be empty");
        }

        if ($crypto_currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Cryptocurrency cannot be empty");
        }

        if ($fix_price == 'true') {
            if ($price == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement quote cannot be empty");
            }
            $is_fix_price_bool = true;
            $price_type = "fix";
            $floating_ratio = '';
            $maximum_price = '';
        } else {
            if ($floating_ratio == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Floating ratio cannot be empty");
            }

            $floating_ratio = $setting->setDecimal($floating_ratio, "marketplacePrice");

            if ($maximum_price == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Maximum price cannot be empty");
            }

            $is_fix_price_bool = false;
            $price_type = "floating";

            // $maximum_price = $setting->setDecimal($maximum_price, "marketplacePrice");
            //  fix the DP for maximum price
            //  if currency is fiat/coins2 => 2 dp else 8 dp
        }

        if ($max == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Maximum purchase cannot be empty");
        }

        if ($min == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Minimum purchase cannot be empty");
        }

        if ($max_processing_orders == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Maximum processing orders cannot be empty");
        }

        if ($remarks == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Remarks cannot be empty");
        }

        if ($info == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Info cannot be empty");
        }

        $tnc_trading_fee = false;

        if (is_array($currency_list)) {
            $currency_list = array_map(function ($v) {
                return strtolower($v);
            }, $currency_list);
            $currency = $currency_list[0];
            $final_currency = implode("##", $currency_list);
        } else {
            $currency = strtolower($currency_list);
            $currency = $currency == "bitcoin cash" ? "bitcoincash" : $currency;
            $currency_list = [$currency];
            $final_currency = $currency;
        }

        if($is_fix_price_bool === true){
            $price_unit = $currency;
        }

        $crypto_currency = strtolower($crypto_currency);
        $crypto_currency = $crypto_currency == "bitcoin cash" ? "bitcoincash" : $crypto_currency;
        $advertisement_currency_list = array_merge($currency_list, [$crypto_currency]);

        $supported_currencies = $xunCurrency->get_marketplace_currencies();

        $currency_list_len = count($currency_list);
        $currency_type_arr = [];
        for ($i = 0; $i < $currency_list_len; $i++) {
            $currency_key = $currency_list[$i];
            if (!isset($supported_currencies[$currency_key])) {
                $error_message = $currency_key . " is not a supported currency.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }
            $currency_type_arr[] = $supported_currencies[$currency_key]["type"];
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];
        $db->where("user_id", $user_id);
        $ip = $db->where("name", "lastLoginIP")->getValue("xun_user_setting", "value");

        $newParams["nickname"] = $xun_user["nickname"];
        $newParams["ip"] = $ip;
        $newParams["price_type"] = $price_type;
        $newParams["final_currency"] = $final_currency;
        $is_c2c = false;

        if (in_array("cryptocurrency", $currency_type_arr)) {
            if (sizeof($currency_list) > 1) {
                $error_message = "Only 1 cryptocurency is allowed.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }

            $currency = $currency == "bitcoin cash" ? "bitcoincash" : $currency;

            if ($currency == $crypto_currency) {
                $error_message = "Exchange of the same cryptocurrency is not allowed.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }

            $is_c2c = true;
            $status = "pre_escrow";
        }

        if (!array_key_exists($crypto_currency, $supported_currencies)) {
            $error_message = $crypto_currency . " is not a supported coin.";
            $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }


        if (!$is_c2c) {
            if ($payment_method == '' or empty($payment_method)) {
                $error_message = "Payment method cannot be empty";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }
            if (sizeof($payment_method) > 5) {
                $error_message = "Maximum of 5 payment methods are allowed.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }
            $new_payment_method = [];

            foreach ($payment_method as $data) {
                $payment_method_id = trim($data);

                if ($payment_method_id == '') {
                    $error_message = "Payment method ID cannot be empty.";
                    $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                }

                $db->where("id", $payment_method_id);
                $db->where("user_id", $user_id);
                $db->where("status", 1);
                $payment_method_rec = $db->getOne("xun_marketplace_user_payment_method");

                if (!$payment_method_rec) {
                    $error_message = "Please select a valid payment method.";
                    $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "errorCode" => -100);
                }

                $user_payment_method = [];
                $user_payment_method["id"] = $payment_method_rec["payment_method_id"];
                $user_payment_method["account_name"] = $payment_method_rec["name"];
                $user_payment_method["account_no"] = $payment_method_rec["account_no"];
                $user_payment_method["qr_code"] = $payment_method_rec["qr_code"];
                $new_payment_method[] = $user_payment_method;
            }
        }

        $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency, true);
        $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($crypto_currency, true);

        $currency_decimal_places = $currency_decimal_place_setting["decimal_places"];
        $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];

        $crypto_decimal_places = $crypto_decimal_place_setting["decimal_places"];
        $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

        if ($is_fix_price_bool === false) {
            $maximum_price = $setting->setDecimal($maximum_price, $currency_dp_credit_type);

            if(bccomp((string)$maximum_price, "0", 8) < 1){
                $error_message = "Invalid maximum price.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message,  "errorCode" => -102);
            }
        }

        if ($is_c2c == true && $xunCurrency->isStableCoin($currency) == true) {
            $dp_credit_type = $currency_dp_credit_type;
        } else if ($is_c2c == false && $xunCurrency->isStableCoin($crypto_currency) == true) {
            $dp_credit_type = $currency_dp_credit_type;

        } else {
            $dp_credit_type = $crypto_dp_credit_type;

        }

        if ($volume == '') {
            $volume = $max;
        }

        if ($is_c2c === true) {
            $volume = $setting->setDecimal($volume, $currency_dp_credit_type);
            $max = $setting->setDecimal($max, $currency_dp_credit_type);
            $min = $setting->setDecimal($min, $crypto_dp_credit_type);
            $price_decimal_places = $currency_decimal_place_setting;
        } else {
            $volume = $setting->setDecimal($volume, $crypto_dp_credit_type);
            $max = $setting->setDecimal($max, $crypto_dp_credit_type);
            $min = $setting->setDecimal($min, $crypto_dp_credit_type);
            $price_decimal_places = $crypto_decimal_place_setting;
        }

        $min_advertisement_value = $setting->systemSetting["marketplaceMinAdvertisementValue"];

        if (bccomp((string) $max, (string) $volume, 8) > 0) {
            $error_message = "Maximum transaction limit cannot be more than your purchase amount.";
            $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);

        $cryptocurrency_rate = $full_currency_list[$crypto_currency];
        $currency_rate = $full_currency_list[$currency];

        $min_cryptocurrency_value = bcdiv((string) $min_advertisement_value, (string) $cryptocurrency_rate, $crypto_decimal_places);

        if ($is_c2c) {
            $limit_currency = $crypto_currency;

            $min_currency_value = bcdiv((string) $min_advertisement_value, (string) $currency_rate, $currency_decimal_places);

            if ($max < $min_currency_value) {
                $error_message = "A minimum of " . $min_advertisement_value . " USD currency value is required to create an advertisement.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -100, "max" => $max, "minimum_value" => $min_currency_value);
            }
            $c2c_rate = $xunCurrency->get_rate($crypto_currency, $currency);

            if ($min <= 0) {
                $error_message = "Minimum purchase must be more than zero.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }
            
            if ($min < $min_cryptocurrency_value) {
                $error_message = "The minimum transaction limit must be more than " . $min_advertisement_value . " USD currency value.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -100, "min" => $min, "minimum_value" => $min_cryptocurrency_value);
            }
            if ($is_fix_price_bool === true) {
                $price = $setting->setDecimal($price, $currency_dp_credit_type);
                $effective_price = $price;

                $min_currency = bcmul((string) $min, (string) $effective_price, $currency_decimal_places);
            } else {
                $min_currency = bcmul((string) $min, (string) $c2c_rate, $currency_decimal_places);
                // check if maximum price is less than effective price
                
                $floating_price = $c2c_rate + ($c2c_rate * ($floating_ratio / 100));
                $floating_price = $setting->setDecimal($floating_price, $currency_dp_credit_type);
                
                if ($maximum_price < $floating_price) {
                    $error_message = "The maximum price cannot be less than the advertisement price.";
                    $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                    return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -101, "maximum_price" => $maximum_price, "advertisement_price" => $floating_price);
                }
            }

            if ($min_currency > $max) {
                $error_message = "Minimum purchase must not be more than the maximum purchase.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "min" => $min_currency, "max" => $max);
            }
        } else {
            $limit_currency = $currency;

            if ($max < $min_cryptocurrency_value) {
                $error_message = "A minimum of " . $min_advertisement_value . " USD currency value is required to create an advertisement.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -100, "max" => $max, "minimum_value" => $min_cryptocurrency_value);
            }
            if ($min < $min_cryptocurrency_value) {
                $error_message = "The minimum transaction limit must be more than " . $min_advertisement_value . " USD currency value.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -100, "min" => $min, "minimum_value" => $min_cryptocurrency_value);
            }

            if ($price_type == "fix") {
                $price = $setting->setDecimal($price, $currency_dp_credit_type);

                // $price_in_usd = bcmul((string) $price, (string) $currency_rate, $currency_decimal_places);
                $effective_price = $price;
            } else {
                $floating_price = $this->get_effective_floating_price($crypto_currency, $currency, $cryptocurrency_rate, $currency_rate, $floating_ratio);
                if ($maximum_price < $floating_price) {
                    $error_message = "The maximum price cannot be less than the advertisement price.";
                    $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                    return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -101, "maximum_price" => $maximum_price, "advertisement_price" => $floating_price);
                }
            }

            if ($min <= 0) {
                $error_message = "Minimum purchase must be more than zero.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            } else if ($min > $max) {
                $error_message = "Minimum purchase must not be more than the maximum purchase.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }
        }

        $quantity = $volume;

        $fee_type = $tnc_trading_fee ? "thenuxcoin" : $crypto_currency;

        $trading_fee_crypto = $tnc_trading_fee ? ($is_c2c ? $currency : $crypto_currency) : $crypto_currency;

        $currency_rate_arr = array($currency => $currency_rate, $crypto_currency => $cryptocurrency_rate);
        $trading_fee_crypto_rate = $currency_rate_arr[$trading_fee_crypto];

        $trading_fee_arr = $this->get_trading_fee($type, $fee_type, $quantity, $trading_fee_crypto_rate);
        $fee_quantity = $trading_fee_arr["fee_quantity"];

        $status = $trading_fee_arr["has_trading_fee"] ? "pre_escrow" : $status;

        $expire_type = $status == "pre_escrow" ? "escrow" : "advertisement";

        $expires_at = $this->get_advertisement_expiration($date, $expire_type);

        $insert_data = array(
            "quantity" => $quantity,
            "price" => $price_type == "fix" ? $effective_price : '',
            "price_unit" => $price_unit ? $price_unit : '',
            "user_id" => $user_id,
            "status" => $status,
            "type" => $type,
            "crypto_currency" => $crypto_currency,
            "currency" => $final_currency,
            "is_cryptocurrency" => $is_c2c ? 1 : 0,
            "min" => $min,
            "max" => $max,
            "limit_currency" => $limit_currency,
            "max_processing_orders" => $max_processing_orders,
            "price_type" => $price_type,
            "floating_ratio" => $floating_ratio,
            "price_limit" => $maximum_price,
            "expires_at" => $expires_at,
            "remarks" => $remarks,
            "info" => $info,
            "fee_quantity" => $fee_quantity,
            "fee_type" => $fee_type,
            "created_at" => $date,
            "updated_at" => $date,
        );

        //  check balance
        if($is_c2c){
            $min = $min_currency;
            $xunUserService = new XunUserService($db);
            $user_address_data = $xunUserService->getActiveAddressByUserIDandType($user_id, "personal", "id, address, user_id");
            $user_address = $user_address_data['address'];

            $user_wallet_balance = $xunCrypto->get_wallet_balance($user_address, $currency);
            
            if(!$user_wallet_balance || ($user_wallet_balance < $quantity)){
                $error_message = "Insufficient balance.";
                $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
                return array("code" => 0, "message" => "FAILED", "message_d" => $error_message);
            }
        }

        $row_id = $db->insert("xun_marketplace_advertisement", $insert_data);
        $return_escrow = false;
        if ($row_id) {
            // return order_id, advertisement_id and escrow_address for c2c
            $return_data = array("advertisement_id" => $row_id, "quantity" => $quantity);

            if (!$is_c2c) {
                // save payment method
                $this->save_payment_method($new_payment_method, $row_id);
            } else {
                $create_order_result = $this->store_create_advertisement_order_transaction($row_id, $insert_data, "create_ad");

                $order_id = $create_order_result["order_id"];
                // $escrow_address = $setting->systemSetting["marketplaceEscrowWalletAddress"];

                $return_data["advertisement_order_id"] = $order_id;
                // $return_data["escrow_address"] = $escrow_address;
                $return_data["crypto_currency"] = $currency;
                $return_data["username"] = $username;
                $return_data["nickname"] = $xun_user["nickname"];

                $return_escrow = true;
            }

            if ($tnc_trading_fee) {
                $trading_fee_order = $this->store_create_advertisement_order_transaction($row_id, $insert_data, "advertisement_trading_fee");

                if ($fee_quantity > 0) {
                    $return_data["trading_fee"] = array(
                        "quantity" => $fee_quantity,
                        "crypto_currency" => $fee_type,
                        "order_id" => $trading_fee_order["order_id"],
                        "fee_rate" => $tnc_trading_fee ? $tnc_crypto_rate : "1.00000000",
                    );

                    $return_escrow = true;
                }
            }

            if ($return_escrow) {
                $escrow_address = $setting->systemSetting["marketplaceEscrowWalletAddress"];
                $return_data["escrow_address"] = $escrow_address;
            }

            //$price_setting = $price_type == "fix" ? "Fix: " . $price : "Floating: " . $floating_ratio;
            $tag = "Create Buy Advertisement";

            $content .= "Username: " . $xun_user["nickname"] . "\n";
            $content .= "Phone number: " . $username . "\n";
            $confent .= "IP: " . $ip . "\n";   
            $content .= "Country: " . ucfirst($owner_country) . "\n";
            $content .= "Device: " . $device_os . "\n"; 
            $content .= "Advertisement ID: " . $row_id . "\n";
            $content .= "Buy: " . ucfirst($crypto_currency) . "\n";
            $content .= "Pay with: " . ucfirst(str_replace("##", ", ", $final_currency)) . "\n";
            $content .= "Price setting: " . $price_type . "\n";
            if ($price_type == "fix"){
                $content .= "Price: " . $price . "\n";
            }
            if ($price_type == "floating"){
                $content .= "Max Price: " . $maximum_price . "\n";
            }
            $content .= "Transaction Limit: " . $min . " - " . $max . "\n";
            $content .= "\nTime: " . $date;

            //$erlang_params = array();
            $erlang_params["tag"] = $tag;
            $erlang_params["message"] = $content;
            $erlang_params["mobile_list"] = $xun_numbers;
            $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");

            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement created.", "data" => $return_data);
        } else {
            $error_message = "Internal server error.";
            $xmpp_result = $this->send_failed_buy_advertisement($newParams, $error_message);
            // print_r($db);
            return array("code" => 0, "message" => "FAILED", "message_d" => $error_message);
        }
    }

    private function send_failed_buy_advertisement ($newParams, $error_message)
    {
        global $xunXmpp, $xun_numbers;

        $username = trim($newParams["username"]);
        $crypto_currency = trim($newParams["crypto_currency"]);
        //$currency_list = $newParams["currency"]; // array, remove trim
        //$fix_price = trim($newParams["fix_price"]);
        $price = trim($newParams["price"]);
        $maximum_price = trim($newParams["maximum_price"]);
        //$volume = trim($newParams["volume"]); // total ad quantity - cryptocurrency
        $max = trim($newParams["max_purchase"]); // max per order - cryptocurrency
        $min = trim($newParams["min_purchase"]); // cryptocurrency
        //$max_processing_orders = trim($newParams["max_processing_orders"]);
        //$remarks = trim($newParams["remarks"]);
        //$info = trim($newParams["info"]);
        $user_nickname = $newParams["nickname"];
        $ip = $newParams["ip"];
        $owner_country = $newParams["owner_country"];
        $device_os = $newParams["device_os"];
        $final_currency = $newParams["final_currency"];
        $price_type = $newParams["price_type"];
        
        $date = date("Y-m-d H:i:s");

        $tag = "Fail to Create Buy Advertisement";

        $content .= "Username: " . $user_nickname . "\n";
        $content .= "Phone number: " . $username . "\n";
        $confent .= "IP: " . $ip . "\n";    //
        $content .= "Country: " . ucfirst($owner_country) . "\n";
        $content .= "Device: " . $device_os . "\n"; //
        $content .= "Buy: " . ucfirst($crypto_currency) . "\n";
        $content .= "Pay with: " . ucfirst(str_replace("##", ", ", $final_currency)) . "\n";
        $content .= "Price setting: " . $price_type . "\n";
        $content .= "Price: " . $price . "\n";
        if ($price_type == "floating"){
            $content .= "Max Price: " . $maximum_price . "\n";
        }
        $content .= "Transaction Limit: " . $min . " - " . $max . "\n";
        $content .= "\nMessage: " . $error_message . "\n";
        $content .= "Time: " . $date;

        //$erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");
        return $xmpp_result;
    }

    public function place_sell_advertisement($params)
    {
        global $setting, $lang, $xunCurrency, $xunXmpp, $xunUser, $xunCrypto, $xun_numbers;
        $db = $this->db;
        $general = $this->general;

        $type = "sell";
        $status = "pre_escrow";

        $username = trim($params["username"]);
        $crypto_currency = trim($params["crypto_currency"]);
        $currency_list = $params["currency"];
        $fix_price = trim($params["fix_price"]);
        $floating_ratio = trim($params["floating_ratio"]);
        $price = trim($params["price"]);
        $minimum_price = trim($params["minimum_price"]);
        $payment_method = $params["payment_method"];
        $volume = trim($params["volume"]); // cryptocurrency
        $max = trim($params["max_purchase"]); // cryptocurrency
        $min = trim($params["min_purchase"]); // currency
        $max_processing_orders = trim($params["max_processing_orders"]);
        $remarks = trim($params["remarks"]);
        $info = trim($params["info"]);

        $newParams = $params;
        $date = date("Y-m-d H:i:s");

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($max == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Maximum purchase cannot be empty");
        }

        if ($min == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Minimum purchase cannot be empty");
        }

        if ((is_array($currency_list) && empty($currency_list)) || (!is_array($currency_list) && trim($currency_list) == '')) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Currency cannot be empty");
        }

        if ($crypto_currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Cryptocurrency cannot be empty");
        }

        if ($fix_price == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Fix price cannot be empty");
        }

        if ($fix_price == 'true') {
            if ($price == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement quote cannot be empty");
            }
            $is_fix_price_bool = true;
            $price_type = 'fix';
            $floating_ratio = '';
            $minimum_price = '';
        } else {
            if ($floating_ratio == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Floating ratio cannot be empty");
            }

            $floating_ratio = $setting->setDecimal($floating_ratio, "marketplacePrice");

            if ($minimum_price == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Minimum price cannot be empty");
            }

            $is_fix_price_bool = false;
            $price_type = 'floating';

            // minimum price cannot be more than floating price
        }

        if ($max_processing_orders == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Maximum processing orders cannot be empty");
        }

        if ($remarks == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Remarks cannot be empty");
        }

        if ($info == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Info cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        //  send notification
        $user_country_info_arr = $xunUser->get_user_country_info([$username]);
        $owner_country_info = $user_country_info_arr[$username];
        $owner_country = $owner_country_info["name"];
        
        $db->where("user_id", $user_id);
        $db->where("name", "lastLoginIP");
        $ip = $db->getValue("xun_user_setting", "value");

        $device_os = $db->where("mobile_number", $username)->getValue("xun_user_device", "os");
        $device_os = $device_os == 1 ? $device_os = "Android" : $device_os = "iOS";

        $newParams["owner_country"] = $owner_country;
        $newParams["device_os"] = $device_os;
        $newParams["nickname"] = $xun_user["nickname"];
        $newParams["ip"] = $ip;
        $newParams["price_type"] = $price_type;


        $tnc_trading_fee = false;

        if (is_array($currency_list)) {
            $currency_list = array_map(function ($v) {
                return strtolower($v);
            }, $currency_list);
            $currency = $currency_list[0];
            $final_currency = implode("##", $currency_list);
        } else {
            $currency = strtolower($currency_list);
            $currency = $currency == "bitcoin cash" ? "bitcoincash" : $currency;
            $currency_list = [$currency];
            $final_currency = $currency;
        }

        if ($is_fix_price_bool === true){
            $price_unit = $currency;
        }

        $crypto_currency = strtolower($crypto_currency);
        $crypto_currency = $crypto_currency == "bitcoin cash" ? "bitcoincash" : $crypto_currency;

        $advertisement_currency_list = array_merge($currency_list, [$crypto_currency]);

        $supported_currencies = $xunCurrency->get_marketplace_currencies();

        $currency_list_len = count($currency_list);
        $currency_type_arr = [];
        for ($i = 0; $i < $currency_list_len; $i++) {
            $currency_key = $currency_list[$i];
            if (!isset($supported_currencies[$currency_key])) {
                $error_message = $currency_key . " is not a supported currency.";
                $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }
            $currency_type_arr[] = $supported_currencies[$currency_key]["type"];
        }

        $newParams["final_currency"] = $final_currency;
        $is_c2c = false;

        if (in_array("cryptocurrency", $currency_type_arr)) {
            if (sizeof($currency_list) > 1) {
                $error_message = "Only 1 cryptocurency is allowed.";
                $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }

            $currency = $currency == "bitcoin cash" ? "bitcoincash" : $currency;

            if ($currency == $crypto_currency) {
                $error_message = "Exchange of the same cryptocurrency is not allowed.";
                $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }

            $is_c2c = true;
        }

        if (!array_key_exists($crypto_currency, $supported_currencies)) {
            $error_message = $crypto_currency . " is not a supported coin.";
            $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }
        
        if (!$is_c2c) {
            if ($payment_method == '' || empty($payment_method)) {
                $error_message = "Payment method cannot be empty";
                $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }

            if (sizeof($payment_method) > 5) {
                return array('code' => 0, 'message' => "SUCCESS", 'message_d' => "Maximum of 5 payment methods are allowed.");
            }

            $new_payment_method = [];

            foreach ($payment_method as $data) {
                $payment_method_id = trim($data);

                if ($payment_method_id == '') {
                    $error_message = "Payment method ID cannot be empty.";
                    $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                }

                $db->where("id", $payment_method_id);
                $db->where("user_id", $user_id);
                $db->where("status", 1);
                $payment_method_rec = $db->getOne("xun_marketplace_user_payment_method");

                if (!$payment_method_rec) {
                    $error_message = "Please select a valid payment method.";
                    $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "errorCode" => -100);
                }

                $user_payment_method = [];
                $user_payment_method["id"] = $payment_method_rec["payment_method_id"];
                $user_payment_method["account_name"] = $payment_method_rec["name"];
                $user_payment_method["account_no"] = $payment_method_rec["account_no"];
                $user_payment_method["qr_code"] = $payment_method_rec["qr_code"];
                $new_payment_method[] = $user_payment_method;
            }
        }

        $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency, true);
        $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($crypto_currency, true);

        $currency_decimal_places = $currency_decimal_place_setting["decimal_places"];
        $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];

        $crypto_decimal_places = $crypto_decimal_place_setting["decimal_places"];
        $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

        if ($is_fix_price_bool === false) {
            $minimum_price = $setting->setDecimal($minimum_price, $currency_dp_credit_type);

            if(bccomp((string)$minimum_price, "0", 8) < 1){
                $error_message = "Invalid minimum price.";
                $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message,  "errorCode" => -102);
            }
        }

        $dp_credit_type = $crypto_dp_credit_type;

        $max = $setting->setDecimal($max, $dp_credit_type); // cryptocurrency
        if ($volume == '') {
            $volume = $max;
        }

        if (bccomp((string) $max, (string) $volume, 8) > 0) {
            $error_message = "Maximum transaction limit cannot be more than your selling amount.";
            $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);

        // $currency_rate = $is_c2c ? $xunCurrency->get_rate($currency, "usd") : $full_currency_list[$currency];
        // $cryptocurrency_rate = $xunCurrency->get_rate($crypto_currency, "usd");

        $currency_rate = $full_currency_list[$currency];
        $cryptocurrency_rate = $full_currency_list[$crypto_currency];
        // $currency_rate = $setting->setDecimal($currency_rate, "fiatCurrency");
        // $cryptocurrency_rate = $setting->setDecimal($cryptocurrency_rate, "fiatCurrency");
        
        $cryptocurrency_usd_base = $setting->setDecimal($cryptocurrency_rate, "fiatCurrency");
        $min = $setting->setDecimal($min, $currency_dp_credit_type);

        $min_advertisement_value = $setting->systemSetting["marketplaceMinAdvertisementValue"];

        $min_cryptocurrency_value = bcdiv((string) $min_advertisement_value, (string) $cryptocurrency_usd_base, $crypto_decimal_places);

        $market_price = $xunCurrency->get_rate($crypto_currency, $currency);

        // echo "\n currency_rate $currency_rate cryptocurrency_rate $cryptocurrency_rate \n min $min min_advertisement_value $min_advertisement_value min_cryptocurrency_value $min_cryptocurrency_value market_price $market_price max $max min_cryptocurrency_value2 $min_cryptocurrency_value2";

        // compare if max is more than 5 USD
        if (bccomp((string) $max, (string) $min_cryptocurrency_value, 8) < 0) {
            $error_message = "A minimum of " . $min_advertisement_value . " USD currency value is required to create an advertisement.";
            $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
            return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -100, "max" => $max, "min_cryptocurrency_value" => $min_cryptocurrency_value);
        }

        if ($is_c2c) {
            $min_currency_value = bcdiv((string) $min_advertisement_value, (string) $currency_rate, 8);

            if ($min < $min_currency_value) {
                $error_message = "A minimum of " . $min_advertisement_value . " USD currency value is required to create an advertisement.";
                $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -100, "min" => $min, "min_currency_value" => $min_currency_value);
            }
            if ($price_type == "fix") {
                $price = $setting->setDecimal($price, $currency_dp_credit_type);
                $effective_price = $price;
                // min is in cryptocurrency value
                $min_cryptocurrency = bcdiv((string) $min, (string) $price, $crypto_decimal_places);
    
            } else {
                $price = $market_price;

                $floating_price = $price + ($price * ($floating_ratio / 100));
                $floating_price = $setting->setDecimal($floating_price, $currency_dp_credit_type);

                // min is in cryptocurrency value
                $min_cryptocurrency = bcdiv((string) $min, (string) $floating_price, $crypto_decimal_places);

                if ($minimum_price > $floating_price) {
                    $error_message = "The minimum price cannot be more than the advertisement price.";
                    $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                    return array("code" => 0, "message" => "FAILED", "message_d" => $error_message , "errorCode" => -101, "minimum_price" => $minimum_price, "advertisement_price" => $floating_price);
                }
            }

            // echo "\n floating_price $floating_price minimum_price $minimum_price min_currency_value $min_currency_value min_cryptocurrency $min_cryptocurrency min $min price $price";
            if ($min <= 0) {
                $error_message = "Minimum purchase must be more than zero.";
                $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            } else if ($min_cryptocurrency > $max) {
                $error_message = "Minimum purchase must not be more than the maximum purchase amount.";
                $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "min_cryptocurrency" => $min_cryptocurrency, "max" => $max);
            }
        } else {
            $min_currency_value = bcdiv((string) $min_advertisement_value, (string) $currency_rate, $currency_decimal_places);

            // echo "\n min_currency_value $min_currency_value min $min ";
            if ($min < $min_currency_value) {
                $error_message = "A minimum of " . $min_advertisement_value . " USD currency value per transaction is required to create an advertisement.";
                $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -100, "min" => $min, "min_currency_value" => $min_currency_value);
            }
            if ($price_type == "fix") {
                $price = $setting->setDecimal($price, "marketplacePrice");

                //  price = 400 MYR -> 
                $price_in_usd = bcmul((string)$price, (string)$currency_rate, $currency_decimal_places);
                $effective_price = $price;
                // min is in cryptocurrency value
                $min_cryptocurrency = bcdiv((string) $min, (string) $price, $crypto_decimal_places);

            } else {
                $price_in_usd = $cryptocurrency_rate;
                $price = $price_in_usd * $currency_rate;
                $price = bcdiv((string)$price_in_usd, (string)$currency_rate, $currency_decimal_places);

                $floating_price = $this->get_effective_floating_price($crypto_currency, $currency, $cryptocurrency_rate, $currency_rate, $floating_ratio);

                // min is in cryptocurrency value
                $min_cryptocurrency = bcdiv((string) $min, (string) $floating_price, $crypto_decimal_places);
                if ($minimum_price > $floating_price) {
                    $error_message = "The minimum price cannot be more than the advertisement price.";
                    $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                    return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -101, "minimum_price" => $minimum_price, "advertisement_price" => $floating_price);
                }
            }

            if ($min <= 0) {
                $error_message = "Minimum purchase must be more than zero.";
                $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "min" => $min);
            } else if ($min_cryptocurrency > $max) {
                $error_message = "Minimum purchase must not be more than the maximum purchase amount.";
                $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "min_cryptocurrency" => $min_cryptocurrency, "max" => $max);
            }
        }

        $price_setting = $price_type == "fix" ? "Fix: " . $price : "Floating: " . $floating_ratio;
        $newParams["price_setting"] = $price_setting;

        $expire_type = $status == "pre_escrow" ? "escrow" : "advertisement";

        $expires_at = $this->get_advertisement_expiration($date, $expire_type);

        $quantity = $volume;

        $fee_type = $tnc_trading_fee ? "thenuxcoin" : $crypto_currency;

        $trading_fee_crypto_rate = $cryptocurrency_rate;

        $trading_fee_arr = $this->get_trading_fee($type, $fee_type, $quantity, $trading_fee_crypto_rate, $crypto_decimal_places);
        $fee_quantity = $trading_fee_arr["fee_quantity"];
        $fee_quantity = $setting->setDecimal($fee_quantity, $dp_credit_type);

        // echo "\n trading_fee_arr";
        // print_r($trading_fee_arr);
        $insert_data = array(
            "quantity" => $quantity,
            "price" => $price_type == "fix" ? $effective_price : '',
            "price_unit" => $price_unit ? $price_unit : '',
            "user_id" => $user_id,
            "status" => $status,
            "type" => $type,
            "crypto_currency" => $crypto_currency,
            "currency" => $final_currency,
            "is_cryptocurrency" => $is_c2c ? 1 : 0,
            "min" => $min,
            "max" => $max,
            "limit_currency" => $currency,
            "price_type" => $price_type,
            "floating_ratio" => $floating_ratio ? $floating_ratio : '',
            "max_processing_orders" => $max_processing_orders,
            "price_limit" => $minimum_price ? $minimum_price : '',
            "expires_at" => $expires_at,
            "remarks" => $remarks,
            "info" => $info,
            "fee_quantity" => $fee_quantity,
            "fee_type" => $fee_type,
            "created_at" => $date,
            "updated_at" => $date,
        );

        //  check balance
        $xunUserService = new XunUserService($db);
        $user_address_data = $xunUserService->getActiveAddressByUserIDandType($user_id, "personal", "id, address, user_id");
        $user_address = $user_address_data['address'];
        $user_wallet_balance = $xunCrypto->get_wallet_balance($user_address, $crypto_currency);
        //$user_wallet_balance = 10000;
        $total_escrow_quantity = bcadd((string)$quantity, (string)$fee_quantity, $crypto_decimal_places);

        if(!$user_wallet_balance || ($user_wallet_balance < $total_escrow_quantity)){
            $error_message = "Insufficient balance.";
            $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
            return array("code" => 0, "message" => "FAILED", "message_d" => $error_message);
        }
        
        $row_id = $db->insert("xun_marketplace_advertisement", $insert_data);
        if ($row_id) {
            // save payment method

            if (!$is_c2c) {
                $this->save_payment_method($new_payment_method, $row_id);
            }

            $create_order_result = $this->store_create_advertisement_order_transaction($row_id, $insert_data, "create_ad");
            $order_id = $create_order_result["order_id"];
            $escrow_address = $setting->systemSetting["marketplaceEscrowWalletAddress"];

            $return_data = array(
                "advertisement_id" => $row_id,
                "quantity" => $quantity,
                "advertisement_order_id" => $order_id,
                "escrow_address" => $escrow_address,
                "crypto_currency" => $crypto_currency,
                "username" => $username,
                "nickname" => $xun_user["nickname"],
                "total_escrow_quantity" => $total_escrow_quantity
            );

            $trading_fee_order = $this->store_create_advertisement_order_transaction($row_id, $insert_data, "advertisement_trading_fee");

            $trading_fee_order_id = $trading_fee_order["order_id"];

            if ($fee_quantity > 0) {
                $return_data["trading_fee"] = array(
                    "quantity" => $fee_quantity,
                    "crypto_currency" => $fee_type,
                    "order_id" => $trading_fee_order_id,
                    "fee_rate" => $tnc_trading_fee ? $trading_fee_arr["tnc_rate"] : "1.00000000",
                );
            }
            /**
             * data: {
             *   trading_fee: {quantity: '', crypto_currency: '', order_id: ''}
             * }
             */


            $tag = "Create Sell Advertisement";

            $content = "Username: " . $xun_user["nickname"] . "\n";
            $content .= "Phone number: " . $username . "\n";
            $content .= "IP: " . $ip . "\n";
            $content .= "Country: " . ucfirst($owner_country) . "\n";
            $content .= "Device: " . $device_os . "\n";
            $content .= "Advertisement ID: " . $row_id . "\n";
            $content .= "Sell: " . ucfirst($crypto_currency) . "\n";
            $content .= "Accept with: " . ucfirst(str_replace("##", ", ", $final_currency)) . "\n";
            $content .= "Price setting: " . $price_setting . "\n";
            if($price_type == "floating"){
                $content .= "Min Price: " . $minimum_price . "\n";
            }
            $content .= "Transaction Limit: " . $min_cryptocurrency . " - " . $max . "\n";
            
            $content .= "\nTime: " . $date;

            $erlang_params = array();
            $erlang_params["tag"] = $tag;
            $erlang_params["message"] = $content;
            $erlang_params["mobile_list"] = $xun_numbers;
            $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement created.", "data" => $return_data);
        } else {
            $error_message = "Internal server error.";
            $xmpp_result = $this->send_failed_sell_advertisement($newParams, $error_message);
            return array("code" => 0, "message" => "FAILED", "message_d" => $error_message);
        }
    }

    private function send_failed_sell_advertisement ($newParams, $error_message)
    {
        global $xunXmpp, $xun_numbers;

        $username = trim($newParams["username"]);
        $crypto_currency = trim($newParams["crypto_currency"]);
        //$currency_list = $newParams["currency"]; // array, remove trim
        $fix_price = trim($newParams["fix_price"]);
        $floating_ratio = trim($params["floating_ratio"]);
        //$price = trim($newParams["price"]);
        $minimum_price = trim($newParams["minimum_price"]);
        //$volume = trim($newParams["volume"]); // total ad quantity - cryptocurrency
        $max = trim($newParams["max_purchase"]); // max per order - cryptocurrency
        $min = trim($newParams["min_purchase"]); // cryptocurrency
        //$max_processing_orders = trim($newParams["max_processing_orders"]);
        //$remarks = trim($newParams["remarks"]);
        //$info = trim($newParams["info"]);
        $user_nickname = $newParams["nickname"];
        $ip = $newParams["ip"];
        $owner_country = $newParams["owner_country"];
        $device_os = $newParams["device_os"];
        $final_currency = $newParams["final_currency"];
        $price_type = $newParams["price_type"];
        
        $date = date("Y-m-d H:i:s");

        $tag = "Fail to Create Sell Advertisement";

        $content .= "Username: " . $user_nickname . "\n";
        $content .= "Phone number: " . $username . "\n";
        $confent .= "IP: " . $ip . "\n";    //
        $content .= "Country: " . ucfirst($owner_country) . "\n";
        $content .= "Device: " . $device_os . "\n"; //
        $content .= "Buy: " . ucfirst($crypto_currency) . "\n";
        $content .= "Pay with: " . ucfirst(str_replace("##", ", ", $final_currency)) . "\n";
        $content .= "Price setting: " . $price_type . "\n";
        //$content .= "Price: " . $price . "\n";
        if ($price_type == "floating"){
            $content .= "Min Price: " . $minimum_price . "\n";
        }
        $content .= "Transaction Limit: " . $min . " - " . $max . "\n";
        $content .= "\nMessage: " . $error_message . "\n";
        $content .= "Time: " . $date;

        //$erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");
        return $xmpp_result;
    }

    public function get_advertisement_order_transaction_table_name($advertisement_date)
    {
        $db = $this->db;

        $tblDate = date("Ymd", strtotime($advertisement_date));

        if (!trim($tblDate)) {
            $tblDate = date("Ymd");
        }

        $table_name = "xun_marketplace_advertisement_order_transaction_" . $db->escape($tblDate);
        return $table_name;
    }

    public function create_advertisement_order_transaction_daily_table($advertisement_date)
    {
        $db = $this->db;

        $table_name = $this->get_advertisement_order_transaction_table_name($advertisement_date);
        $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS " . $table_name . " LIKE xun_marketplace_advertisement_order_transaction");

        return $table_name;
    }

    private function store_refund_advertisement_transaction($advertisement, $refund_quantity)
    {
        global $setting;
        $db = $this->db;

        // create daily table if needed
        $date = $advertisement["created_at"];
        if ($advertisement["type"] == "sell") {
            $advertisement_order_transaction_table = $this->create_advertisement_order_transaction_daily_table($date);
            $order_id = $this->get_order_no();

            $insert_data = array(
                "advertisement_id" => $advertisement["id"],
                "order_id" => $order_id,
                "order_type" => "new_advertisement",
                "user_id" => $advertisement["user_id"],
                "type" => $advertisement["type"],
                "price" => $advertisement["price"],
                "quantity" => $refund_quantity,
                "status" => "refund",
                "expires_at" => '',
                "order_no" => $order_id,
                "disabled" => 0,
                "created_at" => $date,
                "updated_at" => $date,
            );

            $row_id = $db->insert($advertisement_order_transaction_table, $insert_data);
            return array("order_id" => $order_id);
        }
    }

    private function store_create_advertisement_order_transaction($advertisement_id, $advertisement, $transaction_type)
    {
        global $setting;
        $db = $this->db;

        // create daily table if needed

        $order_id = $this->get_order_no();

        $date = $advertisement["created_at"];

        $advertisement_order_transaction_table = $this->create_advertisement_order_transaction_daily_table($date);
        $marketplaceSellerTransactionExpiration = $setting->systemSetting["marketplaceSellerTransactionExpiration"];

        $seller_transfer_expiration = "$marketplaceSellerTransactionExpiration minutes";
        $expires_at = date("Y-m-d H:i:s", strtotime("+$seller_transfer_expiration", strtotime($date)));
        $expires_at_seconds = strtotime($seller_transfer_expiration, 0);

        $currency = $advertisement["type"] == "sell" ? $advertisement["crypto_currency"] : $advertisement["currency"];

        if ($transaction_type == "create_ad") {
            $order_type = "new_advertisement";
            $quantity = $advertisement["quantity"];
        } else if ($transaction_type == "advertisement_trading_fee") {
            $order_type = "advertisement_trading_fee";
            $quantity = $advertisement["fee_quantity"];
            $currency = $advertisement["fee_type"];
        }

        $status = bccomp((string) $quantity, "0", 8) < 1 ? "completed" : "pre_escrow";

        $insert_data = array(
            "advertisement_id" => $advertisement_id,
            "order_id" => $order_id,
            "order_type" => $order_type,
            "user_id" => $advertisement["user_id"],
            "type" => $advertisement["type"],
            "price" => $advertisement["price"],
            "quantity" => $quantity,
            "currency" => $currency,
            "status" => $status,
            "expires_at" => $expires_at,
            "order_no" => $order_id,
            "disabled" => 0,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $row_id = $db->insert($advertisement_order_transaction_table, $insert_data);
        return array("order_id" => $order_id, "expires_at" => $expires_at, "expires_at_seconds" => $expires_at_seconds);
    }

    public function process_trading_fee_order($advertisement_order_table, $params, $trading_fee_arr)
    {
        // print_r($advertisement_order_table);
        // print_r($params);
        // print_r($trading_fee_arr);
        global $setting;
        $db = $this->db;
        $trading_fee_order_id = $this->get_order_no();
        $trading_fee_quantity = $trading_fee_arr["trading_fee_quantity"];
        $trading_fee_currency = $trading_fee_arr["trading_fee_currency"];

        $insert_data = array(
            "advertisement_id" => $params["advertisement_id"],
            "order_id" => $trading_fee_order_id,
            "order_type" => "order_trading_fee",
            "user_id" => $params["user_id"],
            "type" => $params["type"],
            "price" => $params["price"] ? $params["price"] : "",
            "quantity" => $trading_fee_quantity,
            "currency" => $trading_fee_currency,
            "status" => "pre_escrow_fund_out",
            "expires_at" => $params["expires_at"] ? $params["expires_at"] : "",
            "order_no" => $trading_fee_order_id,
            "reference_order_id" => $params["order_id"],
            "disabled" => 0,
            "created_at" => date("Y-m-d, H:i:s"),
            "updated_at" => date("Y-m-d, H:i:s"),
        );

        $row_id = $db->insert($advertisement_order_table, $insert_data);
        if (!$row_id) {
            // print_r($db);
        }

        $trading_fee_wallet_address = $setting->systemSetting["marketplaceTradingFeeWalletAddress"];

        // echo "\n ###process_trading_fee_order";
        $trading_fee_escrow_return = $this->escrow_fund_out($params["advertisement_id"], $trading_fee_order_id, null, $trading_fee_quantity, $trading_fee_currency, $trading_fee_wallet_address);
    }

    public function get_place_advertisement_info($params)
    {
        global $setting, $xunCurrency;
        $db = $this->db;

        $username = trim($params["username"]);
        $type = trim($params["type"]);
        $currency = trim($params["currency"]);
        $crypto_currency = trim($params["crypto_currency"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Type cannot be empty");
        }

        if ($currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Currency cannot be empty");
        }

        if ($crypto_currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Cryptocurrency cannot be empty");
        }

        $currency = strtolower($currency);
        $crypto_currency = strtolower($crypto_currency);

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        // get user's payment method
        $db->where("a.user_id", $user_id);
        $db->where("a.status", 1);
        $db->join("xun_marketplace_payment_method b", "a.payment_method_id=b.id", "LEFT");
        $db->orderBy("b.id", "ASC");
        $user_payment_methods = $db->get("xun_marketplace_user_payment_method a", null, "a.id as id, b.id as payment_method_id, b.name as payment_method_name, b.image as payment_method_image, b.payment_type, b.country");
        $user_payment_methods = $user_payment_methods ? $user_payment_methods : [];

        // get supported currencies
        $db->where("status", 1);
        $xun_currencies = $db->map("currency_id")->ObjectBuilder()->get("xun_marketplace_currencies", null, "name, type, symbol, currency_id, image, fiat_currency_id");

        $currencies = [];
        $cryptocurrencies = [];

        $currencies_list = [];
        $cryptocurrencies_list = [];
        foreach ($xun_currencies as $curr_data) {
            $data = (array) $curr_data;
            if ($data["type"] == "currency") {
                $currencies[] = $data;
                $currencies_list[] = $data["currency_id"];
            } else {
                $cryptocurrencies[] = $data;
                $currency_id = strtolower($data["currency_id"]);
                $cryptocurrencies_list[$currency_id] = array("currency_id" => $currency_id, "fiat_currency_id" => $data["fiat_currency_id"], "type" => $data["type"]);
            }
        }

        $min_advertisement_value = $setting->systemSetting["marketplaceMinAdvertisementValue"];

        $decimal_place_setting = $xunCurrency->get_decimal_places();

        $fiat_decimal_places = $decimal_place_setting["fiat_decimal_places"];
        $fiat_dp_credit_type = $decimal_place_setting["fiat_credit_type"];

        $crypto_decimal_places = $decimal_place_setting["cryptocurrency_decimal_places"];
        $crypto_dp_credit_type = $decimal_place_setting["cryptocurrency_credit_type"];

        $full_currency_list = $xunCurrency->get_all_currency_rate($xun_currencies);

        $currency_rates_rounded = [];
        $crypto_currency_rates_rounded = [];
        $min_currency_list = [];
        $min_cryptocurrency_list = [];
        $decimal_places_arr = [];

        foreach ($currencies_list as $key) {
            $currency_rate = $full_currency_list[$key];
            $currency_rates_rounded[$key] = $currency_rate;

            $min_currency_value = bcdiv((string) $min_advertisement_value, (string) $currency_rate, $fiat_decimal_places);
            $min_currency_list[$key] = $min_currency_value;
            $decimal_places_arr[$key] = (string) $fiat_decimal_places;
        }

        foreach ($cryptocurrencies_list as $key => $crypto_arr) {
            $key_decimal_places = $xunCurrency->get_currency_decimal_places($key);
            $decimal_places_arr[$key] = (string) $key_decimal_places;

            $crypto_currency_rate = $full_currency_list[$key];
            $crypto_currency_rates_rounded[$key] = $crypto_currency_rate;

            $min_cryptocurrency_value = bcdiv((string) $min_advertisement_value, (string) $crypto_currency_rate, $key_decimal_places);

            $min_cryptocurrency_list[$key] = $min_cryptocurrency_value;
        }

        $price_arr = [];
        $lowest_market_price_arr = [];
        $highest_market_price_arr = [];

        //  get price for fiat currencies
        foreach ($currency_rates_rounded as $currency_k => $currency_v) {
            foreach ($crypto_currency_rates_rounded as $crypto_currency_k => $crypto_currency_v) {
                $name = "${currency_k}_${crypto_currency_k}";
                $price = $xunCurrency->get_rate($crypto_currency_k, $currency_k);

                $price_arr[$name] = $price;

                $lowest_market_price = $this->get_advertisement_lowest_highest_market_price("sell", $currency_k, $crypto_currency_k, $price, 0, $fiat_decimal_places, $currency_v);

                $highest_market_price = $this->get_advertisement_lowest_highest_market_price("buy", $currency_k, $crypto_currency_k, $price, 0, $fiat_decimal_places, $currency_v);

                $lowest_market_price_arr[$name] = $lowest_market_price;
                $highest_market_price_arr[$name] = $highest_market_price;
            }
        }

        $c2c_rate_arr = [];
        foreach ($crypto_currency_rates_rounded as $crypto_currency_k1 => $crypto_currency_v1) {
            foreach ($crypto_currency_rates_rounded as $crypto_currency_k2 => $crypto_currency_v2) {
                if ($crypto_currency_k1 == $crypto_currency_k2) {
                    continue;
                }

                $name = "${crypto_currency_k2}_${crypto_currency_k1}";
                $price = $xunCurrency->get_rate($crypto_currency_k1, $crypto_currency_k2);

                $price_arr[$name] = $price;

                $quote_decimal_places = $decimal_places_arr[$crypto_currency_k2];
                $lowest_market_price = $this->get_advertisement_lowest_highest_market_price("sell", $crypto_currency_k2, $crypto_currency_k1, $price, 1, $quote_decimal_places);

                $highest_market_price = $this->get_advertisement_lowest_highest_market_price("buy", $crypto_currency_k2, $crypto_currency_k1, $price, 1, $quote_decimal_places);

                $lowest_market_price_arr[$name] = $lowest_market_price;
                $highest_market_price_arr[$name] = $highest_market_price;
            }
        }

        $trading_fee = $setting->systemSetting["marketplaceTradingFee"];
        $tnc_trading_fee = $setting->systemSetting["marketplaceTNCTradingFee"];
        $advertisement_expiration_length = $setting->systemSetting["marketplaceAdvertisementExpiration"];

        $advertisement_expiration_days = explode(" ", $advertisement_expiration_length)[0];

        $user_currency = "${currency}_${crypto_currency}";
        $return_data = array(
            "currency" => $currency,
            "crypto_currency" => $crypto_currency,
            "payment_method" => $user_payment_methods,
            "price_list" => $price_arr,
            "lowest_market_price_list" => $lowest_market_price_arr,
            "highest_market_price_list" => $highest_market_price_arr,
            "price" => $price_arr[$user_currency],
            "lowest_market_price" => $lowest_market_price_arr[$user_currency],
            "highest_market_price" => $highest_market_price_arr[$user_currency],
            "min_advertisement_currency" => $min_currency_list,
            "min_advertisement_cryptocurrency" => $min_cryptocurrency_list,
            "exchange_rates" => $currency_rates_rounded,
            "cryptocurrency_rates" => $crypto_currency_rates_rounded,
            "trading_fee" => array(
                "thenuxcoin" => $trading_fee,
                "default" => $trading_fee,
            ),
            "advertisement_expiration_days" => $advertisement_expiration_days,
            "decimal_places" => $decimal_places_arr,
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement info.", "data" => $return_data);

    }

    private function get_advertisement_lowest_highest_market_price($type, $currency, $crypto_currency, $price, $is_c2c, $decimal_places, $currency_rate = null)
    {
        global $setting;
        $db = $this->db;
        // lowest or highest market price amongst all open advertisements

        $order = $type == "sell" ? "ASC" : "DESC";
        $db->where("status", "new");
        $db->where("type", $type);
        $db->where("price_type", "fix");
        if ($is_c2c === 1) {
            $db->where("currency", $currency);
        } else {
            $db->where("currency", "%$currency%", "like");
        }
        $db->where("crypto_currency", $crypto_currency);
        $db->orderBy("price", $order);

        $fix_price_advertisement = $db->getOne("xun_marketplace_advertisement", 'price');

        $db->where("status", "new");
        $db->where("type", $type);
        $db->where("price_type", "floating");
        if ($is_c2c === 1) {
            $db->where("currency", $currency);
        } else {
            $db->where("currency", "%$currency%", "like");
        }$db->where("crypto_currency", $crypto_currency);
        $db->orderBy("floating_ratio", $order);

        $floating_price_advertisement = $db->getOne("xun_marketplace_advertisement");

        if ($is_c2c === 1) {
            $currency_rate = 1;
        }

        if ($type == "sell") {
            $lowest_price = null;

            if ($fix_price_advertisement) {
                //  get crypto/currency conversion rate
                $lowest_price = bcdiv((string) $fix_price_advertisement["price"], (string) $currency_rate, $decimal_places);
            }

            if ($floating_price_advertisement) {
                $floating_ratio = $floating_price_advertisement["floating_ratio"];
                $lowest_floating_price = $price + ($price * ($floating_ratio / 100));
                $lowest_price = $lowest_price == null ? $lowest_floating_price : ($lowest_floating_price < $lowest_price ? $lowest_floating_price : $lowest_price);
            }

            if ($lowest_price) {
                $lowest_price = $setting->setDecimal($lowest_price, $round_sf);
                $lowest_price = bcmul((string) $lowest_price, "1", $decimal_places);
            }
            return $lowest_price;
        } else {
            $highest_price = null;
            if ($fix_price_advertisement) {
                $highest_price = bcdiv((string) $fix_price_advertisement["price"], (string) $currency_rate, $decimal_places);
            }

            if ($floating_price_advertisement) {
                $floating_ratio = $floating_price_advertisement["floating_ratio"];
                $highest_floating_price = $price + ($price * ($floating_ratio / 100));
                $highest_price = $highest_price == null ? $highest_floating_price : ($highest_floating_price > $highest_price ? $highest_floating_price : $highest_price);
            }

            if ($highest_price) {
                $highest_price = bcdiv((string) $highest_price, "1", $decimal_places);
            }

            return $highest_price;
        }
    }

    public function get_advertisement_cryptocurrency_price($params)
    {
        global $setting;
        $db = $this->db;

        $currency = trim($params["currency"]);
        $crypto_currency = trim($params["crypto_currency"]);
        $type = trim($params["type"]);

        if ($type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Type cannot be empty");
        }

        if (!($type == "sell" || $type == "buy")) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid value for type.");
        }

        if ($currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Currency cannot be empty");
        }

        if ($crypto_currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Cryptocurrency cannot be empty");
        }

        // get price and lowest/highest market price
        $currency_rate = $this->get_currency_rate_in_usd($currency);
        $cryptocurrency_rate = $this->get_cryptocurrency_rate($crypto_currency);
        $currency_rate = $setting->setDecimal($currency_rate, "marketplacePrice");
        $cryptocurrency_rate = $setting->setDecimal($cryptocurrency_rate, "marketplacePrice");

        $price = $this->get_effective_floating_price($crypto_currency, $currency, $cryptocurrency_rate, $currency_rate);

        $lowest_market_price = $this->get_advertisement_lowest_highest_market_price("sell", $currency, $crypto_currency, $price, $currency_rate);
        $highest_market_price = $this->get_advertisement_lowest_highest_market_price("buy", $currency, $crypto_currency, $price, $currency_rate);

        $return_data = array("currency" => $currency, "crypto_currency" => $crypto_currency, "price" => $price);

        $return_data["lowest_market_price"] = $lowest_market_price;
        $return_data["highest_market_price"] = $highest_market_price;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement info.", "data" => $return_data);
    }

    private function save_payment_method($payment_method_array, $advertisement_id)
    {
        $db = $this->db;
        $date = date('Y-m-m H:i:s');

        foreach ($payment_method_array as $data) {
            $insert_data = array(
                "advertisement_id" => $advertisement_id,
                "payment_method_id" => $data["id"],
                "account_name" => $data["account_name"],
                "account_no" => $data["account_no"],
                "qr_code" => $data["qr_code"],
                "status" => 1,
                "created_at" => $date,
                "updated_at" => $date,
            );

            $db->insert("xun_marketplace_advertisement_payment_method", $insert_data);
        }
    }

    public function get_advertisement_listing($params)
    {
        global $setting, $xunCurrency;
        $db = $this->db;
        $general = $this->general;

        $status = "new";

        // $page_limit = $setting->systemSetting["appsPageLimit"];
        //  temporary fix
        $page_limit = '50';

        $type = trim($params["type"]);
        $currency = trim($params["currency"]);
        $crypto_currency = trim($params["crypto_currency"]);
        $id = trim($params["last_id"]);
        $page_number = $params["page"];
        // $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = strtoupper(trim($params["order"]));

        $page_size = '100';
        if ($type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement type cannot be empty.");
        }
        if ($currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Currency cannot be empty.");
        }
        if ($crypto_currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Cryptocurrency cannot be empty.");
        }

        $currency = strtolower($currency);
        $crypto_currency = strtolower($crypto_currency);

        $db->where("currency_id", [$crypto_currency, $currency], "in");
        $db->where("status", 1);
        $supported_currencies = $db->map("currency_id")->ObjectBuilder()->get("xun_marketplace_currencies", null, "currency_id, type, fiat_currency_id");

        if (!$supported_currencies) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid currency.", "errorCode" => -100);
        }

        $marketplace_currency = (array) $supported_currencies[$currency];
        $currency_type = $marketplace_currency["type"];

        if ($currency_type == "currency") {
            $is_c2c = 0;
        } else {
            $is_c2c = 1;
        }

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        if ($id) {
            // if ($order == 'DESC') {
            //     $db->where("id", $id, '<');
            // } else {
            //     $db->where("id", $id, '>');
            // }
            $totalRecord = 0;
            $page_number = 1;
            $returnData["result"] = [];
            $returnData["totalRecord"] = $totalRecord;
            $returnData["numRecord"] = (int) $page_size;
            $returnData["totalPage"] = ceil($totalRecord / $page_size);
            $returnData["pageNumber"] = $page_number;
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement listing.", "data" => $returnData);
        }

        if ($is_c2c === 1) {
            // compare full string for cryptocurrency
            $db->where("currency", $currency);
        } else {
            $db->where("currency", "%$currency%", "like");
        }
        $db->where("crypto_currency", $crypto_currency);

        $db->where("type", $type);
        $db->where("status", "new");
        $db->where("sold_out", 0);
        $db->where("is_cryptocurrency", $is_c2c);

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = 0;
        $limit = array($start_limit, $page_size);

        $copyDb = $db->copy();
        // $db->orderBy("created_at", $order);

        $result = $db->get("xun_marketplace_advertisement", $limit);
        $return_message = "Advertisement listing.";
        $result = $result ? $result : array();

        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_marketplace_advertisement", "count(id)");

        $new_result = [];

        $max_rating = $setting->systemSetting["marketplaceMaxUserRating"];

        $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);

        $crypto_price_in_usd = $full_currency_list[$crypto_currency];
        $currency_rate_in_usd = $full_currency_list[$currency];
        if ($currency_type == "currency") {
            $currency_rate_arr = array($currency => $currency_rate_in_usd);
            foreach ($result as $advertisement) {
                $advertisement_id = $advertisement["id"];

                $advertisement_data = $this->compose_advertisement_data($advertisement);
                $advertisement_data["currency"] = $currency;
                $advertisement_data["status"] = $advertisement["status"];

                $limit_currency = $advertisement["limit_currency"];
                if ($currency_rate_arr[$limit_currency]) {
                    $limit_currency_rate = $currency_rate_arr[$limit_currency];
                } else {
                    $limit_currency_rate = $this->get_currency_rate_in_usd($limit_currency);
                    $currency_rate_arr[$limit_currency] = $limit_currency_rate;
                }

                $advertisement_final_price = $this->get_advertisement_effective_price($advertisement, $currency, $crypto_price_in_usd, $currency_rate_in_usd, $limit_currency_rate);

                $price = $advertisement_final_price["price_in_currency"];
                $price = $setting->setDecimal($price, "marketplacePrice");

                $advertisement_data["price"] = (string) $price;

                $remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement);

                $max_order = $advertisement["max"];

                $max_order_quantity = (bccomp((string) $max_order, (string) $remaining_quantity, 8) > 0 || bccomp((string) $max_order, "0", 8) == 0) ? $remaining_quantity : $max_order;

                $max = bcmul((string) $max_order_quantity, (string) $price, 2);
                $min = $advertisement["min"];

                // if buy ad, min is coin, sell ad, min is currency
                if ($advertisement["type"] == "sell") {
                    $min_in_currency = bcmul((string) ($min / $limit_currency_rate), (string) $currency_rate_in_usd, 8);
                    $min_cryptocurrency = bcdiv((string) $min_in_currency, (string) $price, 8);
                    $max_cryptocurrency = $max_order_quantity;
                    $min_currency = $min_in_currency;
                    $max_currency = $max;

                    if ($max < $min_currency && $max_cryptocurrency == $min_cryptocurrency) {
                        $max_currency = $min_currency;
                    }

                } else {
                    $min_currency = $min * $price;
                    $min_cryptocurrency = $min;
                    $max_cryptocurrency = $max_order_quantity;
                    $max_currency = $max;
                }

                $min_currency = $setting->setDecimal($min_currency, "marketplacePrice");
                $max_currency = $setting->setDecimal($max_currency, "marketplacePrice");

                $advertisement_data["min_cryptocurrency"] = $min_cryptocurrency;
                $advertisement_data["max_cryptocurrency"] = $max_cryptocurrency;
                $advertisement_data["min"] = $min_currency;
                $advertisement_data["max"] = $max_currency;

                $payment_method_arr = $this->get_advertisement_payment_method($advertisement_id, null, $advertisement["is_cryptocurrency"]);

                $advertisement_data["payment_method"] = $payment_method_arr;

                // get user rating and total trade
                $user_marketplace_data = $this->get_user_marketplace_data($advertisement["user_id"]);
                $user_rating = $user_marketplace_data["avg_rating"];
                $advertisement_data["user_rating"] = $user_rating;
                $advertisement_data["max_rating"] = $max_rating;

                $total_trade = $user_marketplace_data["total_trade"];
                $advertisement_data["total_trade"] = $total_trade;

                $new_result[] = $advertisement_data;
            }
        } else {
            foreach ($result as $advertisement) {
                $advertisement_id = $advertisement["id"];

                $advertisement_data = $this->compose_advertisement_data($advertisement);
                $advertisement_data["currency"] = $currency;
                $advertisement_data["status"] = $advertisement["status"];

                // $currency_rate_in_usd = $this->get_cryptocurrency_rate($currency);
                $currency_rate_in_usd = $xunCurrency->get_rate($currency, "usd");
                $c2c_price = $this->get_cryptocurrency_price($currency, $crypto_currency, $supported_currencies, $full_currency_list);

                $advertisement_final_price = $this->get_advertisement_effective_price($advertisement, $currency, $crypto_price_in_usd, $currency_rate_in_usd, null, $c2c_price);

                $price = $advertisement_final_price;

                $advertisement_data["price"] = (string) $price;

                $remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement);

                $min = $advertisement["min"];
                $max_order = $advertisement["max"];

                $max_order_quantity = (bccomp((string) $max_order, (string) $remaining_quantity, 8) > 0 || bccomp((string) $max_order, "0", 8) == 0) ? $remaining_quantity : $max_order;

                if ($type == "buy") {
                    $min_cryptocurrency = $min;
                    $max_currency = $max_order_quantity;
                    $max_cryptocurrency = bcdiv((string) $max_currency, (string) $price, 8);
                    $min_currency = bcmul((string) $min, (string) $price, 8);
                } else {
                    $min_currency = $min;
                    $max_cryptocurrency = $max_order_quantity;
                    $max_currency = bcmul((string) $max_cryptocurrency, (string) $price, 8);
                    $min_cryptocurrency = bcdiv((string) $min_currency, (string) $price, 8);
                }

                $advertisement_data["max_cryptocurrency"] = $max_cryptocurrency;
                $advertisement_data["min_cryptocurrency"] = $min_cryptocurrency;
                $advertisement_data["max"] = $max_currency;
                $advertisement_data["min"] = $min_currency;

                $payment_method_arr = $this->get_advertisement_payment_method($advertisement_id, null, $advertisement["is_cryptocurrency"]);

                $advertisement_data["payment_method"] = $payment_method_arr;

                // get user rating and total trade
                $user_marketplace_data = $this->get_user_marketplace_data($advertisement["user_id"]);
                $user_rating = $user_marketplace_data["avg_rating"];
                $advertisement_data["user_rating"] = $user_rating;
                $advertisement_data["max_rating"] = $max_rating;

                $total_trade = $user_marketplace_data["total_trade"];
                $advertisement_data["total_trade"] = $total_trade;

                $new_result[] = $advertisement_data;
            }
        }

        if ($type == "sell") {
            $keys = array_map(function ($val) {return $val['price'];}, $new_result);
            array_multisort($keys, $new_result);
        } else {
            $keys = array_map(function ($val) {return $val['price'] * -1;}, $new_result);
            array_multisort($keys, $new_result);
        }
        $returnData["result"] = $new_result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = (int) $page_size;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        $returnData["pageNumber"] = $page_number;
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement listing.", "data" => $returnData);
    }

    public function get_user_advertisement_listing($params)
    {
        global $setting, $xunCurrency;
        $db = $this->db;
        $general = $this->general;
        $status = "new";

        $page_limit = $setting->systemSetting["appsPageLimit"];

        $username = trim($params["username"]);
        $type = trim($params["type"]);
        $status = trim($params["status"]);
        $id = trim($params["last_id"]);
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = strtoupper(trim($params["order"]));

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];
        $date = date("Y-m-d H:i:s");

        if ($id) {
            if ($order == 'DESC') {
                $db->where("id", $id, '<');
            } else {
                $db->where("id", $id, '>');
            }
        }

        if ($type) {
            $db->where("type", $type);
        }

        if ($status) {
            switch ($status) {
                case "active":
                    $db->where("((status = ? and expires_at > NOW()) or status = ?)", array("new", "pending_escrow"));
                    break;

                case "inactive":
                    $db->where("status", ["closed", "cancelled"], "in");
                    break;

                case "expired":
                    $db->where("(status = ? or (status = ? and expires_at < NOW()))", array("expired", "new"));
                    break;

                default:
                    $db->where("status", "pre_escrow", "!=");
                    break;
            }
        } else {
            $db->where("status", "pre_escrow", "!=");
        }

        $db->where("user_id", $user_id);

        $start_limit = 0;
        $limit = array($start_limit, $page_size);

        $copyDb = $db->copy();
        $dist_crypto_db = $db->copy();
        $dist_currency_db = $db->copy();
        $db->orderBy("created_at", $order);

        $result = $db->get("xun_marketplace_advertisement", $limit);
        $return_message = "Advertisement listing.";
        $result = $result ? $result : array();

        /**
         * data out:
         * -    ad id
         * -    created at
         * -    type - sell/buy
         * -    currency
         * -    cryptocurrency
         * -    order number
         * -    price
         * -    limit - min/max
         * -    expires at
         * -    status
         *
         */

        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_marketplace_advertisement", "count(id)");

        // get possible coin and cryptocurrency
        if ($result) {
            $distinct_crypto_currency = $dist_crypto_db->getValue("xun_marketplace_advertisement", "distinct(crypto_currency)", null);
            $advertisement_currencies = $dist_currency_db->getValue("xun_marketplace_advertisement", "distinct(currency)", null);

            $distinct_currency = [];

            foreach ($advertisement_currencies as $curr) {
                $explode = explode("##", $curr);
                $distinct_currency[] = $explode[0];
            }

            $distinct_currency = array_unique($distinct_currency);

            $distinct_crypto_currency = array_unique(array_merge($distinct_currency, $distinct_crypto_currency));

            $merged_currencies_arr = array_merge($distinct_crypto_currency, $distinct_currency);
            $db->where("currency_id", $merged_currencies_arr, "in");
            $supported_currencies = $db->map("currency_id")->ObjectBuilder()->get("xun_marketplace_currencies", null, "currency_id, type, fiat_currency_id");

            $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);
        }

        $new_result = [];

        foreach ($result as $advertisement) {
            $advertisement_id = $advertisement["id"];

            $advertisement_data = $this->compose_advertisement_data($advertisement);

            if ($status == "expired") {
                $advertisement_data["status"] = "expired";

            } else {
                $advertisement_data["status"] = $advertisement["status"];
            }

            $currency_list = explode("##", $advertisement["currency"]);

            $currency = $currency_list[0];
            $advertisement_data["currency"] = $currency;
            $advertisement_data["currency_list"] = $currency_list;
            $crypto_currency = $advertisement["crypto_currency"];
            $price_type = $advertisement["price_type"];
            $price = $advertisement["price"];
            $floating_ratio = $advertisement["floating_ratio"];

            $crypto_price_in_usd = $full_currency_list[$crypto_currency];

            if ($advertisement["is_cryptocurrency"]) {
                $currency_rate_in_usd = $full_currency_list[$currency];

                $c2c_price = $this->get_cryptocurrency_price($currency, $crypto_currency, $supported_currencies, $full_currency_list);

                $advertisement_final_price = $this->get_advertisement_effective_price($advertisement, $currency, $crypto_price_in_usd, $currency_rate_in_usd, null, $c2c_price);

                $price = $advertisement_final_price;

                $advertisement_data["price"] = (string) $price;

                $remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement);

                $min = $advertisement["min"];
                $max_order = $advertisement["max"];

                $max_order_quantity = (bccomp((string) $max_order, (string) $remaining_quantity, 8) > 0 || bccomp((string) $max_order, "0", 8) == 0) ? $remaining_quantity : $max_order;

                if ($advertisement["type"] == "buy") {
                    $min_cryptocurrency = $min;
                    $max_currency = $max_order_quantity;
                    $max_cryptocurrency = bcdiv((string) $max_currency, (string) $price, 8);
                    $min_currency = bcmul((string) $min, (string) $price, 8);
                } else {
                    $min_currency = $min;
                    $max_cryptocurrency = $max_order_quantity;
                    $max_currency = bcmul((string) $max_cryptocurrency, (string) $price, 8);
                    $min_cryptocurrency = bcdiv((string) $min_currency, (string) $price, 8);
                }

            } else {
                $currency_rate_in_usd = $full_currency_list[$currency];

                $price = $this->get_advertisement_price($advertisement, $currency, $crypto_price_in_usd, $currency_rate_in_usd);

                $price = $setting->setDecimal($price, "marketplacePrice");

                $advertisement_data["price"] = (string) $price;

                $remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement);

                $max_order = $advertisement["max"];

                $max_order_quantity = (bccomp((string) $max_order, (string) $remaining_quantity, 8) > 0 || bccomp((string) $max_order, "0", 8) == 0) ? $remaining_quantity : $max_order;

                $max = $max_order_quantity * $price;
                $min = $advertisement["min"];

                // if buy ad, min is coin, sell ad, min is currency
                if ($advertisement["type"] == "sell") {
                    $min_cryptocurrency = bcdiv((string) $min, (string) $price, 8);
                    $max_cryptocurrency = $max_order_quantity;
                    $min_currency = $min;
                    $max_currency = $max;

                } else {
                    $min_currency = $min * $price;
                    $min_cryptocurrency = $min;
                    $max_cryptocurrency = $max_order_quantity;
                    $max_currency = $max;
                }

                $min_currency = $setting->setDecimal($min_currency, "marketplacePrice");
                $max_currency = $setting->setDecimal($max_currency, "marketplacePrice");
            }

            $advertisement_data["min_cryptocurrency"] = $min_cryptocurrency;
            $advertisement_data["max_cryptocurrency"] = $max_cryptocurrency;
            $advertisement_data["min"] = $min_currency;
            $advertisement_data["max"] = $max_currency;

            $total_active_order = $this->get_advertisement_orders_count($advertisement);

            $advertisement_data["total_order"] = (string) $total_active_order;
            $new_result[] = $advertisement_data;
        }

        $returnData["result"] = $new_result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = (int) $page_size;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        // $returnData["pageNumber"] = $page_number;
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement listing.", "data" => $returnData);
    }

    public function get_user_advertisement_details($params)
    {
        global $setting, $xunCurrency;
        $db = $this->db;

        $username = trim($params["username"]);
        $advertisement_id = trim($params["id"]);
        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty.");
        }

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty.");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }
        if ($advertisement["user_id"] != $user_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }

        $date = date("Y-m-d H:i:s");

        $advertisement_status = $advertisement["status"];
        if ($advertisement_status == "pre_escrow") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        } else if (!in_array($advertisement_status, array("expired", "cancelled", "closed", "pending_escrow"))) {
            // update advertisement status to expired
            if ($advertisement["expires_at"] < $date) {
                $update_data = [];
                $update_data["status"] = "expired";
                $update_data["updated_at"] = $date;

                $db->where("id", $advertisement_id);
                $db->update("xun_marketplace_advertisement", $update_data);

                $advertisement_status = "expired";

                // refund expired advertisement
                $this->escrow_refund_advertisement($advertisement);
            }
        }

        $price_type = $advertisement["price_type"];
        $advertisement_currency = $advertisement["currency"];
        $crypto_currency = $advertisement["crypto_currency"];
        $price = $advertisement["price"];
        $floating_ratio = $advertisement["floating_ratio"];

        $currency_list = explode("##", $advertisement_currency);
        $currency = $currency_list[0];

        $advertisement_data = $this->compose_advertisement_data($advertisement);
        $advertisement_data["remarks"] = $advertisement["remarks"];
        $advertisement_data["info"] = $advertisement["info"];
        // $advertisement_data["price"] = (string) $final_price;
        $advertisement_data["currency"] = $currency;
        $advertisement_data["currency_list"] = $currency_list;

        $db->where("currency_id", [$crypto_currency, $currency], "in");
        $supported_currencies = $db->map("currency_id")->ObjectBuilder()->get("xun_marketplace_currencies", null, "currency_id, type, fiat_currency_id");

        $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);

        $crypto_price_in_usd = $full_currency_list[$crypto_currency];
        // min_cryptocurrency, max_cryptocurrency, max_currency, min_currency
        $remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement);
        // $max = $remaining_quantity * $final_price;
        $min = $advertisement["min"];
        $max_order = $advertisement["max"];

        $max_order_quantity = (bccomp((string) $max_order, (string) $remaining_quantity, 8) > 0 || bccomp((string) $max_order, "0", 8) == 0) ? $remaining_quantity : $max_order;

        if ($advertisement["is_cryptocurrency"]) {
            $currency_rate_in_usd = $full_currency_list[$currency];
            $c2c_price = $this->get_cryptocurrency_price($currency, $crypto_currency, $supported_currencies, $full_currency_list);

            $advertisement_final_price = $this->get_advertisement_effective_price($advertisement, $currency, $crypto_price_in_usd, $currency_rate_in_usd, $currency_rate_in_usd, $c2c_price);

            $final_price = $setting->setDecimal($advertisement_final_price);
            $price_list = array($currency => $final_price);

            if ($advertisement["type"] == "buy") {
                $advertisement_quantity = $advertisement["quantity"];
                $c2c_quantity = bcdiv((string) $advertisement_quantity, (string) $final_price, 8);
                $advertisement_data["quantity"] = $c2c_quantity;
                $min_cryptocurrency = $min;
                $max_currency = $max_order_quantity;

                $max_cryptocurrency = bcdiv((string) $max_currency, (string) $final_price, 8);
                $min_currency = bcmul((string) $min, (string) $final_price, 8);
            } else {
                $min_currency = $min;
                $max_cryptocurrency = $max_order_quantity;

                $max_currency = bcmul((string) $max_cryptocurrency, (string) $final_price, 8);
                $min_cryptocurrency = bcdiv((string) $min_currency, (string) $final_price, 8);
            }
            $min_currency_list = array($currency => $min_currency);
            $max_currency_list = array($currency => $max_currency);
            $advertisement_data["crypto_rate"] = $crypto_price_in_usd;
            $advertisement_data["currency_rate"] = $currency_rate_in_usd;
        } else {
            $exchange_rate = [];
            $price_list = [];
            $min_currency_list = [];
            $max_currency_list = [];

            $currency_rate_in_usd = $full_currency_list[$currency];
            $advertisement_final_price = $this->get_advertisement_effective_price($advertisement, $currency, $crypto_price_in_usd, $currency_rate_in_usd, $currency_rate_in_usd);
            $price_in_usd = $advertisement_final_price["price_in_usd"];
            foreach ($currency_list as $curr) {
                // get price list for currencies
                $currency_rate_in_usd = $this->get_currency_rate_in_usd($curr);
                if ($curr == $advertisement["limit_currency"]) {
                    $price = $advertisement_final_price["price_in_currency"];
                } else {
                    $price = $price_in_usd * $currency_rate_in_usd;
                    $price = $setting->setDecimal($price, "marketplacePrice");
                }
                $price_list[$curr] = $price;
                $exchange_rate[$curr] = $currency_rate_in_usd;
            }

            $final_price = $price_list[$currency];

            // if buy ad, min is coin, sell ad, min is currency
            if ($advertisement["type"] == "sell") {
                $min_cryptocurrency = bcdiv((string) $min, (string) $final_price, 8);
                $max_cryptocurrency = $max_order_quantity;
                $min_in_usd = $min / $exchange_rate[$currency];
                foreach ($price_list as $curr => $price) {
                    $max_currency = bcmul((string) $max_order_quantity, (string) $price, 2);

                    $curr_exchange_rate = $exchange_rate[$curr];
                    $min_currency = bcmul((string) $min_in_usd, (string) $curr_exchange_rate, 2);

                    if ($max_currency < $min_currency && $max_cryptocurrency >= $min_cryptocurrency) {
                        $max_currency = $min_currency;
                    }
                    $min_currency_list[$curr] = $min_currency;
                    $max_currency_list[$curr] = $max_currency;
                }

            } else {
                foreach ($price_list as $curr => $price) {
                    $min_currency = bcmul((string) $min, (string) $price, 2);
                    $min_currency_list[$curr] = $min_currency;

                    $max_currency = bcmul((string) $max_order_quantity, (string) $price, 2);
                    $max_currency_list[$curr] = $max_currency;
                }
                $min_cryptocurrency = $min;
                $max_cryptocurrency = $max_order_quantity;
            }

        }
        $advertisement_data["min_cryptocurrency"] = $min_cryptocurrency;
        $advertisement_data["max_cryptocurrency"] = $max_cryptocurrency;
        $advertisement_data["min"] = $min_currency_list[$currency];
        $advertisement_data["max"] = $max_currency_list[$currency];

        $advertisement_data["min_currencies"] = $min_currency_list;
        $advertisement_data["max_currencies"] = $max_currency_list;
        $advertisement_data["price_list"] = $price_list;
        $advertisement_data["price"] = $final_price;

        $payment_method_arr = $this->get_advertisement_payment_method($advertisement_id, null, $advertisement["is_cryptocurrency"]);

        $advertisement_data["payment_method"] = $payment_method_arr;
        $advertisement_data["status"] = $advertisement_status;

        $total_active_order = $this->get_advertisement_orders_count($advertisement);

        $advertisement_data["total_order"] = (string) $total_active_order;

        /**
         * order details
         * -    inprogress
         * -    cancelled
         * -    completed
         */

        $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement["created_at"]);

        $in_progress_orders = 0;
        $completed_orders = 0;
        $cancelled_orders = 0;

        $total_completed_quantity = 0;
        $total_amount_traded = 0;

        if ($db->tableExists($advertisement_order_table)) {
            $db->where("advertisement_id", $advertisement["id"]);
            $db->where("disabled", 0);
            $db->where("order_type", "place_order");

            $advertisement_orders = $db->get($advertisement_order_table, null, "id, expires_at, status, quantity, price, currency");
            if ($advertisement["type"] == "sell") {
                foreach ($advertisement_orders as $advertisement_order) {
                    $order_status = $advertisement_order["status"];
                    $order_expires_at = $advertisement_order["expires_at"];

                    if ($order_status == "completed") {
                        $completed_orders += 1;
                        $order_quantity = $advertisement_order["quantity"];
                        $order_price = $advertisement_order["price"];
                        $total_completed_quantity += $order_quantity;

                        $order_amount = $order_quantity * $order_price;
                        $order_amount = $setting->setDecimal($order_amount, "marketplacePrice");
                        $total_amount_traded += $order_amount;

                    } else if (in_array($order_status, array("cancelled", "expired")) || ($order_status == "pending_payment" && $order_expires_at < date("Y-m-d H:i:s"))) {
                        $cancelled_orders += 1;
                    } else {
                        $in_progress_orders += 1;
                    }
                }
            } else if ($advertisement["type"] == "buy" && $advertisement["is_cryptocurrency"] === 1) {
                foreach ($advertisement_orders as $advertisement_order) {
                    $order_status = $advertisement_order["status"];
                    $order_expires_at = $advertisement_order["expires_at"];

                    if ($order_status == "completed") {
                        $completed_orders += 1;
                        $order_quantity = $advertisement_order["quantity"];
                        $order_price = $advertisement_order["price"];
                        $total_completed_quantity += $order_quantity;
                        $order_amount = bcdiv((string) $order_quantity, (string) $order_price, 8);
                        $order_amount = $setting->setDecimal($order_amount);

                        $total_amount_traded += $order_amount;

                    } else if (in_array($order_status, array("paid", "coin_released")) || ($order_status == "pending_payment" && $order_expires_at > date("Y-m-d H:i:s"))) {
                        $in_progress_orders += 1;

                    } else if (in_array($order_status, array("cancelled", "expired", "refunded")) || ($order_status == "pending_payment" && $order_expires_at < date("Y-m-d H:i:s"))) {
                        $cancelled_orders += 1;
                    }
                }
            } else {
                foreach ($advertisement_orders as $advertisement_order) {
                    $order_status = $advertisement_order["status"];
                    $order_expires_at = $advertisement_order["expires_at"];

                    if ($order_status == "completed") {
                        $completed_orders += 1;
                        $order_quantity = $advertisement_order["quantity"];
                        $order_price = $advertisement_order["price"];
                        $total_completed_quantity += $order_quantity;
                        $order_amount = $order_quantity * $order_price;
                        $order_amount = $setting->setDecimal($order_amount, "marketplacePrice");
                        $total_amount_traded += $order_amount;

                    } else if (in_array($order_status, array("paid", "coin_released")) || ($order_status == "pending_payment" && $order_expires_at > date("Y-m-d H:i:s"))) {
                        $in_progress_orders += 1;

                    } else if (in_array($order_status, array("cancelled", "expired", "refunded")) || ($order_status == "pending_payment" && $order_expires_at < date("Y-m-d H:i:s"))) {
                        $cancelled_orders += 1;
                    }
                }
            }
        }

        $order_counts = array(
            "in_progress" => (string) $in_progress_orders,
            "completed" => (string) $completed_orders,
            "cancelled" => (string) $cancelled_orders,
        );
        $advertisement_data["order"] = $order_counts;

        if ($advertisement["type"] == "buy" && $advertisement["is_cryptocurrency"] === 1) {
            $quantity_left = $c2c_quantity - $total_amount_traded;
            $quantity_left = $setting->setDecimal($quantity_left);
            $total_amount_traded_in_currency = $setting->setDecimal($total_completed_quantity);
            $total_completed_quantity = $setting->setDecimal($total_amount_traded);
        } else {

            $total_completed_quantity = $setting->setDecimal($total_completed_quantity);

            $total_amount_traded = $setting->setDecimal($total_amount_traded);
            $total_amount_traded_in_currency = $total_amount_traded * $currency_rate_in_usd;
            $total_amount_traded_in_currency = $setting->setDecimal($total_amount_traded_in_currency, "marketplacePrice");

            $quantity_left = $advertisement["quantity"] - $total_completed_quantity;
            $quantity_left = $setting->setDecimal($quantity_left);
        }

        $advertisement_data["quantity_traded_cryptocurrency"] = $total_completed_quantity;
        $advertisement_data["quantity_traded_currency"] = $total_amount_traded_in_currency;
        $advertisement_data["quantity_left"] = $quantity_left;

        $escrow_address = $setting->systemSetting["marketplaceEscrowWalletAddress"];
        $advertisement_data["escrow_address"] = $escrow_address;

        // trading fee
        $trading_fee_percentage = $setting->systemSetting["marketplaceTradingFee"];
        $trading_fee_type = $advertisement["fee_type"];
        $db->where("currency_id", $trading_fee_type);
        $trading_fee_unit = $db->getValue("xun_marketplace_currencies", "symbol");
        $advertisement_data["trading_fee"]["fee_unit"] = $trading_fee_unit;
        $advertisement_data["trading_fee"]["percentage"] = $trading_fee_percentage;

        if ($db->tableExists($advertisement_order_table)) {
            $db->where("advertisement_id", $advertisement["id"]);
            $db->where("order_type", "order_trading_fee");
            $db->where("disabled", 0);
            $total_fee = $db->getValue($advertisement_order_table, "sum(quantity)");
        }
        $total_fee = $total_fee ? $total_fee : 0;
        $total_fee = $setting->setDecimal($total_fee);
        $advertisement_data["trading_fee"]["quantity"] = $total_fee;

        if ($advertisement["type"] == "buy" && $advertisement["fee_type"] != "thenuxcoin") {
            $advertisement_data["trading_fee"]["escrow_quantity"] = "0.00000000";
        } else {
            $advertisement_data["trading_fee"]["escrow_quantity"] = $advertisement["fee_quantity"];
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement details.", "data" => $advertisement_data);
    }

    public function get_user_advertisement_order_listing($params)
    {
        /**
         * data in:
         * -    username
         * -    advertisement_id
         * -    status -> in progress/completed/cancelled
         *
         * data out:
         * -    order_id
         * -    order_no
         * -    date
         * -    status
         * -    quantity
         * -    price
         * -    amount
         * -    username
         */

        global $setting, $xunCurrency;
        $db = $this->db;
        $general = $this->general;

        $page_limit = $setting->systemSetting["appsPageLimit"];
        $username = trim($params["username"]);
        $advertisement_id = trim($params["advertisement_id"]);
        $type = trim($params["type"]);
        $status = trim($params["status"]);
        $id = trim($params["last_id"]);
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = strtoupper(trim($params["order"]));

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }
        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }
        $user_id = $xun_user["id"];

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }

        if ($advertisement["user_id"] != $user_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }

        $table_name = $this->get_advertisement_order_transaction_table_name($advertisement["created_at"]);

        if (!$db->tableExists($table_name)) {
            $totalRecord = 0;
            $new_result = [];
        } else {
            $date = date("Y-m-d H:i:s");

            $db->where("advertisement_id", $advertisement_id);
            $db->where("disabled", 0);
            $db->where("order_type", "place_order");

            if ($id) {
                if ($order == 'DESC') {
                    $db->where("order_id", $id, '<');
                } else {
                    $db->where("order_id", $id, '>');
                }
            }

            if ($type) {
                $db->where("type", $type);
            }

            if ($status) {
                switch ($status) {
                    case "in_progress":
                        if ($advertisement["type"] == "sell") {
                            $db->where("(status in ('paid', 'coin_released') or (status = 'pending_payment' and expires_at > NOW()))");
                        } else {
                            $db->where("(status in ('paid', 'coin_released') or (status in ('pending_payment', 'pending_escrow') and expires_at > NOW()))");
                        }
                        break;

                    case "completed":
                        $db->where("status", "completed");
                        break;

                    case "cancelled":
                        if ($advertisement["type"] == "sell") {
                            $db->where("status", ["cancelled", "expired"], "in");
                        } else {
                            $db->where("(status in ('cancelled', 'expired', 'refunded'))");
                        }
                        break;

                    default:
                        if ($advertisement["type"] == "sell") {
                            $db->where("status", ["pending_escrow", "pre_escrow"], "NOT IN");
                        } else {
                            $db->where("status", "pre_escrow", "!=");
                        }
                        break;
                }
            } else {
                if ($advertisement["type"] == "sell") {
                    $db->where("status", ["pending_escrow", "pre_escrow"], "NOT IN");
                } else {
                    $db->where("status", "pre_escrow", "!=");
                }
            }

            // $db->where("user_id", $user_id);

            $start_limit = 0;
            $limit = array($start_limit, $page_size);

            $copyDb = $db->copy();
            $db->orderBy("order_id", $order);

            $result = $db->get($table_name, $limit);
            $return_message = "Advertisement listing.";
            $result = $result ? $result : array();

            //totalPage, pageNumber, totalRecord, numRecord
            $totalRecord = $copyDb->getValue($table_name, "count(id)");

            $new_result = [];

            if (!empty($result)) {
                $supported_currencies = $xunCurrency->get_marketplace_currencies();
                $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);
    
                $crypto_currency = $advertisement["crypto_currency"];

                $order_id_list = array_unique(array_column($result, "order_id"));

                $db->where("rater_user_id", $user_id);
                $db->where("advertisement_order_id", $order_id_list, "in");
                $order_rating = $db->getValue("xun_marketplace_user_rating", "advertisement_order_id", null);
            }

            foreach ($result as $advertisement_order) {
                $db->where("a.order_id", $advertisement_order["order_id"]);
                $db->join("xun_user b", "b.id = a.user_id", "LEFT");
                $db->orderBy("a.created_at", "asc");
                $columns = "b.id, b.username, b.nickname";
                $order_user = $db->getOne($table_name . " a", $columns);

                $order_username = $order_user["username"];
                $order_nickname = $order_user["nickname"];

                $currency = $advertisement_order["currency"];
                $advertisement_data = $this->compose_advertisement_order_data($advertisement_order);

                $advertisement_data["username"] = $order_username;
                $advertisement_data["nickname"] = $order_nickname;
                $advertisement_data["currency"] = $currency;
                // $advertisement_data["currency_list"] = $currency_list;
                $advertisement_data["crypto_currency"] = $crypto_currency;

                if ($advertisement["is_cryptocurrency"]) {
                    if ($advertisement["type"] == "buy") {
                        $amount = $advertisement_data["quantity"] / $advertisement_data["price"];
                        $amount = $setting->setDecimal($amount);
                    } else {
                        $amount = $advertisement_data["quantity"] * $advertisement_data["price"];
                        $amount = $setting->setDecimal($amount);
                    }
                } else {
                    if($advertisement["price_unit"])
                    {
                        $price_in_currency = $advertisement["price"];
                        $price_in_currency = $setting->setDecimal($price_in_currency, "marketplacePrice");
                    }else{
                        $currency_rate = $full_currency_list[$currency];
                        $price_in_currency = bcdiv((string)$advertisement_data["price"], (string)$currency_rate, 2);
                    }

                    $amount = $advertisement_data["quantity"] * $price_in_currency;
                    $amount = $setting->setDecimal($amount, "marketplacePrice");
                    $advertisement_data["price"] = $price_in_currency;
                }
                $advertisement_data["amount"] = $amount;

                // get rating flag
                if ($advertisement_order["status"] == "completed") {
                    $has_rated = in_array($advertisement_order["order_id"], $order_rating) ? true : false;
                } else {
                    $has_rated = false;
                }

                $advertisement_data["has_rated"] = $has_rated;
                $new_result[] = $advertisement_data;
            }
        }

        $returnData["result"] = $new_result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = (int) $page_size;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        // $returnData["pageNumber"] = $page_number;
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement order listing.", "data" => $returnData);
    }

    public function get_advertisement_order_listing($params)
    {
        /**
         * data in:
         * -    username
         * -    type - buy/sell
         * -    status -> in progress/completed/cancelled
         *
         * data out:
         * -    order_id
         * -    order_no
         * -    date
         * -    status
         * -    quantity
         * -    price
         * -    amount
         * -    username
         */

        global $setting, $xunCurrency;
        $db = $this->db;
        $general = $this->general;

        $page_limit = $setting->systemSetting["appsPageLimit"];
        $username = trim($params["username"]);
        $type = trim($params["type"]);
        $status = trim($params["status"]);
        $id = trim($params["last_id"]);
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = strtoupper(trim($params["order"]));

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }
        $user_id = $xun_user["id"];

        $db->where("user_id", $user_id);

        if ($id) {
            if ($order == 'DESC') {
                $db->where("order_id", $id, '<');
            } else {
                $db->where("order_id", $id, '>');
            }
        }

        if ($type) {
            $db->where("type", $type);
        }

        if ($status) {
            switch ($status) {
                case "in_progress":
                    $db->where("(status in ('paid', 'coin_released') or (status in ('pending_payment', 'pending_escrow') and expires_at > NOW()))");
                    break;

                case "completed":
                    $db->where("status", "completed");
                    break;

                case "cancelled":
                    $db->where("status", ["cancelled", "expired"], "in");
                    break;

                default:
                    break;
            }
        }

        $start_limit = 0;
        $limit = array($start_limit, $page_size);

        $copyDb = $db->copy();
        $db->orderBy("order_id", $order);

        $result = $db->get("xun_marketplace_advertisement_order_cache", $limit);
        $result = $result ? $result : array();

        $totalRecord = $copyDb->getValue("xun_marketplace_advertisement_order_cache", "count(id)");

        $new_result = [];

        $order_id_list = [];
        $advertisement_order_data = [];

        $distinct_currency = [];
        foreach ($result as $data) {
            $table_name = $data["table_name"];
            $db->where("a.order_id", $data["order_id"]);
            $db->where("a.disabled", 0);
            $db->join("xun_marketplace_advertisement b", "a.advertisement_id=b.id", "LEFT");
            $columns = " a.*, b.crypto_currency, b.user_id as 'owner_user_id', b.is_cryptocurrency, b.price_unit";
            $advertisement_order = $db->getOne($table_name . " a", $columns);
            $advertisement_order_data[] = $advertisement_order;
            $order_id_list[] = $data["order_id"];
            $distinct_currency[] = $advertisement_order["currency"];
        }

        if ($result) {
            $supported_currencies = $xunCurrency->get_marketplace_currencies();
            $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);

            $db->where("rater_user_id", $user_id);
            $db->where("advertisement_order_id", $order_id_list, "in");
            $order_rating = $db->getValue("xun_marketplace_user_rating", "advertisement_order_id", null);
        }

        foreach ($advertisement_order_data as $data) {
            $owner_user_id = $data["owner_user_id"];

            $db->where("id", $owner_user_id);
            $owner_user = $db->getOne('xun_user');
            $owner_username = $owner_user["username"];
            $owner_nickname = $owner_user["nickname"];

            $advertisement_data = $this->compose_advertisement_order_data($data);

            $currency = $data["currency"];
            $crypto_currency = $data["crypto_currency"];

            $advertisement_data["username"] = $owner_username;
            $advertisement_data["nickname"] = $owner_nickname;
            $advertisement_data["currency"] = $currency;
            $advertisement_data["crypto_currency"] = $crypto_currency;
            if ($data["is_cryptocurrency"]) {
                if ($data["type"] == "buy") {
                    $amount = $data["quantity"] / $data["price"];
                } else {
                    $amount = $data["quantity"] * $data["price"];
                }
                $amount = $setting->setDecimal($amount);
            } else {
                $currency_rate = $full_currency_list[$currency];
                if($data["price_unit"])
                {
                    $price_in_currency = $data["price"];
                    $price_in_currency = $setting->setDecimal($price_in_currency, "marketplacePrice");
                }else{
                    $price_in_currency = bcdiv((string)$data["price"], (string)$currency_rate, 2);
                }

                $advertisement_data["price"] = $price_in_currency;
                $amount = $advertisement_data["quantity"] * $price_in_currency;
                $amount = $setting->setDecimal($amount, "marketplacePrice");
            }

            $advertisement_data["amount"] = $amount;

            if ($data["status"] == "completed" && in_array($data["order_id"], $order_rating)) {
                $has_rated = true;
            } else {
                $has_rated = false;
            }

            $advertisement_data["has_rated"] = $has_rated;

            $new_result[] = $advertisement_data;
        }

        $returnData["result"] = $new_result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = (int) $page_size;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        // $returnData["pageNumber"] = $page_number;
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement order listing.", "data" => $returnData);
    }

    public function get_user_advertisement_order_details($params)
    {
        $return = $this->get_advertisement_order_details_data($params, "owner");
        return $return;
    }

    public function get_advertisement_order_details($params)
    {
        $return = $this->get_advertisement_order_details_data($params, "order");
        return $return;
    }

    private function get_advertisement_order_details_data($params, $user_type)
    {
        /**
         * data in:
         * -    username
         * -    order_id
         * -    advertisement id
         *
         * data out:
         * -    order no
         * -    order id
         * -    status
         * -    quantity
         * -    price
         * -    amount
         * -    currency
         * -    cryptocurrency
         * -    info
         * -    remarks
         * -    type
         * -    user info
         * -    user rating
         * -    user trade
         * -    owner_username
         * -    order_username
         *
         */

        global $setting, $xunCurrency;
        $db = $this->db;

        $username = trim($params["username"]);
        $advertisement_id = trim($params["advertisement_id"]);
        $order_id = trim($params["order_id"]);
        if ($order_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement order ID cannot be empty.");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty.");
        }

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty.");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }

        // if ($advertisement["user_id"] != $user_id) {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        // }

        $date = date("Y-m-d H:i:s");

        $table_name = $this->get_advertisement_order_transaction_table_name($advertisement["created_at"]);

        if ($db->tableExists($table_name)) {
            $db->where("order_id", $order_id);
            $db->where("disabled", 0);
            $advertisement_order = $db->getOne($table_name);

            if (!$advertisement_order) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -101);
            }
            if ($advertisement_order["advertisement_id"] != $advertisement_id) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -101);
            }

            $owner_user_id = $advertisement["user_id"];

            $db->where("id", $owner_user_id);
            $owner_user = $db->getOne('xun_user');
            $owner_username = $owner_user["username"];
            $owner_nickname = $owner_user["nickname"];

            $db->where("order_id", $order_id);
            $db->orderBy("created_at", "asc");
            $order_placement = $db->getOne($table_name);
            $order_user_id = $order_placement["user_id"];

            $db->where("id", $order_user_id);
            $order_user = $db->getOne('xun_user');
            $order_username = $order_user["username"];
            $order_nickname = $order_user["nickname"];

            $max_rating = $setting->systemSetting["marketplaceMaxUserRating"];

            if ($user_type == "owner") {
                if ($user_id != $advertisement["user_id"]) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
                }
                // get order_user_id info
                $user_marketplace_data = $this->get_user_marketplace_data($order_user_id);
            } else {
                // get owner info
                if ($user_id != $order_placement["user_id"]) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
                }
                $user_marketplace_data = $this->get_user_marketplace_data($owner_user_id);
            }

            $user_rating = $user_marketplace_data["avg_rating"];
            $total_trade = $user_marketplace_data["total_trade"];

            // check status
            if (in_array($advertisement_order["status"], array("pending_payment", "pending_escrow"))) {
                if ($advertisement_order["expires_at"] < $date) {
                    $new_status = "expired";

                    $update_data = [];
                    $update_data["updated_at"] = $date;
                    $update_data["disabled"] = 1;
                    $db->where("id", $advertisement_order["id"]);
                    $db->update($table_name, $update_data);

                    $insert_data = $advertisement_order;
                    unset($insert_data["id"]);
                    $insert_data["status"] = $new_status;
                    $insert_data["disabled"] = 0;

                    $db->insert($table_name, $insert_data);
                    $this->update_advertisement_order_cache_data($insert_data);

                }
            }

            $new_status = $new_status ? $new_status : $advertisement_order["status"];

            if ($new_status == "completed") {
                $db->where("advertisement_order_id", $advertisement_order["order_id"]);
                $db->where("rater_user_id", $user_id);
                $user_rating_rec = $db->getOne("xun_marketplace_user_rating");

                $has_rated = $user_rating_rec ? true : false;
            } else {
                $has_rated = false;
            }

            $currency = $advertisement_order["currency"];
            $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency, true);

            $currency_decimal_places = $currency_decimal_place_setting["decimal_places"];
            $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];


            $this->currency_decimal_place_setting = $currency_decimal_place_setting;

            $advertisement_order["created_at"] = $order_placement["created_at"];

            if($advertisement["is_cryptocurrency"] === 0){
                $supported_currencies = $xunCurrency->get_marketplace_currencies();
                $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);
                $currency_rate = $full_currency_list[$currency];
            }

            $advertisement_data = $this->compose_advertisement_order_details_data($advertisement, $advertisement_order, $currency_rate, $currency_decimal_place_setting);
            $advertisement_data["status"] = $new_status;

            $advertisement_data["owner_username"] = $owner_username;
            $advertisement_data["owner_nickname"] = $owner_nickname;
            $advertisement_data["order_username"] = $order_username;
            $advertisement_data["order_nickname"] = $order_nickname;
            $advertisement_data["remarks"] = $advertisement["remarks"];
            $advertisement_data["info"] = $advertisement["info"];
            $payment_method_arr = $this->get_advertisement_payment_method($advertisement_id, true, $advertisement["is_cryptocurrency"]);

            $advertisement_data["payment_method"] = $payment_method_arr;
            $advertisement_data["user_rating"] = $user_rating;
            $advertisement_data["max_rating"] = $max_rating;
            $advertisement_data["total_trade"] = $total_trade;
            $advertisement_data["has_rated"] = $has_rated;

            $escrow_address = $setting->systemSetting["marketplaceEscrowWalletAddress"];
            $advertisement_data["escrow_address"] = $escrow_address;

            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement details.", "data" => $advertisement_data);
        } else {
            // invalid order
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -101);
        }
    }

    public function add_user_payment_method($params)
    {
        global $setting, $xunUser, $xunXmpp, $xun_numbers;
        $db = $this->db;

        /**
         * data in:
         * -    payment_method_id
         * -    name
         * -    account
         * -    qr_code
         */

        $username = trim($params["username"]);
        $payment_method_id = trim($params["payment_method_id"]);
        $account_name = trim($params["name"]);
        $account_no = trim($params["account_no"]);
        $qr_code = trim($params["qr_code"]);
        $bank = trim($params["bank"]);
        $country = trim($params["country"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($payment_method_id == '') {
            if ($bank == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Bank cannot be empty");
            }

            if ($country == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Country cannot be empty");
            }
        }

        if ($account_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Name cannot be empty");
        }

        if ($account_no == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Account cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $date = date("Y-m-d H:i:s");

        //payment method checking
        if ($payment_method_id) {
            $db->where("id", $payment_method_id);
            $db->where("status", 1);
            $payment_method = $db->getOne("xun_marketplace_payment_method");

            if (!$payment_method) {
                return array("code" => 0, "message" => "FAILED", "message_d" => "Please select a valid payment method.", "errorCode" => -101);
            }
        } else {
            $low_country = strtolower($country);
            $db->where("country", $low_country);
            $db->where("name", $bank);

            $payment_method = $db->getOne("xun_marketplace_payment_method");

            $default_bank_image = $setting->systemSetting["marketplaceDefaultBankImage"];
            //inserting new payment method to db
            if (!$payment_method) {
                $insert_payment_method = array(
                    "name" => $bank,
                    "image" => $default_bank_image,
                    "payment_type" => "Online Banking",
                    "country" => $low_country,
                    "record_type" => "user",
                    "status" => 1,
                    "sort_order" => 4,
                    "created_at" => $date,
                    "updated_at" => $date,
                );
                $payment_method_id = $db->insert("xun_marketplace_payment_method", $insert_payment_method);
            } else {
                $payment_method_id = $payment_method["id"];
            }
        }

        $user_id = $xun_user["id"];
        $db->where("user_id", $user_id);
        $db->where("payment_method_id", $payment_method_id);
        $db->where("status", 1);

        $user_payment_method = $db->getOne("xun_marketplace_user_payment_method");

        if ($user_payment_method["status"] == 1) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "This payment method had already been created. Please select another payment method to add.", "errorCode" => -100);
        }

        //  id, user_id, payment_method_id, name, account_no, qr_code, status, created_at, updated_at

        if (!$user_payment_method) {
            $insert_data = array(
                "user_id" => $user_id,
                "payment_method_id" => $payment_method_id,
                "name" => $account_name,
                "account_no" => $account_no,
                "qr_code" => $qr_code,
                "status" => 1,
                "created_at" => $date,
                "updated_at" => $date,
            );
            //insert user payment method info
            $row_id = $db->insert("xun_marketplace_user_payment_method", $insert_data);
        } else {
            $update_data = [];
            $update_data["name"] = $account_name;
            $update_data["account_no"] = $account_no;
            $update_data["qr_code"] = $qr_code;
            $update_data["updated_at"] = $date;
            $update_data["status"] = 1;

            $db->where("id", $user_payment_method["id"]);
            $db->update("xun_marketplace_user_payment_method", $update_data);
            $row_id = $user_payment_method["id"];

        }
        $nickname = $xun_user["nickname"];

        $db->where("user_id", $user_id);
        $ip = $db->where("name", "lastLoginIP")->getValue("xun_user_setting", "value");

        $user_country_info_arr = $xunUser->get_user_country_info([$username]);
        $country_info = $user_country_info_arr[$username];
        $user_country = $country_info["name"];

        $device_os = $db->where("mobile_number", $username)->getValue("xun_user_device", "os");
        if ($device_os == 1) { $device_os = "Android";}
        else if ($device_os == 2) { $device_os = "iOS";}

        $payment_method_name = $payment_method["name"];
        if(!$payment_method_name){
            $payment_method_name = $bank;
        }
        $msg = "Username: $nickname\n";
        $msg .= "Phone number: $username\n";
        $msg .= "IP: " . $ip . "\n";
        $msg .= "Country: " . $user_country . "\n";
        $msg .= "Device: " . $device_os . "\n";
        //$msg .= "Status: Success\n";
        $msg .= "\nPayment Method: " . $payment_method_name . "\n";
        $msg .= "Name: " . $account_name . "\n";
        $msg .= "Account Number: " . $account_no . "\n";
        if($qr_code){
            $msg .= "Uploaded QR: " . "Yes" . "\n";
        }else{
            $msg .= "Uploaded QR: No\n";
        }
        $msg .= "Time: $date\n";
        $erlang_params["tag"]         = "Create Payment Method";
        $erlang_params["message"]     = $msg;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result                  = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Payment method added.", "id" => $row_id);
    }

    public function get_user_payment_method_details($params)
    {
        /**
         * data in:
         * -    id
         *
         * data out:
         * -    payment_method_id
         * -    payment_method_name
         * -    payment_method_image
         * -    account_name
         * -    account_no
         * -    account_qr_code
         */

        $db = $this->db;

        $username = trim($params["username"]);
        $id = trim($params["id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "ID cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];
        $db->where("id", $id);
        $db->where("user_id", $user_id);
        $db->where("status", 1);

        $user_payment_method = $db->getOne("xun_marketplace_user_payment_method");
        if (!$user_payment_method) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "No record found.", "errorCode" => -100);
        }

        $payment_method_id = $user_payment_method["payment_method_id"];

        $db->where("id", $payment_method_id);
        $payment_method = $db->getOne("xun_marketplace_payment_method", "name as payment_method_name, image as payment_method_image, country, payment_type");

        $return_data = $payment_method;
        $return_data["country"] = ucwords($payment_method["country"]);
        $return_data["id"] = $user_payment_method["id"];
        $return_data["payment_method_id"] = $payment_method_id;
        $return_data["name"] = $user_payment_method["name"];
        $return_data["account"] = $user_payment_method["account_no"];
        $return_data["qr_code"] = $user_payment_method["qr_code"];

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Payment method details.", "data" => $return_data);

    }

    public function get_user_payment_method_listing($params)
    {
        /**
         * data in:
         * -    username
         *
         * data out:
         * -    id:
         * -    payment_method_name
         * -    payment_method_image
         */
        $db = $this->db;

        $username = trim($params["username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $db->where("user_id", $user_id);
        $db->where("status", 1);

        $user_payment_method = $db->get("xun_marketplace_user_payment_method");

        $return_data = [];

        if ($user_payment_method) {
            $payment_method_ids = array_unique(array_column($user_payment_method, "payment_method_id"));

            $db->where("id", $payment_method_ids, "in");

            $payment_methods = $db->map("id")->ObjectBuilder()->get("xun_marketplace_payment_method", null, "id, name, image, country");
        }

        foreach ($user_payment_method as $data) {
            $payment_method_id = $data["payment_method_id"];
            $payment_method = $payment_methods[$payment_method_id];

            $final_user_payment_method = [];
            $final_user_payment_method["id"] = $data["id"];
            $final_user_payment_method["payment_method_id"] = $data["payment_method_id"];
            $final_user_payment_method["name"] = $data["name"];
            $final_user_payment_method["account_no"] = $data["account_no"];
            $final_user_payment_method["qr_code"] = $data["qr_code"];
            $final_user_payment_method["payment_method_name"] = $payment_method->name;
            $final_user_payment_method["payment_method_image"] = $payment_method->image;
            if ($payment_method->country != "") {
                $final_user_payment_method["country"] = $payment_method->country;
            }

            $return_data[] = $final_user_payment_method;
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Payment method listing.", "data" => $return_data);
    }

    public function delete_user_payment_method($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $id = $params["id"];

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if (is_array($id)) {
            $id = array_filter($id);
            if (empty($id)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "ID cannot be empty");
            }
        } else {
            if (trim($id) == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "ID cannot be empty");
            }

            $id = [$id];
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $date = date("Y-m-d H:i:s");

        foreach ($id as $data) {
            $db->where("id", $data);
            $db->where("user_id", $user_id);
            $db->where("status", 1);

            $user_payment_method = $db->getOne("xun_marketplace_user_payment_method");

            if ($user_payment_method) {
                $update_data = [];
                $update_data["updated_at"] = $date;
                $update_data["status"] = 0;

                $db->where("id", $data);
                $db->update("xun_marketplace_user_payment_method", $update_data);
            }
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Payment method deleted.");
    }

    public function get_user_marketplace_summary($params)
    {
        global $setting;
        $db = $this->db;

        $username = trim($params["username"]);
        $advertisement_username = trim($params["advertisement_username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($advertisement_username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        $db->where("username", $advertisement_username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $user_marketplace_data = $this->get_user_marketplace_data($user_id);
        $user_rating = $user_marketplace_data["avg_rating"];
        $total_trade = $user_marketplace_data["total_trade"];

        $max_rating = $setting->systemSetting["marketplaceMaxUserRating"];

        $return_data = array(
            "max_rating" => $max_rating,
            "user_rating" => $user_rating,
            "total_trade" => $total_trade,
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "User marketplace summary.", "data" => $return_data);

    }

    public function get_advertisement_effective_remaining_quantity($advertisement)
    {
        global $setting;
        $db = $this->db;

        $advertisement_id = $advertisement["id"];
        $type = $advertisement["type"];
        $initial_quantity = $advertisement["quantity"];
        $advertisement_date = $advertisement["created_at"];

        $table_name = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        if ($db->tableExists($table_name)) {
            $db->where("advertisement_id", $advertisement_id);
            $db->where("disabled", 0);
            $db->where("order_type", "place_order");
            $db->where("status", "completed");

            $copyDb = $db->copy();
            $total_completed_quantity = $db->getValue($table_name, "sum(quantity)");
            $ad_orders = $copyDb->get($table_name);

            $ordered_quantity = $total_completed_quantity;
        }
        $ordered_quantity = $ordered_quantity ? $ordered_quantity : 0;
        $remaining_quantity = $initial_quantity - $ordered_quantity;
        $remaining_quantity = $setting->setDecimal($remaining_quantity);
        return $remaining_quantity;
    }

    public function get_advertisement_locked_remaining_quantity($advertisement, $trading_fee = null)
    {
        global $setting, $xunCurrency;
        $db = $this->db;

        $advertisement_id = $advertisement["id"];
        $type = $advertisement["type"];
        $initial_quantity = $advertisement["quantity"];
        $advertisement_date = $advertisement["created_at"];

        $table_name = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        // echo "\n trading_fee $trading_fee fee_quantity " . $advertisement["fee_quantity"];
        if ($db->tableExists($table_name)) {
            // completed, paid, pending_payment(not expired),coin released

            if($trading_fee || $advertisement["fee_quantity"] != 0){
                // echo "\n ## get_advertisement_locked_remaining_quantity 2";

                // query then loop
                $db->where("advertisement_id", $advertisement_id);
                $db->where("order_type", "place_order");
                $db->where("disabled", 0);
                $db->where("(status in ('paid','completed', 'dispute', 'coin_released') or (status in ('pending_payment', 'pre_escrow', 'pending_escrow') and expires_at > NOW()))");
                $records = $db->get($table_name);

                $total_trading_fee = 0;
                $total_quantity = 0;
                
                $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($advertisement["currency"], true);

                $this->order_decimal_place_setting = $currency_decimal_place_setting;

                foreach ($records as $data) {
                    $total_quantity = bcadd((string) $total_quantity, (string) $data["quantity"], 8);

                    // TODO :this->order_Decimal_places
                    $fund_out_result = $this->get_order_fund_out_amount($advertisement, $data["quantity"]);
                    // echo "\n ## get_advertisement_locked_remaining_quantity 3";

                    // print_r($fund_out_result);
                    $trading_fee_quantity = $fund_out_result["trading_fee_quantity"];
                    $total_trading_fee = bcadd((string) $total_trading_fee, (string) $trading_fee_quantity, 8);
                }
            }else{
                // echo "\n ## get_advertisement_locked_remaining_quantity 1";
                $db->where("advertisement_id", $advertisement_id);
                $db->where("order_type", ["place_order","order_trading_fee"], 'in');
                $db->where("disabled", 0);
                $db->where("(status in ('paid','completed', 'dispute', 'coin_released','pre_escrow_fund_out','pending_escrow_fund_out') or (status = ? and expires_at > NOW()))", array("pending_payment"));

                $total_quantity = $db->getValue($table_name, "sum(quantity)");
            }
        }
        $ordered_quantity = $total_quantity ? $total_quantity : 0;
        $remaining_quantity = bcsub((string) $initial_quantity, (string) $ordered_quantity, 8);
        // echo "\n trading_fee_quantity $trading_fee_quantity total trading fee $total_trading_fee\nordered_quantity $ordered_quantity remaining_quantity $remaining_quantity";
        // $remaining_quantity = $setting->setDecimal($remaining_quantity);
        if ($trading_fee) {
            return array("remaining_quantity" => $remaining_quantity, "total_trading_fee" => $total_trading_fee ? $total_trading_fee : 0);
        }
        return $remaining_quantity;
    }

    public function get_advertisement_remaining_quantity($advertisement)
    {
        global $setting;
        $db = $this->db;

        $advertisement_id = $advertisement["id"];
        $type = $advertisement["type"];
        $initial_quantity = $advertisement["quantity"];
        $advertisement_date = $advertisement["created_at"];

        $table_name = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        if ($db->tableExists($table_name)) {
            if ($type == "sell") {

                $db->where("advertisement_id", $advertisement_id);
                $db->where("order_type", "place_order");
                $db->where("disabled", 0);
                $db->where("(status in ('paid','completed', 'dispute') or (status = ? and expires_at > NOW()))", array("pending_payment"));

                $ad_orders1 = $db->get($table_name);

                $ad_orders = $db->rawQuery("SELECT * FROM `$table_name` WHERE advertisement_id = '$advertisement_id' and order_type = 'place_order' and disabled = 0 and (status in ('paid','completed', 'dispute') or (status = 'pending_payment' and expires_at > NOW()))");
            } else {

                $db->where("advertisement_id", $advertisement_id);
                $db->where("order_type", "place_order");
                $db->where("disabled", 0);
                $db->where("(status in ('paid','completed', 'dispute') or (status in ('pending_escrow', 'pending_payment') and expires_at > NOW()))");

                $ad_orders1 = $db->get($table_name);

                $ad_orders = $db->rawQuery("SELECT * FROM `$table_name` where advertisement_id = '$advertisement_id' and disabled = 0 and (status in ('paid', 'completed', 'dispute') or (status in ('pending_escrow', 'pending_payment') and expires_at > NOW()))");
            }

            $ordered_quantity = 0;
            if ($ad_orders) {
                foreach ($ad_orders as $order) {
                    $ordered_quantity += $order["quantity"];
                }
            }

        }
        $ordered_quantity = $ordered_quantity ? $ordered_quantity : 0;
        $remaining_quantity = $initial_quantity - $ordered_quantity;
        $remaining_quantity = $setting->setDecimal($remaining_quantity);
        return $remaining_quantity;

    }

    private function get_advertisement_orders_count($advertisement)
    {
        /**
         * to get the count of open orders of an advertisement
         */

        $db = $this->db;

        $advertisement_id = $advertisement["id"];
        $type = $advertisement["type"];

        $table_name = $this->get_advertisement_order_transaction_table_name($advertisement["created_at"]);

        if ($db->tableExists($table_name)) {
            $db->where("advertisement_id", $advertisement_id);
            $db->where("disabled", 0);
            $db->where("(status in ('paid', 'coin_released') or (status = ? and expires_at > NOW()))", array("pending_payment"));
            $ad_orders = $db->getValue($table_name, "count(id)");
        }

        $count = $ad_orders ? $ad_orders : 0;
        return $count;

    }
    public function get_advertisement_details($params)
    {
        global $setting, $xunCurrency;
        $db = $this->db;

        $username = trim($params["username"]);
        $advertisement_id = trim($params["id"]);
        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty.");
        }

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty.");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }
        if ($advertisement["status"] != "new") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This advertisement is closed.", "errorCode" => -101);
        }

        $advertisement_currency = $advertisement["currency"];
        $crypto_currency = $advertisement["crypto_currency"];

        $currency_list = explode("##", $advertisement_currency);
        $currency = $currency_list[0];

        $supported_currencies = $xunCurrency->get_marketplace_currencies();
        $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);

        $crypto_price_in_usd = $full_currency_list[$crypto_currency];

        $advertisement_data = $this->compose_advertisement_data($advertisement);
        $advertisement_data["remarks"] = $advertisement["remarks"];
        $advertisement_data["info"] = $advertisement["info"];

        $advertisement_data["currency"] = $currency;
        $advertisement_data["currency_list"] = $currency_list;
        // min_cryptocurrency, max_cryptocurrency, max_currency, min_currency
        $remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement);
        $min = $advertisement["min"];
        $max_order = $advertisement["max"];

        $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency, true);
        $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($crypto_currency, true);

        $currency_decimal_places = $currency_decimal_place_setting["decimal_places"];
        $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];

        $crypto_decimal_places = $crypto_decimal_place_setting["decimal_places"];
        $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

        $this->currency_decimal_place_setting = $currency_decimal_place_setting;
        $this->crypto_decimal_place_setting = $crypto_decimal_place_setting;

        $max_order_quantity = (bccomp((string) $max_order, (string) $remaining_quantity, 8) > 0 || bccomp((string) $max_order, "0", 8) == 0) ? $remaining_quantity : $max_order;

        if ($advertisement["is_cryptocurrency"] === 1) {
            $currency_rate_in_usd = $full_currency_list[$currency];
            $c2c_price = $xunCurrency->get_rate($crypto_currency, $currency);
            
            $final_price = $this->get_advertisement_effective_price_c2c($advertisement, $c2c_price);
            
            $final_price = $setting->setDecimal($final_price, $currency_dp_credit_type);
            $price_list = array($currency => $final_price);
            
            if ($advertisement["type"] == "buy") {
                $min_cryptocurrency = $min;
                $max_currency = $max_order_quantity;
                $max_cryptocurrency = $max_currency / $final_price;
                $max_cryptocurrency = $setting->setDecimal($max_cryptocurrency);
                $min_currency = $min * $final_price;
                $min_currency = $setting->setDecimal($min_currency);
            } else {
                $formatted_quantity = $setting->setDecimal($advertisement["quantity"], $crypto_dp_credit_type);
                $advertisement_data["quantity"] = $formatted_quantity;
                // $min_currency = $setting->setDecimal($min, $currency_dp_credit_type);
                $min_currency = $min;
                // $max_cryptocurrency = $setting->setDecimal($max_order_quantity, $crypto_dp_credit_type);
                $max_cryptocurrency = $max_order_quantity;
                $max_currency = $max_cryptocurrency * $final_price;
                $max_currency = $setting->setDecimal($max_currency, $currency_dp_credit_type);
                $min_cryptocurrency = $min_currency / $final_price;
                // $min_cryptocurrency = $setting->setDecimal($min_cryptocurrency, $crypto_dp_credit_type);
            }
            $min_currency_list = array($currency => $min_currency);
            $max_currency_list = array($currency => $max_currency);
            $advertisement_data["crypto_rate"] = $crypto_price_in_usd;
            $advertisement_data["currency_rate"] = $currency_rate_in_usd;
        } else {
            // fiat currency
            $exchange_rate = [];
            $price_list = [];
            $min_currency_list = [];
            $max_currency_list = [];

            $currency_rate_in_usd = $full_currency_list[$currency];
            $advertisement_final_price = $this->get_advertisement_effective_price($advertisement, $currency, $crypto_price_in_usd, $currency_rate_in_usd, $currency_rate_in_usd);
            $price_in_usd = $advertisement_final_price["price_in_usd"];
            $currency_rate = $full_currency_list[$currency];
            // echo "\n currency_rate_in_usd $currency_rate_in_usd currency_rate $currency_rate advertisement_final_price $advertisement_final_price price_in_usd $price_in_usd ";
            // print_r($advertisement_final_price);
            foreach ($currency_list as $curr) {
                // get price list for currencies
                $currency_rate_in_usd = $full_currency_list[$curr];
                if ($curr == $advertisement["limit_currency"]) {
                    $price = $advertisement_final_price["price_in_currency"];
                } else {
                    $price = $price_in_usd * $currency_rate_in_usd;
                    $price = $setting->setDecimal($price, "marketplacePrice");
                }
                $price_list[$curr] = $price;
                $exchange_rate[$curr] = $currency_rate_in_usd;
            }

            $final_price = $price_list[$currency];

            // if buy ad, min is coin, sell ad, min is currency
            if ($advertisement["type"] == "sell") {
                $min_cryptocurrency = $min / $final_price;
                $min_cryptocurrency = $setting->setDecimal($min_cryptocurrency);
                $max_cryptocurrency = $max_order_quantity;
                $min_in_usd = $min / $exchange_rate[$currency];
                foreach ($price_list as $curr => $price) {
                    $max_currency = $max_order_quantity * $price;
                    $max_currency = $setting->setDecimal($max_currency, "marketplacePrice");
                    $min_currency = $min_in_usd * $exchange_rate[$curr];
                    $min_currency = $setting->setDecimal($min_currency, "marketplacePrice");

                    if ($max_currency < $min_currency && $max_cryptocurrency >= $min_cryptocurrency) {
                        $max_currency = $min_currency;
                    }
                    $min_currency_list[$curr] = $min_currency;
                    $max_currency_list[$curr] = $max_currency;
                }

            } else {
                foreach ($price_list as $curr => $price) {
                    $min_currency = $min * $price;
                    $min_currency = $setting->setDecimal($min_currency, "marketplacePrice");
                    $min_currency_list[$curr] = $min_currency;
                    $max_currency = $max_order_quantity * $price;
                    $max_currency = $setting->setDecimal($max_currency, "marketplacePrice");
                    $max_currency_list[$curr] = $max_currency;
                }
                $min_cryptocurrency = $min;
                $max_cryptocurrency = $max_order_quantity;
            }

        }
        $advertisement_data["min_cryptocurrency"] = $setting->setDecimal($min_cryptocurrency, $crypto_dp_credit_type);
        $advertisement_data["max_cryptocurrency"] = $setting->setDecimal($max_cryptocurrency, $crypto_dp_credit_type);
        $advertisement_data["min"] = $setting->setDecimal($min_currency_list[$currency], $currency_dp_credit_type);
        $advertisement_data["max"] = $setting->setDecimal($max_currency_list[$currency], $currency_dp_credit_type);

        $advertisement_data["min_currencies"] = $min_currency_list;
        $advertisement_data["max_currencies"] = $max_currency_list;
        $advertisement_data["price_list"] = $price_list;
        $advertisement_data["price"] = $final_price;

        $payment_method_arr = $this->get_advertisement_payment_method($advertisement_id, null, $advertisement["is_cryptocurrency"]);

        $advertisement_data["payment_method"] = $payment_method_arr;

        $user_marketplace_data = $this->get_user_marketplace_data($advertisement["user_id"]);
        $user_rating = $user_marketplace_data["avg_rating"];
        $total_trade = $user_marketplace_data["total_trade"];

        $max_rating = $setting->systemSetting["marketplaceMaxUserRating"];

        $advertisement_data["user_rating"] = $user_rating;
        $advertisement_data["max_rating"] = $max_rating;
        $advertisement_data["total_trade"] = $total_trade;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Advertisement details.", "data" => $advertisement_data);
    }

    public function place_order($params)
    {
        global $setting, $config, $general, $xunXmpp, $xunCurrency, $xunUser, $xun_numbers;

        $db = $this->db;

        $date = date('Y-m-d H:i:s');
        $advertisement_id = trim($params["advertisement_id"]);
        $quantity = trim($params["quantity"]);
        $currency = trim($params["currency"]);
        $username = trim($params["username"]);
        $price = trim($params["price"]);
        $newParams = $params;
        
        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty.");
        }

        if ($quantity == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Quantity cannot be empty.");
        }

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username be empty.");
        }

        if ($price == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Price be empty.");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $db->where("user_id", $user_id);
        $db->where("name", "lastLoginIP");
        $user_ip = $db->getValue("xun_user_setting", "value");

        $user_device_os = $db->where($username)->getValue("xun_user_device", "os");
        $user_device_os = $user_device_os == 1 ? $user_device_os = "Android" : $user_device_os = "iOS";

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        $db->where("id", $advertisement["user_id"]);
        $owner_user = $db->getOne("xun_user");
        $owner_username = $owner_user["username"];
        $owner_nickname = $owner_user["nickname"];
        $owner_user_id = $owner_user["id"];

        $db->where("user_id", $owner_user_id);
        $db->where("name", "lastLoginIP");
        $owner_ip = $db->getValue("xun_user_setting", "value");

        $owner_device_os = $db->where($owner_username)->getValue("xun_user_device", "os");
        $owner_device_os = $owner_device_os == 1 ? $owner_device_os = "Android" : $owner_device_os = "iOS";
        
        $user_nickname = $xun_user["nickname"];

        $user_country_info_arr = $xunUser->get_user_country_info([$owner_username, $username]);
        $owner_country_info = $user_country_info_arr[$owner_username];
        $owner_country = $owner_country_info["name"];
        $order_country_info = $user_country_info_arr[$username];
        $order_country = $order_country_info["name"];

        $newParams["owner_country"] = $owner_country;
        $newParams["order_country"] = $order_country;
        $newParams["user_ip"] = $user_ip;
        $newParams["user_device_os"] = $user_device_os;
        $newParams["owner_username"] = $owner_username;
        $newParams["owner_nickname"] = $owner_nickname;
        $newParams["owner_ip"] = $owner_ip;
        $newParams["owner_device_os"] = $owner_device_os;
        $newParams["user_nickname"] = $user_nickname;

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }

        if ($user_id == $advertisement["user_id"]) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You are not allowed to place order on your own advertisement.", "errorCode" => -107);
        }

        $advertisement_status = strtolower($advertisement["status"]);
        $advertisement_type = strtolower($advertisement["type"]);
        $advertisement_quantity = $advertisement["quantity"];
        $price_type = $advertisement["price_type"];
        $advertisement_currency_list = explode("##", $advertisement["currency"]);

        if ($currency == '') {
            $currency = $advertisement_currency_list[0];
        } else {
            $currency = strtolower($currency);
        }

        if (!in_array($currency, $advertisement_currency_list)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid currency option.", "errorCode" => -108);
        }

        $crypto_currency = $advertisement["crypto_currency"];
        $expires_at = $advertisement["expires_at"];

        if ($advertisement_status != "new") {
            $error_message = "This advertisement is no longer accepting orders.";
            $xmpp_result = $this->send_failed_place_order($newParams, $advertisement, $error_message);
            return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -101);
        }

        if ($expires_at < $date) {
            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = "expired";

            $db->where("id", $advertisement_id);
            $db->update("xun_marketplace_advertisement", $update_data);

            // refund expired advertisement
            $this->escrow_refund_advertisement($advertisement);

            $error_message = "This advertisement has expired.";
            $xmpp_result = $this->send_failed_place_order($newParams, $advertisement, $error_message);
            return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -102);
        }

        $supported_currencies = $xunCurrency->get_marketplace_currencies();

        $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);

        $crypto_price_in_usd = $full_currency_list[$crypto_currency];

        $advertisement_currency = $advertisement_currency_list[0];

        /**
         * sell
         * -    fiat
         *      -   cryptocurrency
         * -    c2c
         *      -   cryptocurrency
         *
         * buy
         * -    fiat
         *      -   cryptocurrency
         * -    c2c =
         *      -   currency
         *
         */

        $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency, true);
        $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($crypto_currency, true);

        $currency_decimal_places = $currency_decimal_place_setting["decimal_places"];
        $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];

        $crypto_decimal_places = $crypto_decimal_place_setting["decimal_places"];
        $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

        $this->currency_decimal_place_setting = $currency_decimal_place_setting;
        $this->crypto_decimal_place_setting = $crypto_decimal_place_setting;

        $order_decimal_places = $crypto_decimal_places;
        $order_dp_credit_type = $crypto_dp_credit_type;
        $order_decimal_place_setting = $currency_decimal_place_setting;
        $this->order_decimal_place_setting = $crypto_decimal_place_setting;

        if ($advertisement["is_cryptocurrency"]) {
            $currency_rate_in_usd = $full_currency_list[$currency];
            $c2c_price = $xunCurrency->get_rate($crypto_currency, $currency);
            $price = $this->get_advertisement_effective_price($advertisement, $advertisement_currency, $crypto_price_in_usd, $currency_rate_in_usd, null, $c2c_price);
            // set decimal for price, follow currency dp
            $price = $setting->setDecimal($price, $currency_dp_credit_type);

            $advertisement["price"] = $price;
            $price_in_currency = $price;
            $order_price = $price;

            $newParams["order_price"] = $order_price;

            if ($advertisement["type"] == "buy") {
                $order_decimal_place_setting = $crypto_decimal_place_setting;
                $quantity = $setting->setDecimal($quantity, $currency_dp_credit_type);

                $min = $advertisement["min"] * $price;
            } else {
                $quantity = $setting->setDecimal($quantity, $crypto_dp_credit_type);

                $min = $advertisement["min"] / $price;
            }
            $min = $setting->setDecimal($min, $currency_dp_credit_type);
            if ($quantity < $min) {
                $advertisement["min"] = $min;
                $error_message = "Your quantity must be more than the minimum order of the advertisement";
                $xmpp_result = $this->send_failed_place_order($newParams, $advertisement, $error_message);
                return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -105);
            }
        } else {
            $quantity = $setting->setDecimal($quantity, $crypto_dp_credit_type);

            $currency_rate_in_usd = $full_currency_list[$currency];

            $advertisement_currency_rate_in_usd = $full_currency_list[$advertisement_currency];

            $limit_currency = $advertisement["limit_currency"];

            // $limit_currency_rate = $limit_currency != $currency ? $this->get_currency_rate_in_usd($limit_currency) : $currency_rate_in_usd;

            $limit_currency_rate = $full_currency_list[$currency];

            $cryptocurrency_price = $this->get_advertisement_effective_price($advertisement, $advertisement_currency, $crypto_price_in_usd, $advertisement_currency_rate_in_usd, $limit_currency_rate);

            $price_in_currency = $cryptocurrency_price["price_in_currency"];
            $price = $cryptocurrency_price["price_in_usd"];
            $price = $setting->setDecimal($price, $currency_dp_credit_type);
            $advertisement["price"] = $price;
            $order_price = $advertisement["price_unit"] ? $price_in_currency : $price;
            $newParams["order_price"] = $order_price;
            if ($advertisement_type == "buy") {
                if ($quantity < $advertisement["min"]) {
                    $advertisement["min"] = $min;
                    $error_message = "Your sell quantity must be more than the minimum order of the advertisement";
                    $xmpp_result = $this->send_failed_place_order($newParams, $advertisement, $error_message);
                    return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -105);
                }
            } else {
                // based on initial advertisement currency
                $effective_amount = $price_in_currency * $quantity;
                $effective_amount = $setting->setDecimal($effective_amount, $crypto_dp_credit_type);

                $min_quantity = $advertisement["min"] / $price_in_currency;
                $min_quantity = $setting->setDecimal($min_quantity, $crypto_dp_credit_type);

                if ($quantity < $min_quantity) {
                    $advertisement["min"] = $min_quantity;
                    $error_message = "Your buy amount must be more than the minimum order of the advertisement";
                    $xmpp_result = $this->send_failed_place_order($newParams, $advertisement, $error_message);
                    return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -105, "data" => array("amount" => $effective_amount, "advertisement_min" => $advertisement["min"], "price" => $price_in_currency, "min_quantity" => $min_quantity));
                }
            }
        }

        $order_id = $this->get_order_no();

        $table_name = $this->create_advertisement_order_transaction_daily_table($advertisement["created_at"]);
        $maximum_order_quantity = $advertisement["max"];
        // SELL ADS ONLY
        if ($advertisement_type == "sell") {
            // sell ads checks for max_processing_order and locked quota
            // check maximum_processing_orders
            // allow new order as long as there's quota, quota based on sold only

            $can_place_order_return = $this->can_place_order($advertisement, $quantity);
            if (isset($can_place_order_return["code"]) && $can_place_order_return["code"] == 0) {
                return $can_place_order_return;
            }
            $total_active_order = $can_place_order_return["total_active_order"];
            $remaining_quantity = $can_place_order_return["remaining_quantity"];

        } else {
            $remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement);
        }

        $maximum_order_quantity = bccomp((string) $maximum_order_quantity, "0", 8) == 0 ? $remaining_quantity : $maximum_order_quantity;
        $maximum_order_quantity = bccomp((string) $maximum_order_quantity, (string) $remaining_quantity, 8) > 0 ? $remaining_quantity : $maximum_order_quantity;

        // echo "\n maximum_order_quantity $maximum_order_quantity remaining_quantity $remaining_quantity";
        if (bccomp((string) $quantity, (string) $maximum_order_quantity, 8) > 0) {
            $advertisement["max"] = $maximum_order_quantity;

            $error_message = "The order quantity must not be more than the advertisement order quantity.";
            $xmpp_result = $this->send_failed_place_order($newParams, $advertisement, $error_message);
            return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -108);
        }

        if ($advertisement_type == 'buy') {
            $marketplaceSellerTransactionExpiration = $setting->systemSetting["marketplaceSellerTransactionExpiration"];

            $seller_transfer_expiration = "$marketplaceSellerTransactionExpiration minutes";

            $expires_at = date("Y-m-d H:i:s", strtotime("+$seller_transfer_expiration", strtotime($date)));
            $new_status = "new";

            $insert_data = array(
                "advertisement_id" => $advertisement_id,
                "order_id" => $order_id,
                "order_type" => "place_order",
                "user_id" => $user_id,
                "type" => $advertisement["type"],
                "price" => $order_price,
                "quantity" => $quantity,
                "currency" => $currency,
                "status" => "pre_escrow",
                "expires_at" => $expires_at,
                "order_no" => $order_id,
                "disabled" => 0,
                "created_at" => $date,
                "updated_at" => $date,
            );

            // print_r($insert_data);
            // return;

            $advertisement_order_id = $db->insert($table_name, $insert_data);
            $this->store_advertisement_order_cache_data($insert_data, $table_name);

            // update sold out column
        } else if ($advertisement_type == 'sell') {
            $marketplaceBuyerTransactionExpiration = $setting->systemSetting["marketplaceBuyerTransactionExpiration"];
            $buyer_transfer_expiration = "$marketplaceBuyerTransactionExpiration minutes";
            $expires_at = date("Y-m-d H:i:s", strtotime("+$buyer_transfer_expiration", strtotime($date)));

            $order_status = $advertisement["is_cryptocurrency"] ? "pre_escrow" : "pending_payment";

            $insert_data = array(
                "advertisement_id" => $advertisement_id,
                "order_id" => $order_id,
                "order_type" => "place_order",
                "user_id" => $user_id,
                "type" => $advertisement["type"],
                "price" => $order_price,
                "quantity" => $quantity,
                "currency" => $currency,
                "status" => $order_status,
                "expires_at" => $expires_at,
                "order_no" => $order_id,
                "disabled" => 0,
                "created_at" => $date,
                "updated_at" => $date,
            );

            // print_r($insert_data);
            // return;
            $advertisement_order_id = $db->insert($table_name, $insert_data);
            $this->store_advertisement_order_cache_data($insert_data, $table_name);
            // update sold out column
        }

        $table_name = $this->create_advertisement_order_transaction_daily_table($advertisement["created_at"]);
        $db->where("id", $advertisement_order_id);
        $db->where("advertisement_id", $advertisement_id);
        $advertisement_order = $db->getOne($table_name);

        $max_processing_orders = $advertisement["max_processing_orders"];
        if ($advertisement_type == "sell") {
            $new_total_active_order = $total_active_order + 1;

            if ($max_processing_orders !== 0) {
                if ($new_total_active_order >= $max_processing_orders) {
                    // update to sold_out = 1
                    $has_sold_out = true;
                    $has_updated = true;
                }
            }

            if (!$has_updated) {
                $new_remaining_quantity = $remaining_quantity - $quantity;
                $new_remaining_quantity = $setting->setDecimal($new_remaining_quantity);

                $new_effective_amount = $price_in_currency * $new_remaining_quantity;
                $new_effective_amount = $setting->setDecimal($new_effective_amount);

                if ($new_effective_amount < $advertisement["min"]) {
                    $has_sold_out = true;
                }
            }

            if ($has_sold_out) {
                $update_data = [];
                $update_data["sold_out"] = 1;
                $update_data["updated_at"] = $date;

                $db->where("id", $advertisement["id"]);
                $db->update("xun_marketplace_advertisement", $update_data);
            }
        }

        $marketplace_chat_room_host = "marketplace." . $config["erlang_server"];
        $chatroom_insert_data = array(
            "host" => $marketplace_chat_room_host,
            "advertisement_order_id" => $order_id,
            "owner_user_id" => $advertisement["user_id"],
            "user_id" => $user_id,
            "status" => 'open',
            "created_at" => $date,
            "updated_at" => $date,
        );

        $db->insert("xun_marketplace_chat_room", $chatroom_insert_data);

        if ($advertisement["type"] == "sell") {
            $seller_username = $owner_username;
            $seller_nickname = $owner_nickname;
            $buyer_username = $username;
            $buyer_nickname = $user_nickname;
        } else {
            $buyer_username = $owner_username;
            $buyer_nickname = $owner_nickname;
            $seller_username = $username;
            $seller_nickname = $user_nickname;
        }

        if ($order_status == "pending_payment") {
            $xmpp_recipients = [$owner_username, $username];
            $erlang_params = array(
                "chatroom_id" => (string) $order_id,
                "chatroom_host" => $marketplace_chat_room_host,
                "recipients" => $xmpp_recipients,
                "type" => "new_order",
                "data" => array(
                    "advertisement_id" => (string) $advertisement_id,
                    "order_id" => (string) $order_id,
                    "username" => $username,
                    "nickname" => $user_nickname,
                    "owner_username" => $owner_username,
                    "owner_nickname" => $owner_nickname,
                    "seller_username" => $seller_username,
                    "seller_nickname" => $seller_nickname,
                    "buyer_username" => $buyer_username,
                    "buyer_nickname" => $buyer_nickname,
                    "crypto_currency" => $advertisement["crypto_currency"],
                    "created_at" => $general->formatDateTimeToIsoFormat($date),
                ),
            );
            $xunXmpp->send_xmpp_marketplace_event($erlang_params);



            //$price_setting = $advertisement["price_type"] == "fix" ? "Fix: " . $advertisement["price"] : "Floating: " . $advertisement["floating_ratio"];
            $tag = "Placed Order Successful";

            $price_type = $advertisement["price_type"];
            $content = "Ads Owner\n";
            $content .= "Username: " . $owner_nickname . "\n";
            $content .= "Phone Number: " . $owner_username . "\n";
            $content .= "IP: " . $owner_ip . "\n";
            $content .= "Country: " . $owner_country . "\n";
            $content .= "Device: " . $owner_device_os . "\n";
            $content .= "Advertisement ID: " . $advertisement_id . "\n";
            $content .= "Advertisement Type: " . ucfirst($advertisement["type"]) . "\n";
            $content .= "Sell: " . ucfirst($advertisement["crypto_currency"]) . "\n";
            $content .= "Accept with: " . ucfirst($currency) . "\n";
            $content .= "Price setting: " . ucfirst($price_type) . "\n";
            if($price_type == "fix"){
                $content .= "Price: " . $order_price . "\n";
            }
            else if($price_type == "floating"){
                $content .= "Floating ratio: " . $advertisement["floating_ratio"] . "\n";
                $content .= "Min Price: " . $advertisement["price_limit"] . "\n";
            }
            $content .= "Transaction limit: " . $min_quantity . " - " . $maximum_order_quantity . "\n";

            $content .= "\nPlace Order By \n";
            $content .= "Username: " . $user_nickname . "\n";
            $content .= "Phone Number: " . $username . "\n";
            $content .= "IP: " . $user_ip . "\n";
            $content .= "Country: " . ucfirst($order_country) . "\n";
            $content .= "Device: " . $user_device_os . "\n";
            $content .= "Order Price: " . $advertisement_order["price"] . "\n";
            $content .= "Order Volume: " . $advertisement_order["quantity"] . "\n";
            $content .= "Order ID: " . $order_id . "\n";
            //$content .= "Price setting: " . $price_setting . "\n";
            $content .= "\nTime: " . date("Y-m-d H:i:s");

            $erlang_params = array();
            $erlang_params["tag"] = $tag;
            $erlang_params["message"] = $content;
            $erlang_params["mobile_list"] = $xun_numbers;
            $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");

        }

        $order_data = $this->compose_advertisement_order_details_data($advertisement, $insert_data, $currency_rate_in_usd, $order_decimal_place_setting);

        // ad owner's username and nickname
        $order_data["username"] = $owner_username;
        $order_data["nickname"] = $owner_nickname;
        $order_data["has_rated"] = "false";
        $return_data = array("order" => $order_data);

        $escrow_address = $setting->systemSetting["marketplaceEscrowWalletAddress"];
        if ($advertisement["is_cryptocurrency"]) {
            $return_data["escrow_address"] = $escrow_address;
            $return_data["advertisement_order_id"] = $order_id;
            $return_data["escrow_amount"] = $order_data["amount"];
            $return_data["escrow_cryptocurrency"] = $advertisement["type"] == "buy" ? $order_data["crypto_currency"] : $order_data["currency"];
        } else if ($advertisement["type"] == "buy") {
            $return_data["escrow_address"] = $escrow_address;
            $return_data["advertisement_order_id"] = $order_id;
            $return_data["escrow_amount"] = $order_data["quantity"];
            $return_data["escrow_cryptocurrency"] = $order_data["crypto_currency"];
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Order placed", "data" => $return_data);
    }


    private function send_failed_place_order ($newParams, $advertisement, $error_message)
    {
        global $xunXmpp, $xun_numbers;

        $advertisement_id = trim($newParams["advertisement_id"]);
        $quantity = trim($newParams["quantity"]);
        $currency = trim($newParams["currency"]);
        $username = trim($newParams["username"]);
        $price = trim($newParams["price"]);

        $owner_username = $newParams["owner_username"];
        $owner_nickname = $newParams["owner_nickname"];
        $owner_ip = $newParams["owner_ip"];
        $owner_device_os = $newParams["owner_device_os"];
        $owner_country = $newParams["owner_country"];

        $user_nickname = $newParams["user_nickname"];
        $user_device_os = $newParams["user_device_os"];
        $user_ip = $newParams["user_ip"];
        $order_country = $newParams["order_country"];
        $order_price = $newParams["order_price"];
        
        $date = date("Y-m-d H:i:s");

        $price_type = $advertisement["price_type"];
        $ads_type = $advertisement["type"];

        $min_limit = $advertisement["min"];
        $max_limit = $advertisement["max"];

        $tag = "Fail Placing Order";
        $content = "Ads Owner\n";
        $content .= "Username: " . $owner_nickname . "\n";
        $content .= "Phone Number: " . $owner_username . "\n";
        $content .= "IP: " . $owner_ip . "\n";
        $content .= "Country: " . $owner_country . "\n";
        $content .= "Device: " . $owner_device_os . "\n";
        $content .= "Advertisement ID: " . $advertisement_id . "\n";
        $content .= "Advertisement Type: " . ucfirst($ads_type) . "\n";
        $content .= ucfirst($ads_type). ": " . ucfirst($advertisement["crypto_currency"]) . "\n";
        if($ads_type == "sell"){
            $content .= "Accept with: " . ucfirst($currency) . "\n";
        }else if ($ads_type == "buy"){
            $content .= "Pay with: " . ucfirst($currency) . "\n";
        }
        $content .= "Price setting: " . ucfirst($price_type) . "\n";
        if ($price_type == "fix"){
            $content .= "Price: " . $advertisement["price"] . "\n";
        }
        else if($price_type == "floating"){
            $content .= "Floating ratio: " . $advertisement["floating_ratio"] . "\n";
            if ($ads_type == "sell"){
                $content .= "Min Price: " . $advertisement["price_limit"] . "\n";
            }else if ($ads_type == "buy"){
                $content .= "Max Price: " . $advertisement["price_limit"] . "\n";
            }
        }
        if($advertisement["status"] == "new"){
            $content .= "Transaction limit: " . $min_limit . " - " . $max_limit . "\n";

            $content .= "\nPlace Order By \n";
            $content .= "Username: " . $user_nickname . "\n";
            $content .= "Phone Number: " . $username . "\n";
            $content .= "IP: " . $user_ip . "\n";
            $content .= "Country: " . ucfirst($order_country) . "\n";
            $content .= "Device: " . $user_device_os . "\n";
            $content .= "Order Price: " . $order_price . "\n";
            $content .= "Order Volume: " . $quantity . "\n";
        }
        //$content .= "Price setting: " . $price_setting . "\n";
        $content .= "\nMessage: " . $error_message . "\n";
        $content .= "Time: " . date("Y-m-d H:i:s");

        //$erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");
        //$erlang_params["mobile_list"] = array();
        //$xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);
        return $xmpp_result;
    }

    private function can_place_order($advertisement, $order_quantity)
    {
        //checks for max_processing_order and locked quota
        // check maximum_processing_orders
        $max_processing_orders = $advertisement["max_processing_orders"];

        $total_active_order = $this->get_advertisement_orders_count($advertisement);
        if ($max_processing_orders !== 0) {
            // orders that are not completed, expired, cancelled

            if ($total_active_order >= $max_processing_orders) {
                // return false;
                return array("code" => 0, "message" => "FAILED", "message_d" => "The maximum order placed has reached. Please try again later.", "errorCode" => -103);
            }
        }

        // get remainder
        $remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement);

        if ($remaining_quantity < $order_quantity) {
            $error_message = "Your buy quantity cannot be more than the selling quantity advertised.";
            // } else {
            //     $error_message = "Your sell quantity cannot be more than the buy quantity advertised.";
            // }

            return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "errorCode" => -104, "data" => array("remaining_quantity" => $remaining_quantity, "order_quantity" => $order_quantity));
        }

        return array("total_active_order" => $total_active_order, "remaining_quantity" => $remaining_quantity);

    }

    private function store_advertisement_order_cache_data($advertisement_order, $table_name)
    {
        $db = $this->db;

        $insert_data = array(
            "order_id" => $advertisement_order["order_id"],
            "user_id" => $advertisement_order["user_id"],
            "table_name" => $table_name,
            "type" => $advertisement_order["type"],
            "status" => $advertisement_order["status"],
            "expires_at" => $advertisement_order["expires_at"],
            "created_at" => $advertisement_order["created_at"],
            "updated_at" => $advertisement_order["updated_at"],
        );

        $db->insert("xun_marketplace_advertisement_order_cache", $insert_data);
    }

    private function update_advertisement_order_cache_data($advertisement_order)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");
        $update_data = [];
        $update_data["status"] = $advertisement_order["status"];
        $update_data["updated_at"] = $date;

        if (isset($advertisement_order["expires_at"])) {
            $update_data["expires_at"] = $advertisement_order["expires_at"];
        }

        $db->where("order_id", $advertisement_order["order_id"]);
        $db->update("xun_marketplace_advertisement_order_cache", $update_data);

    }

    public function get_marketplace_payment_method_listing($params)
    {
        $db = $this->db;

        $db->where("status", 1);
        $db->where("record_type", "system");
        $db->orderBy("sort_order", "ASC");
        $db->orderBy("name", "ASC");
        $payment_method = $db->get("xun_marketplace_payment_method", null, "id, name, image, payment_type");

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Payment method listing.", "data" => $payment_method ? $payment_method : []);
    }

    public function get_marketplace_payment_method_listing_v2($params)
    {
        global $setting;
        $db = $this->db;

        $db->where("status", 1);
        $db->where("record_type", "system");
        $db->orderBy("sort_order", "ASC");
        $db->orderBy("name", "ASC");
        $payment_methods = $db->get("xun_marketplace_payment_method", null, "id, name, image, payment_type, country");

        $result = [];
        $online_banks_data = [];
        foreach ($payment_methods as $payment_method) {
            if ($payment_method["payment_type"] != "Online Banking") {
                $result[] = $payment_method;
                continue;
            }

            $country = ucwords($payment_method["country"]);
            $online_banks_data[$country][] = array(
                "id" => $payment_method["id"],
                "name" => $payment_method["name"],
                "image" => $payment_method["image"],
            );
        }

        $result[] = array(
            "payment_type" => "Online Banking",
            "data" => $online_banks_data,
        );

        $default_bank_image = $setting->systemSetting["marketplaceDefaultBankImage"];

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Payment method listing.", "data" => $result ? $result : [], "default_bank_image" => $default_bank_image);
    }

    public function get_supported_currencies($params)
    {
        $db = $this->db;
        $general = $this->general;

        $country_code = trim($params["country_iso_code_2"]);
        $username = trim($params["username"]);

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $db->where("status", 1);
        $xun_currencies = $db->get("xun_marketplace_currencies", null, 'name, type, symbol, currency_id, image');

        $currency_arr = array_column($xun_currencies, 'currency_id');
        $user_currency = in_array($country_currency_code, $currency_arr) ? $country_currency_code : "usd";

        $currencies = [];
        $cryptocurrencies = [];
        foreach ($xun_currencies as $data) {
            if ($data["type"] == "currency") {
                $currencies[] = $data;

            } else {
                $cryptocurrencies[] = $data;
            }
        }

        $return_data = array("currency" => $currencies, "cryptocurrency" => $cryptocurrencies);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Currencies listing.", "data" => $return_data);
    }

    public function get_user_country_currency_code($username, $country_code)
    {
        $db = $this->db;
        $general = $this->general;

        if ($country_code == '') {
            $mobileNumberInfo = $general->mobileNumberInfo($username, null);
            if ($mobileNumberInfo["isValid"] == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/);
            }

            $country_code = $mobileNumberInfo["regionCode"];
        } else {
            $country_code = strtolower($country_code);
        }

        $db->where("iso_code2", $country_code);
        $country_currency_code = $db->getValue("country", "currency_code");

        $country_currency_code = empty($country_currency_code) ? 'usd' : strtolower($country_currency_code);
    }

    public function get_order_no()
    {
        $db = $this->db;
        return $db->getNewID();
    }

    public function get_currency_rate_in_usd($currency)
    {
        global $setting;
        $db = $this->db;

        $db->where("currency", $currency);
        $currency_rec = $db->getOne("xun_currency_rate");

        $currency_rate = $currency_rec["exchange_rate"];
        $currency_rate = $setting->setDecimal($currency_rate, "marketplacePrice");
        return $currency_rate;
    }

    public function get_cryptocurrency_rate($cryptocurrency)
    {
        global $setting, $xunCurrency;
        $db = $this->db;

        if (is_array($cryptocurrency)) {
            $db->where("currency_id", $cryptocurrency, "in");
            $supported_currencies = $db->map("currency_id")->ObjectBuilder()->get("xun_marketplace_currencies", null, "currency_id, type, fiat_currency_id");

            $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);
            return $cryptocurrency_rate_arr;
        } else {
            $db->where("currency_id", $cryptocurrency);
            $supported_currencies = $db->map("currency_id")->ObjectBuilder()->get("xun_marketplace_currencies", null, "currency_id, type, fiat_currency_id");

            $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);
            $cryptocurrency_value = $full_currency_list[$cryptocurrency];
            return $cryptocurrency_value;
        }
    }

    public function get_effective_floating_price($cryptocurrency, $currency, $cryptocurrency_price_in_usd, $currency_rate, $floating_ratio = 0, $usd_pricing = false, $supported_currencies = null)
    {
        global $setting, $xunCurrency;

        $cryptocurrency_price_in_currency = $xunCurrency->get_rate($cryptocurrency, $currency);

        $floating_price_usd = $cryptocurrency_price_in_usd + ($cryptocurrency_price_in_usd * ($floating_ratio / 100));
        $floating_price_usd = $setting->setDecimal($floating_price_usd, "marketplacePrice");

        $floating_price_currency = $cryptocurrency_price_in_currency + ($cryptocurrency_price_in_currency * ($floating_ratio / 100));
        $floating_price_currency = $setting->setDecimal($floating_price_currency, "marketplacePrice");

        if ($usd_pricing) {
            return array("price_in_currency" => $floating_price_currency, "price_in_usd" => $floating_price_usd);
        }
        return $floating_price_currency;
    }

    public function get_effective_floating_price_c2c($c2c_price, $floating_ratio)
    {
        global $setting;

        $floating_price = $c2c_price + ($c2c_price * ($floating_ratio / 100));
        $floating_price = $setting->setDecimal($floating_price);

        return $floating_price;
    }

    public function get_cryptocurrency_price($currency, $cryptocurrency, $currency_info_arr, $currency_rates_arr)
    {
        global $xunCurrency;

        $price = $xunCurrency->get_rate($cryptocurrency, $currency);
        return $price;
    }

    public function get_advertisement_price($advertisement, $currency = null, $crypto_price_in_usd = null, $currency_rate_in_usd = null, $usd_pricing = false)
    {
        global $setting;

        $price_type = $advertisement["price_type"];
        $crypto_currency = $advertisement["crypto_currency"];

        if (!$currency) {
            $currency_list = explode("##", $advertisement["currency"]);
            $currency = $currency_list[0];
        }

        if (!$crypto_price_in_usd) {
            $crypto_price_in_usd = $this->get_cryptocurrency_rate($crypto_currency);
        }
        if (!$currency_rate_in_usd) {
            if ($advertisement["is_cryptocurrency"]) {
                $currency_rate_in_usd = $this->get_cryptocurrency_rate($currency);
                $currency_rate_in_usd = $setting->setDecimal($currency_rate_in_usd);
            } else {
                $currency_rate_in_usd = $this->get_currency_rate_in_usd($currency);
                $currency_rate_in_usd = $setting->setDecimal($currency_rate_in_usd, "marketplacePrice");
            }
        }

        $crypto_price_in_usd = $setting->setDecimal($crypto_price_in_usd, "marketplacePrice");

        if ($advertisement["is_cryptocurrency"]) {
            // will never enter
            if ($price_type == "floating") {
                $floating_ratio = $advertisement["floating_ratio"];
                $price = $this->get_effective_floating_price($crypto_currency, $currency, $crypto_price_in_usd, $currency_rate_in_usd, $floating_ratio, $usd_pricing);
                return $price;
            } else {
                $price = $advertisement["price"];
                $price_in_usd = $price;
                $price = $price_in_usd * $currency_rate_in_usd;
                $price = $setting->setDecimal($price, "marketplacePrice");
            }
        } else {
            if ($price_type == "floating") {
                $floating_ratio = $advertisement["floating_ratio"];
                $price = $this->get_effective_floating_price($crypto_currency, $currency, $crypto_price_in_usd, $currency_rate_in_usd, $floating_ratio, $usd_pricing);
                return $price;
            } else {
                $price = $advertisement["price"];
                if($advertisement["price_unit"]){
                    $price = $setting->setDecimal($price, "marketplacePrice");
                    $price_in_usd = bcmul((string)$price, (string)$currency_rate_in_usd, 2);
                }else{
                    $price_in_usd = $price;
                    $price = $price_in_usd / $currency_rate_in_usd;
                    $price = $setting->setDecimal($price, "marketplacePrice");
                }
            }
        }

        if ($usd_pricing) {
            return array("price_in_currency" => $price, "price_in_usd" => $price_in_usd);
        }

        return $price;
    }

    private function get_advertisement_effective_price_c2c($advertisement, $c2c_price){
        global $setting;

        $usd_pricing = true;

        $advertisement_type = $advertisement["type"];
        $price_type = $advertisement["price_type"];
        $price = $advertisement["price"];
        $floating_ratio = $advertisement["floating_ratio"];
        $price_limit = $advertisement["price_limit"]; // initial currency

        if ($price_type == "floating") {
            $floating_ratio = $advertisement["floating_ratio"];

            $floating_price = $c2c_price + ($c2c_price * ($floating_ratio / 100));
            $price = $floating_price;

            if ($advertisement_type == "sell" && ($price < $price_limit)) {
                $price = $price_limit;
            } else if ($advertisement_type == "buy" && ($price > $price_limit)) {
                $price = $price_limit;
            }
        }

        return $price;
    }

    private function get_advertisement_effective_price($advertisement, $currency, $crypto_price_in_usd, $currency_rate_in_usd, $limit_currency_rate = null, $c2c_price = null)
    {
        /** gets advertisement price taking into account floating ratio and price limit*/
        global $setting;

        $usd_pricing = true;

        $advertisement_type = $advertisement["type"];
        $price_type = $advertisement["price_type"];
        $price = $advertisement["price"];
        $floating_ratio = $advertisement["floating_ratio"];
        $price_limit = $advertisement["price_limit"]; // initial currency

        if ($advertisement["is_cryptocurrency"]) {
            if ($price_type == "floating") {
                $floating_ratio = $advertisement["floating_ratio"];

                $floating_price = $c2c_price + ($c2c_price * ($floating_ratio / 100));
                $price = $floating_price;

                if ($advertisement_type == "sell" && ($price < $price_limit)) {
                    $price = $price_limit;
                } else if ($advertisement_type == "buy" && ($price > $price_limit)) {
                    $price = $price_limit;
                }
            }
            $price = $setting->setDecimal($price);

            return $price;
        } else {
            $price_unit = $advertisement["price_unit"];

            $price_limit_usd = $price_limit * $limit_currency_rate;

            $price_limit_currency = $price_limit_usd / $currency_rate_in_usd;
            $price_limit_currency = $setting->setDecimal($price_limit_currency, "marketplacePrice");
            
            $advertisement_price = $this->get_advertisement_price($advertisement, $currency, $crypto_price_in_usd, $currency_rate_in_usd, $usd_pricing);
            
            // echo "\n !! price_limit_usd $price_limit_usd price_limit $price_limit limit_currency_rate $limit_currency_rate price_limit_currency $price_limit_currency currency_rate_in_usd $currency_rate_in_usd";
            // print_r($advertisement_price);
            if ($price_type == "floating") {
                $live_price_in_currency = $advertisement_price["price_in_currency"];

                //  get final price after market price and min/max limit comparison
                if ($advertisement_type == "sell") {
                    if ($live_price_in_currency < $price_limit_currency) {
                        $advertisement_price["price_in_currency"] = $price_limit_currency;
                        $advertisement_price["price_in_usd"] = $price_limit_usd;
                    }
                } else {
                    if ($live_price_in_currency > $price_limit_currency) {
                        $advertisement_price["price_in_currency"] = $price_limit_currency;
                        $advertisement_price["price_in_usd"] = $price_limit_usd;

                    }
                }
            }
        }
        
        return $advertisement_price;

    }

    private function get_advertisement_payment_method($advertisement_id, $payment_method_details = null, $is_cryptocurrency = null)
    {
        $db = $this->db;

        if ($is_cryptocurrency) {
            $default_c2c_payment_method = $this->get_default_c2c_payment_method();

            return [$default_c2c_payment_method];
        }

        $db->where("a.advertisement_id", $advertisement_id);
        $db->where("a.status", 1);
        $db->join("xun_marketplace_payment_method b", "a.payment_method_id=b.id", "LEFT");
        $db->orderBy("b.id", "ASC");

        $columns = "b.id, b.name, b.image, b.payment_type, b.country";
        if ($payment_method_details) {
            $columns .= ", a.account_name, a.account_no, a.qr_code";
        }

        $payment_method_arr = $db->get("xun_marketplace_advertisement_payment_method a", null, $columns);

        $payment_method_arr = $payment_method_arr ? $payment_method_arr : [];

        return $payment_method_arr;
    }

    private function get_default_c2c_payment_method()
    {
        $db = $this->db;

        $db->where("record_type", "c2c");
        $db->where("status", 1);

        $payment_method = $db->getOne("xun_marketplace_payment_method", "name, image");

        return $payment_method;
    }

    private function compose_advertisement_data($data)
    {
        $db = $this->db;
        $general = $this->general;

        $db->where("id", $data["user_id"]);
        $xun_user = $db->getOne("xun_user");
        $username = $xun_user["username"];
        $nickname = $xun_user["nickname"];
        $return_data = [];
        $return_data["id"] = $data["id"];
        $return_data["username"] = $username;
        $return_data["created_at"] = $general->formatDateTimeToIsoFormat($data["created_at"]);
        $return_data["updated_at"] = $general->formatDateTimeToIsoFormat($data["updated_at"]);
        $return_data["expires_at"] = $general->formatDateTimeToIsoFormat($data["expires_at"]);
        $return_data["quantity"] = $data["quantity"];
        $return_data["type"] = $data["type"];
        $return_data["crypto_currency"] = $data["crypto_currency"];
        $return_data["currency"] = $data["currency"];
        $return_data["nickname"] = $nickname;
        $return_data["price_type"] = $data["price_type"];

        return $return_data;
    }

    private function compose_advertisement_order_data($data, $username = null)
    {
        global $setting;
        $db = $this->db;
        $general = $this->general;

        $return_data = [];
        // $return_data["id"] = $data["id"];
        $return_data["order_id"] = (string) $data["order_id"];
        $return_data["order_no"] = (string) $data["order_no"];
        // $return_data["username"] = $username;
        $return_data["advertisement_id"] = $data["advertisement_id"];
        $return_data["created_at"] = $general->formatDateTimeToIsoFormat($data["created_at"]);
        $return_data["updated_at"] = $general->formatDateTimeToIsoFormat($data["updated_at"]);
        $return_data["expires_at"] = $general->formatDateTimeToIsoFormat($data["expires_at"]);
        $return_data["quantity"] = $data["quantity"];
        $return_data["status"] = $data["status"];
        $return_data["type"] = $data["type"];
        $return_data["price"] = $data["price"];

        return $return_data;
    }

    public function compose_advertisement_order_details_data($advertisement, $advertisement_order, $currency_rate = null, $decimal_place_setting)
    {
        global $setting;
        $db = $this->db;

        $advertisement_data = $this->compose_advertisement_order_data($advertisement_order);

        $currency = $advertisement_order["currency"];
        $advertisement_data["currency"] = $currency;
        $advertisement_data["crypto_currency"] = $advertisement["crypto_currency"];

        $decimal_places = $decimal_place_setting["decimal_places"];
        if ($advertisement["is_cryptocurrency"]) {
            $price = $advertisement_order["price"];

            if ($advertisement["type"] == "buy") {
                $amount = bcdiv((string)$advertisement_order["quantity"], (string)$price, $decimal_places);
            } else {
                $amount = bcmul((string)$advertisement_order["quantity"], (string)$price, $decimal_places);
            }
        } else {
            if($advertisement["price_type"] == "fix" && $advertisement["price_unit"])
            {
                $price = $advertisement["price"];
                $price = $setting->setDecimal($price, "marketplacePrice");
            }else
            {
                $price = bcdiv((string)$advertisement_data["price"], (string)$currency_rate, 2);
            }
            $amount = $advertisement_data["quantity"] * $price;
            $amount = $setting->setDecimal($amount, "marketplacePrice");
        }

        $advertisement_data["price"] = $price;
        $advertisement_data["amount"] = $amount;

        return $advertisement_data;
    }

    public function xmpp_marketplace_chat_room($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $user_host = trim($params["user_host"]);
        $chatroom_id = trim($params["chatroom_id"]);
        $chatroom_host = trim($params["chatroom_host"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        if ($user_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "user_host cannot be empty");
        }
        if ($chatroom_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "chatroom_id cannot be empty");
        }
        if ($chatroom_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "chatroom_host cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $db->where("advertisement_order_id", $chatroom_id);
        $db->where("status", "open");
        $marketplace_chatroom = $db->getOne("xun_marketplace_chat_room");

        if (!$marketplace_chatroom) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "chatroom does not exists");
        }

        if (!($marketplace_chatroom["owner_user_id"] == $user_id || $marketplace_chatroom["user_id"] == $user_id)) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "chatroom does not exists");
        }

        $recipients_id = [$marketplace_chatroom["owner_user_id"], $marketplace_chatroom["user_id"]];
        $recipients_username = [];
        foreach ($recipients_id as $data) {
            $db->where("id", $data);
            $user = $db->getOne("xun_user");

            if ($user) {
                $recipients_username[] = $user["username"];
            }
        }
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Chatroom participants.", "return_data" => array("chatroom_id" => (string) $chatroom_id, "chatroom_host" => $chatroom_host, "username" => $username, "user_host" => $user_host, "recipients" => $recipients_username));
    }

    public function paid_advertisement_order($params)
    {
        global $xunXmpp, $xunUser, $xun_numbers;

        $general = $this->general;
        $db = $this->db;

        $advertisement_id = trim($params["advertisement_id"]);
        $order_id = trim($params["advertisement_order_id"]);
        $username = trim($params["username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty");
        }

        if ($order_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Order ID cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $date = date("Y-m-d H:i:s");

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");
        $owner_user_id = $advertisement["user_id"];

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $advertisement_date = $advertisement["created_at"];
        $advertisement_type = $advertisement["type"];

        $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        $db->where("order_id", $order_id);
        $db->where("advertisement_id", $advertisement_id);
        $db->where("disabled", 0);

        $advertisement_order = $db->getOne($advertisement_order_table);

        if (!$advertisement_order) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        if ($advertisement_order["status"] != "pending_payment") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This action is not allowed for this advertisement order.", "errorCode" => -101, "status" => $advertisement_order["status"]);
        }

        if ($advertisement_order["user_id"] != $user_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This action is not allowed for this advertisement order.", "errorCode" => -101);
        }

        if ($advertisement["type"] == "sell") {
            $seller_user_id = $advertisement["user_id"];
        } else {
            $db->where("order_id", $order_id);
            // $db->where("order_type", "place_order");
            $db->orderBy("created_at", "asc");
            $order_placement = $db->getOne($advertisement_order_table);
            $seller_user_id = $order_placement["user_id"];
        }

        $db->where("id", $seller_user_id);
        $seller_user = $db->getOne("xun_user");
        $seller_username = $seller_user["username"];
        $seller_nickname = $seller_user["nickname"];

        $user_nickname = $xun_user["nickname"];

        // check if expired
        $order_expires_at = $advertisement_order["expires_at"];
        if ($order_expires_at < $date) {
            $this->update_expired_advertisement_order($advertisement, $advertisement_order, $advertisement_order_table, $username, $user_nickname, $seller_username, $seller_nickname);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This order has expired.", "errorCode" => -102);
        }

        $new_status = "paid";
        $update_data = [];
        $update_data["status"] = $new_status;
        $update_data["updated_at"] = $date;

        $db->where("id", $advertisement_order["id"]);
        $db->update($advertisement_order_table, $update_data);

        $new_advertisement_order = [];
        $new_advertisement_order["order_id"] = $advertisement_order["order_id"];
        $new_advertisement_order["status"] = $new_status;
        $new_advertisement_order["updated_at"] = $date;

        $this->update_advertisement_order_cache_data($new_advertisement_order);
        // TODO:
        // send xmpp message

        $owner_username = $advertisement["type"] == "sell" ? $seller_username : $username;
        $owner_nickname = $advertisement["type"] == "sell" ? $seller_nickname : $user_nickname;

        $xmpp_recipients = [$seller_username, $username];
        $marketplace_chat_room_host = $xunXmpp->get_marketplace_host();
        $erlang_params = array(
            "chatroom_id" => (string) $order_id,
            "chatroom_host" => $marketplace_chat_room_host,
            "recipients" => $xmpp_recipients,
            "type" => $new_status,
            "data" => array(
                "advertisement_id" => (string) $advertisement_id,
                "order_id" => (string) $order_id,
                "username" => $username,
                "nickname" => $user_nickname,
                "owner_username" => $owner_username,
                "owner_nickname" => $owner_nickname,
                "seller_username" => $seller_username,
                "seller_nickname" => $seller_nickname,
                "buyer_username" => $username,
                "buyer_nickname" => $user_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "created_at" => $general->formatDateTimeToIsoFormat($date),
            ),
        );
        $xunXmpp->send_xmpp_marketplace_event($erlang_params);

        $order_user_id = $advertisement_order["user_id"];
        $order_user = $db->where("id", $order_user_id)->getOne("xun_user");
        $order_username = $order_user["username"];
        $order_nickname = $order_user["nickname"];

        $user_country_info_arr = $xunUser->get_user_country_info([$owner_username, $order_username]);
        $owner_country_info = $user_country_info_arr[$owner_username];
        $owner_country = $owner_country_info["name"];
        
        $order_country_info = $user_country_info_arr[$username];
        $order_country = $order_country_info["name"];

        $owner_device_ip_arr = $xunUser->get_device_os_ip($owner_user_id, $owner_username);
        $owner_ip = $owner_device_ip_arr["ip"];
        $owner_device_os = $owner_device_ip_arr["device_os"];

        $order_device_ip_arr = $xunUser->get_device_os_ip($order_user_id, $order_username);
        $order_ip = $order_device_ip_arr["ip"];
        $order_device_os = $order_device_ip_arr["device_os"];

        $ads_min_max = $this->get_ads_order_min_max_cryptocurrency($advertisement, $advertisement_order);
        $min_limit = $ads_min_max["min"];
        $max_limit = $ads_min_max["max"];

        $currency = $advertisement["currency"];
        $price_type = $advertisement["price_type"];
        $ads_type = $advertisement["type"];
        $order_price = $advertisement_order["price"];
        $order_price = $this->order_price_convert($advertisement, $advertisement_order, $order_price);

        $content = "Ads Owner\n";
        $content .= "Username: " . $owner_nickname . "\n";
        $content .= "Phone Number: " . $owner_username . "\n";
        $content .= "IP: " . $owner_ip . "\n";
        $content .= "Country: " . $owner_country . "\n";
        $content .= "Device: " . $owner_device_os . "\n";
        $content .= "Advertisement ID: " . $advertisement_id . "\n";
        $content .= "Advertisement Type: " . ucfirst($advertisement["type"]) . "\n";
        $content .= ucfirst($ads_type). ": " . ucfirst($advertisement["crypto_currency"]) . "\n";
        if($ads_type == "sell"){
            $content .= "Accept with: " . ucfirst($currency) . "\n";
        }else if ($ads_type == "buy"){
            $content .= "Pay with: " . ucfirst($currency) . "\n";
        }
        $content .= "Price setting: " . ucfirst($price_type) . "\n";
        if ($price_type == "fix"){
            $content .= "Price: " . $advertisement["price"] . " " . ucfirst($advertisement["price_unit"]) . "\n";
        }
        else if($price_type == "floating"){
            $content .= "Floating ratio: " . $advertisement["floating_ratio"] . "\n";
            if ($ads_type == "sell"){
                $content .= "Min Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
            }else if ($ads_type == "buy"){
                $content .= "Max Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
            }
        }
        $content .= "Transaction limit: " . $min_limit . " - " . $max_limit . "\n";

        $content .= "\nPlace Order By \n";
        $content .= "Username: " . $order_nickname . "\n";
        $content .= "Phone Number: " . $order_username . "\n";
        $content .= "IP: " . $order_ip . "\n";
        $content .= "Country: " . ucfirst($order_country) . "\n";
        $content .= "Device: " . $order_device_os . "\n";
        $content .= "Order Price: " . $order_price . "\n";
        $content .= "Order Volume: " . $advertisement_order["quantity"] . "\n";
        $content .= "Order ID: " . $order_id . "\n";

        //$content .= "Price setting: " . $price_setting . "\n";
        $content .= "\nTime: " . date("Y-m-d H:i:s");

        $tag = "Placed Order (Paid)";
        $erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");
        //$erlang_params["mobile_list"] = array();
        //$xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Order paid.");
    }

    public function cancel_advertisement($params)
    {
        global $xun_numbers, $xunUser, $xunXmpp, $setting, $xunCurrency;
        $db = $this->db;

        $advertisement_id = trim($params["advertisement_id"]);
        $username = trim($params["username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];

        $db->where("user_id", $user_id);
        $db->where("name", "lastLoginIP");
        $ip = $db->getValue("xun_user_setting", "value");

        $date = date("Y-m-d H:i:s");

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        $ads_type = $advertisement["type"];
        $crypto_currency = $advertisement["crypto_currency"];
        $currency = $advertisement["currency"];
        $price = $advertisement["price"];
        $price_type = $advertisement["price_type"];
        $price_limit = $advertisement["price_limit"];
        $min = $advertisement["min"];
        $max = $advertisement["max"];

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }

        if ($advertisement["user_id"] != $user_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You are not allowed to cancel this advertisement", "errorCode" => -101);
        }

        $user_country_info_arr = $xunUser->get_user_country_info([$username]);
        $user_country_info = $user_country_info_arr[$username];
        $user_country = $user_country_info["name"];

        $device_os = $db->where("mobile_number", $username)->getValue("xun_user_device", "os");
        $device_os = $device_os == 1 ? $device_os = "Android" : $device_os = "iOS";

        // check status and expiration
        if ($advertisement["expires_at"] < $date) {
            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = "expired";

            $db->where("id", $advertisement_id);
            $db->update("xun_marketplace_advertisement", $update_data);

            // refund expired advertisement
            $refund_response = $this->escrow_refund_advertisement($advertisement);
            if ($refund_response) {
                $refund_quantity = $refund_response["refund_quantity"];
            }

            $return_data = array(
                "advertisement_id" => $advertisement_id,
                "remaining_quantity" => $refund_quantity,
            );

            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This advertisement had expired", "errorCode" => -102, "data" => $return_data);
        }

        if ($advertisement["status"] == "cancelled") {

            return array('code' => 0, 'message' => "SUCCESS", 'message_d' => "Advertisement is already cancelled.", "status" => $advertisement["status"], "errorCode" => -103);
        }

        $update_data = [];
        $update_data["updated_at"] = $date;
        $update_data["status"] = "cancelled";

        $db->where("id", $advertisement_id);
        $db->update("xun_marketplace_advertisement", $update_data);

        $refund_response = $this->escrow_refund_advertisement($advertisement);
        if ($refund_response) {
            $refund_quantity = $refund_response["refund_quantity"];
        }

        $return_data = array(
            "advertisement_id" => $advertisement_id,
            "remaining_quantity" => $refund_quantity,
        );

        if ($refund_quantity < $max){
            $max = $refund_quantity;
        }
        $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($crypto_currency, true);
        $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

        $c2c_rate = $xunCurrency->get_rate($crypto_currency, $currency);
        if ($floating_ratio != 0){
            $floating_price = $c2c_rate + ($c2c_rate * ($floating_ratio / 100));
            $floating_price = $setting->setDecimal($floating_price, $currency_dp_credit_type);
        }else{
            $floating_price = $c2c_rate;
        }
        if($ads_type == "sell"){
            if ($price_type == "fix"){
                $min = $min / $price;
            }else{
                $min = $min / $floating_price;
            }

        }else if($ads_type == "buy" && $advertisement["is_cryptocurrency"] == 1){
            if($price_type == "fix"){
                $max = $max / $price;
            }else{
                $max = $max / $floating_price;
            }
        }else{
            $max = $advertisement["max"];
        }
        $max = $setting->setDecimal($max, $crypto_dp_credit_type);
        $min = $setting->setDecimal($min, $crypto_dp_credit_type);

        $tag = "Advertisement Removed";

        $content = "Advertisement Owner\n";
        $content .= "Username: " . $nickname . "\n";
        $content .= "Phone number: " . $username . "\n";
        $confent .= "IP: " . $ip . "\n";   
        $content .= "Country: " . ucfirst($user_country) . "\n";
        $content .= "Device: " . $device_os . "\n"; 
        $content .= "Advertisement ID: " . $advertisement_id . "\n";
        $content .= "Advertisement Type: " . ucfirst($ads_type) . "\n";

        if ($ads_type == "buy"){
            $content .= "Buy: " . ucfirst($crypto_currency) . "\n";
            $content .= "Pay with: " . ucfirst($currency) . "\n";
            $content .= "Price setting: " . ucfirst($price_type) . "\n";
            if ($price_type == "floating"){
                $content .= "Max Price: " . $price_limit . "\n";
            }else{
                $content .= "Price: " . $price . "\n";
            }
        }

        else if($ads_type == "sell"){
            $content .= "Sell: " . ucfirst($crypto_currency) . "\n";
            $content .= "Accept with: " . ucfirst($currency) . "\n";
            $content .= "Price setting: " . ucfirst($price_type) . "\n";
            if($price_type == "floating"){
                $content .= "Min Price: " . $price_limit . "\n";
            }else{
                $content .= "Price: " . $price . "\n";
            }
        }
        $content .= "Transaction Limit: " . $min . " - " . $max . "\n";
        $content .= "\nTime: " . $date;

        //$erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = $xun_numbers;
        //$erlang_params["mobile_list"] = array();
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");
        //$xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Advertisement cancelled.", "data" => $return_data);
    }

    public function cancel_advertisement_order($params)
    {
        // only buyer can cancel order, when status is paid, pending_payment
        global $xunXmpp, $xunUser, $xun_numbers;

        $db = $this->db;
        $general = $this->general;

        $advertisement_id = trim($params["advertisement_id"]);
        $order_id = trim($params["advertisement_order_id"]);
        $username = trim($params["username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty");
        }

        if ($order_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Order ID cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];
        $user_nickname = $xun_user["nickname"];

        $date = date("Y-m-d H:i:s");

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $advertisement_date = $advertisement["created_at"];
        $advertisement_type = $advertisement["type"];
        // get advertisement
        $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        $db->where("order_id", $order_id);
        $db->where("advertisement_id", $advertisement_id);
        $db->where("disabled", 0);

        $advertisement_order = $db->getOne($advertisement_order_table);

        if (!$advertisement_order) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        // only buyer can cancel order
        if ($advertisement["type"] == "sell") {
            $seller_user_id = $advertisement["user_id"];
            $buyer_user_id = $advertisement_order["user_id"];
        } else {
            $db->where("order_id", $order_id);
            $db->orderBy("created_at", "asc");
            $seller_user_id = $db->getValue($advertisement_order_table, "user_id");
            $buyer_user_id = $advertisement["user_id"];
        }

        if ($buyer_user_id != $user_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You are not allowed to cancel this order.", "errorCode" => -104);
        }

        // only allow cancellation when status is pending payment and paid
        $advertisement_order_status = $advertisement_order["status"];
        if (!in_array($advertisement_order_status, array("paid", "pending_payment"))) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This advertisement order cannot be cancelled.", "errorCode" => -103, "status" => $advertisement_order_status);
        }

        $db->where("id", $seller_user_id);
        $seller_user = $db->getOne("xun_user");
        $seller_username = $seller_user["username"];
        $seller_nickname = $seller_user["nickname"];

        // check if expired
        $order_expires_at = $advertisement_order["expires_at"];
        if ($order_expires_at < $date) {
            $this->update_expired_advertisement_order($advertisement, $advertisement_order, $advertisement_order_table, $username, $user_nickname, $seller_username, $seller_nickname);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This order has expired.", "errorCode" => -102);
        }

        $new_status = "cancelled";

        $update_data = [];
        $update_data["updated_at"] = $date;
        $update_data["status"] = $new_status;

        $db->where("id", $advertisement_order["id"]);
        $db->update($advertisement_order_table, $update_data);

        $new_advertisement_order = [];
        $new_advertisement_order["order_id"] = $advertisement_order["order_id"];
        $new_advertisement_order["status"] = $new_status;
        $new_advertisement_order["updated_at"] = $date;

        $this->update_advertisement_order_cache_data($new_advertisement_order);

        $is_sold_out = $this->is_advertisement_sold_out($advertisement);

        if (!$is_sold_out) {
            $update_data = [];
            $update_data["sold_out"] = 0;
            $update_data["updated_at"] = $date;

            $db->where("id", $advertisement_id);
            $db->update("xun_marketplace_advertisement", $update_data);
        }

        // send xmpp message
        $owner_username = $advertisement["type"] == "sell" ? $seller_username : $username;
        $owner_nickname = $advertisement["type"] == "sell" ? $seller_nickname : $user_nickname;

        $xmpp_recipients = [$seller_username, $username];

        $marketplace_chat_room_host = $xunXmpp->get_marketplace_host();

        $erlang_params = array(
            "chatroom_id" => (string) $order_id,
            "chatroom_host" => $marketplace_chat_room_host,
            "recipients" => $xmpp_recipients,
            "type" => $new_status,
            "data" => array(
                "advertisement_id" => (string) $advertisement_id,
                "order_id" => (string) $order_id,
                "username" => $username,
                "nickname" => $user_nickname,
                "owner_username" => $owner_username,
                "owner_nickname" => $owner_nickname,
                "seller_username" => $seller_username,
                "seller_nickname" => $seller_nickname,
                "buyer_username" => $username,
                "buyer_nickname" => $user_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "created_at" => $general->formatDateTimeToIsoFormat($date),
            ),
        );

        $xunXmpp->send_xmpp_marketplace_event($erlang_params);

        $effective_remaining_quantity = $this->get_advertisement_effective_remaining_quantity($advertisement);
        $updated_advertisement_order = array_merge($advertisement_order, $new_advertisement_order);
        $this->process_closed_advertisement_order($advertisement, $updated_advertisement_order, $advertisement_order_table, $owner_username, $seller_username);

        if ($advertisement["type"] == "buy") {
            $return_data = array(
                "advertisement_id" => $advertisement_id,
                "remaining_quantity" => $effective_remaining_quantity,
            );
        }
        // if ($advertisement["type"] == "buy") {
        //     $return_data = array(
        //         "advertisement_id" => $advertisement_id,
        //         "remaining_quantity" => $effective_remaining_quantity,
        //     );

        //     // TODO: refund trading fee if ad is cancelled || expired
        //     // pass advertisement, order_id, seller_username
        //     $this->escrow_refund_advertisement_order($advertisement, $advertisement_order, $seller_username);

        //     // get_order_fund_out_amount
        // } else {
        //     if ($advertisement["type"] == "cancelled" || $advertisement["type"] == "expired") {
        //         // refund
        //         $this->escrow_refund_advertisement_order($advertisement, $advertisement_order, $seller_username);
        //     }
        // }

        $user_country_info_arr = $xunUser->get_user_country_info([$owner_username, $username]);
        $user_country_info = $user_country_info_arr[$username];
        $user_country = $user_country_info["name"];

        $ip_device_info_arr = $xunUser->get_device_os_ip($user_id, $username);
        $user_ip = $ip_device_info_arr["ip"];
        $user_device_os = $ip_device_info_arr["device_os"];

        //$price_setting = $advertisement["price_type"] == "fix" ? "Fix: " . $advertisement["price"] : "Floating: " . $advertisement["floating_ratio"];
        $price_type = $advertisement["price_type"];
        $ads_type = $advertisement["type"];
        $currency = $advertisement["currency"];
        
        $order_min_max = $this->get_ads_order_min_max_cryptocurrency($advertisement, $advertisement_order);

        $min_limit = $order_min_max["min"];
        $max_limit = $order_min_max["max"];

        $price_type = $advertisement["price_type"];
        $tag = "Advertisement Order Cancelled";
        $content = "Ads Owner\n";
        $content .= "Username: " . $user_nickname . "\n";
        $content .= "Phone Number: " . $username . "\n";
        $content .= "IP: " . $user_ip . "\n";
        $content .= "Country: " . $user_country . "\n";
        $content .= "Device: " . $user_device_os . "\n";
        $content .= "Advertisement ID: " . $advertisement_id . "\n";
        $content .= "Advertisement Type: " . ucfirst($ads_type) . "\n";
        $content .= ucfirst($ads_type). ": " . ucfirst($advertisement["crypto_currency"]) . "\n";
        if($ads_type == "sell"){
            $content .= "Accept with: " . ucfirst($currency) . "\n";
        }else if ($ads_type == "buy"){
            $content .= "Pay with: " . ucfirst($currency) . "\n";
        }
        $content .= "Price setting: " . ucfirst($price_type) . "\n";
        if ($price_type == "fix"){
            $content .= "Price: " . $advertisement["price"] . " ". $advertisement["price_unit"] . "\n";
        }
        else if($price_type == "floating"){
            $content .= "Floating ratio: " . $advertisement["floating_ratio"] . "\n";
            if ($ads_type == "sell"){
                $content .= "Min Price: " . $advertisement["price_limit"] . " ". ucfirst($advertisement["limit_currency"]) ."\n";
            }else if ($ads_type == "buy"){
                $content .= "Max Price: " . $advertisement["price_limit"] . " ". ucfirst($advertisement["limit_currency"]) . "\n";
            }
        }
        $content .= "Transaction limit: " . $min_limit . " - " . $max_limit . "\n";

        /*$erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");
        //$erlang_params["mobile_list"] = array();
        //$xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);*/


        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Order cancelled.", "data" => $return_data);
    }

    public function extend_time_advertisement_order($params)
    {
        global $setting, $xunXmpp, $xun_numbers, $xunUser;
        $db = $this->db;
        $general = $this->general;

        $advertisement_id = trim($params["advertisement_id"]);
        $order_id = trim($params["advertisement_order_id"]);
        $username = trim($params["username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty");
        }

        if ($order_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Order ID cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $date = date("Y-m-d H:i:s");

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");
        $owner_user_id = $advertisement["user_id"];

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $advertisement_date = $advertisement["created_at"];
        $advertisement_type = $advertisement["type"];

        $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        $db->where("order_id", $order_id);
        $db->where("advertisement_id", $advertisement_id);
        $db->where("disabled", 0);

        $advertisement_order = $db->getOne($advertisement_order_table);

        if (!$advertisement_order) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $db->where("order_id", $order_id);
        $db->orderBy("created_at", "asc");
        $order_placement = $db->getOne($advertisement_order_table);

        if ($advertisement["type"] == "sell") {
            $seller_user_id = $advertisement["user_id"];
            $buyer_user_id = $order_placement["user_id"];
        } else {
            $seller_user_id = $order_placement["user_id"];
            $buyer_user_id = $advertisement["user_id"];
        }

        if ($seller_user_id != $user_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This action is not allowed for this advertisement order.", "errorCode" => -101);
        }
        // if status = paid, cancelled, expired, then order cannot be cancelled
        $advertisement_order_status = $advertisement_order["status"];

        if ($advertisement_order_status != "pending_payment") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid action", "errorCode" => -103, "status" => $advertisement_order_status);
        }

        // check if expired
        $order_expires_at = $advertisement_order["expires_at"];
        if ($order_expires_at < $date) {
            $new_status = "expired";
            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = $new_status;

            $db->where("id", $advertisement_order["id"]);
            $db->update($advertisement_order_table, $update_data);

            $new_advertisement_order = [];
            $new_advertisement_order["order_id"] = $advertisement_order["order_id"];
            $new_advertisement_order["status"] = $new_status;
            $new_advertisement_order["updated_at"] = $date;

            $this->update_advertisement_order_cache_data($new_advertisement_order);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This order has expired.", "errorCode" => -102);
        }

        $db->where("order_id", $order_id);
        $db->where("status", "pending_payment");
        $pending_payment = $db->get($advertisement_order_table);

        if (sizeof($pending_payment) > 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This order has already extended its time.", "errorCode" => -104);
        }

        $update_data = [];
        $update_data["updated_at"] = $date;
        $update_data["disabled"] = 1;

        $db->where("id", $advertisement_order["id"]);
        $db->update($advertisement_order_table, $update_data);

        $marketplaceBuyerTransactionExpiration = $setting->systemSetting["marketplaceBuyerTransactionExpiration"];
        $buyer_transfer_expiration = "$marketplaceBuyerTransactionExpiration minutes";
        $expires_at = date("Y-m-d H:i:s", strtotime("+$buyer_transfer_expiration", strtotime($advertisement_order["expires_at"])));

        $order_status = "pending_payment";

        $new_advertisement_order = $advertisement_order;
        unset($new_advertisement_order["id"]);
        $new_advertisement_order["disabled"] = 0;
        $new_advertisement_order["expires_at"] = $expires_at;
        $new_advertisement_order["created_at"] = $date;
        $new_advertisement_order["updated_at"] = $date;

        $db->insert($advertisement_order_table, $new_advertisement_order);

        $this->update_advertisement_order_cache_data($new_advertisement_order);

        // send xmpp message

        $db->where("id", $buyer_user_id);
        $buyer_user = $db->getOne("xun_user");
        $buyer_username = $buyer_user["username"];
        $buyer_nickname = $buyer_user["nickname"];

        $user_nickname = $xun_user["nickname"];

        $owner_username = $advertisement["type"] == "sell" ? $username : $buyer_username;
        $owner_nickname = $advertisement["type"] == "sell" ? $user_nickname : $buyer_nickname;

        $xmpp_recipients = [$buyer_username, $username];
        $marketplace_chat_room_host = $xunXmpp->get_marketplace_host();
        $erlang_params = array(
            "chatroom_id" => (string) $order_id,
            "chatroom_host" => $marketplace_chat_room_host,
            "recipients" => $xmpp_recipients,
            "type" => "extend_time",
            "data" => array(
                "advertisement_id" => (string) $advertisement_id,
                "order_id" => (string) $order_id,
                "username" => $username,
                "nickname" => $user_nickname,
                "owner_username" => $owner_username,
                "owner_nickname" => $owner_nickname,
                "seller_username" => $username,
                "seller_nickname" => $user_nickname,
                "buyer_username" => $buyer_username,
                "buyer_nickname" => $buyer_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "expires_at" => $general->formatDateTimeToIsoFormat($expires_at),
                "created_at" => $general->formatDateTimeToIsoFormat($date),
            ),
        );
        $xunXmpp->send_xmpp_marketplace_event($erlang_params);
        $order_user_id = $advertisement_order["user_id"];
        $order_user = $db->where("id", $order_user_id)->getOne("xun_user");
        $order_username = $order_user["username"];
        $order_nickname = $order_user["nickname"];

        $user_country_info_arr = $xunUser->get_user_country_info([$owner_username, $order_username]);
        $owner_country_info = $user_country_info_arr[$owner_username];
        $owner_country = $owner_country_info["name"];
        
        $order_country_info = $user_country_info_arr[$order_username];
        $order_country = $order_country_info["name"];

        $owner_device_ip_arr = $xunUser->get_device_os_ip($owner_user_id, $owner_username);
        $owner_ip = $owner_device_ip_arr["ip"];
        $owner_device_os = $owner_device_ip_arr["device_os"];

        $order_device_ip_arr = $xunUser->get_device_os_ip($order_user_id, $order_username);
        $order_ip = $order_device_ip_arr["ip"];
        $order_device_os = $order_device_ip_arr["device_os"];

        $ads_min_max = $this->get_ads_order_min_max_cryptocurrency($advertisement, $advertisement_order);
        $min_limit = $ads_min_max["min"];
        $max_limit = $ads_min_max["max"];

        $currency = $advertisement["currency"];
        $price_type = $advertisement["price_type"];
        $ads_type = $advertisement["type"];
        $order_price = $advertisement_order["price"];
        $order_price = $this->order_price_convert($advertisement, $advertisement_order, $order_price);

        $content = "Ads Owner\n";
        $content .= "Username: " . $owner_nickname . "\n";
        $content .= "Phone Number: " . $owner_username . "\n";
        $content .= "IP: " . $owner_ip . "\n";
        $content .= "Country: " . $owner_country . "\n";
        $content .= "Device: " . $owner_device_os . "\n";
        $content .= "Advertisement ID: " . $advertisement_id . "\n";
        $content .= "Advertisement Type: " . ucfirst($advertisement["type"]) . "\n";
        $content .= ucfirst($ads_type). ": " . ucfirst($advertisement["crypto_currency"]) . "\n";
        if($ads_type == "sell"){
            $content .= "Accept with: " . ucfirst($currency) . "\n";
        }else if ($ads_type == "buy"){
            $content .= "Pay with: " . ucfirst($currency) . "\n";
        }
        $content .= "Price setting: " . ucfirst($price_type) . "\n";
        if ($price_type == "fix"){
            $content .= "Price: " . $advertisement["price"] . " " . ucfirst($advertisement["price_unit"]) . "\n";
        }
        else if($price_type == "floating"){
            $content .= "Floating ratio: " . $advertisement["floating_ratio"] . "\n";
            if ($ads_type == "sell"){
                $content .= "Min Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
            }else if ($ads_type == "buy"){
                $content .= "Max Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
            }
        }
        $content .= "Transaction limit: " . $min_limit . " - " . $max_limit . "\n";

        $content .= "\nPlace Order By \n";
        $content .= "Username: " . $order_nickname . "\n";
        $content .= "Phone Number: " . $order_username . "\n";
        $content .= "IP: " . $order_ip . "\n";
        $content .= "Country: " . ucfirst($order_country) . "\n";
        $content .= "Device: " . $order_device_os . "\n";
        $content .= "Order Price: " . $order_price . "\n";
        $content .= "Order Volume: " . $advertisement_order["quantity"] . "\n";
        $content .= "Order ID: " . $order_id . "\n";

        //$content .= "Price setting: " . $price_setting . "\n";
        $content .= "\nTime: " . date("Y-m-d H:i:s");

        $tag = "Placed Order (Extend Time)";
        $erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");
        //$erlang_params["mobile_list"] = array();
        //$xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Order time extended.");
    }

    public function release_coin_advertisement_order($params)
    {
        global $setting, $xunXmpp, $xunCurrency, $xunUser, $xun_numbers;
        $db = $this->db;
        $general = $this->general;

        $advertisement_id = trim($params["advertisement_id"]);
        $order_id = trim($params["advertisement_order_id"]);
        $username = trim($params["username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty");
        }

        if ($order_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Order ID cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $date = date("Y-m-d H:i:s");

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");
        $owner_user_id = $advertisement["user_id"];

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $advertisement_date = $advertisement["created_at"];
        $advertisement_type = $advertisement["type"];

        $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        $db->where("advertisement_id", $advertisement_id);
        $db->where("order_id", $order_id);
        $db->where("disabled", 0);

        $advertisement_order = $db->getOne($advertisement_order_table);

        if (!$advertisement_order) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $db->where("order_id", $order_id);
        // $db->where("order_type", "place_order");
        $db->orderBy("created_at", "asc");
        $order_placement = $db->getOne($advertisement_order_table);

        if ($advertisement["type"] == "buy") {
            $buyer_user_id = $advertisement["user_id"];
            $seller_user_id = $order_placement["user_id"];
        } else {
            $buyer_user_id = $order_placement["user_id"];
            $seller_user_id = $advertisement["user_id"];
        }

        if ($user_id != $seller_user_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This action is not allowed for this advertisement order.", "errorCode" => -101);
        }

        $advertisement_order_status = $advertisement_order["status"];

        if ($advertisement_order_status != "paid") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid action", "errorCode" => -103, "status" => $advertisement_order_status);
        }

        $update_data = [];
        // $update_data["updated_at"] = $date;
        $update_data["disabled"] = 1;

        $db->where("id", $advertisement_order["id"]);
        $db->update($advertisement_order_table, $update_data);

        // TODO: this->order_decimal_places

        $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($advertisement_order["currency"], true);
        $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($advertisement["crypto_currency"], true);

        $currency_decimal_places = $currency_decimal_place_setting["decimal_places"];
        $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];

        $crypto_decimal_places = $crypto_decimal_place_setting["decimal_places"];
        $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

        $this->order_decimal_place_setting = $crypto_decimal_place_setting;

        $order_quantity_currency = $advertisement_order["quantity"];
        $order_quantity = bcdiv((string)$order_quantity_currency, (string)$advertisement_order["price"], $crypto_decimal_places);

        $fund_out_result = $this->get_order_fund_out_amount($advertisement, $advertisement_order["quantity"]);

        $fund_out_quantity = $fund_out_result["fund_out_quantity"];
        $fund_out_quantity = $setting->setDecimal($fund_out_quantity);

        // insert data
        $final_order_status = "coin_released";
        $insert_data = $advertisement_order;
        unset($insert_data["id"]);
        $insert_data["user_id"] = $user_id;
        $insert_data["status"] = $final_order_status;
        $insert_data["quantity"] = $fund_out_quantity;
        $insert_data["disabled"] = 0;
        $insert_data["created_at"] = $date;
        $insert_data["updated_at"] = $date;

        $db->insert($advertisement_order_table, $insert_data);

        $this->update_advertisement_order_cache_data($insert_data);

        $db->where("id", $buyer_user_id);
        $buyer_user = $db->getOne("xun_user");
        $buyer_username = $buyer_user["username"];
        $buyer_nickname = $buyer_user["nickname"];

        $user_nickname = $xun_user["nickname"];

        // call escrow

        /**
         *   for fiat ads only
         *      # sell ad
         *      -   ad owner releases coin -> trading fee already paid
         *      -   ## fund out full amount
         *
         *      # buy ad
         *      -   seller (order placer) releases coin -> trading fee not paid
         *      -   deduct fee from seller's coin if pay buy coin
         *      -   fund out full amount if pay buy thenuxcoin
         *
         *  */

        $this->escrow_fund_out($advertisement_id, $order_id, $buyer_username, $fund_out_quantity, $advertisement["crypto_currency"]);

        // send xmpp message

        $owner_username = $advertisement["type"] == "buy" ? $buyer_username : $username;
        $owner_nickname = $advertisement["type"] == "buy" ? $buyer_nickname : $user_nickname;
        $xmpp_recipients = [$buyer_username, $username];
        $marketplace_chat_room_host = $xunXmpp->get_marketplace_host();
        $erlang_params = array(
            "chatroom_id" => (string) $order_id,
            "chatroom_host" => $marketplace_chat_room_host,
            "recipients" => $xmpp_recipients,
            "type" => "coin_released",
            "data" => array(
                "advertisement_id" => (string) $advertisement_id,
                "order_id" => (string) $order_id,
                "username" => $username,
                "nickname" => $user_nickname,
                "owner_username" => $owner_username,
                "owner_nickname" => $owner_nickname,
                "seller_username" => $username,
                "seller_nickname" => $user_nickname,
                "buyer_username" => $buyer_username,
                "buyer_nickname" => $buyer_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "created_at" => $general->formatDateTimeToIsoFormat($date),
            ),
        );
        $xunXmpp->send_xmpp_marketplace_event($erlang_params);

        if ($fund_out_result["fund_out_trading_fee"]) {
            $trading_fee_params = array(
                "advertisement_id" => $advertisement_id,
                "order_id" => $order_id,
                "user_id" => $advertisement["user_id"],
                "type" => $advertisement["type"],
            );

            $this->process_trading_fee_order($advertisement_order_table, $trading_fee_params, $fund_out_result);
        }
        $order_user_id = $advertisement_order["user_id"];
        $order_user = $db->where("id", $order_user_id)->getOne("xun_user");
        $order_username = $order_user["username"];
        $order_nickname = $order_user["nickname"];

        $user_country_info_arr = $xunUser->get_user_country_info([$owner_username, $order_username]);
        $owner_country_info = $user_country_info_arr[$owner_username];
        $owner_country = $owner_country_info["name"];
        
        $order_country_info = $user_country_info_arr[$order_username];
        $order_country = $order_country_info["name"];

        $owner_device_ip_arr = $xunUser->get_device_os_ip($owner_user_id, $owner_username);
        $owner_ip = $owner_device_ip_arr["ip"];
        $owner_device_os = $owner_device_ip_arr["device_os"];

        $order_device_ip_arr = $xunUser->get_device_os_ip($order_user_id, $order_username);
        $order_ip = $order_device_ip_arr["ip"];
        $order_device_os = $order_device_ip_arr["device_os"];

        $ads_min_max = $this->get_ads_order_min_max_cryptocurrency($advertisement, $advertisement_order);
        $min_limit = $ads_min_max["min"];
        $max_limit = $ads_min_max["max"];

        $currency = $advertisement["currency"];
        $price_type = $advertisement["price_type"];
        $ads_type = $advertisement["type"];
        $order_price = $advertisement_order["price"];
        $order_price = $this->order_price_convert($advertisement, $advertisement_order, $order_price);

        $content = "Ads Owner\n";
        $content .= "Username: " . $owner_nickname . "\n";
        $content .= "Phone Number: " . $owner_username . "\n";
        $content .= "IP: " . $owner_ip . "\n";
        $content .= "Country: " . $owner_country . "\n";
        $content .= "Device: " . $owner_device_os . "\n";
        $content .= "Advertisement ID: " . $advertisement_id . "\n";
        $content .= "Advertisement Type: " . ucfirst($advertisement["type"]) . "\n";
        $content .= ucfirst($ads_type). ": " . ucfirst($advertisement["crypto_currency"]) . "\n";
        if($ads_type == "sell"){
            $content .= "Accept with: " . ucfirst($currency) . "\n";
        }else if ($ads_type == "buy"){
            $content .= "Pay with: " . ucfirst($currency) . "\n";
        }
        $content .= "Price setting: " . ucfirst($price_type) . "\n";
        if ($price_type == "fix"){
            $content .= "Price: " . $advertisement["price"] . " " . ucfirst($advertisement["price_unit"]) . "\n";
        }
        else if($price_type == "floating"){
            $content .= "Floating ratio: " . $advertisement["floating_ratio"] . "\n";
            if ($ads_type == "sell"){
                $content .= "Min Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
            }else if ($ads_type == "buy"){
                $content .= "Max Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
            }
        }
        $content .= "Transaction limit: " . $min_limit . " - " . $max_limit . "\n";

        $content .= "\nPlace Order By \n";
        $content .= "Username: " . $order_nickname . "\n";
        $content .= "Phone Number: " . $order_username . "\n";
        $content .= "IP: " . $order_ip . "\n";
        $content .= "Country: " . ucfirst($order_country) . "\n";
        $content .= "Device: " . $order_device_os . "\n";
        $content .= "Order Price: " . $order_price . "\n";
        $content .= "Order Volume: " . $advertisement_order["quantity"] . "\n";
        $content .= "Order ID: " . $order_id . "\n";

        //$content .= "Price setting: " . $price_setting . "\n";
        $content .= "\nTime: " . date("Y-m-d H:i:s");

        $tag = "Placed Order (Released)";
        $erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");
        //$erlang_params["mobile_list"] = array();
        //$xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);
        
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Coins is released. This order is completed.");
    }

    public function remind_seller_advertisement_order($params)
    {
        global $xunXmpp;
        $db = $this->db;
        $general = $this->general;

        $advertisement_id = trim($params["advertisement_id"]);
        $order_id = trim($params["advertisement_order_id"]);
        $username = trim($params["username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty");
        }

        if ($order_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Order ID cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $date = date("Y-m-d H:i:s");

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $advertisement_date = $advertisement["created_at"];
        $advertisement_type = $advertisement["type"];

        $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        $db->where("order_id", $order_id);
        $db->where("advertisement_id", $advertisement_id);
        $db->where("disabled", 0);

        $advertisement_order = $db->getOne($advertisement_order_table);

        if (!$advertisement_order) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        if ($advertisement_order["user_id"] != $user_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This action is not allowed for this advertisement order.", "errorCode" => -101);
        }

        $advertisement_order_status = $advertisement_order["status"];

        if ($advertisement_order_status != "paid") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid action", "errorCode" => -103, "status" => $advertisement_order_status);
        }

        // send xmpp message

        if ($advertisement["type"] == "sell") {
            $seller_user_id = $advertisement["user_id"];
        } else {
            $db->where("order_id", $order_id);
            // $db->where("order_type", "place_order");
            $db->orderBy("created_at", "asc");
            $order_placement = $db->getOne($advertisement_order_table);
            $seller_user_id = $order_placement["user_id"];
        }

        $db->where("id", $seller_user_id);
        $seller_user = $db->getOne("xun_user");
        $seller_username = $seller_user["username"];
        $seller_nickname = $seller_user["nickname"];

        $user_nickname = $xun_user["nickname"];

        $owner_username = $advertisement["type"] == "sell" ? $seller_username : $username;
        $owner_nickname = $advertisement["type"] == "sell" ? $seller_nickname : $user_nickname;

        $xmpp_recipients = [$seller_username, $username];
        $marketplace_chat_room_host = $xunXmpp->get_marketplace_host();
        $erlang_params = array(
            "chatroom_id" => (string) $order_id,
            "chatroom_host" => $marketplace_chat_room_host,
            "recipients" => $xmpp_recipients,
            "type" => "remind_seller",
            "data" => array(
                "advertisement_id" => (string) $advertisement_id,
                "order_id" => (string) $order_id,
                "username" => $username,
                "nickname" => $user_nickname,
                "owner_username" => $owner_username,
                "owner_nickname" => $owner_nickname,
                "seller_username" => $seller_username,
                "seller_nickname" => $seller_nickname,
                "buyer_username" => $username,
                "buyer_nickname" => $user_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "created_at" => $general->formatDateTimeToIsoFormat($date),
            ),
        );
        $xunXmpp->send_xmpp_marketplace_event($erlang_params);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Seller notified.");
    }

    public function report_user_advertisement_order($params)
    {
        global $xunXmpp, $xunUser;
        $db = $this->db;
        $general = $this->general;

        $advertisement_id = trim($params["advertisement_id"]);
        $order_id = trim($params["advertisement_order_id"]);
        $username = trim($params["username"]);
        $report_type = trim($params["type"]);
        $report_description = trim($params["description"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty");
        }

        if ($order_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Order ID cannot be empty");
        }

        if ($report_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Type cannot be empty");
        }

        if ($report_description == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Description cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $date = date("Y-m-d H:i:s");

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");
        $owner_user_id = $advertisement["user_id"];

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $advertisement_date = $advertisement["created_at"];
        $advertisement_type = $advertisement["type"];

        $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        $db->where("order_id", $order_id);
        $db->where("advertisement_id", $advertisement_id);
        $db->where("disabled", 0);

        $advertisement_order = $db->getOne($advertisement_order_table);

        if (!$advertisement_order) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $db->where("order_id", $order_id);
        $db->orderBy("created_at", "asc");
        $order_user_id = $db->getValue($advertisement_order_table, "user_id");

        if ($advertisement["type"] == "sell") {
            $seller_user_id = $advertisement["user_id"];
            $buyer_user_id = $order_user_id;
        } else {
            $seller_user_id = $order_user_id;
            $buyer_user_id = $advertisement["user_id"];
        }

        if ($buyer_user_id == $user_id) {
            $db->where("id", $seller_user_id);
            $seller_user = $db->getOne("xun_user");
            $seller_username = $seller_user["username"];
            $seller_nickname = $seller_user["nickname"];
            $buyer_username = $username;
            $buyer_nickname = $xun_user["nickname"];
            $reported_username = $seller_username;
            $reported_nickname = $seller_nickname;
        } else if ($seller_user_id == $user_id) {
            $db->where("id", $buyer_user_id);
            $buyer_user = $db->getOne("xun_user");
            $buyer_username = $buyer_user["username"];
            $buyer_nickname = $buyer_user["nickname"];
            $seller_username = $username;
            $seller_nickname = $xun_user["nickname"];
            $reported_username = $buyer_username;
            $reported_nickname = $buyer_nickname;
        } else {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This action is not allowed for this advertisement order.", "errorCode" => -101);
        }

        $advertisement_order_status = $advertisement_order["status"];

        // allow when completed, cancelled
        if (!in_array($advertisement_order_status, array("cancelled", "completed", "paid"))) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid action", "errorCode" => -103, "status" => $advertisement_order_status);
        }

        // TODO:
        // store to report table
        $db->where("advertisement_order_id", $order_id);
        $db->where("user_id", $user_id);
        $user_report = $db->getOne("xun_marketplace_user_report");

        if ($user_report) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You can only report once per advertisement order.", "errorCode" => -104);
        }
        $insert_data = array(
            "advertisement_order_id" => $order_id,
            "user_id" => $user_id,
            "type" => $report_type,
            "description" => $report_description,
            "created_at" => $date,
        );

        $db->insert("xun_marketplace_user_report", $insert_data);
        // send xmpp message

        $user_nickname = $xun_user["nickname"];
        $ticket_params = array(
            "username" => $username,
            "nickname" => $user_nickname,
            "advertisement_order_id" => $order_id,
            "advertisement_id" => $advertisement_id,
            "reported_username" => $reported_username,
            "reported_nickname" => $reported_nickname,
            "type" => $report_type,
            "description" => $report_description,
        );

        $this->send_report_ticket($ticket_params);
        $owner_username = $advertisement["type"] == "sell" ? $seller_username : $buyer_username;
        $owner_nickname = $advertisement["type"] == "sell" ? $seller_nickname : $buyer_nickname;
        $xmpp_recipients = [$seller_username, $buyer_username];
        $marketplace_chat_room_host = $xunXmpp->get_marketplace_host();
        $erlang_params = array(
            "chatroom_id" => (string) $order_id,
            "chatroom_host" => $marketplace_chat_room_host,
            "recipients" => $xmpp_recipients,
            "type" => "report",
            "data" => array(
                "advertisement_id" => (string) $advertisement_id,
                "order_id" => (string) $order_id,
                "username" => $username,
                "nickname" => $user_nickname,
                "owner_username" => $owner_username,
                "owner_nickname" => $owner_nickname,
                "seller_username" => $seller_username,
                "seller_nickname" => $seller_nickname,
                "buyer_username" => $buyer_username,
                "buyer_nickname" => $buyer_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "created_at" => $general->formatDateTimeToIsoFormat($date),
            ),
        );
        $xunXmpp->send_xmpp_marketplace_event($erlang_params);

        //  send notification to admin
        if ($advertisement_type == "buy") {
            $order_username = $seller_username;
            $order_nickname = $seller_nickname;
        } else {
            $order_username = $buyer_username;
            $order_nickname = $buyer_nickname;
        }

        $user_country_info_arr = $xunUser->get_user_country_info([$owner_username, $order_username]);
        $owner_country_info = $user_country_info_arr[$owner_username];
        $owner_country = $owner_country_info["name"];

        $order_country_info = $user_country_info_arr[$order_username];
        $order_country = $order_country_info["name"];

        $owner_device_ip_arr = $xunUser->get_device_os_ip($owner_user_id, $owner_username);
        $owner_ip = $owner_device_ip_arr["ip"];
        $owner_device_os = $owner_device_ip_arr["device_os"];

        $order_device_ip_arr = $xunUser->get_device_os_ip($order_user_id, $order_username);
        $order_ip = $order_device_ip_arr["ip"];
        $order_device_os = $order_device_ip_arr["device_os"];

        $ads_min_max = $this->get_ads_order_min_max_cryptocurrency($advertisement, $advertisement_order);
        $min_limit = $ads_min_max["min"];
        $max_limit = $ads_min_max["max"];

        $currency = $advertisement["currency"];
        $price_type = $advertisement["price_type"];
        $ads_type = $advertisement["type"];
        $order_price = $advertisement_order["price"];
        $order_price = $this->order_price_convert($advertisement, $advertisement_order, $order_price);

        $content = "Ads Owner\n";
        $content .= "Username: " . $owner_nickname . "\n";
        $content .= "Phone Number: " . $owner_username . "\n";
        $content .= "IP: " . $owner_ip . "\n";
        $content .= "Country: " . $owner_country . "\n";
        $content .= "Device: " . $owner_device_os . "\n";
        $content .= "Advertisement ID: " . $advertisement_id . "\n";
        $content .= "Advertisement Type: " . ucfirst($advertisement["type"]) . "\n";
        $content .= ucfirst($ads_type). ": " . ucfirst($advertisement["crypto_currency"]) . "\n";
        if($ads_type == "sell"){
            $content .= "Accept with: " . ucfirst($currency) . "\n";
        }else if ($ads_type == "buy"){
            $content .= "Pay with: " . ucfirst($currency) . "\n";
        }
        $content .= "Price setting: " . ucfirst($price_type) . "\n";
        if ($price_type == "fix"){
            $content .= "Price: " . $advertisement["price"] . " " . ucfirst($advertisement["price_unit"]) . "\n";
        }
        else if($price_type == "floating"){
            $content .= "Floating ratio: " . $advertisement["floating_ratio"] . "\n";
            if ($ads_type == "sell"){
                $content .= "Min Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
            }else if ($ads_type == "buy"){
                $content .= "Max Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
            }
        }
        $content .= "Transaction limit: " . $min_limit . " - " . $max_limit . "\n";

        $content .= "\nPlace Order By \n";
        $content .= "Username: " . $order_nickname . "\n";
        $content .= "Phone Number: " . $order_username . "\n";
        $content .= "IP: " . $order_ip . "\n";
        $content .= "Country: " . ucfirst($order_country) . "\n";
        $content .= "Device: " . $order_device_os . "\n";
        $content .= "Order Price: " . $order_price . "\n";
        $content .= "Order ID: " . $order_id . "\n";
        $content .= "Oder Timestamp: " . $advertisement_order["created_at"] . "\n";
        //$content .= "Price setting: " . $price_setting . "\n";
        $content .= "\nReport By: " . $xun_user["username"] . " " . $xun_user["nickname"];

        $tag = "Dispute/Report (xChange)";
        $erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");
        /*$erlang_params["mobile_list"] = array();
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);*/

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "User has been reported.");
    }

    public function rate_user($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $advertisement_id = trim($params["advertisement_id"]);
        $order_id = trim($params["advertisement_order_id"]);
        $ratee_username = trim($params["ratee_username"]);
        $rating = trim($params["rating"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty");
        }

        if ($order_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Order ID cannot be empty");
        }

        if ($ratee_username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Ratee username cannot be empty");
        }

        if ($rating == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Rating cannot be empty");
        }

        if (!(is_numeric($rating) && floor((int) $rating) == $rating)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Rating must be an integer.");
        }

        if ($rating < 1 || $rating > 5) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Rating must be between 1 and 5");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $date = date("Y-m-d H:i:s");

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }

        $advertisement_date = $advertisement["created_at"];
        $advertisement_type = $advertisement["type"];

        $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        if (!$db->tableExists($advertisement_order_table)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $db->where("order_id", $order_id);
        $db->where("advertisement_id", $advertisement_id);
        $db->where("disabled", 0);

        $advertisement_order = $db->getOne($advertisement_order_table);

        if (!$advertisement_order) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $db->where("order_id", $order_id);
        // $db->where("order_type", "place_order");
        $db->orderBy("created_at", "asc");
        $order_placement = $db->getOne($advertisement_order_table);

        $owner_user_id = $advertisement["user_id"];
        $order_user_id = $order_placement["user_id"];

        $db->where("username", $ratee_username);
        // $db->where("disabled", 0);

        $xun_user_ratee = $db->getOne("xun_user");

        if (!$xun_user_ratee) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $ratee_user_id = $xun_user_ratee["id"];

        if (!($owner_user_id == $user_id || $order_user_id == $user_id)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You're not allowed to rate in this advertisement order.", "errorCode" => -101);
        }

        if (!($owner_user_id == $ratee_user_id || $order_user_id == $ratee_user_id)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You're not allowed to rate this user in this advertisement order.", "errorCode" => -102);
        }

        if ($advertisement_order["status"] != "completed") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "This order has not complete.", "errorCode" => -104);
        }
        $db->where("advertisement_order_id", $order_id);
        $db->where("user_id", $ratee_user_id);
        $db->where("rater_user_id", $user_id);
        $user_rating = $db->getOne("xun_marketplace_user_rating");

        if ($user_rating) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You can only rate once per advertisement order.", "errorCode" => -103);
        }

        $insert_data = array(
            "advertisement_order_id" => $order_id,
            "user_id" => $ratee_user_id,
            "rater_user_id" => $user_id,
            "rating" => $rating,
            "created_at" => $date,
        );

        $db->insert("xun_marketplace_user_rating", $insert_data);

        $avg_user_rating = $this->get_user_rating($ratee_user_id);

        $db->where("user_id", $ratee_user_id);
        $marketplace_user = $db->getOne("xun_marketplace_user");

        if ($marketplace_user) {
            $update_user_data = [];
            $update_user_data["avg_rating"] = $avg_user_rating;
            $update_user_data["updated_at"] = $date;

            $db->where("id", $marketplace_user["id"]);
            $db->update("xun_marketplace_user", $update_user_data);
        } else {
            $insert_user_data = array(
                "user_id" => $ratee_user_id,
                "avg_rating" => $avg_user_rating,
                "created_at" => $date,
                "updated_at" => $date,
            );

            $db->insert("xun_marketplace_user", $insert_user_data);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
    }

    public function save_user_transaction_hash($params)
    {
        /**
         * data in:
         * -    username
         * -    transaction_hash
         * -    order_id
         *
         * data_out:
         * -
         */
        global $xunXmpp, $general, $xunCurrency, $setting;
        $db = $this->db;

        $username = trim($params["username"]);
        $transaction_hash = trim($params["transaction_hash"]);
        $order_id = trim($params["advertisement_order_id"]);
        $advertisement_id = trim($params["advertisement_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty");
        }

        if ($transaction_hash == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction hash cannot be empty");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty");
        }

        if ($order_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Order ID cannot be empty");
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];
        $date = date("Y-m-d H:i:s");

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }

        $advertisement_date = $advertisement["created_at"];

        $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement_date);
        if (!$db->tableExists($advertisement_order_table)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $db->where("order_id", $order_id);
        $db->where("advertisement_id", $advertisement_id);
        $db->where("disabled", 0);

        $advertisement_order = $db->getOne($advertisement_order_table);
        if (!$advertisement_order) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $db->where("transaction_hash", $transaction_hash);
        $transaction_rec = $db->getOne("xun_marketplace_escrow_transaction");

        $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency, true);
        $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];

        $ads_type = $advertisement["type"];
        $ads_params["username"] = $username;
        $ads_params["id"] = $advertisement_id;
        $advertisement_info = $this->get_advertisement_details($ads_params);
        $max_limit = $advertisement["max"];

        if ($ads_type == "sell"){

            $advertisement["min"] = $advertisement_info["data"]["min_cryptocurrency"];

        }else if ($ads_type == "buy" && $advertisement["is_cryptocurrency"] == 1){

            $can_place_order_return = $this->can_place_order($advertisement, $advertisement_order["quantity"]);
            
            if ($can_place_order_return["remaining_quantity"]<$max){
                $max_limit = $can_place_order_return["remaining_quantity"];
            }

            $max_limit = $max_limit / $advertisement_order["price"];
            $max_limit = $setting->setDecimal($max_limit, $currency_dp_credit_type);
            $advertisement["max"] = $max_limit;
        }
        
        $transaction_type = "receive";
        if ($transaction_rec) {
            if ($transaction_rec["status"] == "pending") {
                $error_message = "Transaction hash has already been updated.";
                $xmpp_result = $this->send_failed_place_order($newParams, $advertisement, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "errorCode" => -104);
            }

            $update_data = [];
            $update_data["advertisement_id"] = $advertisement_id;
            $update_data["advertisement_order_id"] = $order_id;
            $update_data["user_id"] = $user_id;
            $update_data["updated_at"] = $date;

            $db->where("id", $transaction_rec["id"]);
            $db->update("xun_marketplace_escrow_transaction", $update_data);

            if ($advertisement_order["order_type"] == "place_order") {
                // send new order xmpp event
                $res = $this->send_new_order_xmpp_event($advertisement, $advertisement_order, $xun_user);
            }

            $this->update_advertisement_order_transaction($advertisement_id, $order_id, $transaction_type, $advertisement, $advertisement_order, $advertisement_order_table);

        } else {
            $insert_data = array(
                "transaction_hash" => $transaction_hash,
                "advertisement_id" => $advertisement_id,
                "advertisement_order_id" => $order_id,
                "user_id" => $user_id,
                "type" => $transaction_type,
                "status" => "pending",
                "created_at" => $date,
                "updated_at" => $date,
            );

            $db->insert("xun_marketplace_escrow_transaction", $insert_data);

            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = "pending_escrow";

            $db->where("id", $advertisement_order["id"]);
            $db->update($advertisement_order_table, $update_data);

            // only update for new advertisement related escrow
            if (in_array($advertisement_order["order_type"], ["new_advertisement", "advertisement_trading_fee"])) {
                $db->where("id", $advertisement["id"]);
                $db->update("xun_marketplace_advertisement", $update_data);
            }

            if ($advertisement_order["order_type"] == "place_order") {
                // send new order xmpp event
                $res = $this->send_new_order_xmpp_event($advertisement, $advertisement_order, $xun_user);
            }
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
    }

    private function send_new_order_xmpp_event($advertisement, $advertisement_order, $order_user)
    {
        global $xunXmpp, $general, $xunUser, $xun_numbers;
        $db = $this->db;

        $username = $order_user["username"];
        $buyer_seller_info = $this->get_buyer_seller_info($advertisement, $advertisement_order, null, $order_user);
        $owner_user = $buyer_seller_info["owner_user"];
        $buyer_user = $buyer_seller_info["buyer_user"];
        $seller_user = $buyer_seller_info["seller_user"];

        $owner_username = $owner_user["username"];
        $owner_nickname = $owner_user["nickname"];
        $owner_id = $owner_user["id"];
        $user_nickname = $order_user["nickname"];

        $buyer_username = $buyer_user["username"];
        $buyer_nickname = $buyer_user["nickname"];

        $seller_username = $seller_user["username"];
        $seller_nickname = $seller_user["nickname"];

        $xmpp_recipients = [$owner_username, $username];

        $order_id = $advertisement_order["order_id"];
        $advertisement_id = $advertisement["id"];
        $currency = $advertisement["currency"];
        $marketplace_chat_room_host = $xunXmpp->get_marketplace_host();

        $erlang_params = array(
            "chatroom_id" => (string) $order_id,
            "chatroom_host" => $marketplace_chat_room_host,
            "recipients" => $xmpp_recipients,
            "type" => "new_order",
            "data" => array(
                "advertisement_id" => (string) $advertisement_id,
                "order_id" => (string) $order_id,
                "username" => $username,
                "nickname" => $user_nickname,
                "owner_username" => $owner_username,
                "owner_nickname" => $owner_nickname,
                "seller_username" => $seller_username,
                "seller_nickname" => $seller_nickname,
                "buyer_username" => $buyer_username,
                "buyer_nickname" => $buyer_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "created_at" => $general->formatDateTimeToIsoFormat($advertisement_order["created_at"]),
            ),
        );

        $res = $xunXmpp->send_xmpp_marketplace_event($erlang_params);

        $user_country_info_arr = $xunUser->get_user_country_info([$owner_username, $username]);
        $owner_country_info = $user_country_info_arr[$owner_username];
        $owner_country = $owner_country_info["name"];

        $db->where("user_id", $owner_id);
        $db->where("name", "lastLoginIP");
        $owner_ip = $db->getValue("xun_user_setting", "value");

        $owner_device_os = $db->where($owner_username)->getValue("xun_user_device", "os");
        $owner_device_os = $owner_device_os == 1 ? $owner_device_os = "Android" : $owner_device_os = "iOS";

        $order_country_info = $user_country_info_arr[$username];
        $order_country = $order_country_info["name"];

        $user_id = $order_user["id"];
        $db->where("user_id", $user_id);
        $db->where("name", "lastLoginIP");
        $user_ip = $db->getValue("xun_user_setting", "value");

        $user_device_os = $db->where($username)->getValue("xun_user_device", "os");
        $user_device_os = $user_device_os == 1 ? $user_device_os = "Android" : $user_device_os = "iOS";

        $price_setting = $advertisement["price_type"] == "fix" ? "Fix: " . $advertisement["price"] : "Floating: " . $advertisement["floating_ratio"];
        $price_type = $advertisement["price_type"];
        $ads_type = $advertisement["type"];

        $min_limit = $advertisement["min"];
        $max_limit = $advertisement["max"];

        $tag = "Placed Order Successful";
        $content = "Ads Owner\n";
        $content .= "Username: " . $owner_nickname . "\n";
        $content .= "Phone Number: " . $owner_username . "\n";
        $content .= "IP: " . $owner_ip . "\n";
        $content .= "Country: " . $owner_country . "\n";
        $content .= "Device: " . $owner_device_os . "\n";
        $content .= "Advertisement ID: " . $advertisement_id . "\n";
        $content .= "Advertisement Type: " . ucfirst($ads_type) . "\n";
        $content .= ucfirst($ads_type). ": " . ucfirst($advertisement["crypto_currency"]) . "\n";
        if($ads_type == "sell"){
            $content .= "Accept with: " . ucfirst($currency) . "\n";
        }else if ($ads_type == "buy"){
            $content .= "Pay with: " . ucfirst($currency) . "\n";
        }
        $content .= "Price setting: " . ucfirst($price_type) . "\n";
        if ($price_type == "fix"){
            $content .= "Price: " . $advertisement["price"] . "\n";
        }
        else if($price_type == "floating"){
            $content .= "Floating ratio: " . $advertisement["floating_ratio"] . "\n";
            if ($ads_type == "sell"){
                $content .= "Min Price: " . $advertisement["price_limit"] . "\n";
            }else if ($ads_type == "buy"){
                $content .= "Max Price: " . $advertisement["price_limit"] . "\n";
            }
        }
        $content .= "Transaction limit: " . $min_limit . " - " . $max_limit . "\n";

        $content .= "\nPlace Order By \n";
        $content .= "Username: " . $user_nickname . "\n";
        $content .= "Phone Number: " . $username . "\n";
        $content .= "IP: " . $user_ip . "\n";
        $content .= "Country: " . ucfirst($order_country) . "\n";
        $content .= "Device: " . $user_device_os . "\n";
        $content .= "Order Price: " . $advertisement_order["price"] . "\n";
        $content .= "Order Volume: " . $advertisement_order["quantity"] . "\n";
        $content .= "Order ID: " . $order_id . "\n";
        //$content .= "Price setting: " . $price_setting . "\n";
        $content .= "\nTime: " . date("Y-m-d H:i:s");

        //$erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");

        return $res;
    }

    public function save_escrow_transaction_hash($params)
    {
        /**
         * usage: escrow callback after performing fund out
         *
         * data in:
         * -    transaction_hash
         * -    order_id
         *
         * data_out:
         * -    success
         */
        $db = $this->db;

        $transaction_hash = trim($params["transaction_hash"]);
        $order_id = trim($params["advertisement_order_id"]);
        $advertisement_id = trim($params["advertisement_id"]);

        if ($transaction_hash == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction hash cannot be empty");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Advertisement ID cannot be empty");
        }

        if ($order_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Order ID cannot be empty");
        }

        $date = date("Y-m-d H:i:s");

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }

        $advertisement_date = $advertisement["created_at"];

        $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        if (!$db->tableExists($advertisement_order_table)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $db->where("order_id", $order_id);
        $db->where("advertisement_id", $advertisement_id);
        $db->where("disabled", 0);

        $advertisement_order = $db->getOne($advertisement_order_table);

        if (!$advertisement_order) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        $db->where("transaction_hash", $transaction_hash);
        $transaction_rec = $db->getOne("xun_marketplace_escrow_transaction");

        // if sell user is order user, buy is ad onwer
        if ($advertisement["type"] == "sell" && $advertisement["is_cryptocurrency"] && $advertisement_order["status"] == "paid") {
            $user_id = $advertisement["user_id"];
        }
        if ($advertisement["type"] == "buy" && $advertisement["is_cryptocurrency"] && in_array($advertisement_order["status"], ["pending_escrow", "pre_escrow"])) {
            $db->where("order_id", $order_id);
            $db->where("advertisement_id", $advertisement_id);
            $order_placement = $db->getOne($advertisement_order_table);

            $user_id = $order_placement["user_id"];
        }
        if ($advertisement["type"] == "sell") {
            $db->where("order_id", $order_id);
            $db->where("advertisement_id", $advertisement_id);
            $db->orderBy("created_at", "asc");
            $order_placement = $db->getOne($advertisement_order_table);

            $user_id = $order_placement["user_id"];
        } else {
            $user_id = $advertisement["user_id"];
        }

        $transaction_type = "send";
        if ($transaction_rec) {
            if ($transaction_rec["status"] == "pending") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction hash has already been updated.", "errorCode" => -104);
            }

            $update_data = [];
            $update_data["advertisement_id"] = $advertisement_id;
            $update_data["advertisement_order_id"] = $order_id;
            $update_data["user_id"] = $user_id;
            $update_data["updated_at"] = $date;

            $db->where("id", $transaction_rec["id"]);
            $db->update("xun_marketplace_escrow_transaction", $update_data);

            $this->update_advertisement_order_transaction($advertisement_id, $order_id, $transaction_type, $advertisement, $advertisement_order, $advertisement_order_table);
        } else {
            $insert_data = array(
                "transaction_hash" => $transaction_hash,
                "advertisement_id" => $advertisement_id,
                "advertisement_order_id" => $order_id,
                "user_id" => $user_id,
                "type" => $transaction_type,
                "status" => "pending",
                "created_at" => $date,
                "updated_at" => $date,
            );

            $row_id = $db->insert("xun_marketplace_escrow_transaction", $insert_data);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
    }

    public function escrow_notification($params)
    {
        global $general;

        $content = trim($params["content"]);
        $tag = trim($params["tag"]);

        if ($content == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Content cannot be empty");
        }

        if ($tag == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Tag cannot be empty");
        }

        $thenux_params["tag"] = $tag;
        $thenux_params["message"] = $content;
        $thenux_params["mobile_list"] = array();
        //$thenux_result = $general->send_thenux_notification($thenux_params);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "erlangReturn" => $thenux_result);

    }

    public function escrow_validation($params)
    {
        $db = $this->db;

        $receiverAddress = trim($params["receiverAddress"]);
        $advertisement_id = trim($params["advertisement_id"]);
        $order_id = trim($params["advertisement_order_id"]);
        $amount = trim($params["amount"]);
        $walletType = trim($params["walletType"]);

        if ($receiverAddress == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Receiver address cannot be empty");
        }

        if ($advertisement_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "advertisement_id cannot be empty");
        }

        if ($order_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "order_id cannot be empty");
        }

        if ($amount == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "amount cannot be empty");
        }

        if ($walletType == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "walletType cannot be empty");
        }

        $db->where("id", $advertisement_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }

        $advertisement_date = $advertisement["created_at"];

        $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement_date);

        if (!$db->tableExists($advertisement_order_table)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -101);
        }

        $db->where("order_id", $order_id);
        $db->where("advertisement_id", $advertisement_id);
        $db->where("disabled", 0);

        $advertisement_order = $db->getOne($advertisement_order_table);

        if ($advertisement_order["quantity"] != $amount) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Amount does not match.", "errorCode" => -102);
        }

        if ($advertisement["crypto_currency"] != $walletType) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet type does not match.", "errorCode" => -103);
        }

        /**
         * coin released -> buyer
         *  -   sell ad -> orderer
         *  -   buy ad -> ad owner
         *
         * cancel order -> seller
         * -    buy ad -> orderer
         *
         * expired order -> seller
         * -    buy ad -> orderer
         *
         * refunded order -> seller
         * -    buy ad -> orderer
         *
         * cancel ad -> seller
         * -    sell ad -> owner
         *
         * expired ad -> seller
         * -    sell ad -> owner
         */
        // get destination user id
        $order_status = $advertisement_order["status"];
        if ($advertisement_order["order_type"] == "place_order") {
            if ($order_status == "coin_released") {
                if ($advertisement["type"] == "buy") {
                    $user_id = $advertisement["user_id"];
                } else {
                    $db->where("order_id", $order_id);
                    $user_id = $db->getValue("xun_marketplace_advertisement_order_cache", "user_id");
                }
            } else if (in_array($order_status, array("cancelled", "expired", "refunded"))) {
                $db->where("order_id", $order_id);
                $user_id = $db->getValue("xun_marketplace_advertisement_order_cache", "user_id");
            }
        } else {
            if ($order_status == "refund" && ($advertisement["status"] == "cancelled" || $advertisement["status"] == "expired")) {
                $user_id = $advertisement["user_id"];
            }
        }

        // $db->where("id", $user_id);
        // $username = $db->getValue("xun_user", "username");

        $db->where("user_id", $user_id);
        $db->where("active", 1);
        $db->where("address_type", "personal");
        $user_address = $db->getValue("xun_crypto_user_address", "address");

        if ($receiverAddress != $user_address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Receiver address does not match.", "errorCode" => -104);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
    }

    private function get_user_marketplace_data($user_id)
    {
        $db = $this->db;

        $db->where("user_id", $user_id);
        $user_marketplace_data = $db->getOne("xun_marketplace_user");

        if ($user_marketplace_data) {
            $total_trade = $user_marketplace_data["total_trade"];
            $avg_rating = $user_marketplace_data["avg_rating"];
        }

        $total_trade = $total_trade ? $total_trade : 0;
        $avg_rating = $avg_rating ? $avg_rating : 0;
        return array(
            "total_trade" => (string) $total_trade,
            "avg_rating" => (string) $avg_rating,
        );
    }

    public function get_user_rating($user_id)
    {
        $db = $this->db;
        $db->where("user_id", $user_id);
        $user_rating = $db->getValue("xun_marketplace_user_rating", "avg(rating)");

        $user_rating = $user_rating ? round($user_rating, 2) : 0;
        return (string) $user_rating;
    }

    public function get_user_trade_count($user_id)
    {
        $db = $this->db;

        $db->where("user_id", $user_id);
        $db->where("status", "completed");
        $order_count = $db->getValue("xun_marketplace_advertisement_order_cache", "count(id)");

        $db->where("user_id", $user_id);
        $user_advertisements = $db->get("xun_marketplace_advertisement");

        if (!$user_advertisements) {
            return (string) $order_count;
        }

        $advertisement_order_count = 0;
        foreach ($user_advertisements as $advertisement) {
            $table_name = $this->get_advertisement_order_transaction_table_name($advertisement["created_at"]);

            if ($db->tableExists($table_name)) {
                $db->where("advertisement_id", $advertisement["id"]);
                $db->where("order_type", "place_order");
                $db->where("disabled", 0);
                $db->where("status", "completed");
                $ad_order_count = $db->getValue($table_name, "count(DISTINCT(order_id))");

                $advertisement_order_count += $ad_order_count;
            }
        }

        $total_count = $order_count + $advertisement_order_count;
        return (string) $total_count;
    }

    public function update_advertisement_order_transaction($advertisement_id, $order_id, $transaction_type, $advertisement = null, $advertisement_order = null, $advertisement_order_table = null)
    {
        /**
         * usage:
         *      update order status to completed if total escrow transaction equals quantity
         * data in
         * -    advertisement_id
         * -    order_id
         * -    status = confirmed
         * -    amount
         */

        global $setting, $xunXmpp, $xunCurrency, $xun_numbers, $xunUser;
        $db = $this->db;
        $general = $this->general;

        if (is_null($advertisement)) {
            $db->where("id", $advertisement_id);
            $advertisement = $db->getOne("xun_marketplace_advertisement");
        }

        $date = date("Y-m-d H:i:s");
        $marketplace_chat_room_host = $xunXmpp->get_marketplace_host();

        if (!$advertisement) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement.", "errorCode" => -100);
        }

        if (!$advertisement_order_table) {
            $advertisement_date = $advertisement["created_at"];

            $advertisement_order_table = $this->get_advertisement_order_transaction_table_name($advertisement_date);
        }

        if (!$db->tableExists($advertisement_order_table)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid advertisement order.", "errorCode" => -100);
        }

        if (!$advertisement_order) {
            $db->where("order_id", $order_id);
            $db->where("advertisement_id", $advertisement_id);
            $db->where("disabled", 0);

            $advertisement_order = $db->getOne($advertisement_order_table);
        }

        $order_quantity = $advertisement_order["quantity"];

        // fund in to escrow
        /**
         * status: pre_escrow/pending_escrow
         * new_advertisement && sell = create ad
         * new_advertisement && buy = create ad
         * place_order && sell && is_cryptocurrency = pay with coin
         * place_order && buy == sell order
         *
         * trading_fee && sell - either ad cryptocurrency/tnc
         * trading_fee && buy - only tnc
         *
         * ##
         * new_advertisement && sell = create ad - total debit of advertisement == advertisement_order quantity && (total debit for fee == trading_fee quantity)
         *
         */

        $advertisement_type = $advertisement["type"];
        $advertisement_order_type = $advertisement_order["order_type"];
        $advertisement_order_status = $advertisement_order["status"];
        $advertisement_status = $advertisement["status"];

        if ($transaction_type == "receive" &&
            ($advertisement_order_status == "pending_escrow" || $advertisement_order_status == "pre_escrow")) {
            // check for expiry
            $db->where("advertisement_order_id", $order_id);
            $db->where("type", "receive");
            $db->where("status", "confirmed");
            $total_debit = $db->getValue("xun_marketplace_escrow_transaction", "sum(debit)");

            // update status to completed if total debit is sufficient
            // sell ad, update order, update advertisement
            // buy ad, update order, add new pending_payment
            if (($advertisement_order_type == "new_advertisement" || $advertisement_order_type == "advertisement_trading_fee") && ($total_debit >= $order_quantity)) {
                // on create advertisement for sell ads and c2c buy ads
                $update_data = [];
                $update_data["updated_at"] = $date;
                $update_data["status"] = "completed";

                $db->where("id", $advertisement_order["id"]);
                $db->update($advertisement_order_table, $update_data);

                // check if trading_fee and advertisement quantity is sufficient

                $advertisement_order_type_fee = $advertisement_order_type == "new_advertisement" ? "advertisement_trading_fee" : "new_advertisement";

                $db->where("advertisement_id", $advertisement_id);
                $db->where("order_type", $advertisement_order_type_fee);
                $advertisement_order_fee = $db->getOne($advertisement_order_table, "id, quantity, currency, status, disabled");

                /**
                 * buy ads: fiat currency &&  pay trading_fee by tnc, no "new_advertisement"
                 *        : c2c && pay trading fee with buy coin, no "advertisement_trading_fee"
                 */
                if (!in_array($advertisement_status, ["pre_escrow", "pending_escrow"])) {
                    // // refund
                    $owner_user_id = $advertisement_order["user_id"];
                    $db->where("id", $owner_user_id);
                    $owner_user = $db->getOne("xun_user", "id, username, nickname");
                    $owner_username = $owner_user["username"];
                    $order_currency = $advertisement_order["currency"];

                    $update_order_data = [];
                    $update_order_data["updated_at"] = date("Y-m-d H:i:s");
                    $update_order_data["status"] = "refund";

                    $db->where("id", $advertisement_order["id"]);
                    $db->update($advertisement_order_table, $update_order_data);

                    $this->escrow_fund_out($advertisement_id, $order_id, $owner_username, $order_quantity, $order_currency);
                } elseif (($advertisement_type == "buy" && $advertisement_order_type == "new_advertisement") || $advertisement_order_fee["status"] == "completed") {
                    // update advertisement status to new
                    $update_data = [];
                    $update_data["updated_at"] = $date;
                    $update_data["status"] = "new";
                    $update_data["expires_at"] = $this->get_advertisement_expiration(date("Y-m-d H:i:s"), "advertisement");

                    $db->where("id", $advertisement_id);
                    $db->update("xun_marketplace_advertisement", $update_data);

                    $db->where("id", $advertisement_order["user_id"]);
                    $xun_user = $db->getOne("xun_user", "username, nickname");
                    $username = $xun_user["username"];
                    $user_nickname = $xun_user["nickname"];

                    $xmpp_recipients = [$username];

                    if ($advertisement["type"] == "sell") {
                        $data_arr = array(
                            "seller_username" => $username,
                            "seller_nickname" => $user_nickname,
                        );
                    } else {
                        $data_arr = array(
                            "buyer_username" => $username,
                            "buyer_nickname" => $user_nickname,
                        );
                    }

                    $default_data_arr = array(
                        "advertisement_id" => (string) $advertisement_id,
                        "order_id" => (string) $order_id,
                        "username" => $username,
                        "nickname" => $user_nickname,
                        "owner_username" => $username,
                        "owner_nickname" => $user_nickname,
                        "crypto_currency" => $advertisement["crypto_currency"],
                        "created_at" => $general->formatDateTimeToIsoFormat($date),
                    );

                    $data_arr_final = array_merge($default_data_arr, $data_arr);
                    $final_order_status = "completed_escrow";
                    $xmpp_event = true;
                }
            } else if ($advertisement_type == "buy") {
                // sell order for buy ads : status: place_order/order_transaction
                /**
                 * if fiat currency -> pending_escrow -> completed_escrow -> pending_payment (no fund out)
                 * if is_cryptocurrency -> pending_escrow -> completed_escrow -> fund out to seller
                 */
                $order_currency = $advertisement_order["currency"];
                $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($order_currency, true);
                $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($advertisement["crypto_currency"], true);

                $currency_decimal_places = $currency_decimal_place_setting["decimal_places"];
                $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];

                $crypto_decimal_places = $crypto_decimal_place_setting["decimal_places"];
                $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

                $this->currency_decimal_place_setting = $currency_decimal_place_setting;
                $this->crypto_decimal_place_setting = $crypto_decimal_place_setting;

                if ($advertisement["is_cryptocurrency"]) {
                    $order_quantity = $advertisement_order["quantity"];
                    // $order_quantity = $advertisement_order["quantity"] / $advertisement_order["price"];
                    // $order_quantity = $setting->setDecimal($order_quantity);
                    $order_quantity = bcdiv((string)$advertisement_order["quantity"], $advertisement_order["price"], $crypto_decimal_places);
                }

                if ($total_debit >= $order_quantity) {
                    // on place sell order
                    $update_data = [];
                    $update_data["updated_at"] = $date;
                    $update_data["status"] = "completed_escrow";
                    $update_data["disabled"] = 1;

                    $db->where("id", $advertisement_order["id"]);
                    $db->update($advertisement_order_table, $update_data);

                    $seller_user_id = $advertisement_order["user_id"];
                    $buyer_user_id = $advertisement["user_id"];

                    $db->where("id", [$seller_user_id, $buyer_user_id], "in");
                    $xun_users = $db->map("id")->ObjectBuilder()->get("xun_user", null, "id, username, nickname");

                    $seller_user = $xun_users[$seller_user_id];
                    $seller_username = $seller_user->username;
                    $seller_nickname = $seller_user->nickname;

                    $buyer_user = $xun_users[$buyer_user_id];
                    $buyer_username = $buyer_user->username;
                    $buyer_nickname = $buyer_user->nickname;

                    /**
                     * check advertisement processing orders and locked quota
                     * reject and refund if fails
                     */

                    $can_place_order_return = $this->can_place_order($advertisement, $order_quantity);

                    // echo "\n can_place_order_return";
                    // print_r($can_place_order_return);
                    // check  if buy ads is sold out 
                    $order_successful = false;
                    if (isset($can_place_order_return["code"]) && $can_place_order_return["code"] == 0) {

                        // update to refund
                        $final_order_status = "refunded";
                        $insert_data = $advertisement_order;
                        unset($insert_data["id"]);
                        $insert_data["status"] = $final_order_status;
                        $insert_data["disabled"] = 0;
                        $insert_data["created_at"] = $date;
                        $insert_data["updated_at"] = $date;
                        $insert_data["expires_at"] = '';

                        // TODO call escrow to refund
                        $this->escrow_fund_out($advertisement_id, $order_id, $seller_username, $order_quantity, $advertisement["crypto_currency"]);
                    } else {
                        $expires_at = $this->get_advertisement_expiration($date, "payment");

                        $final_order_status = "pending_payment";
                        $insert_data = $advertisement_order;
                        unset($insert_data["id"]);
                        $insert_data["status"] = $final_order_status;
                        $insert_data["disabled"] = 0;
                        $insert_data["created_at"] = $date;
                        $insert_data["updated_at"] = $date;
                        $insert_data["expires_at"] = $expires_at;
                        $insert_data["user_id"] = $advertisement["user_id"];

                        $order_successful = true;
                        
                        // update sold out 
                        $new_remaining_quantity = bcsub((string)$can_place_order_return["remaining_quantity"], (string)$order_quantity, 8);
                        $advertisement_min = $advertisement["min"];

                        if(bccomp((string)$new_remaining_quantity, (string)$advertisement_min, 8) < 0){
                            $update_sold_out = [];
                            $update_sold_out["sold_out"] = 1;
                            $update_sold_out["updated_at"] = date("Y-m-d H:i:s");

                            $db->where("id", $advertisement_id);
                            $db->update("xun_marketplace_advertisement", $update_sold_out);
                        }
                    }
                    $row_id = $db->insert($advertisement_order_table, $insert_data);
                    $this->update_advertisement_order_cache_data($insert_data);

                    /**
                     * escrow_completed -> refunded
                     * escrow_completed -> pending payment
                     */

                    $xmpp_recipients = [$seller_username, $buyer_username];
                    $data_arr_final = array(
                        "advertisement_id" => (string) $advertisement_id,
                        "order_id" => (string) $order_id,
                        "username" => $seller_username,
                        "nickname" => $seller_nickname,
                        "owner_username" => $buyer_username,
                        "owner_nickname" => $buyer_nickname,
                        "seller_username" => $seller_username,
                        "seller_nickname" => $seller_nickname,
                        "buyer_username" => $buyer_username,
                        "buyer_nickname" => $buyer_nickname,
                        "crypto_currency" => $advertisement["crypto_currency"],
                        "created_at" => $general->formatDateTimeToIsoFormat($date),
                    );

                    $xmpp_event = true;

                    if ($order_successful && $advertisement["is_cryptocurrency"]) {
                        // c2c advertisement, auto perform escrow fund out
                        // status: pending_payment -> paid
                        $order_status = "paid";
                        $update_data = [];
                        $update_data["disabled"] = 0;
                        $update_data["status"] = $order_status;
                        $update_data["updated_at"] = date("Y-m-d H:i:s");

                        $db->where("id", $row_id);
                        $db->update($advertisement_order_table, $update_data);

                        $updated_advertisement_order = $insert_data;
                        $updated_advertisement_order["status"] = $order_status;
                        $this->update_advertisement_order_cache_data($updated_advertisement_order);

                        $this->escrow_fund_out($advertisement_id, $order_id, $seller_username, $advertisement_order["quantity"], $advertisement["currency"]);
                    }
                }
            } else if ($advertisement_type == "sell" && $advertisement_order_type == "place_order" && $advertisement["is_cryptocurrency"]) {
                // c2c sell advertisement (buy order)

                $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($advertisement_order["currency"], true);

                $currency_decimal_places = $currency_decimal_place_setting["decimal_places"];
                $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];
    
                $order_quantity = bcmul((string)$advertisement_order["quantity"], (string)$advertisement_order["price"], $currency_decimal_places);

                if ($total_debit >= $order_quantity) {
                    // on place sell order
                    $update_data = [];
                    $update_data["updated_at"] = $date;
                    $update_data["status"] = "completed_escrow";
                    $update_data["disabled"] = 1;

                    $db->where("id", $advertisement_order["id"]);
                    $db->update($advertisement_order_table, $update_data);

                    $buyer_user_id = $advertisement_order["user_id"];
                    $seller_user_id = $advertisement["user_id"];

                    $db->where("id", [$seller_user_id, $buyer_user_id], "in");
                    $xun_users = $db->map("id")->ObjectBuilder()->get("xun_user", null, "id, username, nickname");

                    $seller_user = $xun_users[$seller_user_id];
                    $seller_username = $seller_user->username;
                    $seller_nickname = $seller_user->nickname;

                    $buyer_user = $xun_users[$buyer_user_id];
                    $buyer_username = $buyer_user->username;
                    $buyer_nickname = $buyer_user->nickname;

                    /**
                     * check advertisement processing orders and locked quota
                     * reject and refund if fails
                     */

                    $can_place_order_return = $this->can_place_order($advertisement, $advertisement_order["quantity"]);
                    $order_successful = false;
                    if (isset($can_place_order_return["code"]) && $can_place_order_return["code"] == 0) {

                        // update to refund
                        $final_order_status = "refunded";
                        $insert_data = $advertisement_order;
                        unset($insert_data["id"]);
                        $insert_data["status"] = $final_order_status;
                        $insert_data["disabled"] = 0;
                        $insert_data["created_at"] = $date;
                        $insert_data["updated_at"] = $date;
                        $insert_data["expires_at"] = '';

                        // TODO call escrow to refund
                        $this->escrow_fund_out($advertisement_id, $order_id, $buyer_username, $order_quantity, $advertisement["currency"]);

                    } else {
                        $expires_at = $this->get_advertisement_expiration($date, "escrow");

                        $final_order_status = "paid";
                        $insert_data = $advertisement_order;
                        unset($insert_data["id"]);
                        $insert_data["status"] = $final_order_status;
                        $insert_data["disabled"] = 0;
                        $insert_data["created_at"] = $date;
                        $insert_data["updated_at"] = $date;
                        $insert_data["expires_at"] = $expires_at;
                        $insert_data["user_id"] = $buyer_user_id;

                        $order_successful = true;

                    }
                    $row_id = $db->insert($advertisement_order_table, $insert_data);
                    $this->update_advertisement_order_cache_data($insert_data);

                    /**
                     * escrow_completed -> refunded
                     * escrow_completed -> pending payment
                     */

                    $xmpp_recipients = [$seller_username, $buyer_username];
                    $data_arr_final = array(
                        "advertisement_id" => (string) $advertisement_id,
                        "order_id" => (string) $order_id,
                        "username" => $buyer_username,
                        "nickname" => $buyer_username,
                        "owner_username" => $buyer_username,
                        "owner_nickname" => $buyer_nickname,
                        "seller_username" => $seller_username,
                        "seller_nickname" => $seller_nickname,
                        "buyer_username" => $buyer_username,
                        "buyer_nickname" => $buyer_nickname,
                        "crypto_currency" => $advertisement["crypto_currency"],
                        "created_at" => $general->formatDateTimeToIsoFormat($date),
                    );

                    $xmpp_event = true;

                    if ($order_successful) {
                        // c2c advertisement, auto perform escrow fund out
                        // status: paid
                        // escrow to seller
                        $this->escrow_fund_out($advertisement_id, $order_id, $seller_username, $order_quantity, $advertisement["currency"]);
                    }
                }
            }
        } else if ($transaction_type == "send" && $advertisement_order["status"] == "coin_released") {
            $db->where("advertisement_order_id", $order_id);
            $db->where("type", "send");
            $db->where("status", "confirmed");
            $total_credit = $db->getValue("xun_marketplace_escrow_transaction", "sum(credit)");

            if ($advertisement["is_cryptocurrency"] && $advertisement["type"] == "buy") {
                $order_quantity = $advertisement_order["quantity"] / $advertisement_order["price"];
                $order_quantity = $setting->setDecimal($order_quantity);
            }

            if ($advertisement["type"] == "buy") {
                // deduct 1%

                $fee_pct = $setting->systemSetting["marketplaceTradingFee"];
                $fund_out_quantity = $order_quantity * ((100 - $fee_pct) / 100);
                $order_quantity = $setting->setDecimal($fund_out_quantity);
            }

            if ($total_credit >= $order_quantity) {
                /**
                 * coin_released
                 * if buy ad -> place order
                 * if sell -> ad owner
                 *
                 * update status to completed
                 * check if ad is closed
                 */

                $update_data = [];
                $update_data["updated_at"] = $date;
                $update_data["status"] = "completed";

                $db->where("id", $advertisement_order["id"]);
                $db->update($advertisement_order_table, $update_data);

                $new_advertisement_order = [];
                $new_advertisement_order["order_id"] = $advertisement_order["order_id"];
                $new_advertisement_order["status"] = "completed";
                $new_advertisement_order["updated_at"] = $date;

                $this->update_advertisement_order_cache_data($new_advertisement_order);

                //  update advertisement status to closed if min > max
                $effective_remaining_quantity = $this->get_advertisement_effective_remaining_quantity($advertisement);

                $is_closed = $this->is_below_advertisement_minimum($advertisement, $effective_remaining_quantity);

                if ($is_closed) {
                    $update_data = [];
                    $update_data["updated_at"] = $date;
                    $update_data["status"] = "closed";
                    // update sold_out

                    $db->where("id", $advertisement["id"]);
                    $db->update("xun_marketplace_advertisement", $update_data);

                    // refund trading fee
                    /**
                     * sell ad: must refund trading fee
                     * buy ads: refund trading fee if it's paid with tnc
                     */
                    if ($advertisement["type"] == "sell" || $advertisement["is_cryptocurrency"]) {
                        $this->escrow_refund_advertisement($advertisement);
                    }
                } else {
                    if ($advertisement["sold_out"] == 1) {
                        // update sold_out column
                        // check max order, min>remaining
                        // check if current locked quantity is > min

                        $locked_remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement);
                        $is_sold_out = $this->is_below_advertisement_minimum($advertisement, $locked_remaining_quantity);

                        if (!$is_sold_out) {
                            $update_data = [];
                            $update_data["updated_at"] = $date;
                            $update_data["sold_out"] = 0;
                            $db->where("id", $advertisement["id"]);
                            $db->update("xun_marketplace_advertisement", $update_data);
                        }
                    }
                }

                $db->where("order_id", $order_id);
                $db->orderBy("created_at", "asc");
                $order_user_id = $db->getValue($advertisement_order_table, "user_id");

                if ($advertisement["type"] == "sell") {
                    $seller_user_id = $advertisement["user_id"];
                    $buyer_user_id = $order_user_id;
                } else {
                    $buyer_user_id = $advertisement["user_id"];
                    $seller_user_id = $order_user_id;
                }

                // update user trade count
                $this->update_user_trade_count($buyer_user_id);
                $this->update_user_trade_count($seller_user_id);

                // send xmpp message

                $db->where("id", [$seller_user_id, $buyer_user_id], "in");
                $xun_users = $db->map("id")->ObjectBuilder()->get("xun_user", null, "id, username, nickname");

                $seller_user = $xun_users[$seller_user_id];
                $seller_username = $seller_user->username;
                $seller_nickname = $seller_user->nickname;

                $buyer_user = $xun_users[$buyer_user_id];
                $buyer_username = $buyer_user->username;
                $buyer_nickname = $buyer_user->nickname;

                $owner_username = $advertisement["type"] == "sell" ? $seller_username : $buyer_username;
                $owner_nickname = $advertisement["type"] == "sell" ? $seller_nickname : $buyer_nickname;
                $xmpp_recipients = [$buyer_username, $seller_username];

                $final_order_status = "completed";

                $xmpp_event = true;

                $data_arr_final = array(
                    "advertisement_id" => (string) $advertisement_id,
                    "order_id" => (string) $order_id,
                    "username" => $seller_username,
                    "nickname" => $seller_nickname,
                    "owner_username" => $owner_username,
                    "owner_nickname" => $owner_nickname,
                    "seller_username" => $seller_username,
                    "seller_nickname" => $seller_nickname,
                    "buyer_username" => $buyer_username,
                    "buyer_nickname" => $buyer_nickname,
                    "crypto_currency" => $advertisement["crypto_currency"],
                    "created_at" => $general->formatDateTimeToIsoFormat($date),
                );
            }
        } else if ($transaction_type == "send" && ($advertisement_order["status"] == "cancelled" || $advertisement_order["status"] == "expired") && $advertisement["type"] == "buy") {
            $db->where("order_id", $order_id);
            $seller_user_id = $db->getValue("xun_marketplace_advertisement_order_cache", "user_id");

            $buyer_user_id = $advertisement["user_id"];

            $db->where("id", [$seller_user_id, $buyer_user_id], "in");
            $xun_users = $db->map("id")->ObjectBuilder()->get("xun_user", null, "id, username, nickname");

            $seller_user = $xun_users[$seller_user_id];
            $seller_username = $seller_user->username;
            $seller_nickname = $seller_user->nickname;

            $buyer_user = $xun_users[$buyer_user_id];
            $buyer_username = $buyer_user->username;
            $buyer_nickname = $buyer_user->nickname;

            $owner_username = $buyer_username;
            $owner_nickname = $buyer_nickname;
            $xmpp_recipients = [$buyer_username, $seller_username];

            $xmpp_event = true;
            $final_order_status = "refund_successful";
            $data_arr_final = array(
                "advertisement_id" => (string) $advertisement_id,
                "order_id" => (string) $order_id,
                "username" => $seller_username,
                "nickname" => $seller_nickname,
                "owner_username" => $owner_username,
                "owner_nickname" => $owner_nickname,
                "seller_username" => $seller_username,
                "seller_nickname" => $seller_nickname,
                "buyer_username" => $buyer_username,
                "buyer_nickname" => $buyer_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "quantity" => $advertisement_order["quantity"],
                "created_at" => $general->formatDateTimeToIsoFormat($date),
            );
        } else if ($transaction_type == "send" && $advertisement_order["status"] == "refund") {
            // && ($advertisement["type"] == "sell" || ($advertisement["type"] == "buy" && $advertisement["is_cryptocurrency"]))) {
            // refund sell ad owner after ad closed/cancelled/expired
            // refund buy ad owner after ad closed/cancelled/expired for c2c advertisements

            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = "refund_successful";
            $db->where("id", $advertisement_order["id"]);
            $db->update($advertisement_order_table, $update_data);

            $db->where("id", $advertisement["user_id"]);
            $owner_user = $db->getOne("xun_user", "username, nickname");
            $owner_username = $owner_user["username"];
            $owner_nickname = $owner_user["nickname"];

            $xmpp_recipients = [$owner_username];

            $final_order_status = "refund_successful";
            $xmpp_event = true;

            if ($advertisement_type == "sell") {
                $data_arr = array(
                    "seller_username" => $owner_username,
                    "seller_nickname" => $owner_nickname,
                    "buyer_username" => "",
                    "buyer_nickname" => "",
                    "crypto_currency" => $advertisement["crypto_currency"],
                );
            } else {
                $data_arr = array(
                    "seller_username" => "",
                    "seller_nickname" => "",
                    "buyer_username" => $owner_username,
                    "buyer_nickname" => $owner_nickname,
                    "crypto_currency" => $advertisement["currency"],
                );
            }

            $default_data_arr = array(
                "advertisement_id" => (string) $advertisement_id,
                "order_id" => (string) $order_id,
                "username" => $owner_username,
                "nickname" => $owner_nickname,
                "owner_username" => $owner_username,
                "owner_nickname" => $owner_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "quantity" => $advertisement_order["quantity"],
                "created_at" => $general->formatDateTimeToIsoFormat($date),
            );

            $data_arr_final = array_merge($default_data_arr, $data_arr);

        } else if ($transaction_type == "send" && $advertisement["is_cryptocurrency"] && $advertisement_order["type"] == "buy" && $advertisement_order["status"] == "paid") {
            // c2c sell order after coin is released to seller
            // deduct trading fee if advertisement pays trading with ad cryptocurrency

            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["disabled"] = 1;

            $db->where("id", $advertisement_order["id"]);
            $db->update($advertisement_order_table, $update_data);

            $db->where("order_id", $order_id);
            $db->orderBy("created_at", "asc");
            $seller_user_id = $db->getValue($advertisement_order_table, "user_id");
            $buyer_user_id = $advertisement["user_id"];

            $db->where("id", [$seller_user_id, $buyer_user_id], "in");
            $xun_users = $db->map("id")->ObjectBuilder()->get("xun_user", null, "id, username, nickname");

            $seller_user = $xun_users[$seller_user_id];
            $seller_username = $seller_user->username;
            $seller_nickname = $seller_user->nickname;

            $buyer_user = $xun_users[$buyer_user_id];
            $buyer_username = $buyer_user->username;
            $buyer_nickname = $buyer_user->nickname;

            $order_status = "coin_released";
            $insert_data = $advertisement_order;
            unset($insert_data["id"]);
            $insert_data["status"] = $order_status;
            $insert_data["disabled"] = 0;
            $insert_data["created_at"] = $date;
            $insert_data["updated_at"] = $date;
            $insert_data["expires_at"] = "";
            $insert_data["user_id"] = $seller_user_id;

            $row_id = $db->insert($advertisement_order_table, $insert_data);
            $this->update_advertisement_order_cache_data($insert_data);
            
            // TODO: this->order_decimal_places
            $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($advertisement_order["currency"], true);
            $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($advertisement["crypto_currency"], true);

            $currency_decimal_places = $currency_decimal_place_setting["decimal_places"];
            $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];

            $crypto_decimal_places = $crypto_decimal_place_setting["decimal_places"];
            $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

            $this->currency_decimal_place_setting = $currency_decimal_place_setting;
            $this->crypto_decimal_place_setting = $crypto_decimal_place_setting;
            $this->order_decimal_place_setting = $crypto_decimal_place_setting;

            $order_quantity_currency = $advertisement_order["quantity"];
            $order_quantity = bcdiv((string)$order_quantity_currency, (string)$advertisement_order["price"], $crypto_decimal_places);

            $fund_out_result = $this->get_order_fund_out_amount($advertisement, $order_quantity);
            $fund_out_quantity = $fund_out_result["fund_out_quantity"];
            $fund_out_trading_fee = $fund_out_result["fund_out_trading_fee"];
            // print_r($fund_out_result);

            $this->escrow_fund_out($advertisement_id, $order_id, $buyer_username, $fund_out_quantity, $advertisement["crypto_currency"]);

            $xmpp_recipients = [$seller_username, $owner_username];
            $xmpp_event = true;
            $final_order_status = $order_status;
            $data_arr_final = array(
                "advertisement_id" => (string) $advertisement_id,
                "order_id" => (string) $order_id,
                "username" => $seller_username,
                "nickname" => $seller_nickname,
                "owner_username" => $buyer_username,
                "owner_nickname" => $buyer_nickname,
                "seller_username" => $seller_username,
                "seller_nickname" => $seller_nickname,
                "buyer_username" => $buyer_username,
                "buyer_nickname" => $buyer_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "quantity" => $advertisement_order["quantity"],
                "created_at" => $general->formatDateTimeToIsoFormat($date),
            );

            if ($fund_out_trading_fee) {
                $trading_fee_params = array(
                    "advertisement_id" => $advertisement_id,
                    "order_id" => $order_id,
                    "user_id" => $advertisement["user_id"],
                    "type" => $advertisement["type"],
                );

                $this->process_trading_fee_order($advertisement_order_table, $trading_fee_params, $fund_out_result);
            }
    
            $user_country_info_arr = $xunUser->get_user_country_info([$buyer_username, $seller_username]);
            $owner_country_info = $user_country_info_arr[$buyer_username];
            $owner_country = $owner_country_info["name"];
            
            $order_country_info = $user_country_info_arr[$seller_username];
            $order_country = $order_country_info["name"];
    
            $owner_device_ip_arr = $xunUser->get_device_os_ip($buyer_user_id, $buyer_username);
            $owner_ip = $owner_device_ip_arr["ip"];
            $owner_device_os = $owner_device_ip_arr["device_os"];
    
            $order_device_ip_arr = $xunUser->get_device_os_ip($seller_user_id, $seller_username);
            $order_ip = $order_device_ip_arr["ip"];
            $order_device_os = $order_device_ip_arr["device_os"];
    
            $ads_min_max = $this->get_ads_order_min_max_cryptocurrency($advertisement, $advertisement_order);
            $min_limit = $ads_min_max["min"];
            $max_limit = $ads_min_max["max"];
    
            $currency = $advertisement["currency"];
            $price_type = $advertisement["price_type"];
            $ads_type = $advertisement["type"];
            $order_price = $advertisement_order["price"];
            $order_price = $this->order_price_convert($advertisement, $advertisement_order, $order_price);
    
            $content = "Ads Owner\n";
            $content .= "Username: " . $buyer_nickname . "\n";
            $content .= "Phone Number: " . $buyer_username . "\n";
            $content .= "IP: " . $owner_ip . "\n";
            $content .= "Country: " . $owner_country . "\n";
            $content .= "Device: " . $owner_device_os . "\n";
            $content .= "Advertisement ID: " . $advertisement_id . "\n";
            $content .= "Advertisement Type: " . ucfirst($advertisement["type"]) . "\n";
            $content .= ucfirst($ads_type). ": " . ucfirst($advertisement["crypto_currency"]) . "\n";
            if($ads_type == "sell"){
                $content .= "Accept with: " . ucfirst($currency) . "\n";
            }else if ($ads_type == "buy"){
                $content .= "Pay with: " . ucfirst($currency) . "\n";
            }
            $content .= "Price setting: " . ucfirst($price_type) . "\n";
            if ($price_type == "fix"){
                $content .= "Price: " . $advertisement["price"] . " " . ucfirst($advertisement["price_unit"]) . "\n";
            }
            else if($price_type == "floating"){
                $content .= "Floating ratio: " . $advertisement["floating_ratio"] . "\n";
                if ($ads_type == "sell"){
                    $content .= "Min Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
                }else if ($ads_type == "buy"){
                    $content .= "Max Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
                }
            }
            $content .= "Transaction limit: " . $min_limit . " - " . $max_limit . "\n";
    
            $content .= "\nPlace Order By \n";
            $content .= "Username: " . $seller_nickname . "\n";
            $content .= "Phone Number: " . $seller_username . "\n";
            $content .= "IP: " . $order_ip . "\n";
            $content .= "Country: " . ucfirst($order_country) . "\n";
            $content .= "Device: " . $order_device_os . "\n";
            $content .= "Order Price: " . $order_price . "\n";
            $content .= "Order Volume: " . $advertisement_order["quantity"] . "\n";
            $content .= "Order ID: " . $order_id . "\n";
    
            //$content .= "Price setting: " . $price_setting . "\n";
            $content .= "\nTime: " . date("Y-m-d H:i:s");
    
            $tag = "Placed Order (Released)";
            $erlang_params = array();
            $erlang_params["tag"] = $tag;
            $erlang_params["message"] = $content;
            $erlang_params["mobile_list"] = $xun_numbers;
            $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");

        } else if ($transaction_type == "send" && $advertisement["is_cryptocurrency"] && $advertisement_order["type"] == "sell" && $advertisement_order["status"] == "paid") {
            // c2c sell order after coin is released to seller
            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["disabled"] = 1;

            $db->where("id", $advertisement_order["id"]);
            $db->update($advertisement_order_table, $update_data);

            $buyer_user_id = $advertisement_order["user_id"];
            $seller_user_id = $advertisement["user_id"];

            $db->where("id", [$seller_user_id, $buyer_user_id], "in");
            $xun_users = $db->map("id")->ObjectBuilder()->get("xun_user", null, "id, username, nickname");

            $seller_user = $xun_users[$seller_user_id];
            $seller_username = $seller_user->username;
            $seller_nickname = $seller_user->nickname;

            $buyer_user = $xun_users[$buyer_user_id];
            $buyer_username = $buyer_user->username;
            $buyer_nickname = $buyer_user->nickname;

            $order_status = "coin_released";
            $insert_data = $advertisement_order;
            unset($insert_data["id"]);
            $insert_data["status"] = $order_status;
            $insert_data["disabled"] = 0;
            $insert_data["created_at"] = $date;
            $insert_data["updated_at"] = $date;
            $insert_data["expires_at"] = "";
            $insert_data["user_id"] = $seller_user_id;

            $row_id = $db->insert($advertisement_order_table, $insert_data);
            $this->update_advertisement_order_cache_data($insert_data);
 
            $crypto_currency = $advertisement["crypto_currency"];
            // get decimal places for cryptocurrency
            $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($crypto_currency, true);

            $crypto_decimal_places = $crypto_decimal_place_setting["decimal_places"];
            $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

            // print_r($crypto_decimal_place_setting);
            $this->order_decimal_place_setting = $crypto_decimal_place_setting;
            // echo "\n order_quantity $order_quantity";

            $fund_out_result = $this->get_order_fund_out_amount($advertisement, $order_quantity);
            $fund_out_quantity = $fund_out_result["fund_out_quantity"];
            $fund_out_trading_fee = $fund_out_result["fund_out_trading_fee"];
            // print_r($fund_out_result);

            $fund_out_quantity = $advertisement_order["quantity"];
            $fund_out_quantity = $setting->setDecimal($fund_out_quantity);

            $this->escrow_fund_out($advertisement_id, $order_id, $buyer_username, $fund_out_quantity, $crypto_currency);

            $xmpp_recipients = [$seller_username, $buyer_username];

            $erlang_params = array(
                "chatroom_id" => (string) $order_id,
                "chatroom_host" => $marketplace_chat_room_host,
                "recipients" => $xmpp_recipients,
                "type" => $order_status,
                "data" => array(
                    "advertisement_id" => (string) $advertisement_id,
                    "order_id" => (string) $order_id,
                    "username" => $seller_username,
                    "nickname" => $seller_nickname,
                    "owner_username" => $seller_username,
                    "owner_nickname" => $seller_nickname,
                    "seller_username" => $seller_username,
                    "seller_nickname" => $seller_nickname,
                    "buyer_username" => $buyer_username,
                    "buyer_nickname" => $buyer_nickname,
                    "crypto_currency" => $advertisement["crypto_currency"],
                    "quantity" => $advertisement_order["quantity"],
                    "created_at" => $general->formatDateTimeToIsoFormat($date),
                ),
            );

            $erlang_return = $xunXmpp->send_xmpp_marketplace_event($erlang_params);

            if ($fund_out_trading_fee) {
                $trading_fee_params = array(
                    "advertisement_id" => $advertisement_id,
                    "order_id" => $order_id,
                    "user_id" => $advertisement["user_id"],
                    "type" => $advertisement["type"],
                );

                $this->process_trading_fee_order($advertisement_order_table, $trading_fee_params, $fund_out_result);
            }

            $user_country_info_arr = $xunUser->get_user_country_info([$seller_username, $buyer_username]);
            $owner_country_info = $user_country_info_arr[$seller_username];
            $owner_country = $owner_country_info["name"];
            
            $order_country_info = $user_country_info_arr[$buyer_username];
            $order_country = $order_country_info["name"];

            $owner_device_ip_arr = $xunUser->get_device_os_ip($seller_user_id, $seller_username);
            $owner_ip = $owner_device_ip_arr["ip"];
            $owner_device_os = $owner_device_ip_arr["device_os"];
    
            $order_device_ip_arr = $xunUser->get_device_os_ip($buyer_user_id, $buyer_username);
            $order_ip = $order_device_ip_arr["ip"];
            $order_device_os = $order_device_ip_arr["device_os"];
    
            $ads_min_max = $this->get_ads_order_min_max_cryptocurrency($advertisement, $advertisement_order);
            $min_limit = $ads_min_max["min"];
            $max_limit = $ads_min_max["max"];
    
            $currency = $advertisement["currency"];
            $price_type = $advertisement["price_type"];
            $ads_type = $advertisement["type"];
            $order_price = $advertisement_order["price"];
            $order_price = $this->order_price_convert($advertisement, $advertisement_order, $order_price);
    
            $content = "Ads Owner\n";
            $content .= "Username: " . $seller_nickname . "\n";
            $content .= "Phone Number: " . $seller_username . "\n";
            $content .= "IP: " . $owner_ip . "\n";
            $content .= "Country: " . $owner_country . "\n";
            $content .= "Device: " . $owner_device_os . "\n";
            $content .= "Advertisement ID: " . $advertisement_id . "\n";
            $content .= "Advertisement Type: " . ucfirst($advertisement["type"]) . "\n";
            $content .= ucfirst($ads_type). ": " . ucfirst($advertisement["crypto_currency"]) . "\n";
            if($ads_type == "sell"){
                $content .= "Accept with: " . ucfirst($currency) . "\n";
            }else if ($ads_type == "buy"){
                $content .= "Pay with: " . ucfirst($currency) . "\n";
            }
            $content .= "Price setting: " . ucfirst($price_type) . "\n";
            if ($price_type == "fix"){
                $content .= "Price: " . $advertisement["price"] . " " . ucfirst($advertisement["price_unit"]) . "\n";
            }
            else if($price_type == "floating"){
                $content .= "Floating ratio: " . $advertisement["floating_ratio"] . "\n";
                if ($ads_type == "sell"){
                    $content .= "Min Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
                }else if ($ads_type == "buy"){
                    $content .= "Max Price: " . $advertisement["price_limit"] . " " . ucfirst($advertisement["limit_currency"]) . "\n";
                }
            }
            $content .= "Transaction limit: " . $min_limit . " - " . $max_limit . "\n";
    
            $content .= "\nPlace Order By \n";
            $content .= "Username: " . $buyer_nickname . "\n";
            $content .= "Phone Number: " . $buyer_username . "\n";
            $content .= "IP: " . $order_ip . "\n";
            $content .= "Country: " . ucfirst($order_country) . "\n";
            $content .= "Device: " . $order_device_os . "\n";
            $content .= "Order Price: " . $order_price . "\n";
            $content .= "Order Volume: " . $advertisement_order["quantity"] . "\n";
            $content .= "Order ID: " . $order_id . "\n";
    
            //$content .= "Price setting: " . $price_setting . "\n";
            $content .= "\nTime: " . date("Y-m-d H:i:s");
    
            $tag = "Placed Order (Released)";
            $erlang_params = array();
            $erlang_params["tag"] = $tag;
            $erlang_params["message"] = $content;
            $erlang_params["mobile_list"] = $xun_numbers;
            $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_xchange");

        } else if ($transaction_type == "send" && $advertisement_order_type == "order_trading_fee" && in_array($advertisement_order["status"], ["pre_escrow_fund_out", "pending_escrow_fund_out"])) {
            // thenux -> escrow -> trading fee wallet
            // pending_escrow -> completed
            // trading fee wallet received
            // trading fee -> upline & company pool
            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = "completed";
            $db->where("id", $advertisement_order["id"]);
            $db->update($advertisement_order_table, $update_data);

            // check if there's upline
            // get upline and company pool quantity
            // get currency decimal places settings
            $fund_out_currency = $advertisement_order["currency"];
            $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($fund_out_currency, true);
    
            $trading_fee_params = array("user_id" => $advertisement["user_id"],
                "quantity" => $advertisement_order["quantity"]);
            $trading_fee_arr = $this->calculate_upline_trading_fee_quantity($trading_fee_params, $currency_decimal_place_setting);

            $upline_id = $trading_fee_arr["upline_id"];
            $upline_quantity = $trading_fee_arr["upline_quantity"];
            $company_pool_quantity = $trading_fee_arr["company_pool_quantity"];
            /**
             * if upline_id
             * -    get upline user info, crypto address
             * -    add to xun_referral_transaction table
             */

            if ($upline_id) {
                $db->where("id", $upline_id);
                $db->where("disabled", 0);
                $upline_user = $db->getOne("xun_user", "id, username, nickname");

                $row_id = $this->insert_referral_transaction($upline_id, $advertisement_order, $upline_quantity, 0, $advertisement["user_id"]);

                if ($row_id) {
                    $upline_order_id = $this->get_order_no();

                    $insert_data = array(
                        "advertisement_id" => $advertisement["id"],
                        "order_id" => $upline_order_id,
                        "order_type" => "upline_trading_fee_payout",
                        "user_id" => $upline_id,
                        "type" => $advertisement["type"],
                        "price" => '',
                        "quantity" => $upline_quantity,
                        "currency" => $fund_out_currency,
                        "status" => "pending_fund_out",
                        "expires_at" => '',
                        "order_no" => $upline_order_id,
                        "reference_order_id" => $advertisement_order["reference_order_id"],
                        "disabled" => 0,
                        "created_at" => date("Y-m-d H:i:s"),
                        "updated_at" => date("Y-m-d H:i:s"),
                    );

                    $upline_order_row_id = $db->insert($advertisement_order_table, $insert_data);

                    if ($upline_order_row_id) {
                        $upline_escrow_return = $this->escrow_fund_out($advertisement_id, $upline_order_id, $upline_user["username"], $upline_quantity, $fund_out_currency, null, "trading_fee");
                    }

                }
            }

            if (bccomp((string) $company_pool_quantity, "0", 8) > 0) {
                $cp_order_id = $this->get_order_no();

                $insert_data = array(
                    "advertisement_id" => $advertisement["id"],
                    "order_id" => $cp_order_id,
                    "order_type" => "company_pool_trading_fee_payout",
                    "user_id" => $advertisement_order["user_id"],
                    "type" => $advertisement["type"],
                    "price" => '',
                    "quantity" => $company_pool_quantity,
                    "currency" => $fund_out_currency,
                    "status" => "pending_fund_out",
                    "expires_at" => '',
                    "order_no" => $cp_order_id,
                    "reference_order_id" => $advertisement_order["reference_order_id"],
                    "disabled" => 0,
                    "created_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s"),
                );

                $cp_order_row_id = $db->insert($advertisement_order_table, $insert_data);

                if ($cp_order_row_id) {
                    $company_pool_address = $setting->systemSetting["marketplaceCompanyPoolWalletAddress"];

                    $cp_escrow_return = $this->escrow_fund_out($advertisement_id, $cp_order_id, null, $company_pool_quantity, $fund_out_currency, $company_pool_address, "trading_fee");

                }
            }
        } else if ($transaction_type == "send" && $advertisement_order_type == "upline_trading_fee_payout" && in_array($advertisement_order["status"], ["pre_fund_out", "pending_fund_out"])) {
            // update to completed
            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = "completed";
            $db->where("id", $advertisement_order["id"]);
            $db->update($advertisement_order_table, $update_data);
        } else if ($transaction_type == "send" && $advertisement_order_type == "company_pool_trading_fee_payout" && in_array($advertisement_order["status"], ["pre_fund_out", "pending_fund_out"])) {
            // update to completed
            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = "completed";
            $db->where("id", $advertisement_order["id"]);
            $db->update($advertisement_order_table, $update_data);

            // check if there's upline
            // get upline and company pool quantity
            $fund_out_currency = $advertisement_order["currency"];

            //  get fund out currency decimal places
            $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($fund_out_currency, true);

            // echo "\n currency_decimal_place_setting";
            // print_r($currency_decimal_place_setting);
            $trading_fee_params = array("user_id" => $advertisement["user_id"],
                "quantity" => $advertisement_order["quantity"]);
            $trading_fee_arr = $this->calculate_master_upline_trading_fee_quantity($trading_fee_params, $currency_decimal_place_setting);

            // echo "\n trading_fee_arr";
            // print_r($trading_fee_arr);
            $master_upline_id = $trading_fee_arr["master_upline_id"];
            $master_upline_quantity = $trading_fee_arr["master_upline_quantity"];
            $company_acc_quantity = $trading_fee_arr["company_acc_quantity"];
            /**
             * if master_upline_id
             * -    get upline user info, crypto address
             * -    add to xun_referral_transaction table
             */

            if ($master_upline_id) {
                $db->where("id", $master_upline_id);
                $db->where("disabled", 0);
                $upline_user = $db->getOne("xun_user", "id, username, nickname");

                $row_id = $this->insert_referral_transaction($master_upline_id, $advertisement_order, $master_upline_quantity, 1, $advertisement["user_id"]);

                if ($row_id) {
                    $master_upline_order_id = $this->get_order_no();

                    $insert_data = array(
                        "advertisement_id" => $advertisement["id"],
                        "order_id" => $master_upline_order_id,
                        "order_type" => "master_upline_trading_fee_payout",
                        "user_id" => $master_upline_id,
                        "type" => $advertisement["type"],
                        "price" => '',
                        "quantity" => $master_upline_quantity,
                        "currency" => $fund_out_currency,
                        "status" => "pending_fund_out",
                        "expires_at" => '',
                        "order_no" => $master_upline_order_id,
                        "reference_order_id" => $advertisement_order["reference_order_id"],
                        "disabled" => 0,
                        "created_at" => date("Y-m-d H:i:s"),
                        "updated_at" => date("Y-m-d H:i:s"),
                    );

                    $upline_order_row_id = $db->insert($advertisement_order_table, $insert_data);

                    if ($upline_order_row_id) {
                        $upline_escrow_return = $this->escrow_fund_out($advertisement_id, $master_upline_order_id, $upline_user["username"], $master_upline_quantity, $fund_out_currency, null, "company_pool");
                    }

                }
            }

            if (bccomp((string) $company_acc_quantity, "0", 8) > 0) {
                $ca_order_id = $this->get_order_no();

                $insert_data = array(
                    "advertisement_id" => $advertisement["id"],
                    "order_id" => $ca_order_id,
                    "order_type" => "company_acc_trading_fee_payout",
                    "user_id" => $advertisement_order["user_id"],
                    "type" => $advertisement["type"],
                    "price" => '',
                    "quantity" => $company_acc_quantity,
                    "currency" => $fund_out_currency,
                    "status" => "pending_fund_out",
                    "expires_at" => '',
                    "order_no" => $ca_order_id,
                    "reference_order_id" => $advertisement_order["reference_order_id"],
                    "disabled" => 0,
                    "created_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s"),
                );

                $ca_order_row_id = $db->insert($advertisement_order_table, $insert_data);

                if ($ca_order_row_id) {
                    $company_acc_address = $setting->systemSetting["marketplaceCompanyAccWalletAddress"];
                    $ca_escrow_return = $this->escrow_fund_out($advertisement_id, $ca_order_id, null, $company_acc_quantity, $fund_out_currency, $company_acc_address, "company_pool");
                }
            }
        } else if ($transaction_type == "send" && $advertisement_order_type == "company_acc_trading_fee_payout" && in_array($advertisement_order["status"], ["pre_fund_out", "pending_fund_out"])) {
            // update to completed
            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = "completed";
            $db->where("id", $advertisement_order["id"]);
            $db->update($advertisement_order_table, $update_data);
        } else if ($transaction_type == "send" && $advertisement_order_type == "master_upline_trading_fee_payout" && in_array($advertisement_order["status"], ["pre_fund_out", "pending_fund_out"])) {
            // update to completed
            $update_data = [];
            $update_data["updated_at"] = $date;
            $update_data["status"] = "completed";
            $db->where("id", $advertisement_order["id"]);
            $db->update($advertisement_order_table, $update_data);
        }

        if ($xmpp_event) {
            $erlang_params = array(
                "chatroom_id" => (string) $order_id,
                "chatroom_host" => $marketplace_chat_room_host,
                "recipients" => $xmpp_recipients,
                "type" => $final_order_status,
                "data" => $data_arr_final,
            );
            $res = $xunXmpp->send_xmpp_marketplace_event($erlang_params);
        }
    }

    public function escrow_fund_out($advertisement_id, $order_id, $username, $quantity, $crypto_currency, $receiver_address = null, $destination_wallet_server = "escrow")
    {
        global $config, $setting, $xunXmpp, $xunCrypto;
        $post = $this->post;
        $db = $this->db;

        if ($username) {
            $db->where("username", $username);
            $user_id = $db->getValue("xun_user", "id");
            
            $db->where("user_id", $user_id);
            $db->where("active", 1);
            $db->where("address_type", "personal");
            $receiver_address = $db->getValue("xun_crypto_user_address", "address");
        }

        $quantity = $setting->setDecimal($quantity);
        //  convert quantity to satoshi value before posting to wallet server

        $satoshi_amount = $xunCrypto->get_satoshi_amount($crypto_currency, $quantity);

        $new_params = [];
        $new_params["advertisement_id"] = $advertisement_id;
        $new_params["advertisement_order_id"] = $order_id;
        $new_params["receiverAddress"] = $receiver_address;
        $new_params["amount"] = $satoshi_amount;
        $new_params["walletType"] = $crypto_currency;

        // post to escrow
        if ($destination_wallet_server == "escrow") {
            $url_string = $config["escrowURL"];
        } else if ($destination_wallet_server == "trading_fee") {
            $url_string = $config["tradingFeeURL"];
        } else if ($destination_wallet_server == "company_pool") {
            $url_string = $config["companyPoolURL"];
        }

        $post_return = $post->curl_post($url_string, $new_params, 0, 0);

        $post_return_obj = json_decode($post_return);
        if ($post_return_obj->code == 0) {
            // send notification
            $result = $post_return_obj->result;
            if (gettype($result) == "object") {
                $error_message = $result->statusMsg;
            } else {
                $error_message = $result;
            }
            $tag = "Escrow Error";
            $content = "Error: " . $error_message;
            $content .= "\n\nAdvertisement ID: " . $advertisement_id;
            $content .= "\nOrder ID: " . $order_id;
            $content .= "\nReceiver Address: " . $receiver_address;
            $content .= "\nAmount: " . $quantity;

            $erlang_params["tag"] = $tag;
            $erlang_params["message"] = $content;
            $erlang_params["mobile_list"] = array();
            $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

            $insert_data = [];
            $insert_data["username"] = $username ? $username : '';
            $insert_data["data"] = json_encode($new_params);
            $insert_data["processed"] = 0;
            $insert_data["created_at"] = date("Y-m-d H:i:s");
            $insert_data["updated_at"] = date("Y-m-d H:i:s");

            $db->insert("xun_marketplace_escrow_error", $insert_data);
        }
        return $post_return;
    }

    public function escrow_refund_advertisement($advertisement)
    {
        global $setting;
        $db = $this->db;

        // fiat buy ad, fee type != tnc, no refund
        // buy tnc, pay with myr, fee type == tnc, no refund
        if ($advertisement["type"] == "buy" && $advertisement["is_cryptocurrency"] === 0) {
            return;
        }

        $date = date("Y-m-d H:i:s");
        // create daily table if needed
        $order_id = $this->get_order_no();

        $advertisement_quantity = $advertisement["quantity"];

        // only buy && c2c no need to refund trading fee
        // refund trading fee for sell ads
        $refund_trading_fee_bool = false;
        if ($advertisement["type"] == "buy") {
            $locked_remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement);

            $refund_advertisement_bool = true;
        } else {
            $advertisement_locked_remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement, true);
            $locked_remaining_quantity = $advertisement_locked_remaining_quantity["remaining_quantity"];
            $total_trading_fee = $advertisement_locked_remaining_quantity["total_trading_fee"];

            $refund_advertisement_bool = true;
            $refund_trading_fee_bool = true;
        }

        // if($advertisement["type"] == "buy" && $advertisement["is_cryptocurrency"] === 0 && $advertisement["fee_type"] == "thenuxcoin") $refund_advertisement_bool = false;

        if ($locked_remaining_quantity > 0) {
            $advertisement_date = $advertisement["created_at"];
            $advertisement_order_transaction_table = $this->create_advertisement_order_transaction_daily_table($advertisement_date);
            if ($refund_advertisement_bool) {

                if ($advertisement["type"] == "buy" && $advertisement["is_cryptocurrency"]) {
                    $refund_currency = $advertisement["currency"];
                } else {
                    $refund_currency = $advertisement["crypto_currency"];
                }
                $insert_data = array(
                    "advertisement_id" => $advertisement["id"],
                    "order_id" => $order_id,
                    "order_type" => "refund_advertisement",
                    "user_id" => $advertisement["user_id"],
                    "type" => $advertisement["type"],
                    "price" => $advertisement["price"],
                    "quantity" => $locked_remaining_quantity,
                    "currency" => $refund_currency,
                    "status" => "refund",
                    "expires_at" => '',
                    "order_no" => $order_id,
                    "disabled" => 0,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $row_id = $db->insert($advertisement_order_transaction_table, $insert_data);

                $db->where("id", $advertisement["user_id"]);
                $username = $db->getValue("xun_user", "username");
                $escrow_return = $this->escrow_fund_out($advertisement["id"], $order_id, $username, $locked_remaining_quantity, $refund_currency);
            }
            if ($refund_trading_fee_bool) {
                if (bccomp((string) $advertisement["fee_quantity"], "0", 8) > 0) {
                    // advertisement has trading fee
                    // check if trading fee is zero
                    $refund_fee_currency = $advertisement["fee_type"];
                    $refund_fee_quantity = bcsub((string) $advertisement["fee_quantity"], (string) $total_trading_fee, 8);

                    $refund_fee_order_id = $this->get_order_no();
                    unset($insert_data);
                    $insert_data = array(
                        "advertisement_id" => $advertisement["id"],
                        "order_id" => $refund_fee_order_id,
                        "order_type" => "refund_trading_fee",
                        "user_id" => $advertisement["user_id"],
                        "type" => $advertisement["type"],
                        "price" => $advertisement["price"],
                        "quantity" => $refund_fee_quantity,
                        "currency" => $refund_fee_currency,
                        "status" => "refund",
                        "expires_at" => '',
                        "order_no" => $refund_fee_order_id,
                        "disabled" => 0,
                        "created_at" => $date,
                        "updated_at" => $date,
                    );
                    // print_r($insert_data);
                    $row_id = $db->insert($advertisement_order_transaction_table, $insert_data);
                    // if(!$row_id) print_r($db);
                    $refund_trading_fee_escrow_return = $this->escrow_fund_out($advertisement["id"], $refund_fee_order_id, $username, $refund_fee_quantity, $refund_fee_currency);
                }
            }

            $refund_fee_quantity = $refund_fee_quantity ? $refund_fee_quantity : 0;
            return array("refund_quantity" => $locked_remaining_quantity, "escrow_return" => $escrow_return, "refund_fee_quantity" => $refund_fee_quantity, "refund_fee_currency" => $refund_fee_currency);
        }
    }

    public function escrow_refund_advertisement_order($advertisement, $order, $username)
    {
        // only for buy ads/sell order
        // seller is order placer
        $db = $this->db;

        $advertisement_id = $advertisement["id"];
        $order_id = $order["order_id"];
        $order_quantity = $order["quantity"];

        $crypto_currency = $advertisement["crypto_currency"];

        $escrow_return = $this->escrow_fund_out($advertisement_id, $order_id, $username, $order_quantity, $crypto_currency);
        return $escrow_return;
    }

    private function get_advertisement_expiration($date, $type)
    {
        global $setting;

        if ($type == "payment") {
            $marketplaceBuyerTransactionExpiration = $setting->systemSetting["marketplaceBuyerTransactionExpiration"];

            $buyer_transfer_expiration = "$marketplaceBuyerTransactionExpiration minutes";
            $expires_at = date("Y-m-d H:i:s", strtotime("+$buyer_transfer_expiration", strtotime($date)));
        } else if ($type == "escrow") {
            $marketplaceSellerTransactionExpiration = $setting->systemSetting["marketplaceSellerTransactionExpiration"];

            $seller_transfer_expiration = "$marketplaceSellerTransactionExpiration minutes";
            $expires_at = date("Y-m-d H:i:s", strtotime("+$seller_transfer_expiration", strtotime($date)));
        } else if ($type == "advertisement") {
            $advertisement_expiration_length = $setting->systemSetting["marketplaceAdvertisementExpiration"];
            $expires_at = date("Y-m-d H:i:s", strtotime($advertisement_expiration_length, strtotime($date)));
        }

        return $expires_at;

    }

    public function is_below_advertisement_minimum($advertisement, $quantity)
    {
        // check if current locked quantity is > min
        global $setting, $xunCurrency;
        $db = $this->db;

        $min = $advertisement["min"];

        if ($advertisement["is_cryptocurrency"]) {
            $currency = $advertisement["currency"];
            $crypto_currency = $advertisement["crypto_currency"];

            $cryptocurrency_rate = $xunCurrency->get_rate($crypto_currency, $currency);

            if ($advertisement["type"] == "sell") {
                $min = $min / $cryptocurrency_rate;
                $remaining_amount = $quantity;
            } else {
                $remaining_amount = $quantity / $cryptocurrency_rate;
                $remaining_amount = $setting->setDecimal($remaining_amount);
            }
        } else {
            if ($advertisement["type"] == "sell") {
                $min_currency = $advertisement["min"];

                $price_in_currency = $this->get_advertisement_price($advertisement);
                $remaining_amount = $quantity * $price_in_currency;
            } else {
                $remaining_amount = $quantity;
            }
        }

        $is_sold_out = $remaining_amount < $min ? true : false;
        return $is_sold_out;
    }

    private function is_advertisement_sold_out($advertisement)
    {
        // get order count
        // check if locked remaining < min

        $advertisement_max_order = $advertisement["max_processing_orders"];

        if ($advertisement_max_order != 0) {
            $current_processing_orders = $this->get_advertisement_orders_count($advertisement);
            if ($current_processing_orders >= $advertisement_max_order) {
                return true;
            }
        }

        $locked_remaining_quantity = $this->get_advertisement_locked_remaining_quantity($advertisement);
        $is_sold_out = $this->is_below_advertisement_minimum($advertisement, $locked_remaining_quantity);

        return $is_sold_out;
    }

    public function test_order_count($params)
    {
        $db = $this->db;
        $ad_id = $params["advertisement_id"];
        $db->where("id", $ad_id);
        $advertisement = $db->getOne("xun_marketplace_advertisement");
        // $count = $this->get_advertisement_orders_count($advertisement);
        $sold_out = $this->is_advertisement_sold_out($advertisement);
        return array("count" => $count, "sold_out" => $sold_out);
    }

    public function update_expired_advertisement_order($advertisement, $advertisement_order, $advertisement_order_table, $buyer_username, $buyer_nickname, $seller_username, $seller_nickname)
    {
        /**
         * cancel order - buyer
         * paid - buyer
         *
         * buy ad/sell order
         * -    owner = buyer
         * -    refund
         *
         * sell ad/buy order
         * -    buyer = orderer
         * -    refund if advertisement is cancelled or expired
         *
         */

        global $xunXmpp;

        $db = $this->db;
        $general = $this->general;

        $date = date("Y-m-d H:i:s");
        $new_status = "expired";

        $update_data = [];
        $update_data["updated_at"] = $date;
        $update_data["status"] = $new_status;

        $db->where("id", $advertisement_order["id"]);
        $db->update($advertisement_order_table, $update_data);

        $new_advertisement_order = [];
        $new_advertisement_order["order_id"] = $advertisement_order["order_id"];
        $new_advertisement_order["status"] = $new_status;
        $new_advertisement_order["updated_at"] = $date;

        $this->update_advertisement_order_cache_data($new_advertisement_order);

        // TODO:
        // refund trading fee
        // ## check if refund advertisement already refund trading fee
        $updated_advertisement_order = array_merge($advertisement_order, $new_advertisement_order);
        $owner_username = $advertisement["type"] == "buy" ? $buyer_username : $seller_username;
        $this->process_closed_advertisement_order($advertisement, $updated_advertisement_order, $advertisement_order_table, $owner_username, $seller_username);

        $is_sold_out = $this->is_advertisement_sold_out($advertisement);

        if (!$is_sold_out) {
            $update_data = [];
            $update_data["sold_out"] = 0;
            $update_data["updated_at"] = $date;

            $db->where("id", $advertisement["id"]);
            $db->update("xun_marketplace_advertisement", $update_data);
        }

        // send xmpp message
        $owner_username = $advertisement["type"] == "sell" ? $seller_username : $buyer_username;
        $owner_nickname = $advertisement["type"] == "sell" ? $seller_nickname : $buyer_nickname;

        $xmpp_recipients = [$seller_username, $buyer_username];

        $marketplace_chat_room_host = $xunXmpp->get_marketplace_host();

        $erlang_params = array(
            "chatroom_id" => (string) $advertisement_order["order_id"],
            "chatroom_host" => $marketplace_chat_room_host,
            "recipients" => $xmpp_recipients,
            "type" => $new_status,
            "data" => array(
                "advertisement_id" => (string) $advertisement["id"],
                "order_id" => (string) $advertisement_order["order_id"],
                "username" => "",
                "nickname" => "",
                "owner_username" => $owner_username,
                "owner_nickname" => $owner_nickname,
                "seller_username" => $seller_username,
                "seller_nickname" => $seller_nickname,
                "buyer_username" => $buyer_username,
                "buyer_nickname" => $buyer_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "created_at" => $general->formatDateTimeToIsoFormat($date),
            ),
        );

        $res = $xunXmpp->send_xmpp_marketplace_event($erlang_params);
    }

    private function update_user_trade_count($user_id)
    {
        $db = $this->db;

        $db->where("user_id", $user_id);
        $user_marketplace = $db->getOne("xun_marketplace_user");
        $date = date("Y-m-d H:i:s");

        if ($user_marketplace) {
            $update_data = [];
            $update_data["total_trade"] = $db->inc(1);
            $update_data["updated_at"] = $date;

            $db->where("id", $user_marketplace["id"]);
            $db->update("xun_marketplace_user", $update_data);
        } else {
            $insert_data = array(
                "user_id" => $user_id,
                "total_trade" => 1,
                "created_at" => $date,
                "updated_at" => $date,
            );

            $db->insert("xun_marketplace_user", $insert_data);
        }
    }

    public function get_order_fund_out_amount($advertisement, $advertisement_order_quantity)
    {
        global $setting;

        $fund_out_trading_fee = false;
        $advertisement_order_quantity = (string) $advertisement_order_quantity;
        $advertisement_quantity = (string) $advertisement["quantity"];
        $tnc_trading_fee = false;
        // $tnc_trading_fee = $advertisement["fee_type"] == "thenuxcoin" ? true : false;

        $fee_pct_setting = $tnc_trading_fee ? "marketplaceTNCTradingFee" : "marketplaceTradingFee";
        $fee_pct = $setting->systemSetting[$fee_pct_setting];
        $fee_pct = (string) bcdiv((string) $fee_pct, "100", 8);

        $trading_fee_currency = $advertisement["fee_type"];

        /**
         * buy - crypto
         * sell - fiat - crypto, c2c - currency
         */
        // if ($advertisement["type"] == "buy"){
        //     $decimal_place_setting = $this->

        // }
        // find a way to get the decimal place setting
        if(isset($this->order_decimal_place_setting)){
            $decimal_places = $this->order_decimal_place_setting["decimal_places"];
        }else{
            $decimal_places = 8;
        }

        if (!$tnc_trading_fee && $advertisement["type"] == "buy") {
            if (bccomp($fee_pct, "0", 8) >= 1) {
                $fund_out_pct = bcsub("1", $fee_pct, 8);
                $fund_out_quantity = bcmul($advertisement_order_quantity, (string) $fund_out_pct, $decimal_places);
                // $fund_out_quantity = $setting->setDecimal($fund_out_quantity);

                $fund_out_trading_fee = true;
                $trading_fee_quantity = bcsub($advertisement_order_quantity, (string) $fund_out_quantity, $decimal_places);
            } else {
                $fund_out_quantity = $advertisement_order_quantity;
            }
        } else {
            $fund_out_quantity = $advertisement_order_quantity;
            if (bccomp($fee_pct, "0", 8) >= 1) {
                $fund_out_trading_fee = true;

                if (!$tnc_trading_fee) {
                    $trading_fee_quantity = bcmul($fee_pct, $advertisement_order_quantity, $decimal_places);
                } else {
                    $order_percentage = bcdiv($advertisement_order_quantity, $advertisement_quantity, 20);

                    $trading_fee_quantity = bcmul((string) $order_percentage, (string) $advertisement["fee_quantity"], 2);

                }
            }
        }

        return array(
            "fund_out_trading_fee" => $fund_out_trading_fee,
            "fund_out_quantity" => $fund_out_quantity,
            "trading_fee_currency" => $trading_fee_currency,
            "trading_fee_quantity" => $trading_fee_quantity,
        );
    }

    public function send_report_ticket($params)
    {
        global $setting, $ticket;

        $username = $params["username"];
        $order_id = $params["advertisement_order_id"];
        $advertisement_id = $params["advertisement_id"];
        $report_type = $params["type"];
        $report_description = $params["description"];
        $reported_username = $params["reported_username"];
        $reported_nickname = $params["reported_nickname"];
        $nickname = $params["nickname"];

        $clientName = $nickname;
        $clientEmail = $setting->systemSetting["systemEmailAddress"];

        $subject = "TheNux xchange: User Report. Order ID: ${order_id}";
        $content = "Advertisement ID: ${advertisement_id}\n";
        $content .= "Order ID: ${order_id} \n";
        $content .= "Reported By: \n";
        $content .= "&nbsp;&nbsp;Username: ${username}\n";
        $content .= "&nbsp;&nbsp;Nickname: ${nickname}\n";
        $content .= "User reported: \n";
        $content .= "&nbsp;&nbsp;Username: ${reported_username}\n";
        $content .= "&nbsp;&nbsp;Nickname: ${reported_nickname}\n";
        $content .= "Report Type: ${report_type}\n";
        $content .= "Report Description: ${report_description}";

        $ticket_params = array(
            'clientID' => '',
            'clientName' => $clientName,
            'clientEmail' => $clientEmail,
            'clientPhone' => $username,
            'status' => "open",
            'priority' => 1,
            'type' => "incident",
            'subject' => $subject,
            'department' => "customerService",
            'reminderDate' => "",
            'assigneeID' => "",
            'assigneeName' => "",
            'creatorID' => '',
            'internal' => 1,
            'content' => $content,
        );

        $res = $ticket->addTicket($ticket_params);
        return $res;
    }

    private function get_buyer_seller_info($advertisement, $advertisement_order = null, $owner_user = null, $order_user = null)
    {
        $db = $this->db;

        if (!$owner_user) {
            $owner_user_id = $advertisement["user_id"];
        }

        if (!$order_user) {
            $order_id = $advertisement_order["order_id"];

            $db->where("user_id", $order_id);
            $order_user_id = $db->getOne("xun_marketplace_advertisement_order_cache", "user_id");
        }

        if (!$owner_user_id && !$order_user) {
            $db->where("id", [$owner_user_id, $order_user_id], "in");
            $xun_users = $db->map("id")->ObjectBuilder()->get("xun_user", null, "id, username, nickname");
            $owner_user_obj = $xun_users[$owner_user_id];
            $order_user_obj = $xun_users[$order_user_id];

            $owner_user = (array) $owner_user_obj;
            $order_user = (array) $order_user_obj;
        } else if (!$owner_user) {
            $db->where("id", $owner_user_id);
            $owner_user = $db->getOne("xun_user", "id, username, nickname");
        } else if (!$order_user) {
            $db->where("id", $order_user_id);
            $order_user = $db->getOne("xun_user", "id, username, nickname");
        }

        if ($advertisement["type"] == "buy") {
            $buyer_user = $owner_user;
            $seller_user = $order_user;
        } else {
            $buyer_user = $order_user;
            $seller_user = $owner_user;
        }

        return array("owner_user" => $owner_user, "buyer_user" => $buyer_user, "seller_user" => $seller_user);

        // $seller_user = $xun_users[$seller_user_id];
        // $seller_username = $seller_user->username;
        // $seller_nickname = $seller_user->nickname;

        // $buyer_user = $xun_users[$buyer_user_id];
        // $buyer_username = $buyer_user->username;;
        // $buyer_nickname = $buyer_user->nickname;
    }

    private function handle_xmpp_event($advertisement, $advertisement_order)
    {
        $db = $this->db;
        $db->where("id", $advertisement["user_id"]);
        $owner_user = $db->getOne("xun_user");
        $owner_username = $owner_user["username"];
        $owner_nickname = $owner_user["nickname"];

        $user_nickname = $xun_user["nickname"];

        if ($advertisement["type"] == "sell") {
            $seller_username = $owner_username;
            $seller_nickname = $owner_nickname;
            $buyer_username = $username;
            $buyer_nickname = $user_nickname;
        } else {
            $buyer_username = $owner_username;
            $buyer_nickname = $owner_nickname;
            $seller_username = $username;
            $seller_nickname = $user_nickname;
        }
        $xmpp_recipients = [$owner_username, $username];

        $erlang_params = array(
            "chatroom_id" => (string) $order_id,
            "chatroom_host" => $marketplace_chat_room_host,
            "recipients" => $xmpp_recipients,
            "type" => "new_order",
            "data" => array(
                "advertisement_id" => (string) $advertisement_id,
                "order_id" => (string) $order_id,
                "username" => $username,
                "nickname" => $user_nickname,
                "owner_username" => $owner_username,
                "owner_nickname" => $owner_nickname,
                "seller_username" => $seller_username,
                "seller_nickname" => $seller_nickname,
                "buyer_username" => $buyer_username,
                "buyer_nickname" => $buyer_nickname,
                "crypto_currency" => $advertisement["crypto_currency"],
                "created_at" => $general->formatDateTimeToIsoFormat($date),
            ),
        );
        $xunXmpp->send_xmpp_marketplace_event($erlang_params);
    }

    private function fund_out_trading_fee($advertisement, $fund_out_result)
    {
        global $setting;
        $db = $this->db;
        /**
         * buys ads, pay with !tnc
         * -    deduct from seller's amount
         *
         * others
         * -    get percentage of sale, x 1%/0.5%
         * -    1 btc -> 0.005 btc (0.05 tnc)
         * -    order = 0.1btc
         */

        $trading_fee_quantity = $fund_out_result["trading_fee_quantity"];
        if ($fund_out_result["fund_out_trading_fee"] && $trading_fee_quantity > 0) {
            $trading_fee_order_id = $this->get_order_no();
            $trading_fee_currency = $fund_out_result["trading_fee_currency"];

            $insert_data = array(
                "advertisement_id" => $advertisement["id"],
                "order_id" => $trading_fee_order_id,
                "order_type" => "order_trading_fee",
                "user_id" => $advertisement["user_id"],
                "type" => $advertisement["type"],
                "price" => '',
                "quantity" => $trading_fee_quantity,
                "currency" => $trading_fee_currency,
                "status" => "pending_escrow_fund_out",
                // "reference_order_id" => "",
                "expires_at" => '',
                "order_no" => $trading_fee_order_id,
                "disabled" => 0,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $row_id = $db->insert($advertisement_order_table, $insert_data);

            $trading_fee_wallet_address = $setting->systemSetting["marketplaceTradingFeeWalletAddress"];

            // echo "## fund_out_trading_fee";
            if ($trading_fee_wallet_address != '') {
                // fund out trading fee
                $trading_fee_escrow_return = $this->escrow_fund_out($advertisement["id"], $trading_fee_order_id, null, $trading_fee_quantity, $trading_fee_currency, $trading_fee_wallet_address);
            }
        }
    }

    public function get_trading_fee($advertisement_type, $trading_fee_currency, $quantity, $trading_fee_crypto_rate, $crypto_decimal_places = 8)
    {
        global $setting;
        $has_trading_fee = true;
        $crypto_decimal_places = (int) $crypto_decimal_places;
        // $fee_pct_setting = $trading_fee_currency == "thenuxcoin" ? "marketplaceTNCTradingFee" : "marketplaceTradingFee";
        $fee_pct_setting = "marketplaceTradingFee";

        $fee_pct = $setting->systemSetting[$fee_pct_setting];
        $fee_pct = bcdiv((string) $fee_pct, "100", 8);

        if (bccomp((string) $fee_pct, '0', 8) == 0) {
            $fee_quantity = '0';
            $has_trading_fee = false;
        } else {
            // if($trading_fee_currency == "thenuxcoin"){
            //     $fee_cur = bcmul((string)$quantity, (string)$fee_pct, 20);
            //     $tnc_rate = $this->get_cryptocurrency_rate("thenuxcoin");

            //     $tnc_crypto_rate = bcdiv((string)$trading_fee_crypto_rate, (string)$tnc_rate, 8);

            //     $fee_quantity = bcmul((string)$fee_cur, (string)$tnc_crypto_rate, 8);
            // }else{
            if ($advertisement_type == "buy") {
                $fee_quantity = '0';
                $has_trading_fee = false;
            } else {
                $fee_quantity = bcmul((string) $quantity, (string) $fee_pct, $crypto_decimal_places);
            }
            // }
        }

        $retArr = array("fee_quantity" => (string) $fee_quantity, "tnc_rate" => $tnc_crypto_rate, "fee_currency" => $fee_cur, "has_trading_fee" => $has_trading_fee);

        // print_r($retArr);
        return $retArr;
    }

    public function test_upline_trading_fee_quantity($params)
    {
        return $this->calculate_master_upline_trading_fee_quantity($params);
    }

    public function calculate_upline_trading_fee_quantity($params, $decimal_place_setting = null)
    {
        global $setting, $xunTree, $xunCrypto;
        $db = $this->db;

        $trading_fee_quantity = $params["quantity"];
        // $user_id = $params["user_id"];
        // $upline_id = $xunTree->getSponsorUplineIDByUserID($user_id);

        if($decimal_place_setting){
            $decimal_places = $decimal_place_setting["decimal_places"];
        }else{
            $decimal_places = 8;
        }

        // $xun_user_service = new XunUserService($db);

        // if (!$upline_id) {
        $company_pool_pct = 100;
        $upline_pct = 0;
        // } else {
        //     // check if upline is eligible for bonus trading fee

        //     $upline_address_data = $xun_user_service->getActiveInternalAddressByUserID($upline_id, "id, user_id, address");

        //     if ($upline_address_data){
        //         $upline_address = $upline_address_data["address"];
        //         $upline_wallet_balance = $xunCrypto->get_wallet_balance($upline_address, "thenuxcoin");

        //         $upline_wallet_tnc_min = $setting->systemSetting["tradingFeeUplineBonusTNCAmount"];

        //         if (bccomp((string)$upline_wallet_balance, (string)$upline_wallet_tnc_min, 8) >= 0){
        //             $upline_pct = $setting->systemSetting["tradingFeeUplineBonusPercentage"];
        //         }else{
        //             $upline_pct = $setting->systemSetting["tradingFeeUplinePercentage"];
        //         }
        //     }

        //     $company_pool_pct = bcsub("100", (string) $upline_pct);
        // }

        $company_pool_pct = bcdiv((string) $company_pool_pct, "100", 8);
        $upline_pct = bcdiv((string) $upline_pct, "100", 8);

        $upline_quantity = bcmul((string) $upline_pct, $trading_fee_quantity, $decimal_places);

        $company_pool_quantity = bcsub((string) $trading_fee_quantity, (string) $upline_quantity, $decimal_places);

        return array("upline_id" => $upline_id, "upline_quantity" => $upline_quantity, "company_pool_quantity" => $company_pool_quantity);

    }

    public function calculate_master_upline_trading_fee_quantity($params, $decimal_place_setting)
    {
        global $setting, $xunTree;
        $db = $this->db;

        $trading_fee_quantity = $params["quantity"];
        // $user_id = $params["user_id"]; // advertisement owner
        // $master_upline_id = $xunTree->getSponsorMasterUplineIDByUserID($user_id);

        // if (!$master_upline_id) {
        $company_acc_pct = 100;
            // $master_upline_pct = 0;
        // } else {
        //     $company_acc_pct = $setting->systemSetting["tradingFeeCompanyAccPercentage"];
        //     // $master_upline_pct = bcsub("100", (string)$company_acc_pct);
        // }

        if($decimal_place_setting){
            $decimal_places = $decimal_place_setting["decimal_places"];
        }else{
            $decimal_places = 8;
        }

        $company_acc_pct = bcdiv((string) $company_acc_pct, "100", 8);
        $master_upline_pct = bcsub("1", (string) $company_acc_pct, 8);

        $master_upline_quantity = bcmul((string) $master_upline_pct, $trading_fee_quantity, $decimal_places);
        $company_acc_quantity = bcsub((string) $trading_fee_quantity, (string) $master_upline_quantity, $decimal_places);

        return array("master_upline_id" => $master_upline_id, "master_upline_quantity" => $master_upline_quantity, "company_acc_quantity" => $company_acc_quantity);

    }

    public function insert_referral_transaction($upline_user_id, $advertisement_order, $trading_fee_quantity, $master_upline = null, $service_charged_user_id)
    {
        $db = $this->db;

        $master_upline = $master_upline ? $master_upline : 0;
        $db->where("advertisement_id", $advertisement_order["advertisement_id"]);
        $db->where("advertisement_order_id", $advertisement_order["reference_order_id"]);
        $db->where("master_upline", $master_upline);
        $referral_upline = $db->getOne("xun_referral_transaction", "id");

        if ($referral_upline) {
            return $referral_upline["id"];
        }

        $insert_data = array(
            "user_id" => $upline_user_id,
            "service_charged_user_id" => $service_charged_user_id,
            "advertisement_id" => $advertisement_order["advertisement_id"],
            "advertisement_order_id" => $advertisement_order["reference_order_id"],
            "quantity" => $trading_fee_quantity,
            "crypto_currency" => $advertisement_order["currency"],
            "master_upline" => $master_upline,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $row_id = $db->insert("xun_referral_transaction", $insert_data);

        if (!$row_id) {
            // print_r($db);
        }

        return $row_id;
    }

    /*

    if ($advertisement["type"] == "buy") {
    $return_data = array(
    "advertisement_id" => $advertisement_id,
    "remaining_quantity" => $effective_remaining_quantity,
    );

    // TODO: refund trading fee if ad is cancelled || expired
    // pass advertisement, order_id, seller_username
    $this->escrow_refund_advertisement_order($advertisement, $advertisement_order, $seller_username);

    // get_order_fund_out_amount
    } else {
    if ($advertisement["type"] == "cancelled" || $advertisement["type"] == "expired") {
    // refund
    $this->escrow_refund_advertisement_order($advertisement, $advertisement_order, $seller_username);
    }
    }
     */
    public function process_closed_advertisement_order($advertisement, $advertisement_order, $advertisement_order_transaction_table, $owner_username, $seller_username)
    {
        //  this function is to perform fund out when an advertisement order is cancelled or expired.

        $db = $this->db;
        if ($advertisement["type"] == "buy") {
            // TODO: refund trading fee if ad is cancelled || expired
            // pass advertisement, order_id, seller_username
            $refund_order_bool = true;
            $refund_trading_fee_bool = false;
            // $this->escrow_refund_advertisement_order($advertisement, $advertisement_order, $seller_username);
            //  check if this order has trading fee
            //  if fee_quantity > 0

            // refund trading fee only if the ad is closed
            //  buy - none
            //  sell - both

            // if (in_array($advertisement["status"], ["expired", "cancelled"]) && $advertisement["fee_type"] == "thenuxcoin"){
            //     // refund trading fee
            //     $refund_trading_fee_bool = true;
            // }
        } else {
            if (in_array($advertisement["status"], ["expired", "cancelled"])) {
                // refund
                $refund_order_bool = true;
                $refund_trading_fee_bool = true;
            }
        }
        if ($refund_order_bool) {
            $refund_order_id = $this->get_order_no();

            $insert_data = array(
                "advertisement_id" => $advertisement["id"],
                "order_id" => $refund_order_id,
                "order_type" => "refund_order",
                "user_id" => $advertisement["type"] == "sell" ? $advertisement["user_id"] : $advertisement_order["user_id"],
                "type" => $advertisement_order["type"],
                "price" => $advertisement_order["price"],
                "quantity" => $advertisement_order["quantity"],
                "currency" => $advertisement["crypto_currency"],
                "status" => "refund",
                "reference_order_id" => $advertisement_order["order_id"],
                "expires_at" => '',
                "order_no" => $refund_order_id,
                "disabled" => 0,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $row_id = $db->insert($advertisement_order_transaction_table, $insert_data);

            $this->escrow_refund_advertisement_order($advertisement, $insert_data, $seller_username);
        }

        if ($refund_trading_fee_bool) {
            // insert record
            // perform escrow fund out
            if (bccomp((string) $advertisement["fee_quantity"], "0", 8) > 0) {
                // TODO: this->order_decimal_places
                $fund_out_result = $this->get_order_fund_out_amount($advertisement, $advertisement_order["quantity"]);
                $trading_fee_quantity = $fund_out_result["trading_fee_quantity"];
                $trading_fee_currency = $fund_out_result["trading_fee_currency"];

                // print_r($fund_out_result);
                //  insert refund trading fee record
                $refund_fee_order_id = $this->get_order_no();

                $insert_data = array(
                    "advertisement_id" => $advertisement["id"],
                    "order_id" => $refund_fee_order_id,
                    "order_type" => "refund_trading_fee",
                    "user_id" => $advertisement["user_id"],
                    "type" => $advertisement["type"],
                    "price" => $advertisement["price"],
                    "quantity" => $trading_fee_quantity,
                    "currency" => $trading_fee_currency,
                    "status" => "refund",
                    "reference_order_id" => $advertisement_order["order_id"],
                    "expires_at" => '',
                    "order_no" => $refund_fee_order_id,
                    "disabled" => 0,
                    "created_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s"),
                );

                $row_id = $db->insert($advertisement_order_transaction_table, $insert_data);

                // $refund_trading_fee_escrow_return = $this->escrow_fund_out($advertisement["id"], $refund_fee_order_id, $username, $refund_fee_quantity, $refund_fee_currency);
                $res = $this->escrow_fund_out($advertisement["id"], $refund_fee_order_id, $owner_username, $trading_fee_quantity, $trading_fee_currency);
                // print_r($res);
            }
        }
    }

    private function get_ads_order_min_max_cryptocurrency($advertisement, $advertisement_order, $remaining_quantity = null){

        global $xunCurrency, $setting;

        $min = $advertisement["min"];
        $max_order = $advertisement["max"];
        $crypto_currency = $advertisement["crypto_currency"];
        $currency = $advertisement["currency"];

        $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency, true);

        if($advertisement["is_cryptocurrency"] === 0){
            $supported_currencies = $xunCurrency->get_marketplace_currencies();
            $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);
            $currency_rate = $full_currency_list[$currency];
        }

        $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency, true);
        $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($crypto_currency, true);

        $currency_decimal_places = $currency_decimal_place_setting["decimal_places"];
        $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];

        $crypto_decimal_places = $crypto_decimal_place_setting["decimal_places"];
        $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

        if($remaining_quantity == null){
            $remaining_quantity = $this->get_advertisement_effective_remaining_quantity($advertisement);
        }
        
        $max_order_quantity = (bccomp((string) $max_order, (string) $remaining_quantity, 8) > 0 || bccomp((string) $max_order, "0", 8) == 0) ? $remaining_quantity : $max_order;
        $c2c_rate = $xunCurrency->get_rate($crypto_currency, $currency);

        if ($advertisement["price_type"] == "fix"){
            if($advertisement["type"] == "sell"){
                $min_limit = bcdiv((string) $min, (string) $advertisement["price"], $crypto_decimal_places);
            }else if ($advertisement["type"] == "buy"){
                $min_limit = bcmul((string) $min, (string) $advertisement["price"], $currency_decimal_places);
            }
        }else if($advertisement["price_type"] == "floating"){
            if ($advertisement["is_cryptocurrency"] == 0 && $advertisement["type"] == "sell"){
                $price = $advertisement_order["price"] / $currency_rate;
                $min_limit = bcdiv((string) $min, (string) $price, $crypto_decimal_places);
            }else if ($advertisement["is_cryptocurrency"] == 1 && $advertisement["type"] == "buy"){
                $min_limit = bcmul((string) $min, (string) $c2c_rate, $crypto_decimal_places);
            }else{
                $min_limit = bcdiv((string) $min, (string) $advertisement_order["price"], $crypto_decimal_places);
            }
        }

        if ($advertisement["type"] == "sell"){
            $advertisement["min"] = $min_limit . " " . ucfirst($advertisement["crypto_currency"]);
            $advertisement["max"] = $max_order_quantity . " " . ucfirst($advertisement["crypto_currency"]);
        }else if($advertisement["type"] == "buy" && $advertisement["is_cryptocurrency"] == 1){
            $advertisement["min"] = $min_limit . " " . ucfirst($advertisement["currency"]);
            $advertisement["max"] = $max_order_quantity . " " . ucfirst($advertisement["currency"]);
        }else{
            $advertisement["min"] = $min . " " . ucfirst($advertisement["crypto_currency"]);
            $advertisement["max"] = $max_order_quantity . " " . ucfirst($advertisement["crypto_currency"]);
        }
        return $advertisement;
    }

    private function order_price_convert ($advertisement, $advertisement_order, $order_price)
    {
        global $xunCurrency, $setting;

        $currency = $advertisement["currency"];

        $currency_decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency, true);

        $currency_decimal_places = $currency_decimal_place_setting["decimal_places"];
        $currency_dp_credit_type = $currency_decimal_place_setting["credit_type"];

        if($advertisement["is_cryptocurrency"] === 0){
            $supported_currencies = $xunCurrency->get_marketplace_currencies();
            $full_currency_list = $xunCurrency->get_all_currency_rate($supported_currencies);
            $currency_rate = $full_currency_list[$currency];
            if($advertisement["price_type"] == "floating"){
                $order_price = bcdiv((string) $order_price, (string) $currency_rate, $currency_decimal_places);
            }
        }

        $order_price = $order_price . " " . ucfirst($currency);

        return $order_price;
    }
    

}
