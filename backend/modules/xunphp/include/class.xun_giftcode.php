<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/

class XunGiftCode
{

    public function __construct($db, $post, $general)
    {
        $this->db = $db;
        $this->post = $post;
        $this->general = $general;
    }

    public function purchase_gift_code($params)
    {
        global $setting, $xunBusiness, $xunXmpp;
        $db = $this->db;
        $general = $this->general;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $business_id = trim($params["business_id"]);
        $quantity = trim($params["quantity"]);
        $currency = trim($params["currency"]);
        $api_key = trim($params["api_key"]);

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00002'][$language]);
        }
        if ($quantity == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00162'][$language]);
        }
        if ($currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00180'][$language]/**Currency is required. */);
        }
        if ($api_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00086'][$language]/** Api key cannot be empty. */);
        }

        $xunBusinessService = new XunBusinessService($db);
        $business = $xunBusinessService->getBusinessByBusinessID($business_id);

        if (!$business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00032'][$language]/*Invalid business id.*/);
        }

        if (!$xunBusiness->validate_api_key($business_id, $api_key)) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $translations['E00148'][$language]/*Invalid Apikey.*/);
        }

        if ($quantity <= 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00164'][$language]/* Quantity must be more than zero.*/);
        }

        $wallet_type = strtolower($currency);

        $db->where("currency_id", $wallet_type);
        $is_gift_code_coin = $db->getValue("xun_coins", "is_gift_code_coin");

        if ($is_gift_code_coin == 0) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00169'][$language]/* Invalid coin. Please try again.*/);
        }

        $code_valid = false;

        do {
            $code = $general->generateAlpaNumeric(15);

            $db->where("code", $code);
            $code_record = $db->getOne("xun_gift_code", "id");

            if (!$code_record) {
                $code_valid = true;
            }
        } while (!$code_valid);

        $reference_id = $db->getNewID();

        $date = date("Y-m-d H:i:s");
        $insert_data = [];
        $insert_data["code"] = $code;
        $insert_data["reference_id"] = $reference_id;
        $insert_data["quantity"] = (string) $quantity;
        $insert_data["business_id"] = $business_id;
        $insert_data["wallet_type"] = $wallet_type;
        $insert_data["redeemed"] = 0;
        $insert_data["redeemed_by"] = '';
        $insert_data["redeemed_wallet_type"] = '';
        $insert_data["created_at"] = $date;
        $insert_data["updated_at"] = $date;

        $row_id = $db->insert("xun_gift_code", $insert_data);

        //  send notification
        $tag = "Gift Code Purchase";
        $content = "Business Name: " . $business["name"];
        $content .= "\nBusiness ID: " . $business_id;
        $content .= "\nAmount: " . $quantity;
        $content .= "\nWallet Type: " . $wallet_type;
        $content .= "\nTime: " . $date;

        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = array();
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

        if ($row_id) {
            $return_data = array(
                "gift_code" => $code,
                "quantity" => $quantity,
                "reference_id" => (string) $reference_id,
            );
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['B00102'][$language]/**  Gift Code. */, "data" => $return_data);
        } else {
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00141'][$language]/**  Internal server error. Please try again later. */);
        }
    }

    public function get_wallet_details($params)
    {
        global $setting, $xunBusiness, $xunCurrency;

        $db = $this->db;
        $general = $this->general;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $business_id = trim($params["business_id"]);
        $api_key = trim($params["api_key"]);
        $wallet_type = trim($params["wallet_type"]);

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00002'][$language]);
        }
        if ($api_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00086'][$language]/** Api key cannot be empty. */);
        }
        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00150']/*Wallet Type cannot be empty*/);
        }

        $wallet_type = strtolower($wallet_type);

        $xunBusinessService = new XunBusinessService($db);
        $business = $xunBusinessService->getBusinessByBusinessID($business_id);

        if (!$business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00032'][$language]/*Invalid business id.*/);
        }

        if (!$xunBusiness->validate_api_key($business_id, $api_key)) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $translations['E00148'][$language]/*Invalid Apikey.*/);
        }

        $currency_info = $xunCurrency->get_currency_info($wallet_type);
        $currency_rate = $xunCurrency->get_rate($wallet_type, "usd");

        $return_data = [];
        $return_data["name"] = $currency_info["name"];
        $return_data["unit"] = $currency_info["symbol"];
        $return_data["image"] = $currency_info["image"];
        $return_data["value"] = $currency_rate;
        $return_data["currency"] = "usd";

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['B00108'][$language]/*Wallet details.*/, "data" => $return_data);
    }

    public function redeem_gift_code($params)
    {
        global $xunXmpp;

        $db = $this->db;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $username = trim($params["username"]);
        $code = trim($params["gift_code"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00130'][$language]/*username cannot be empty.*/);
        }
        if ($code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00165'][$language]/*Giftcode cannot be empty.*/);
        }

        // $code = strtoupper($code);

        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00025'][$language]/*User does not exist.*/);
        }

        $user_id = $xun_user["id"];

        $xunUserService = new XunUserService($db);
        $db->where("user_id", $user_id);
        $db->where("active", 1);
        $db->where("address_type", "personal");

        $user_address = $db->getOne("xun_crypto_user_address");

        if (!$user_address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00168'][$language]/* You do not have an active wallet.*/);
        }

        $nickname = $xun_user["nickname"];
        $tag = "Gift Code Redeemed";

        $content = "Username: " . $username;
        $content .= "\nNickname: " . $nickname;
        $content .= "\nGift code: " . $code;
        $content .= "\nTime: " . $date;

        $erlang_params["tag"] = $tag;
        $erlang_params["mobile_list"] = array();

        $db->where("code", $code);
        $gift_code = $db->getOne("xun_gift_code");

        if (!$gift_code) {
            $content .= "\nStatus: Failed";
            $content .= "\nMessage: Invalid gift code.";

            $erlang_params["message"] = $content;
            $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00166'][$language]/* Invalid gift code.*/, "errorCode" => -100, "status" => "invalid_code");
        }

        if ($gift_code["redeemed"]) {
            $content .= "\nStatus: Failed";
            $content .= "\nMessage: Code already redeemed.";

            $erlang_params["message"] = $content;
            $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00167'][$language]/* This gift code has already been redeemed.*/, "errorCode" => -101, "status" => "redeemed");
        }

        // $content .= "\nStatus: Success";
        // $content .= "\nMessage: Code redeemed.";

        // $erlang_params["message"] = $content;
        // $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

        // if ($row_id) {
        // transfer coin to user's address
        $business_id = $gift_code["business_id"];
        $business_prepaid_address_data = $xunUserService->getActiveAddressByUserIDandType($business_id, "prepaid", "address");

        if ($business_prepaid_address_data) {
            $business_prepaid_address = $business_prepaid_address_data["address"];

            $tx_obj = new stdClass();
            $tx_obj->userID = $business_id;
            $tx_obj->address = $business_prepaid_address;
            $tx_obj->referenceID = $code;

            $transaction_token = $xunUserService->insertCryptoTransactionToken($tx_obj);

            $gift_code_amount = $gift_code["quantity"];
            $gift_code_wallet_type = $gift_code["wallet_type"];
            $receiver_address = $user_address["address"];

            $new_params = array(
                "command" => "fundOut",
                "params" => array(
                    "senderAddress" => $business_prepaid_address,
                    "receiverAddress" => $receiver_address,
                    "amount" => $gift_code_amount,
                    "walletType" => $gift_code_wallet_type,
                    "transactionToken" => $transaction_token,
                ),
            );

            // fund out, call wallet server

            $tag = "Gift Code Coin Fund Out";
            $content = "Username: " . $username;
            $content .= "\nNickname: " . $nickname;
            $content .= "\nGift code: " . $code;
            $content .= "\nAmount: " . $gift_code_amount;
            $content .= "\nWallet Type: " . $gift_code_wallet_type;
            $content .= "\nReceiver Address: " . $receiver_address;
            $content .= "\n\nBusiness Name: " . $business_name;
            $content .= "\nBusiness ID: " . $business_id;
            $content .= "\nTime: " . date("Y-m-d H:i:s");

            $erlang_params["tag"] = $tag;
            $erlang_params["message"] = $content;
            $erlang_params["mobile_list"] = array();
            $xmpp_result1 = $xunXmpp->send_xmpp_notification($erlang_params);

            $res = $this->fund_out_coin($new_params, $username, $nickname, $code);

            if ($res["code"] == 0) {
                return array("code" => 0, "errorCode" => -102, "message" => "FAILED", "message_d" => "Unable To Redeem. Some error occurred. Please contact your vendor.", "status" => "internal_error");
            }

            $db->where("id", $gift_code["id"]);
            $update_data = array(
                "redeemed" => 1,
                "redeemed_by" => $xun_user["id"],
                // "redeemed_wallet_type" => $coin_type,
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $row_id = $db->update("xun_gift_code", $update_data);

            $content .= "\nStatus: Success";
            $content .= "\nMessage: Code redeemed.";

            $erlang_params["message"] = $content;
            $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['B00105'][$language]/** Your gift code has been successfully redeemed. */);
        }

        return array("code" => 0, "errorCode" => -102, "message" => "FAILED", "message_d" => "Unable To Redeem. Some error occurred. Please contact your vendor.", "status" => "internal_error");
        // }

        // return array("code" => 0, "message" => "FAILED", "message_d" => $translations['M00309'][$language]/** An internal error has occurred. Please contact to our support team. */);
    }

    private function fund_out_coin($params, $username, $nickname, $gift_code)
    {
        global $config, $xunXmpp;
        $db = $this->db;
        $post = $this->post;

        $url_string = $config["giftCodeUrl"];
        $post_return = $post->curl_post($url_string, $params, 0, 1);
        // {\"code\":0,\"message\":\"FAILED\",\"message_d\":\"Insufficient balance.\",\"result\":{\"status\":\"error\",\"code\":1,\"statusMsg\":\"Insufficient balance.\",\"data\":\"\"}}
        if ($post_return["code"] == 0) {
            // send notification
            // $error_message = $post_return_obj->result;
            $error_message = $post_return["message_d"];

            $tag = "Gift Code Transfer Error";
            $content = "Error: " . $error_message;
            $content .= "\n\nUsername: " . $username;
            // $content .= "\nGift code: " . $gift_code;
            $content .= "\nAmount: " . $params["amount"];
            $content .= "\nWallet Type: " . $params["walletType"];
            $content .= "\nReceiver Address: " . $params["receiverAddress"];

            $erlang_params = [];
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

    public function get_business_credit_balance($business_id, $type)
    {
        $db = $this->db;

        $balance = 0;

        $db->where("user_id", $business_id);
        $db->where("type", $type);
        $db->orderBy("date", "desc");

        $business_acc_closing = $db->getOne("xun_acc_closing");

        $acc_type = "gift_code";
        if ($business_acc_closing) {
            $balance = $business_acc_closing["balance"];

            $acc_closing_date = $business_acc_closing["date"];

            $daily_acc_credit_total = $this->get_daily_table_balance_forward($business_id, $acc_type, $acc_closing_date);
        } else {
            $daily_acc_credit_total = $this->get_daily_table_balance_backward($business_id, $acc_type);
        }

        $final_balance = bcadd((string) $balance, (string) $daily_acc_credit_total, 8);

        return $final_balance;
    }

    public function test_date($params)
    {
        $current_date = date("Y-m-d");
        echo "current date $current_date";

        $this->get_daily_table_balance_forward("", "", "2019-05-22");
    }
    public function get_daily_table_balance_forward($business_id, $type, $acc_closing_date)
    {
        $db = $this->db;

        $current_date = date("Y-m-d");

        $date = date('Y-m-d', strtotime($acc_closing_date . "+1 days"));

        $total = 0;

        while ($date <= $current_date) {
            $table_name = $this->get_acc_credit_daily_table_name($date);

            if ($db->tableExists($table_name)) {
                $db->where("user_id", $business_id);
                $db->where("type", $type);

                $daily_acc_credit = $db->get($table_name, null, "credit, debit, balance");

                $daily_total = 0;
                foreach ($daily_acc_credit as $data) {
                    if ($data["debit"] > 0) {
                        $daily_total = bcadd((string) $daily_total, (string) $data["debit"], 8);
                    } else {
                        $daily_total = bcsub((string) $daily_total, (string) $data["credit"], 8);
                    }
                }

                $total = bcadd((string) $total, (string) $daily_total, 8);
            }
            $date = date('Y-m-d', strtotime($date . "+1 days"));
        }
        return $total;
    }

    public function get_daily_table_balance_backward($business_id, $type)
    {
        $db = $this->db;

        $current_date = date("Y-m-d");

        $date = $current_date;
        $has_acc_credit_table = true;
        $total = 0;

        do {
            $table_name = $this->get_acc_credit_daily_table_name($date);
            if ($db->tableExists($table_name)) {
                $db->where("user_id", $business_id);
                $db->where("type", $type);

                $daily_acc_credit = $db->get($table_name, null, "credit, debit, balance");

                $daily_total = 0;
                foreach ($daily_acc_credit as $data) {
                    if ($data["debit"] > 0) {
                        $daily_total = bcadd((string) $daily_total, (string) $data["debit"], 8);
                    } else {
                        $daily_total = bcsub((string) $daily_total, (string) $data["credit"], 8);
                    }
                }

                $total = bcadd((string) $total, (string) $daily_total, 8);
                $date = date('Y-m-d', strtotime($date . "-1 days"));
            } else {
                $has_acc_credit_table = false;
            }
        } while ($has_acc_credit_table);

        return $total;
    }

    public function get_acc_credit_daily_table_name($date)
    {
        $db = $this->db;

        $tblDate = date("Ymd", strtotime($date));

        if (!trim($tblDate)) {
            $tblDate = date("Ymd");
        }

        $table_name = "xun_acc_credit_" . $db->escape($tblDate);
        return $table_name;
    }

    public function create_acc_credit_daily_table($date)
    {
        $db = $this->db;

        $table_name = $this->get_acc_credit_daily_table_name($date);
        $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS " . $table_name . " LIKE xun_acc_credit");

        return $table_name;
    }

    public function store_daily_acc_credit($business_id, $type, $debit, $credit)
    {
        $db = $this->db;

        $table_name = $this->create_acc_credit_daily_table(date("Y-m-d"));

        $insert_data = array(
            "user_id" => $business_id,
            "type" => $type,
            "debit" => $debit,
            "credit" => $credit,
            "reference_id" => "",
            "balance" => 0,
            "created_at" => date("Y-m-d H:i:s"),
        );

        $row_id = $db->insert($table_name, $insert_data);
        // if (!$row_id) {
        //     print_r($db);
        // }

        return $row_id;
    }

    public function app_verify_gift_code($params)
    {
        global $xunCurrency;

        $db = $this->db;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $username = trim($params["username"]);
        $code = trim($params["gift_code"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00130'][$language]/*username cannot be empty.*/);
        }
        if ($code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00165'][$language]/*Giftcode cannot be empty.*/);
        }

        // $code = strtoupper($code);

        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user", "id");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00025'][$language]/*User does not exist.*/);
        }

        $db->where("code", $code);
        $gift_code_rec = $db->getOne("xun_gift_code");

        if (!$gift_code_rec) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00170'][$language]/* Invaid gift code.*/, "errorCode" => -100, "status" => "invalid_code");
        }

        if ($gift_code_rec["redeemed"] == 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00167'][$language]/* This gift code has already been redeemed.*/, "errorCode" => -101, "status" => "redeemed");
        }

        $code_quantity = $gift_code_rec["quantity"];
        $wallet_type = $gift_code_rec["wallet_type"];
        $wallet_info = $xunCurrency->get_currency_info($wallet_type);
        $wallet_image = $wallet_info["image"];

        if (bccomp((string) $code_quantity, "1", 8) >= 0) {
            $trimmed_amount = (float) $code_quantity;
        } else if ($code_quantity == 0) {
            $trimmed_amount = 0;
        } else {
            $trimmed_amount = rtrim(sprintf("%0.8f", $code_quantity), "0");
        }

        $return_data = array(
            "quantity" => (string) $trimmed_amount,
            "wallet_type" => $wallet_type,
            "wallet_image" => $wallet_image,
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['B00104'][$language]/* Gift code is valid.*/, "data" => $return_data);
    }
}
