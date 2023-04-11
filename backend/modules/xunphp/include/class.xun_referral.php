<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/

class XunReferral
{

    public function __construct($db, $setting, $general, $xunTree)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
        $this->xunTree = $xunTree;
    }

    public function add_upline($params)
    {
        global $setting, $xunUser, $xunXmpp;

        $db = $this->db;
        $xunTree = $this->xunTree;
        $general = $this->general;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $username = trim($params["username"]);
        $internal_address = trim($params["internal_address"]);
        $encrypted_string = trim($params["ref"]);
        $primary_address = trim($params["primary_address"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00130'][$language]/*username cannot be empty.*/);
        }

        if ($internal_address == '' && $encrypted_string == "" && $primary_address == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>
                $translations['E00178'][$language], /**Internal address or ref is required. */
            );
        }

        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user", "id, username, nickname");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00025'][$language]/*User does not exist.*/);
        }

        if ($encrypted_string) {
            $key = $setting->systemSetting["userQRCodeKey"];
            $cipher_method = $setting->systemSetting["userQRCodeCipherMethod"];

            $user_id = openssl_decrypt($encrypted_string, $cipher_method, $key);
            $db->where("id", $user_id);
            $db->where("disabled", 0);
            $upline_user = $db->getOne("xun_user", "id, username, nickname, type");

        } else if ($internal_address) {
            $db->where("address", $internal_address);
            $db->where("active", 1);
            $db->where("deleted", 0);
            $upline_user_id = $db->getValue("xun_crypto_user_address", "user_id");
            $db->where("id", $upline_user_id);
            $db->where("disabled", 0);
            $upline_user = $db->getOne("xun_user", "id, username, nickname, type");
        } else if ($primary_address){
            $db->where("external_address", $primary_address);
            $db->where("deleted", 0);
            $upline_user_id = $db->getValue("xun_crypto_user_address", "user_id");

            $db->where("id", $upline_user_id);
            $db->where("disabled", 0);
            $upline_user = $db->getOne("xun_user", "id, username, nickname, type");
        }
        
        if (!$upline_user) {
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Address is not a user's wallet internal address.", "data" => array("internal_address" => $internal_address, "username" => "")
            );
        }

        if (!$internal_address) {
            $db->where("user_id", $upline_user["id"]);
            $db->where("active", 1);
            $internal_address = $db->getValue("xun_crypto_user_address", "address");
        }

        if ($upline_user["type"] != "user")
        {
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Success", "developer_msg" => "business internal address", "data" => array("internal_address" => $internal_address, "username" => "")
            );
        }

        if ($username == $upline_user["username"]) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You cannot scan your own QR code.",
                // $translations['E00174'][$language]/**You cannot add yourself as your referrer */,
                "errorCode" => -100,
            );
        }

        // check if both users have upline
        $user_sponsor = $xunTree->getSponsorByUsername($username, $xun_user);
        $upline_sponsor = $xunTree->getSponsorByUsername($upline_user["username"], $upline_user);

        if ($user_sponsor) {
            $return_message = $translations['E00176'][$language]/*You already have a referrer added.*/;

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $return_message, "data" => array("internal_address" => $internal_address, "username" => $upline_user["username"]));
        }

        $user_id = $xun_user["id"];
        $upline_user_id = $upline_user["id"];

        if (!$upline_sponsor) {
            // add default user as upline
            $top_tree_user_id = 0;
            $insert_upline_res = $xunTree->insertSponsorTree($upline_user_id, $top_tree_user_id);

            if (!$insert_upline_res) {
                return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00141'][$language]/*"Internal server error. Please try again.")*/);
            }
        }

        $insert_tree_res = $xunTree->insertSponsorTree($user_id, $upline_user_id);
        if (!$insert_tree_res) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00141'][$language]/*"Internal server error. Please try again.")*/);
        }

        //  get number of downline
        $number_of_downline = $xunTree->getNumberOfDownline($upline_user_id);
        $this->send_new_downline_message($upline_user, $number_of_downline);

        // check for freecoin claim
        $xun_user_service = new XunUserService($db);
        $user_address_data = $xun_user_service->getActiveAddressByUserIDandType($user_id, "personal");
        $user_internal_address = $user_address_data["address"];

        //  disable freecoin
        /*
        if($user_address_data && $user_internal_address != ''){
            $freecoin_params = array("user_id" => $user_id, "address" => $user_internal_address);
            $xunFreecoinPayout = new XunFreecoinPayout($db, $setting, $general);
            $user_freecoin_record = $xunFreecoinPayout->fundOutFreecoin($freecoin_params);
        }
        */

        $upline_user_nickname = $upline_user["nickname"];
        // get master upline of tree
        if ($upline_sponsor["master_upline"] === 1) {
            $master_upline_username = $upline_sponsor["username"];
            $master_upline_user_id = $upline_user_id;
            $master_upline_nickname = $upline_user_nickname;
        } else {
            $upline_user_upline_id = $upline_sponsor["upline_id"];
            if ($upline_user_upline_id != 0) {
                $master_upline_user_id = $xunTree->getSponsorMasterUplineIDByUserID($upline_user_upline_id);

                if ($master_upline_user_id) {
                    $db->where("id", $master_upline_user_id);
                    $master_upline_user = $db->getOne("xun_user", "id, username, nickname");
                    $master_upline_username = $master_upline_user["username"];
                    $master_upline_nickname = $master_upline_user["nickname"];
                }
            }
        }
        // send push notification
        $user_nickname = $xun_user["nickname"];
        $isVoip = false;
        $upline_username = $upline_user["username"];
        $upline_payload = array(
            "xun_type" => "referral",
            "xun_username" => $username,
            "xun_nickname" => $user_nickname
        );

        $res1 = $xunUser->send_push_notification($upline_username, $upline_payload, $isVoip);

        if($master_upline_user_id){
            $master_upline_payload = array(
                "xun_type" => "master_dealer",
                "xun_username" => $username,
                "xun_nickname" => $user_nickname
            );
            $res2 = $xunUser->send_push_notification($master_upline_username, $master_upline_payload, $isVoip);

            // get master dealer default coin
            $default_coin_arr = $this->get_master_upline_default_coin($master_upline_user_id);
            $default_coin = !empty($default_coin_arr) ? $default_coin_arr[0] : '';
        }
        $default_coin_arr = $default_coin_arr ? $default_coin_arr : [];

        //  send notification
        $tag = "New Referral";
        $content = "Username: " . $username . "\n";
        $content .= "Nickname: " . $user_nickname . "\n";
        $content .= "Referrer: " . $upline_username . "\n";
        $content .= "Referrer Nickname: " . $upline_user_nickname . "\n";
        if($master_upline_user_id){
            $content .= "Master Dealer: " . $master_upline_username . "\n";
            $content .= "Master Dealer Nickname: " . $master_upline_nickname . "\n";
        }else{
            $content .= "Master Dealer: No master dealer\n";
        }

        $content .= "Time: " . date("Y-m-d H:i:s") . "\n";

        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = array();
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

        if (!$internal_address) {
            $db->where("user_id", $upline_user["id"]);
            $db->where("active", 1);

            $internal_address = $db->getValue("xun_crypto_user_address", "address");
        }

        $message = $translations['B00106'][$language]/*You've added %%username%% as your referrer.*/;

        $return_message = str_replace("%%username%%", $upline_user["username"], $message);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $return_message, "data" => array("internal_address" => $internal_address, "username" => $upline_user["username"], "default_coin" => $default_coin, "default_coin_list" => $default_coin_arr));

    }

    public function add_downline($params)
    {
        $db = $this->db;
        $xunTree = $this->xunTree;
        $general = $this->general;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $username = trim($params["username"]);
        $downline_qr_code = trim($params["referee_code"]);
        $downline_username = trim($params["referee_username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00130'][$language]/*username cannot be empty.*/);
        }

        if ($downline_username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Referee username cannot be empty.",
                // $translations['E00002'][$language]/**Business ID cannot be empty. */
            );
        }

        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user", "id, username, nickname");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00025'][$language]/*User does not exist.*/);
        }

        if ($username == $downline_username) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You cannot add yourself as a referee.",
                // $translations['E00002'][$language]/**Business ID cannot be empty. */
            );
        }
        $db->where("username", $downline_username);
        $db->where("disabled", 0);
        $downline_user = $db->getOne("xun_user", "id, username, nickname");

        if (!$downline_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid code.",
                // $translations['E00025'][$language]/*User does not exist.*/
            );
        }

        // check if both users have upline
        $user_sponsor = $xunTree->getSponsorByUsername($username, $xun_user);
        $downline_sponsor = $xunTree->getSponsorByUsername($downline_user["username"], $downline_user);

        if ($downline_sponsor) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $downline_user["username"] . " already has a referrer.");
        }

        if (!$user_sponsor) {
            // add default user as upline
            $top_tree_user_id = 0;
            $insert_upline_res = $xunTree->insertSponsorTree($xun_user["id"], $top_tree_user_id);

            if (!$insert_upline_res) {
                return array("code" => 0, "message" => "FAILED", "message_d" => "Internal server error. Please try again.");
            }
        }

        $insert_tree_res = $xunTree->insertSponsorTree($downline_user["id"], $xun_user["id"]);
        if (!$insert_tree_res) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Internal server error. Please try again.");
        }
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "You've added " . $downline_user["username"] . " as a referee.");
    }

    public function get_user_referral_tree($params)
    {
        $db = $this->db;
        $xunTree = $this->xunTree;
        $general = $this->general;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $username = trim($params["username"]);
        $master_upline = trim($params["master_upline"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00130'][$language]/*username cannot be empty.*/);
        }

        if ($master_upline == "true") {
            $master_upline = 1;
        } else {
            $master_upline = 0;
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user", "id, username, nickname");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00025'][$language]/*User does not exist.*/);
        }

        $user_id = $xun_user["id"];

        $return_data = [];
        if ($master_upline === 1) {
            // check if user is master upline
            $user_tree = $xunTree->getSponsorUplineAndMasterUplineByUserID($user_id);
            if ($user_tree && $user_tree["master_upline"] === 1) {
                $downline_user_data = $xunTree->getSponsorDownlineByUserID($user_id, true);
                $list_created_at = array_column($downline_user_data, 'created_at');
                array_multisort($list_created_at, SORT_ASC, $downline_user_data);
            } else {
                $downline_user_data = [];
            }

        } else {
            $upline_id = $xunTree->getSponsorUplineIDByUserID($user_id);
            if (!$upline_id) {
                $upline_data = new stdClass();
            } else {
                $db->where("id", $upline_id);
                $upline_data = $db->getOne("xun_user", "id, username, nickname");
            }

            $downline_user_data = $xunTree->getSponsorDirectDownlineByUserID($user_id, true);
            $return_data["upline"] = $upline_data;
        }

        $return_data["downline"] = $downline_user_data;
        // array("upline" => $upline_data, "downline" => $downline_user_data)

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $translations['B00107'][$language]/*User referral details.*/,
            "data" => $return_data);
    }

    public function get_referral_summary($params)
    {
        global $xunCurrency, $xunTree;

        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $username = trim($params["username"]);
        $date_range = trim($params["date_range"]);
        $master_upline = trim($params["master_upline"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00130'][$language]/*username cannot be empty.*/);
        }

        if ($date_range == '') {
            $date_range = "day";
        }

        if ($master_upline == "true" || $master_upline === true) {
            $master_upline = 1;
        } else {
            $master_upline = 0;
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user", "id, username, nickname");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00025'][$language]/*User does not exist.*/);
        }

        $user_id = $xun_user["id"];
        $db->where("user_id", $user_id);
        $db->where("master_upline", $master_upline);

        switch ($date_range) {
            case "day":
                $start_date = date('Y-m-d');
                break;

            case "week":
                $start_date = date("Y-m-d", strtotime('monday this week'));
                break;

            case "month":
                $start_date = date('Y-m-01');
                break;

            default:
                $start_date = date('Y-m-d');
                break;
        };

        $db->where("created_at", $start_date, ">=");
        $db->groupBy("crypto_currency");

        $referral_transactions = $db->get("xun_referral_transaction", null, "user_id, crypto_currency, sum(quantity) as sum");

        $crypto_result_arr = [];
        $total_earning = "0.00";
        $currency_unit = "USD";
        if (!empty($referral_transactions)) {
            $crypto_currency_arr = array_column($referral_transactions, 'crypto_currency');

            $crypto_currency_rate_arr = $xunCurrency->get_cryptocurrency_rate($crypto_currency_arr);

            $crypto_details_arr = $xunCurrency->get_marketplace_currency_details($crypto_currency_arr);

            foreach ($referral_transactions as $data) {
                $crypto_currency = $data["crypto_currency"];
                $crypto_sum = $data["sum"];
                $currency_amount = bcmul((string) $crypto_sum, (string) $crypto_currency_rate_arr[$crypto_currency], 2);
                $total_earning = bcadd((string) $total_earning, (string) $currency_amount, 2);

                $crypto_data = array();

                $crypto_details = $crypto_details_arr[$crypto_currency];
                $crypto_data["name"] = $crypto_details->name;
                $crypto_data["image"] = $crypto_details->image;
                $crypto_data["unit"] = $crypto_details->symbol;
                $crypto_data["crypto_amount"] = (string) $crypto_sum . " " . strtoupper($crypto_details->symbol);
                $crypto_data["crypto_amount_details"] = array("amount" => $crypto_sum, "unit" => strtoupper($crypto_details->symbol));
                $crypto_data["currency_amount"] = $currency_amount . " " . $currency_unit;
                $crypto_data["currency_amount_details"] = array("amount" => $currency_amount, "unit" => $currency_unit);
                $crypto_result_arr[] = $crypto_data;
            }
        }

        $prev_month_earning = "0.00";
        $prev_start_date = date('Y-m-d', strtotime('first day of last month'));
        $prev_end_date = date('Y-m-d', strtotime('first day of this month'));

        $db->where("user_id", $user_id);
        $db->where("master_upline", 0);
        $db->where("created_at", $prev_start_date, ">=");
        $db->where("created_at", $prev_end_date, "<");
        $db->groupBy("crypto_currency");

        $prev_referral_transactions = $db->get("xun_referral_transaction", null, "user_id, crypto_currency, sum(quantity) as sum");

        if (!empty($prev_referral_transactions)) {
            $crypto_currency_arr = array_column($prev_referral_transactions, 'crypto_currency');

            $crypto_currency_rate_arr = $xunCurrency->get_cryptocurrency_rate($crypto_currency_arr);

            foreach ($prev_referral_transactions as $data) {
                $crypto_currency = $data["crypto_currency"];
                $crypto_sum = $data["sum"];

                $currency_amount = bcmul((string) $crypto_sum, (string) $crypto_currency_rate_arr[$crypto_currency], 2);

                $prev_month_earning = bcadd((string) $prev_month_earning, (string) $currency_amount, 2);
            }
        }

        if ($master_upline === 1) {
            $user_tree = $xunTree->getSponsorUplineAndMasterUplineByUserID($user_id);
            if ($user_tree && $user_tree["master_upline"] === 1) {
                $downline_list = $xunTree->getSponsorDownlineByUserID($user_id);
                $total_downline = count($downline_list);
            } else {
                $total_downline = 0;
            }
        } else {
            $db->where("upline_id", $user_id);
            $total_downline = $db->getValue("xun_tree_referral", "count(id)");
        }

        $return_data = array(
            "date_range" => $date_range,
            "total_earning" => $total_earning . " " . $currency_unit,
            "total_earning_details" => array("amount" => $total_earning, "unit" => $currency_unit),
            "prev_month_earning" => $prev_month_earning . " " . $currency_unit,
            "prev_month_earning_details" => array("amount" => $prev_month_earning, "unit" => $currency_unit),
            "referral_summary" => $crypto_result_arr,
            "total_downline" => $total_downline,
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Referral earning summary", "data" => $return_data);
    }

    public function get_referral_transaction_history_listing($params)
    {
        global $xunCurrency;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $page_limit = $setting->systemSetting["appsPageLimit"];

        $username = trim($params["username"]);
        $id = trim($params["last_id"]);
        $crypto_currency = trim($params["crypto_currency"]);
        $master_upline = trim($params["master_upline"]);
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00130'][$language]/*username cannot be empty.*/);
        }

        if ($master_upline == "true" || $master_upline === true || $master_upline == 1) {
            $master_upline = 1;
        } else {
            $master_upline = 0;
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user", "id, username, nickname");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00025'][$language]/*User does not exist.*/);
        }

        $user_id = $xun_user["id"];

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $db->where("user_id", $user_id);
        $db->where("master_upline", $master_upline);
        if ($id) {
            if ($order == 'DESC') {
                $db->where("id", $id, '<');
            } else {
                $db->where("id", $id, '>');
            }
        }

        if ($crypto_currency) {
            $db->where("crypto_currency", $crypto_currency);
        }

        $start_limit = 0;
        $limit = array($start_limit, $page_size);

        $copyDb = $db->copy();
        $db->orderBy("created_at", $order);

        $referral_transactions = $db->get("xun_referral_transaction", $limit, "id, service_charged_user_id, quantity, crypto_currency, created_at");
        $return_message = "Referral transactions.";
        $referral_transactions = $referral_transactions ? $referral_transactions : array();

        if (!empty($referral_transactions)) {
            $currency_unit = "USD";
            $crypto_currency_arr = array();
            $user_id_arr = array();

            foreach ($referral_transactions as $data) {
                $user_id_arr[] = $data["service_charged_user_id"];
                $crypto_currency_arr[] = $data["crypto_currency"];
            }

            $user_id_arr = array_unique($user_id_arr);
            $crypto_currency_arr = array_unique($crypto_currency_arr);

            $crypto_currency_rate_arr = $xunCurrency->get_cryptocurrency_rate($crypto_currency_arr);

            $crypto_details_arr = $xunCurrency->get_marketplace_currency_details($crypto_currency_arr);

            $db->where("id", $user_id_arr, "in");
            $user_ids = $db->map("id")->ObjectBuilder()->get("xun_user", null, "id, username, nickname");

            for ($i = 0; $i < sizeof($referral_transactions); $i++) {
                $data = $referral_transactions[$i];
                $crypto_currency_rate = $crypto_currency_rate_arr[$data["crypto_currency"]];
                $crypto_unit = $crypto_details_arr[$data["crypto_currency"]]->symbol;
                $currency_amount = bcmul((string) $data["quantity"], (string) $crypto_currency_rate, 2);
                $referral_transactions[$i]["currency_amount"] = $currency_amount . " " . $currency_unit;
                $referral_transactions[$i]["currency_amount_details"] = array("amount" => $currency_amount, "unit" => $currency_unit);
                $referral_transactions[$i]["crypto_amount"] = $data["quantity"] . " " . strtoupper($crypto_unit);
                $referral_transactions[$i]["crypto_amount_details"] = array("amount" => $data["quantity"], "unit" => strtoupper($crypto_unit));
                $referral_transactions[$i]["created_at"] = $general->formatDateTimeToIsoFormat($referral_transactions[$i]["created_at"]);

                $service_charged_user_id = $data["service_charged_user_id"];
                $user_details = $user_ids[$service_charged_user_id];
                $referral_transactions[$i]["nickname"] = $user_details->nickname;
                $referral_transactions[$i]["username"] = $user_details->username;
            }
        }

        $last_el = end($referral_transactions);
        $last_id = $last_el ? $last_el["id"] : null;

        $result = $referral_transactions;
        $return_data = array(
            "result" => $result,
            "last_id" => $last_id,
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Referral earning history", "data" => $return_data);
    }

    public function getSponsorUplineByUserID($params)
    {
        $xunTree = $this->xunTree;

        return $xunTree->getSponsorMasterUplineIDByUserID($params["user_id"]);
    }

    public function get_master_upline_status($params)
    {
        $db = $this->db;
        $xunTree = $this->xunTree;
        $general = $this->general;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $username = trim($params["username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00130'][$language]/*username cannot be empty.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user", "id, username, nickname");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00025'][$language]/*User does not exist.*/);
        }

        $user_id = $xun_user["id"];

        $user_tree = $xunTree->getSponsorUplineAndMasterUplineByUserID($user_id);

        $is_master_upline = false;
        if ($user_tree && $user_tree["master_upline"]) {
            $is_master_upline = true;
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Master dealer status.", "data" => array("is_master_dealer" => $is_master_upline));
    }

    public function get_master_upline_default_coin($user_id)
    {
        $db = $this->db;

        $db->where("user_id", $user_id);
        $master_upline_data = $db->getOne("xun_master_upline", "id, user_id, default_coin");

        if ($master_upline_data){
            $default_coin = $master_upline_data["default_coin"];

            $default_coin_arr = json_decode($default_coin);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // JSON is valid
                $default_coin_arr = $default_coin == '' ? [] : [$default_coin];
            }
        }

        return $default_coin_arr;
    }

    public function insert_referral_transaction($upline_user_id, $advertisement_order = null, $trading_fee_quantity, $service_charged_user_id, $master_upline = null, $crypto_currency = null, $transaction_id)
    {
        $db = $this->db;

        $master_upline = $master_upline ? $master_upline : 0;

        if($advertisement_order){
            $db->where("advertisement_id", $advertisement_order["advertisement_id"]);
            $db->where("advertisement_order_id", $advertisement_order["reference_order_id"]);
            $db->where("master_upline", $master_upline);
            $referral_upline = $db->getOne("xun_referral_transaction", "id");
    
            if ($referral_upline) {
                return $referral_upline["id"];
            }

            $advertisement_id = $advertisement_order["advertisement_id"];
            $advertisement_order_id = $advertisement_order["reference_order_id"];
            $crypto_currency = $advertisement_order["currency"];
        }
        
        $crypto_currency = strtolower($crypto_currency);
        
        $date = date("Y-m-d H:i:s");
        $insert_data = array(
            "user_id" => $upline_user_id,
            "service_charged_user_id" => $service_charged_user_id,
            "wallet_transaction_id" => $transaction_id,
            // "advertisement_id" => $advertisement_id ? $advertisement_id : '',
            // "advertisement_order_id" => $advertisement_order_id ? $advertisement_order_id : '',
            "quantity" => $trading_fee_quantity,
            "crypto_currency" => $crypto_currency,
            "master_upline" => $master_upline,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $row_id = $db->insert("xun_referral_transaction", $insert_data);

        // if (!$row_id) {
        //     print_r($insert_data);
        //     print_r($db);
        // }

        return $row_id;

    }
    
    private function send_new_downline_message($user_data, $number_of_downline)
    {
        $db = $this->db;
        $setting = $this->setting;

        switch ($number_of_downline){
            case 1: 
                $notification_id = 3;

                break;

            case 10: 
                $notification_id = 4;
                break;

            default:
                return;
        }

        $username = $user_data["username"];
        $xun_in_app_notification = new XunInAppNotification($db, $setting);


        $xun_in_app_notification->send_message($username, $notification_id);
    }
}
