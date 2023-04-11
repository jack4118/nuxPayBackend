<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/

class XunPay
{

    public function __construct($db, $setting, $general, $account)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
	    $this->account = $account;
    }

    public function get_product_type_listing($params)
    {
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $columns = "id, type, name, image_url, image_md5";
        // $xun_pay_service = new XunPayService($db);

        // $xun_pay_product_type = $xun_pay_service->getActivePayProductType($columns);
        $db->where("id", [1, 2, 3], "in");
        $db->where("status", 1);
        $xun_pay_product_type = $db->get("xun_pay_product_type", null, $columns);
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['B00108'][$language] /*Product type listing. */, "data" => $xun_pay_product_type);
    }

    public function get_product_listing($params)
    {
        global $country;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $username = trim($params["username"]);
        $type = trim($params["type"]);
        $country_code = $params["country_code"];
        $type_id_arr = $params["type_id"];
        $name = trim($params["name"]);

        $page = trim($params["page"]);
        $page_limit = $setting->systemSetting["appsPageLimit"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = strtoupper(trim($params["order"]));
        $order = ($order == 'ASCENDING' ? "ASC" : ($order == 'DESCENDING' ? "DESC" : "ASC"));

        if ($username == '') {
        return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        $type_is_array = 0;
        if (isset($type_id_arr)) {
            //  array of type_id
            if (!is_array($type_id_arr)) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00200'][$language] /*Something went wrong. Please try again.*/, "dev_msg" => "type_id must be an array");
            }

            $type_is_array = 1;
        } else if ($type == '') {
        return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00201'][$language]/*Type is required.*/);
        }

        $xunUserService = new XunUserService($db);

        $xunUser = $xunUserService->getUserByUsername($username);

        if (!$xunUser) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $translations['E00202'][$language] /*User does not exist.*/);
        }

        $userID = $xunUser["id"];

        $device_info_obj = new stdClass();
        $device_info_obj->username = $username;

        $xun_device_info = $xunUserService->getDeviceInfo($device_info_obj);

        $is_old_version = false;
        if ($xun_device_info) {
            $os = $xun_device_info["os"];
            $app_version = $xun_device_info["app_version"];
            $app_version = str_replace("(", ".", $app_version);
            $app_version = str_replace(")", "", $app_version);

            $min_android_version = '1.0.222.3';
            $min_ios_version = '1.0.160';

            if ($os == 1 && version_compare($min_android_version, $app_version) > 0) {
                $is_old_version = true;
            } else if ($os == 2 && version_compare($min_ios_version, $app_version) > 0) {
                $is_old_version = true;
            }
        }

        $date = date("Y-m-d H:i:s");

        $type_arr = [];
        if ($type_is_array == 0) {
            switch ($type) {
                case "topup":
                    $type_id = 1;
                    break;
                case "utility":
                    $type_id = 2;
                    break;

                case "giftcard":
                // $type_id = 3;
                // break;

                default:
                    // $type_id = 0;
                return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00203'][$language] /*Invalid product type.*/);
                    break;
            }
            $type_arr = [$type_id];
        } else {
            $type_arr = $type_id_arr;
        }

        if (is_array($country_code)) {
            $country_code_arr = array_map(function ($v) {
                return strtolower($v);
            }, $country_code);
        } else {
            $country_code_arr = [trim($params["country_code"])];
        }

        $product_params = [];
        $product_params["type_arr"] = $type_arr;
        $product_params["name"] = $name;
        $product_params["country_iso_code2"] = $country_code_arr;
        $product_params["page"] = $page;
        $product_params["page_size"] = $page_size;
        $product_params["order"] = $order;
        $product_params["is_old_version"] = $is_old_version;

        $xun_pay_service = new XunPayService($db);

        $product_return_data = $xun_pay_service->getProductList($product_params, "id, name, image_url, image_md5, type as type_id, country_iso_code2 as country_code");

        $product_arr = $product_return_data["data"];

        if (!empty($product_arr)) {
            $product_id_arr = array_column($product_arr, "id");
            $product_type_obj = new stdClass();
            $product_type_obj->productIdArr = $product_id_arr;
            $product_type_map = $xun_pay_service->getProductTypeMap($product_type_obj);

            $product_type_id_arr = [];
            for ($i = 0; $i < count($product_type_map); $i++) {
                $product_type_data = $product_type_map[$i];
                $data_product_id = $product_type_data["product_id"];
                $data_type_id = $product_type_data["type_id"];

                $product_type_id_arr[$data_product_id][] = $data_type_id;
            }

            for ($i = 0; $i < count($product_arr); $i++) {
                $product_data = $product_arr[$i];
                $product_id = $product_data["id"];
                $product_type_id = $product_type_id_arr[$product_id];
                sort($product_type_id);

                $product_data["type_ids"] = $product_type_id;
                $product_type = $product_data["type_id"];
                $product_data["type_id"] = $product_type == 0 ? $product_type_id[0] : $product_type;

                $product_arr[$i] = $product_data;
            }
        }
        $page_details = $product_return_data["page_details"];

        $return_data = [];
        $return_data["products"] = $product_arr;
        $return_data["total_record"] = $page_details["total_record"];
        $return_data["num_record"] = $page_details["num_record"];
        $return_data["total_page"] = $page_details["total_page"];
        $return_data["page_number"] = $page_details["page_number"];

        if ($type_is_array === 0) {
            $country_iso_code2_arr = $this->get_product_country_by_type($type_arr);

            $country_params = array("iso_code2_arr" => $country_iso_code2_arr);
            $country_data = $country->getCountryDataByIsoCode2($country_params);
            $return_data["countries"] = $this->get_country_info($country_data);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['B00109'][$language] /*Product listing.*/, "data" => $return_data);
    }

    public function get_product_details($params)
    {
        global $country, $post;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();
        
        $username = trim($params["username"]);
        $product_id = trim($params["id"]);

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00199'][$language]/*Username is required.*/);
        }

        if ($product_id == '') {
        return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00204'][$language] /*Product ID is required.*/);
        }

        $xunUserService = new XunUserService($db);

        $xunUser = $xunUserService->getUserByUsername($username);

        if (!$xunUser) {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00202'][$language] /*User does not exist.*/);
        }

        $userID = $xunUser["id"];

        $date = date("Y-m-d H:i:s");

        $xun_pay_service = new XunPayService($db);

        $product_obj = new stdClass();
        $product_obj->id = $product_id;

        $product_data = $xun_pay_service->getActiveProductById($product_obj, "id, name, description, image_url, image_md5, account_type, country_iso_code2, provider_id, product_code, currency_code, input_type");

        if (!$product_data) {
        return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00205'][$language] /*Invalid product.*/);
        }

        // $product_option = $this->get_product_option_by_product_id($product_id);

        $provider_id = $product_data["provider_id"];
        $product_code = $product_data["product_code"];
        $country_iso_code2 = $product_data["country_iso_code2"];
        $currency_code = $product_data["currency_code"];
        $input_type_json = $product_data["input_type"];
        $input_type = json_decode($input_type_json, 1);

        // $product_type = $product_data["type"];
        $product_type_obj = new stdClass();
        $product_type_obj->productIdArr = [$product_id];
        $product_type_map = $xun_pay_service->getProductTypeMap($product_type_obj);

        $product_type_id_arr = array_column($product_type_map, "type_id");
        sort($product_type_id_arr);

        $product_type = $product_type_id_arr[0];

        $system_currency = $this->get_product_provider_account_currency($provider_id);

        $price_details = [];
        $reloadly = new reloadly($db, $setting, $post);

        $country_params = array("iso_code2_arr" => [$country_iso_code2]);
        $country_data_arr = $country->getCountryDataByIsoCode2($country_params);
        $country_data = $country_data_arr[strtoupper($country_iso_code2)];
        // $product_currency = strtolower($country_data["currency_code"]);
        $product_currency = $currency_code;
        $exchange_rate_params = array(
            "product_currency" => $product_currency,
            "system_currency" => $system_currency,
        );
        $exchange_rate_return = $this->get_product_exchange_rate($exchange_rate_params);

        $exchange_rate_arr = $exchange_rate_return["exchange_rate_arr"];
        $cryptocurrency_arr = $exchange_rate_return["cryptocurrency_arr"];
        $xun_coins_arr = $exchange_rate_return["xun_coins_arr"];

        $product_amount_type = $input_type["amount"];
        $product_input_type = $this->map_input_type($input_type);

        $product_option = $this->get_product_option_by_product_id($product_id, $product_amount_type);

        if ($provider_id == 1) {
            if ($product_type == 1) {
                $price_details["type"] = "list";
                $price_list = [];
                $price_detail_list = [];
                for ($i = 0; $i < count($product_option); $i++) {
                    $price_amount = $product_option[$i]["amount"];
                    $price_amount = $setting->setDecimal($price_amount, "fiatCurrency");

                    $local_amount = $price_amount;
                    $price_list_data = array(
                        "local_price" => $local_amount,
                        "system_price" => $price_amount,
                    );
                    $price_list[] = $price_amount;
                    $price_detail_list[] = $price_list_data;
                }
                $price_details["price_list"] = $price_list;
                $price_details["price_detail_list"] = $price_detail_list;
            } else if ($product_type == 2) {
                $product_option_type = array_column($product_option, "amount_type");

                if (in_array("dropdown", $product_option_type)) {
                    $price_details["type"] = "list";
                    $product_system_rate = $exchange_rate_arr[$product_currency . "/" . $system_currency];

                    for ($i = 0; $i < count($product_option); $i++) {
                        $price_amount = $product_option[$i]["amount"];
                        $price_amount = $setting->setDecimal($price_amount, "fiatCurrency");

                        $system_amount = bcmul((string) $price_amount, (string) $product_system_rate, 2);

                        $price_list_data = array(
                            "local_price" => $price_amount,
                            "system_price" => $system_amount,
                        );
                        $price_list[] = $price_amount;
                        $price_detail_list[] = $price_list_data;
                    }
                    $price_details["type"] = "list";
                    $price_details["price_list"] = $price_list;
                    $price_details["price_detail_list"] = $price_detail_list;
                } else {
                    $price_details["type"] = "min_max";
                    for ($i = 0; $i < count($product_option); $i++) {
                        $product_option_data = $product_option[$i];
                        $price_amount = $product_option_data["amount"];
                        $price_amount = $setting->setDecimal($price_amount, "fiatCurrency");

                        // $local_amount = $price_amount;
                        $ex_rate = $exchange_rate_arr[$product_currency . '/myr'];
                        $local_amount = bcmul((string) $price_amount, (string) $ex_rate, 2);
                        $price_list_data = array(
                            "local_price" => $price_amount,
                            "system_price" => $local_amount,
                        );
                        if ($product_option_data["amount_type"] == "min") {
                            $price_details["min_price"] = $price_list_data;
                        } else if ($product_option_data["amount_type"] == "max") {
                            $price_details["max_price"] = $price_list_data;
                        }
                    }
                }
            }
        } else if ($provider_id == 2) {
            $price_details["type"] = "list";
            $price_list = [];
            $price_detail_list = [];
            // get fx rate from reloadly
            if ($country_iso_code2 == "my") {
                $reloadly_fx_rate = '1';
            } else {
                $reloadly_params = [];
                $reloadly_params["amount"] = 1;
                $reloadly_params["operatorId"] = $product_code;
                $reloadly_params["currencyCode"] = 'MYR';
                $reloadly_response = $reloadly->getFxRate($reloadly_params);
                $reloadly_fx_rate = $reloadly_response["fxRate"];
            }

            for ($i = 0; $i < count($product_option); $i++) {
                $product_option_data = $product_option[$i];
                if ($product_option_data["amount_type"] == "dropdown") {
                    $price_amount = $product_option_data["amount"];
                    $price_amount = $setting->setDecimal($price_amount, "fiatCurrency");

                    $local_amount = bcmul((string) $price_amount, (string) $reloadly_fx_rate, 2);
                    $price_list_data = array(
                        "local_price" => $local_amount,
                        "system_price" => $price_amount,
                    );
                    $price_list[] = $price_amount;
                    $price_detail_list[] = $price_list_data;
                }
            }
            $price_details["price_list"] = $price_list;
            $price_details["price_detail_list"] = $price_detail_list;
        } else if ($provider_id == 3) {
            $product_system_rate = $exchange_rate_arr[$product_currency . "/" . $system_currency];
            if ($product_amount_type == "dropdown") {
                $price_details["type"] = "list";

                for ($i = 0; $i < count($product_option); $i++) {
                    $price_amount = $product_option[$i]["amount"]; //  final price
                    $price_amount = $setting->setDecimal($price_amount, "fiatCurrency");

                    $sell_price = $product_option[$i]["sell_price"]; //  sell price/what user will receive
                    $sell_price = $setting->setDecimal($sell_price, "fiatCurrency");

                    $sp1 = bcmul((string) $price_amount, (string) $product_system_rate, 8);

                    $sp2 = ceil(bcmul((string) $sp1, '100', 8));
                    $system_amount = bcdiv((string) $sp2, '100', 2);

                    $price_list_data = array(
                        "local_price" => $sell_price,
                        "system_price" => $system_amount,
                    );
                    $price_list[] = $price_amount;
                    $price_detail_list[] = $price_list_data;
                }
                $price_details["type"] = "list";
                $price_details["price_list"] = $price_list;
                $price_details["price_detail_list"] = $price_detail_list;
            } elseif ($product_amount_type == "input") {
                $price_details["type"] = "min_max";
                $product_option_data = $product_option[0];

                $min_price = $product_option_data["min_price"];
                $max_price = $product_option_data["max_price"];
                $min_price = $setting->setDecimal($min_price, "fiatCurrency");
                $max_price = $setting->setDecimal($max_price, "fiatCurrency");

                $sp1 = bcmul((string) $min_price, (string) $product_system_rate, 8);
                $sp2 = ceil(bcmul((string) $sp1, '100', 8));
                $min_system_price = bcdiv((string) $sp2, '100', 2);

                $sp1 = bcmul((string) $max_price, (string) $product_system_rate, 8);
                $sp2 = ceil(bcmul((string) $sp1, '100', 8));
                $max_system_price = bcdiv((string) $sp2, '100', 2);

                $price_details["min_price"] = array(
                    "local_price" => $min_price,
                    "system_price" => $min_system_price,
                );

                $price_details["max_price"] = array(
                    "local_price" => $max_price,
                    "system_price" => $max_system_price,
                );
            }
        }

        $filtered_cryptocurrency_arr = $this->get_product_available_wallet_types($exchange_rate_arr, $cryptocurrency_arr,
            $xun_coins_arr, $price_details, $system_currency);

        $maintenance_start_time = $setting->systemSetting["bcMaintenanceStartTime"];
        $maintenance_end_time = $setting->systemSetting["bcMaintenanceEndTime"];

        $maintenance_coins = $setting->systemSetting["bcMaintenanceCoins"];

        $maintenance_coins_arr = explode(",", $maintenance_coins);

        $date = date("Y-m-d H:i:s");

        if($date >= $maintenance_start_time && $date <= $maintenance_end_time){
            $filtered_cryptocurrency_arr = array_diff($filtered_cryptocurrency_arr, $maintenance_coins_arr);
            $filtered_cryptocurrency_arr = array_values($filtered_cryptocurrency_arr);
        }
        $return_data = [];
        $return_data["id"] = $product_data["id"];
        $return_data["name"] = $product_data["name"];
        $return_data["description"] = $product_data["description"];
        $return_data["image_url"] = $product_data["image_url"];
        $return_data["image_md5"] = $product_data["image_md5"];
        $return_data["type_id"] = $product_type;
        $return_data["type_ids"] = $product_type_id_arr;
        $return_data["account_type"] = $product_data["account_type"];
        $return_data["input_type"] = $product_input_type;
        $return_data["country_code"] = strtolower($country_data["iso_code2"]);
        $return_data["currency"] = $product_currency;
        $return_data["system_price_currency"] = $system_currency;
        $return_data["price_details"] = $price_details;
        $return_data["exchange_rate"] = $exchange_rate_arr;
        $return_data["wallet_types"] = $filtered_cryptocurrency_arr;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['B00110'][$language] /*Product details.*/, "data" => $return_data);
    }

    public function get_signing_details($params)
    {
        global $xunCurrency, $country, $post, $setting, $xunCoins;
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        $account = $this->account;
        
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $product_id = trim($params["product_id"]);
        $amount_currency = trim($params["amount_currency"]);
        $local_amount = trim($params["local_amount"]);
        $wallet_type = trim($params["wallet_type"]);
        $account_number = trim($params["account_number"]);
        $phone_number = trim($params["phone_number"]);
        $email = trim($params["email"]);
        $quantity = trim($params["quantity"]);

        $require_quantity = false;
        if ($product_id == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00204'][$language] /*Product ID is required.*/);
        }
        if ($amount_currency == '') {
        return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00206'][$language]/*Amount in currency is required.*/);
        }
        if ($local_amount != '' && !is_numeric($local_amount)) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00200'][$language] /*Something went wrong. Please try again.*/, "developer_message" => "invalid value for local_amount", "local_amount" => $local_amount);
        }

        if ($wallet_type == '') {
        return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00207'][$language]/*Wallet type is required.*/);
        }
        $wallet_type = strtolower($wallet_type);

        $xun_coins_arr = $xunCoins->getPayCoins();

        $xun_coin = $xun_coins_arr[$wallet_type];

        // return array("code" => 0, "message" => "FAILED", "message_d" => "Pay service not available in this coin.", "errorCode" => -111);
        //  check from xun_coins
        if (is_null($xun_coin)) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00208'][$language] /*Pay service not available in this coin.*/, "errorCode" => -111);
        }

        $is_custom_coin = $xun_coin["is_custom_coin"];

        $xun_user_service = new XunUserService($db);

        $date = date("Y-m-d H:i:s");

        $product_data = $this->get_active_product_by_id($product_id);
        if (empty($product_data)) {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00209'][$language] /*Please select a valid product.*/);
        }

        $account_type = $product_data["account_type"];
        $product_type = $product_data["type"];
        $product_code = $product_data["product_code"];
        $provider_id = $product_data["provider_id"];
        $product_country_iso_code2 = $product_data["country_iso_code2"];
        $currency_code = $product_data["currency_code"];
        $input_type_json = $product_data["input_type"];

        $input_type_arr = json_decode($input_type_json, 1);
        $amount_type = $input_type_arr["amount"];
        $product_input_type = $this->map_input_type($input_type_arr);

        if (in_array("phone_number", $product_input_type)) {

        }
        if (in_array("account_number", $product_input_type) && $account_number == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00210'][$language]/*Account number cannot be empty.*/);
        }
        if (in_array("phone_number", $product_input_type) && $phone_number == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00016'][$language] /*Phone number cannot be empty.*/);
        }

        if (in_array("email", $product_input_type)) {
            if ($email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00211'][$language] /*Email cannot be empty.*/);
            }

            // validate email
            if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00212'][$language] /*Please enter a valid email address.*/);
            }
        }

        $recipient_address = $setting->systemSetting["payWalletAddress"];

        $product_option = $this->get_product_option_by_product_id($product_id, $amount_type);

        $system_currency = $this->get_product_provider_account_currency($provider_id);
        // $product_currency = $this->get_product_currency($product_country_iso_code2);
        $product_currency = $currency_code;

        $currency_rate = $xunCurrency->get_rate($wallet_type, $product_currency);

        $system_currency_rate = $xunCurrency->get_rate($wallet_type, $system_currency);

        $product_system_rate = $xunCurrency->get_rate($product_currency, $system_currency);

        $usd_rate = $xunCurrency->get_rate($wallet_type, 'usd');

        if ($provider_id == 1) {
            //  mereload
            if ($product_type == 1) {
                //  topup
                // check if amount is in the list
                $is_allowed_payment_amount = false;
                foreach ($product_option as $product_option_data) {
                    $product_option_amount = $product_option_data["amount"];

                    if ($amount_currency == $product_option_amount) {
                        $is_allowed_payment_amount = true;
                        $chosen_product_option = $product_option_data;
                        break;
                    }
                }

                if ($is_allowed_payment_amount == false) {
                    return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00213'][$language] /*Please select a valid top up package.*/, "errorCode" => -104);
                }
                $local_amount = $chosen_product_option["amount"];
                $sell_price = $local_amount;
            } else if ($product_type == 2) {
                if ($local_amount <= 0) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00214'][$language] /*Invalid amount.*/);
                }

                $product_option_type = array_column($product_option, "amount_type");

                if (in_array("dropdown", $product_option_type)) {
                    $is_allowed_payment_amount = false;
                    foreach ($product_option as $product_option_data) {
                        $product_option_amount = $product_option_data["amount"];

                        if ($amount_currency == $product_option_amount) {
                            $is_allowed_payment_amount = true;
                            $chosen_product_option = $product_option_data;
                            break;
                        }
                    }

                    if ($is_allowed_payment_amount == false) {
                        return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00213'][$language] /*Please select a valid top up package.*/, "errorCode" => -104);
                    }
                    $local_amount = $chosen_product_option["amount"];
                    $sell_price = $local_amount;

                } else {
                    foreach ($product_option as $data) {
                        $amount_type = $data["amount_type"];
                        $amount = $data["amount"];
                        if ($amount_type == "min") {
                            $min_price = $amount;
                        } else if ($amount_type == "max") {
                            $max_price = $amount;
                        }
                    }

                    if (is_null($min_price) || is_null($max_price)) {
                        return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00200'][$language] /*Something went wrong. Please try again.*/, "developer_message" => "min  or max is null.");
                    }
                    // local price -> system price -> coin price
                    $sp1 = bcmul((string) $local_amount, (string) $product_system_rate, 8);

                    $sp2 = ceil(bcmul((string) $sp1, '100', 8));
                    $amount_currency = bcdiv((string) $sp2, '100', 2);
                    $sell_price = $amount_currency;
                    if ($amount_currency < $min_price || $amount_currency > $max_price) {
                        $min_price_formatted = $setting->setDecimal($min_price, "fiatCurrency");
                        $max_price_formatted = $setting->setDecimal($max_price, "fiatCurrency");
                        $translations_message = $this->get_translation_message('E00215');/* Please enter an amount between %%min_price_formatted%% and %%max_price_formatted%%.*/
                        $return_message = str_replace("%%min_price_formatted%%", $min_price_formatted, $translations_message);
                        $return_message = str_replace("%%max_price_formatted%%", $max_price_formatted, $return_message);
                        return array("code" => 0, "message" => "FAILED", "message_d" => $return_message);
                    }
                }
            }
        } else if ($provider_id == 2) {
            //  reloadly
            //  topup
            // check if amount is in the list
            $allowed_payment_amount = array_column($product_option, "amount");
            $is_allowed_payment_amount = false;
            foreach ($product_option as $product_option_data) {
                $product_option_amount = $product_option_data["amount"];

                if ($amount_currency == $product_option_amount) {
                    $is_allowed_payment_amount = true;
                    $chosen_product_option = $product_option_data;
                    break;
                }
            }

            if ($is_allowed_payment_amount == false) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00213'][$language] /*Please select a valid top up package.*/, "errorCode" => -104);
            }

            if ($product_country_iso_code2 == "my") {
                $reloadly_fx_rate = '1';
            } else {
                $reloadly_params = [];
                $reloadly_params["amount"] = 1;
                $reloadly_params["operatorId"] = $product_code;
                $reloadly_params["currencyCode"] = 'MYR';

                $reloadly = new reloadly($db, $setting, $post);

                $reloadly_response = $reloadly->getFxRate($reloadly_params);
                $reloadly_fx_rate = $reloadly_response["fxRate"];
            }
            $local_amount = bcmul((string) $amount_currency, (string) $reloadly_fx_rate, 2);
            $sell_price = $local_amount;
        } else if ($provider_id == 3) {
            if ($product_type == 3) {
                $require_quantity = true;
            } else {
                $quantity = 1;
            }

            if ($local_amount <= 0) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00214'][$language] /*Invalid amount.*/);
            }

            if ($quantity <= 0 || $quantity == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00216'][$language] /*Invalid quantity.*/);
            }

            if ($amount_type == "dropdown") {
                $is_sell_price = false;
                foreach ($product_option as $data) {
                    $sell_price = $data["sell_price"];
                    if ($local_amount == $sell_price) {
                        $local_amount = $data["amount"];
                        $is_sell_price = true;
                        $pid = $data["pid"];
                        break;
                    }
                }

                if ($is_sell_price === false) {
                    return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00213'][$language] /*Please select a valid top up package.*/, "errorCode" => -104);
                }

                // local price -> system price -> coin price
                $sp1 = bcmul((string) $local_amount, (string) $product_system_rate, 8);
                
                $sp2 = ceil(bcmul((string) $sp1, '100', 8));
                $amount_currency = bcdiv((string) $sp2, '100', 2);

            } else if ($amount_type == "input") {
                $product_option_data = $product_option[0];
                if (!$product_option_data) {
                    return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00200'][$language] /*Something went wrong. Please try again.*/, "developer_message" => "product option data not found");
                }
                $pid = $product_option_data["pid"];
                $min_price = $product_option_data["min_price"];
                $max_price = $product_option_data["max_price"];

                if (is_null($min_price) || is_null($max_price)) {
                    return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00200'][$language] /*Something went wrong. Please try again.*/, "developer_message" => "min or max is null.");
                }
                // local price -> system price -> coin price
                $sp1 = bcmul((string) $local_amount, (string) $product_system_rate, 8);

                $sp2 = ceil(bcmul((string) $sp1, '100', 8));
                $amount_currency = bcdiv((string) $sp2, '100', 2);

                if ($local_amount < $min_price || $local_amount > $max_price) {
                    $min_price_formatted = $setting->setDecimal($min_price, "fiatCurrency");
                    $max_price_formatted = $setting->setDecimal($max_price, "fiatCurrency");
                    $translations_message = $this->get_translation_message('E00215');/* Please enter an amount between %%min_price_formatted%% and %%max_price_formatted%%.*/
                    $return_message = str_replace("%%min_price_formatted%%", $min_price_formatted, $translations_message);
                    $return_message = str_replace("%%max_price_formatted%%", $max_price_formatted, $return_message);
                    return array("code" => 0, "message" => "FAILED", "message_d" => $return_message);
                }

                $sell_price = $local_amount;
            }
        } else {
            //  handle for min max
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00209'][$language] /*Please select a valid product.*/, "errorCode" => -101);
        }

        if (!empty($phone_number)) {
            $mobileNumberInfo = $general->mobileNumberInfo($phone_number, null);
            if ($mobileNumberInfo["isValid"] == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00217')/*Please enter a valid phone number.*/, "errorCode" => -102);
            }
            //  check phone number region code to product country
            $mobile_region_code = strtolower($mobileNumberInfo["regionCode"]);
            if ($mobile_region_code != $product_country_iso_code2) {
                $country_params = array("iso_code2_arr" => [$product_country_iso_code2]);
                $country_data_arr = $country->getCountryDataByIsoCode2($country_params);
                $product_country_data = $country_data_arr[strtoupper($product_country_iso_code2)];
                $country_name = $product_country_data["name"];

                $translations_message = $this->get_translation_message('E00218');/*Please enter a valid %%country_name%% phone number.*/
                $return_message = str_replace("%%country_name%%", $country_name, $translations_message);                
                return array("code" => 0, "message" => "FAILED", "message_d" => $return_message, "errorCode" => -103);
            }
        }

        //  check phone number operator with reloadly
        $reloadly_params = array(
            "phone_number" => $phone_number,
            "country_code" => $mobile_region_code,
        );
        $reloadly = new reloadly($db, $setting, $post);
        $reloadly_operator = $reloadly->detectPhoneNumber($reloadly_params);
        if (isset($reloadly_operator["errorCode"])) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $reloadly_operator["message"], "errorCode" => -105);
        }

        if ($product_data["provider_id"] == 2) {
            if ($product_data["product_code"] != $reloadly_operator["operatorId"]) {
                $translations_message = $this->get_translation_message('E00219');/* Please enter a valid %%product_data["name"]%% phone number.*/
                $return_message = str_replace('%%product_data["name"]%%', $product_data["name"], $translations_message);
                return array("code" => 0, "message" => "FAILED", "message_d" => $return_message, "errorCode" => -106);
            }
        }

        $unit_amount_currency = $amount_currency;

        $product_currency_amount = $xunCurrency->get_conversion_amount($wallet_type, $system_currency, $unit_amount_currency, true);
        
        if ($require_quantity === true) {
            $amount_currency = bcmul((string) $quantity, (string) $amount_currency, 2);
            $product_currency_amount = bcmul((string) $quantity, (string) $product_currency_amount, 8);
        }

        $amount_usd = $xunCurrency->get_conversion_amount('usd', $wallet_type, $product_currency_amount);

        if ($is_custom_coin == true) {
            //  check coin balance
            $credit_type = "coinCredit";
            $coin_user = $xun_user_service->getUserByUsername($wallet_type, null, "coin");
            $coin_user_id = $coin_user["id"];

            $account_balance = $account->getClientCacheBalance($coin_user_id, $credit_type);

            if ($account_balance < $product_currency_amount) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00220')/*"The selected coin has insufficient credit. Please contact coin's owner."*/, "errorCode" => -108);
            }
        }

        $return_data = [];
        $return_data["recipient_address"] = $recipient_address;
        $return_data["amount_currency"] = $amount_currency;
        $return_data["product_currency"] = $product_currency;
        $return_data["amount"] = $product_currency_amount;
        $return_data["local_amount"] = $local_amount;
        $return_data["sell_price"] = $sell_price;
        $return_data["amount_usd"] = $amount_usd;
        $return_data["wallet_type"] = $wallet_type;
        $return_data["product_id"] = $product_id;
        $return_data["account_number"] = $account_number;
        $return_data["phone_number"] = $phone_number;
        $return_data["email"] = $email;
        $return_data["quantity"] = $require_quantity ? $quantity : 1;
        $return_data["pid"] = $pid;

        return array("code" => 1, "data" => $return_data);
    }

    public function insert_pay_transaction($user_id, $pay_signing_details, $wallet_transaction_arr)
    {
        $db = $this->db;

        $product_id = $pay_signing_details["product_id"];
        $pid = $pay_signing_details["pid"];
        $amount_currency = $pay_signing_details["amount_currency"];
        $local_amount = $pay_signing_details["local_amount"];
        $sell_price = $pay_signing_details["sell_price"];
        $quantity = $pay_signing_details["quantity"];
        $product_currency = $pay_signing_details["product_currency"];
        $account_number = $pay_signing_details["account_number"];
        $phone_number = $pay_signing_details["phone_number"];
        $email = $pay_signing_details["email"];
        $status = "pending";
        $date = date("Y-m-d H:i:s");

        for ($i = 0; $i < count($wallet_transaction_arr); $i++) {
            $data = $wallet_transaction_arr[$i];

            if ($data["transaction_type"] == "pay") {
                $wallet_transaction_id = $data["id"];
                $amount = $data["amount"];
                $wallet_type = $data["wallet_type"];
                $reference_id = $this->get_pay_reference_id();

                $insert_data = array(
                    "user_id" => $user_id,
                    "wallet_transaction_id" => $wallet_transaction_id,
                    "product_id" => $product_id,
                    "pid" => $pid ? $pid : "",
                    "reference_id" => $reference_id,
                    "account_no" => $account_number ? $account_number : '',
                    "phone_number" => $phone_number ? $phone_number : "",
                    "email" => $email ? $email : "",
                    "amount" => $amount,
                    "quantity" => $quantity,
                    "wallet_type" => $wallet_type,
                    "amount_currency" => $amount_currency,
                    "local_currency" => $local_amount,
                    "sell_price" => $sell_price,
                    "currency" => $product_currency,
                    "status" => $status,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $row_id = $db->insert("xun_pay_transaction", $insert_data);
                if (!$row_id) {
                    throw new Exception($db->getLastError());
                }

                //  insert into xun_pay_transaction_item table
                $insert_item_data_arr = [];
                for($i = 0; $i < $quantity; $i++){
                    $insert_item_data = array(
                        "pay_transaction_id" => $row_id,
                        "payment_id" => "",
                        "status" => $status,
                        "message" => "",
                        "action" => "",
                        "code" => "",
                        "expired_date" => "",
                        "created_at" => $date,
                        "updated_at" => $date
                    );

                    $insert_item_data_arr[] = $insert_item_data;
                }

                if(!empty($insert_item_data_arr)){
                    $row_ids = $db->insertMulti("xun_pay_transaction_item", $insert_item_data_arr);
                 
                    if(!$row_ids){
                        throw new Exception($db->getLastError());
                    }
                }
            }
        }
    }

    public function pay_transaction_listing($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);

        $name = trim($params["name"]);
        $status = trim($params["status"]);
        $from_ts = trim($params["from_timestamp"]);
        $to_ts = trim($params["to_timestamp"]);

        $page = trim($params["page"]);
        $page_limit = $setting->systemSetting["appsPageLimit"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        // $order = strtoupper(trim($params["order"]));
        // $order = ($order == 'ASCENDING' ? "ASC" : ($order == 'DESCENDING' ? "DESC" : "ASC"));
        $order = "DESC";

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        $status_arr = null;
        if ($status != "") {
            switch ($status) {
                case "all":
                    break;

                case "success":
                    $status_arr = ["success"];
                    break;
                case "pending":
                    $status_arr = ["pending", "submitted"];
                    break;
                case "failed":
                    $status_arr = ["failed", "pending_refund", "refunded"];
                    break;
                default:
            return array("code" => 0, "message" => "FAILED", "message_d" =>  $this->get_translation_message('E00221') /*Invalid status.*/);
                    break;
            }
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00202')/*User does not exist.*/);
        }

        $user_id = $xun_user["id"];
        if (!empty($business_id)) {
            // validate business id
            $business_user = $xun_user_service->getUserByID($business_id);

            $xun_business_service = new XunBusinessService($db);
            $isBusinessEmployee = $xun_business_service->isBusinessEmployee($business_id, $username);

            if (!$isBusinessEmployee) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00222')/*You're not an employee of this business.*/, "errorCode" => -100);
            }

            if (!$business_user || $business_user["type"] != "business") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00223')/*"Invalid business."*/, "errorCode" => -101);
            }

            $user_id = $business_id;
        }

        if ($from_ts) {
            $from_datetime = date("Y-m-d H:i:s", $from_ts);
        }

        if ($to_ts) {
            $to_datetime = date("Y-m-d H:i:s", strtotime("+1 day", $to_ts));
        }

        $xun_pay_service = new XunPayService($db);

        if ($name) {
            //  get product id
            $params = [];
            $params["name"] = $name;
            $params["pagination"] = false;

            $product_list = $xun_pay_service->searchProductList($params, true, "id, name, description, image_url, image_md5");

            $product_id_arr = array_keys($product_list);
        }

        if (is_array($product_id_arr) && empty($product_id_arr)) {
            $transaction_data = [];
            $page_details["total_record"] = 0;
            $page_details["num_record"] = 0;
            $page_details["total_page"] = 0;
            $page_details["page_number"] = 1;
        } else {
            $pay_transaction_obj = new stdClass();

            $pay_transaction_obj->name = $name;
            $pay_transaction_obj->userID = $user_id;
            $pay_transaction_obj->statusArr = $status_arr;
            $pay_transaction_obj->productIdArr = $product_id_arr;
            $pay_transaction_obj->from = $from_datetime;
            $pay_transaction_obj->to = $to_datetime;

            $pay_transaction_obj->pageSize = $page_size;
            $pay_transaction_obj->page = $page;
            $pay_transaction_obj->order = $order;

            $columns = "b.id, a.product_id, b.status, b.created_at";
            $transaction_return_data = $xun_pay_service->getProductTransactionPagination($pay_transaction_obj, $columns);

            $transaction_data = $transaction_return_data["data"];

            if (is_null($product_id_arr)) {
                $transaction_product_id = array_column($transaction_data, "product_id");

                $product_listing_obj = new stdClass();
                $product_listing_obj->ids = $transaction_product_id;
                $product_columns = "id, name, type, image_url, image_md5";

                $product_list = $xun_pay_service->getProductListingByID($product_listing_obj, true, $product_columns);
            }

            for ($i = 0; $i < count($transaction_data); $i++) {
                $data = $transaction_data[$i];
                $product_id = $data["product_id"];
                $product_data = $product_list[$product_id];
                $tx_status = $data["status"];
                $display_status = $this->get_display_status($tx_status);
                $data["status"] = $display_status;
                $data["product_name"] = $product_data["name"];
                $data["image_url"] = $product_data["image_url"];
                $data["image_md5"] = $product_data["image_md5"];
                $data["transaction_date"] = $general->formatDateTimeToIsoFormat($data["created_at"]);

                unset($data["created_at"]);
                $transaction_data[$i] = $data;
            }

            $page_details = $transaction_return_data["page_details"];
        }

        $return_data = [];
        $return_data["listing"] = $transaction_data;
        $return_data["total_record"] = $page_details["total_record"];
        $return_data["num_record"] = $page_details["num_record"];
        $return_data["total_page"] = $page_details["total_page"];
        $return_data["page_number"] = $page_details["page_number"];

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00111') /*"Pay Transaction Listing."*/, "data" => $return_data);
    }

    public function pay_transaction_detail($params)
    {
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $username = trim($params["username"]);
        $rec_id = trim($params["id"]);

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        if ($rec_id == '') {
        return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00224')/*Record ID is required.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00202') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];

        $xun_pay_service = new XunPayService($db);

        $pay_obj = new stdClass();
        $pay_obj->id = $rec_id;
        $pay_obj->joinWalletTransaction = true;

        $columns = "b.*, a.*, c.transaction_hash";

        $xun_pay_service = new XunPayService($db);
        $pay_transaction = $xun_pay_service->getPayTransactionDetails($pay_obj, $columns);

        if ($pay_transaction) {
            $pay_transaction_user_id = $pay_transaction["user_id"];
            if ($pay_transaction_user_id != $user_id) {
                $pay_transaction_user_data = $xun_user_service->getUserByID($pay_transaction_user_id);
                if ($pay_transaction_user_data["type"] == "user") {
                    return array("code" => 0, "message" => "FAILED", "message_d" =>$this->get_translation_message('E00225')/*Invalid transaction ID.*/, "errorCode" => -102);
                } else {
                    $xun_business_service = new XunBusinessService($db);
                    $isBusinessEmployee = $xun_business_service->isBusinessEmployee($pay_transaction_user_id, $username);

                    if (!$isBusinessEmployee) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00222')/*You're not an employee of this business.*/, "errorCode" => -100);
                    }
                }
            }
            $product_id = $pay_transaction["product_id"];

            $product_obj = new stdClass();
            $product_obj->id = $product_id;
            $product_data = $xun_pay_service->getProduct($product_obj, "id, name, description, image_url, image_md5, account_type");

            $sell_price = $pay_transaction["sell_price"];
            $sell_price = $setting->setDecimal($sell_price, "fiatCurrency");

            $product_currency = $pay_transaction["currency"];
            $fiat_details = array(
                "amount" => $sell_price,
                "unit" => $product_currency,
            );

            $wallet_type = $pay_transaction["wallet_type"];
            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
            $credit_type = $decimal_place_setting["credit_type"];
            $amount = $pay_transaction["amount"];
            $quantity = $pay_transaction["quantity"];
            $crypto_unit_price = bcdiv((string)$amount, (string)$quantity, 8);
            $crypto_unit_price = $setting->setDecimal($crypto_unit_price, $credit_type);

            $currency_info = $xunCurrency->get_currency_info($wallet_type);
            $crypto_details = array(
                "amount" => $crypto_unit_price,
                "unit" => $currency_info["symbol"],
            );
            $tx_status = $pay_transaction["status"];
            $display_status = $this->get_display_status($tx_status);

            $return_data = [];
            $return_data["id"] = $pay_transaction["id"];
            $return_data["product_id"] = $pay_transaction["product_id"];
            $return_data["transaction_hash"] = $pay_transaction["transaction_hash"];
            $return_data["account_no"] = $pay_transaction["account_no"];
            $return_data["phone_number"] = $pay_transaction["phone_number"];
            $return_data["email"] = $pay_transaction["email"];
            $return_data["quantity"] = 1; //    quantity is always 1
            $return_data["wallet_type"] = $wallet_type;
            $return_data["status"] = $display_status;
            $return_data["product_name"] = $product_data["name"];
            $return_data["product_image_url"] = $product_data["image_url"];
            $return_data["product_image_md5"] = $product_data["image_md5"];
            $return_data["product_description"] = $product_data["description"];
            $return_data["product_account_type"] = $product_data["account_type"];
            $return_data["payment_number"] = $pay_transaction["provider_transaction_id"];
            $return_data["crypto_details"] = $crypto_details;
            $return_data["fiat_details"] = $fiat_details;

            $return_data["transaction_date"] = $general->formatDateTimeToIsoFormat($pay_transaction["created_at"]);

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00112') /*Transaction details.*/, "data" => $return_data);
        } else {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00225')/*Invalid transaction ID.*/, "errorCode" => -101);
        }

    }

    private function get_display_status($tx_status)
    {
        $general = $this->general;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $success_language_code = "B00148";
        $failed_language_code = "B00149";
        $pending_language_code = "B00150";

        switch ($tx_status) {
            case "failed":
            case "pending_refund":
                $display_status = $translations[$failed_language_code][$language];
                break;
            case "refunded":
                $display_status = $translations[$failed_language_code][$language];
                break;
            case "pending":
            case "submitted":
                $display_status = $translations[$pending_language_code][$language];
                break;
            case "success": 
            case "completed":
                $display_status = $translations[$success_language_code][$language];
                break;
            default:
                $display_status = $tx_status;

        }
        return $display_status;
    }

    public function get_active_product_by_id($id, $columns = null)
    {
        $db = $this->db;

        $db->where("id", $id);
        $db->where("active", 1);
        $data = $db->getOne("xun_pay_product", $columns);
        return $data;
    }

    public function get_product_by_name_type_coutry($params, $columns = null)
    {
        $db = $this->db;

        $name = $params["name"];
        $type_arr = $params["type_arr"];
        $country_code_arr = $params["country_iso_code2"];
        $page_size = $params["page_size"];
        $order = $params["order"];
        $page = $params["page"];

        if ($page) {
            if ($page < 1) {
                $page = 1;
            }
            $start_limit = 0;
            $limit = array($start_limit, $page_size);

            $start_limit = ($page - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        } else {
            $limit = null;
        }

        // if(!empty($type_arr)){
        //     $db->where("type", $type_arr, "in");
        // }
        if (!empty($type_arr)) {

        }
        if (!empty($name)) {
            $db->where("name", "%$name%", "LIKE");
        }
        if ($country_code_arr) {
            $db->where("country_iso_code2", $country_code_arr, "in");
        }
        $db->where("active", 1);
        $copyDb = $db->copy();

        $db->orderBy("name", $order);

        $data = $db->get("xun_pay_product", $limit, $columns);
        $total_record = $copyDb->getValue("xun_pay_product", "count(id)");

        $return_data = [];
        $return_data["total_record"] = $total_record;
        $return_data["num_record"] = (int) $page_size;
        $return_data["total_page"] = ceil($total_record / $page_size);
        $return_data["page_number"] = (int) $page;
        return array("data" => $data, "page_details" => $return_data);
    }

    public function get_product_by_type_and_coutry($params, $columns = null)
    {
        $db = $this->db;

        $type_arr = $params["type_arr"];
        $country_code_arr = $params["country_iso_code2"];
        $page_size = $params["page_size"];
        $order = $params["order"];
        $page = $params["page"];

        if ($page) {
            if ($page < 1) {
                $page = 1;
            }
            $start_limit = 0;
            $limit = array($start_limit, $page_size);

            $start_limit = ($page - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        } else {
            $limit = null;
        }

        if (!empty($type_arr)) {
            $db->where("type", $type_arr, "in");
        }
        if ($country_code_arr) {
            $db->where("country_iso_code2", $country_code_arr, "in");
        }
        $db->where("active", 1);
        $copyDb = $db->copy();

        $db->orderBy("name", $order);

        $data = $db->get("xun_pay_product", $limit, $columns);
        $total_record = $copyDb->getValue("xun_pay_product", "count(id)");

        $return_data = [];
        $return_data["total_record"] = $total_record;
        $return_data["num_record"] = (int) $page_size;
        $return_data["total_page"] = ceil($total_record / $page_size);
        $return_data["page_number"] = (int) $page;
        return array("data" => $data, "page_details" => $return_data);
    }

    public function get_product_country_by_type($type)
    {
        $db = $this->db;

        if (is_array($type)) {
            $db->where("type", $type, "in");
        } else {
            $db->where("type", $type);
        }
        $db->where("active", 1);
        $data = $db->getValue("xun_pay_product", "distinct(country_iso_code2)", null);

        return $data;
    }

    public function get_product_option_by_product_id($product_id, $amount_type = null, $columns = null)
    {
        $db = $this->db;

        $db->where("product_id", $product_id);
        if($amount_type){
            $db->where("amount_type", $amount_type);
        }
        $db->where("status", 1);
        $data = $db->get("xun_pay_product_option", null, $columns);

        return $data;
    }

    public function get_product_transaction_by_wallet_transaction_id($wallet_transaction_id, $columns = null)
    {
        $db = $this->db;

        $db->where("wallet_transaction_id", $wallet_transaction_id);
        $data = $db->getOne("xun_pay_transaction", $columns);

        return $data;
    }

    public function get_product_provider($provider_id, $columns = null)
    {
        $db = $this->db;

        $db->where("id", $provider_id);
        $data = $db->getOne("xun_pay_provider", $columns);

        return $data;
    }

    public function get_product_provider_account_currency($provider_id)
    {
        $product_provider = $this->get_product_provider($provider_id, "account_currency");

        if ($product_provider) {
            return $product_provider["account_currency"];
        }

    }

    private function get_country_listing($country_iso_code2_arr)
    {
        global $country;

        if(empty($country_iso_code2_arr)) return [];
        $country_params = array("iso_code2_arr" => $country_iso_code2_arr);
        $country_data = $country->getCountryDataByIsoCode2($country_params);

        $country_info = $this->get_country_info($country_data);
        return $country_info;
    }
    public function get_country_info($country_data)
    {
        $countries = [];
        foreach ($country_data as $k => $country) {
            $data = [];
            $data["name"] = $country["name"];
            $data["image_url"] = $country["image_url"];
            $data["image_md5"] = $country["image_md5"];
            $data["country_code"] = strtolower($country["iso_code2"]);

            $countries[] = $data;
            unset($data);
        }
        return $countries;
    }

    public function get_product_exchange_rate($params)
    {
        global $xunCurrency;
        $db = $this->db;

        $product_currency = $params["product_currency"];
        $system_currency = $params["system_currency"];

        $db->where("is_pay", 1);
        $xun_coins_arr = $db->get("xun_coins", null, "currency_id, is_custom_coin");
        $cryptocurrency_id_arr = array_column($xun_coins_arr, "currency_id");

        $rate_arr = [];
        for ($i = 0; $i < count($cryptocurrency_id_arr); $i++) {
            $cryptocurrency = $cryptocurrency_id_arr[$i];
            $currency_rate = $xunCurrency->get_rate($cryptocurrency, $system_currency);
            $key = $cryptocurrency . '/' . $system_currency;
            $rate_arr[$key] = $currency_rate;

            $currency_rate = $xunCurrency->get_rate($cryptocurrency, $product_currency);
            $key = $cryptocurrency . '/' . $product_currency;
            $rate_arr[$key] = $currency_rate;

            if ($product_currency != 'usd') {
                $usd_rate = $xunCurrency->get_rate($cryptocurrency, "usd");
                $key = $cryptocurrency . '/' . 'usd';
                $rate_arr[$key] = $usd_rate;
            }
        }

        $system_currency_rate = $xunCurrency->get_rate("usd", $system_currency);
        $key = "usd/" . $system_currency;
        $rate_arr[$key] = $system_currency_rate;

        $system_currency_rate = $xunCurrency->get_rate($system_currency, $product_currency);
        $key = $system_currency . "/" . $product_currency;
        $rate_arr[$key] = $system_currency_rate;

        $product_currency_rate = $xunCurrency->get_rate($product_currency, $system_currency);
        $key = $product_currency . "/" . $system_currency;
        $rate_arr[$key] = $product_currency_rate;

        $product_currency_rate_usd = $xunCurrency->get_rate("usd", $product_currency);
        $key = "usd/" . $product_currency;
        $rate_arr[$key] = $product_currency_rate_usd;

        return array("exchange_rate_arr" => $rate_arr, "cryptocurrency_arr" => $cryptocurrency_id_arr, "xun_coins_arr" => $xun_coins_arr);
    }

    public function get_product_currency($country_iso_code2)
    {
        global $country;

        $country_params = array("iso_code2_arr" => [$country_iso_code2]);
        $country_data_arr = $country->getCountryDataByIsoCode2($country_params);

        if (!empty($country_data_arr)) {
            $country_data = $country_data_arr[strtoupper($country_iso_code2)];
            $currency_code = $country_data["currency_code"];

            return strtolower($currency_code);
        }
    }

    public function update_pay_transaction_status($obj)
    {
        $db = $this->db;

        $id = $obj->id;
        $status = $obj->status;
        $message = $obj->message;
        $provider_transaction_id = $obj->provider_transaction_id;
        $order_id_arr = $obj->order_id_arr;

        $date = date("Y-m-d H:i:s");

        $update_data = [];
        $update_data["status"] = $status;
        $update_data["updated_at"] = $date;

        $update_item_data = [];
        $update_item_data["status"] = $status;
        $update_item_data["updated_at"] = $date;

        if (!is_null($message)) {
            $update_data["message"] = $message;
            $update_item_data["message"] = $message;
        }
        if ($provider_transaction_id) {
            $update_data["provider_transaction_id"] = $provider_transaction_id;
            $update_item_data["payment_id"] = $provider_transaction_id;
        }
        $update_data["updated_at"] = $date;

        $db->where("id", $id);
        $db->where("status", $status, "!=");
        $retVal = $db->update("xun_pay_transaction", $update_data);

        if($order_id_arr){
            $db->where("pay_transaction_id", $id);
            $pay_transaction_item_arr = $db->get("xun_pay_transaction_item");
            for($i = 0; $i < count($order_id_arr); $i++){
                $order_id = $order_id_arr[$i];
                $pay_transaction_item = $pay_transaction_item_arr[$i];

                $update_item_data["order_id"] = $order_id;

                $db->where("id", $pay_transaction_item["id"]);
                $db->update("xun_pay_transaction_item", $update_item_data);
            }
        }else if($status != 'submitted'){
            $update_item_data["status"] = $status;

            $db->where("pay_transaction_id", $id);
            $db->update("xun_pay_transaction_item", $update_item_data);
        }
        return $retVal;
    }


    public function update_pay_transaction_item_status($obj)
    {
        global $log;
        $db = $this->db;

        $xun_pay_service = new XunPayService($db);

        if(is_null($obj->id)){
            $log->write(date("Y-m-d H:i:s") . ": update_pay_transaction_status - id is null");
        }

        $xun_pay_service->updatePayTransactionItemStatus($obj);
    }

    public function update_pay_transaction_status_by_wallet_id($obj)
    {
        $db = $this->db;

        $id = $obj->id;
        $status = $obj->status;
        $message = $obj->message;
        $update_data = [];
        $update_data["status"] = $status;
        $update_data["message"] = $message ? $message : '';
        if ($obj->provider_transaction_id) {
            $update_data["provider_transaction_id"] = $obj->provider_transaction_id;
        }
        $update_data["updated_at"] = date("Y-m-d H:i:s");
        $db->where("id", $id);
        $retVal = $db->update("xun_pay_transaction", $update_data);
        return $retVal;
    }

    public function process_request_to_provider($pay_transaction_rec)
    {
        global $post, $xunCurrency;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $xun_company_wallet = new XunCompanyWallet($db, $setting, $post);

        $coin_creditted = $this->perform_coin_credit($pay_transaction_rec);

        if ($coin_creditted == false) {
            //  refund coin

            $update_obj = new stdClass();
            $update_obj->id = $pay_transaction_rec["id"];
            $update_obj->status = "failed";
            $update_obj->message = "Insufficient coin credit";
            $this->update_pay_transaction_status($update_obj);
            
            $update_pay_tx_item_data = array(
                "status" => "failed",
                "updated_at" => date('Y-m-d H:i:s')
            );

            $db->where("pay_transaction_id", $pay_transaction_rec["id"]);
            $db->update("xun_pay_transaction_item", $update_pay_tx_item_data);

            $xun_company_wallet->payTransactionRefund($pay_transaction_rec);
            return;
        }

        $product_id = $pay_transaction_rec["product_id"];
        $product_data = $this->get_active_product_by_id($product_id);

        if (empty($product_data)) {
            return "Invalid product.";
        }

        $product_provider_id = $product_data["provider_id"];
        $product_code = $product_data["product_code"];
        $product_type = $product_data["type"];

        $user_id = $pay_transaction_rec["user_id"];
        $reference_id = $pay_transaction_rec["reference_id"];
        $product_amount = $pay_transaction_rec["amount_currency"];
        $product_quantity = $pay_transaction_rec["quantity"];
        $transaction_phone_number = $pay_transaction_rec["phone_number"];
        $account_number = $pay_transaction_rec["account_no"];
        $wallet_type = $pay_transaction_rec["wallet_type"];
        $transaction_crypto_amount = $pay_transaction_rec["amount"];

        $product_unit_price = bcdiv((string)$product_amount, (string)$product_quantity, 8);
        //  check crypto rate
        //  if crypto amount < amount_currency * rate
        $system_currency = $this->get_product_provider_account_currency($product_provider_id);

        $current_rate = $xunCurrency->get_rate($wallet_type, $system_currency);

        $current_product_crypto_unit_amount = $xunCurrency->get_conversion_amount($wallet_type, $system_currency, $product_unit_price, true);
        
        $current_product_crypto_amount = bcmul((string)$current_product_crypto_unit_amount, (string)$product_quantity, 8);

        if (bccomp((string) $transaction_crypto_amount, (string) $current_product_crypto_amount, 8) < 0) {
            $update_obj = new stdClass();
            $update_obj->id = $pay_transaction_rec["id"];
            $update_obj->status = "failed";
            $update_obj->message = "Current rate: $current_rate";

            $this->update_pay_transaction_status($update_obj);

            $xun_company_wallet->payTransactionRefund($pay_transaction_rec);

            return;
        }

        $xunPayProvider = new XunPayProvider($db, $setting, $post);

        $mobileNumberInfo = $general->mobileNumberInfo($transaction_phone_number, null);
        $mobile_region_code = $mobileNumberInfo["regionCode"];

        $xun_user_service = new XunUserService($db);
        $user_data = $xun_user_service->getUserByID($user_id);
        //  check user type

        $sender_phone_number = $user_data["username"];
        $senderMobileNumberInfo = $general->mobileNumberInfo($transaction_phone_number, null);
        $sender_mobile_region_code = $senderMobileNumberInfo["regionCode"];

        $product_provider_data = $this->get_product_provider($product_provider_id);
        if ($product_provider_id == 1) {
            $command_type = '';
            $product_code = strtoupper($product_code);

            if (strtolower($mobile_region_code) == "my") {
                $phone_number = ltrim($transaction_phone_number, '+6');
            }

            if ($product_type == 1) {
                $command_type = 'R';

                $product_amount = (int) $product_amount;

                $command = $command_type . '_' . $phone_number . '_' . $product_amount . '_' . $product_code;

            } else if ($product_type == 2) {
                $command_type = 'B';
                $account_name = '';
                $product_amount = $setting->setDecimal($product_amount, "fiatCurrency");
                // $account_number = "xmxmxmxmxmxmk";
                $command = $command_type . '_';
                $command .= $product_amount . '_';
                $command .= $product_code . '_';
                $command .= $phone_number . '_';
                $command .= $account_name . '_';
                $command .= $account_number;

                // B_<amount>_<Product>_<mobile number>_<account name>_<account number>
                // * B_10_AT_0131231234_John Doe_0812345
                // * B_10_TN_0124466833__210083115205
            }

            $result = $xunPayProvider->sendCommandMeReload($command, $reference_id);

            if ($result["code"] == 1) {
                $status = "submitted";
            } else {
                $status = "failed";
                $error_message = $result["error_message"];
            }

            $update_obj = new stdClass();
            $update_obj->id = $pay_transaction_rec["id"];
            $update_obj->status = $status;
            $update_obj->message = $error_message;
            $this->update_pay_transaction_status($update_obj);
        } else if ($product_provider_id == 2) {
            // call reloadly
            $provider_params = [];
            $provider_params["amount"] = $product_amount;
            $provider_params["operatorId"] = $product_code;
            $provider_params["referenceId"] = $reference_id;
            $provider_params["senderPhone"] = array(
                "countryCode" => $sender_mobile_region_code,
                "number" => $sender_phone_number);
            $provider_params["recipientPhone"] = array("countryCode" => strtoupper($mobile_region_code), "number" => $transaction_phone_number);

            $result = $xunPayProvider->reloadlyTopup($provider_params);

            if ($result["code"] == 1) {
                $status = "success";
                $provider_transaction_id = $result["transaction_id"];
            } else {
                $status = "failed";
                $error_message = $result["error_message"];
            }

            $update_obj = new stdClass();
            $update_obj->id = $pay_transaction_rec["id"];
            $update_obj->status = $status;
            $update_obj->message = $error_message;
            $update_obj->provider_transaction_id = $provider_transaction_id;

            $this->update_pay_transaction_status($update_obj);

            $user_id_arr = [$user_id];
            $result_data = array(
                "status" => $status,
                "message" => $error_message,
            );
            $status_data = array(
                "pay_transaction" => $pay_transaction_rec,
                "result" => $result_data,
            );
            $status_arr[] = $status_data;
            $xun_pay_product_arr[$product_id] = $product_data;
            $this->send_notification($product_provider_data, $user_id_arr, $status_arr, $xun_pay_product_arr);

            if ($status == "failed") {
                $xun_company_wallet->payTransactionRefund($pay_transaction_rec);
            }
        } else if ($product_provider_id == 3) {
            $pid = $pay_transaction_rec["pid"];
            $db->where("pid", $pid);
            $product_option_data = $db->getOne("xun_pay_product_option");

            $result = $xunPayProvider->giftnpayGiftcard($pay_transaction_rec, $product_data, $product_provider_data, $product_option_data);

            if ($result["code"] == 1) {
                $status = "submitted";
                $provider_transaction_id = $result["transaction_id"];
                $order_id_arr = $result["order_id"];
            } else {
                $status = "failed";
                $error_message = $result["error_message"];
            }

            $update_obj = new stdClass();
            $update_obj->id = $pay_transaction_rec["id"];
            $update_obj->status = $status;
            $update_obj->message = $error_message;
            $update_obj->provider_transaction_id = $provider_transaction_id;
            $update_obj->order_id_arr = $order_id_arr;

            $this->update_pay_transaction_status($update_obj);

            $user_id_arr = [$user_id];
            $result_data = array(
                "status" => $status,
                "message" => $error_message,
            );
            $status_data = array(
                "pay_transaction" => $pay_transaction_rec,
                "result" => $result_data,
            );
            $status_arr[] = $status_data;
            $xun_pay_product_arr[$product_id] = $product_data;
            $this->send_notification($product_provider_data, $user_id_arr, $status_arr, $xun_pay_product_arr);

            if ($status == "failed") {
                $xun_company_wallet->payTransactionRefund($pay_transaction_rec);
            }
        }
    }

    public function send_notification($provider_data, $user_id_arr, $status_arr, $xun_pay_product_arr)
    {
        global $xun_numbers;
        $db = $this->db;

        if (!empty($status_arr)) {
            $insert_data_arr = [];
            $recipient_list = $xun_numbers;

            $db->where("id", $user_id_arr, "in");
            $xun_user_arr = $db->map("id")->ArrayBuilder()->get("xun_user", null, "id, username, nickname, type");

            for ($i = 0; $i < count($status_arr); $i++) {
                $data = $status_arr[$i];
                $pay_transaction = $data["pay_transaction"];
                $result_data = $data["result"];
                $user_id = $pay_transaction["user_id"];
                $ref_id = $pay_transaction["id"];
                $product_id = $pay_transaction["product_id"];
                $product_amount = $pay_transaction["amount_currency"];
                $product_currency = $pay_transaction["currency"];

                $user_data = $xun_user_arr[$user_id];
                $username = $user_data["username"];
                $nickname = $user_data["nickname"];

                $product_data = $xun_pay_product_arr[$product_id];
                $product_name = $product_data["name"];

                $balance = $result_data["balance"];

                $result_status = $result_data["status"];
                $status = !empty($result_status) ? ucfirst($result_status) : "";
                $message = !empty($result_data["message"]) ? $result_data["message"] : "" ;
                // if ($result_status == "failed") {
                //     $status = "Failed";
                //     $message = $result_data["message"];
                // } else if ($result_status == "success") {
                //     $status = "Success";
                //     $message = '';
                // }

                $provider_name = $provider_data["name"];

                $notification_message = "Username: " . $username;
                $notification_message .= "\nNickname: " . $nickname;
                $notification_message .= "\nReference ID: " . $ref_id;
                $notification_message .= "\nAmount: " . $product_amount . ' ' . $product_currency;
                $notification_message .= "\nProduct: " . $product_name;
                $notification_message .= "\nStatus: " . $status;
                $notification_message .= "\nMessage: " . $message;
                $notification_message .= "\nProvider: " . $provider_name;
                if ($balance) {
                    $notification_message .= "\nBalance: " . $balance;
                }
                $notification_message .= "\nTime: " . date("Y-m-d H:i:s");

                $json_params = array(
                    "business_id" => "1",
                    "tag" => "Pay TopUp",
                    "message" => $notification_message,
                    "mobile_list" => $recipient_list,
                );

                $insert_data = array(
                    "data" => json_encode($json_params),
                    "message_type" => "business",
                    "created_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s"),
                );

                $insert_data_arr[] = $insert_data;

            }
            $ids = $db->insertMulti('xun_business_sending_queue', $insert_data_arr);
        }
    }

    public function get_pay_reference_id()
    {
        global $config;
        $db = $this->db;

        $env = $config["environment"];

        if ($env == "prod") {
            $prefix = "theNux_p_";
        } else if ($env == "dev") {
            $prefix = "theNux_d_";
        } else {
            $prefix = "theNux_l_";
        }

        $id = $db->getNewID();
        return $prefix . $id;
    }

    public function get_pay_main_page_listing($params)
    {
        global $config;
        $env = $config["environment"];

        $db = $this->db;

        $username = trim($params["username"]);
        $country_code = $params["country_code"];

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        if (is_array($country_code)) {
            // if(empty($country_code)){
            //     return array("code" => 0, "message" => "FAILED", "message_d" => "Please select a country.");
            // }

            $country_code_arr = array_map(function ($v) {
                return strtolower($v);
            }, $country_code);
        } else {
            if (trim($country_code) == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00226') /*Country code is required.*/);
            }
            $country_code_arr = [trim($country_code)];
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00202') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];

        $device_info_obj = new stdClass();
        $device_info_obj->username = $username;

        $xun_device_info = $xun_user_service->getDeviceInfo($device_info_obj);

        $is_old_version = false;
        if ($xun_device_info) {
            $os = $xun_device_info["os"];
            $app_version = $xun_device_info["app_version"];
            $app_version = str_replace("(", ".", $app_version);
            $app_version = str_replace(")", "", $app_version);

            $min_android_version = '1.0.222.3';
            $min_ios_version = '1.0.160';

            if ($os == 1 && version_compare($min_android_version, $app_version) > 0) {
                $is_old_version = true;
            } else if ($os == 2 && version_compare($min_ios_version, $app_version) > 0) {
                $is_old_version = true;
            }
        }

        $xun_pay_service = new XunPayService($db);

        $list_limit = 10;

        $pay_obj = new StdClass();
        $pay_obj->userID = $user_id;
        $pay_obj->countryIsoCode2 = $country_code_arr;
        $pay_obj->limit = $list_limit;

        // $frequently_used_product_arr = $xun_pay_service->getFrequentlyUsedProductList($pay_obj, "product_id");

        $db->where("a.user_id", $user_id);
        $db->where("b.active", 1);
        $db->join("xun_pay_product b", "a.product_id=b.id", "LEFT");

        $db->groupBy("a.product_id");
        $frequently_used_product_arr = $db->get("xun_pay_transaction a", $list_limit, "product_id");

        $popular_prod_obj = new StdClass();
        $popular_prod_obj->userID = $user_id;
        $popular_prod_obj->countryIsoCode2 = $country_code_arr;
        $popular_prod_obj->isOldVersion = $is_old_version;

        $popular_limit = $list_limit;
        $popular_columns = "id as product_id, name, image_url, image_md5";
        $popular_product_arr = $xun_pay_service->getPopularProductByCountryCode($popular_prod_obj, $popular_limit, $popular_columns);
        // select * from xun_pay_product where country code = my order by

        $top_type_arr = $xun_pay_service->getTopProductTypeByCountyCode($pay_obj, "product_type_id");

        $product_id_arr = [];
        $product_type_id_arr = [];

        if (!empty($frequently_used_product_arr)) {
            $product_id_column = array_column($frequently_used_product_arr, "product_id");
            $product_id_arr = array_merge($product_id_arr, $product_id_column);
        }

        if (!empty($popular_product_arr)) {
            $product_id_column = array_column($popular_product_arr, "product_id");
            $product_id_arr = array_merge($product_id_arr, $product_id_column);
        }

        if ($is_old_version == true) {
            $product_type_id_arr = [1, 2];
        } else {
            if (!empty($top_type_arr)) {
                $product_type_id_column = array_column($top_type_arr, "product_type_id");
                $product_type_id_arr = array_merge($product_type_id_arr, $product_type_id_column);
            } else {
                $product_type_id_arr = [1, 2, 3];
            }
        }

        $product_listing_obj = new stdClass();
        $product_listing_obj->ids = $product_id_arr;
        $product_columns = "id, name, type, image_url, image_md5";

        $xun_product_listing = $xun_pay_service->getProductListingByID($product_listing_obj, true, $product_columns);

        $product_type_listing_obj = null;
        if ($is_old_version === true) {
            $product_type_listing_obj = new stdClass();
            $product_type_listing_obj->ids = $product_type_id_arr;
        }

        $product_type_columns = "id, name, type, image_url, image_md5, language_code";

        $xun_product_type_listing = $xun_pay_service->getProductTypeListingByID($product_type_listing_obj, true, $product_type_columns);

        $frequently_user_product = $this->compose_product_listing($frequently_used_product_arr, $xun_product_listing);
        $popular_product = $this->compose_product_listing($popular_product_arr, $xun_product_listing);
        $top_type = $this->compose_product_type_listing($product_type_id_arr, $xun_product_type_listing);
        // $top_type = $this->compose_product_type_listing($top_type_arr, $xun_product_type_listing);
        $categories_arr = $this->compose_product_category_listing($xun_product_type_listing);

        $type_name = array_column($top_type, 'name');
        array_multisort($type_name, SORT_ASC, $top_type);

        $type_name = array_column($xun_product_type_listing, 'name');
        array_multisort($type_name, SORT_ASC, $xun_product_type_listing);

        $product_country_obj = new stdClass();
        $product_country_obj->isOldVersion = $is_old_version;
        $country_iso_code2_arr = $xun_pay_service->getProductCountryByType($product_country_obj);

        $country_arr = $this->get_country_listing($country_iso_code2_arr);

        $return_data = [];
        $return_data["frequently_used_products"] = $frequently_user_product;
        $return_data["popular_products"] = $popular_product;
        $return_data["top_categories"] = $top_type;
        $return_data["countries"] = $country_arr;
        $return_data["categories"] = array_values($categories_arr);
        $return_data["country_code"] = $country_code;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00113')/*Pay listing.*/, "data" => $return_data);

    }

    private function compose_product_listing($arr, $xun_product_listing)
    {
        for ($i = 0; $i < count($arr); $i++) {
            $data = $arr[$i];
            $product_id = $data["product_id"];
            $product_data = $xun_product_listing[$product_id];

            $data["name"] = $product_data["name"];
            $data["image_url"] = $product_data["image_url"];
            $data["image_md5"] = $product_data["image_md5"];
            $data["type_id"] = $product_data["type"];
            unset($data["id"]);
            unset($data["user_id"]);
            $arr[$i] = $data;
        }

        return $arr;
    }

    private function compose_product_type_listing($arr, $product_type_listing)
    {
        $general = $this->general;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();
        for ($i = 0; $i < count($arr); $i++) {
            // $data = $arr[$i];
            $data = [];
            $product_type_id = $arr[$i];
            // $product_type_id = $data["product_type_id"];
            $product_type_data = $product_type_listing[$product_type_id];

            $language_code = $product_type_data["language_code"];
            $data["product_type_id"] = $product_type_id;
            $data["name"] = $translations[$language_code][$language];
            $data["image_url"] = $product_type_data["image_url"];
            $data["image_md5"] = $product_type_data["image_md5"];
            $data["type"] = $product_type_data["type"];
            $arr[$i] = $data;
        }

        return $arr;
    }

    private function compose_product_category_listing($product_type_listing)
    {
        $general = $this->general;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();
        $arr = [];
        foreach($product_type_listing as $product_type_data){
            $data = [];
            $language_code = $product_type_data["language_code"];
            
            $data["id"] = $product_type_data["id"];
            $data["name"] = $translations[$language_code][$language];
            $data["image_url"] = $product_type_data["image_url"];
            $data["image_md5"] = $product_type_data["image_md5"];
            $data["type"] = $product_type_data["type"];
            $arr[] = $data;
        }

        return $arr;
    }

    public function perform_coin_credit($pay_transaction_rec)
    {
        global $xunCoins, $account;

        $db = $this->db;

        //  check coin acc_credit
        $wallet_type = $pay_transaction_rec["wallet_type"];
        $coin_obj = new stdClass();
        $coin_obj->currencyID = $wallet_type;

        $coin_data = $xunCoins->getCoin($coin_obj);

        if ($coin_data["is_custom_coin"] == 1) {
            // validate coin credit is sufficient
            $amount = $pay_transaction_rec["amount"];
            $type = "coinCredit";
            $xun_user_service = new XunUserService($db);
            $xun_user = $xun_user_service->getUserByUsername($wallet_type, null, 'coin');
            $coin_user_id = $xun_user["id"];
            $subject = "Pay Transaction Payment";

            $account_res = $account->insertTAccount($coin_user_id, $type, $amount, $subject, $pay_transaction_rec["id"]);

            //  account_res will return true or false
            return $account_res;
        }

        return true;
    }

    private function get_product_available_wallet_types($exchange_rate_arr, $cryptocurrency_arr,
        $xun_coins_arr, $price_details, $currency) {
        global $account;
        $db = $this->db;

        $xun_user_service = new XunUserService($db);
        $price_type = $price_details["type"];

        if ($price_type == "list") {
            $price_detail_list = $price_details["price_detail_list"];
            $system_price_arr = array_column($price_detail_list, "system_price");
            $max_price = max($system_price_arr);
            $min_price = min($system_price_arr);
        } else if ($price_type == "min_max") {
            $max_price_data = $price_details['max_price'];
            $min_price_data = $price_details['min_price'];
            $max_price = $max_price_data["system_price"];
            $min_price = $min_price_data["system_price"];
        }

        $coins_user_id_arr = $xun_user_service->getUserByUsername($cryptocurrency_arr, "id, username", "coin");

        $credit_type = "coinCredit";

        $removed_wallet_type_arr = [];
        foreach ($coins_user_id_arr as $data) {
            $coin_user_id = $data["id"];
            $wallet_type = $data["username"];

            $account_balance = $account->getClientCacheBalance($coin_user_id, $credit_type);

            $exchange_rate = $exchange_rate_arr[$wallet_type . "/" . $currency];
            $max_amount = bcdiv((string) $max_price, (string) $exchange_rate, 8);
            $min_amount = bcdiv((string) $min_price, (string) $exchange_rate, 8);

            if ($account_balance < $min_amount) {
                //  remove wallet_type from array if balance is in sufficient
                $removed_wallet_type_arr[] = $wallet_type;
            }
        }

        return array_values(array_diff($cryptocurrency_arr, $removed_wallet_type_arr));
    }

    public function map_input_type($input_type_arr)
    {
        foreach ($input_type_arr as $key => $value) {
            if ($key == "phoneNum") {
                $product_input_type[] = "phone_number";
            } else if ($key == "accNum") {
                $product_input_type[] = "account_number";
            } else if ($key == "email") {
                $product_input_type[] = "email";
            }
        }
        return $product_input_type;
    }

    public function get_bind_user_verify_code($params, $ip= null, $user_agent= null) {

        //$user_id="10104";//send email
        //$user_id="10108";//send sms

        global $xunUser;
        $db = $this->db;
        $general = $this->general;

        $req_type = $params['req_type'];
        $user_id = $params['user_id'];
        $mobile = $params['mobile'];
        $email = $params['email'];

        $db->where("a.user_id", $user_id);
        $db->join("xun_user u", "u.id=a.user_id", "INNER");
        $businessAccountDetail = $db->getOne("xun_business_account a", "a.main_mobile_verified, a.email_verified, u.register_site");

        if($businessAccountDetail) {

            $register_site = $businessAccountDetail['register_site'];

            if($req_type=="email") {

                $email_verified = $businessAccountDetail['email_verified'];

                if($email_verified) {

                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00547') /*You are not allowed to perform this action.*/);

                } else {

                    if ($email == '') {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00211') /*Email cannot be empty*/);
                    } else {

                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00543') /*Please enter a valid email address.*/);
                        }

                    }


                    //CHECK MOBILE EXIST IN DB
                    $db->where("a.email_verified", 1);
                    $db->where("a.email", $email);
                    $db->where("u.register_site", $register_site);
                    $db->join("xun_user u", "u.id=a.user_id", "INNER");
                    $checkEmailExist = $db->getOne("xun_business_account a");

                    if($checkEmailExist) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00228') /*User already exist.*/);
                    }

                }

            } else {

                $main_mobile_verified = $businessAccountDetail['main_mobile_verified'];

                if($main_mobile_verified) {

                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00547') /*You are not allowed to perform this action.*/);

                } else {
                    
                    if ($mobile == '') {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00227') /*Mobile cannot be empty*/);
                    }

                    $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
                    $mobile = str_replace("-", "", $mobileNumberInfo["phone"]);

                    if(!$mobileNumberInfo['isValid']) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00217')/*Please enter a valid phone number.*/, "errorCode" => -102);
                    }
                    

                    //CHECK MOBILE EXIST IN DB
                    $db->where("a.main_mobile_verified", 1);
                    $db->where("a.main_mobile", $mobile);
                    $db->where("u.register_site", $register_site);
                    $db->join("xun_user u", "u.id=a.user_id", "INNER");
                    $checkMobileExist = $db->getOne("xun_business_account a");

                    if($checkMobileExist) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00228') /*User already exist.*/);
                    }

                }

            }


            $req_data = array("mobile" => $mobile,
                                "email" => $email,
                                "req_type" => $req_type,
                                "company_name" => $register_site,
                                "language" => 0,
                                "device" => $user_agent,
                                "ip" => $ip);

            $return =  $xunUser->register_verifycode_get($req_data);


            return $return;

        } else {

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00202') /*User does not exist.*/);
        }

        

    }

    public function reset_password_merchant($params, $ip= null, $user_agent= null) {

        global $xunUser;
        $db = $this->db;
        $general = $this->general;

        $req_type = trim($params['req_type']);
        $email  = trim($params["email"]);
        $mobile = trim($params["mobile"]);
        $source = trim($params["source"]);
        $verify_code = trim($params["verify_code"]);
        $request_id = trim($params["request_id"]);
        $validate_id = trim($params["validate_id"]);

        $password = trim($params['password']);
        $confirm_password = trim($params['confirm_password']);


        if($req_type=="email") {
            $db->where('email', $email);
        } else {

            $db->where('username', $mobile);
        }

        $db->where('register_site', $source);
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
        } else {
            $user_id = $xun_user['id'];
        }


        if($password != $confirm_password){
            return array('code' => 0, 'message' => "FAILED", "message_d" => $this->get_translation_message('E00243')/*Password not match.*/);
        } else {

            // Password validation
            $validate_password = $this->validate_password($password, $confirm_password);

            if ($validate_password['code'] == 0) {
                $error_message = $validate_password['error_message'];
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00240')/*Invalid password combination.*/, "developer_msg" => "password has an invalid character combination", "error_message" => $error_message);

            }

            $hash_password = password_hash($password, PASSWORD_BCRYPT);
        }


        if($req_type=="email") {
            $db->where("email", $email);
        } else {
            $db->where("mobile", $mobile);
        }
        
        $db->where("is_valid", 1);
        $db->where("source", $source);
        $db->where("verification_code", $verify_code);
        $db->where("type", "resetMerchantPasswordVerifyCode");

        $dbCopy = $db->copy();


        $db->where("is_verified", 0);
        $db->where("id", $request_id);
        $requestDetail = $db->getOne("xun_user_verification");

        if($requestDetail) {

            $dbCopy->where("is_verified", 1);
            $dbCopy->where("id", $validate_id);
            $validateDetail = $dbCopy->getOne("xun_user_verification");

            if($validateDetail) {

                $reset_pass = array(
                    "password" => $hash_password,
                    "updated_at" => date("Y-m-d H:i:s")
                );

                $db->where('user_id', $user_id);
                $db->update('xun_business_account', $reset_pass);


                $db->where('id', $request_id);
                $db->update('xun_user_verification', array('is_verified'=>1, 'verify_at'=>date('Y-m-d H:i:s')) );

                return array('code' => 1, 'message' => "Success", 'message_d' => 'Successful changed password.', 'data' => '');

            } else {

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00547') /*You are not allowed to perform this action.*/);

            }

        } else {
            
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00547') /*You are not allowed to perform this action.*/);

        }

    }

    public function reset_password_verifiycode_validate($params, $ip= null, $user_agent= null) {

        global $xunUser;
        $db = $this->db;

        $req_type = $params['req_type'];
        $email  = $params["email"];
        $mobile = $params["mobile"];
        $source = $params["source"];
        $verify_code = $params["verify_code"];


        if($req_type=="email") {
            $verify_code_return = $xunUser->verify_code($email, $verify_code, $ip, $user_agent, "New", $source, "email", "resetMerchantPasswordVerifyCode");
        } else {
            $verify_code_return = $xunUser->verify_code($mobile, $verify_code, $ip, $user_agent, "New", $source, "mobile", "resetMerchantPasswordVerifyCode");
        }
        
        if ($verify_code_return["code"] === 0) {
            return $verify_code_return;
        }

        return array('code' => 1, 'message' => "Success", 'message_d' => '', 'data' => array("request_id"=>$verify_code_return['request_arr']['id'], "validate_id"=>$verify_code_return['row_id']));


    }

    public function reset_password_verifiycode_get($params, $ip= null, $user_agent= null) {

        global $xunUser;
        $db = $this->db;
        $general = $this->general;

        $req_type = $params['req_type'];
        $email  = $params["email"];
        $mobile = $params["mobile"];
        $source = $params["source"];
        $user_type = $params["user_type"];

        if($req_type=="email") {
            if ($email == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00211') /*Email cannot be empty*/);
            } else {

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00543') /*Please enter a valid email address.*/);
                }

            }

            $db->where('email', $email);

        } else {
            if ($mobile == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00227') /*Mobile cannot be empty*/);
            }

            $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
            $mobile = str_replace("-", "", $mobileNumberInfo["phone"]);

            $db->where('username', $mobile);
        }

        $db->where('register_site', $source);
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
        }

        $req_data = array(
            "mobile" => $mobile,
            "email" => $email,
            "req_type" => $req_type,
            "company_name" => $source,
            "language" => 0,
            "device" => $user_agent,
            "ip" => $ip,
            "user_type" => $user_type
        );

        $return =  $xunUser->register_verifycode_get($req_data);
     
        return $return;

    }

    public function get_pay_user_verify_code($params, $ip= null, $user_agent= null) {

        global $config, $post, $xunBusiness, $xunUser;
        $db = $this->db;
        $general = $this->general;

        $req_type = $params['req_type'];
        $email  = $params["email"];
        $mobile = $params["mobile"];
        $source = $params["source"];
        // $email = $params["email"];

        if($req_type=="email") {
            if ($email == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00211') /*Email cannot be empty*/);
            } else {

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00543') /*Please enter a valid email address.*/);
                }

            }

            $db->where('email', $email);

        } else {
            if ($mobile == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00227') /*Mobile cannot be empty*/);
            }

            $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
            $mobile = str_replace("-", "", $mobileNumberInfo["phone"]);

            $db->where('username', $mobile);
        }
        
        $db->where('type', 'business');
        $db->where('register_site', $source);
        $xun_user = $db->getOne('xun_user');

        if($xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00228') /*User already exist.*/);
        }
       
        $req_data = array(
            "mobile" => $mobile,
            "email" => $email,
            "req_type" => $req_type,
            "company_name" => $source,
            "language" => 0,
            "device" => $user_agent,
            "ip" => $ip,
        );

        $return =  $xunUser->register_verifycode_get($req_data);
     
        return $return;
  
    }

    public function validate_pay_user_verify_code($params, $ip, $user_agent) {

        global $config, $post, $xunBusiness, $xunUser;
        $db = $this->db;
        $general = $this->general;

        $mobile = $params["mobile"];
        $verifyCode = $params["verify_code"];

        // $name = $params["name"];
        // $email = $params["email"];
        // $password = $params["password"];

        if ($mobile == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00227') /*Mobile cannot be empty*/);
        }

        if ($verifyCode == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00229')/*Verify Code cannot be empty*/);
        }

        // if ($name == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Name cannot be empty");
        // }

        // if ($email == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Email cannot be empty");
        // }

        // if ($password == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Password cannot be empty");
        // }

        // if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Please enter a valid email address.");
        // }

        $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
        $mobile = str_replace("-", "", $mobileNumberInfo["phone"]);

        $new_params["companyName"] = "NuxPay";
        $new_params["mobile"] = $mobile;
        $new_params["verify_code"] = $verifyCode;

        $db->where('type', 'user');
        $db->where("username", $mobile);
        $xun_user = $db->getOne("xun_user");
        $new_params["user_check"] = 0;
        if (!$xun_user){
            $user_result = $xunUser->register_verifycode_verify($new_params, $ip, $user_agent);
            if ($user_result['code'] != 1)
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00230')/*SMS Code Verify Failed.*/);
            
            $erlang_params['username'] = $mobile;
            $erlang_post = $post->curl_post("user/register", $erlang_params);
            if ($erlang_post["code"] == 0)
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00231')/*Register New User Failed*/);
        }
        
        return $xunBusiness->business_mobile_verifycode_verify($new_params, "nuxpay");

        // $db->where("email", $email);
        // $xun_business = $db->getOne('xun_business');

        // if ($xun_business){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Email already in use.");
	    // }else {
        //     $db->where("is_valid", 1);
        //     $db->where("expires_at", date("Y-m-d H:i:s"), ">=");
        //     $db->where("mobile", $mobile);
        //     $db->orderby("id", "desc");
        //     $xun_verify_code = $db->getOne('xun_user_verification');

        //     if (!$xun_verify_code){
        //         return array('code' => 0, 'error_code' => -102, 'message' => "FAILED", 'message_d' => "Please request for new a verification code.");
        //     }else {
        //         if($xun_verify_code["is_verified"] == 1) {
        //             return array('code' => 0, 'error_code' => -102, 'message' => "FAILED", 'message_d' => "Please request for new a verification code.");
        //         } else {}
        //             $verify_code = $xun_verify_code["verification_code"];
        //             if($verify_code == $verifyCode) {
        //                 $msg = "Verification code verified.";
        //                 $insertData = array(
        //                     "mobile" => $xun_verify_code["mobile"],
        //                     "verification_code" => $xun_verify_code["verification_code"],
        //                     "expires_at" => $xun_verify_code["expires_at"],
        //                     "verify_at" => date("Y-m-d H:i:s"),
        //                     "is_verified" => 1,
        //                     "is_valid" => 1,
        //                     "status" => "success",
        //                     "country" => $xun_verify_code["country"],
        //                     "message" => $msg,
        //                     "sms_message_content" => "",
        //                     "device_os" => "",
        //                     "os_version" => "",
        //                     "phone_model" => "",
        //                     "user_type" => $xun_verify_code["user_type"],
        //                     "match" => $xun_verify_code["match"],
        //                     "created_at" => date("Y-m-d H:i:s")
        //                 );
		// 	            $id = $db->insert('xun_user_verification', $insertData);
        //                 $db->where("username", $mobile);
        //                 $db->where("type", "user");
        //                 $xun_user = $db->getOne('xun_user');

        //                 if (!$xun_user){
        //                     $new_params = [];
        //                     $new_params["username"] = $mobile;
        //                     $curl_return = $post->curl_post("user/register", $new_params);
        //                     $curl_code = $curl_return["code"];

        //                     if ($curl_code == 1) {
        //                         $insertUserData = array(
        //                             "username" => $mobile,
        //                             "server_host" => $config["server"],
        //                             "type" => "user",
        //                             "nickname" => $name,
        //                             "email" => "",
        //                             "language" => 0,
        //                             "disabled" => 0,
        //                             "disable_type" => "",
        //                             "web_password" => "",
        //                             "created_at" => date("Y-m-d H:i:s"),
        //                             "updated_at" => date("Y-m-d H:i:s")
        //                         );
        //                         $id = $db->insert('xun_user', $insertUserData);
        //                     }
        //                 }
		// 	            $insertBusinessUserData = array(
        //                     "username" => "",
        //                     "server_host" => $config["server"],
        //                     "type" => "business",
        //                     "nickname" => $name,
        //                     "email" => "",
        //                     "language" => 0,
        //                     "disabled" => 0,
        //                     "disable_type" => "",
        //                     "web_password" => "",
        //                     "created_at" => date("Y-m-d H:i:s"),
        //                     "updated_at" => date("Y-m-d H:i:s")
        //                 );
        //                 $buid = $db->insert('xun_user', $insertBusinessUserData);
        //                 $insertBusinessData = array(
        //                     "user_id" => $buid,
        //                     "email" => $email,
        //                     "name" => $name,
        //                     "created_at" => date("Y-m-d H:i:s"),
        //                     "updated_at" => date("Y-m-d H:i:s")                                        
        //                 );
		// 	            $bid = $db->insert('xun_business', $insertBusinessData);
		// 	            $hash_password = password_hash($password, PASSWORD_BCRYPT);
        //                 $insertBusinessAccountData = array(
        //                     "user_id" => $buid,
        //                     "email" => $email,
        //                     "password" => $hash_password,
        //                     "email_verified" => 0,
        //                     "main_mobile" => $mobile,
        //                     "main_mobile_verified" => 1,
        //                     "status" => 1,
        //                     "created_at" => date("Y-m-d H:i:s"),
        //                     "updated_at" => date("Y-m-d H:i:s")                                        
        //                 );
        //                 $baid = $db->insert('xun_business_account', $insertBusinessAccountData);
        //                 return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'User successfully registered');
        //             } else {
        //                 $msg = "The code you entered is incorrect. Please try again.";
        //                 $insertData = array(
        //                     "mobile" => $xun_verify_code["mobile"],
        //                     "verification_code" => $xun_verify_code["verification_code"],
        //                     "expires_at" => $xun_verify_code["expires_at"],
        //                     "verify_at" => date("Y-m-d H:i:s"),
        //                     "is_verified" => 0,
        //                     "is_valid" => 0,
        //                     "status" => "failed",
        //                     "country" => $xun_verify_code["country"],
        //                     "message" => $msg,
        //                     "sms_message_content" => "",
        //                     "device_os" => "",
        //                     "os_version" => "",
        //                     "phone_model" => "",
        //                     "user_type" => $xun_verify_code["user_type"],
        //                     "match" => $xun_verify_code["match"],
        //                     "created_at" => date("Y-m-d H:i:s")
        //                 );
        //                 $id = $db->insert('xun_user_verification', $insertData);
        //                 return array('code' => 0, 'error_code' => -100, 'message' => "FAILED", 'message_d' => $msg);
        //             }
        //         }
        //     }
        // }

    }

    public function pay_login($params, $ip, $user_agent) {

        global $xun_numbers, $xunUser, $xunXmpp, $xunPaymentGateway, $xunCrypto;
        $db = $this->db;
        $general = $this->general;


        $emailMobile = trim($params["emailMobile"]);
        $password = trim($params["password"]);
        $time_zone = trim($params["time_zone"]);
        $source = trim($params["source"]);
        $mode = trim($params["mode"]);

        if($emailMobile=="") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00545')/*Email or mobile cannot be empty*/);
        } else if ($password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00232')/*Password cannot be empty*/);
        }

        if($mode=="email") {
            if (!filter_var($emailMobile, FILTER_VALIDATE_EMAIL)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00543') /*Please enter a valid email address.*/);
            }
        }

       
        if ($time_zone == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00233')/*Time zone cannot be empty.*/, "developer_msg" => "time_zone cannot be empty");
        };

        // $db->where("a.status", 1);
        // $db->where("a.email", $email);
        // $db->join("xun_business b", "a.user_id=b.user_id", "INNER");
        // $xun_business = $db->getOne('xun_business_account a', 'a.password, a.user_id, a.main_mobile, a.email_verified, b.name');

        $db->where("((a.username='".$emailMobile."' AND b.main_mobile_verified=1) OR (a.email='".$emailMobile."' AND b.email_verified=1)) ");
        $db->where('a.register_site', $source);
        $db->where('b.main_mobile=a.username');
        $db->where('b.email=a.email');
        $db->join('xun_business_account b' , 'a.id = b.user_id', 'INNER');
        $xun_business = $db->getOne('xun_user a', 'a.id, a.nickname, a.type, b.password, b.main_mobile, b.email, b.account_type, a.created_at');
        
        if (!$xun_business){
            if($mode=="email") {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Your email or password is incorrect. Please try again.');
            } else {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Your mobile or password is incorrect. Please try again.');
            }
            
        }else {
            $verify_password = $xun_business["password"];
            $user_id = $xun_business["id"];

            if (!password_verify($password, $verify_password)) {
                if($mode=="email") {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Your email or password is incorrect. Please try again.');
                } else {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => 'Your mobile or password is incorrect. Please try again.');
                }
                
            } else {
                $db->where("business_id", $user_id);
                $firstTimeFundOut = $db->getOne('blockchain_external_address');
                if(!$firstTimeFundOut){
                    $hasSetFundOutAddress = "false";
                } else{
                    $hasSetFundOutAddress = "true";
                }

                $now = date("Y-m-d H:i:s");
                if (!$xun_business["time_zone"] || $xun_business["time_zone"] == "") {
                    $update_xun_business_account["time_zone"] = $time_zone;
                    $update_xun_business_account["updated_at"] = $now;
                }

                $update_xun_business_account["last_login"] = $now;
                $db->where("user_id", $user_id);
                $db->update("xun_business_account", $update_xun_business_account);
        
                $db->where("user_id", $user_id);
                $business = $db->getOne("xun_business");
                $business_id = $business["user_id"];

                $access_token = $general->generateAlpaNumeric(32);

                $updateData["status"] = 0;
                $db->where("business_id", $business_id);
                $db->update("xun_access_token", $updateData);

                $access_token_expires_at = date("Y-m-d H:i:s", strtotime('+12 hours', strtotime(date("Y-m-d H:i:s"))));

                $fields = array("business_email", "business_id", "access_token", "expired_at");
                $values = array('', $business_id, $access_token, $access_token_expires_at);

                $insertData = array_combine($fields, $values);

                $row_id = $db->insert("xun_access_token", $insertData);

                $db->where('user_id', $user_id);
                $db->where('name', 'hasChangedPassword');
                $changedPassword = $db->getValue('xun_user_setting', 'value');

                $db->where('user_id', $user_id);
                $db->where('address_type', 'nuxpay_wallet');
                $db->where('active', 1);
                $crypto_user_address = $db->getOne('xun_crypto_user_address');

                $internal_address = $crypto_user_address['address'];

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

                $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'INNER');
                $db->orderBy("a.sequence", "ASC");
                $xun_coins = $db->get('xun_coins a', null, 'b.name, b.currency_id, b.image, b.symbol');

                foreach($xun_coins as $coin_key => $coin_value){
                    $wallet_type = $coin_value['currency_id'];
                    $wallet_external_address = $xunCrypto->get_external_address($internal_address, $wallet_type);

                    $fund_in_address_list[$wallet_type] = $wallet_external_address;
 
                }

                $returnData = array(
                    "business_id" => $business_id,
                    "mobile" => $xun_business["main_mobile"] ? $xun_business["main_mobile"] : "", 
                    "email" => $xun_business["email"] ? $xun_business["email"] : "", 
                    "name" => $xun_business["nickname"],
                    "hasSetFundOutAddress" => $hasSetFundOutAddress, 
                    "access_token" => $access_token,
                    "picture_url" => $business['profile_picture_url'] ? $business['profile_picture_url'] : '',
                    "registerDate" => $xun_business["created_at"],
                    "hasChangedPassword" => $changedPassword,
                    "account_type" => $xun_business["account_type"],
                    "fund_in_address_list" => $fund_in_address_list
                );

                $update = $xunPaymentGateway->update_user_setting($business_id, $ip, $user_agent);

                $user_country_info_arr = $xunUser->get_user_country_info([$emailMobile]);
                $user_country_info = $user_country_info_arr[$emailMobile];
                $user_country = $user_country_info["name"];

                $message = "Username: " .$xun_business["nickname"]. "\n";
                $message .= "Email/Mobile: " .$emailMobile. "\n";
                $message .= "IP: " . $ip . "\n";
                $message .= "Country: " . $user_country . "\n";
                $message .= "Device: " . $user_agent . "\n";
                $message .= "Type of user: " .$xun_business["type"] . "\n";
                $message .= "Time: " . date("Y-m-d H:i:s");
        
                $thenux_params["tag"] = "Login";
                $thenux_params["message"] = $message;
                $thenux_params["mobile_list"] = array();
                //$thenux_result = $general->send_thenux_notification($thenux_params, "thenux_pay");

                return array('code' => 1, 'message' => "Success", 'message_d' => $this->get_translation_message('B00114') /*User credentials verified.*/, 'user' => $returnData);
            }
        }
    }

    public function get_bind_user_account($params, $ip, $user_agent) {

        global $xunUser;
        $db = $this->db;
        $general = $this->general;

        $user_id = trim($params["user_id"]);
        $req_type = trim($params["req_type"]);
        $email = trim($params["email"]);
        $mobile = trim($params["mobile"]);
        $verify_code = trim($params["verify_code"]);
        $source = trim($params['source']);
        
        // Param validations
        if($req_type=="email") {
            if ($email == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00211') /*Email cannot be empty*/);
            }
        } else {
            if ($mobile == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00227') /*mobile cannot be empty*/);
            }
        }

        if ($verify_code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00229')/*Verify code cannot be empty*/);
        }


        $db->where("a.user_id", $user_id);
        $db->join("xun_user u", "u.id=a.user_id", "INNER");
        $businessAccountDetail = $db->getOne("xun_business_account a", "a.main_mobile_verified, a.email_verified, u.register_site");

        if($businessAccountDetail) {

            $register_site = $businessAccountDetail['register_site'];

            if($req_type=="email") {

                $email_verified = $businessAccountDetail['email_verified'];

                if($email_verified) {

                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00547') /*You are not allowed to perform this action.*/);

                } else {

                    if ($email == '') {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00211') /*Email cannot be empty*/);
                    } else {

                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00543') /*Please enter a valid email address.*/);
                        }

                    }


                    //CHECK MOBILE EXIST IN DB
                    $db->where("a.email_verified", 1);
                    $db->where("a.email", $email);
                    $db->where("u.register_site", $register_site);
                    $db->join("xun_user u", "u.id=a.user_id", "INNER");
                    $checkEmailExist = $db->getOne("xun_business_account a");

                    if($checkEmailExist) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00228') /*User already exist.*/);
                    }

                }

            } else {

                $main_mobile_verified = $businessAccountDetail['main_mobile_verified'];

                if($main_mobile_verified) {

                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00547') /*You are not allowed to perform this action.*/);

                } else {
                    
                    if ($mobile == '') {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00227') /*Mobile cannot be empty*/);
                    }

                    $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
                    $mobile = str_replace("-", "", $mobileNumberInfo["phone"]);

                    if(!$mobileNumberInfo['isValid']) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00217')/*Please enter a valid phone number.*/, "errorCode" => -102);
                    }
                    

                    //CHECK MOBILE EXIST IN DB
                    $db->where("a.main_mobile_verified", 1);
                    $db->where("a.main_mobile", $mobile);
                    $db->where("u.register_site", $register_site);
                    $db->join("xun_user u", "u.id=a.user_id", "INNER");
                    $checkMobileExist = $db->getOne("xun_business_account a");

                    if($checkMobileExist) {
                        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00228') /*User already exist.*/);
                    }

                }

            }


            
            if($req_type=="email") {
                $verify_code_return = $xunUser->verify_code($email, $verify_code, $ip, $user_agent, "Exist", $register_site, "email");
            } else {
                $verify_code_return = $xunUser->verify_code($mobile, $verify_code, $ip, $user_agent, "Exist", $register_site, "mobile");
            }

            if ($verify_code_return["code"] === 0) {
                return $verify_code_return;
            }

            if($req_type=="email") {

                $db->where("id", $user_id);
                $db->update("xun_user", array("email"=>$email, "email_verified"=>1, "updated_at"=>date("Y-m-d H:i:s") ));

                $db->where("user_id", $user_id);
                $db->update("xun_business_account", array("email"=>$email, "email_verified"=>1, "updated_at"=>date("Y-m-d H:i:s") ));


            } else {

                $db->where("id", $user_id);
                $db->update("xun_user", array("username"=>$mobile, "updated_at"=>date("Y-m-d H:i:s") ));

                $db->where("user_id", $user_id);
                $db->update("xun_business_account", array("main_mobile"=>$mobile, "main_mobile_verified"=>1, "updated_at"=>date("Y-m-d H:i:s") ));

            }


            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00323') /*Account successfully bind. */);
            

        } else {

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00202') /*User does not exist.*/);
        }


    }

    public function pay_register($params, $ip, $user_agent, $rid)
    {
        global $post, $xunCrypto;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        // $post = $this->post;

        $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);

        global $config, $xunBusiness, $post, $xunUser, $xunXmpp, $xunPaymentGateway;

        $req_type = trim($params["req_type"]);
        $email = trim($params["email"]);
        $mobile = trim($params["mobile"]);
        $password = trim($params["pay_password"]);
        $retype_password = trim($params["pay_retype_password"]);
        $verify_code = trim($params["verify_code"]);
        $nickname = trim($params["nickname"]);
        $referral_code = trim($params["referral_code"]);
        $type = trim($params["type"]);
        $source = trim($params["source"]);
        $content = trim($params["content"]);
        $reseller_code = trim($params["reseller_code"]);
        $signup_type = trim($params["signup_type"]);
        //$rid = trim($params['rid']);

        if($req_type=="email") {
            $mobile = "";
        } else {
            $email = "";
        }

        // Param validations
        if($req_type=="email") {
            if ($email == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00211') /*Email cannot be empty*/);
            }
        } else {
            if ($mobile == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00227') /*mobile cannot be empty*/);
            }
        }
        
        if ($verify_code == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00229')/*Verify code cannot be empty*/);
        }
        if ($password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00232')/*Password cannot be empty*/);
        }
        if ($retype_password == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00236')/*Retype Password cannot be empty*/);
        }
        if ($nickname == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00237')/*Nickname cannot be empty*/);
        }
        if ($type == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00238')/*Type cannot be empty*/);
        }
        if ($reseller_code != "") {
            $db->where('referral_code', $reseller_code);
            $db->where('source', $source);
            $reseller_merchant = $db->getOne('reseller', "id, marketer_id");
            
            if(!$reseller_merchant){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00542')/*Reseller does not exist.*/, "developer_msg" => $db->getLastError(),'a'=>$rid);
            } else {
                $reseller_id = $reseller_merchant['id'];
                $marketerId = $reseller_merchant['marketer_id'];
            }
        } else {
            $reseller_id = 0;
        }


        $register_through = empty($content) ? "Normal Register" : $content;

        if($req_type=="mobile") {
            $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
            $mobile = str_replace("-", "", $mobileNumberInfo["phone"]);
        }
        

        $new_params["companyName"] = $source;
        $new_params["req_type"] = $req_type;
        $new_params["email"] = $email;
        $new_params["mobile"] = $mobile;
        $new_params["verify_code"] = $verify_code;
        $new_params["nickname"] = $nickname;
        $new_params["user_check"] = 0;
        $new_params["content"] = $register_through;

        if($rid != "") {

            $db->where("id", $rid);
            $db->where("source", $source);
            $resellerDetail = $db->getOne("reseller");

            if(!$resellerDetail) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => $db->getLastError(),'a'=>$rid);
            } else {
                $resellerId = $resellerDetail['id'];
                $marketerId = $resellerDetail['marketer_id'];
            }

        } else {
            $resellerId = 0;
            $marketerId = 0;
        }
	    

        $db->where('register_site', $type);
        $db->where('type', 'business');

        if($req_type=="email") {
            $db->where("email", $email);
            $result = $db->getOne("xun_user");
            if ($result) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00544')/*"An account already exists with this email address. Please select another email address."*/);
            }

        } else {
            $db->where("username", $mobile);
            $result = $db->getOne("xun_user");
            if ($result) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00239')/*"An account already exists with this phone number. Please select another phone number."*/);
            }

        }
        
        if($req_type=="email") {
            $verify_code_return = $xunUser->verify_code($email, $verify_code, $ip, $user_agent, "New", $source, "email");
        } else {
            $verify_code_return = $xunUser->verify_code($mobile, $verify_code, $ip, $user_agent, "New", $source, "mobile");
        }
        

        if ($verify_code_return["code"] === 0) {
            return $verify_code_return;
        } else {
            if($req_type=="email") {
                $email_verified = 1;
                $mobile_verified = 0;
            } else {
                $email_verified = 0;
                $mobile_verified = 1;
            }
        }
       
       
        // Password validation
        $validate_password = $this->validate_password($password, $retype_password);

        if ($validate_password['code'] == 0) {
            $error_message = $validate_password['error_message'];
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00240')/*Invalid password combination.*/, "developer_msg" => "password has an invalid character combination", "error_message" => $error_message);

        }
        $password = password_hash($password, PASSWORD_BCRYPT);

        $created_at = date("Y-m-d H:i:s");
        $server = $config["server"];
        
        $service_charge_rate = $setting->systemSetting["theNuxCommissionFeePct"];

        $insertUserData = array(
            "username" => $mobile,
            "email" => $email,
            "email_verified" => $email_verified,
            "server_host" => $server,
            "type" => "business",
            "register_site" => $type,
            "register_through" => $register_through,
            "nickname" => $nickname,
            // "reseller_id" => $resellerId,
            "reseller_id" => $reseller_id,
            "web_password" => $password,
            "created_at" => $created_at,
            "updated_at" => $created_at,
            "service_charge_rate" => $service_charge_rate
        );

        // create nuxpay user
        $user_id = $db->insert("xun_user", $insertUserData);
        if(!$user_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00241')/*Failed to create account*/, 'developer_message' => $db->getLastError());
        }

        // Insert user setting - changed password
        $insertData = array(
            'user_id' => $user_id,
            'name' => 'hasChangedPassword',
            'value' => ($signup_type == 'requestFund' || $signup_type == 'landingPage' || $signup_type == 'sendFund') ? '0' : (($signup_type == 'newSignup') ? '2' : '1'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        $db->insert('xun_user_setting', $insertData);

        $fields = array("user_id", "email" ,"password", "email_verified", "main_mobile", "main_mobile_verified", "referral_code", "created_at", "updated_at");
        $values = array($user_id, $email, $password, $email_verified, $mobile, $mobile_verified, $referral_code, $created_at, $created_at);
        $arrayData = array_combine($fields, $values);
        $db->insert("xun_business_account", $arrayData);

        // // Insert User setting - showWallet
        $db->where("is_payment_gateway", 1);
        $xun_coins = $db->get('xun_coins', null, 'currency_id');
        $coin_list = array_column($xun_coins,'currency_id');

        $insertArray = array(
            'user_id' => $user_id,
            'name' => 'showWallet',
            'value' => json_encode($coin_list),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
            
        );
        $db->insert('xun_user_setting', $insertArray); //update user setting

        // // Insert User setting - showWallet
        $db->where("is_payment_gateway", 1);
        $xun_coins = $db->get('xun_coins', null, 'currency_id');
        $coin_list = array_column($xun_coins,'currency_id');

        $insertWalletArray = array(
            'user_id' => $user_id,
            'name' => 'showNuxpayWallet',
            'value' => json_encode($coin_list),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
            
        );
        $db->insert('xun_user_setting', $insertWalletArray); //update user setting

        // Insert user setting - changed password
        $insertArray = array(
            'user_id' => $user_id,
            'name' => 'allowSwitchCurrency',
            'value' => '0',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
            
        );
        $db->insert('xun_user_setting', $insertArray); //update user setting

        // // create business
        $insertBusinessData = array(
            "user_id" => $user_id,
            "name" => $nickname,
            "created_at" => $created_at,
            "updated_at" => $created_at
        );

        $business_details_id = $db->insert("xun_business", $insertBusinessData);
        if (!$business_details_id)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => $db->getLastError());
     

        
        //$business_verify =  $xunBusiness->business_mobile_verifycode_verify($new_params, $type);

        $access_token = $general->generateAlpaNumeric(32);

        $access_token_expires_at = date("Y-m-d H:i:s", strtotime('+12 hours', strtotime(date("Y-m-d H:i:s"))));

        $fields = array("business_email", "business_id", "access_token", "expired_at");
        $values = array('', $business_id, $access_token, $access_token_expires_at);

        $insertData = array_combine($fields, $values);

        $row_id = $db->insert("xun_access_token", $insertData);

        $returnData = array(
            "business_id" => $user_id,
            "mobile" => $mobile, 
            "email" => $email,
            "name" => $nickname, 
            "access_token" => $access_token,
            "registerDate" => $created_at,
            "hasChangedPassword" => ($signup_type == 'requestFund' || $signup_type == 'landingPage') ? '0' : (($signup_type == 'newSignup') ? '2' : '1'),
            "account_type" => 'premium',
        );

        $xunPaymentGateway->update_user_setting($user_id, $ip, $user_agent);


        if($marketerId > 0) {

            $db->where("marketer_id", $marketerId);
            $marketerDetail = $db->get("xun_marketer_destination_address");
            
            foreach($marketerDetail as $mDetail) {

                $marketerSchemeData['business_id'] = $user_id;
                $marketerSchemeData['marketer_id'] = $marketerId;
                $marketerSchemeData['destination_address'] = $mDetail['destination_address'];
                $marketerSchemeData['wallet_type'] = $mDetail['wallet_type'];
                $marketerSchemeData['commission_rate'] = $mDetail['commission_rate'];
                $marketerSchemeData['transaction_type'] = $mDetail['transaction_type'];
                $marketerSchemeData['disabled'] = 0;
                $marketerSchemeData['created_at'] = date("Y-m-d H:i:s");

                $db->insert("xun_business_marketer_commission_scheme", $marketerSchemeData);

            }

        }

        $wallet_return = $xunCompanyWallet->createUserServerWallet($user_id, 'nuxpay_wallet', '');

        $internal_address = $wallet_return['data']['address'];
        
        $insertAddress = array(
            "address" => $internal_address,
            "user_id" => $user_id,
            "address_type" => 'nuxpay_wallet',
            "active" => '1',
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $inserted = $db->insert('xun_crypto_user_address', $insertAddress);

        if(!$inserted){
            return array('code' => 0, 'status' => 'error', 'statusMsg' => $this->get_translation_message('E00242')/*Something went wrong.*/, "developer_msg" => $db->getLastQuery());
        }

        $db->where("a.is_default", 1);
        $db->join('xun_marketplace_currencies b', 'a.currency_id = b.currency_id', 'INNER');
        $db->orderBy("a.sequence", "ASC");
        $xun_coins = $db->get('xun_coins a', null, 'b.name, b.currency_id, b.image, b.symbol');

        foreach($xun_coins as $coin_key => $coin_value){
            $wallet_type = $coin_value['currency_id'];
            $wallet_external_address = $xunCrypto->get_external_address($internal_address, $wallet_type);

            $fund_in_address_list[$wallet_type] = $wallet_external_address;
        }
        
        $returnData['fund_in_address_list'] = $fund_in_address_list;
        
        $user_country_info_arr = $xunUser->get_user_country_info([$mobile]);
        $user_country_info = $user_country_info_arr[$mobile];
        $user_country = $user_country_info["name"];

        $message = "Username: " .$nickname. "\n";
        if($req_type=="email") {
            $message .= "Email address: " .$email. "\n";
        } else {
            $message .= "Phone number: " .$mobile. "\n";
        }
        $message .= "IP: " . $ip . "\n";
        $message .= "Country: " . $user_country . "\n";
        $message .= "Device: " . $user_agent . "\n";
        $message .= "Type of user: " .$insertUserData["type"] . "\n";
        $message .= "Time: " . date("Y-m-d H:i:s");

        $thenux_params["tag"] = "Login";
        $thenux_params["message"] = $message;
        $thenux_params["mobile_list"] = array();
        
        //PENDING0818 - ok
        $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_pay");
        
        //print_r($curl_return);   
        $message_d = $this->get_translation_message('B00115');
        $message_d = str_replace("%%companyName%%", $type, $message_d);
        
        //post_man
        // $test = array("code" => 0, "status" => "ok", "statusMsg" => $this->get_translation_message('B00307') /*Successfully Sign Up*/, 'data' => $returnData);

        // echo json_encode($test);
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $message_d/*"NuxPay Account successfully registered."*/, "data" => $returnData);

    }

    public function validate_password($password, $confirm_password)
    {
        // if (preg_match("/^.*(?=.{4,})(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).*$/", $password) === 0) {
        // $error_message = array("- Minimum 4 characters", "- At least 1 alphabet", "- At least 1 numeric", "- At least 1 capital letter");
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid password combination.", "developer_msg" => "business_password has an invalid character combination", "error_message" => $error_message);
        // }

        $length = strlen($password);
        if($password != $confirm_password){
        return array('code' => 0, "error_message" => $this->get_translation_message('E00243')/*Password not match.*/);
        }

        if ($length < 4) {
            $error_message = array("- Minimum 4 characters");
            return array('code' => 0, "error_message" => $error_message);
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            return array('code' => 1, "hashed_password" => $hashed_password);
        }
    }

    public function send_activation_email($business_email, $business_name, $verification_code)
    {
        $general = $this->general;

        global $setting, $xunEmail;
        // $companyName = $setting->systemSetting["companyName"];
        $companyName = "NuxPay";
        // $server = "nuxpay.com";
        // $button_gradient = "to right, #51c2c6 0%, #51c2db 100%";
        // $logoPath = "https://nuxpay.com/images/thenuxWhiteLatest_Pay_logo.svg";

        // $email_body = $xunEmail->getActivationEmailHtml($business_name, $verification_code, $business_email, $include_email, $companyName, $server, $button_gradient, $logoPath);
        $email_body = $xunEmail->getNuxPayActivationEmailHtml($business_name, $verification_code, $business_email, $companyName);
        $translations_message = "Activate your email at %%companyName%%.";
        $subject = str_replace("%%companyName%%", $companyName, $translations_message);

        $emailParams["subject"] = $subject;
        $emailParams["body"] = $email_body;
        $emailParams["recipients"] = array($business_email);
        $emailParams["emailFromName"] = "NuxPay";
        $emailParams["emailPassword"] = "nuxpay0909";
        $emailParams["emailAddress"] = "support@nuxpay.com";

        $result = $general->sendEmail($emailParams);
        return $result;
    }


    public function pay_verify_code($params)
    {
        $db = $this->db;

        global $config;

        $verify_code = trim($params["verify_code"]);

        if ($verify_code == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00229')/*Verify code cannot be empty.*/);
        };

        $now = date("Y-m-d H:i:s");

        $db->where("verification_code", $verify_code);
        $result = $db->getOne("xun_email_verification");

        if (!$result) {
            $error_message = $this->get_translation_message('E00244')/*Invalid activation link.*/;
            $errorCode = -102;
            $title = "Error Activating Account.";

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "errorCode" => $errorCode, "title" => $title);
        }

        // check if is expired
        // code expires in 2 days
        $expired_at = date("Y-m-d H:i:s", strtotime('+2 days', strtotime($result["created_at"])));

        if ($expired_at < $now) {
            $error_message = $this->get_translation_message('E00245') /*Your activation link has expired. Please request a new activation link.*/;
            $errorCode = -101;
            $title = "Activation Link Has Expired";

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "errorCode" => $errorCode, "title" => $title);
        }
        // if success
        // update xun_business_account
        $email = $result["email"];
        $db->where("email", $email);
        $xun_business_account = $db->getOne("xun_business_account");

        if ($xun_business_account["email_verified"] == 1) {
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00139')/*NuxPay account verified.*/, "title" => "Account Successfully Activated");
        } else {
            $updateData["email_verified"] = 1;
            $updateData["updated_at"] = $now;
            $db->where("email", $email);
            $db->update("xun_business_account", $updateData);

            $updateVerificaton["verified_at"] = date("Y-m-d H:i:s");
            $db->where("id", $result['id']);
            $db->update("xun_email_verification", $updateVerificaton);

            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00139')/*NuxPay account verified.*/, "title" => "Account Successfully Activated");
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

    public function get_redemption_main_page_listing($params){
        global $config;
        $env = $config["environment"];

        $db = $this->db;

        $username = trim($params["username"]);
        $country_code = $params["country_code"];

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        if (is_array($country_code)) {
            // if(empty($country_code)){
            //     return array("code" => 0, "message" => "FAILED", "message_d" => "Please select a country.");
            // }

            $country_code_arr = array_map(function ($v) {
                return strtolower($v);
            }, $country_code);
        } else if (!is_array($country_code)){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00491') /*Country Code must be an array.*/);
        }else {
            if (trim($country_code) == '') {
                $country_code_arr = [];   
            }
        }


        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00202') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];

        $device_info_obj = new stdClass();
        $device_info_obj->username = $username;

        $xun_device_info = $xun_user_service->getDeviceInfo($device_info_obj);

        $is_old_version = false;
        if ($xun_device_info) {
            $os = $xun_device_info["os"];
            $app_version = $xun_device_info["app_version"];
            $app_version = str_replace("(", ".", $app_version);
            $app_version = str_replace(")", "", $app_version);

            $min_android_version = '1.0.222.3';
            $min_ios_version = '1.0.160';

            if ($os == 1 && version_compare($min_android_version, $app_version) > 0) {
                $is_old_version = true;
            } else if ($os == 2 && version_compare($min_ios_version, $app_version) > 0) {
                $is_old_version = true;
            }
        }

        $xun_pay_service = new XunPayService($db);

        $list_limit = 10;

        $pay_obj = new StdClass();
        $pay_obj->userID = $user_id;
        $pay_obj->countryIsoCode2 = $country_code_arr;
        $pay_obj->limit = $list_limit;

        // $frequently_used_product_arr = $xun_pay_service->getFrequentlyUsedProductList($pay_obj, "product_id");

        // $db->where("a.user_id", $user_id);
        // $db->where("b.active", 1);
        // $db->join("xun_pay_product b", "a.product_id=b.id", "LEFT");

        // $db->groupBy("a.product_id");
        // $frequently_used_product_arr = $db->get("xun_pay_transaction a", $list_limit, "product_id");
        $db->where("user_id", $user_id);
        $total_redemption = $db->getValue("xun_pay_transaction", "count(id)");

        $popular_prod_obj = new StdClass();
        $popular_prod_obj->userID = $user_id;
        $popular_prod_obj->countryIsoCode2 = $country_code_arr;
        $popular_prod_obj->isOldVersion = $is_old_version;

        $popular_limit = $list_limit;
        $popular_columns = "id as product_id, name, image_url, image_md5, currency_code";
        $popular_product_arr = $xun_pay_service->getPopularProductByCountryCode($popular_prod_obj, $popular_limit, $popular_columns);
        // select * from xun_pay_product where country code = my order by

        // $top_type_arr = $xun_pay_service->getTopProductTypeByCountyCode($pay_obj, "product_type_id");

        $product_id_arr = [];
        $product_type_id_arr = [];

        // if (!empty($frequently_used_product_arr)) {
        //     $product_id_column = array_column($frequently_used_product_arr, "product_id");
        //     $product_id_arr = array_merge($product_id_arr, $product_id_column);
        // }

        if (!empty($popular_product_arr)) {
            $product_id_column = array_column($popular_product_arr, "product_id");
            $product_id_arr = array_merge($product_id_arr, $product_id_column);
        }

        if ($is_old_version == true) {
            $product_type_id_arr = [1, 2];
        } else {
            $product_type_id_arr = [1, 2, 3];
        }

        $product_listing_obj = new stdClass();
        $product_listing_obj->ids = $product_id_arr;
        $product_columns = "id, name, type, image_url, image_md5";

        $xun_product_listing = $xun_pay_service->getProductListingByID($product_listing_obj, true, $product_columns);

        $product_type_listing_obj = null;
        if ($is_old_version === true) {
            $product_type_listing_obj = new stdClass();
            $product_type_listing_obj->ids = $product_type_id_arr;
        }

        $product_type_columns = "id, name, type, image_url, image_md5, language_code";

        $xun_product_type_listing = $xun_pay_service->getProductTypeListingByID($product_type_listing_obj, true, $product_type_columns);

        // $frequently_user_product = $this->compose_product_listing($frequently_used_product_arr, $xun_product_listing);
        // $top_type = $this->compose_product_type_listing($product_type_id_arr, $xun_product_type_listing);
        // $top_type = $this->compose_product_type_listing($top_type_arr, $xun_product_type_listing);
        $popular_product = $this->compose_product_listing($popular_product_arr, $xun_product_listing);
        $categories_arr = $this->compose_product_category_listing($xun_product_type_listing);
        // $type_name = array_column($top_type, 'name');
        // array_multisort($type_name, SORT_ASC, $top_type);

        if(!empty($popular_product)){
            // get total redeemed for each product
            $db->where("product_id", $product_id_arr, "IN");
            $db->groupBy("product_id");
            $db->where("status", array("refunded", "pending"), "NOT IN");
            $total_redeemed = $db->map("product_id")->ArrayBuilder()->get("xun_pay_transaction", null, "product_id, count(id) as total_redeemed");
            
            $db->where("product_id", $product_id_arr, "IN");
            $db->where("amount_type", "max", "!=");
            $db->where("status", "1");
            $db->groupBy("product_id");
            $min_amount_arr = $db->map("product_id")->ArrayBuilder()->get("xun_pay_product_option", null, "product_id, min(amount) as amount, min_price");
            // print_r($min_amount_arr);
            // print_r($db->getLastQuery());
    
            foreach($popular_product as &$product){
                $pid = $product['product_id'];
                $product['total_purchased'] = $total_redeemed[$pid] ? $total_redeemed[$pid] : 0;
                if ($min_amount_arr[$pid]["amount"] != 0){
                    $product['min_amount'] = $min_amount_arr[$pid]["amount"];
                }else if($min_amount_arr[$pid]["min_price"] != 0){
                    $product['min_amount'] = $min_amount_arr[$pid]["min_price"];
                }else{
                    $product['min_amount'] = "0.00";
                }
            }
        }


        $type_name = array_column($xun_product_type_listing, 'name');
        array_multisort($type_name, SORT_ASC, $xun_product_type_listing);

        $product_country_obj = new stdClass();
        $product_country_obj->isOldVersion = $is_old_version;
        $country_iso_code2_arr = $xun_pay_service->getProductCountryByType($product_country_obj);

        $country_arr = empty($country_iso_code2_arr) ? [] : $this->get_country_listing($country_iso_code2_arr);

        $db->orderBy("sort_order", "ASC");
        $db->where("active", "1");
        $banner_display = $db->get("xun_banner_setting", null, "pay_product_id, banner_url, sort_order");

        $return_data = [];
        // $return_data["top_categories"] = $top_type;
        // $return_data["frequently_used_products"] = $frequently_user_product;
        $return_data["popular_products"] = $popular_product;
        $return_data["my_redemption"] = $total_redemption;
        $return_data["banner"] = $banner_display;
        $return_data["countries"] = $country_arr;
        $return_data["categories"] = array_values($categories_arr);
        $return_data["country_code"] = $country_code;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00256')/*Main Page listing.*/, "data" => $return_data);

    }
    
    public function redemption_get_product_listing($params)
    {
        global $country;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $username = trim($params["username"]);
        $type = trim($params["type"]);
        $country_code = $params["country_code"];
        $type_id_arr = $params["type_id"];
        $name = trim($params["name"]);

        $page = trim($params["page"]);
        $page_limit = $setting->systemSetting["appsPageLimit"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = strtoupper(trim($params["order"]));
        $order = ($order == 'ASCENDING' ? "ASC" : ($order == 'DESCENDING' ? "DESC" : "ASC"));

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        $type_is_array = 0;
        if (isset($type_id_arr)) {
            //  array of type_id
            if (!is_array($type_id_arr)) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00200'][$language] /*Something went wrong. Please try again.*/, "dev_msg" => "type_id must be an array");
            }

            $type_is_array = 1;
        } else if ($type == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00201'][$language]/*Type is required.*/);
        }

        $xunUserService = new XunUserService($db);

        $xunUser = $xunUserService->getUserByUsername($username);

        if (!$xunUser) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $translations['E00202'][$language] /*User does not exist.*/);
        }

        $userID = $xunUser["id"];

        $device_info_obj = new stdClass();
        $device_info_obj->username = $username;

        $xun_device_info = $xunUserService->getDeviceInfo($device_info_obj);

        $is_old_version = false;
        if ($xun_device_info) {
            $os = $xun_device_info["os"];
            $app_version = $xun_device_info["app_version"];
            $app_version = str_replace("(", ".", $app_version);
            $app_version = str_replace(")", "", $app_version);

            $min_android_version = '1.0.222.3';
            $min_ios_version = '1.0.160';

            if ($os == 1 && version_compare($min_android_version, $app_version) > 0) {
                $is_old_version = true;
            } else if ($os == 2 && version_compare($min_ios_version, $app_version) > 0) {
                $is_old_version = true;
            }
        }

        $date = date("Y-m-d H:i:s");

        $type_arr = [];
        if ($type_is_array == 0) {
            switch ($type) {
                case "topup":
                    $type_id = 1;
                    break;
                case "utility":
                    $type_id = 2;
                    break;

                case "giftcard":
                // $type_id = 3;
                // break;

                default:
                    // $type_id = 0;
                return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00203'][$language] /*Invalid product type.*/);
                    break;
            }
            $type_arr = [$type_id];
        } else {
            $type_arr = $type_id_arr;
        }

        if (is_array($country_code)) {
            $country_code_arr = array_map(function ($v) {
                return strtolower($v);
            }, $country_code);
        } else {
            $country_code_arr = [trim($params["country_code"])];
        }

        $product_params = [];
        $product_params["type_arr"] = $type_arr;
        $product_params["name"] = $name;
        $product_params["country_iso_code2"] = $country_code_arr;
        $product_params["page"] = $page;
        $product_params["page_size"] = $page_size;
        $product_params["order"] = $order;
        $product_params["is_old_version"] = $is_old_version;

        $xun_pay_service = new XunPayService($db);

        $product_return_data = $xun_pay_service->getProductList($product_params, "id, name, image_url, image_md5, type as type_id, country_iso_code2 as country_code, currency_code");

        $product_arr = $product_return_data["data"];

        if (!empty($product_arr)) {
            $product_id_arr = array_column($product_arr, "id");
            $product_type_obj = new stdClass();
            $product_type_obj->productIdArr = $product_id_arr;
            $product_type_map = $xun_pay_service->getProductTypeMap($product_type_obj);

            $product_type_id_arr = [];
            for ($i = 0; $i < count($product_type_map); $i++) {
                $product_type_data = $product_type_map[$i];
                $data_product_id = $product_type_data["product_id"];
                $data_type_id = $product_type_data["type_id"];

                $product_type_id_arr[$data_product_id][] = $data_type_id;
            }

            for ($i = 0; $i < count($product_arr); $i++) {
                $product_data = $product_arr[$i];
                $product_id = $product_data["id"];
                $product_type_id = $product_type_id_arr[$product_id];
                sort($product_type_id);

                $product_data["type_ids"] = $product_type_id;
                $product_type = $product_data["type_id"];
                $product_data["type_id"] = $product_type == 0 ? $product_type_id[0] : $product_type;

                $product_arr[$i] = $product_data;
            }
            $db->where("product_id", $product_id_arr, "IN");
            $db->groupBy("product_id");
            $db->where("status", array("refunded", "pending"), "NOT IN");
            $total_redeemed = $db->map("product_id")->ArrayBuilder()->get("xun_pay_transaction", null, "product_id, count(id) as total_redeemed");
                
            $db->where("product_id", $product_id_arr, "IN");
            $db->where("amount_type", "max", "!=");
            $db->where("status", "1");
            $db->groupBy("product_id");
            $min_amount_arr = $db->map("product_id")->ArrayBuilder()->get("xun_pay_product_option", null, "product_id, min(amount) as amount, min_price");

            foreach($product_arr as &$product){
                $pid = $product['id'];
                $product['total_transaction'] = $total_redeemed[$pid] ? $total_redeemed[$pid] : 0;
                if ($min_amount_arr[$pid]["amount"] != 0){
                    $product['min_amount'] = $min_amount_arr[$pid]["amount"];
                }else if($min_amount_arr[$pid]["min_price"] != 0){
                    $product['min_amount'] = $min_amount_arr[$pid]["min_price"];
                }else{
                    $product['min_amount'] = "0.00";
                }
            }
        }

        $page_details = $product_return_data["page_details"];

        $return_data = [];
        $return_data["products"] = $product_arr;
        $return_data["total_record"] = $page_details["total_record"];
        $return_data["num_record"] = $page_details["num_record"];
        $return_data["total_page"] = $page_details["total_page"];
        $return_data["page_number"] = $page_details["page_number"];

        if ($type_is_array === 0) {
            $country_iso_code2_arr = $this->get_product_country_by_type($type_arr);

            $country_params = array("iso_code2_arr" => $country_iso_code2_arr);
            $country_data = $country->getCountryDataByIsoCode2($country_params);
            $return_data["countries"] = $this->get_country_info($country_data);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['B00109'][$language] /*Product listing.*/, "data" => $return_data);
    }

    public function redemption_get_product_details($params)
    {
        global $country, $post;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();
        
        $username = trim($params["username"]);
        $product_id = trim($params["id"]);

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00199'][$language]/*Username is required.*/);
        }

        if ($product_id == '') {
        return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00204'][$language] /*Product ID is required.*/);
        }

        $xunUserService = new XunUserService($db);

        $xunUser = $xunUserService->getUserByUsername($username);

        if (!$xunUser) {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00202'][$language] /*User does not exist.*/);
        }

        $userID = $xunUser["id"];

        $date = date("Y-m-d H:i:s");

        $xun_pay_service = new XunPayService($db);

        $product_obj = new stdClass();
        $product_obj->id = $product_id;

        $product_data = $xun_pay_service->getActiveProductById($product_obj, "id, name, description, image_url, image_md5, account_type, country_iso_code2, provider_id, product_code, currency_code, input_type");

        if (!$product_data) {
        return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00205'][$language] /*Invalid product.*/);
        }

        // $product_option = $this->get_product_option_by_product_id($product_id);

        $provider_id = $product_data["provider_id"];
        $product_code = $product_data["product_code"];
        $country_iso_code2 = $product_data["country_iso_code2"];
        $currency_code = $product_data["currency_code"];
        $input_type_json = $product_data["input_type"];
        $input_type = json_decode($input_type_json, 1);

        // $product_type = $product_data["type"];
        $product_type_obj = new stdClass();
        $product_type_obj->productIdArr = [$product_id];
        $product_type_map = $xun_pay_service->getProductTypeMap($product_type_obj);

        $product_type_id_arr = array_column($product_type_map, "type_id");
        sort($product_type_id_arr);

        $product_type = $product_type_id_arr[0];

        $system_currency = $this->get_product_provider_account_currency($provider_id);

        $price_details = [];
        $reloadly = new reloadly($db, $setting, $post);

        $country_params = array("iso_code2_arr" => [$country_iso_code2]);
        $country_data_arr = $country->getCountryDataByIsoCode2($country_params);
        $country_data = $country_data_arr[strtoupper($country_iso_code2)];
        // $product_currency = strtolower($country_data["currency_code"]);
        $product_currency = $currency_code;
        $exchange_rate_params = array(
            "product_currency" => $product_currency,
            "system_currency" => $system_currency,
        );
        $exchange_rate_return = $this->get_product_exchange_rate($exchange_rate_params);

        $exchange_rate_arr = $exchange_rate_return["exchange_rate_arr"];
        $cryptocurrency_arr = $exchange_rate_return["cryptocurrency_arr"];
        $xun_coins_arr = $exchange_rate_return["xun_coins_arr"];

        $product_amount_type = $input_type["amount"];
        $product_input_type = $this->map_input_type($input_type);

        $product_option = $this->get_product_option_by_product_id($product_id, $product_amount_type);

        if ($provider_id == 1) {
            if ($product_type == 1) {
                $price_details["type"] = "list";
                $price_list = [];
                $price_detail_list = [];
                for ($i = 0; $i < count($product_option); $i++) {
                    $price_amount = $product_option[$i]["amount"];
                    $price_amount = $setting->setDecimal($price_amount, "fiatCurrency");

                    $local_amount = $price_amount;
                    $price_list_data = array(
                        "local_price" => $local_amount,
                        "system_price" => $price_amount,
                    );
                    $price_list[] = $price_amount;
                    $price_detail_list[] = $price_list_data;
                }
                $price_details["price_list"] = $price_list;
                $price_details["price_detail_list"] = $price_detail_list;
            } else if ($product_type == 2) {
                $product_option_type = array_column($product_option, "amount_type");

                if (in_array("dropdown", $product_option_type)) {
                    $price_details["type"] = "list";
                    $product_system_rate = $exchange_rate_arr[$product_currency . "/" . $system_currency];

                    for ($i = 0; $i < count($product_option); $i++) {
                        $price_amount = $product_option[$i]["amount"];
                        $price_amount = $setting->setDecimal($price_amount, "fiatCurrency");

                        $system_amount = bcmul((string) $price_amount, (string) $product_system_rate, 2);

                        $price_list_data = array(
                            "local_price" => $price_amount,
                            "system_price" => $system_amount,
                        );
                        $price_list[] = $price_amount;
                        $price_detail_list[] = $price_list_data;
                    }
                    $price_details["type"] = "list";
                    $price_details["price_list"] = $price_list;
                    $price_details["price_detail_list"] = $price_detail_list;
                } else {
                    $price_details["type"] = "min_max";
                    for ($i = 0; $i < count($product_option); $i++) {
                        $product_option_data = $product_option[$i];
                        $price_amount = $product_option_data["amount"];
                        $price_amount = $setting->setDecimal($price_amount, "fiatCurrency");

                        // $local_amount = $price_amount;
                        $ex_rate = $exchange_rate_arr[$product_currency . '/myr'];
                        $local_amount = bcmul((string) $price_amount, (string) $ex_rate, 2);
                        $price_list_data = array(
                            "local_price" => $price_amount,
                            "system_price" => $local_amount,
                        );
                        if ($product_option_data["amount_type"] == "min") {
                            $price_details["min_price"] = $price_list_data;
                        } else if ($product_option_data["amount_type"] == "max") {
                            $price_details["max_price"] = $price_list_data;
                        }
                    }
                }
            }
        } else if ($provider_id == 2) {
            $price_details["type"] = "list";
            $price_list = [];
            $price_detail_list = [];
            // get fx rate from reloadly
            if ($country_iso_code2 == "my") {
                $reloadly_fx_rate = '1';
            } else {
                $reloadly_params = [];
                $reloadly_params["amount"] = 1;
                $reloadly_params["operatorId"] = $product_code;
                $reloadly_params["currencyCode"] = 'MYR';
                $reloadly_response = $reloadly->getFxRate($reloadly_params);
                $reloadly_fx_rate = $reloadly_response["fxRate"];
            }

            for ($i = 0; $i < count($product_option); $i++) {
                $product_option_data = $product_option[$i];
                if ($product_option_data["amount_type"] == "dropdown") {
                    $price_amount = $product_option_data["amount"];
                    $price_amount = $setting->setDecimal($price_amount, "fiatCurrency");

                    $local_amount = bcmul((string) $price_amount, (string) $reloadly_fx_rate, 2);
                    $price_list_data = array(
                        "local_price" => $local_amount,
                        "system_price" => $price_amount,
                    );
                    $price_list[] = $price_amount;
                    $price_detail_list[] = $price_list_data;
                }
            }
            $price_details["price_list"] = $price_list;
            $price_details["price_detail_list"] = $price_detail_list;
        } else if ($provider_id == 3) {
            $product_system_rate = $exchange_rate_arr[$product_currency . "/" . $system_currency];
            if ($product_amount_type == "dropdown") {
                $price_details["type"] = "list";

                for ($i = 0; $i < count($product_option); $i++) {
                    $price_amount = $product_option[$i]["amount"]; //  final price
                    $price_amount = $setting->setDecimal($price_amount, "fiatCurrency");

                    $sell_price = $product_option[$i]["sell_price"]; //  sell price/what user will receive
                    $sell_price = $setting->setDecimal($sell_price, "fiatCurrency");

                    $sp1 = bcmul((string) $price_amount, (string) $product_system_rate, 8);

                    $sp2 = ceil(bcmul((string) $sp1, '100', 8));
                    $system_amount = bcdiv((string) $sp2, '100', 2);

                    $price_list_data = array(
                        "local_price" => $sell_price,
                        "system_price" => $system_amount,
                    );
                    $price_list[] = $price_amount;
                    $price_detail_list[] = $price_list_data;
                }
                $price_details["type"] = "list";
                $price_details["price_list"] = $price_list;
                $price_details["price_detail_list"] = $price_detail_list;
            } elseif ($product_amount_type == "input") {
                $price_details["type"] = "min_max";
                $product_option_data = $product_option[0];

                $min_price = $product_option_data["min_price"];
                $max_price = $product_option_data["max_price"];
                $min_price = $setting->setDecimal($min_price, "fiatCurrency");
                $max_price = $setting->setDecimal($max_price, "fiatCurrency");

                $sp1 = bcmul((string) $min_price, (string) $product_system_rate, 8);
                $sp2 = ceil(bcmul((string) $sp1, '100', 8));
                $min_system_price = bcdiv((string) $sp2, '100', 2);

                $sp1 = bcmul((string) $max_price, (string) $product_system_rate, 8);
                $sp2 = ceil(bcmul((string) $sp1, '100', 8));
                $max_system_price = bcdiv((string) $sp2, '100', 2);

                $price_details["min_price"] = array(
                    "local_price" => $min_price,
                    "system_price" => $min_system_price,
                );

                $price_details["max_price"] = array(
                    "local_price" => $max_price,
                    "system_price" => $max_system_price,
                );
            }
        }

        $filtered_cryptocurrency_arr = $this->get_product_available_wallet_types($exchange_rate_arr, $cryptocurrency_arr,
            $xun_coins_arr, $price_details, $system_currency);

        $maintenance_start_time = $setting->systemSetting["bcMaintenanceStartTime"];
        $maintenance_end_time = $setting->systemSetting["bcMaintenanceEndTime"];

        $maintenance_coins = $setting->systemSetting["bcMaintenanceCoins"];

        $maintenance_coins_arr = explode(",", $maintenance_coins);

        $date = date("Y-m-d H:i:s");

        if($date >= $maintenance_start_time && $date <= $maintenance_end_time){
            $filtered_cryptocurrency_arr = array_diff($filtered_cryptocurrency_arr, $maintenance_coins_arr);
            $filtered_cryptocurrency_arr = array_values($filtered_cryptocurrency_arr);
        }
        $return_data = [];
        $return_data["id"] = $product_data["id"];
        $return_data["name"] = $product_data["name"];
        $return_data["description"] = $product_data["description"];
        $return_data["image_url"] = $product_data["image_url"];
        $return_data["image_md5"] = $product_data["image_md5"];
        $return_data["type_id"] = $product_type;
        $return_data["type_ids"] = $product_type_id_arr;
        $return_data["account_type"] = $product_data["account_type"];
        $return_data["input_type"] = $product_input_type;
        $return_data["country_code"] = strtolower($country_data["iso_code2"]);
        $return_data["currency"] = $product_currency;
        $return_data["system_price_currency"] = $system_currency;
        $return_data["price_details"] = $price_details;
        $return_data["exchange_rate"] = $exchange_rate_arr;
        $return_data["wallet_types"] = $filtered_cryptocurrency_arr;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['B00110'][$language] /*Product details.*/, "data" => $return_data);
    }
}
