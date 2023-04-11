<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/
class XunBusinessPartner
{
    public function __construct($db, $post, $general, $partnerDB, $xunCrypto)
    {
        $this->db = $db;
        $this->post = $post;
        $this->general = $general;
        $this->partnerDB = $partnerDB;
        $this->xunCrypto = $xunCrypto;
    }

    public function partner_update_user($params, $source = null)
    {
        global $xunCrypto, $config;
        $db = $this->db;
        $partnerDB = $this->partnerDB;

        $business_id = trim($params["business_id"]);
        $api_key = trim($params["api_key"]);
        $mobile_list = $params["mobile_list"];
        $date = date("Y-m-d H:i:s");
        $server_host = $config["erlang_server"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business ID is required");
        }

        if (is_null($source)){
            if ($api_key == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "api_key is required");
            }

            $validate_api_key = $xunCrypto->validate_crypto_api_key($api_key, $business_id);
    
            if ($validate_api_key !== true) {
                return $validate_api_key;
            }
        }

        if (empty($mobile_list)) {
            return array(
                "code" => 1,
                "message" => "SUCCESS",
                "message_d" => "Success",
            );
        }

        $mobile_list_trimmed = array_map(
            function ($v) {
                $v = trim($v);
                if($v[0] != "+"){
                    $v = "+" . $v;
                }
                return $v;
            }, $mobile_list);

        $mobile_list_trimmed = array_filter($mobile_list_trimmed);
        if (empty($mobile_list_trimmed)) {
            return array(
                "code" => 1,
                "message" => "SUCCESS",
                "message_d" => "Success",
            );
        }

        $xun_user_service = new XunUserService($db);
        $xun_user_arr = $xun_user_service->getUserByUsername($mobile_list_trimmed, "id, username, disabled", "user", "username");

        $insert_data_arr = [];
        $registered_user_list = [];
        $unregistered_user_list = [];

        $registered_user_id_list = [];

        $date = date("Y-m-d H:i:s");

        for ($i = 0; $i < count($mobile_list_trimmed); $i++) {
            $mobile = $mobile_list_trimmed[$i];

            $xun_user = $xun_user_arr[$mobile];

            $is_registered = 0;
            if ($xun_user) {
                if ($xun_user["disabled"] === 0) {
                    $is_registered = 1;
                }
            }

            if ($is_registered) {
                $user_id = $xun_user["id"];
                $registered_user_list[] = $mobile;
                $registered_user_id_list[] = $user_id;
            }else{
                $unregistered_user_list[] = $mobile;
            }

            $insert_data = array(
                "business_id" => $business_id,
                "mobile" => $mobile,
                "is_registered" => $is_registered,
                "created_at" => $date,
                "updated_at" => $date
            );

            $update_columns = array(
                "is_registered",
                "updated_at",
            );

            $partnerDB->onDuplicate($update_columns);

            $ids = $partnerDB->insert("business_user", $insert_data);

            if (!$ids) {
                return array(
                    "code" => 0,
                    "message" => "FAILED",
                    "message_d" => "Something went wrong. Please try again.",
                    "error_message" => $partnerDB->getLastError()
                );
            }

        }

        //  get business coin
        $xun_business_coin = $this->get_business_coin($business_id);

        if($xun_business_coin){
            $this->add_user_coin($xun_business_coin["id"], $registered_user_id_list, $business_id);
        }

        if(!empty($registered_user_list)){
            $db->where("username", $registered_user_list, "IN");
            $business_follow_arr = $db->map("username")->get("xun_business_follow");

            foreach($registered_user_list as $username){
                $business_follow = $business_follow_arr[$username];
                if(!$business_follow){
                    $insert_business_follow_data_arr[] = array(
                        "business_id" => $business_id,
                        "username" => $username,
                        "server_host" => $server_host,
                        "created_at" => $date,
                        "updated_at" => $date
                    );
                }
            }

            if(!empty($insert_business_follow_data_arr)){
                $ids = $db->insertMulti("xun_business_follow", $insert_business_follow_data_arr);
                if(!$ids){
                    return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $partnerDB->getLastError());
                }
            }
        }

        $return_data = [];
        $return_data["registered_users"] = $registered_user_list;
        $return_data["unregistered_users"] = $unregistered_user_list;

        return array(
            "code" => 1,
            "message" => "SUCCESS",
            "message_d" => "Added customer's phone number successfully.",
            "data" => $return_data,
        );
    }

    public function add_user_coin($business_coin_id, $user_id_arr, $business_id){
        $db = $this->db;
        global $xunReward;

        $date = date("Y-m-d H:i:s");

        foreach($user_id_arr as $user_id){
            $insert_data = array(
                "user_id" => $user_id,
                "business_coin_id" => $business_coin_id,
                "created_at" => $date
            );

            $db->onDuplicate(["business_coin_id"]);
            $row_id = $db->insert("xun_user_coin", $insert_data);

            if(!$row_id){
                throw new Exception($db->getLastError());
            }else{
                //send reward
                try{
                    $send_reward_params = array(
                        "user_id" => $user_id,
                        "business_coin_id" => $business_coin_id,
                        // "wallet_type" => $wallet_type,
                        "business_id" => $business_id
                    );
                    $xunReward->new_follower_send_welcome_reward($send_reward_params);
                }catch(Exception $e){
                    $error_msg = $e->getMessage();
                }
            }
        }
    }

    public function get_business_coin($business_id){
        $db = $this->db;

        $db->where("business_id", $business_id);
        $xun_business_coin = $db->getOne("xun_business_coin");

        return $xun_business_coin;
    }

    public function get_user_coin($user_id){
        $db = $this->db;

        $db->where("a.user_id", $user_id);
        $db->join("xun_business_coin b", "b.id=a.business_coin_id", "LEFT");
        $data = $db->map("wallet_type")->ArrayBuilder()->get("xun_user_coin a", null, "b.wallet_type, b.default_show, a.user_id");

        return $data;
    }

    public function update_registered_user($username, $user_id, $registered_on){
        $db = $this->db;
        $partnerDB = $this->partnerDB;
        $post = $this->post;
        global $config, $xunReward;

        $server_host = $config["erlang_server"];
        
        $partnerDB->where("mobile", $username);
        $business_user_list = $partnerDB->get("business_user", null, "id, business_id, mobile, is_registered");

        $date = date("Y-m-d H:i:s");

        if(empty($business_user_list)){
            return;
        }

        $update_data = [];
        $update_data["is_registered"] = 1;
        $update_data["updated_at"] = $date;

        $partnerDB->where("mobile", $username);
        $partnerDB->update("business_user", $update_data);

        $business_id_list = array_column($business_user_list, "business_id");

        $db->where("business_id", $business_id_list, "IN");
        $db->where("type", "reward");
        $business_coin_list = $db->map("business_id")->ArrayBuilder()->get("xun_business_coin");

        foreach($business_id_list as $business_id){
            //insert into xun_business_follow
            $follow_insert_data = array(
                "business_id" => $business_id,
                "username" => $username,
                "server_host" => $server_host,
                "created_at" => $date,
                "updated_at" => $date
            );

            $follow_id = $db->insert("xun_business_follow", $follow_insert_data);
            if (!$follow_id){
                return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $partnerDB->getLastError());
            }
            
            $business_coin = $business_coin_list[$business_id];
            if($business_coin){
                $user_coin = array(
                    "user_id" => $user_id,
                    "business_coin_id" => $business_coin["id"],
                    "created_at" => $date
                );

                $db->insert("xun_user_coin", $user_coin);
            }
        }

        //  get business' callback url
        $xun_business_service = new XunBusinessService($db);


        $business_callback_setting_name = "businessUpdateRegisteredUserCallbackURL";
        
        $business_callback_url_list = $xun_business_service->getUserSettingByName($business_id_list, $business_callback_setting_name, null, "user_id");

        $registered_on_ts = strtotime($registered_on);

        $callback_command = "newUserCallback";
        $post_params = array(
            "mobile" => $username,
            "register_on" => $registered_on_ts
        );

        $post_data = array(
            "command" => $callback_command,
            "params" => $post_params
        );

        $header = array('Content-Type: application/json');
        foreach($business_callback_url_list as $callback_url_data){
            $callback_url = $callback_url_data["value"];
            $post_result = $post->curl_post($callback_url, $post_data, 0, 1, $header);
        }
    }

    public function get_business_partner_rewards_user_info($params){
        $db = $this->db;
        $xunCrypto = $this->xunCrypto;

        /**
         * Data IN
         * {
                business_id: 12345,
                api_key: "123456", (payment gateway API Key)
                mobile_list: ["+12345678"]
            }
         *
         * Data OUT
         * {
                "code": 1,
                "message": "SUCCESS",
                "message_d": "Success",
                "data": {
                    "currency": "sms123rewards,
                    "business_balance": "123",
                    "user_balance_list": [{
                            "mobile": "+12345678",
                            "balance": "123"
                    }]
                }
            }
         */

        $business_id = trim($params["business_id"]);
        $api_key = trim($params["api_key"]);
        $mobile_list = $params["mobile_list"];
        $date = date("Y-m-d H:i:s");

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business ID is required");
        }

        if ($api_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "api_key is required");
        }

        if (!empty($mobile_list) && !is_array($mobile_list)){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid data type for mobile list.");
        }

        $validate_api_key = $xunCrypto->validate_crypto_api_key($api_key, $business_id);

        if ($validate_api_key !== true) {
            return $validate_api_key;
        }

        //  get business coin
        $xun_business_coin = $this->get_business_coin($business_id);

        if(!$xun_business_coin){
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => "Business does not have a reward coin."
            );
        }

        $wallet_type = $xun_business_coin["wallet_type"];

        $db->where("user_id", $business_id);
        $db->where("address_type", "reward");
        $db->where("active", 1);
        $business_cp_address_data = $db->getOne("xun_crypto_user_address");

        if(!$business_cp_address_data){
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => "You do not have a wallet created yet."
            );
        }

        $business_cp_address = $business_cp_address_data["address"];

        $wallet_balance = $xunCrypto->get_wallet_balance($business_cp_address, $wallet_type);

        $mobile_list = array_filter($mobile_list);

        $xun_user_service = new XunUserService($db);
        if(!empty($mobile_list)){
            $xun_user_arr = $xun_user_service->getUserByUsername($mobile_list, null, "user", "username");
            $user_id_arr = array_column($xun_user_arr, "id");
            if(!empty($user_id_arr)){
                $user_address_data_arr = $xun_user_service->getActiveAddressDetailsByUserID($user_id_arr);

                $user_address_arr = array_column($user_address_data_arr, "address");
                if(!empty($user_address_arr)){
                    $db->where("address", $user_address_arr, "IN");
                    $db->where("verified", 1);
                    $user_verification_arr = $db->map("address")->get("xun_crypto_user_address_verification");
                }

            }
        
        }

        $user_info_list = [];
        //  get balance and get wallet email
        foreach($mobile_list as $mobile){
            $xun_user = $xun_user_arr[$mobile];
            $user_balance = 0;
            $user_wallet_email = "";
            if($xun_user){
                $user_id = $xun_user["id"];
                $user_address_data = $user_address_data_arr[$user_id];
                if($user_address_data_arr[$user_id]){
                    $user_address = $user_address_data["address"];
                    try{
                        $user_balance = $xunCrypto->get_wallet_balance($user_address, $wallet_type);
                    }catch(Exception $e){
                        $user_balance = null;
                    }
                    $user_verification_data = $user_verification_arr[$user_address];
                    $user_wallet_email = $user_verification_data["email"];
                }
            }

            $user_info_list[] = array(
                "mobile" => $mobile,
                "balance" => $user_balance,
                "wallet_email" => $user_wallet_email ? $user_wallet_email : ""
            );
        }

        $return_data = [];
        $return_data["currency"] = $wallet_type;
        $return_data["business_balance"] = $wallet_balance;
        $return_data["user_balance_list"] = $user_info_list;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "data" => $return_data);
    }

    public function callback_business_coin($params){
        $db = $this->db;

        $wallet_type = trim($params["wallet_type"]);
        $business_id = trim($params["business_id"]);
        $command = trim($params["command"]);
        $callback_url = trim($params["callback_url"]);
        $callback_params = $params["callback_params"];

        if(empty($callback_url)){
            if(empty($business_id)){
                $db->where("wallet_type", $wallet_type);
                $business_id = $db->getValue("xun_business_coin", "business_id");
            }
        }

        $new_params = array(
            "business_id" => $business_id,
            "command" => $command,
            "callback_url" => $callback_url,
            "callback_params" => $callback_params
        );
        return $this->callback_business_partner($new_params);
    }

    public function callback_business_partner($params){
        $db = $this->db;
        $post = $this->post;

        $callback_url = trim($params["callback_url"]);
        $command = trim($params["command"]);
        $callback_params = $params["callback_params"];
        $business_id = trim($params["business_id"]);

        if(empty($callback_url) && !empty($business_id)){
            //  get business wallet callback url
            $db->where("id", $business_id);
            $callback_url = $db->getValue('xun_user', 'wallet_callback_url');
        }

        if(empty($callback_url)){
            return;
        }

        $post_data = array(
            "command" => $command,
            "params" => $callback_params
        );

        $headers = array('Content-Type: application/json');
        $post_result = $post->curl_post($callback_url, $post_data, 0, 1, $headers);

        return $post_result;
    }
}
