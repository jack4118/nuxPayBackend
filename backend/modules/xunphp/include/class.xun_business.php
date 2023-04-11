<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/
class XunBusiness
{
    public function __construct($db, $post, $general, $xunEmail)
    {
        $this->db = $db;
        $this->post = $post;
        $this->general = $general;
        $this->xunEmail = $xunEmail;
    }

    public function business_message_sending($url_string, $params)
    {
        global $config;

        $db = $this->db;
        $post = $this->post;

        $api_key = $params["api_key"];
        $business_id = $params["business_id"];
        $mobile_list = $params["mobile_list"];
        $tag = $params["tag"];
        $message = $params["message"];
        $group_id = $params["group_id"];
        $send_to_followers = $params["send_to_followers"]; //FLAG
        $hidden_message = trim($params["extra_message"]);

        $created_at = date("Y-m-d H:i:s");

        if ($business_id == '') {
            $error_message = $this->get_translation_message('E00002');
            // "Business Id cannot be empty"
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => "business_id cannot be empty");
        };

        if ($tag == '') {
            $error_message = $this->get_translation_message('E00003');
            // "Tag cannot be empty"
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00003') /*Tag cannot be empty*/, 'developer_msg' => "tag cannot be empty");
        };

        if (strlen($tag) >= 25) {
            $tag = substr($tag, 0, 25);
        };

        if ($message == '') {
            // "Message cannot be empty"
            $error_message = $this->get_translation_message('E00004');

            return array('code' => 0, 'message' => FAILED, 'message_d' => $error_message, 'developer_msg' => "message cannot be empty");
        };

        if (strlen($message) >= 3550) {
            $message = substr($message, 0, 3550);
        }

        //checks for valid business id

        $db->where('user_id', $business_id);
        $check_business = $db->getOne('xun_business');
        if (empty($check_business)) {
            $error_message = $this->get_translation_message('E00021');
            // "The business id you've entered is incorrect"
            return array('code' => 0, 'message' => FAILED, 'message_d' => $error_message, 'developer_msg' => "");

        }

        //checks for valid api key (exists or within date)
        $db->where('apikey', $api_key);
        $check_api_key = $db->getOne('xun_business_api_key');

        if (empty($check_api_key)) {
            if(!(isset($params["access_token"]) && $params["source"] == "business")){
                $error_message = $this->get_translation_message('E00148');
                // Invalid API Key.
                return array('code' => 0, 'message' => FAILED, 'message_d' => $error_message);
            }
        }else{
            if($check_api_key["business_id"] != $business_id){
                $error_message = $this->get_translation_message('E00148');
                // Invalid API Key.
                return array('code' => 0, 'message' => FAILED, 'message_d' => $error_message);
            }
            $DateNow = time();

            $Status = $check_api_key['status'];
            $Enable = $check_api_key['is_enabled'];
            $EndDate = $check_api_key['apikey_expire_datetime'];
            $END_Date = strtotime($EndDate);

            if ($Status != "active") {
                $error_message = $this->get_translation_message('E00022');
                // "This API Key has been inactivated. Please activate the key or use an active key."
                return array('code' => 0, 'message' => FAILED, 'message_d' => $error_message);
            };
            if ($Enable != "1") {
                $error_message = $this->get_translation_message('E00023');
                // "This API Key is no longer valid. Please use an active key."
                return array('code' => 0, 'message' => FAILED, 'message_d' => $error_message);
            };
            if ($DateNow >= $END_Date) {
                $error_message = $this->get_translation_message('E00024');
                // "This API key has expired. Please use a valid key."
                return array('code' => 0, 'message' => FAILED, 'message_d' => $error_message);
            };

        }

        $mobile_list = array_filter($mobile_list);
        $valid_xun_mobile = [];
        $non_xun_user = [];

        $non_followers_mobile = [];

        $final_valid_number = [];
        $final_invalid_number = [];

        //filter xun_user
        foreach ($mobile_list as $value) {
            $mobile_first_char = $value[0];
            if($mobile_first_char != '+'){
                $value = "+" . $value;
            }

            $db->where("username", $value);
            $db->where("disabled", 0);
            $filter_check_user = $db->getOne("xun_user");

            if ($filter_check_user) {
                $valid_xun_mobile[] = $value;
            } else {
                $non_xun_user[] = $value;
            }
        }

        // get followers and non followers
        $db->where("business_id", $business_id);
        $business_followers = $db->get("xun_business_follow");

        $followers_mobile = [];
        foreach ($business_followers as $follower) {
            $followers_mobile[] = $follower["username"];
        }

        $non_followers_mobile = array_diff($valid_xun_mobile, $followers_mobile);
        $final_valid_number = array_intersect($valid_xun_mobile, $followers_mobile);

        // filter by send_to_followers flag
        if ($send_to_followers) {
            $final_valid_number = array_unique(array_merge($final_valid_number, $followers_mobile));
            $recipient = array_unique(array_merge($mobile_list, $followers_mobile));
        } else {
            $recipient = $mobile_list;
        }

        //filter message in follow message
        foreach ($non_followers_mobile as $value) {
            $db->where("mobile_number", $value);
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $business_block = $db->getOne("xun_business_block");
            if ($business_block) {
                continue;
            }

            $db->where("username", $value);
            $db->where("business_id", $business_id);
            $check_follower_number = $db->getOne("xun_business_follow_message");

            if (empty($check_follower_number)) {

                $fields = array("business_id", "username", "created_at", "updated_at");
                $values = array($business_id, $value, $created_at, $created_at);
                $arrayData = array_combine($fields, $values);
                $db->insert("xun_business_follow_message", $arrayData);

                $final_valid_number[] = $value;
            }
        }

        $final_valid_number = array_filter($final_valid_number);

        $final_invalid_number = array_filter(array_diff($mobile_list, $final_valid_number));

        foreach ($final_valid_number as $mobile_number) {

            $db->where("mobile_number", $mobile_number);
            $user_device = $db->getOne("xun_user_device");

            if ($user_device["google_play_token"] == 1) {
                $android_access_token[] = $user_device["access_token"];
            } else {
                if ($user_device["voip_access_token"]) {
                    $ios_voip_access_token[] = array($mobile_number => $user_device["voip_access_token"]);
                }

                if ($user_device["access_token"] && !$user_device["voip_access_token"]) {
                    $ios_access_token[] = $user_device["access_token"];
                }
            }
        }

        //valid number and count
        $valid_number = implode('##', $final_valid_number);
        $sent_mobile_length = count($final_valid_number);

        //count unfollwer number
        $count_unfollwer_number = count($non_followers_mobile);

        //diff total non xun user

        $total_non_xun_user = count($non_xun_user);

        //total_recipient
        $total_recipient = count($recipient);

        //invalid number   ?????
        $invalid_number = implode('##', $final_invalid_number);

        if ($api_key == '') {
            $api_key = "";
        }

        //insert the message record in xun_publish_message_log
        $fields = array("apikey_id", "business_id", "sent_datetime", "request_mobile_length", "sent_mobile_length", "tag", "valid_mobile_list", "invalid_mobile_list");
        $values = array($api_key, $business_id, $created_at, $total_recipient, $sent_mobile_length, $tag, $valid_number, $invalid_number);
        $arrayData = array_combine($fields, $values);
        $db->insert("xun_publish_message_log", $arrayData);

        $result = array('unregistered_users' => array_values($non_xun_user), 'total_recipient' => $total_recipient, 'total_non_xun_user' => $total_non_xun_user, 'total_message_sent' => $sent_mobile_length, 'non_follower' => array_values($non_followers_mobile), 'total_non_follower' => $count_unfollwer_number);

        // do not pass to erlang if no recipient
        if (!$final_valid_number) {
            // "Messages sent."
            return array("message_d" => $this->get_translation_message('B00002') /*Messages sent.*/, "message" => "success", "code" => 1, "result" => $result);
        }

        //rebuild params for erlang side
        $newParams["business_id"] = $business_id;
        $newParams["mobile_list"] = array_values($final_valid_number);
        $newParams["tag"] = $tag;
        $newParams["message"] = $message;
        $newParams["hidden_message"] = $hidden_message;
        $newParams["android_access_token"] = $android_access_token ? $android_access_token : array(1);
        $newParams["ios_voip_access_token"] = $ios_voip_access_token ? $ios_voip_access_token : array(1);
        $newParams["ios_access_token"] = $ios_access_token ? $ios_access_token : array(1);

        if ($config['businessSendingType'] == "nodejs") {
            $fields = array("data", "message_type", "created_at", "updated_at");
            $values = array(json_encode($newParams), "business", date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));

            $insertData = array_combine($fields, $values);
            $db->insert("xun_business_sending_queue", $insertData);

            return array("status" => "ok", "statusMsg" => "success", "code" => 1, "message_d" => $this->get_translation_message('B00002') /*Messages sent.*/, "result" => $result, "params" => $params);
        } else {
            $erlangReturn = $post->curl_post($url_string, $newParams);

            if ($erlangReturn["code"] == 0) {
                return array("status" => "error", "statusMsg" => $erlangReturn["message_d"], "code" => 1);
            }

            return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00002') /*Messages sent.*/, "code" => 1, "result" => $result);
        }
    }

    public function business_block($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];
        $mobile = $params["mobile"];

        $date = date("Y-m-d H:i:s");

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        ;

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }
        ;

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("user_id", $mobile);
        $result = $db->getOne("xun_business_block");

        if ($result && $result["status"] == 'blocked') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00026') /*This business is already blocked.*/, 'errorCode' => -100);
        } else {
            if ($result && $result["status"] == 'unblocked') {
                $row_id = $result["id"];

                $updateData["status"] = 'blocked';
                $updateData["updated_at"] = $date;
                $db->where("id", $row_id);
                $db->update("xun_business_block", $updateData);

                // $db->rawQuery("UPDATE xun_business_block SET status = 'blocked', updated_at = '" . date("Y-m-d H:i:s") . "' WHERE id = '$row_id'");
            } else {
                $fields = array("business_id", "mobile_number", "status", "created_at", "updated_at");
                $values = array($business_id, $mobile, "blocked", date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));

                $insertData = array_combine($fields, $values);
                $db->insert("xun_business_block", $insertData);
            }
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00003') /*Business has been blocked.*/);
    }

    public function business_unblock($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];
        $mobile = $params["mobile"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        ;

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }
        ;

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("user_id", $mobile);
        $result = $db->getOne("xun_business_block");

        if ($result && $result["status"] == 'unblocked') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00027') /*This business is not blocked.*/, 'errorCode' => -100);
        } else {
            if ($result && $result["status"] == 'blocked') {
                $row_id = $result["id"];

                $updateData["status"] = 'unblocked';
                $updateData["updated_at"] = $date;
                $db->where("id", $row_id);
                $db->update("xun_business_block", $updateData);

                // $db->rawQuery("UPDATE xun_business_block SET status = 'unblocked', updated_at = '" . date("Y-m-d H:i:s") . "' WHERE id = '$row_id'");
            } else {
                $fields = array("business_id", "mobile_number", "status", "created_at", "updated_at");
                $values = array($business_id, $mobile, "unblocked", date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));

                $insertData = array_combine($fields, $values);
                $db->insert("xun_business_block", $insertData);
            }
        }
        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00004') /*Business has been unblocked.*/);
    }

    public function business_block_list($params)
    {
        $db = $this->db;

        $mobile = $params["mobile"];

        if ($mobile == '') {
            $return_message = $this->get_translation_message('E00005');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }
        ;

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/, "result" => []);
        }

        $db->where("user_id", $mobile);
        $result = $db->get("xun_business_block");

        $returnData = [];
        foreach ($result as $data) {
            $business_id = $data["business_id"];

            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            $business_name = $business_result["name"];
            $business_profile_url = is_null($business_result["profile_picture_url"]) ? "" : $business_result["profile_picture_url"];

            $returnData[] = array("business_uuid" => (string) $business_id,
                "business_name" => $business_name,
                "business_profile_picture_url" => $business_profile_url);

        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00005') /*User's blocked business list.*/, "result" => $returnData);
    }

    public function business_follow($params)
    {
        $db = $this->db;
        global $config;

        $business_id = trim($params["business_id"]);
        $mobile = trim($params["mobile"]);

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        ;

        if ($mobile == '') {
            $return_message = $this->get_translation_message('E00005');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }
        ;

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        /*{ "uuid": "10002", "business_email": "jasper.leong@ekomas.com",  "business_name": "Penguin Company", "business_website": "",   "business_address1": "",      "business_address2": "",  "business_city": "",  "business_state": "", "business_postal": "", "business_country": "", "business_info": "",  "business_profile_picture": "",  "business_profile_picture_url": "",  "business_verified": 0,  "business_created_date": "2017-8-25T10:11:14Z", "business_status": 1, "business_company_size": "", "business_email_address":"tescofood@gmail.com" }*/
        $xun_business = $this->compose_xun_business($business_result);

        $xun_business_follow["business_uuid"] = $business_id;
        $xun_business_follow["user_username"] = $mobile;

        $db->where("business_id", $business_id);
        $db->where("username", $mobile);
        $result = $db->getOne("xun_business_follow");

        if ($result) {

            $xun_business_follow["uuid"] = (string) $result["id"];

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00029') /*Business is already followed.*/, "errorCode" => -100, "xun_business_follow" => $xun_business_follow, "xun_business" => $xun_business);

        }

        $fields = array("business_id", "username", "server_host", "old_id", "created_at", "updated_at");
        $values = array($business_id, $mobile, $config["erlang_server"], "", date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));

        $insertData = array_combine($fields, $values);

        $row_id = $db->insert("xun_business_follow", $insertData);

        $xun_business_follow["uuid"] = (string) $row_id;

        //delete business follow message record
        $db->where("business_id", $business_id);
        $db->where("username", $mobile);
        $db->delete("xun_business_follow_message");

        // update xun_business_block
        $now = date("Y-m-d H:i:s");
        $updateBlockBusiness = [];
        $updateBlockBusiness["status"] = 0;
        $updateBlockBusiness["updated_at"] = $now;

        $db->where("business_id", $business_id);
        $db->where("mobile_number", $mobile);
        $db->where("status", 1);
        $db->update("xun_business_block", $updateBlockBusiness);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00073') /*Business successfully followed.*/, "xun_business_follow" => $xun_business_follow, "xun_business" => $xun_business);
    }

    public function business_unfollow($params)
    {
        $db = $this->db;

        $id = trim($params["id"]);

        if ($id == '') {
            $return_message = $this->get_translation_message('E00006');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00006') /*ID cannot be empty.*/);
        }
        ;

        $db->where("old_id", $id);
        $db->orWhere("id", $id);
        $result = $db->getOne("xun_business_follow");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00031') /*Invalid id. Record does not exist.*/, "errorCode" => -100);
        }

        $record_id = $result["id"];
        $db->where('id', $record_id);
        $db->delete('xun_business_follow');

        $business_id = $result["business_id"];
        $mobile = $result["username"];
        // add to business_block table
        $db->where("business_id", $business_id);
        $db->where("mobile_number", $mobile);
        $xun_business_block = $db->getOne("xun_business_block");

        if ($xun_business_block) {
            $now = date("Y-m-d H:i:s");
            $updateBlockBusiness = [];
            $updateBlockBusiness["status"] = 1;
            $updateBlockBusiness["updated_at"] = $now;

            $db->where("business_id", $business_id);
            $db->where("mobile_number", $mobile);
            $db->update("xun_business_block", $updateBlockBusiness);
        } else {
            $fields = array("business_id", "mobile_number", "user_id", "status", "created_at", "updated_at");
            $values = array($business_id, $mobile, $mobile, 1, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));

            $insertData = array_combine($fields, $values);
            $row_id = $db->insert("xun_business_block", $insertData);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00006') /*Business unfollowed.*/);
    }

    public function business_follow_count($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot empty");
        }
        ;

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00031') /*You do not have the right to modify properties of this business.*/);
        }

        $db->where('business_id', $business_id);
        $result = $db->get('xun_business_follow');

        $count_follower = count($result);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00007') /*Number of followers.*/, "count_follower" => $count_follower);
    }

    public function business_follow_list_user($params)
    {
        $db = $this->db;

        $mobile = $params["mobile"];

        if ($mobile == '') {
            $return_message = $this->get_translation_message('E00005');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }
        ;

        $db->where("username", $mobile);
        $result = $db->get("xun_business_follow");

        $xun_business_follow = [];

        foreach ($result as $follow_data) {
            $business_id = $follow_data["business_id"];

            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            $xun_business = $this->compose_xun_business($business_result);

            $xun_business["uuid"] = (string) $follow_data["id"];
            $xun_business["user_username"] = $mobile;
            $xun_business["business_uuid"] = (string) $business_id;

            $xun_business_follow[] = $xun_business;

        }

        $db->where("username", $mobile);
        $result = $db->get("xun_business_follow_message");

        $pending_business = [];
        foreach ($result as $follow_message_data) {
            $business_id = $follow_message_data["business_id"];

            $db->where("user_id", $business_id);
            $business_result = $db->getOne("xun_business");

            $xun_business = $this->compose_xun_business($business_result);

            $xun_business["uuid"] = (string) $follow_message_data["id"];
            $xun_business["user_username"] = $mobile;
            $xun_business["business_uuid"] = (string) $business_id;

            $pending_business[] = $xun_business;

        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00008') /*Businesses followed.*/, "xun_business_follow" => $xun_business_follow, "pending_business" => $pending_business);
    }

    public function business_tag_list($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $result = $db->get("xun_business_tag");

        foreach ($result as $data) {

            $tag_description = $data["description"];
            if (is_null($tag_description)) {
                $tag_description = "";
            }

            $working_hour_to = $data["working_hour_to"];
            $working_hour_from = $data["working_hour_from"];
            $tag_description = $data["description"];
            $tag = $data["tag"];
            $priority = $data["priority"];
            $created_at = $data["created_at"];

            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->where("status", "1");
            $callback_result = $db->getOne("xun_business_forward_message");

            $forward_url = $callback_result["callback_url"] ? $callback_result["callback_url"] : "";

            $number_employee_tag_rec = $db->rawQuery("SELECT count(*) as total_member FROM xun_business_tag_employee as bte JOIN xun_employee as xe on bte.employee_id = xe.id or bte.employee_id = xe.old_id WHERE bte.business_id = '" . $business_id . "' and xe.status = '1'  and bte.status = '1' and tag = '" . $tag . "' and role = 'employee' and xe.employment_status = 'confirmed'");

            $total_employee = $number_employee_tag_rec[0]["total_member"];

            $returnData[] = array("working_hour_to" => $working_hour_to,
                "working_hour_from" => $working_hour_from,
                "tag_description" => $tag_description,
                "tag" => $tag,
                "priority" => $priority,
                "callback_url" => $forward_url,
                "created_date" => $created_at,
                "total_members" => $total_employee,
                "business_id" => $result[business_id],

            );

        }

        $sort = array();
        foreach ($returnData as $key => $row) {
            $sort[$key] = $row['priority'];
        }
        array_multisort($sort, SORT_ASC, $returnData);

        if (is_null($returnData)) {
            $returnData = [];
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00009') /*Business Tag List.*/, "business_id" => $business_id, "result" => $returnData);
    }

    public function business_tag_get($params)
    {
        $db = $this->db;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        $tag = $params["tag"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($tag == '') {
            $return_message = $this->get_translation_message('E00007');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00007') /*Business tag cannot be empty.*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $result = $db->getOne("xun_business_tag");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00033') /*Business Tag not found.*/);
        }

        $tag = $result["tag"];
        $tag_description = $result["description"];
        $created_date = $result["created_at"];
        $working_hour_to = $result["working_hour_to"];
        $working_hour_from = $result["working_hour_from"];
        $priority = $result["priority"];

        $result_business_forward_message = $db->rawQuery("SELECT `forward_url` FROM `xun_business_forward_message` WHERE business_id = '$business_id' AND tag = '$tag' AND status = 1");

        if (empty($result_business_forward_message)) {
            $callback_url = "";
        } else {
            $callback_url = $result_business_forward_message[0]["forward_url"];
        }

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $employee_result = $db->get("xun_business_tag_employee");
        foreach ($employee_result as $employee_data) {

            $employees[] = $employee_data["username"];

        }

        $returnData["business_id"] = $business_id;
        $returnData["tag"] = $tag;
        $returnData["callback_url"] = $callback_url;
        $returnData["employee_mobile"] = $employees;
        $returnData["created_date"] = $result["created_at"];
        $returnData["working_hour_to"] = $working_hour_to;
        $returnData["working_hour_from"] = $working_hour_from;
        $returnData["priority"] = $priority;
        $returnData["tag_description"] = $tag_description;

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00010') /*Category details.*/, "business_id" => $business_id, "result" => $returnData);
    }

    public function app_business_employee_vcard_update($url_string, $params)
    {
        $db = $this->db;
        $post = $this->post;

        $stream_jid = $params["stream_jid"];
        $employee_jid = $params["employee_jid"];
        $nickname = $params["nickname"];
        $photo = $params["photo"];

        if ($stream_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00008') /*Stream JID cannot be empty*/);
        };
        if ($employee_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00009') /*Employee JID cannot be empty*/);
        };
        if ($nickname == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00010') /*"Nickname cannot be empty"*/);
        };

        //rebuild params for erlang side
        $newParams["employee_jid"] = $employee_jid;
        $newParams["nickname"] = $nickname;
        $newParams["photo"] = $photo;

        $erlangReturn = $post->curl_post($url_string, $newParams);

        return $erlangReturn;
    }

    public function app_business_employee_vcard_get($url_string, $params)
    {
        $db = $this->db;
        $post = $this->post;

        $stream_jid = $params["stream_jid"];
        $employee_jid = $params["employee_jid"];

        if ($stream_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00008') /*Stream JID cannot be empty*/);
        };
        if ($employee_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00009') /*Employee JID cannot be empty*/);
        };

        $pos = stripos($employee_jid, '@');

        if ($pos === false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00034') /*Malformed JID.*/);
        } else {
            $employee_jid_arr = explode("@", $employee_jid);
            $employee_id = $employee_jid_arr[0];
        }

        $db->where("old_id", $employee_id);
        $result = $db->getOne("xun_employee");

        if (!$result) {
            $db->where("id", $employee_id);
            $result = $db->getOne("xun_employee");

            if (!$result) {
                $photo["type"] = "";
                $photo["binval"] = "";
                $vcard["photo"] = $photo;
                $vcard["nickname"] = "";

                return array('code' => 0, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00011') /*User VCard.*/, "jid" => $employee_jid, "vcard" => $vcard);
            } else {
                $nickname = $result["name"];
            }
        } else {
            $nickname = $result["name"];
        }

        //rebuild params for erlang side
        $newParams["employee_jid"] = $employee_jid;
        $newParams["nickname"] = $nickname;

        $erlangReturn = $post->curl_post($url_string, $newParams);

        return $erlangReturn;
    }

    public function app_business_employee_response($params)
    {
        $db = $this->db;

        $stream_jid = trim($params["stream_jid"]);
        $employee_id = trim($params["employee_id"]);
        $status = trim($params["status"]);

        if ($stream_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00008') /*Stream JID cannot be empty*/);
        };

        if ($employee_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00011') /*Employee ID cannot be empty*/);
        };

        if ($status == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00012') /*Status cannot be empty*/);
        };

        if ($status == 'reject') {
            $employment_status = "rejected";
        } else if ($status == 'accept') {
            $employment_status = "confirmed";
        } else {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00035') /*Invalid value for status.*/);

        }

        $pos = stripos($stream_jid, '@');

        if ($pos === false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00036') /*Malformed stream JID.*/);
        } else {
            $stream_jid_arr = explode("@", $stream_jid);
            $mobile = $stream_jid_arr[0];
        }

        $db->where("old_id", $employee_id);
        $db->where("status", 1);
        $result = $db->getOne("xun_employee");

        if (!$result) {
            $db->where("id", $employee_id);
            $db->where("status", 1);
            $result = $db->getOne("xun_employee");

            if (!$result) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00037') /*This is not a valid employee ID.*/);
            }
        }

        $row_id = $result["id"];

        if ($result["mobile"] != $mobile) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00038') /*You have insufficient privilege to perform this operation.*/);
        }

        if ($result["employment_status"] != "pending") {
            $user_employment_status = $result["employment_status"];
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00039') /*This employee's status is no longer pending.*/, 'errorCode' => -100, "status" => $user_employment_status, "stream_jid" => $stream_jid);
        }

        if ($result["status"] == 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00040') /*This employee is no longer active.*/, 'errorCode' => -101, "status" => "inactive", "stream_jid" => $stream_jid);
        }

        $now = date("Y-m-d H:i:s");
        $updateEmployeeData["employment_status"] = $employment_status;
        $updateEmployeeData["updated_at"] = $now;
        $db->where("id", $row_id);
        $db->update("xun_employee", $updateEmployeeData);

        $business_id = $result["business_id"];
        $username = $result["mobile"];

        // send xmpp event for new_tag_user
        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->where("username", $username);
        if ($employment_status == "confirmed") {
            $tag_list = $db->getValue("xun_business_tag_employee", "tag", null);
            $business_tag_arr["new_tag"] = $tag_list ? $tag_list : array();
            $business_tag_arr["removed_tag"] = array();
            $this->update_xmpp_business_tag_employee($business_id, $result, $business_tag_arr);
        } else {
            // remove from xun_business_tag_employee
            $updateData = [];
            $updateData["status"] = 0;
            $updateData["updated_at"] = $now;
            $db->update("xun_business_tag_employee", $updateData);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/, "status" => $employment_status);

    }

    public function business_register($params)
    {
        $db = $this->db;
        $general = $this->general;
        $post = $this->post;

        global $config;

        $business_email = trim($params["business_email"]);
        $password = trim($params["business_password"]);
        $business_name = trim($params["business_name"]);
        $referral_code = trim($params["referral_code"]);
        $utm_source = trim($params["utm_source"]);
        $utm_medium = trim($params["utm_medium"]);
        $utm_campaign = trim($params["utm_campaign"]);
        $utm_term = trim($params["utm_term"]);
        $ip = trim($params["ip"]);
        $user_agent = trim($params["user_agent"]);
        $device_id = trim($params["device_id"]);
        $type = trim($params["type"]);
        $country = trim($params["country"]);
        $url = trim($params["url"]);
        $table_id = trim($params["table_id"]);

        // Param validations
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        };

        if ($password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00014') /*Password cannot be empty*/);
        };
        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }
        // Password validation
        $validate_password = $this->validate_password($password);

        if ($validate_password['code'] == 0) {
            $error_message = $validate_password['error_message'];
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00042') /*Invalid password combination.*/, "developer_msg" => "business_password has an invalid character combination", "error_message" => $error_message);

        }
        $password = password_hash($password, PASSWORD_BCRYPT);

        // Check if email is already registered
        $db->where("email", $business_email);
        $result = $db->getOne("xun_business_account");

        if ($result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00043') /*An account already exists with this email. Please select another email address.*/);
        }

        $created_at = date("Y-m-d H:i:s");
        $erlang_server = $config["erlang_server"];
        $insertUserData = array(
            "server_host" => $erlang_server,
            "type" => "business",
            "nickname" => $business_name,
            "created_at" => $created_at,
            "updated_at" => $created_at,
        );

        // regular registration
        if ($table_id == '') {
            // create business user
            $business_id = $db->insert("xun_user", $insertUserData);

            $fields = array("user_id", "email", "password", "referral_code", "created_at", "updated_at");
            $values = array($business_id, $business_email, $password, $referral_code, $created_at, $created_at);
            $arrayData = array_combine($fields, $values);
            $db->insert("xun_business_account", $arrayData);
        } else {
            // get record from xun_business_register_marketing
            $get_business_register_marketing = $db->rawQuery("SELECT * FROM `xun_business_register_marketing` WHERE reference_id = $table_id AND verified_at IS NOT NULL ORDER BY verified_at DESC LIMIT 1");
            if (empty($get_business_register_marketing)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00044') /*Invalid record.*/);
            } else if ($get_business_register_marketing[0]["status"] == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00045') /*Please verify your account before continuing.*/);
            } else {
                $business_id = $db->insert("xun_user", $insertUserData);

                $mobile = $get_business_register_marketing[0]["phone_number"];
                $created_at = date("Y-m-d H:i:s");
                $fields = array("user_id", "email", "password", "referral_code", "main_mobile", "main_mobile_verified", "created_at", "updated_at");
                $values = array($business_id, $business_email, $password, $referral_code, $mobile, 1, $created_at, $created_at);
                $arrayData = array_combine($fields, $values);
                $db->insert("xun_business_account", $arrayData);
            }
        }

        $verification_code = $general->generateAlpaNumeric(16);

        $send_email_result = $this->send_activation_email($business_email, $business_name, $verification_code);

        // insert to xun_business_verification

        $fields = array("business_email", "verification_code", "created_at");
        $values = array($business_email, $verification_code, $created_at);
        $arrayData = array_combine($fields, $values);
        $db->insert("xun_business_verification", $arrayData);

        if(!$business_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('A00040'));
        }
        // // create business
        $insertBusinessData = array(
            "user_id" => $business_id,
            "email" => $business_email,
            "name" => $business_name,
            "created_at" => $created_at,
            "updated_at" => $created_at
        );

        $business_details_id = $db->insert("xun_business", $insertBusinessData);

        if ($table_id) {
            $this->initialise_new_business($business_id, $business_email, $business_name, $mobile);
        }

        $utm_params["business_id"] = $business_id;
        $utm_params["business_name"] = $business_name;
        $utm_params["utm_source"] = $utm_source;
        $utm_params["utm_medium"] = $utm_medium;
        $utm_params["utm_campaign"] = $utm_campaign;
        $utm_params["utm_term"] = $utm_term;
        $utm_params["ip"] = $ip;
        $utm_params["user_agent"] = $user_agent;
        $utm_params["device_id"] = $device_id;
        $utm_params["type"] = $type;
        $utm_params["country"] = $country;
        $utm_params["url"] = $url;
        $utm_params["register_status"] = 1;

        $this->utm_record($utm_params);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00013') /*Business successfully registered.*/, "business_email" => $business_email);

    }

    public function business_market_register($params)
    {
        $db = $this->db;
        $general = $this->general;
        global $setting;

        $business_name = trim($params["business_name"]);
        $mobile = trim($params["phone_number"]);

        if ($business_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00015') /*Business name cannot be empty.*/);
        };

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00016') /*Phone number cannot be empty*/);
        };

        $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
        if ($mobileNumberInfo["isValid"] == 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/);
        }

        // Check if mobile is registered Xun user
        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        $companyName = $setting->systemSetting["companyName"];

        if (!$result) {
            $translations_message = $this->get_translation_message('E00047') /*%%mobile%% is not a registered %%companyName%% account. Download and install %%companyName%% now to link it with your %%companyName%% Business account.*/;
            $return_message = str_replace("%%mobile%%", $mobile, $translations_message);
            $return_message = str_replace("%%companyName%%", $companyName, $return_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $return_message, "errorCode" => -100);
        }

        do {
            // check if reference_id exists
            $reference_id = $db->getNewID();
            $db->where("reference_id", $reference_id);
            $result = $db->get("xun_business_register_marketing");

            $generate_new_id = false;
            if ($result) {
                $generate_new_id = true;
            }
        } while ($generate_new_id);

        $this->generate_marketing_verification_code($reference_id, $business_name, $mobile);

        // return response
        $translations_message = $this->get_translation_message('B00014') /*%%companyName%% verification code has been sent.*/;
        $return_message = str_replace("%%companyName%%", $companyName, $translations_message);
        return array("code" => "1", "message" => "SUCCESS", "message_d" => $return_message, "id" => $reference_id);
    }

    public function business_market_get($params)
    {
        $db = $this->db;
        $general = $this->general;

        $reference_id = trim($params["table_id"]);
        $business_name = trim($params["business_name"]);
        $mobile = trim($params["phone_number"]);

        if ($reference_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00017') /*Table ID cannot be empty.*/);
        };

        if ($business_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00015') /*Business name cannot be empty.*/);
        };

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00016') /*Phone number cannot be empty*/);
        };

        // validate mobile
        $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
        if ($mobileNumberInfo["isValid"] == 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/);
        }
        // check if is xun user
        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            $translations_message = $this->get_translation_message('E00047') /*%%mobile%% is not a registered %%companyName%% account. Download and install %%companyName%% now to link it with your %%companyName%% Business account.*/;
            $return_message = str_replace("%%mobile%%", $mobile, $translations_message);
            $return_message = str_replace("%%companyName%%", $companyName, $return_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $return_message, "errorCode" => -100);
        }

        // get xun_business_register_marketing record
        // $get_business_register_marketing = $db->rawQuery("SELECT * FROM `xun_business_register_marketing` WHERE reference_id = $reference_id AND verified_at IS NOT NULL ORDER BY verified_at DESC LIMIT 1");
        $now = date("Y-m-d H:i:s");
        $request_at_limit = date("Y-m-d H:i:s", strtotime('-30 minutes', strtotime($now)));
        $timeout = 15;

        $get_business_register_marketing = $db->rawQuery("SELECT * FROM `xun_business_register_marketing` where `reference_id` = '$reference_id' and `request_at` > 0 AND `request_at` > '$request_at_limit' ORDER BY request_at DESC");

        $record_size = sizeof($get_business_register_marketing);

        global $setting;
        $companyName = $setting->systemSetting["companyName"];

        // if no record, insert new record
        if ($record_size == 0) {
            $this->generate_marketing_verification_code($reference_id, $business_name, $mobile);

            $translations_message = $this->get_translation_message('B00014') /*%%companyName%% verification code has been sent.*/;
            $return_message = str_replace("%%companyName%%", $companyName, $translations_message);

            return array("code" => "1", "message" => "SUCCESS", "message_d" => $return_message, "timeout" => $timeout);
        } else if (sizeof($get_business_register_marketing) >= 5) {
            $error_message = $this->get_translation_message('E00048') /*You have reached the limit of resending verification code. Please try again later.*/;

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        // check if it's after timeout
        $latest_request = $get_business_register_marketing[0];

        $latest_request_at = $latest_request["request_at"];
        $timeout_time = date("Y-m-d H:i:s", strtotime('+' . $timeout . ' seconds', strtotime($latest_request_at)));

        if ($now < $timeout_time) {
            $error_message = $this->get_translation_message('E00049') /*Please request the verification code again after the timeout.*/;

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $expires_at = $latest_request["expires_at"];

        // check expiry
        if ($latest_request["expires_at"] < $now) {
            // code expired
            $this->generate_marketing_verification_code($reference_id, $business_name, $mobile);

            $translations_message = $this->get_translation_message('B00014') /*%%companyName%% verification code has been sent.*/;
            $return_message = str_replace("%%companyName%%", $companyName, $translations_message);

            return array("code" => "1", "message" => "SUCCESS", "message_d" => $return_message, "timeout" => $timeout);
        }

        // generate verification code
        $verification_code = $latest_request["verify_code"];
        // send verification code, add a request at record to  xun_business_register_marketing
        $this->generate_marketing_verification_code($reference_id, $business_name, $mobile, $verification_code, $expires_at);

        $translations_message = $this->get_translation_message('B00014') /*%%companyName%% verification code has been sent.*/;
        $return_message = str_replace("%%companyName%%", $companyName, $translations_message);

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $return_message, "timeout" => $timeout);
    }

    public function business_market_code($params)
    {
        $db = $this->db;

        $verify_code = trim($params["verify_code"]);
        $reference_id = trim($params["id"]);

        if ($verify_code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00018') /*Verify code cannot be empty.*/);
        };

        if ($reference_id == '') {
            $return_message = $this->get_translation_message('E00006');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00006') /*ID cannot be empty.*/);
        };

        $now = date("Y-m-d H:i:s");

        $xun_business_register_marketing = $db->rawQuery("SELECT * FROM `xun_business_register_marketing` WHERE reference_id = $reference_id AND request_at IS NOT NULL ORDER BY request_at DESC LIMIT 1");

        $expires_at = $xun_business_register_marketing[0]["expires_at"];

        $latest_request = $xun_business_register_marketing[0];
        if (empty($xun_business_register_marketing)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00044') /*Invalid record.*/);
        } else if ($latest_request["expires_at"] < $now) {
            $error_message = $this->get_translation_message('E00050') /*You have failed to verify your mobile number. Click resend to receive a new verification code.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        } else if ($latest_request["verify_code"] != $verify_code) {
            // invalid code
            unset($latest_request["id"]);
            $latest_request["request_at"] = 0;
            $latest_request["verified_at"] = $now;

            $db->insert("xun_business_register_marketing", $latest_request);
            $error_message = $this->get_translation_message('E00062') /*The code you entered is incorrect. Please try again later.*/;

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        unset($latest_request["id"]);
        $latest_request["request_at"] = 0;
        $latest_request["verified_at"] = $now;
        $latest_request["status"] = 1;

        $db->insert("xun_business_register_marketing", $latest_request);

        // return response
        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00015') /*Verification code successfully verified*/, "id" => $reference_id);
    }

    public function business_verify($params)
    {
        $db = $this->db;

        global $config;

        $verify_code = trim($params["verify_code"]);

        if ($verify_code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00018') /*Verify code cannot be empty.*/);
        };

        $now = date("Y-m-d H:i:s");

        $db->where("verification_code", $verify_code);
        $result = $db->getOne("xun_business_verification");

        if (!$result) {
            $error_message = $this->get_translation_message('E00052') /*Invalid activation link.*/;
            $errorCode = -102;
            $title = $this->get_translation_message('E00053') /*Error Activating Account.*/;

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "errorCode" => $errorCode, "title" => $title);
            // header("Location: https://" . $config["server"] . "/signUpActivateExpired.php", true, 301);
            // exit();
        }

        // check if is expired
        // code expires in 2 days
        $expired_at = date("Y-m-d H:i:s", strtotime('+2 days', strtotime($result["created_at"])));

        if ($expired_at < $now) {
            $error_message = $this->get_translation_message('E00054') /*Your activation link has expired. Please request a new activation link.*/;
            $errorCode = -101;
            $title = $this->get_translation_message('E00055') /*Activation Link Has Expired*/;

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "errorCode" => $errorCode, "title" => $title);
        }
        // if success
        // update xun_business_account

        $business_email = $result["business_email"];
        $db->where("email", $business_email);
        $xun_business_account = $db->getOne("xun_business_account");

        if ($xun_business_account["email_verified"] == 1) {

            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00016') /*Business account verified.*/, "title" => $this->get_translation_message('B00017') /*Account Successfully Activated*/);
        } else {
            $updateData["email_verified"] = 1;
            $updateData["updated_at"] = $now;
            $db->where("email", $business_email);
            $db->update("xun_business_account", $updateData);

            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00016') /*Business account verified.*/, "title" => $this->get_translation_message('B00017') /*Account Successfully Activated*/);
            // redirect URL to success page
            // header("Location: https://" . $config["server"] . "/signUpSuccessActivated.php", true, 303);
            // exit();
        }
    }

    public function business_register_resend_email($params, $source)
    {
        $db = $this->db;
        $general = $this->general;

        $business_email = trim($params["business_email"]);

        // Param validations
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        };

        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        // get verification code from xun_business_verification
        $db->where("email", $business_email);
        $xun_business_account = $db->getOne("xun_business_account");

        if (!$xun_business_account) {
            $error_message = $this->get_translation_message('E00056') /*This is not a registered user. Please register at our Sign Up page.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        if($source == "nuxpay"){
            $db->where("email", $business_email);
            $xun_business_verification = $db->getOne("xun_email_verification");
        }else{
            $db->where("business_email", $business_email);
            $xun_business_verification = $db->getOne("xun_business_verification");
        }

        $now = date("Y-m-d H:i:s");
        if (!$xun_business_verification) {
            // generate new verification code
            $verification_code = $general->generateAlpaNumeric(16);

            // insert to xun_business_verification
            if($source == "nuxpay"){
                $fields = array("email", "verification_code", "type", "created_at");
                $values = array($email, $verification_code, "nuxpay", $created_at);
                $arrayData = array_combine($fields, $values);
                $email_verification_id = $db->insert("xun_email_verification", $arrayData);
            }else{
                $fields = array("business_email", "verification_code", "created_at");
                $values = array($business_email, $verification_code, $now);
                $arrayData = array_combine($fields, $values);
                $db->insert("xun_business_verification", $arrayData);
            }
        } else {
            $code_expires_at = date("Y-m-d H:i:s", strtotime('+2 days', strtotime($xun_business_verification["created_at"])));

            // check if email is already verified
            if ($code_expires_at < $now) {
                // check expiry (2 days)
                // generate new code if expired

                $verification_code = $general->generateAlpaNumeric(16);

                $updateData["verification_code"] = $verification_code;
                $updateData["created_at"] = $now;

                // update record
                if($source == "nuxpay"){
                    $db->where("email", $business_email);
                    $db->update("xun_email_verification", $updateData);
                }else{
                    $db->where("business_email", $business_email);
                    $db->update("xun_business_verification", $updateData);
                }
            } else {
                $verification_code = $xun_business_verification["verification_code"];
            }
        }

        // get business name
        $db->where("email", $business_email);
        $xun_business = $db->getOne("xun_business");

        if (!$xun_business) {
            // invalid business
            $error_message = $this->get_translation_message('E00056') /*This is not a registered user. Please register at our Sign Up page.*/;

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $business_name = $xun_business["name"];

        $send_email_result = $this->send_activation_email($business_email, $business_name, $verification_code, $source
    );

        // return response
        $translations_message = $this->get_translation_message('B00018') /*Activation email resent to %%business_email%%.*/;
        $return_message = str_replace("%%business_email%%", $business_email, $translations_message);

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $return_message, "xun_business" => array("business_email" => $business_email));
    }

    public function business_mobile_verifycode_get($params)
    {
        $db = $this->db;
        $general = $this->general;

        $business_email = trim($params["business_email"]);
        $mobile = trim($params["mobile"]);

        global $setting;
        $companyName = $setting->systemSetting["companyName"];
        $companyName = $params["companyName"] ? trim($params["companyName"]) : $companyName;

        // Param validations
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/, "developer_msg" => "business_email cannot be empty");
        };

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/, "developer_msg" => "mobile cannot be empty");
        };

        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        // check mobile format
        $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
        if ($mobileNumberInfo["isValid"] == 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/);
        }

        // Check if mobile is registered Xun user
        $db->where("username", $mobile);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            if ($companyName != 'TheNux'){
                $translations_message = "%%mobile%% is not a registered %%companyName%% account. Download and install %%companyName%% now to link it with your %%companyName%% account.";
            }else{
                $translations_message = $this->get_translation_message('E00047') /*%%mobile%% is not a registered %%companyName%% account. Download and install %%companyName%% now to link it with your %%companyName%% Business account.*/;
            }
            $return_message = str_replace("%%mobile%%", $mobile, $translations_message);
            $return_message = str_replace("%%companyName%%", $companyName, $return_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $return_message, "errorCode" => -100);
        }

        // get from xun_business_mobile_verification
        // id     business_email     mobile_number     verification_code     expires_at     request_at
        $now = date("Y-m-d H:i:s");
        $request_at_limit = date("Y-m-d H:i:s", strtotime('-30 minutes', strtotime($now)));

        $xun_business_mobile_verification = $db->rawQuery("SELECT * FROM `xun_business_mobile_verification` where `business_email` = '$business_email' and `request_at` > 0 AND `request_at` > '$request_at_limit' ORDER BY request_at DESC");

        $db->where("email", $business_email);
        $xun_business = $db->getOne("xun_business");

        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00038') /*You have insufficient privilege to perform this operation.*/);
        }

        $business_name = $xun_business["name"];

        $record_size = sizeof($xun_business_mobile_verification);

        // if no record, insert new record
        if ($record_size == 0) {
            $this->generate_mobile_verification_code($business_email, $business_name, $mobile);
            $timeout = $this->get_mobile_verification_timeout(1);

            $translations_message = $this->get_translation_message('B00014') /*%%companyName%% verification code has been sent.*/;
            $return_message = str_replace("%%companyName%%", $companyName, $translations_message);

            return array("code" => "1", "message" => "SUCCESS", "message_d" => $return_message, "timeout" => $timeout);
        } else if ($record_size >= 5) {
            $error_message = $this->get_translation_message('E00048') /*You have reached the limit of resending verification code. Please try again later.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        // check if it's after timeout
        $timeout = $this->get_mobile_verification_timeout($record_size);
        $latest_request = $xun_business_mobile_verification[0];
        $latest_request_at = $latest_request["request_at"];
        $timeout_time = date("Y-m-d H:i:s", strtotime('+' . $timeout . ' seconds', strtotime($latest_request_at)));

        if ($now < $timeout_time) {
            $error_message = $this->get_translation_message('E00049') /*Please request the verification code again after the timeout.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $expires_at = $latest_request["expires_at"];

        // check expiry
        if ($latest_request["expires_at"] < $now) {
            // code expired
            // generate verification code
            $this->generate_mobile_verification_code($business_email, $business_name, $mobile);
        } else {
            $verification_code = $latest_request["verification_code"];
            // send verification code, add a request at record to  xun_business_register_marketing
            $this->generate_mobile_verification_code($business_email, $business_name, $mobile, $verification_code, $expires_at);

        }

        $new_timeout = $this->get_mobile_verification_timeout($record_size + 1);

        $translations_message = $this->get_translation_message('B00014') /*%%companyName%% verification code has been sent.*/;
        $return_message = str_replace("%%companyName%%", $companyName, $translations_message);
        return array("code" => "1", "message" => "SUCCESS", "message_d" => $return_message, "timeout" => $new_timeout);
    }

    public function business_mobile_verifycode_verify($params, $type = null)
    {
        $db = $this->db;
        $general = $this->general;

        global $setting;
        $companyName = $setting->systemSetting["companyName"];

        $business_email = trim($params["business_email"]);
        $mobile = trim($params["mobile"]);
        $verify_code = trim($params["verify_code"]);
        $companyName = $params["companyName"] ? trim($params["companyName"]) : $companyName;
        $user_check = isset($params["user_check"]) ? $params["user_check"] : 1;
        $from_nuxpay_admin = trim($params['from_nuxpay_admin']) ? trim($params['from_nuxpay_admin']) : '';

        if(!$type){
            // Param validations
            if ($business_email == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/, "developer_msg" => "business_email cannot be empty");
            };
        }

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/, "developer_msg" => "mobile cannot be empty");
        };

        if($from_nuxpay_admin == ''){
            if ($verify_code == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00060') /*Verification code cannot be empty.*/, "developer_msg" => "verify_code cannot be empty");
            };
        }
       
        if(!$type){
            if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
            }
        }
        
        // check mobile format
        $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
        if ($mobileNumberInfo["isValid"] == 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/);
        }
      
        if ($user_check == 1){
            // Check if mobile is registered Xun user
            $db->where("username", $mobile);
            $xun_user = $db->getOne("xun_user");
            if (!$xun_user) {
                $translations_message = $this->get_translation_message('E00047') /*%%mobile%% is not a registered %%companyName%% account. Download and install %%companyName%% now to link it with your %%companyName%% Business account.*/;
                $return_message = str_replace("%%mobile%%", $mobile, $translations_message);
                $return_message = str_replace("%%companyName%%", $companyName, $return_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $return_message, "errorCode" => -100);
            }

            // get from xun_business_mobile_verification
            // id     business_email     mobile_number     verification_code     expires_at     request_at
            $now = date("Y-m-d H:i:s");
    
            $xun_business_mobile_verification = $db->rawQuery("SELECT * FROM `xun_business_mobile_verification` where `business_email` = '$business_email' AND request_at IS NOT NULL ORDER BY request_at DESC LIMIT 1");
    
            $latest_request = $xun_business_mobile_verification[0];
    
            if (empty($xun_business_mobile_verification)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00044') /*Invalid record.*/);
            } else if ($latest_request["expires_at"] < $now) {
                $error_message = $this->get_translation_message('E00050') /*You have failed to verify your mobile number. Click resend to receive a new verification code.*/;
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            } else if ($latest_request["verification_code"] != $verify_code) {
                // invalid code
                $error_message = "The code you entered is incorrect. Please try again later.";
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }
            $updateData["main_mobile"] = $latest_request["mobile_number"];
        }else{
            $updateData["main_mobile"] = $mobile;
        }
        $updateData["main_mobile_verified"] = 1;

        if($type){
            $db->where('username', $mobile);
            $db->where('register_site', $type);
            $xun_user = $db->getOne('xun_user');
            
            $user_id = $xun_user["id"];

            $db->where('user_id', $user_id);
        }
        else{
            $db->where("email", $business_email);
        }
       
        $db->update("xun_business_account", $updateData);

        if($type){
            $db->where('user_id', $user_id);
        }
        else{
            $db->where("email", $business_email);
        }
        
        $xun_business = $db->getOne("xun_business");
        $business_id = $xun_business["user_id"];
        $business_name = $xun_business["name"];
        // get business id and business name

        // create business owner....
        //$erlang_return = $this->initialise_new_business($business_id, $business_email, $business_name, $mobile);

        // return response
        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00015') /*Verification code successfully verified*/, "erlang_return" => $erlangReturn);
    }

    private function get_mobile_verification_timeout($length)
    {
        switch ($length) {
            case 1:
                $timeout = 1 * 60;

                break;
            case 2:
                $timeout = 2 * 60;

                break;
            case 3:
                $timeout = 3 * 60;

                break;
            case 4:
                $timeout = 4 * 60;

                break;
            case 5:
                $timeout = 30 * 60;

                break;

            default:
                $timeout = 30 * 60;
                break;
        }

        return $timeout;

    }

    public function initialise_new_business($business_id, $business_email, $business_name, $owner_mobile, $ip = NULl, $country = NULL)
    {
        $db = $this->db;
        $general = $this->general;
        $post = $this->post;

        global $config;
        $erlang_server = $config["erlang_server"];
        // create employee, general tag, tag_employee
        // call erlang
        // Full texts    id     business_id     mobile     name     status     employment_status     created_at     updated_at     old_id

        $old_id = $this->get_employee_old_id($business_id, $owner_mobile);

        $created_at = date("Y-m-d H:i:s");
        $fields = array("business_id", "name", "mobile", "employment_status", "role", "old_id", "created_at", "updated_at");
        $values = array($business_id, $business_name, $owner_mobile, "confirmed", "owner", $old_id, $created_at, $created_at);
        $arrayData = array_combine($fields, $values);

        $employee_id = $db->insert("xun_employee", $arrayData);

        $default_business_tag = "General";

        // id     business_id     tag     description     working_hour_from     working_hour_to     status     priority     created_at     updated_at
        $fields = array("business_id", "tag", "status", "created_at", "updated_at");
        $values = array($business_id, $default_business_tag, 1, $created_at, $created_at);
        $arrayData = array_combine($fields, $values);
        $business_tag_id = $db->insert("xun_business_tag", $arrayData);

        // id     employee_id     username     business_id     tag     status     created_at     updated_at
        $fields = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
        $values = array($old_id, $owner_mobile, $business_id, $default_business_tag, 1, $created_at, $created_at);
        $arrayData = array_combine($fields, $values);
        $xun_business_tag_employee = $db->insert("xun_business_tag_employee", $arrayData);

        // follow business

        $fields = array("business_id", "username", "server_host", "created_at", "updated_at");
        $values = array($business_id, $owner_mobile, $erlang_server, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));

        $insertData = array_combine($fields, $values);

        $row_id = $db->insert("xun_business_follow", $insertData);

        $xun_business_follow_uuid = (string) $row_id;

        $this->create_default_business_subscription($business_id);

        $xmpp_password = $general->generateAlpaNumeric(8);

        $db->where("mobile_number", $owner_mobile);
        $device_os = $db->getValue("xun_user_device", "os");
        if ($device_os == "1"){ $device_os = "Android";}
        else if ($device_os == "2") { $device_os = "iOS"; }
        
        $db->where("username", $owner_mobile);
        $user_info = $db->getOne("xun_user", "id, nickname");
        $user_nickname = $user_info["nickname"];
        $user_id = $user_info["id"];

        $user_country_info_arr = $this->get_user_country_info([$owner_mobile]);
        $user_country_info = $user_country_info_arr[$owner_mobile];
        $country = $user_country_info["name"];

        $db->where("user_id", $user_id);
        $db->where("name", "lastLoginIP");
        $user_ip = $db->getValue("xun_user_setting", "value");

        $xmpp_message = "Username: " . $user_nickname . "\n";
        $xmpp_message .= "Phone number: " . $owner_mobile . "\n";
        $xmpp_message .= "IP: " . $user_ip . "\n";
        $xmpp_message .= "Country: " . $country . "\n";
        $xmpp_message .= "Device: " . $device_os . "\n";
        $xmpp_message .= "Email: " . $business_email . "\n";
        $xmpp_message .= "Business Name: " . $business_name . "\n";

        $erlang_params["business_id"] = (string) $business_id;
        $erlang_params["business_name"] = $business_name;
        $erlang_params["message_tag"] = "New Business Registration";
        $erlang_params["message"] = $xmpp_message;
        $erlang_params["business_follow_id"] = $xun_business_follow_uuid;
        $erlang_params["mobile"] = $owner_mobile;
        $erlang_params["employee_id"] = (string) $old_id;
        $erlang_params["password"] = $xmpp_password;
        $erlang_params["default_tag"] = $default_business_tag;
        $erlang_params["user_server"] = $erlang_server;
        $erlangReturn = $post->curl_post("business/register", $erlang_params);

        if ($erlangReturn["code"] == 1) {
            $passwd_fields = array("username", "server_host", "password");
            $passwd_values = array($business_id, $erlang_server, $xmpp_password);
            $insertData = array_combine($passwd_fields, $passwd_values);

            $db->insert("xun_passwd", $insertData);
        }
        return $erlangReturn;
    }

    public function send_activation_email($business_email, $business_name, $verification_code, $source = "business")
    {
        $general = $this->general;
        $xunEmail = $this->xunEmail;

        global $setting;
        
        if($source == "nuxpay"){
            $companyName = "NuxPay";
            $email_body = $xunEmail->getNuxPayActivationEmailHtml($business_name, $verification_code, $business_email, $companyName);
        }else{
            $companyName = $setting->systemSetting["companyName"];
            $email_body = $xunEmail->getActivationEmailHtml($business_name, $verification_code, $business_email, $server);
        }

        $translations_message = $this->get_translation_message('B00076') /*Activate your email at %%companyName%% Business.*/;
        $subject = str_replace("%%companyName%%", $companyName, $translations_message);

        $emailParams["subject"] = $subject;
        $emailParams["body"] = $email_body;
        $emailParams["recipients"] = array($business_email);
        if($source == "nuxpay")
            $emailParams["emailFromName"] = "NuxPay";

        $result = $general->sendEmail($emailParams);
        return $result;
    }

    public function send_forgot_password_email($business_email, $password, $source)
    {
        $general = $this->general;
        $xunEmail = $this->xunEmail;

        global $setting;
        
        if($source == "nuxpay"){
            $companyName = "NuxPay";
            $email_body = $xunEmail->getPayForgetPasswordEmailHtml($password, $companyName);
        }else{
            $companyName = $setting->systemSetting["companyName"];
            $email_body = $xunEmail->getForgotPasswordHtml($password);
        }

        $translations_message = $this->get_translation_message('B00077') /*Reset Your %%companyName%% Business Password*/;
        $subject = str_replace("%%companyName%%", $companyName, $translations_message);

        $emailParams["subject"] = $subject;
        $emailParams["body"] = $email_body;
        $emailParams["recipients"] = array($business_email);
        if($source == "nuxpay")
            $emailParams["emailFromName"] = "NuxPay";
            $emailParams["emailPassword"] = "nuxpay0909";
            $emailParams["emailAddress"] = "support@nuxpay.com";

        $result = $general->sendEmail($emailParams);
    }

    public function business_signin($params, $ip)
    {
        $db = $this->db;
        $general = $this->general;

        $business_email = trim($params["business_email"]);
        $business_password = trim($params["business_password"]);
        $time_zone = trim($params["time_zone"]);

        // Param validations
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/, "developer_msg" => "business_email cannot be empty");
        };

        if ($business_password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00014') /*Password cannot be empty*/, "developer_msg" => "business_password cannot be empty");
        };

        if ($time_zone == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00020') /*Time zone cannot be empty.*/, "developer_msg" => "time_zone cannot be empty");
        };

        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        // TODO: check hashed password

        // get xun_business_account record
        $db->where("email", $business_email);
        $xun_business_account = $db->getOne("xun_business_account");

        if (!$xun_business_account) {
            $error_message = $this->get_translation_message('E00063') /*This email address doesn't exist. Enter a different email address or get a new one.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        //if(!password_verify($password, $result[0]['password']))

        if (!password_verify($business_password, $xun_business_account['password'])) {
            $error_message = $this->get_translation_message('E00064') /*Your password is incorrect. Please try again.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        if ($xun_business_account["email_verified"] === 0) {
            $error_message = $this->get_translation_message('E00065') /*Your email is not activated. Please activate your email before signing in.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }
        $db->where('email', $business_email);
        $business_account = $db->getOne('xun_business_account', 'email, last_login');

        $previous_last_login = $business_account['last_login'];//get the last login time before the upcoming login
        
        $now = date("Y-m-d H:i:s");
        if (!$xun_business_account["time_zone"] || $xun_business_account["time_zone"] == "") {
            $update_xun_business_account["time_zone"] = $time_zone;
            $update_xun_business_account["updated_at"] = $now;
        }
        $update_xun_business_account['previous_last_login'] = $previous_last_login;
        $update_xun_business_account["last_login"] = $now;
        $db->where("email", $business_email);
        $db->update("xun_business_account", $update_xun_business_account);

        $db->where("email", $business_email);
        $xun_business = $db->getOne("xun_business");
        $business_id = $xun_business["user_id"];

        $db->where('business_id', $business_id);
        $business_coin = $db->getOne('xun_business_coin');

        $has_setup_reward = $business_coin ? 1 : 0;

        $db->where('user_id', $business_id);
        $db->where('name', array('ipCountry', 'lastLoginIP', 'isDemoAccount', 'accessDenied'), 'IN');
        $user_setting = $db->map('name')->ArrayBuilder()->get('xun_user_setting');
        $xunIP = new XunIP($db);
        $ip_country = $xunIP->get_ip_country($ip);
        $general->setIsDemoAccount($user_setting['isDemoAccount']["value"]);
        // $permission["accessible"] = "";
        $permission["access_denied"] = $user_setting['accessDenied']["value"]?:""; // example : nuxpay,live_chat,team_member,business_chat

        if($user_setting['ipCountry']){
            
            $update_country = array(
                "value" => $ip_country,
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $db->where('user_id', $business_id);
            $db->where('name','ipCountry');
            $db->update('xun_user_setting', $update_country);
        }
        else{
            $insert_country = array(
                "user_id" => $business_id,
                "name" => 'ipCountry',
                "value" => $ip_country,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $db->insert('xun_user_setting', $insert_country);
        }

        if($user_setting['lastLoginIP']){
            $update_ip = array(
                "value" => $ip,
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $db->where('user_id', $business_id);
            $db->where('name','lastLoginIP');
            $db->update('xun_user_setting', $update_ip);
        }
        else{
            $insert_ip = array(
                "user_id" => $business_id,
                "name" => 'lastLoginIP',
                "value" => $ip,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $db->insert('xun_user_setting', $insert_ip);
        }

        //get livechat settting contact url
        $url = $db->rawQuery("SELECT `contact_us_url` FROM `xun_business_livechat_setting` WHERE business_id = '$business_id' ");
        $contact_us_url = $url[0][contact_us_url];

        // get demo account existing token 
        if($general->isDemoAccount){
            $db->where("business_id", $business_id);
            $db->where("status", "1");
            $demo_account_access_token = $db->getValue("xun_access_token", "access_token");
        }

        // generate access token
        $access_token = $demo_account_access_token?:$general->generateAlpaNumeric(32);

        // update all status to 0, insert new record
        if(!$general->isDemoAccount){
            $updateData["status"] = 0;
            $db->where("business_email", $business_email);
            $db->update("xun_access_token", $updateData);
        }
        
        // insert new token
        if(!$general->isDemoAccount || ($general->isDemoAccount && $demo_account_access_token=="")){
            $access_token_expires_at = date("Y-m-d H:i:s", strtotime('+12 hours', strtotime($now)));

            $fields = array("business_email", "business_id", "access_token", "expired_at");
            $values = array($business_email, $business_id, $access_token, $access_token_expires_at);

            $insertData = array_combine($fields, $values);

            $row_id = $db->insert("xun_access_token", $insertData);
        }

        $xun_business_obj = $this->compose_xun_business($xun_business);
        $xun_business_obj['contact_us_url'] = $contact_us_url;
        $mobile = $xun_business_account["main_mobile"];
        $mobile_is_verified = $xun_business_account["main_mobile_verified"];
        $package_code = "";
        $business_package = (object) [];
        $credit_balance = 0;
        $session_timeout = 12 * 60 * 60;

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00021') /*Business' credentials verified.*/, "xun_business_account" => array("business_email" => $business_email), "access_token" => $access_token, "isMobileVerified" => $mobile_is_verified, "isBusinessCreated" => 1, "mobile" => $mobile, "business" => $xun_business_obj, "package_code" => $package_code, "credit_balance" => $credit_balance, "session_timeout" => $session_timeout, "has_setup_reward" => $has_setup_reward, "is_demo_account" => $general->isDemoAccount, "permission" => $permission);
    }

    public function validate_access_token($business_id, $access_token)
    {

        $db = $this->db;
        $general = $this->general;

        $date = date("Y-m-d H:i:s");

        // get demo account flag
        $db->where("user_id", $business_id);
        $db->where("name", "isDemoAccount");
        $isDemoAccount = $db->getValue("xun_user_setting", "value");
        $general->setIsDemoAccount($isDemoAccount);

        $db->where("business_id", $business_id);
        $db->where("access_token", $access_token);
        $db->where("status", "1");
        if(!$general->isDemoAccount) $db->where("expired_at", $date, ">"); // demo account no token expired
        $token_result = $db->getOne("xun_access_token");

        if (!$token_result) {
            return false;
        }

        return true;

    }

    public function validate_api_key($business_id, $api_key){
        $db = $this->db;

        $db->where("business_id", $business_id);
        $db->where("apikey", $api_key);
        $db->where("is_enabled", 1);
        $db->where("status", "active");
        $db->where("apikey_expire_datetime", date("Y-m-d H:i:s"), ">");

        $api_key_data = $db->getOne("xun_business_api_key");

        if(!$api_key_data){
            return false;
        }

        return true;
    }

    public function business_changepassword($params){
        $db = $this->db;
        $business_email = trim($params["business_email"]);
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/, "developer_msg" => "business_email cannot be empty");
        }
        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        $db->where("email", $business_email);
        return $this->change_password($params);
    }

    public function nuxpay_changepassword($params){
        $db = $this->db;
        $business_id = trim($params["business_id"]);
        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        
        $db->where("user_id", $business_id);
        return $this->change_password($params);
    }

    private function change_password($params)
    {
        // X-Xun-Token
        // business_email
        // current_password
        // new_password
        // confirm_password
        $db = $this->db;

        $business_email = trim($params["business_email"]);
        $business_id = trim($params["business_id"]);
        $current_password = trim($params["current_password"]);
        $new_password = trim($params["new_password"]);
        $confirm_password = trim($params["confirm_password"]);

        // Param validations

        if ($current_password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00066') /*Current password cannot be empty*/, "developer_msg" => "current_password cannot be empty");
        }

        if ($new_password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00067') /*New password cannot be empty*/, "developer_msg" => "new_password cannot be empty");
        }

        if ($confirm_password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00068') /*Confirm password cannot be empty*/, "developer_msg" => "confirm_password cannot be empty");
        };

        $xun_business_account = $db->getOne("xun_business_account");

        if (!$xun_business_account) {
            $error_message = $this->get_translation_message('E00069') /*This business account does not exists.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $password = $xun_business_account["password"];

        if (!password_verify($current_password, $password)) {
            $error_message = $this->get_translation_message('E00070') /*Your password is incorrect. Please try again.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        if ($new_password !== $confirm_password) {
            $error_message = $this->get_translation_message('E00071') /*New password confirmation does not match.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $validate_password = $this->validate_password($new_password);

        if ($validate_password['code'] == 0) {
            $error_message = $validate_password['error_message'];
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00072') /*Invalid password combination.*/, "developer_msg" => "business_password has an invalid character combination", "error_message" => $error_message);

        }

        $now = date("Y-m-d H:i:s");

        $hash_password = password_hash($new_password, PASSWORD_BCRYPT);

        $update_xun_business_account["password"] = $hash_password;
        $update_xun_business_account["updated_at"] = $now;
        if ($business_email){
            $db->where("email", $business_email);
        }else{
            $db->where("user_id", $business_id);
        }
        $db->update("xun_business_account", $update_xun_business_account);

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00022') /*Account password changed.*/);
    }

    public function business_forgotpassword($params, $source = null)
    {
        $db = $this->db;
        $general = $this->general;
        global $setting;
        $companyName = $setting->systemSetting["companyName"];

        $business_email = trim($params["business_email"]);
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/, "developer_msg" => "business_email cannot be empty");
        }

        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        $new_password = $general->generateAlpaNumeric(8);
        $hash_password = password_hash($new_password, PASSWORD_BCRYPT);

        $db->where("email", $business_email);
        $xun_business_account = $db->getOne("xun_business_account");

        if (!$xun_business_account) {
            $translations_message = $this->get_translation_message('E00073') /*This email address does not have a registered %%companyName%% Business account*/;
            $error_message = str_replace("%%companyName%%", $companyName, $translations_message);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $update_xun_business_account["password"] = $hash_password;

        $db->where("email", $business_email);
        $db->update("xun_business_account", $update_xun_business_account);

        $this->send_forgot_password_email($business_email, $new_password, $source);

        $translations_message = $this->get_translation_message('B00023') /*Success! We've sent an email to %%business_email%% with password reset instructions.*/;
        $return_message = str_replace("%%business_email%%", $business_email, $translations_message);

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $return_message);
    }

    public function business_profile_get($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $returnData = $this->compose_xun_business($result);
        
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00024') /*Business Profile.*/, "business_id" => $business_id, "xun_business" => $returnData);
    }

    public function business_list($params)
    {
        $db = $this->db;

        $business_email = $params["business_email"];

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        $db->where("email", $business_email);
        $result = $db->get("xun_business");

        foreach ($result as $data) {
            $returnData[] = $this->compose_xun_business($data);
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00025') /*Business listing.*/, "result" => $returnData);
    }

    public function business_edit($params){
        $business_id = $params["business_id"];
        $business_email = $params["business_email"];
        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }
        return $this->profile_edit($params);
    }

    public function nuxpay_edit($params){
        $business_id = $params["business_id"];
        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        return $this->profile_edit($params);
    }

    private function profile_edit($params)
    {
        $db = $this->db;
        $post = $this->post;

        $business_id = $params["business_id"];
        //$business_email = $params["business_email"];
        $business_website = $params["business_website"];
        $business_phone_number = $params["business_phone_number"];
        $business_address1 = $params["business_address1"];
        $business_address2 = $params["business_address2"];
        $business_city = $params["business_city"];
        $business_state = $params["business_state"];
        $business_postal = $params["business_postal"];
        $business_country = $params["business_country"];
        $business_info = $params["business_info"];
        $business_company_size = $params["business_company_size"];
        $business_email_address = $params["business_email_address"];
        $business_name = $params["business_name"];

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $updateData = array();
        // if ($business_website != '') {
            $updateData["website"] = $business_website;
        // }

        // if ($business_phone_number != '') {
            $updateData["phone_number"] = $business_phone_number;
        // }

        // if ($business_address1 != '') {
            $updateData["address1"] = $business_address1;
        // }

        // if ($business_address2 != '') {
            $updateData["address2"] = $business_address2;
        // }

        // if ($business_city != '') {
            $updateData["city"] = $business_city;
        // }

        // if ($business_state != '') {
            $updateData["state"] = $business_state;
        // }

        // if ($business_postal != '') {
            $updateData["postal"] = $business_postal;
        // }

        // if ($business_country != '') {
            $updateData["country"] = $business_country;
        // }

        // if ($business_info != '') {
            $updateData["info"] = $business_info;
        // }

        // if ($business_company_size != '') {
            $updateData["company_size"] = $business_company_size;
        // }

        // if ($business_email_address != '') {
            //$updateData["email"] = $business_email_address;
            //$updateData["display_email"] = $business_email_address;
        // }

            $updateData["name"] = $business_name;

        if (!empty($updateData)) {
            $db->where("id", $result["id"]);
            $db->update("xun_business", $updateData);

            $db->where("id", $business_id);
            $db->update("xun_user", array("nickname"=>$business_name));

            //$db->where("user_id", $business_id);
            //$db->update("xun_business_account", array("email"=>$business_email_address));
        }


        $updated_xun_business = array_merge($result, $updateData);
        $returnData = $this->compose_xun_business($updated_xun_business);
        // header('Content-Type: application/json');

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00026') /*Business profile updated.*/, "xun_business" => $returnData, "erlang_return" => $erlangReturn);
    }

    public function business_delete($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        //update business status to 0 = deleted
        $updateData["status"] = 0;

        $db->where("user_id", $business_id);
        $db->update("xun_business", $updateData);

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00027') /*Business Deleted.*/, "result" => $returnData);
    }

    public function business_wallet_search($params)
    {
        $db = $this->db;

        $business_name = $params["business_name"];

        if ($business_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00015') /*Business name cannot be empty.*/);
        }       

        $db->where("name", "%$business_name%", "LIKE");
        $db->where("b.active", 1); 
        $db->where("b.deleted", 0);
        $db->where("c.verified", 1);
        $db->join("xun_crypto_user_address b", "a.user_id=b.user_id", "INNER");
        $db->join("xun_crypto_user_address_verification c", "a.user_id=c.user_id and b.address=c.address", "INNER");

        $result = $db->get("xun_business a");

        foreach ($result as $data) {

            $arrData["business_id"] = (string) $data["user_id"];
            $arrData["business_name"] = $data["name"] ? $data["name"] : "";
            $arrData["business_profile_picture_url"] = $data["profile_picture_url"] ? $data["profile_picture_url"] : "";
            $returnData[] = $arrData;
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00028') /*Search result.*/, "result" => $returnData);
    }

    public function business_search($params)
    {
        $db = $this->db;

        $business_name = $params["name"];

        $db->where("name", "%$business_name%", "LIKE");
        $result = $db->get("xun_business");

        foreach ($result as $data) {
            $returnData[] = $this->compose_xun_business($data);
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00028') /*Search result.*/, "result" => $returnData);
    }

    public function nuxpay_profile_picture_uplaod($params){
        $business_id = trim($params["business_id"]);
        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        return $this->profile_picture_upload($params);
    }

    public function business_profile_picture_upload($params){
        $business_id = trim($params["business_id"]);
        $business_email = trim($params["business_email"]);
        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        return $this->profile_picture_upload($params);
    }

    private function profile_picture_upload($params)
    {
        $db = $this->db;
        $post = $this->post;

        $business_id = trim($params["business_id"]);
        $business_email = trim($params["business_email"]);
        $business_profile_picture = trim($params["business_profile_picture"]);
        $source = trim($params['source']);

        if (is_null($business_profile_picture)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00074') /*Business Profile Picture field is required*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        //upload and get url from s3

        $updateData["profile_picture"] = $business_profile_picture;
        if(empty($business_profile_picture)){
            $updateData["profile_picture_url"] = '';
        }
        $db->where("user_id", $business_id);
        $update_id = $db->update("xun_business", $updateData);

        if($business_profile_picture && $update_id){
            $xunBusinessService = new XunBusinessService($db);
            $uploadImageRet = $xunBusinessService->updateBusinessProfilePicture($business_id, $business_profile_picture, $source);
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00029') /*Updated business profile picture.*/, "result" => $returnData);
    }

    private function compose_xun_business($result)
    {
        $db = $this->db;
        $general = $this->general;
        // $mobile = $result['phone_number'];

        // if(!$mobile){
        $db->where('user_id', $result["user_id"]);
        $userDetail = $db->getOne('xun_business_account', 'main_mobile, main_mobile_verified, email, email_verified');
        $main_mobile = $userDetail['main_mobile'];
        $main_mobile_verified = $userDetail['main_mobile_verified'];
        $email = $userDetail['email'];
        $email_verified = $userDetail['email_verified'];
        // }

        $mobileNumberInfo = $general->mobileNumberInfo($main_mobile, null);

        if($mobileNumberInfo['isValid'] == 1){
            $countryCode = $mobileNumberInfo['countryCode']; 
            $mobile = $mobileNumberInfo['mobileNumberWithoutFormat'];
            $phoneNumber = ltrim($mobile, $countryCode);
        }

        $returnData["uuid"] = (string) $result["user_id"];
        $returnData["business_email"] = $email;
        $returnData["business_email_verified"] = $email_verified;
        $returnData["business_name"] = $result["name"] ? $result["name"] : "";
        $returnData["business_phone_number"] = $phoneNumber ? $phoneNumber : "";
        $returnData["business_phone_number_verified"] = $main_mobile_verified;
        $returnData["business_country_code"] = $countryCode ? $countryCode : "";
        $returnData["business_website"] = $result["website"] ? $result["website"] : "";
        $returnData["business_address1"] = $result["address1"] ? $result["address1"] : "";
        $returnData["business_address2"] = $result["address2"] ? $result["address2"] : "";
        $returnData["business_city"] = $result["city"] ? $result["city"] : "";
        $returnData["business_state"] = $result["state"] ? $result["state"] : "";
        $returnData["business_postal"] = $result["postal"] ? $result["postal"] : "";
        $returnData["business_country"] = $result["country"] ? $result["country"] : "";
        $returnData["business_info"] = $result["info"] ? $result["info"] : "";
        $returnData["business_profile_picture"] = $result["profile_picture"] ? $result["profile_picture"] : "";
        $returnData["business_profile_picture_url"] = $result["profile_picture_url"] ? $result["profile_picture_url"] : "";
        $returnData["business_verified"] = is_null($result["verified"]) || "" ? 0 : $result["verified"];
        $returnData["business_status"] = 1;
        $returnData["business_company_size"] = $result["company_size"] ? $result["company_size"] : "";
        $returnData["business_email_address"] = $email ? $email : "";
        $returnData["business_created_date"] = $result["created_at"] ? $general->formatDateTimeToIsoFormat($result["created_at"]) : "";

        $returnData['main_mobile'] = $main_mobile;

        return $returnData;
    }

    public function app_business_employee_edit($params) {

        $db = $this->db;
        global $config;

        $server_host = $config["erlang_server"];
        $crypto_host = "crypto." . $server_host;

        $business_id = $params["business_id"];
        $employee_name = $params["employee_name"];
        $employee_id = $params["employee_id"];
        $livechat_tag = $params["live_chat_tag"];
        $business_employee_tag = $params["business_chat_tag"];
        $share_key = $params["share_key"] ? $params["share_key"] : false;
        $encrypted_wallet_key = $params["encrypted_wallet_key"];
		$share_mode = $params["share_mode"] ? $params["share_mode"] : "";

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($employee_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00075') /*Employee Name cannot be empty*/);
        }

        if ($employee_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00011') /*Employee ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00082') /*Business not found.*/);
        }

        ///////
        if($livechat_tag == "all") {

            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $livechat_tag = $db->getValue("xun_business_tag", "DISTINCT tag", null);
        }

        if($business_employee_tag == "all") {

            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $business_employee_tag = $db->getValue("xun_business_employee_tag", "DISTINCT tag", null);
        }
        $db->where("old_id", $employee_id);
        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $xun_employee = $db->getOne("xun_employee");

        if (!$xun_employee) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00083') /*This team member does not exist.*/);
        }

        $username = $xun_employee["mobile"];

		$updateData["share_mode"] = $share_mode;
        $updateData["is_share_key"] = $share_key;
        $updateData["name"] = $employee_name;

        $db->where("id", $xun_employee["id"]);
        $db->update("xun_employee", $updateData);

        /////
        if ($share_key == true && $share_mode == "full_access") {
            $db->where("key_user_id", $employee_id);
            $db->where("key_host", $crypto_host);
            $db->where("status", 1);
            $business_wallet_encrypted_key = $db->getOne("xun_public_key");

            $created_at = date("Y-m-d H:i:s");

            if (!$business_wallet_encrypted_key) {

                $fields = array("old_id", "key_user_id", "key_host", "key", "status", "created_at", "updated_at");
                $values = array($employee_id, $employee_id, $crypto_host, $encrypted_wallet_key, 1, $created_at, $created_at);
                $arrayData2 = array_combine($fields, $values);
                $db->insert("xun_public_key", $arrayData2);

            } else {

                $row_id = $business_wallet_encrypted_key["id"];

                $updateData2["key"] = $encrypted_wallet_key;
                $updateData2["updated_at"] = $created_at;
                $db->where("id", $row_id);
                $db->update("xun_public_key", $updateData2);
            }
        } else {
            $created_at = date("Y-m-d H:i:s");
            $updateData2["status"] = 0;
            $updateData2["updated_at"] = $created_at;
            $db->where("key_user_id", $employee_id);
            $db->where("key_host", $crypto_host);
            $db->where("status", 1);
            $db->update("xun_public_key", $updateData2);
        }


        $business_tag_res = $this->update_employee_tag($business_id, $username, $employee_id, "xun_business_tag", "xun_business_tag_employee", $livechat_tag);

        $business_employee_tag_res = $this->update_employee_tag($business_id, $username, $employee_id, "xun_business_employee_tag", "xun_business_employee_tag_employee", $business_employee_tag);

        // check employment status
        $employment_status = $xun_employee["employment_status"];
        if ($employment_status == "confirmed") {
            // send xmpp event
            $this->update_xmpp_business_tag_employee($business_id, $xun_employee, $business_tag_res);
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00034') /*Business employee details updated.*/);


    }

	public function app_business_employee_add($params) {

        $db = $this->db;
        $post = $this->post;
        global $config;
        global $setting;

        $now = date("Y-m-d H:i:s");

        $companyName = $setting->systemSetting["companyName"];
        $business_id = $params["business_id"];
        $employee_name = $params["employee_name"];
        $employee_mobile = $params["employee_mobile"];
        $livechat_tag = $params["live_chat_tag"];
        $business_employee_tag = $params["business_chat_tag"];
        $share_key = $params["share_key"] ? $params["share_key"] : false;
        $encrypted_wallet_key = $params["encrypted_wallet_key"];
		$share_mode = $params["share_mode"] ? $params["share_mode"] : "";

        $server_host = $config["erlang_server"];
        $crypto_host = "crypto." . $server_host;

        $employee_role = "employee";

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($employee_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00075') /*Employee Name cannot be empty*/);
        }

        if ($employee_mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00076') /*Employee Mobile cannot be empty*/);
        }

        if ($share_key == true && $encrypted_wallet_key == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00299') /*Encrypted wallet key cannot be empty.*/);
        }

        ////
        if($livechat_tag == "all") {

            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $livechat_tag = $db->getValue("xun_business_tag", "DISTINCT tag", null);
        }

        if($business_employee_tag == "all") {

            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $business_employee_tag = $db->getValue("xun_business_employee_tag", "DISTINCT tag", null);
        }

        $db->where("username", $employee_mobile);
        $result = $db->getOne("xun_user");
        if (!$result) {
            $translations_message = $this->get_translation_message('E00077') /*This mobile number is not a registered  %%companyName%% user. Only registered %%companyName%% users are allowed to be added as an employee.*/;
            $return_message = str_replace("%%companyName%%", $companyName, $translations_message);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $return_message);
        }

        $db->where("business_id", $business_id);
        $db->where("mobile", $employee_mobile);
        $db->where("status", 1);
        $result = $db->getOne("xun_employee");

        if ($result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00078') /*This mobile number has already been added as an employee.*/);
        }

        $old_id = $this->get_employee_old_id($business_id, $employee_mobile);
        $fields = array("business_id", "mobile", "name", "status", "employment_status", "created_at", "updated_at", "old_id", "role", "is_share_key", "share_mode");
        $values = array($business_id, $employee_mobile, $employee_name, "1", "pending", $now, $now, $old_id, $employee_role, $share_key, $share_mode);

        $insertData = array_combine($fields, $values);

        $new_employee_id = $db->insert("xun_employee", $insertData);


        if ($share_key == true && $share_mode == "full_access") {

            $created_at = date("Y-m-d H:i:s");
            $fields = array("old_id", "key_user_id", "key_host", "key", "status", "created_at", "updated_at");
            $values = array($old_id, $old_id, $crypto_host, $encrypted_wallet_key, 1, $created_at, $created_at);
            $arrayData = array_combine($fields, $values);
            $db->insert("xun_public_key", $arrayData);
        }

        // add to live chat tag and business chat tag
        foreach ($livechat_tag as $tag) {
            $db->where("tag", $tag);
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $xun_business_tag = $db->getOne("xun_business_tag");

            if ($xun_business_tag) {
                $fields = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
                $values = array($old_id, $employee_mobile, $business_id, $tag, "1", $now, $now);
                $insertData = array_combine($fields, $values);
                $db->insert("xun_business_tag_employee", $insertData);
            }
        }

        foreach ($business_employee_tag as $tag) {
            $db->where("tag", $tag);
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $xun_business_employee_tag = $db->getOne("xun_business_employee_tag");

            if ($xun_business_employee_tag) {
                $fields = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
                $values = array($old_id, $employee_mobile, $business_id, $tag, "1", $now, $now);
                $insertData = array_combine($fields, $values);
                $db->insert("xun_business_employee_tag_employee", $insertData);
            }
        }

        //call api to send new employee message
        $newParams["business_id"] = $business_id;
        $newParams["employee_mobile"] = $employee_mobile;
        $newParams["employee_id"] = $old_id;
        $newParams["employee_role"] = $employee_role;
        $erlangReturn = $post->curl_post("/business/employee/add", $newParams);

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00030') /*New member successfully added.*/);

    }

    public function business_employee_add($url_string, $params)
    {

        $db = $this->db;
        $post = $this->post;
        global $setting;

        $now = date("Y-m-d H:i:s");

        $companyName = $setting->systemSetting["companyName"];

        $business_id = $params["business_id"];
        $business_email = $params["business_email"];
        $employee_name = $params["employee_name"];
        $employee_mobile = $params["employee_mobile"];
        $livechat_tag = $params["live_chat_tag"];
        $business_employee_tag = $params["business_chat_tag"];
        $employee_role = "employee";

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($employee_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00075') /*Employee Name cannot be empty*/);
        }

        if ($employee_mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00076') /*Employee Mobile cannot be empty*/);
        }

        if (!$livechat_tag) {
            $livechat_tag = [];
        } else if (!is_array($livechat_tag)) {
            $livechat_tag = [$livechat_tag];
        } else {
            array_values(array_filter(array_unique($livechat_tag)));
        }

        if (!$business_employee_tag) {
            $business_employee_tag = [];
        } else if (!is_array($business_employee_tag)) {
            $business_employee_tag = [$business_employee_tag];
        } else {
            array_values(array_filter(array_unique($business_employee_tag)));
        }

        $db->where("username", $employee_mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            $translations_message = $this->get_translation_message('E00077') /*This mobile number is not a registered  %%companyName%% user. Only registered %%companyName%% users are allowed to be added as an employee.*/;
            $return_message = str_replace("%%companyName%%", $companyName, $translations_message);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $return_message);
        }

        $db->where("business_id", $business_id);
        $db->where("mobile", $employee_mobile);
        $db->where("status", 1);
        $result = $db->getOne("xun_employee");

        if ($result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00078') /*This mobile number has already been added as an employee.*/);
        }

        $old_id = $this->get_employee_old_id($business_id, $employee_mobile);
        $fields = array("business_id", "mobile", "name", "status", "employment_status", "created_at", "updated_at", "old_id", "role");
        $values = array($business_id, $employee_mobile, $employee_name, "1", "pending", $now, $now, $old_id, $employee_role);

        $insertData = array_combine($fields, $values);

        $new_employee_id = $db->insert("xun_employee", $insertData);

        // add to live chat tag and business chat tag
        foreach ($livechat_tag as $tag) {
            $db->where("tag", $tag);
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $xun_business_tag = $db->getOne("xun_business_tag");

            if ($xun_business_tag) {
                $fields = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
                $values = array($old_id, $employee_mobile, $business_id, $tag, "1", $now, $now);
                $insertData = array_combine($fields, $values);

                $db->insert("xun_business_tag_employee", $insertData);
            }
        }

        foreach ($business_employee_tag as $tag) {
            $db->where("tag", $tag);
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $xun_business_employee_tag = $db->getOne("xun_business_employee_tag");

            if ($xun_business_employee_tag) {
                $fields = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
                $values = array($old_id, $employee_mobile, $business_id, $tag, "1", $now, $now);
                $insertData = array_combine($fields, $values);

                $db->insert("xun_business_employee_tag_employee", $insertData);
            }
        }

        //call api to send new employee message

        $newParams["business_id"] = $business_id;
        $newParams["employee_mobile"] = $employee_mobile;
        $newParams["employee_id"] = $old_id;
        $newParams["employee_role"] = $employee_role;

        $erlangReturn = $post->curl_post($url_string, $newParams);

        // if ($erlangReturn["code"] == 0) {
        //     return $erlangReturn;
        // }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00030') /*New member successfully added.*/, "result" => $erlangReturn);
    }

	public function app_business_employee_detail($params) {

        $db = $this->db;
        $general = $this->general;

        $business_id = $params["business_id"];
        $employee_id = $params["employee_id"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($employee_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00011') /*Employee ID cannot be empty*/);
        }

        $db->where("business_id", $business_id);
        $db->where("old_id", $employee_id);
		$db->where("status", 1);
        $result = $db->getOne("xun_employee");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00080') /*This record does not exist.*/);
        }

        $returnData = $this->compose_xun_employee_v1($result);
        $returnData["employee_created_date"] = $general->formatDateTimeToIsoFormat($returnData["employee_created_date"]);
        $returnData["employee_modified_date"] = $general->formatDateTimeToIsoFormat($returnData["employee_modified_date"]);

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->where("employee_id", $employee_id);
        $copyDb = $db->copy();
        $business_tag = $db->getValue("xun_business_tag_employee", "DISTINCT tag", null);
        $business_employee_tag = $copyDb->getValue("xun_business_employee_tag_employee", "DISTINCT tag", null);

        $db->where("old_id", $employee_id);
        $db->where("status", 1);
        $result_key = $db->getOne("xun_public_key");
        $encrypted_key = $result_key["key"] ? $result_key["key"] : "";

        $returnData["live_chat_tag"] = $business_tag ? $business_tag : [];
        $returnData["business_chat_tag"] = $business_employee_tag ? $business_employee_tag : [];
		$returnData["encrypted_wallet_key"] = $encrypted_key;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00031') /*Team member details.*/, "result" => $returnData);

    }

    public function business_employee_get($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];
        $business_email = $params["business_email"];
        $employee_id = $params["employee_id"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($employee_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00011') /*Employee ID cannot be empty*/);
        }

        $db->where("business_id", $business_id);
        $db->where("old_id", $employee_id);
        $result = $db->getOne("xun_employee");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00080') /*This record does not exist.*/);
        }

        $returnData = $this->compose_xun_employee($result);
        // get livechat tag and business chat tag

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->where("employee_id", $employee_id);
        $copyDb = $db->copy();
        $business_tag = $db->getValue("xun_business_tag_employee", "tag", null);
        $business_employee_tag = $copyDb->getValue("xun_business_employee_tag_employee", "tag", null);

        $returnData["live_chat_tag"] = $business_tag ? $business_tag : [];
        $returnData["business_chat_tag"] = $business_employee_tag ? $business_employee_tag : [];

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00031') /*Team member details.*/, "result" => $returnData);
    }

    public function app_business_team_member_list($params) {

        $db = $this->db;
		$general = $this->general;

        $business_id = $params["business_id"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->where("role", "employee");
        $result = $db->get("xun_employee");

        $returnData = array();
        foreach ($result as $data) {
			$data["created_at"] = $general->formatDateTimeToIsoFormat($data["created_at"]);
            $data["updated_at"] = $general->formatDateTimeToIsoFormat($data["updated_at"]);
            $returnData[] = $this->compose_xun_employee_v1($data);
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00032') /*Team member listing.*/, "result" => $returnData);

    }

    public function business_employee_list($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];
        $business_email = $params["business_email"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->where("role", "employee");
        $result = $db->get("xun_employee");

        if (empty($result)) {
            $returnData = [];
        }

        foreach ($result as $data) {
            $returnData[] = $this->compose_xun_employee($data);
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00032') /*Team member listing.*/, "result" => $returnData);
    }

    public function business_employee_confirmed_list($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];
        $business_email = $params["business_email"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        $db->where("business_id", $business_id);
        $db->where("employment_status", "confirmed");
        $db->where("status", 1);
        $db->where("role", 'employee');
        $result = $db->get("xun_employee");

        foreach ($result as $data) {
            $returnData[] = $this->compose_xun_employee($data);
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00033') /*Confirmed team members.*/, "result" => $returnData);
    }


    public function business_employee_edit($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];
        $business_email = $params["business_email"];
        $employee_name = $params["employee_name"];
        $employee_id = $params["employee_id"];
        $livechat_tag = $params["live_chat_tag"];
        $business_employee_tag = $params["business_chat_tag"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($employee_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00075') /*Employee Name cannot be empty*/);
        }

        if ($employee_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00011') /*Employee ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00082') /*Business not found.*/);
        }

        if ($result["email"] != $business_email) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00038') /*You have insufficient privilege to perform this operation.*/);
        }

        if (!$livechat_tag) {
            $livechat_tag = [];
        } else if (!is_array($livechat_tag)) {
            $livechat_tag = [$livechat_tag];
        } else {
            array_values(array_filter(array_unique($livechat_tag)));
        }

        if (!$business_employee_tag) {
            $business_employee_tag = [];
        } else if (!is_array($business_employee_tag)) {
            $business_employee_tag = [$business_employee_tag];
        } else {
            array_values(array_filter(array_unique($business_employee_tag)));
        }

        $db->where("old_id", $employee_id);
        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $xun_employee = $db->getOne("xun_employee");

        if (!$xun_employee) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00083') /*This team member does not exist.*/);
        }

        $username = $xun_employee["mobile"];

        $updateData["name"] = $employee_name;

        $db->where("id", $xun_employee["id"]);
        $db->update("xun_employee", $updateData);

        $business_tag_res = $this->update_employee_tag($business_id, $username, $employee_id, "xun_business_tag", "xun_business_tag_employee", $livechat_tag);

        $business_employee_tag_res = $this->update_employee_tag($business_id, $username, $employee_id, "xun_business_employee_tag", "xun_business_employee_tag_employee", $business_employee_tag);

        // check employment status
        $employment_status = $xun_employee["employment_status"];
        if ($employment_status == "confirmed") {
            // send xmpp event
            $this->update_xmpp_business_tag_employee($business_id, $xun_employee, $business_tag_res);
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00034') /*Business employee details updated.*/);
    }

    public function update_employee_tag($business_id, $username, $employee_id, $tag_table_name, $table_name, $tag_list)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");
        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->where("username", $username);
        $initial_tag = $db->getValue($table_name, "tag", null);

        $initial_tag = $initial_tag ? $initial_tag : array();

        $new_tag = array_diff($tag_list, $initial_tag);
        $removed_tag = array_diff($initial_tag, $tag_list);

        $final_new_tag = [];
        foreach ($new_tag as $tag) {
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $db->where("tag", $tag);
            $tag_record = $db->getOne($tag_table_name);

            if (!$tag_record) {
                continue;
            }

            $fields = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
            $values = array($employee_id, $username, $business_id, $tag, "1", $now, $now);
            $insertData = array_combine($fields, $values);

            $db->insert($table_name, $insertData);
            $final_new_tag[] = $tag;
        }

        $updateData = [];
        $updateData["updated_at"] = $now;
        $updateData["status"] = 0;
        foreach ($removed_tag as $tag) {
            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->where("status", 1);
            $db->where("username", $username);
            $db->update($table_name, $updateData);
        }

        return array("new_tag" => $final_new_tag, "removed_tag" => $removed_tag);
    }

    /*
    for accept as employee:
    get all business tag employee
    send to all tag employees as new user
     */
    public function update_xmpp_business_tag_employee($business_id, $employee, $tag_arr)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        $new_tag = $tag_arr["new_tag"];
        $removed_tag = $tag_arr["removed_tag"];

        foreach ($new_tag as $tag) {
            $this->send_xmpp_event_employee_update($business_id, $employee, $tag, "new");
        }

        foreach ($removed_tag as $tag) {
            $this->send_xmpp_event_employee_update($business_id, $employee, $tag, "removed");
        }
        return 1;
    }

    public function send_xmpp_event_employee_update($business_id, $employee, $tag, $type)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        global $xunXmpp;
        $erlang_server = $this->get_erlang_server();

        $employee_username = $employee["mobile"];
        $employee_id = $employee["old_id"];
        $employee_role = $employee["role"];

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", "1");
        $xun_business_tag_employee = $db->get("xun_business_tag_employee");

        // subscribers
        $subscribers_jid = array();
        foreach ($xun_business_tag_employee as $tag_employee) {
            $tag_employee_username = $tag_employee["username"];

            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $db->where("employment_status", "confirmed");
            $db->where("mobile", $tag_employee_username);
            $xun_employee = $db->getOne("xun_employee");

            if (!$xun_employee) {
                continue;
            }

            $subscribers_jid[] = $xunXmpp->get_user_jid($tag_employee_username);
        }

        $new_employee_list = array();
        $removed_employee_list = array();

        $employee_obj["employee_mobile"] = $employee_username;
        $employee_obj["employee_server"] = $erlang_server;
        $employee_obj["employee_role"] = $employee_role;

        if ($type == "removed") {
            $subscribers_jid[] = $xunXmpp->get_user_jid($employee_username);
            $removed_employee_list[] = $employee_obj;
        } else {
            $new_employee_list[] = $employee_obj;
        }

        $erlangParams["business_id"] = $business_id;
        $erlangParams["tag"] = $tag;
        $erlangParams["new_employee_list"] = $new_employee_list;
        $erlangParams["removed_employee_list"] = $removed_employee_list;
        $erlangParams["subscribers_jid"] = $subscribers_jid;

        $subscribers_jid = $subscribers_jid ? $subscribers_jid : array();
        $new_employee_list = $new_employee_list ? $new_employee_list : array();
        $removed_employee_list = $removed_employee_list ? $removed_employee_list : array();

        $erlangReturn = $this->send_xmpp_business_tag_event($business_id, $tag, $new_employee_list, $removed_employee_list, $subscribers_jid);

        return $erlangReturn;

    }

    public function app_business_employee_delete($params)
    {
        $db = $this->db;
        global $xunXmpp;

        $business_id = $params["business_id"];
        $employee_id = $params["employee_id"];
        $now = date("Y-m-d H:i:s");

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($employee_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00011') /*Employee ID cannot be empty*/);
        }

        if (gettype($employee_id) == 'string') {
            $employee_id_arr = array($employee_id);
        } else {
            $employee_id_arr = $employee_id;
        }

        $erlangReturn = $this->delete_business_employee($business_id, $employee_id_arr);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00036') /*Business employee successfully deleted.*/, "return" => $erlangReturn);
    }

    public function business_employee_delete($params)
    {
        $db = $this->db;
        global $xunXmpp;

        $business_id = $params["business_id"];
        $business_email = $params["business_email"];
        $employee_id = $params["employee_id"];
        $now = date("Y-m-d H:i:s");

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($employee_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00011') /*Employee ID cannot be empty*/);
        }

        if (gettype($employee_id) == 'string') {
            $employee_id_arr = array($employee_id);
        } else {
            $employee_id_arr = $employee_id;
        }

        $erlangReturn = $this->delete_business_employee($business_id, $employee_id_arr);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00036') /*Business employee successfully deleted.*/, "return" => $erlangReturn);
    }

    public function business_employee_delete_all($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];
        $business_email = $params["business_email"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->where("role", "owner", "!=");

        $xun_employee = $db->get("xun_employee", null, "old_id");

        $employee_id_arr = [];

        foreach ($xun_employee as $employee) {
            $employee_id_arr[] = $employee['old_id'];
        }

        $erlangReturn = $this->delete_business_employee($business_id, $employee_id_arr);

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00036') /*Business employee successfully deleted.*/);
    }

    private function compose_xun_employee_v1($result)
    {
        $status = $result["employment_status"];

        if ($status == 'pending') {
            $status_cp = 0;
        } else if ($status == 'confirmed') {
			$status_cp = 1;
		} else {
            $status_cp = 2;
        }

        $returnData["employee_id"] = $result["old_id"];
        $returnData["business_id"] = $result["business_id"];
        $returnData["employee_mobile"] = $result["mobile"];
        $returnData["employee_name"] = $result["name"];
        $returnData["employee_status"] = $status_cp;
        $returnData["employee_role"] = $result["role"];
        $returnData["employee_created_date"] = $result["created_at"];
        $returnData["employee_modified_date"] = $result["updated_at"];
        $returnData["is_share_key"] = $result["is_share_key"];
        $returnData["share_mode"] = $result["share_mode"];

        return $returnData;
    }

    private function compose_xun_employee($result)
    {
        $status = $result["employment_status"];

        if ($status == 'pending') {
            $status_cp = 0;
        } else {
            $status_cp = 1;
        }

        $returnData["employee_id"] = $result["old_id"];
        $returnData["business_id"] = $result["business_id"];
        $returnData["employee_mobile"] = $result["mobile"];
        $returnData["employee_name"] = $result["name"];
        $returnData["employee_status"] = $status_cp;
        $returnData["employee_role"] = $result["role"];
        $returnData["employee_created_date"] = $result["created_at"];
        $returnData["employee_modified_date"] = $result["updated_at"];
		$returnData["is_share_key"] = $result["is_share_key"];
		$returnData["share_mode"] = $result["share_mode"];

        return $returnData;
    }

    public function generate_marketing_verification_code($reference_id, $business_name, $mobile, $verification_code = null, $expired_at = null)
    {
        $db = $this->db;
        $general = $this->general;

        global $xunXmpp;

        // generate verification code
        if (!$verification_code) {
            $verification_code = $general->generateRandomNumber(5);
        }
        $created_at = date("Y-m-d H:i:s");

        // get expiration time -> 5 minutes
        // verification code expires in 5 minutes
        if (!$expired_at) {
            $expired_at = date("Y-m-d H:i:s", strtotime('+5 minutes', strtotime($created_at)));
        }

        // insert into xun_business_register_marketing

        $fields = array("reference_id", "business_name", "phone_number", "verify_code", "expires_at", "request_at");
        $values = array($reference_id, $business_name, $mobile, $verification_code, $expired_at, $created_at);
        $arrayData = array_combine($fields, $values);
        $row_id = $db->insert("xun_business_register_marketing", $arrayData);

        // send verification code through xun message -> call erlang
        $message = "Business Name: " . $business_name . "\nVerify Code: " . $verification_code .
            "\nTime: " . $created_at;
        $erlang_params["tag"] = "Verify Code";
        $erlang_params["message"] = $message;
        $erlang_params["mobile_list"] = array($mobile);
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

        // return response
        return $xmpp_result;
    }
    //-----------------------------------------------------------------------------------------------------------------------------------------------
    ///API KRY
    //-----------------------------------------------------------------------------------------------------------------------------------------------
    public function generate_api_key($params, $source = "business")
    {
        global $xunPaymentGateway;
        $db = $this->db;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        $apikey_name = $params["apikey_name"];
        $apikey_expiry_date = $params["apikey_expiry_date"];

        $created_at = date("Y-m-d H:i:s");
        $apikey_is_enabled = "1";

        $date = str_replace('/', '-', $apikey_expiry_date);
        date('Y-m-d', strtotime($date));

        $expire_date = date('Y-m-d', strtotime($date));
        if($source != 'nuxpay'){
            if ($business_email == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
            }
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($apikey_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00084') /*Api key name cannot be empty.*/);
        }
        if ($apikey_expiry_date == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00085') /*Api key expiry date cannot be empty.*/);
        }

        //validate the business id got record in xun_business
        $db->where('user_id', $business_id);
        $check_business = $db->getOne('xun_business');
        if (empty($check_business)) {
            return array('code' => 0, 'message' => FAILED, 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/, 'developer_msg' => "");
        };

        //generate the apikey
        $flag = true;
        while ($flag) {

            $random_number = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $value = substr(str_shuffle($random_number), 0, 32);

            $db->where('apikey', $value);
            $result = $db->get('xun_business_api_key');

            if (!$result) {

                $flag = false;
                $api_key = $value;
            }
        }

        $fields = array("apikey", "business_id", "apikey_name", "apikey_expire_datetime", "is_enabled", "created_at");
        $values = array($api_key, $business_id, $apikey_name, $expire_date, $apikey_is_enabled, $created_at);
        $arrayData = array_combine($fields, $values);
        $db->insert("xun_business_api_key", $arrayData);

        $date_format = date("d/m/Y", strtotime($created_at));
        $date_format_expiry = date("d/m/Y", strtotime($expire_date));

        $api_list = array(
            "apikey_uuid" => $api_key,
            "business_uuid" => $business_id,
            "apikey_expire_date" => $date_format_expiry,
            "apikey_is_enabled" => $apikey_is_enabled, //chg
            "apikey_status" => "1",
            "apikey_created_date" => $date_format,
            "apikey_modified_date" => $date_format,
        );

        $return_message = $this->get_translation_message('B00037');
        if($source == 'nuxpay'){
            $db->where('id', $business_id);
            $db->where('register_site', 'nuxpay');
            $xun_user = $db->getOne('xun_user');

            $nickname = $xun_user["nickname"];
            $phone_number = $xun_user["username"];

            $tag = "Generate API Key";
            $message = "Username: ".$nickname. "\n";
            $message .= "Phone number: ".$phone_number. "\n";
            $message .= "Expiry Date: ".$date_format_expiry."\n";
            $message .= "Status: SUCCESS\n";
            $message .= "Message: ".$return_message."\n"; 
            $message .= "Time: ".date("Y-m-d H:i:s")."\n";

            $xunPaymentGateway->send_nuxpay_notification($tag, $message);
        }

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $return_message /*Business API key created.*/, "result" => $api_list);

    }

    public function api_key_listing($params)
    {
        $db = $this->db;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("business_id", $business_id);
        $result = $db->rawQuery("SELECT * FROM `xun_business_api_key` WHERE  business_id = $business_id AND status = 'active'");

        $xun_business_api_key = [];
        if ($result) {

            foreach ($result as $key) {

                $businessID = $key['business_id'];
                $apikey_uuid = $key['apikey'];
                $apikey_name = $key['apikey_name'];
                $apikey_expire_date = $key['apikey_expire_datetime'];
                $apikey_is_enabled = $key['is_enabled'];
                $apikey_status = $key['status'];
                $created_datetime = $key['created_at'];

                $api_list[] = array(
                    "apikey_uuid" => $apikey_uuid,
                    "business_uuid" => $businessID,
                    "apikey_name" => $apikey_name,
                    "apikey_expire_date" => $apikey_expire_date,
                    "apikey_is_enabled" => $apikey_is_enabled,
                    "apikey_status" => $apikey_status,
                    "apikey_created_date" => $created_datetime,
                );

            }
            $xun_business_api_key = $api_list;

        }

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00038') /*Business API key listing.*/, "xun_business_api_key" => $xun_business_api_key);
    }

    public function update_api_key($params)
    {
        $db = $this->db;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        $api_key = $params["api_key"];
        $apikey_name = $params["apikey_name"];
        $apikey_expiry_date = $params["apikey_expiry_date"];
        $apikey_status = $params["apikey_status"];
        $update_date = date("Y-m-d H:i:s");

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        // if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        // }
        if ($api_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00086') /*Api key cannot be empty.*/);
        }
        if ($apikey_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00084') /*Api key name cannot be empty.*/);
        }
        if ($apikey_expiry_date == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00085') /*Api key expiry date cannot be empty.*/);
        }

        //chech the business record
        $db->where('user_id', $business_id);
        $check_business = $db->getOne('xun_business');
        if (empty($check_business)) {
            return array('code' => 0, 'message' => FAILED, 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/, 'developer_msg' => "");
        }

        $db->where('apikey', $api_key);
        $check_api_key = $db->getOne('xun_business_api_key');
        if (empty($check_api_key)) {
            return array('code' => 0, 'message' => FAILED, 'message_d' => $this->get_translation_message('E00044') /*Invalid record.*/, 'developer_msg' => "");
        }

        $date = explode("/", $apikey_expiry_date);
        if (checkdate($date[1], $date[0], $date[2])) {
            $date = str_replace('/', '-', $apikey_expiry_date);
            $apikey_expiry_date = date('Y-m-d', strtotime($date));
        }

        //update name ,date ,is_enable , modified date
        $updateData = [];
        $updateData["apikey_name"] = $apikey_name;
        $updateData["apikey_expire_datetime"] = $apikey_expiry_date;
        $updateData["is_enabled"] = $apikey_status;
        $updateData["updated_at"] = $update_date;
        $db->where("apikey", $api_key);
        $db->update("xun_business_api_key", $updateData);

        // $update_api_key = $db->rawQuery(" UPDATE `xun_business_api_key` SET `apikey_name` = '$apikey_name' , `apikey_expire_datetime` = '$apikey_expiry_date' ,`is_enabled` = '$apikey_status' ,`updated_at` = '$update_date' WHERE `apikey` = '$api_key'");

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00039') /*Business API key details updated.*/, 'developer_msg' => "");

    }

    public function delete_multiple_record($params)
    {
        $db = $this->db;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        $api_key_list = $params["api_key"];
        $update_date = date("Y-m-d H:i:s");

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($api_key_list == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00086') /*Api key cannot be empty.*/);
        }

        $new_api_key_list = $api_key_list;
        if (!is_array($api_key_list)) {
            $new_api_key_list = [$api_key_list];
        }

        $now = date("Y-m-d H:i:s");

        foreach ($new_api_key_list as $api_key) {
            $db->where("apikey", $api_key);
            $db->where("business_id", $business_id);

            $api_key_record = $db->getOne("xun_business_api_key");

            if (!$api_key_record) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00044') /*Invalid record.*/);
            }

            $updateData = [];
            $updateData["status"] = "deleted";
            $updateData["updated_at"] = $now;
            $db->where("apikey", $api_key);
            $db->update("xun_business_api_key", $updateData);
        }

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00040') /*Business API key revoked.*/);
    }

    public function delete_all_record($params)
    {
        $db = $this->db;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        // if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        // }

        $updateData["status"] = 'deleted';
        $updateData["updated_at"] = '';

        $db->where("business_id", $business_id);
        $db->update("xun_business_api_key", $updateData);

        // $delete_all_api_key = $db->rawQuery("UPDATE `xun_business_api_key` SET `status` = 'deleted' WHERE `business_id` = '$business_id'");

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00041') /*All Business API Keys has been deleted.*/);

    }
//-----------------------------------------------------------------------------------------------------------------------------------------------
    ///Live chat setting
    //-----------------------------------------------------------------------------------------------------------------------------------------------

    public function add_edit_setting($params)
    {

        $db = $this->db;

        $business_id = trim($params["business_id"]);
        $contactUsURL = trim($params["contactUsURL"]);
        $websiteUrl = trim($params["websiteUrl"]);
        $liveChatNoAgentMsg = trim($params["liveChatNoAgentMsg"]);
        $liveChatAfterWorkingHrsMsg = trim($params["liveChatAfterWorkingHrsMsg"]);
        $liveChatFirstMsg = trim($params["liveChatFirstMsg"]);
        $liveChatPromp = $params["liveChatPromp"];
        $liveChatInfo = $params["liveChatInfo"];

        $date = date("Y-m-d H:i:s");

        $final = $live_chat_info;

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($liveChatInfo == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00087') /*Live chat info cannot be empty.*/);
        }

        if ($contactUsURL) {
            if (!filter_var($contactUsURL, FILTER_VALIDATE_URL)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00088') /*Please enter a valid Contact Us URL.*/, "developer_msg" => "contactUsURL is not a valid URL");
            }
        }

        if ($websiteUrl) {
            if (!filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00089') /*Please enter a valid Website URL.*/, "developer_msg" => "websiteUrl is not a valid URL");
            }
        }

        $db->where("business_id", $business_id);
        $result = $db->getOne("xun_business_livechat_setting");

        if (!$result) {

            $fields = array("business_id", "contact_us_url", "website_url", "live_chat_no_agent_msg", "live_chat_after_working_hrs_msg", "live_chat_first_msg", "live_chat_prompt", "created_at");
            $values = array($business_id, $contactUsURL, $websiteUrl, $liveChatNoAgentMsg, $liveChatAfterWorkingHrsMsg, $liveChatFirstMsg, $liveChatPromp, $date);
            $arrayData = array_combine($fields, $values);
            $insert = $db->insert("xun_business_livechat_setting", $arrayData);

            $key["name"] = $liveChatInfo[0];
            $key["email"] = $liveChatInfo[1];
            $final_key = $key;

            foreach ($final_key as $key => $x) {

                $name = $key;
                $value = $x;

                $fields_livechat_info = array("business_id", "livechat_setting_id", "live_chat_info", "type", "created_at", "updated_at");
                $values_livechat_info = array($business_id, $insert, $value, $name, $date, $date);
                $arrayData = array_combine($fields_livechat_info, $values_livechat_info);
                $db->insert("xun_business_livechat_setting_livechat_info", $arrayData);

            }
        } else {
            $livechat_id = $result["id"];
            $updateData["contact_us_url"] = $contactUsURL;
            $updateData["website_url"] = $websiteUrl;
            $updateData["live_chat_no_agent_msg"] = $liveChatNoAgentMsg;
            $updateData["live_chat_after_working_hrs_msg"] = $liveChatAfterWorkingHrsMsg;
            $updateData["live_chat_first_msg"] = $liveChatFirstMsg;
            $updateData["live_chat_prompt"] = $liveChatPromp;
            $updateData["updated_at"] = $date;

            $db->where("business_id", $business_id);
            $db->update("xun_business_livechat_setting", $updateData);

            $key["name"] = $liveChatInfo[0];
            $key["email"] = $liveChatInfo[1];
            $final_key = $key;

            foreach ($final_key as $key => $x) {

                $name = $key;
                $value = $x;
                $updateData = [];
                $updateData["business_id"] = $business_id;
                $updateData["livechat_setting_id"] = $livechat_id;
                $updateData["live_chat_info"] = $value;
                $updateData["created_at"] = $date;
                $updateData["updated_at"] = $date;
                $db->where("business_id", $business_id);
                $db->where("type", $name);
                $db->update("xun_business_livechat_setting_livechat_info", $updateData);
            }
        }
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00042') /*Live Chat settings updated.*/);

    }

    public function get_livechat_setting($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_id = $params["business_id"];
        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $check_record = $db->rawQuery("SELECT * FROM `xun_business_livechat_setting` WHERE  business_id = '$business_id'");

        $livechat_id = $check_record[0][id];

        if (!empty($check_record)) {

            $result_livechat_info = $db->rawQuery("SELECT `live_chat_info` FROM `xun_business_livechat_setting_livechat_info` WHERE  `business_id` = $business_id AND  `livechat_setting_id` = $livechat_id ");

            foreach ($result_livechat_info as $data) {

                $liveChatInfo = $data["live_chat_info"];

                $result[] = $liveChatInfo;

                $array_setting_info = $result;
            }
            foreach ($check_record as $data) {
                $business_id = $data["business_id"];
                $contactUsURL = $data["contact_us_url"];
                $websiteUrl = $data['website_url'];
                $contaliveChatNoAgentMsgctUsURL = $data["live_chat_no_agent_msg"];
                $liveChatAfterWorkingHrsMsg = $data['live_chat_after_working_hrs_msg'];
                $liveChatFirstMsg = $data["live_chat_first_msg"];
                $liveChatPromp = $data['live_chat_prompt'];

                $arrayData = array("business_id" => $business_id,
                    "contactUsURL" => $contactUsURL,
                    "websiteUrl" => $websiteUrl,
                    "liveChatNoAgentMsg" => $liveChatNoAgentMsg,
                    "liveChatAfterWorkingHrsMsg" => $liveChatAfterWorkingHrsMsg,
                    "liveChatFirstMsg" => $liveChatFirstMsg,
                    "liveChatPromp" => $liveChatPromp,
                    "liveChatInfo" => $array_setting_info,
                );
                $array_setting = $arrayData;
            }

            return array('code' => 1, 'message' => Success, 'message_d' => $this->get_translation_message('B00043') /*Setting details.*/, 'result' => $array_setting);
        } else {

            $arrayData = array("business_id" => $business_id,
                "contactUsURL" => "",
                "websiteUrl" => "",
                "liveChatNoAgentMsg" => "",
                "liveChatAfterWorkingHrsMsg" => "",
                "liveChatFirstMsg" => "",
                "liveChatPromp" => "",
                "liveChatInfo" => array("Name", "Email"),
            );
            $array_setting = $arrayData;

            return array('code' => 1, 'message' => Success, 'message_d' => $this->get_translation_message('B00043') /*Setting details.*/, 'result' => $array_setting);
        }
    }

    public function livechat_get_script($params)
    {
        $db = $this->db;
        global $setting;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }
        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $domainName = $setting->systemSetting['officialURL'];

        $script = "<script src=\"https://" . $domainName . "/js/liveChatWidget.js?id=";
        $script_2 = "\" id=\"liveChatWidgetScript\"></script>";

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00044') /*Live chat script.*/, "result" => $script . $business_id . $script_2);

    }

    public function generate_mobile_verification_code($business_email, $business_name, $mobile, $verification_code = null, $expired_at = null)
    {
        $db = $this->db;
        $general = $this->general;

        global $xunXmpp;

        // generate verification code
        if (!$verification_code) {
            $verification_code = $general->generateRandomNumber(5);
        }
        $created_at = date("Y-m-d H:i:s");

        // get expiration time -> 5 minutes
        // verification code expires in 5 minutes
        if (!$expired_at) {
            $expired_at = date("Y-m-d H:i:s", strtotime('+5 minutes', strtotime($created_at)));
        }

        // insert into xun_business_mobile_verification
        $fields = array("business_email", "mobile_number", "verification_code", "expires_at", "request_at");
        $values = array($business_email, $mobile, $verification_code, $expired_at, $created_at);
        $arrayData = array_combine($fields, $values);
        $row_id = $db->insert("xun_business_mobile_verification", $arrayData);

        // send verification code through xun message -> call erlang
        $message = "Business Name: " . $business_name . "\nVerify Code: " . $verification_code .
            "\nTime: " . $created_at;
        $erlang_params["tag"] = "Verify Code";
        $erlang_params["message"] = $message;
        $erlang_params["mobile_list"] = array($mobile);
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

        // return response
        return $xmpp_result;
        // return true;
    }

    public function xun_group_contact_edit($params)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        $contact_group_member_id = $params["contact_group_member_id"];
        $contact_name = $params["contact_name"];
        $contact_mobile = $params["contact_mobile"];

        $date = date("Y-m-d H:i:s");

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($contact_group_member_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00090') /*Group member id cannot be empty.*/);
        }

        if ($contact_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00092') /*Contact name cannot be empty.*/);
        }

        if ($contact_mobile == '') {
        }

        $check_group_member = $db->rawQuery("SELECT *  FROM `xun_business_contact_group_member` WHERE business_id = $business_id AND id = $contact_group_member_id AND status = '1'");

        if (empty($check_group_member)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00093') /*Record not found.*/);
        }

        $updateData["contact_mobile"] = $contact_mobile;
        $updateData["contact_name"] = $contact_name;
        $updateData["modified_date"] = $date;

        $db->where("business_id", $business_id);
        $db->where("contact_group_id", $contact_group_member_id);
        $db->where("id", $contact_group_member_id);
        $db->update("xun_business_contact_group_member", $updateData);

        // $db->rawQuery("UPDATE `xun_business_contact_group_member` SET `contact_mobile` = $contact_mobile,`contact_name` = $contact_name,`modified_date` = '$date'WHERE`business_id` = $business_id AND `contact_group_id` = $contact_group_member_id AND id = $contact_group_member_id ");

        $result[] = array(
            "business" => $business_id,
            "contact_group_member_id" => $contact_group_member_id,
            "contact_name" => $contact_name,
            "contact_mobile" => $contact_mobile,

        );

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00045') /*Mobile added to business.*/, 'result' => $result);

    }

    public function xun_group_contact_delete($params)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        $group_id = $params["group_id"];
        $contact_group_member_id = $params["contact_group_member_id"];

        $date = date("Y-m-d H:i:s");

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($group_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty.*/);
        }

        if ($contact_group_member_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00095') /*Contact group member id cannot be empty.*/);
        }

        $check_group_id = $db->rawQuery("SELECT *  FROM `xun_business_contact_group` WHERE business_id = $business_id AND id = $group_id AND status = '1' ");

        if (empty($check_group_id)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00093') /*Record not found.*/);
        }

        foreach ($contact_group_member_id as $key) {
            $member = $key;

            $updateData["status"] = 0;
            $updateData["modified_date"] = $date;

            $db->where("business_id", $business_id);
            $db->where("contact_group_id", $group_id);
            $db->where("id", $member);

            $db->update("xun_business_contact_group_member", $updateData);

            // $db->rawQuery("UPDATE `xun_business_contact_group_member` SET `status` = '0',`modified_date` = '$date'WHERE`business_id` = $business_id AND `contact_group_id` = $group_id AND id = $member ");

        }

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00055') /*Contact group(s) successfully deleted.*/);

    }

    public function xun_group_contact_delete_all($params)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        $group_id = $params["group_id"];

        $date = date("Y-m-d H:i:s");

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($group_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty.*/);
        }

        $check_group_id = $db->rawQuery("SELECT *  FROM `xun_business_contact_group` WHERE business_id = $business_id AND id = $group_id AND status = '1' ");

        if (empty($check_group_id)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00093') /*Record not found.*/);
        }

        $updateData["status"] = 0;
        $updateData["modified_date"] = $date;
        $db->where("business_id", $business_id);
        $db->where("contact_group_id", $group_id);
        $db->update("xun_business_contact_group_member", $updateData);

        // $db->rawQuery("UPDATE `xun_business_contact_group_member` SET `status` = '0',`modified_date` = '$date'WHERE`business_id` = $business_id AND `contact_group_id` = $group_id");

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/);

    }

    public function sms_add($params)
    {

        $db = $this->db;
        // $setting = $this->setting;
        // $post    = $this->post;

        $business_id = $params["business_id"];
        $provider_id = $params["provider_id"];
        $integration_name = $params["integration_name"];
        $key_1 = $params["key_input_1"];
        $key_2 = $params["key_input_2"];
        $key_3 = $params["key_input_3"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($provider_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00096') /*Provider Id cannot be empty.*/);
        }
        if ($integration_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00097') /*Integration name cannot be empty.*/);
        }

        switch ($provider_id) {
            //SMS123.NET
            case "SMS001":

                $key["key"] = $key_1;
                $key["api_key"] = $key_2;
                $final_key[] = $key;

                break;
            //SMS 360
            case "SMS002":

                $key["email"] = $key_1;
                $key["key"] = $key_2;
                $final_key[] = $key;

                break;
            //nexmo
            case "SMS003":

                $key["api_key"] = $key_1;
                $key["secret_key"] = $key_2;
                $final_key[] = $key;

                break;

            //twilio
            case "SMS004":

                $key["account_sid"] = $key_1;
                $key["auth_token"] = $key_2;
                $key["number_form"] = $key_3;
                $final_key[] = $key;

                break;

            //ifobip
            case "SMS005":

                $key["basic_url"] = $key_1;
                $key["authorization"] = $key_2;
                $key["authorization_type"] = $key_3;
                $final_key[] = $key;

                break;

            default:

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00098') /*Input incorrect.*/, 'data' => '');
                break;
        }

        $sms_provider_id = $provider_id;
        $status = '1';
        $key_input = $final_key;
        $today = date("Y-m-d H:i:s");

        $db->where('sms_provider_id', $sms_provider_id); //where sms_provider_id = SMS001
        $id = $db->getOne('sms_provider', id); //Select ID From SMS provider table
        $check_id = $id[id]; //take the sms_provider_id form sms_provider_table

        $fields = array("business_id", "sms_provider_id", "status", "name", "created_at", "updated_at");
        $values = array($business_id, $check_id, $status, $integration_name, $today, $today);

        //check duplicate
        $db->where('business_id', $business_id);
        $db->where('sms_provider_id', $check_id);
        $db->where('name', $integration_name);
        $db->where('status', '1');

        $result_duplicate = $db->getOne('business_sms_provider', name);

        if ($result_duplicate) {
            return array('code' => 0, 'message' => Failed, 'date' => $today, 'message_d' => $this->get_translation_message('E00099') /*This integration name already have already been added.*/);
        } else {

            $arrayData = array_combine($fields, $values);
            $sms_id = $db->insert("business_sms_provider", $arrayData);

            //check the id and insert into record

            foreach ($key_input[0] as $x => $x_value) {
                $name = $x;
                $value = $x_value;

                $fields_business_sms_provider_detail_id = array("business_sms_provider_id", "name", "value", "created_at", "updated_at");
                $values_business_sms_provider_detail_id = array($sms_id, $name, $value, $today, $today);
                $arrayData = array_combine($fields_business_sms_provider_detail_id, $values_business_sms_provider_detail_id);
                $business_sms_provider_detail_id = $db->insert("business_sms_provider_details", $arrayData);

            }

            return array('code' => 1, 'message' => SUCCESS, 'date' => $today, 'message_d' => $this->get_translation_message('B00048') /*SMS Integration successfully added*/);
        }
    }

    public function sms_edit($params)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_id = $params["business_id"];
        $provider_id = $params["business_sms_provider_id"];
        $sms_provider_id = $params["sms_provider_key"];
        $integration_name = $params["integration_name"];
        $key_1 = $params["key_input_1"];
        $key_2 = $params["key_input_2"];
        $key_3 = $params["key_input_3"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($provider_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00096') /*Provider Id cannot be empty.*/);
        }
        if ($sms_provider_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00102') /*SMS provider ID cannot be empty.*/);
        }
        if ($integration_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00097') /*Intergration name cannot be empty.*/);
        }

        switch ($sms_provider_id) {
            //SMS123.NET
            case "SMS001":

                $key["key"] = $key_1;
                $key["api_key"] = $key_2;
                $final_key[] = $key;

                break;
            //SMS 360
            case "SMS002":

                $key["email"] = $key_1;
                $key["key"] = $key_2;
                $final_key[] = $key;

                break;
            //nexmo
            case "SMS003":

                $key["api_key"] = $key_1;
                $key["secret_key"] = $key_2;
                $final_key[] = $key;

                break;

            //twilio
            case "SMS004":

                $key["account_sid"] = $key_1;
                $key["auth_token"] = $key_2;
                $key["number_form"] = $key_3;
                $final_key[] = $key;

                break;

            //ifobip
            case "SMS005":

                $key["basic_url"] = $key_1;
                $key["authorization"] = $key_2;
                $key["authorization_type"] = $key_3;
                $final_key[] = $key;

                break;

            default:

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00098') /*Input incorrect.*/, 'data' => '');
                break;
        }

        // $business_id      = $data["business_id"];
        // $sms_provider_id  = $data["sms_provider_id"];
        // $provider_id      = $data["provider_id"];

        $key_input = $final_key;
        // $today            = date("Y-m-d H:i:s");

        $db->where('sms_provider_id', $sms_provider_id); //where sms_provider_id = SMS001
        $id = $db->getOne('sms_provider', id); //Select ID From SMS provider table

        $check_id = $id[id]; //take the sms_provider_id form sms_provider_table
        $now = date("Y-m-d H:i:s");
        //check the record got inside the SQL
        $db->where('id', $provider_id);
        $record = $db->getOne('business_sms_provider');

        if (!empty($record)) {
            $db->where('business_id', $business_id);

            // $db->where('sms_provider_id', $check_id);
            $db->where('name', $integration_name);
            $result_duplicate = $db->getOne('business_sms_provider', name);

            if ($result_duplicate) {

                $db->where('business_id', $business_id);
                $db->where('sms_provider_id', $check_id);
                $db->where('name', $integration_name);
                $db->where('status', '0');
                $result_duplicate_name = $db->getOne('business_sms_provider', id);

                $id_compare = $result_duplicate_name[id];

                if ($id_compare != $provider_id) {

                    foreach ($key_input[0] as $x => $x_value) {
                        $name = $x;
                        $value = $x_value;
                        $updateData = [];
                        $updateData["name"] = $integration_name;
                        $updateData["updated_at"] = $now;

                        $db->where("business_id", $business_id);
                        $db->where("id", $provider_id);
                        $db->update("business_sms_provider", $updateData);

                        // $result_update_sms_provider         = $db->rawQuery(" UPDATE `business_sms_provider` SET `name` = '$integration_name' WHERE `business_id` = $business_id and `id` = $provider_id   ");

                        $updateData = [];
                        $updateData["value"] = $value;
                        $updateData["updated_at"] = $now;

                        $db->where("business_sms_provider_id", $provider_id);
                        $db->where("name", $name);
                        $db->update("business_sms_provider_details", $updateData);

                        // $result_update_sms_provider_detials = $db->rawQuery("  UPDATE `business_sms_provider_details` SET `value` = '$value' WHERE `business_sms_provider_id` = $provider_id and `name` = '$name' ");
                    }
                    return array('status' => Success, 'code' => 1, 'message' => success, 'message_d' => $this->get_translation_message('B00049') /*SMS Integration details updated.*/);

                } else {
                    return array('status' => Failed, 'code' => 0, 'message' => failed, 'message_d' => $this->get_translation_message('E00099') /*This integration name already have already been added.*/);

                }
            } else {
                foreach ($key_input[0] as $x => $x_value) {
                    $name = $x;
                    $value = $x_value;
                    $updateData = [];
                    $updateData["name"] = $integration_name;
                    $updateData["updated_at"] = $now;

                    $db->where("business_id", $business_id);
                    $db->where("id", $provider_id);
                    $db->update("business_sms_provider", $updateData);

                    // $result_update_sms_provider         = $db->rawQuery(" UPDATE `business_sms_provider` SET `name` = '$integration_name' WHERE `business_id` = $business_id and `id` = $provider_id   ");
                    $updateData = [];
                    $updateData["value"] = $value;
                    $updateData["updated_at"] = $now;

                    $db->where("business_sms_provider_id", $provider_id);
                    $db->where("name", $name);
                    $db->update("business_sms_provider_details", $updateData);

                    // $result_update_sms_provider_detials = $db->rawQuery("  UPDATE `business_sms_provider_details` SET `value` = '$value' WHERE `business_sms_provider_id` = $provider_id and `name` = '$name' ");
                }
                return array('status' => Success, 'code' => 1, 'message' => success, 'message_d' => $this->get_translation_message('B00049') /*SMS Integration details updated.*/);
            }
        } else {
            return array('status' => Failed, 'code' => 0, 'message' => failed, 'message_d' => $this->get_translation_message('E00031') /*You do not have the right to modify properties of this business.*/);

        }
    }

    public function sms_list($params)
    {

        $db = $this->db;
        // $setting = $this->setting;
        // $post    = $this->post;

        $business_id = $params["business_id"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $check_record = $db->rawQuery("SELECT * FROM `business_sms_provider` WHERE  business_id = $business_id and status = 1");

        if (!empty($check_record)) {

            $result_sms_provider = $db->rawQuery("SELECT `id`, `sms_provider_id`, `name` FROM `sms_provider` WHERE status = '1' ");

            $result_business_sms_provider = $db->rawQuery("SELECT `id`, `name` ,`sms_provider_id`,`created_at` FROM `business_sms_provider` WHERE  business_id = $business_id AND status = '1' ");

            foreach ($result_sms_provider as $data) {
                $sms_provider_company_name = $data["name"];
                $sms_provider_id = $data["sms_provider_id"];
                $sms_id = $data['id'];

                $arrayData[$sms_id] = array("sms_provider_name" => $sms_provider_company_name,
                    "sms_provider_id" => $sms_provider_id); //create a Array

            }

            foreach ($result_business_sms_provider as $data) {

                $busienss_sms_provider_name = $data["name"];
                $business_sms_provider_provider_id = $data["id"];
                $business_datatime = $data['created_at'];
                $business_sms_provider_id = $data['sms_provider_id']; //key to map
                //array use to map the array data
                $business_company_name = $arrayData[$business_sms_provider_id]["sms_provider_name"];
                $provider_id = $arrayData[$business_sms_provider_id]["sms_provider_id"];
                $data_array = array("name" => $busienss_sms_provider_name,
                    "integration_company" => $business_company_name,
                    "xun_provider_id" => $provider_id,
                    "xun_business_sms_provider_id" => $business_sms_provider_provider_id,
                    "created_datetime" => $business_datatime,
                );

                $temp_array[] = $data_array;
            }
            return array('code' => 1, 'message' => Success, 'message_d' => $this->get_translation_message('B00050') /*SMS list*/, 'result' => $temp_array);
        } else {

            return array('code' => 1, 'message' => Success, 'message_d' => $this->get_translation_message('B00050') /*SMS list*/, 'result' => []);
        }
    }

    //sms integration listing
    public function sms_get($params)
    {

        $db = $this->db;
        // $setting = $this->setting;
        // $post    = $this->post;

        $business_id = $params["business_id"];
        $SMS_provider_id = $params["business_sms_provider_id"];
        $status = '1';

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($SMS_provider_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00102') /*SMS provider ID cannot be empty.*/);
        }

        $check_result_got_inside = $db->rawQuery("SELECT * FROM `business_sms_provider`  WHERE business_id = $business_id and id = $SMS_provider_id and status = $status");

        if (!empty($check_result_got_inside)) {

            $result_business_sms_provider = $db->rawQuery("SELECT `name` , `id` FROM `business_sms_provider` WHERE id = $SMS_provider_id AND business_id = $business_id AND status = $status ");

            $result_business_sms_provider_details = $db->rawQuery("SELECT `business_sms_provider_id`, `name` ,`value` FROM `business_sms_provider_details` WHERE  business_sms_provider_id = $SMS_provider_id");

            foreach ($result_business_sms_provider as $data) {

                $business_sms_provider = $data["name"];
                $business_sms_id = $data["id"];

                $arrayData[$business_sms_id] = array("name" => $business_sms_provider);

            };

            foreach ($result_business_sms_provider_details as $data) {

                $business_sms_provider_details_name = $data["name"];
                $business_sms_provider_details_value = $data["value"];
                $business_sms_provider_id = $data['business_sms_provider_id']; //key to map
                $business_sms_provider_name_1 = $arrayData[$business_sms_provider_id]["name"];
                $data_array["name"] = $arrayData[$business_sms_provider_id]["name"];
                $data_array[$business_sms_provider_details_name] = $business_sms_provider_details_value;

            }
            return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00012') /*Success*/, 'result' => $data_array);
        } else {
            return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => []);
        }
    }

    public function sms_delete($params)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_id = $params["business_id"];
        $sms_id = $params["business_sms_provider_id"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($sms_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00102') /*SMS provider ID cannot be empty.*/);
        }

        foreach ($sms_id as $key => $value) {
            $db->where('business_id', $business_id);
            $db->where('id', $value);
            $result = $db->get('business_sms_provider', 1);

            $updateData["status"] = 0;
            $db->where("business_id", $business_id);
            $db->where("id", $value);
            $db->update("business_sms_provider", $updateData);

            // $result_business_sms_provider = $db->rawQuery("UPDATE  `business_sms_provider` SET `status` = '0'WHERE business_id = '$business_id' AND id = $value ");

        }
        return array('code' => 1, 'status' => Success, 'message_d' => $this->get_translation_message('B00052') /*Success delete integration*/);
        //}
    }

    public function sms_delete_all($params)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_id = $params["business_id"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where('business_id', $business_id);
        $result_business_sms_provider_id = $db->rawQuery("SELECT `id`  FROM `business_sms_provider` WHERE business_id = $business_id ");

        if (!empty($result_business_sms_provider_id)) {
            foreach ($result_business_sms_provider_id as $data) {

                $business_sms_id = $data["id"];

                $updateData["status"] = 0;
                $db->where("business_id", $business_id);
                $db->update("business_sms_provider", $updateData);

                // $result_business_sms_provider = $db->rawQuery("UPDATE  `business_sms_provider` SET `status` = '0' WHERE business_id = '$business_id' ");

            }

            return array('code' => 1, 'status' => Success, 'message_d' => $this->get_translation_message('B00053') /*Success delete all integration*/);

        } else {
            return array('code' => 0, 'status' => Failed, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/);
        }

    }

    public function sendSmsMessage($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_id = $params["business_id"];
        $message = $params["message"];
        $mobile_list = $params["mobile_list"];
        $business_sms_provider_id = $params["business_sms_provider_id"];
        $status = '1';

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($message == '') {
            $return_message = $this->get_translation_message('E00004'); //"Message cannot be empty"
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $return_message);
        }

        if ($mobile_list == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00135'), /*Mobile list cannot be empty*/
            );
        }

        if ($business_sms_provider_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00104') /*Business SMS provider ID cannot be empty.*/);
        }

        $business_sms_provider = $db->rawQuery("SELECT bsp.sms_provider_id, bspd.name, bspd.value FROM business_sms_provider as bsp JOIN business_sms_provider_details as bspd on bspd.business_sms_provider_id = bsp.id where bsp.id = " . $business_sms_provider_id . " AND bsp.status = " . $status . " and bsp.business_id = " . $business_id);

        if (!empty($business_sms_provider)) {

            // get sms_provider_id

            $sms_provider_id = $business_sms_provider[0][sms_provider_id];

            $sms_pro = $db->rawQuery("SELECT `sms_provider_id` FROM `sms_provider` WHERE `id` = $sms_provider_id");

            $select = $sms_pro[0][sms_provider_id];

            switch ($select) {
                case "SMS001":
                    //echo "sms123net";
                    $SMSGateway = "https://www.sms123.net/api/send.php";
                    $arr = array();
                    foreach ($business_sms_provider as $value) {
                        $arr[$value[name]] = $value[value];
                    }

                    $recipients = "";
                    foreach ($mobile_list as $key => $value) {
                        $mobile = str_replace('+', '', $value);
                        if ($key == 0) {
                            $recipients = $mobile;
                        } else {
                            $recipients = $recipients . ";" . $mobile;
                        }
                    }
                    $req_data = array(
                        "apiKey" => $arr[api_key],
                        "" => $arr[key],
                        "messageContent" => $message,
                        "recipients" => $recipients,
                    );

                    $result = $post->curl_get($SMSGateway, $req_data, 0);
                    break;

                case "SMS002":
                    // echo "smss360";

                    $SMSGateway = "https://www.smss360.com/api/sendsms.php";
                    $arr = array();
                    foreach ($business_sms_provider as $value) {
                        $arr[$value[name]] = $value[value];
                    }
                    $recipients = "";
                    foreach ($mobile_list as $key => $value) {
                        $mobile = str_replace('+', '', $value);
                        if ($key == 0) {
                            $recipients = $mobile;
                        } else {
                            $recipients = $recipients . ";" . $mobile;

                        }
                    }

                    $req_data = array(
                        "email" => $arr[email],
                        "key" => $arr[key],
                        "message" => $message,
                        "recipient" => $recipients,
                    );

                    $result = $post->curl_get($SMSGateway, $req_data, 0);
                    break;

                case "SMS003":
                    // nexmo
                    // echo "nexmo";
                    $SMSGateway = "https://rest.nexmo.com/sms/json";
                    $ContentType = "application/x-www-form-urlencoded";
                    $From = "Xun";
                    $arr = array();

                    foreach ($business_sms_provider as $value) {
                        $arr[$value[name]] = $value[value];
                    }

                    $recipients = "";
                    foreach ($mobile_list as $key => $value) {

                        $url = 'https://rest.nexmo.com/sms/json?' . http_build_query([

                            'api_key' => $arr[api_key],
                            'api_secret' => $arr[api_secret],
                            'to' => $value,
                            'from' => $From,
                            'text' => $message,
                            'type' => 'unicode',
                        ]);

                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $response = curl_exec($ch);
                    }

                    break;

                case "SMS004":
                    //echo "twilio";
                    // echo "twilio";
                    $arr = array();

                    foreach ($business_sms_provider as $value) {
                        $arr[$value[name]] = $value[value];
                    }

                    $ID = $arr[account_sid];
                    $token = $arr[auth_token];
                    $number_form = $arr[number_form];

                    $recipients = "";
                    foreach ($mobile_list as $key => $value) {

                        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $ID . '/Messages.json';

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                        curl_setopt($ch, CURLOPT_USERPWD, $ID . ':' . $token);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS,

                            'To=' . rawurlencode($value) . //
                            '&From=' . rawurlencode($number_form) .
                            '&Body=' . rawurlencode($message));
                        $resp = curl_exec($ch);
                        curl_close($ch);
                    }

                    break;

                case "SMS005":
                    // echo "infobip";
                    $curl = curl_init();

                    foreach ($business_sms_provider as $value) {
                        $arr[$value[name]] = $value[value];
                    }

                    $basic_url = $arr[basic_url];
                    $authorization = $arr[authorization];
                    $authorization_type = $arr[authorization_type];

                    if ($authorization_type == 'Basic') {
                        $URL = $basic_url . "sms/1/text/single";
                    } else {
                        $URL = $basic_url . "sms/2/text/single";
                    }

                    foreach ($mobile_list as $key => $value) {

                        if ($key == 0) {
                            $recipients = "\"" . $value . "\"";
                        } else {
                            $recipients = $recipients . "," . "\"" . $value . "\"";

                        }
                    }

                    $msg = "{ \"from\":\"InfoSMS\",
                                  \"to\": [$recipients],
                                  \"text\":\" " . $message . " \" }";
                    $author = $authorization_type . "\x20" . $authorization;

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $URL,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS => $msg,
                        CURLOPT_HTTPHEADER => array(
                            "accept: application/json",
                            "authorization:" . $author,
                            "content-type: application/json",
                        ),
                    ));

                    $response = curl_exec($curl);
                    $err = curl_error($curl);
                    curl_close($curl);

                    break;

                default:
                    // echo "no number";
                    break;
            }
            return array('status' => ok, 'code' => 1, 'message' => Success, 'message_d' => $this->get_translation_message('B00002') /*Messages sent.*/, 'business_id' => $business_id, 'mobile_list' => $mobile_list, 'business_sms_provider_id' => $business_sms_provider_id);
        } else {
            return array('status' => Failed, 'code' => 0, 'message' => Failed, 'message_d' => $this->get_translation_message('E00105') /*Your business don't have any provider, please add new provider first.*/);
        }

        // return array('status' => 'ok', 'code' => $code, 'statusMsg' => $message, 'data' => $returnData);
    }

    public function xun_group_contact_add($params)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        $group_id = $params["group_id"];
        $contact_name = $params["contact_name"];
        $contact_mobile = $params["contact_mobile"];

        $date = date("Y-m-d H:i:s");

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($group_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty.*/);
        }

        if ($contact_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00091') /*Contact name cannot be empty.*/);
        }

        if ($contact_mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00092') /*Contact mobile cannot be empty.*/);
        }

        $check_contact_group = $db->rawQuery("SELECT *  FROM `xun_business_contact_group` WHERE business_id = $business_id AND id = $group_id AND status = '1'");

        if (empty($check_contact_group)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00106') /*Invalid contact group record.*/);
        }

        $fields_add_contact = array("business_id", "contact_group_id", "contact_mobile", "contact_name", "status", "created_date", "modified_date");
        $values_add_contact = array($business_id, $group_id, $contact_mobile, $contact_name, "1", $date, $date);
        $arrayData = array_combine($fields_add_contact, $values_add_contact);
        $db->insert("xun_business_contact_group_member", $arrayData);

        $result = array("business_id" => $business_id,
            "group_id" => $group_id,
            "contact_name" => $contact_name,
            "contact_mobile" => $contact_mobile);

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00045') /*Mobile added to business.*/, 'result' => $result);

    }

    public function delete_all_contact_group($params)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        $group_id = $params["group_id"];

        $date = date("Y-m-d H:i:s");

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($group_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty.*/);
        }

        $check_group_id = $db->rawQuery("SELECT *  FROM `xun_business_contact_group` WHERE business_id = $business_id AND id = $group_id AND status = '1' ");

        if (empty($check_group_id)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00093') /*Record not found.*/);
        }

        $updateData["status"] = 0;
        $updateData["modified_date"] = $date;

        $db->where("business_id", $business_id);
        $db->where("id", $group_id);
        $db->update("xun_business_contact_group", $updateData);

        // $db->rawQuery("UPDATE `xun_business_contact_group` SET `status` = '0',`modified_date` = '$date'WHERE business_id = $business_id AND id = $group_id");

        $updateData["status"] = 0;
        $updateData["modified_date"] = $date;

        $db->where("business_id", $business_id);
        $db->where("contact_group_id", $group_id);
        $db->update("xun_business_contact_group_member", $updateData);

        // $db->rawQuery("UPDATE `xun_business_contact_group_member` SET `status` = '0',`modified_date` = '$date'WHERE`business_id` = $business_id AND `contact_group_id` = $group_id");

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00055') /*Contact group(s) successfully deleted.*/);

    }

    public function delete_contact_group($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        $group_id = $params["group_id"];

        $date = date("Y-m-d H:i:s");

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($group_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty.*/);
        }

        foreach ($group_id as $key) {

            $check_group_id = $db->rawQuery("SELECT *  FROM `xun_business_contact_group` WHERE business_id = $business_id AND id = $key AND status = '1' ");

            if (empty($check_group_id)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00093') /*Record not found.*/);
            }

            $updateData["status"] = 0;
            $updateData["modified_date"] = $date;
            $db->where("business_id", $business_id);
            $db->where("id", $key);
            $db->update("xun_business_contact_group", $updateData);

            // $db->rawQuery("UPDATE `xun_business_contact_group` SET `status` = '0',`modified_date` = '$date'WHERE`business_id` = $business_id AND id = $key ");

            $updateData["status"] = 0;
            $updateData["modified_date"] = $date;
            $db->where("business_id", $business_id);
            $db->where("contact_group_id", $key);
            $db->update("xun_business_contact_group_member", $updateData);

            // $db->rawQuery("UPDATE `xun_business_contact_group_member` SET `status` = '0',`modified_date` = '$date'WHERE`business_id` = $business_id AND `contact_group_id` = $key");

        }
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00055') /*Contact group(s) successfully deleted.*/);

    }

    public function get_contact_group_details($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        $group_id = $params["group_id"];

        $date = date("Y-m-d H:i:s");

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($group_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty.*/);
        }

        $check_group_id = $db->rawQuery("SELECT *  FROM `xun_business_contact_group` WHERE business_id = $business_id AND id = $group_id AND status = '1' ");

        if (empty($check_group_id)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00093') /*Record not found.*/);
        }
        $get_group_contact_member = $db->rawQuery("SELECT *  FROM `xun_business_contact_group_member` WHERE business_id = $business_id AND contact_group_id = $group_id AND status = '1' ");

        foreach ($get_group_contact_member as $key) {

            $member['contact_group_member_id'] = $key['id'];
            $member['contact_mobile'] = $key['contact_mobile'];
            $member['contact_name'] = $key['contact_name'];
            $member['created_date'] = $key['created_date'];

            $member_list[] = $member;

        }
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00045') /*Mobile added to business.*/, 'result' => $member_list);

    }

    public function business_contact_group_import($params, $files)
    {
        $db = $this->db;
        $general = $this->general;
        // business_id
        // group_name
        // contact_file

        $business_email = trim($params["business_email"]);
        $business_id = trim($params["business_id"]);
        $group_name = trim($params["group_name"]);

        // Param validations
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/, "developer_msg" => "business_email cannot be empty");
        };

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot be empty");
        };

        if ($group_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00107') /*Group name cannot be empty.*/, "developer_msg" => "group_name cannot be empty");
        };

        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        // check contact_file variable
        $file_param = "contact_file";
        if (isset($files[$file_param])) {
            $filename = $_FILES[$file_param]["tmp_name"];

            if ($_FILES[$file_param]["size"] > 0) {
                $now = date("Y-m-d H:i:s");
                $file = fopen($filename, "r");

                // check file header
                $file_header = fgetcsv($file);

                if (trim(strtolower($file_header[0])) !== "mobile") {
                    $error_message = $this->get_translation_message('E00108') /*Missing 'mobile' header.*/;
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                }

                if (trim(strtolower($file_header[1])) !== "name") {
                    $error_message = $this->get_translation_message('E00109') /*Missing 'name' header.*/;
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                }

                // create group
                $db->where("business_id", $business_id);
                $db->where("name", $group_name);
                $contact_group = $db->get("xun_business_contact_group");

                if ($contact_group) {
                    $error_message = $this->get_translation_message('E00110') /*A contact group with this name already exist. Please try another name.*/;
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                }

                $fields = array("business_id", "name", "status", "created_date", "modified_date");
                $values = array($business_id, $group_name, 1, $now, $now);
                $arrayData = array_combine($fields, $values);
                $contact_group_id = $db->insert("xun_business_contact_group", $arrayData);

                $contact_group_member_fields = array("business_id", "contact_group_id", "contact_mobile", "contact_name", "status", "created_date", "modified_date");

                while (($getData = fgetcsv($file, 10000, ",")) !== false) {
                    // check mobile format
                    $mobile = $getData[0];
                    $name = $getData[1];

                    if ($mobile != '' && $name != '') {
                        $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
                        if ($mobileNumberInfo["isValid"] == 0) {
                            continue;
                        }

                        $new_mobile = str_replace("-", "", $mobileNumberInfo["phone"]);

                        $contact_group_member_values = array($business_id, $contact_group_id, $new_mobile, $name, 1, $now, $now);
                        $contact_group_member_array_data = array_combine($contact_group_member_fields, $contact_group_member_values);
                        $result = $db->insert("xun_business_contact_group_member", $contact_group_member_array_data);
                    }
                }

                fclose($file);

            } else {
                $error_message = $this->get_translation_message('E00111') /*Invalid File:Please Upload CSV File*/;
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }
        } else {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00112') /*File cannot be empty.*/, "developer_msg" => "$file_param cannot be empty");
        }

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00057') /*CSV File has been successfully Imported.*/);

    }

    public function business_contact_group_edit_import($params, $files)
    {
        $db = $this->db;
        $general = $this->general;
        // business_id
        // group_id
        // contact_file
        $business_email = trim($params["business_email"]);
        $business_id = trim($params["business_id"]);
        $group_id = trim($params["group_id"]);

        // Param validations
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/, "developer_msg" => "business_email cannot be empty");
        };

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot be empty");
        };

        if ($group_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty.*/, "developer_msg" => "group_id cannot be empty");
        };

        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        // check contact_file variable
        $file_param = "contact_file";
        if (isset($files[$file_param])) {
            $filename = $_FILES[$file_param]["tmp_name"];

            if ($_FILES[$file_param]["size"] > 0) {
                $now = date("Y-m-d H:i:s");
                $file = fopen($filename, "r");

                // check file header
                $file_header = fgetcsv($file);

                if (trim(strtolower($file_header[0])) !== "mobile") {
                    $error_message = $this->get_translation_message('E00108') /*Missing 'mobile' header.*/;
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                }

                if (trim(strtolower($file_header[1])) !== "name") {
                    $error_message = $this->get_translation_message('E00109') /*Missing 'name' header.*/;
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                }

                // create group
                $db->where("id", $group_id);
                $contact_group = $db->getOne("xun_business_contact_group");

                if (!$contact_group) {
                    $error_message = $this->get_translation_message('E00113') /*Invalid group ID.*/;
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                }

                $contact_group_member_fields = array("business_id", "contact_group_id", "contact_mobile", "contact_name", "status", "created_date", "modified_date");

                while (($getData = fgetcsv($file, 10000, ",")) !== false) {
                    // check mobile format
                    $mobile = $getData[0];
                    $name = $getData[1];
                    if ($mobile != '' && $name != '') {
                        $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
                        if ($mobileNumberInfo["isValid"] == 0) {
                            continue;
                        }

                        $new_mobile = str_replace("-", "", $mobileNumberInfo["phone"]);
                        $db->where("contact_group_id", $contact_group_id);
                        $db->where("contact_mobile", $new_mobile);
                        $xun_business_contact_group_member = $db->getOne("xun_business_contact_group_member");
                        if (!$xun_business_contact_group_member) {
                            $contact_group_member_values = array($business_id, $group_id, $new_mobile, $name, 1, $now, $now);
                            $contact_group_member_array_data = array_combine($contact_group_member_fields, $contact_group_member_values);
                            $result = $db->insert("xun_business_contact_group_member", $contact_group_member_array_data);
                        }
                    }
                }

                fclose($file);

            } else {
                $error_message = $this->get_translation_message('E00111') /*Invalid File:Please Upload CSV File*/;
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }
        } else {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00112') /*File cannot be empty.*/, "developer_msg" => "$file_param cannot be empty");
        }

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00057') /*CSV File has been successfully Imported.*/);

    }

    public function message_history_listing($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;
        $general = $this->general;

        $pageNumber = $params['page'] ? $params['page'] : 1;
        //Get the limit.
        $limit = $general->getLimit($pageNumber);

        $business_email = $params["business_email"];
        $business_id = $params["business_id"];
        // Param validations
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/, "developer_msg" => "business_email cannot be empty");
        };

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot be empty");
        };

        $db->orderBy("sent_datetime", "DESC");
        $copyDb = $db->copy();
        $db->where('business_id', $business_id);
        $history = $db->get("xun_publish_message_log", $limit);

        $db->where('business_id', $business_id);
        $record = $db->get("xun_publish_message_log");

        // $history = $db->rawQuery("SELECT `id` , `sent_mobile_length`,`tag` ,`sent_datetime` FROM xun_publish_message_log  WHERE `business_id` = '$business_id'");

        $totalRecords = sizeof($record);
        //$totalRecords = $copyDb->getValue("xun_publish_message_log", "count(id)");
        $data['data'] = $history;

        $data['total_page'] = ceil($totalRecords / $limit[1]);
        $data['page'] = $pageNumber;
        $data['total_records'] = $totalRecords;
        $data['page_size'] = $limit[1];

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00058') /*Business broadcast history.*/, "result" => $data);

    }
    public function business_contact_group_edit($params)
    {
        /*
        x-xun-token
        business email
        business_id
        group_name
        group_id
         */

        $db = $this->db;

        $business_email = trim($params["business_email"]);
        $business_id = trim($params["business_id"]);
        $group_id = trim($params["group_id"]);
        $group_name = trim($params["group_name"]);

        // Param validations
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/, "developer_msg" => "business_email cannot be empty");
        };

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot be empty");
        };

        if ($group_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty.*/, "developer_msg" => "group_id cannot be empty");
        };

        if ($group_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00107') /*Group name cannot be empty.*/, "developer_msg" => "group_name cannot be empty");
        };

        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        $db->where("id", $group_id);
        $xun_business_contact_group = $db->getOne("xun_business_contact_group");

        if (!$xun_business_contact_group) {
            $error_message = $this->get_translation_message('E00136') /*This contact group does not exists.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        if ($xun_business_contact_group["business_id"] !== $business_id) {
            $error_message = $this->get_translation_message('E00136') /*This contact group does not exists.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        if ($xun_business_contact_group["status"] == 0) {
            $error_message = $this->get_translation_message('E00136') /*This contact group does not exists.*/;
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        if ($xun_business_contact_group["name"] === $group_name) {
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "business_id" => $business_id, "group_id" => $group_id, "group_name" => $group_name);
            // business_id, group_id, group_name
        }

        $now = date("Y-m-d H:i:s");

        $updateContactGroup["modified_date"] = $now;
        $updateContactGroup["name"] = $group_name;
        $db->where("id", $group_id);
        $db->update("xun_business_contact_group", $updateContactGroup);

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "business_id" => $business_id, "group_id" => $group_id, "group_name" => $group_name);

    }

    public function message_history_detail($params)
    {
        $db = $this->db;
        $general = $this->general;

        $business_id = $params["business_id"];
        $message_history_id = $params["message_history_id"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($message_history_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00115') /*Message history ID cannot be empty.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("id", $message_history_id);

        $result = $db->getOne("xun_publish_message_log");

        if ($result) {
            $total_recipients = $result['request_mobile_length'];
            $total_sent = $result['sent_mobile_length'];
            $tag = $result['tag'];
            $valid_mobile_list = $result['valid_mobile_list'];
            $invalid_mobile_list = $result['invalid_mobile_list'];
            $id = $result['id'];
            $datetime = $result['sent_datetime'];

            $valid_mobile_list_arr = explode("##", $valid_mobile_list);
            $invalid_mobile_list_arr = explode("##", $invalid_mobile_list);

            $utc_datetime = gmdate('Y-m-d H:i:s', strtotime($datetime));

            $mobile_list = array_merge($valid_mobile_list_arr, $invalid_mobile_list_arr);
            $returnData = array(
                "total_sent" => $total_sent,
                "total_recipients" => $total_recipients,
                "tag" => $tag,
                "mobile_list" => $mobile_list,
                "id" => $id,
                "datetime" => $utc_datetime,

            );

            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00058') /*Business broadcast history.*/, "result" => $returnData);
        }
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00044') /*Invalid record.*/);
    }

    public function country_phone_code_list($params)
    {
        $db = $this->db;

        $result = $db->rawQuery("SELECT * FROM `country`");

        if ($result) {
            foreach ($result as $key) {

                $country_code = $key['iso_code2'];
                $country_name = $key['name'];
                $phone_code = $key['country_code'];

                $api_list[] = array(
                    "country_code" => $country_code,
                    "country_name" => $country_name,
                    "phone_code" => $phone_code,
                );
            }
            return array('code' => 1, 'message' => "SUCCESS", "result" => $api_list);
        }
        return array('code' => 1, 'message' => "SUCCESS", "result" => $api_list);
    }

    public function business_contact_group_list($params)
    {
        /*
        business_email
        business_id
         */

        $db = $this->db;

        $business_email = trim($params["business_email"]);
        $business_id = trim($params["business_id"]);

        // Param validations
        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/, "developer_msg" => "business_email cannot be empty");
        };

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot be empty");
        };

        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        $xun_business_contact_group_list = $db->rawQuery("SELECT bcg.id contact_group_id, bcg.name as contact_group_name, bcg.business_id, IFNULL(total_contact_group_member, 0) as total_contact_group_member, DATE_FORMAT(CONVERT_TZ(bcg.created_date,'+08:00','+00:00'),'%Y-%m-%dT%TZ') as contact_group_created_time FROM xun_business_contact_group as bcg LEFT JOIN (SELECT contact_group_id as id, count(*) as total_contact_group_member FROM xun_business_contact_group_member WHERE status = '1' GROUP BY contact_group_id) xun_business_contact_group_member USING (id) WHERE bcg.business_id =  '$business_id'  and bcg.status = '1' ");

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00059') /*Contact group listing.*/, "business_id" => $business_id, "result" => $xun_business_contact_group_list);
    }

    public function business_tag_delete_all($params)
    {

        $db = $this->db;
        $post = $this->post;

        global $config;
        $employee_server = $config["erlang_server"];

        $business_id = $params["business_id"];
        $date = date("Y-m-d H:i:s");

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        // get tag list of business
        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $xun_business_tag = $db->get("xun_business_tag", null, "tag");

        $tag_list = [];
        if ($xun_business_tag) {
            foreach ($xun_business_tag as $value) {
                $tag_list[] = $value["tag"];
            }

        }

        $returnResult = $this->delete_business_tag($business_id, $tag_list);

        return $returnResult;
    }

    public function business_tag_delete($params)
    {
        $db = $this->db;
        $post = $this->post;

        global $config;
        global $xunXmpp;
        $employee_server = $config["erlang_server"];

        $business_id = $params["business_id"];
        $tag_list = $params["tag"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($tag_list == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00116') /*Tag list cannot be empty.*/);
        }

        if (!is_array($tag_list)) {
            $tag_list = [$tag_list];
        }

        $returnResult = $this->delete_business_tag($business_id, $tag_list);

        return $returnResult;
    }

    private function delete_business_tag($business_id, $tag_list)
    {
        $db = $this->db;
        $post = $this->post;

        global $config;
        global $xunXmpp;

        $employee_server = $config["erlang_server"];
        $date = date("Y-m-d H:i:s");

        $default_tag_employee = [];
        foreach ($tag_list as $tag) {
            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->where("status", 1);
            $xun_business_tag_employee = $db->get("xun_business_tag_employee");
            if ($xun_business_tag_employee) {
                $default_tag_employee[$tag][] = $xun_business_tag_employee;
            }
        }

        foreach ($tag_list as $tag) {
            $updateTag = [];
            $updateTag["status"] = 0;
            $updateTag["updated_at"] = $date;
            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->update("xun_business_tag", $updateTag);

            $updateTagEmployee = [];
            $updateTagEmployee["status"] = 0;
            $updateTagEmployee["updated_at"] = $date;
            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->where("status", 1);
            $db->update("xun_business_tag_employee", $updateTagEmployee);

            $updateForwardMessage = [];
            $updateForwardMessage["status"] = 0;
            $updateForwardMessage["updated_at"] = $date;
            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->where("status", 1);
            $db->update("xun_business_forward_message", $updateForwardMessage);
        }

        /*
        get_default_tag_employee = [ [], [] ]
         */
        //build final remove employee

        $erlangReturnArr = [];
        foreach ($default_tag_employee as $tag => $tag_employee_arr) {
            // loop tag
            $final_remove_employee_list = [];
            $subscriber_jid_list = [];

            foreach ($tag_employee_arr[0] as $tag_employee) {
                // loop tag_employee
                # code...

                $employee_id = $tag_employee["employee_id"];
                $employee_mobile = $tag_employee["username"];

                $db->where("business_id", $business_id);
                $db->where("mobile", $employee_mobile);
                $db->where("status", 1);
                $xun_employee = $db->getOne("xun_employee");

                $remove_employee_mobile = $employee_mobile;
                $remove_employee_server = $employee_server;
                $remove_employee_role = $xun_employee["role"];

                $remove_employee_arr = array(
                    'employee_mobile' => $remove_employee_mobile,
                    'employee_server' => $remove_employee_server,
                    'employee_role' => $remove_employee_role,
                );

                $final_remove_employee_list[] = $remove_employee_arr;

                //  build sub employee
                $subscriber_jid_list[] = $employee_mobile . "@" . $employee_server;
            }

            // call erlang here - per tag
            // only call of new and removed list are empty
            $erlangReturnArr[] = $xunXmpp->send_xmpp_business_tag_event($business_id, $tag, [], $final_remove_employee_list, $subscriber_jid_list);
        }

        return array("message" => "SUCCESS", "message_d" => $this->get_translation_message('B00060') /*Categories has been deleted.*/, "code" => 1, "erlang_return" => $erlangReturnArr);
    }

    public function business_tag_edit($params)
    {
        global $config;

        $db = $this->db;
        $post = $this->post;

        $business_id = $params["business_id"];
        $tag = $params["tag"];
        $callback_url = $params["callback_url"];
        $tag_description = $params["tag_description"];
        $employee_mobile = $params["employee_mobile"];
        $working_hour_from = $params["working_hour_from"];
        $working_hour_to = $params["working_hour_to"];
        $priority = $params["priority"];

        $date = date("Y-m-d H:i:s");

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($tag == '') {
            $return_message = $this->get_translation_message('E00003');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00003') /*Tag cannot be empty*/);
        }
        if ($priority == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00117') /*Priority cannot be empty.*/);
        }

        if ($callback_url) {
            if (!filter_var($callback_url, FILTER_VALIDATE_URL)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00118') /*Please enter a valid forward URL.*/, "developer_msg" => "forward_url is not a valid URL");
            }
        }

        //check record in xun tag
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", "1");
        $check_tag = $db->getOne("xun_business_tag");

        if (!$check_tag) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00044') /*Invalid record.*/);
        }

        $updateData["working_hour_from"] = $working_hour_from;
        $updateData["working_hour_to"] = $working_hour_to;
        $updateData["description"] = $tag_description;
        $updateData["priority"] = $priority;
        $updateData["updated_at"] = $date;

        $db->where("id", $check_tag["id"]);
        $db->update("xun_business_tag", $updateData);

        unset($updateData);

        //check tag_employee (check the record in xun_business_tag)
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", "1");
        $check_tag_employee = $db->get("xun_business_tag_employee");

        $initial_tag_employee_list = array();
        $owner_mobile = array();

        // param employee id
        foreach ($check_tag_employee as $value) {
            $initial_tag_employee_list[] = $value["username"];
            $initial_employee_id[$value["username"]] = $value["id"];
        }

        $db->where("business_id", $business_id);
        $db->where("employment_status", "confirmed");
        $employee_result = $db->get("xun_employee");

        foreach ($employee_result as $employee_data) {

            if ($employee_data["role"] == "owner") {
                $owner_mobile = $employee_data["mobile"];
            }

            $employee_ids[$employee_data["mobile"]] = $employee_data["old_id"];
            $employee_roles[$employee_data["mobile"]] = $employee_data["role"];

        }

        $employee_mobile = array_filter($employee_mobile);
        //remove owner mobile
        $initial_employee_list = array_diff($initial_tag_employee_list, array($owner_mobile));

        $remove_employee_list = array_diff($initial_employee_list, $employee_mobile);
        $add_employee_list = array_diff($employee_mobile, $initial_employee_list);

        if ($add_employee_list) {

            foreach ($add_employee_list as $mobile) {

                $employee_id = $employee_ids[$mobile];

                $fields = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
                $values = array($employee_id, $mobile, $business_id, $tag, "1", $date, $date);
                $insertData = array_combine($fields, $values);

                $db->insert("xun_business_tag_employee", $insertData);

            }

        }

        if ($remove_employee_list) {

            foreach ($remove_employee_list as $mobile) {

                $id = $initial_employee_id[$mobile];

                $updateData["status"] = "0";
                $updateData["updated_at"] = $date;

                $db->where("id", $id);
                $db->update("xun_business_tag_employee", $updateData);

            }

        }

        unset($updateData);

        //insert  / update url xun_business_forward table
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $xun_business_forward_message = $db->getOne("xun_business_forward_message");

        if ($callback_url) {
            if (!$xun_business_forward_message) {
                $fields = array("tag", "business_id", "forward_url", "status", "created_at", "updated_at");
                $values = array($tag, $business_id, $callback_url, '1', $date, $date);
                $arrayData = array_combine($fields, $values);
                $db->insert("xun_business_forward_message", $arrayData);

            } else {
                $id = $xun_business_forward_message["id"];

                $updateData["status"] = 1;
                $updateData["forward_url"] = $callback_url;
                $updateData["updated_at"] = $date;
                $db->where("id", $id);
                $db->update("xun_business_forward_message", $updateData);
            }
        } else {
            if ($xun_business_forward_message["status"] == 1) {
                $id = $xun_business_forward_message["id"];

                $updateData["status"] = 0;
                $updateData["updated_at"] = $date;
                $db->where("id", $id);
                $db->update("xun_business_forward_message", $updateData);
            }
        }

        // build final subscribers_jid
        $final_new_subscribers = array_unique(array_merge($initial_tag_employee_list, $add_employee_list));

        foreach ($final_new_subscribers as $mobile) {
            $subscribers_jid[] = $mobile . "@" . $config["erlang_server"];
        }

        foreach ($add_employee_list as $mobile) {
            $employee_role = $employee_roles[$mobile];

            $employee["employee_mobile"] = $mobile;
            $employee["employee_server"] = $config["erlang_server"];
            $employee["employee_role"] = $employee_role;

            $new_employee_list[] = $employee;
        }

        foreach ($remove_employee_list as $mobile) {
            $employee_role = $employee_roles[$mobile];

            $employee["employee_mobile"] = $mobile;
            $employee["employee_server"] = $config["erlang_server"];
            $employee["employee_role"] = $employee_role;

            $removed_employee_list[] = $employee;
        }

        $subscribers_jid = $subscribers_jid ? $subscribers_jid : array();
        $new_employee_list = $new_employee_list ? $new_employee_list : array();
        $removed_employee_list = $removed_employee_list ? $removed_employee_list : array();

        $erlangReturn = $this->send_xmpp_business_tag_event($business_id, $tag, $new_employee_list, $removed_employee_list, $subscribers_jid);

        if ($erlangReturn["code"] === 0) {
            return array("status" => "error", "statusMsg" => $erlangReturn["message_d"], code => 0);
        }

        return array("message" => "Success", "message_d" => $this->get_translation_message('B00061') /*Category details updated.*/, "code" => 1, "params" => $params, "erlangReturn" => $erlangReturn);
    }

    public function business_tag_add($params)
    {
        $db = $this->db;
        $post = $this->post;

        $business_id = $params["business_id"];
        $tag = $params["tag"];
        $callback_url = $params["callback_url"];
        $tag_description = $params["tag_description"];
        $employee_mobile = $params["employee_mobile"];
        $working_hour_from = $params["working_hour_from"];
        $working_hour_to = $params["working_hour_to"];
        $priority = $params["priority"];
        global $config;
        $employee_server = $config["erlang_server"];
        $date = date("Y-m-d H:i:s");

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($tag == '') {
            $return_message = $this->get_translation_message('E00003');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00003') /*Tag cannot be empty*/);
        }

        if ($priority == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00117') /*Priority cannot be empty.*/);
        }

        if ($callback_url) {
            if (!filter_var($callback_url, FILTER_VALIDATE_URL)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00118') /*Please enter a valid forward URL.*/, "developer_msg" => "forward_url is not a valid URL");
            }
        }

        //check business
        $db->where("user_id", $business_id);
        $check_business = $db->getOne("xun_business");
        if (empty($check_business)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        //check duplicate name
        $check_duplicate_name = $db->rawQuery("SELECT 'name' FROM `xun_business_tag` WHERE business_id = '$business_id' AND status = '1' AND  tag = '$tag'");
        if (!empty($check_duplicate_name)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00119') /*This business already have a similar tag added.*/);
        }

        //get employee
        $get_owner_moblie = $db->rawQuery("SELECT * FROM `xun_employee` WHERE business_id = '$business_id' AND status = '1' AND  role = 'owner'");
        $owner_mobile = $get_owner_moblie[0][mobile];
        $new_owner_mobile[] = $owner_mobile;

        //store in database xun_business_tag
        $fields = array("business_id", "tag", "description", "working_hour_from", "working_hour_to", "status", "priority", "created_at", "updated_at");
        $values = array($business_id, $tag, $tag_description, $working_hour_from, $working_hour_to, "1", $priority, $date, $date);
        $arrayData = array_combine($fields, $values);
        $db->insert("xun_business_tag", $arrayData);

        //combine onwer mobile and moblie list
        if (empty($employee_mobile)) {

            $new_moblie_list = $new_owner_mobile;
        } else {

            $new_moblie_list = array_unique(array_merge($new_owner_mobile, $employee_mobile));

        }

        //store in database xun_business_tag
        foreach ($new_moblie_list as $value) {

            $get_employee_details = $db->rawQuery("SELECT * FROM `xun_employee` WHERE business_id = '$business_id' AND status = '1' AND  mobile = '$value' AND employment_status = 'confirmed'");

            // if (empty($get_employee_details)) {
            //   return array('code' => 0, 'message' => "FAILED", 'message_d' => "Not employee");
            //  }

            foreach ($get_employee_details as $value) {

                $employee_id = $get_employee_details[0][old_id];
                $username = $get_employee_details[0][mobile];
                $business_id = $get_employee_details[0][business_id];

                $fields = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
                $values = array($employee_id, $username, $business_id, $tag, "1", $date, $date);
                $arrayData = array_combine($fields, $values);
                $db->insert("xun_business_tag_employee", $arrayData);

            }

        }

        //store xun business forward message
        if (!$callback_url == '') {
            $check_business_forward_message = $db->rawQuery("SELECT * FROM `xun_business_forward_message` WHERE business_id = '$business_id' AND tag = '1' AND  status = '$1'");

            if (empty($check_business_forward_message)) {
                $fields = array("tag", "business_id", "forward_url", "status", "created_at", "updated_at");
                $values = array($tag, $business_id, $callback_url, '1', $date, $date);
                $arrayData = array_combine($fields, $values);
                $db->insert("xun_business_forward_message", $arrayData);

            }

        }
        $new_moblie_list = array_filter($new_moblie_list);
        $final_employee_list = array();

        //builed final mobile list
        foreach ($new_moblie_list as $value) {

            $get_employee_details = $db->rawQuery("SELECT `mobile` , `role` FROM `xun_employee` WHERE business_id = '$business_id' AND status = '1' AND  mobile = '$value'");

            $add_employee_mobile = $get_employee_details[0][mobile];
            $add_employee_server = $employee_server;
            $add_employee_role = $get_employee_details[0][role];

            $final_employee_list[] = array('employee_mobile' => $add_employee_mobile,
                'employee_server' => $add_employee_server, 'employee_role' => $add_employee_role);

        }

        //subcribe list
        $subscribe_list = [];
        foreach ($new_moblie_list as $value) {

            $employe_number = $value . "@" . $employee_server;
            $subscribe_list[] = $employe_number;

        }

        if (empty($get_employee_details)) {
            $final_employee_list = [];
        }

        $removed_employee_list = [];

        $erlangReturn = $this->send_xmpp_business_tag_event($business_id, $tag, $final_employee_list, $removed_employee_list, $subscribe_list);

        if ($erlangReturn["code"] === 0) {
            return array("status" => "error", "statusMsg" => $erlangReturn["message_d"], code => 0);
        }

        return array('code' => 1, 'message' => "Success", 'message_d' => $this->get_translation_message('B00062') /*New category successfully added.*/);
    }

    public function app_business_tag_detail($params)
    {
        // mobile business_id tag

        $db = $this->db;
        $general = $this->general;

        $mobile = trim($params["mobile"]);
        $business_id = trim($params["business_id"]);
        $tag = trim($params["tag"]);

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot be empty");
        }

        if ($tag == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00003') /*Tag cannot be empty*/, "developer_msg" => "tag cannot be empty");
        }

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/, "developer_msg" => "mobile cannot be empty");
        }

        // get business tag and business tag employee

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $xun_business_tag = $db->getOne("xun_business_tag");

        if (!$xun_business_tag) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00033') /*Business Tag not found.*/);
        }

        // get business_tag_employee details
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $xun_business_tag_employee = $db->get("xun_business_tag_employee");

        $employee_list = [];
        foreach ($xun_business_tag_employee as $data) {
            // employee_role, employee_mobile, employee_id
            $db->where('business_id', $business_id);
            $db->where("mobile", $data["username"]);
            $db->where("status", 1);
            $xun_employee = $db->getOne("xun_employee");
            if (!$xun_employee) {
                continue;
            }

            $employee = array();
            $employee["employee_mobile"] = $xun_employee["mobile"];
            $employee["employee_id"] = $xun_employee["old_id"];
            $employee["employee_role"] = $xun_employee["role"];
            $employee_list[] = $employee;
        }

        $tag_description = $xun_business_tag["description"] ? $xun_business_tag["description"] : "";
        $tag_created_at = $general->formatDateTimeToIsoFormat($xun_business_tag["created_at"]);

        $result_arr = array("tag" => $tag, "tag_description" => $tag_description, "employees" => $employee_list, "created_date" => $tag_created_at, "business_id" => $business_id);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00183') /*Business tag details.*/, "result" => $result_arr);
    }

    public function send_business_update_profile_message($business_id, $event_type)
    {
        global $xunXmpp;
        $erlangReturn = $xunXmpp->send_business_update_profile_message($business_id, $event_type);

        return $erlangReturn;
    }

    public function business_package_subscription($params)
    {
        $db = $this->db;

        $business_email = trim($params["business_email"]);
        $business_id = trim($params["business_id"]);

        // get record from xun_business_package_subscription

        $db->where("user_id", $business_id);
        $xun_business = $db->getOne("xun_business");

        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("status", 1);

        $package_subscription = $db->getOne("xun_business_package_subscription");

        $returnData = array();

        if ($package_subscription) {
            // {"package_description": "Basic Plan", "package_price": "99.90",  "package_currency": "USD",  "package_start_date": "2017-8-21T6:13:37Z",    "package_end_date": "2017-9-20T6:13:37Z"}

            $package_code = $package_subscription["package_code"];

            if ($package_code) {
                $db->where("code", $package_code);
                $xun_package = $db->getOne("xun_business_package");

                if ($xun_package) {
                    $package_currency = $xun_package["currency"];
                    $package_price = $xun_package["price"];
                    $package_description = $xun_package["description"];
                    $package_message_limit = $xun_package["message_limit"];
                }
            }

            $package_start_date = $package_subscription["startdate"];
            $package_end_date = $package_subscription["enddate"];

            $new_package_start_date = new DateTime($package_start_date);
            $new_package_start_date = $new_package_start_date->format('Y-m-d H:i:s');
            $new_package_start_date = date("Y-m-d H:i:s", strtotime($new_package_start_date . "-8 hours"));

            $new_package_end_date = new DateTime($package_end_date);
            $new_package_end_date = $new_package_end_date->format('Y-m-d H:i:s');
            $new_package_end_date = date("Y-m-d H:i:s", strtotime($new_package_end_date . "-8 hours"));

            $returnData = array("package_description" => $package_description, "package_price" => $package_price, "package_currency" => $package_currency, "package_start_date" => $new_package_start_date, "package_end_date" => $new_package_end_date, "package_message_limit" => $package_message_limit);
        }

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/, "result" => $returnData);
    }

    private function create_default_business_subscription($business_id)
    {
        $db = $this->db;

        $default_package_code = "S0001";
        $db->where("code", $default_package_code);
        $xun_package = $db->getOne("xun_business_package");
        if ($xun_package) {
            $package_message_limit = $xun_package["message_limit"];
        }

        $now = date("Y-m-d H:i:s");
        $end_date = date("Y-m-d H:i:s", strtotime('+30 days', strtotime($now)));

        $fields = array("business_id", "package_code", "billing_id", "message_limit", "status", "startdate", "enddate", "created_at", "updated_at");
        $values = array($business_id, $default_package_code, "", $package_message_limit, 1, $now, $end_date, $now, $now);
        $arrayData = array_combine($fields, $values);
        $row_id = $db->insert("xun_business_package_subscription", $arrayData);
        return $row_id;
    }

//--------------------------------------------------------------------------------------------------------------------------------------------------
    //UTM
    //--------------------------------------------------------------------------------------------------------------------------------------------------

    public function utm_record($data)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $business_id = $data["business_id"] ? $data["business_id"] : 0;
        $business_name = $data["business_name"] ? $data["business_name"] : 0;
        $utm_source = $data["utm_source"] ? $data["utm_source"] : '-';
        $utm_medium = $data["utm_medium"] ? $data["utm_medium"] : '-';
        $utm_campaign = $data["utm_campaign"] ? $data["utm_campaign"] : '-';
        $utm_term = $data["utm_term"] ? $data["utm_term"] : '-';
        $device_id = $data["device_id"];
        $ip = $data["ip"] ? $data["ip"] : 0;
        $userAgent = $data["user_agent"] ? $data["user_agent"] : 0;
        $type = $data["type"] ? $data["type"] : 0;
        $country = $data["country"] ? $data["country"] : 0;
        $url = $data["url"];
        $register_status = $data["register_status"];
        $today = date("Y-m-d H:i:s");

        $flag = true;

        if (!$device_id) {

            while ($flag) {

                $randNum = rand(1, 100000000);
                $value = $randNum;

                $db->where('device_id', $value);
                $result = $db->get('utm_record');

                if (!$result) {

                    $flag = false;
                    $device_id = $value;
                }
            }
        }

        $fields = array("business_id", "business_name", "utm_source", "utm_medium", "utm_campaign", "utm_term", "device_id"
            , "ip", "user_agent", "type", "country", "created_at", "url", "register_status");

        $values = array($business_id, $business_name, $utm_source, $utm_medium, $utm_campaign, $utm_term, $device_id, $ip, $userAgent, $type, $country, $today, $url, $register_status);
        $arrayData = array_combine($fields, $values);

        $debitID = $db->insert("utm_record", $arrayData);

        return array('status' => ok, 'code' => 1, 'statusMsg' => Success, 'device_id' => $device_id);

    }

    public function utm_list($data)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;
        $general = $this->general;

        $pageNumber = $data['pageNumber'] ? $data['pageNumber'] : 1;
        //Get the limit.
        $limit = $general->getXunLimit($pageNumber);

        $searchCountry = $data["country"];
        $searchDate_to = $data["date_to"];
        $searchDate_form = $data["date_from"];
        $searchDevice_id = $data["device_id"];
        $searchIp = $data["ip"];

        // $date_form =  date('Y-m-d H:i:s', $searchDate_form);
        // $date_to = date('Y-m-d H:i:s', $searchDate_to);

        foreach ($data as $key => $x_value) {

            $columnName = 'created_at';
            switch ($key) {
                case 'country':
                    if ($searchCountry != '') {
                        $db->where('country', $searchCountry);
                    }
                    break;
                case 'date_from':
                    if ($searchDate_form != '') {
                        // $db->where('created_at', $searchDate_form ,'>=');
                        $db->where($columnName, date('Y-m-d H:i:s', $searchDate_form), '>=');
                    }

                    break;
                case 'date_to':
                    if ($searchDate_to != '') {
                        $db->where($columnName, date('Y-m-d H:i:s', $searchDate_to), '<=');
                    }
                    break;
                case 'device_id':
                    if ($searchDevice_id != '') {
                        $db->where('device_id', $searchDevice_id . "%", 'LIKE');
                    }
                    break;

                case 'ip':
                    if ($searchIp != '') {
                        $db->where('ip', $searchIp . "%", 'LIKE');
                    }
                    break;

            }
        }

        $db->orderBy("created_at", "DESC");
        $copyDb = $db->copy();

        if ($data['pagination'] == "No") {
            $result = $db->get("utm_record");
        } else {
            $result = $db->get("utm_record", $limit);

        }
        if (!empty($result)) {
            foreach ($result as $value) {

                $utm_record['created_at'] = $value['created_at'];
                $utm_record['business_id'] = $value['business_id'];
                $utm_record['business_name'] = $value['business_name'];
                $utm_record['device_id'] = $value['device_id'];
                $utm_record['utm_source'] = $value['utm_source'];
                $utm_record['utm_medium'] = $value['utm_medium'];
                $utm_record['utm_term'] = $value['utm_term'];
                $utm_record['utm_campaign'] = $value['utm_campaign'];
                $utm_record['ip'] = $value['ip'];
                $utm_record['url'] = $value['url'];
                $utm_record['user_Agent'] = $value['user_Agent'];
                $utm_record['type'] = $value['type'];
                $utm_record['country'] = $value['country'];
                $countriesList[] = $utm_record;
            }

            $totalRecords = $copyDb->getValue("utm_record", "count(id)");

            $data['countriesList'] = $countriesList;
            $data['totalPage'] = ceil($totalRecords / $limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];

            return array('status' => "ok", 'code' => 1, 'statusMsg' => '', 'data' => $data);
        } else {
            return array('status' => "ok", 'code' => 1, 'statusMsg' => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => "");
        }

    }

//UTM _traking_ add
    public function utm_tracking($data)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;
        global $action_tracking_number;

        $business_name = $data["business_name"] ? $data["business_name"] : '-';
        $mobile_number = $data["mobile_number"] ? $data["mobile_number"] : '-';
        $email_address = $data["email_address"] ? $data["email_address"] : '-';
        $device_id = $data["device_id"] ? $data["device_id"] : 0;
        $utm_campaign = $data["utm_campaign"] ? $data["utm_campaign"] : '-';
        $action_type = $data["action_type"] ? $data["action_type"] : 0;

        $utm_source = $data["utm_source"] ? $data["utm_source"] : '-';
        $utm_medium = $data["utm_medium"] ? $data["utm_medium"] : '-';
        $utm_term = $data["utm_term"] ? $data["utm_term"] : '-';
        $device = $data["device"] ? $data["device"] : '-';

        $staus_msg = $data["status_msg"];
        $today = date("Y-m-d H:i:s");

        $fields = array("business_name", "mobile_number", "email_address", "device_id", "utm_campaign", "action_type", "utm_Source", "utm_Medium", "utmTerm", "device", "created_at");
        $values = array($business_name, $mobile_number, $email_address, $device_id, $utm_campaign, $action_type, $utm_source, $utm_medium, $utm_term, $device, $today);
        $arrayData = array_combine($fields, $values);

        $debitID = $db->insert("utm_tracking", $arrayData);

        if ($staus_msg != "0") {
//////// sms to xun
            $url_string = "business/broadcast";
            $msg = "Device Id : $device_id\r\nDevice : $device\r\n\r\nUtm Source  : $utm_source\r\nUtm Medium : $utm_medium\r\nUtm Campaign : $utm_campaign\r\nUtm Term : $utm_term\r\n\r\nAction Type  : $action_type\r\nBusiness Name : $business_name\r\nMobile Number : $mobile_number\r\nEmail : $email_address\r\n\r\nNotice on : $today";

            $params = array(
                "api_key" => 'ZjO6oby2vPi3BQdWiFpzB0fWEPJ7CwZH',
                "mobile_list" => $action_tracking_number,
                "tag" => $device_id,
                "message" => $msg,
                "business_id" => '10224',
            );

            $this->business_message_sending($url_string, $params);
        }
        return array('code' => 1, 'status' => Success, 'statusMsg' => $this->get_translation_message('B00012') /*Success*/);

    }

// UTM _traking _list
    public function utm_tracking_list($data)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;
        $general = $this->general;

        $pageNumber = $data['pageNumber'] ? $data['pageNumber'] : 1;
        //Get the limit.
        $limit = $general->getXunLimit($pageNumber);

        $searchActionType = $data["action_type"];
        $searchDate_to = $data["date_to"];
        $searchDate_form = $data["date_from"];
        $searchDevice_id = $data["device_id"];

        // $date_form =  date('Y-m-d H:i:s', $searchDate_form);
        // $date_to = date('Y-m-d H:i:s', $searchDate_to);

        foreach ($data as $key => $x_value) {

            $columnName = 'created_at';
            switch ($key) {
                case 'action_type':
                    if ($searchActionType != '') {
                        $db->where('action_type', $searchActionType . "%", 'LIKE');
                    }
                    break;
                case 'date_from':
                    if ($searchDate_form != '') {
                        // $db->where('created_at', $searchDate_form ,'>=');
                        $db->where($columnName, date('Y-m-d H:i:s', $searchDate_form), '>=');
                    }
                    break;
                case 'date_to':
                    if ($searchDate_to != '') {
                        $db->where($columnName, date('Y-m-d H:i:s', $searchDate_to), '<=');
                    }
                    break;
                case 'device_id':
                    if ($searchDevice_id != '') {
                        $db->where('device_id', $searchDevice_id . "%", 'LIKE');

                    }
                    break;

            }
        }

        $db->orderBy("created_at", "DESC");
        $copyDb = $db->copy();

        if ($data['pagination'] == "No") {
            $result = $db->get("utm_tracking");
        } else {
            $result = $db->get("utm_tracking", $limit);
        }

        if (!empty($result)) {
            foreach ($result as $value) {

                $utm_tracking['created_at'] = $value['created_at'];
                $utm_tracking['device_id'] = $value['device_id'];
                $utm_tracking['action_type'] = $value['action_type'];
                $utm_tracking['utm_campaign'] = $value['utm_campaign'];
                $utm_tracking['business_name'] = $value['business_name'];
                $utm_tracking['mobile_number'] = $value['mobile_number'];
                $utm_tracking['email_address'] = $value['email_address'];

                $countriesList[] = $utm_tracking;
            }

            $totalRecords = $copyDb->getValue("utm_tracking", "count(id)");

            $data['List'] = $countriesList;
            $data['totalPage'] = ceil($totalRecords / $limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];

            return array('status' => "ok", 'code' => 1, 'statusMsg' => '', 'data' => $data);
        } else {
            return array('status' => "ok", 'code' => 1, 'statusMsg' => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => "");
        }

    }
    /////-------------------------------------------------------------------
    //mobile
    /////-------------------------------------------------------------------

    public function update_device_information($params)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $mobile = $params["mobile"];
        $device_model = $params["device_model"];
        $device_os = $params["device_os"];
        $device_os_version = $params["device_os_version"];
        $device_voip_access_token = $params["device_voip_access_token"];
        $device_access_token = $params["device_access_token"];
        $device_google_play = $params["device_google_play"];
        $app_version = $params["app_version"];
        $additional_info = $params["additional_info"];
        $ip = $params["X-Real-Ip"];

        $date = date("Y-m-d H:i:s");

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }
        if ($device_model == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00121') /*Device model cannot be empty.*/);
        }
        if ($device_os == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00122') /*Device OS cannot be empty.*/);
        }

        if ($device_os_version == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00123') /*Device os version cannot be empty.*/);
        }
        if ($device_access_token == '') {
            $device_access_token = '';
            // return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00124') /*Device access token cannot be empty.*/);
        }
        if ($device_google_play == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00125') /*Device google play cannot be empty.*/);
        }
        if ($app_version == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00126') /*App Version cannot be empty.*/);
        }

        $device_voip_access_token = is_null($device_voip_access_token) ? "" : $device_voip_access_token;

        // if ($additional_info == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Additional info cannot be empty");
        // }

        //random nunmber for user id
        $flag = true;

        if (!$device_id) {

            while ($flag) {

                $randNum = rand(1, 100000000);
                $value = $randNum;

                $db->where('user_id', $value);
                $result = $db->get('xun_user_device');

                if (!$result) {

                    $flag = false;
                    $device_id = $value;
                }
            }
        }

        //check the username inside in the mobile list
        // $check_mobile = $db->rawQuery("SELECT *  FROM `xun_user` WHERE username = $mobile");
        $db->where("username", $mobile);
        $check_mobile = $db->getOne("xun_user");

        if (empty($check_mobile)) {
            return array('code' => 0, 'status' => Failes, 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        //upsert
        $user_id = $check_mobile["id"];
        if($ip){
            $db->where("user_id", $user_id);
            $db->where("name", ["lastLoginIP", "ipCountry"], "in");
            $ip_data = $db->map("name")->ObjectBuilder()->get("xun_user_setting", null, "id, user_id, name, value");
            
            $last_login_ip_data = $ip_data["lastLoginIP"];
            $get_ip_country = false;
            if(!$last_login_ip_data){
                $insert_ip_data = array(
                    "user_id" => $user_id,
                    "name" => "lastLoginIP",
                    "value" => $ip,
                    "created_at" => $date,
                    "updated_at" => $date
                );
    
                $db->insert("xun_user_setting", $insert_ip_data);
                $get_ip_country = true;
            }
            else if($last_login_ip_data && $last_login_ip_data->value != $ip){
                $update_ip_data = array(
                    "value" => $ip,
                    "updated_at" => $date,
                );
    
                $db->where("id", $last_login_ip_data->id);
                $db->update("xun_user_setting", $update_ip_data);
                $get_ip_country = true;
            }
            
            if($get_ip_country === true){
                //  get country
                $xunIP = new XunIP($db);
                $ip_country = $xunIP->get_ip_country($ip);
                
                $ip_country_data = $ip_data["ipCountry"];
    
                if($ip_country_data){
                    $update_ip_country = array(
                        "updated_at" => $date,
                        "value" => $ip_country
                    );
                    $db->where("id", $ip_country_data->id);
                    $db->update("xun_user_setting", $update_ip_country);
                }else{
                    $insert_ip_country = array(
                        "user_id" => $user_id,
                        "name" => "ipCountry",
                        "value" => $ip_country,
                        "created_at" => $date,
                        "updated_at" => $date
                    );
    
                    $db->insert("xun_user_setting", $insert_ip_country);
                }
            }
        }

        $db->where("mobile_number", $mobile);
        $check_user_device_record = $db->getOne("xun_user_device");

        $uuid = $check_user_device_record[user_id];

        if (empty($check_user_device_record)) {

            $fields = array("user_id", "mobile_number", "os", "device_model", "os_version", "app_version", "access_token", "voip_access_token", "google_play_token", "device_name", "disabled", "created_at", "updated_at");

            $values = array($device_id, $mobile, $device_os, $device_model, $device_os_version, $app_version, $device_access_token, $device_voip_access_token, $device_google_play, $additional_info, 1, $date, $date);
            $arrayData = array_combine($fields, $values);
            $db->insert("xun_user_device", $arrayData);

            return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00064') /*Updated user's device details.*/, 'developer_msg' => "");
        } else {

            $updateData["device_model"] = $device_model;
            $updateData["os"] = $device_os;
            $updateData["os_version"] = $device_os_version;
            $updateData["access_token"] = $device_access_token;
            $updateData["app_version"] = $app_version;
            $updateData["voip_access_token"] = $device_voip_access_token;
            $updateData["google_play_token"] = $device_google_play;
            $updateData["disabled"] = 1;
            $updateData["device_name"] = $additional_info;

            $updateData["updated_at"] = $date;
            $db->where("mobile_number", $mobile);
            $db->where("user_id", $uuid);
            $db->update("xun_user_device", $updateData);

            return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00064') /*Updated user's device details.*/, 'developer_msg' => "");

        }

    }

    public function user_setting_notification_chatroom_get_all($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $mobile = trim($params["mobile"]);

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }

        $get_setting_notification_chatroom = $db->rawQuery("SELECT *  FROM `xun_user_chat_preference` WHERE username = '$mobile'");

        $setting_notification_chatroom_result = [];
        foreach ($get_setting_notification_chatroom as $data) {
            $chatroom_setting = $this->compose_chat_room_preference($data);
            $setting_notification_chatroom_result[] = $chatroom_setting;
        }

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00065') /*User's chat room preferences.*/, 'result' => $setting_notification_chatroom_result);
    }

    public function get_privacy_settings($params)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $mobile = $params["mobile"];

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }

        $get_setting = $db->rawQuery("SELECT `profile_picture`  FROM `xun_user_privacy_settings` WHERE mobile_number = '$mobile'");

        $profile_picture = array('profile_picture' => $get_setting[0][profile_picture]);

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00066') /*Privacy settings updated.*/, 'result' => $profile_picture);
    }

    public function app_crypto_address_set($params)
    {
        global $xunXmpp, $setting, $xunTree, $xunReward;
        $db = $this->db;
        $general = $this->general;

        // $stream_jid = trim($params["stream_jid"]);
        $username = trim($params["username"]);
        $crypto_address = trim($params["crypto_address"]);
        $business_id = trim($params["business_id"]);


        $date = date("Y-m-d H:i:s");

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00008') /*Stream JID cannot be empty*/);
        }
        if ($crypto_address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00127') /*Crypto Address cannot be empty.*/);
        }

        // $check_stream_jid = $xunXmpp->get_xmpp_jid($stream_jid);

        // if ($check_stream_jid["code"] == 0) {
        //     return $check_stream_jid;
        // }

        // $mobile = $check_stream_jid["jid_user"];
        $mobile = $username;

        $db->where("username", $mobile);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user", "id, username, nickname");
        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];

        $db->where("user_id", $user_id);
        $db->where("name", "lastLoginIP");
        $ip = $db->getValue("xun_user_setting", "value");

        $device_os = $db->where("mobile_number", $mobile)->getValue("xun_user_device", "os");
        if($device_os == 1){$device_os = "Android";}
        else if ($device_os == 2){$device_os = "iOS";}

        $is_user = 1;
        //  check if it's business's address
        if(!empty($business_id)){
            $xunBusinessService = new XunBusinessService($db);

            $business_data = $xunBusinessService->getBusinessDetails($business_id);
            if (!$business_data) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
            }

            $business_owner = $this->get_business_owner($business_id);

            if(!$business_owner || $mobile != $business_owner["mobile"]){
            // if($mobile != $business_data["main_mobile"]){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00268')/*You're not allowed to edit properties of this business.*/);
            }

            $user_id = $business_id;
            $business_name = $business_data["name"];
            $is_user = 0;
        }

        $db->where("address", $crypto_address);
        // $db->where("deleted", 0);

        $xun_crypto_user_address = $db->getOne("xun_crypto_user_address");

        // if key exists and user_id !== mobile -> reject update
        if ($xun_crypto_user_address) {
            if ($xun_crypto_user_address["user_id"] != $user_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00269') /*Error. This address is already in use.*/);
            }

            if($xun_crypto_user_address["address_type"] != "personal"){
                return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00269') /*Error. This address is already in use.*/ );
            }
            // activate key if it's inactive
            if ($xun_crypto_user_address["active"] === 0) {
                // inactivate the current active key
                $updateData;
                $updateData["active"] = 0;
                $updateData["updated_at"] = $date;
                $db->where("user_id", $user_id);
                $db->where('address_type', 'personal');
                $db->where("active", 1);
                $db->update("xun_crypto_user_address", $updateData);

                $updateData = [];
                $updateData["active"] = 1;
                $updateData["deleted"] = 0;
                $updateData["updated_at"] = $date;

                $db->where("id", $xun_crypto_user_address["id"]);
                $row_id = $db->update("xun_crypto_user_address", $updateData);
            }else{
                $row_id = $xun_crypto_user_address["id"];
            }
        } else {
            // inactivate the current active key
            $updateData;
            $updateData["active"] = 0;
            $updateData["updated_at"] = $date;
            $db->where("user_id", $user_id);
            $db->where("active", 1);
            $db->where('address_type', 'reward', '!=');
            $db->update("xun_crypto_user_address", $updateData);

            $arrayData = [];

            $arrayData = array(
                "user_id" => $user_id,
                "address" => $crypto_address,
                "address_type" => "personal",
                "active" => '1',
                "deleted" => 0,
                "created_at" => $date,
                "updated_at" => $date
            );
            $row_id = $db->insert("xun_crypto_user_address", $arrayData);

            $user_country_info_arr = $this->get_user_country_info([$mobile]);

            $user_country_info = $user_country_info_arr[$mobile];
            $user_country = $user_country_info["name"];

            //send notification
            $tag = "Create Wallet";

            if($is_user === 1){
                $msg .= "Username: " . $nickname . "\n";
                $msg .= "Phone number: " . $mobile . "\n";
                $msg .= "IP: " . $ip . "\n";
                $msg .= "Country: " . $user_country . "\n";
                $msg .= "Device: " . $device_os . "\n";
                $msg .= "Type of User: User\n";
            }else{
                $msg .= "Business ID: $business_id\n";
                $msg .= "Business Name: $business_name\n";
                $msg .= "Type of User: Business\n";
            }

//            $msg .= "Status: Success\n";
            $msg .= "Time: $date";

            $erlang_params["tag"] = $tag;
            $erlang_params["message"] = $msg;
            $erlang_params["mobile_list"] = array();
            $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);
            //end send notification

        }

        //  call fund out if havent receive freecoin
        if ($is_user === 1 && !is_null($row_id)){            
            // from unregistered to registered create wallet and send reward coin
            try{
                $send_reward_params = array(
                    "user_id" => $user_id,
                    // "business_coin_id" => $business_coin_id,
                    // "wallet_type" => $wallet_type,
                    // "business_id" => $coin_business_id
                );
                $xunReward->new_follower_send_welcome_reward($send_reward_params);
            }catch(Exception $e){
                $error_msg = $e->getMessage();
            }
            // check if have upline
            // if no upline skip freecoin claim
            $upline_id = $xunTree->getSponsorUplineIDByUserID($user_id);

            if(!is_null($upline_id)){
                // check for claim

                //  disable freecoin fundout
                /*
                $newParams = array("user_id" => $user_id, "address" => $crypto_address);
                $xunFreecoinPayout = new XunFreecoinPayout($db, $setting, $general);
                $userFreecoinRecord = $xunFreecoinPayout->fundOutFreecoin($newParams);
                */
            }

        }

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00012') /*Success*/);

    }

    public function xun_app_crypto_address($params)
    {

        $db = $this->db;
        $post = $this->post;

        $stream_jid = $params["stream_jid"];
        $mobile = trim($params["mobile"]);
        $business_id = trim($params["business_id"]);

        if ($stream_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00008') /*Stream JID cannot be empty*/);
        }

        if ($mobile == '' && $business_id == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00273') /*Mobile or Business_id is required.*/);
        }

        if(!empty($mobile)){
            $db->where("username", $mobile);
            $xun_user = $db->getOne("xun_user", "id, username");
            $user_id = $xun_user["id"];
            $db->where("user_id", $user_id);
            $db->where("active", 1);
            $db->where("deleted", 0);
            $db->where("address_type", "personal");
            $xun_crypto_user_address = $db->getOne("xun_crypto_user_address");
    
            if (!$xun_crypto_user_address) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00128') /*This mobile does not have an associated crypto address.*/, "errorCode" => -100);
            }
    
            $crypto_address = $xun_crypto_user_address["address"];
    
            return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00068') /*User's crypto address.*/, "crypto_address" => $crypto_address, "mobile" => $mobile);
    
        }else{
            $db->where("id", $business_id);
            $db->where("type", "business");
            $business_data = $db->getOne("xun_user");
            if(!$business_data){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00273')/*This business id does not have an associated crypto address.*/, "errorCode" => -101);
            }
            $business_name = $business_data["nickname"];
            $user_id = $business_id;

            $db->where("user_id", $user_id);
            $db->where("active", 1);
            $db->where("deleted", 0);
            $db->where("address_type", "personal");
            $xun_crypto_user_address = $db->getOne("xun_crypto_user_address");
    
            if (!$xun_crypto_user_address) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00273')/*This business id does not have an associated crypto address.*/, "errorCode" => -101);
            }
    
            $crypto_address = $xun_crypto_user_address["address"];
    
            return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00068') /*User's crypto address.*/, "crypto_address" => $crypto_address, "business_id" => $business_id, "business_name" => $business_name);
        }

    }

    public function app_crypto_mobile_get($params)
    {
        global $setting;
        $db = $this->db;
        $post = $this->post;

        global $xunXmpp;

        $stream_jid = $params["stream_jid"];
        $crypto_address = $params["crypto_address"];
        $reference_address = $params["reference_address"];
        $transaction_hash = $params["transaction_hash"];

        if ($stream_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00008') /*Stream JID cannot be empty*/);
        }
        if ($crypto_address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00127') /*Crypto Address cannot be empty.*/);
        }

        $check_stream_jid = $xunXmpp->get_xmpp_jid($stream_jid);

        if ($check_stream_jid["code"] == 0) {
            return $check_stream_jid;
        }

        $mobile = $check_stream_jid["jid_user"];

        // check if contains '0x' prefix
        $crypto_address = (strpos($crypto_address, '0x') === 0) ? $crypto_address : "0x" . $crypto_address;

        $db->where("address", $crypto_address);
        $db->where("deleted", 0);
        $xun_crypto_user_address = $db->getOne("xun_crypto_user_address");

        if($transaction_hash){
            //  get reference id
            $db->where("transaction_hash", $transaction_hash);
            $receiver_reference = $db->getValue("xun_wallet_transaction","receiver_reference");
            $receiver_reference = $receiver_reference ? $receiver_reference : '';
        }

        if (!$xun_crypto_user_address) {
            $escrow_address = $setting->systemSetting["marketplaceEscrowWalletAddress"];
            $trading_fee_address = $setting->systemSetting["marketplaceTradingFeeWalletAddress"];
            $company_pool_address = $setting->systemSetting["marketplaceCompanyPoolWalletAddress"];
            $company_pool_address2 = $setting->systemSetting["marketplaceCompanyPoolWalletAddress2"];
            $freecoin_address = $setting->systemSetting["freecoinWalletAddress"];
            $payment_gateway_address = $setting->systemSetting["paymentGatewayWalletAddress"];
            $payment_gateway_address_arr = explode("#", $payment_gateway_address);
            $pay_address = $setting->systemSetting["payWalletAddress"];

            if($crypto_address == $escrow_address){
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "mobile" => "", "address_type" => "escrow");
            }elseif($crypto_address == $trading_fee_address){
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "mobile" => "", "address_type" => "Referral Commission");
            }elseif($crypto_address == $company_pool_address){
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "mobile" => "", "address_type" => "Master Dealer Commission");
            }elseif($crypto_address == $freecoin_address){
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "mobile" => "", "address_type" => "TheNux Airdrop");
            }elseif($crypto_address == $company_pool_address2){
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "mobile" => "", "address_type" => "Master Dealer Commission");
            }
            elseif(in_array($crypto_address, $payment_gateway_address_arr)){

                //if ($reference_address == "") {
                    return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "mobile" => "", "address_type" => "Payment Gateway");

                //} else {

                //    $db->where("a.crypto_address", $reference_address);
                //    $db->where("a.status", 1);
                //    $db->where("b.status", 1);
                //    $db->join("xun_crypto_wallet b", "a.wallet_id=b.id", "INNER");
                //    $db->join("xun_business c", "b.business_id=c.user_id", "INNER");
                //    $result = $db->getOne("xun_crypto_address a");

                //    if ($result) {
                //        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "business_name" => $result['name'], "type" => "business");
                //    } else {

                //        $db->where("a.destination_address", $reference_address);
                //        $db->where("a.status", 1);
                //        $db->where("b.status", 1);
                //        $db->join("xun_crypto_wallet b", "a.wallet_id=b.id", "INNER");
                //        $db->join("xun_business c", "b.business_id=c.user_id", "INNER");
                //        $result2 = $db->getOne("xun_crypto_destination_address a");

                //        if ($result2) {
                //            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "business_name" => $result2['name'], "type" => "business");
                //        } else {
                //            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "mobile" => "", "address_type" => "Payment Gateway");
                //        }

                //    }
                //}

            }elseif($crypto_address == $pay_address){
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "mobile" => "", "address_type" => "Top Up");
            }else{
                $message_d = $this->get_translation_message('E00129') /*This cryto address does not have an associated mobile number.*/;
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $message_d, "errorCode" => -100);
            }
        }else{
            $address_user_id = $xun_crypto_user_address["user_id"];
            $db->where("id", $address_user_id);
            $xun_user_res = $db->getOne("xun_user", "username, nickname, type");

            if($xun_crypto_user_address["address_type"] == "reward"){
                if($reference_address){
                    $db->where("a.crypto_user_address_id", $xun_crypto_user_address["id"]);
                    $db->where("a.external_address", $reference_address);
                    $db->where("b.type", "user");
                    $db->join("xun_user b", "a.user_id=b.id");
                    $reference_address_user = $db->getOne("xun_user_crypto_external_address a", "b.*");
                    
                    $db->where("user_id", $address_user_id);
                    $business_data = $db->getOne("xun_business", "id, user_id, name");
                    $business_name = $business_data["name"];
                    if(!$reference_address_user){
                        
                        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "business_name" => $business_name, "type" => "business",
                        "reference_number" => $receiver_reference);

                    }
                    return array(
                        "code" => 1,
                        "message" => "SUCCESS",
                        "message_d" => $this->get_translation_message('B00012') /*Success*/,
                        "mobile" => $reference_address_user["username"],
                        "nickname" => $business_name,
                        "type" => "company_pool",
                        "reference_number" => $receiver_reference
                    );
                }else{
                    $db->where("user_id", $address_user_id);
                    $business_data = $db->getOne("xun_business", "id, user_id, name");
                    $business_name = $business_data["name"];
                    return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "business_name" => $business_name, "type" => "business",
                    "reference_number" => $receiver_reference);
                }
            }else if($xun_user_res["type"] == "user"){
                $returnMobile = $xun_user_res["username"];
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "mobile" => $returnMobile, "nickname" => $xun_user_res["nickname"], "type" => "user",
                "reference_number" => $receiver_reference);
            }else{
                $db->where("user_id", $address_user_id);
                $business_data = $db->getOne("xun_business", "id, user_id, name");
                $business_name = $business_data["name"];
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "business_name" => $business_name, "type" => "business",
                "reference_number" => $receiver_reference);
            }
        }
    }

    public function app_crypto_address_verify($params)
    {
        $db = $this->db;

        $crypto_address = trim($params["crypto_address"]);
        $mobile = trim($params["username"]);
        $business_id = trim($params["business_id"]);

        if(empty($business_id)){
            $db->where("username", $mobile);
            $xun_user = $db->getOne("xun_user", "id, username");

            $user_id = $xun_user["id"];

            $db->where("user_id", $user_id);
            $db->where("address", $crypto_address);
            $xun_crypto_user_address = $db->getOne("xun_crypto_user_address");
        }else{
            $db->where("user_id", $business_id);
            $db->where("address", $crypto_address);
            $xun_crypto_user_address = $db->getOne("xun_crypto_user_address");
        }
        $is_address = false;
        if ($xun_crypto_user_address) {
            $is_address = true;
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00069') /*Address verification.*/, "is_address" => $is_address);
    }

    public function get_app_crypto_transaction_token($params)
    {
        $db = $this->db;
        $general = $this->general;
        $now = date("Y-m-d H:i:s");

        $username = trim($params["mobile"]);
        $reference_id = trim($params["ref"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $user_id = $result["id"];
        $db->where("user_id", $user_id);
        $db->where("address_type", "personal");
        $db->where("active", 1);
        $user_address = $db->getOne("xun_crypto_user_address");
        if(!$user_address){
              return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00093') /*Record not found.*/);
        }

        $address = $user_address["address"];

        $transaction_token = $general->generateApiKey($username);
        $expires_at = date("Y-m-d H:i:s", strtotime('+1 year', strtotime($now)));

        $insertData = array(
            "user_id" => $user_id,
            "address" => $address,
            "transaction_token" => $transaction_token,
            "reference_id" => $reference_id,
            "expires_at" => $expires_at,
            "verified" => '0',
            "created_at" => $now,
            "updated_at" => $now
        );

        $row_id = $db->insert("xun_crypto_user_transaction_verification", $insertData);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00070') /*Transaction Token.*/, "transaction_token" => $transaction_token);
    }

    public function get_app_crypto_transaction_token_v2($params)
    {
        $db = $this->db;
        $general = $this->general;
        $now = date("Y-m-d H:i:s");

        $username = trim($params["username"]);
        $reference_id = trim($params["ref"]);
        $address = trim($params["internal_address"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        if ($address == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00314') /*address cannot be empty.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("address", $address);
        $db->where("active", 1);
        $db->where("address_type", "personal");
        $user_address = $db->getOne("xun_crypto_user_address");
        if(!$user_address){
              return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00093') /*Record not found.*/);
        }

        $user_id = $user_address["user_id"];
        $address = $user_address["address"];

        $transaction_token = $general->generateApiKey($username);
        $expires_at = date("Y-m-d H:i:s", strtotime('+1 year', strtotime($now)));

        $insertData = array(
            "user_id" => $user_id,
            "address" => $address,
            "transaction_token" => $transaction_token,
            "reference_id" => $reference_id,
            "expires_at" => $expires_at,
            "verified" => '0',
            "created_at" => $now,
            "updated_at" => $now
        );

        $row_id = $db->insert("xun_crypto_user_transaction_verification", $insertData);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00070') /*Transaction Token.*/, "transaction_token" => $transaction_token);
    }

    private function validate_password($password)
    {
        // if (preg_match("/^.*(?=.{4,})(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).*$/", $password) === 0) {
        // $error_message = array("- Minimum 4 characters", "- At least 1 alphabet", "- At least 1 numeric", "- At least 1 capital letter");
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid password combination.", "developer_msg" => "business_password has an invalid character combination", "error_message" => $error_message);
        // }

        $length = strlen($password);

        if ($length < 8) {
            $error_message = array("- Minimum 8 characters");
            return array('code' => 0, "error_message" => $error_message);
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            return array('code' => 1, "hashed_password" => $hashed_password);
        }
    }

    private function compose_chat_room_preference($result)
    {
        $general = $this->general;

        $returnData["uuid"] = (string) $result["id"];
        $returnData["user_username"] = $result["username"];
        $returnData["chat_room_id"] = $result["chat_room_id"] ? $result["chat_room_id"] : "";
        $returnData["tag"] = $result["tag"] ? $result["tag"] : "";
        $returnData["ringtone"] = $result["ringtone"] ? $result["ringtone"] : "";
        $returnData["mute_validity"] = $result["mute_validity"] ? $general->formatDateTimeToIsoFormat($result["mute_validity"]) : "";
        $returnData["show_notification"] = is_null($result["show_notification"]) ? "" : $result["show_notification"];
        $returnData["created_date"] = $result["created_at"] ? $general->formatDateTimeToIsoFormat($result["created_at"]) : "";
        $returnData["modified_date"] = $result["updated_at"] ? $general->formatDateTimeToIsoFormat($result["updated_at"]) : "";

        return $returnData;
    }

    public function get_employee_old_id($business_id, $mobile)
    {
        $new_mobile = str_replace("+", "", $mobile);

        $employee_id = $business_id . "_" . $new_mobile;
        return $employee_id;
    }

    private function send_xmpp_business_tag_event($business_id, $tag, $new_employee_list, $removed_employee_list, $subscriber_jid_list)
    {
        global $xunXmpp;

        $erlangReturn = $xunXmpp->send_xmpp_business_tag_event($business_id, $tag, $new_employee_list, $removed_employee_list, $subscriber_jid_list);

        return $erlangReturn;
    }

    public function delete_business_employee($business_id, $employee_id_arr, $mobile = null, $employee_role = null)
    {
        global $config;
        global $xunXmpp;
        $db = $this->db;
        $employee_server = $config["erlang_server"];

        $now = date("Y-m-d H:i:s");

        if (gettype($employee_id_arr) == 'string') {
            $employee_id_arr = array($employee_id_arr);
        }

        $removed_employee_list = array();

        foreach ($employee_id_arr as $employee_id) {
            // update xun_employee
            $employee_mobile = "";

            if (!$mobile) {
                $db->where("business_id", $business_id);
                $db->where("old_id", $employee_id);
                $db->where("status", 1);
                $xun_employee = $db->getOne("xun_employee");
                $employee_mobile = $xun_employee["mobile"];
                $employee_role = $xun_employee["role"];
            } else {
                $employee_mobile = $mobile;
            }

            $updateData = [];
            $updateData["status"] = 0;
            $updateData["updated_at"] = $now;
            $db->where("business_id", $business_id);
            $db->where("old_id", $employee_id);
            $db->where("status", 1);
            $db->update("xun_employee", $updateData);

            $employee["employee_mobile"] = $employee_mobile;
            $employee["employee_id"] = $employee_id;
            $removed_employee_list[] = $employee;

            $db->where("employee_id", $employee_id);
            $db->where("status", 1);
            $xun_business_tag_employee = $db->get("xun_business_tag_employee");

            // send xmpp event for each tag

            foreach ($xun_business_tag_employee as $tag_employee) {
                // loop tag_employee
                $final_remove_employee_list = [];
                $subscriber_jid_list = [];

                $tag = $tag_employee["tag"];

                $remove_employee_mobile = $employee_mobile;
                $remove_employee_server = $employee_server;
                $remove_employee_role = $employee_role;

                $remove_employee_arr = array(
                    'employee_mobile' => $remove_employee_mobile,
                    'employee_server' => $remove_employee_server,
                    'employee_role' => $remove_employee_role,
                );

                $final_remove_employee_list[] = $remove_employee_arr;

                $db->where("business_id", $business_id);
                $db->where("tag", $tag);
                $db->where("status", 1);
                $all_business_tag_employee = $db->get("xun_business_tag_employee");

                foreach ($all_business_tag_employee as $tag_employee) {
                    $aa = $tag_employee["username"] . "@" . $employee_server;
                    $subscriber_jid_list[] = $tag_employee["username"] . "@" . $employee_server;
                }

                // call erlang here - per tag
                $erlangReturnArr[] = $xunXmpp->send_xmpp_business_tag_event($business_id, $tag, [], $final_remove_employee_list, $subscriber_jid_list);

            }

            $updateTagEmployee["status"] = 0;
            $updateTagEmployee["updated_at"] = $now;

            $db->where("employee_id", $employee_id);
            $db->where("status", 1);
            $db->update("xun_business_tag_employee", $updateTagEmployee);
        }

        $newParams["business_id"] = (string) $business_id;
        $newParams["employee_list"] = $removed_employee_list;

        $erlangReturn = $xunXmpp->send_xmpp_remove_employee_event($newParams);
        return $erlangReturn;
    }

    public function business_employee_tag_add($params)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        $business_id = trim($params["business_id"]);
        $tag = trim($params["tag"]);
        $description = trim($params["tag_description"]);
        $employee_mobile_list = $params["employee_mobile"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        };

        if ($tag == '') {
            $return_message = $this->get_translation_message('E00003');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00003') /*Tag cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $employee_tag = $db->getOne("xun_business_employee_tag");

        if ($employee_tag) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00131') /*This tag has already been created. Please choose another name.*/);
        }

        $fields = array("business_id", "tag", "description", "status", "created_at", "updated_at");
        $values = array($business_id, $tag, $description, 1, $now, $now);
        $arrayData = array_combine($fields, $values);
        $db->insert("xun_business_employee_tag", $arrayData);

        $db->where("business_id", $business_id);
        $db->where("role", "owner");

        $owner_employee = $db->getOne("xun_employee");

        $new_employee_list = [];
        if ($owner_employee) {
            $owner_mobile = $owner_employee["mobile"];

            //combine onwer mobile and moblie list
            if (empty($employee_mobile_list)) {
                $employee_mobile_list = [];
                $employee_mobile_list[] = $owner_mobile;
            } else {
                $employee_mobile_list[] = $owner_mobile;
                $new_employee_list = $employee_mobile_list;
            }
        }

        $new_employee_list = $employee_mobile_list;

        $new_employee_list = array_values(array_filter(array_unique($new_employee_list)));

        foreach ($new_employee_list as $employee_mobile) {
            $db->where("mobile", $employee_mobile);
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $db->where("employment_status", "confirmed");
            $xun_employee = $db->getOne("xun_employee");

            if (!$xun_employee) {
                continue;
            }

            $employee_id = $xun_employee["old_id"];

            $fields = array("business_id", "tag", "employee_id", "username", "status", "created_at", "updated_at");
            $values = array($business_id, $tag, $employee_id, $employee_mobile, 1, $now, $now);
            $arrayData = array_combine($fields, $values);
            $db->insert("xun_business_employee_tag_employee", $arrayData);
        }

        return array('code' => 1, 'message' => "Success", 'message_d' => $this->get_translation_message('B00062') /*New category successfully added.*/);
    }

    public function business_employee_tag_edit($params)
    {

        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        $business_id = trim($params["business_id"]);
        $tag = trim($params["tag"]);
        $description = trim($params["tag_description"]);
        $employee_mobile_list = $params["employee_mobile"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        };

        if ($tag == '') {
            $return_message = $this->get_translation_message('E00003');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00003') /*Tag cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $employee_tag = $db->getOne("xun_business_employee_tag");

        if (!$employee_tag) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00080') /*This record does not exist.*/);
        }

        if (!$employee_mobile_list) {
            $employee_mobile_list = [];
        }

        $employee_mobile_list = array_values(array_filter(array_unique($employee_mobile_list)));

        $updateData = [];
        $updateData["description"] = $description;
        $updateData["updated_at"] = $now;

        $db->where("id", $employee_tag["id"]);
        $db->update("xun_business_employee_tag", $updateData);

        // update xun_business_employee_tag_employee
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);

        $employee_tag_employee_list = $db->getValue("xun_business_employee_tag_employee", "username", null);

        // rempve owner from list
        $db->where("business_id", $business_id);
        $db->where("role", "owner");
        $db->where("status", 1);
        $owner_employee = $db->getOne("xun_employee");
        $owner_mobile = $owner_employee["mobile"];

        $initial_employee_mobile = $employee_tag_employee_list ? $employee_tag_employee_list : array();

        $initial_employee_mobile_without_owner = array_values(array_diff($initial_employee_mobile, array($owner_mobile)));

        $new_employee_list = array_diff($employee_mobile_list, $initial_employee_mobile_without_owner);
        $removed_employee_list = array_diff($initial_employee_mobile_without_owner, $employee_mobile_list);

        $this->add_business_employee_tag_employee($business_id, $tag, $new_employee_list);

        foreach ($removed_employee_list as $employee_mobile) {
            $db->where("username", $employee_mobile);
            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->where("status", 1);

            $ete_rec = $db->getOne("xun_business_employee_tag_employee");
            if ($ete_rec) {
                $updateData = [];
                $updateData["updated_at"] = $now;
                $updateData["status"] = 0;
                $db->where("id", $ete_rec["id"]);
                $db->update("xun_business_employee_tag_employee", $updateData);
            }
        }

        return array("code" => 1, "message" => "Success", "message_d" => $this->get_translation_message('B00061') /*Category details updated.*/);
    }

    private function add_business_employee_tag_employee($business_id, $tag, $new_employee_list)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        foreach ($new_employee_list as $employee_mobile) {
            $db->where("mobile", $employee_mobile);
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $db->where("employment_status", "confirmed");
            $xun_employee = $db->getOne("xun_employee");

            if (!$xun_employee) {
                continue;
            }

            $employee_id = $xun_employee["old_id"];

            $fields = array("business_id", "tag", "employee_id", "username", "status", "created_at", "updated_at");
            $values = array($business_id, $tag, $employee_id, $employee_mobile, 1, $now, $now);
            $arrayData = array_combine($fields, $values);
            $db->insert("xun_business_employee_tag_employee", $arrayData);
        }
    }

	public function get_business_tag_listing($params){

        $db = $this->db;

        $business_id = $params["business_id"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $xun_business_employee_tag = $db->get("xun_business_employee_tag");

        $arr_employee_tag = array();
        foreach($xun_business_employee_tag as $employee_tag) {
            $arr_employee_tag[] = $employee_tag["tag"];
        }


        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->orderBy("priority", "ASC");
        $xun_business_tag = $db->get("xun_business_tag");

        $arr_tag = array();
        foreach($xun_business_tag as $tag) {
            $arr_tag[] = $tag["tag"];
        }

        $arr_result["live_chat"] = $arr_tag;
        $arr_result["business_chat"] = $arr_employee_tag;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00181') /*Business tag listing*/, "business_id" => $business_id, "result" => $arr_result);
    }

    public function business_employee_tag_list($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $xun_business_employee_tag = $db->get("xun_business_employee_tag");

        foreach ($xun_business_employee_tag as $data) {
            $tag_description = $data["description"] ? $data["description"] : "";
            $tag = $data["tag"];
            $created_at = $data["created_at"];

            $number_employee_tag_rec = $db->rawQuery("SELECT count(*) as total_member FROM xun_business_employee_tag_employee as bte JOIN xun_employee as xe on bte.employee_id = xe.id or bte.employee_id = xe.old_id WHERE bte.business_id = '" . $business_id . "' and xe.status = '1'  and bte.status = '1' and tag = '" . $tag . "' and role = 'employee' and xe.employment_status = 'confirmed'");

            $total_employee = $number_employee_tag_rec[0]["total_member"];

            $returnData[] = array(
                "business_id" => $business_id,
                "tag_description" => $tag_description,
                "tag" => $tag,
                "created_date" => $created_at,
                "total_members" => $total_employee,
            );
        }

        $sort = array();
        foreach ($returnData as $key => $row) {
            $sort[$key] = $row['priority'];
        }
        array_multisort($sort, SORT_ASC, $returnData);

        if (is_null($returnData)) {
            $returnData = [];
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00071') /*Business chat category listing.*/, "business_id" => $business_id, "result" => $returnData);
    }

    public function business_employee_tag_get_details($params)
    {
        $db = $this->db;

        $business_id = $params["business_id"];
        $tag = $params["tag"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($tag == '') {
            $return_message = $this->get_translation_message('E00007');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00007') /*Business tag cannot be empty.*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $result = $db->getOne("xun_business_employee_tag");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00133') /*Business category not found.*/);
        }

        $tag = $result["tag"];
        $tag_description = $result["description"];
        $created_date = $result["created_at"];

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $employee_result = $db->get("xun_business_employee_tag_employee");

        $employees = array();
        $db->where("role", "owner");
        $db->where("status", 1);
        $db->where("employment_status", "confirmed");
        $db->where("business_id", $business_id);
        $owner_employee = $db->getOne("xun_employee");
        $owner_mobile = $owner_employee ? $owner_employee["mobile"] : '';

        foreach ($employee_result as $employee_data) {
            $employees[] = $employee_data["username"];
        }

        if ($owner_mobile) {
            $employees = array_values(array_diff($employees, array($owner_mobile)));
        }
        $returnData["business_id"] = $business_id;
        $returnData["tag"] = $tag;
        $returnData["employee_mobile"] = $employees;
        $returnData["created_date"] = $result["created_at"];
        $returnData["tag_description"] = $tag_description;

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00010') /*Category details.*/, "business_id" => $business_id, "result" => $returnData);
    }

    public function business_employee_tag_delete($params)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        $business_id = $params["business_id"];
        $tag_list = $params["tag"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($tag_list == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00116') /*Tag list cannot be empty.*/);
        }

        if (!is_array($tag_list)) {
            $tag_list = [$tag_list];
        }

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        $updateData = [];
        $updateData["status"] = 0;
        $updateData["updated_at"] = $now;

        foreach ($tag_list as $tag) {
            $db->where("business_id", $business_id);
            $db->where("tag", $tag);
            $db->where("status", 1);
            $employee_tag = $db->getOne("xun_business_employee_tag");

            if ($employee_tag) {
                $db->where("id", $employee_tag["id"]);
                $db->update("xun_business_employee_tag", $updateData);

                $db->where("business_id", $business_id);
                $db->where("tag", $tag);
                $db->update("xun_business_employee_tag_employee", $updateData);
            }
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00060') /*Categories has been deleted.*/);
    }

    public function business_employee_tag_delete_all($params)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        $business_id = $params["business_id"];

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        $updateData = [];
        $updateData["status"] = 0;
        $updateData["updated_at"] = $now;

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->update("xun_business_employee_tag", $updateData);

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->update("xun_business_employee_tag_employee", $updateData);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00060') /*Categories has been deleted.*/);

    }

    public function business_send_employee_message($params)
    {
        /*
         *  -   if tag is not defined, it acts as an open tag
         *  -   recipients: business employees that are not bounded to any
         *      tag will receive the message
         *
         *  -   if tag is defined:
         *  -   ecipiemts: business employees that are bounded to
         *      the tag as well as employees that are not bounded to
         *      any tag.
         **/

        $db = $this->db;
        global $xunXmpp;

        $now = date("Y-m-d H:i:s");

        $business_id = trim($params["business_id"]);
        $api_key = trim($params["api_key"]);
        $tag = trim($params["tag"]);
        $message = trim($params["message"]);

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => FAILED, 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, 'developer_msg' => "business_id cannot be empty");
        };

        if ($tag == '') {
            $return_message = $this->get_translation_message('E00003');
            return array('code' => 0, 'message' => FAILED, 'message_d' => $this->get_translation_message('E00003') /*Tag cannot be empty*/, 'developer_msg' => "tag cannot be empty");
        };

        if (strlen($tag) >= 25) {
            $tag = substr($tag, 0, 25);
        };

        if ($message == '') {
            $return_message = $this->get_translation_message('E00004');
            return array('code' => 0, 'message' => FAILED, 'message_d' => $this->get_translation_message('E00004') /*Message cannot be empty.*/, 'developer_msg' => "message cannot be empty");
        };

        if (strlen($message) >= 3550) {
            $message = substr($message, 0, 3550);
        }

        if ($api_key == '') {
            return array('code' => 0, 'message' => FAILED, 'message_d' => $this->get_translation_message('E00086') /*Api key cannot be empty.*/, 'developer_msg' => "api_key cannot be empty");
        };

        //checks for valid business id
        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00021') /*The business ID you've entered is incorrect.*/);
        }

        //checks for valid api key (exists or within date)
        $db->where("apikey", $api_key);
        $db->where("is_enabled", 1);
        $db->where("status", "active");
        $xun_api_key = $db->getOne('xun_business_api_key');
        if ($xun_api_key) {
            $DateNow = time();

            $EndDate = $xun_api_key['apikey_expire_datetime'];
            $END_Date = strtotime($EndDate);

            if ($DateNow >= $END_Date) {
                $return_message = $this->get_translation_message('E00024');
                // "This API key has expired. Please use a valid API key.
                return array('code' => 0, 'message' => FAILED, 'message_d' => $return_message);
            };
        } else {
            $return_message = $this->get_translation_message('E00023');
            return array('code' => 0, 'message' => FAILED, 'message_d' => $return_message);
        }

        // get employee list
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $xun_business_employee_tag_employee = $db->get("xun_business_employee_tag_employee");

        $recipient_list = [];
        $recipient_mobile_list = [];

        foreach ($xun_business_employee_tag_employee as $employee_tag_employee) {
            $employee_username = $employee_tag_employee["username"];
            $employee_confirmed = $this->is_employee_confirmed($employee_username, $business_id);

            if ($employee_confirmed) {
                $employee_info = [];
                $employee_info["username"] = $employee_username;
                $employee_info["recipient_jid"] = $xunXmpp->get_livechat_jid($employee_tag_employee["employee_id"]);
                $recipient_list[] = $employee_info;
                $recipient_mobile_list[] = $employee_username;
            }
        }

        // select employee that does not have business chat tag
        $employee_without_tag = $this->get_employee_without_employee_tag($business_id);
        // $employee_without_tag = array_intersect($xun_employee, $xun_employee2);

        foreach ($employee_without_tag as $employee_mobile) {
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $db->where("mobile", $employee_mobile);
            $employee_id = $db->getValue("xun_employee", "old_id");
            if ($employee_id) {
                $employee_info = [];
                $employee_info["username"] = $employee_mobile;
                $employee_info["recipient_jid"] = $xunXmpp->get_livechat_jid($employee_id);
                $recipient_list[] = $employee_info;
                $recipient_mobile_list[] = $employee_mobile;
            }
        }

        $owner = $this->get_business_owner($business_id);

        if ($owner) {
            $owner_mobile = $owner["mobile"];
            if (!in_array($owner_mobile, $recipient_mobile_list)) {
                $employee_info = [];
                $employee_info["username"] = $owner_mobile;
                $employee_info["recipient_jid"] = $xunXmpp->get_livechat_jid($employee_tag_employee["employee_id"]);
                $recipient_list[] = $employee_info;
                $recipient_mobile_list[] = $owner_mobile;
            }
        }

        $sender_jid = $xunXmpp->get_user_jid($business_id);

        //rebuild params for erlang side
        $newParams["business_id"] = $business_id;
        $newParams["tag"] = $tag;
        $newParams["message"] = $message;
        $newParams["sender_jid"] = $sender_jid;
        $newParams["recipients"] = $recipient_list;

        $fields = array("data", "message_type", "created_at", "updated_at");
        $values = array(json_encode($newParams), "business_employee", $now, $now);

        $insertData = array_combine($fields, $values);
        $db->insert("xun_business_sending_queue", $insertData);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00002') /*Messages sent.*/, "result" => array("recipients" => $recipient_mobile_list));

    }

    public function get_business_owner($business_id)
    {
        $db = $this->db;

        $db->where("business_id", $business_id);
        $db->where("role", "owner");
        $db->where("status", 1);

        $owner = $db->getOne("xun_employee");
        return $owner;
    }
    public function get_employee_without_livechat_tag($business_id)
    {
        // select employees that does not have any tag (livechat tag )
        $db = $this->db;

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->groupBy("username");
        $xun_business_tag_employee = $db->getValue("xun_business_tag_employee", "username", null);

        if ($xun_business_tag_employee) {
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $db->where("employment_status", "confirmed");
            $db->where("mobile", $xun_business_tag_employee, "not in");
            $xun_employee = $db->getValue("xun_employee", "mobile", null);
        } else {
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $db->where("employment_status", "confirmed");
            $xun_employee = $db->getValue("xun_employee", "mobile", null);
        }

        $xun_employee = $xun_employee ? $xun_employee : [];
        return $xun_employee;
    }

    public function get_employee_without_employee_tag($business_id)
    {
        // select employees that does not have any tag (business chat tag )
        $db = $this->db;

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->groupBy("username");
        $xun_business_employee_tag_employee = $db->getValue("xun_business_employee_tag_employee", "username", null);

        if ($xun_business_employee_tag_employee) {
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $db->where("employment_status", "confirmed");
            $db->where("mobile", $xun_business_employee_tag_employee, "not in");
            $xun_employee = $db->getValue("xun_employee", "mobile", null);
        } else {
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $db->where("employment_status", "confirmed");
            $xun_employee = $db->getValue("xun_employee", "mobile", null);
        }

        $xun_employee = $xun_employee ? $xun_employee : [];
        return $xun_employee;
    }

    public function cryptocurrency_live_price_listing($params)
    {
        global $setting;
        // Name, Price, Market Cap, Change (24Hr)"
        $db      = $this->db;
        $general = $this->general;
        $page_limit = $setting->systemSetting["cryptocurrencyLivePricePageLimit"];

        $sort_column           = trim($params["sort_column"]);
        $page_number           = trim($params["page"]);
        $page_size             = trim($params["page_size"]);
        $order                 = strtoupper(trim($params["order"]));
        $currency              = trim($params["currency"]);

        $page_size = $page_size ? $page_size : $page_limit;

        $order = ($order == 'ASCENDING' ? "ASC" : ($order == 'DESCENDING' ? "DESC" : "ASC"));

        if ($currency) {
            $db->where("name", "%$currency%", "LIKE");
        }

        if ($page_number < 1) {
            $page_number = 1;
        }

        switch($sort_column){
            case "name": 
                $sort_column = "name";
                break;
            case "price": 
                $sort_column = "value";
                break;
            case "change_24h":
                $sort_column = "price_change_percentage_24h";
                break;
            case "market_cap_value":
                $sort_column = "market_cap";
                break;
            case "market_cap_rank":
                $sort_column = "market_cap_rank";
                break;
            default:
                $sort_column = "market_cap_rank";
                break;
        };

        $db->where("live_price", 1);
        $db->where("market_cap_rank", 0, '>');

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $copyDb = $db->copy();
        $db->orderBy($sort_column, $order);
        $result = $db->get("xun_cryptocurrency_rate", $limit, 'id, name, cryptocurrency_id, image, value, price_change_percentage_24h, market_cap, market_cap_rank');

        $return_message = $this->get_translation_message('B00158');/*Cryptocurrency live price listing*/
        $result = $result ? $result : array();

        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_cryptocurrency_rate", "count(id)");
        
        $returnData["result"] = $result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $return_message, 'data' => $returnData);
    }

    public function get_news_post_listing($params){
        global $setting;

        $db      = $this->db;
        $general = $this->general;
        $page_limit = $setting->systemSetting["memberBlogPageLimit"];

        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order                 = $params["order"] ? $params["order"] : "DESC";

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        $db->where("status", 1);
        $db->where("type", "news_update");

        $copyDb = $db->copy();
        $db->orderBy("created_at", $order);
        $result = $db->get("xun_blog_post", $limit, 'id, title, content_type, SUBSTR(content, 1, 500) as content, source, url_name, redirect_url, meta_title, meta_description, created_at, updated_at');
        $return_message = $this->get_translation_message('B00159'); /*News Listing*/
        $result = $result ? $result : array();

        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_blog_post", "count(id)");
        
        $returnData["result"] = $result;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $return_message, 'data' => $returnData);

    }

    public function get_blog_post_listing($params){
        global $setting;

        $db      = $this->db;
        $general = $this->general;
        $page_limit = $setting->systemSetting["memberBlogPageLimit"];

        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order                 = $params["order"] ? $params["order"] :"DESC";
        $title                 = $params["title"];
        $tag                   = $params["tag"];

        if ($page_number < 1) {
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);
        if($title){
            $title = "%$title%";
            $db->where('title' , $title, 'like');
            
        }

        if($tag){
            $tag = "%$tag%";
            $db->where('tag', $tag, 'like');
        }
 
        $db->where("status", 1);
        $db->where("type", 'new_blog');

        $copyDb = $db->copy();
        $db->orderBy("created_at", $order);
        $result = $db->get("xun_blog_post", $limit, 'id, title, media_type, SUBSTR(content, 1, 500) as content, tag,media_url, meta_title, meta_description, created_at, updated_at');
        $return_message = $this->get_translation_message('B00160'); /*Blog listing.*/
        //$result = $result ? $result : array();

        foreach($result as $data){
            if($data["media_type"] == "video"){
                $videoData = array(
                    "id" => $data["id"],
                    "title" => $data["title"],
                    "media_type" => $data["media_type"],
                    "tag" => $data["tag"],
                    "video_url" => $data["media_url"],
                    "created_at" => $data["created_at"],
                    "updated_at" => $data["updated_at"],
                );
                $return[] = $videoData;
            }
            elseif($data["media_type"] == "article"){
                $articleData = array(
                    "id" => $data["id"],
                    "title" => $data["title"],
                    "media_type" => $data["media_type"],
                    "tag" => $data["tag"],
                    "content" => $data["content"],
                    "image_url" => $data["media_url"],
                    "meta_title" => $data["meta_title"],
                    "meta_description" => $data["meta_description"],
                    "created_at" => $data["created_at"],
                    "updated_at" => $data["updated_at"],

                );
                $return[] = $articleData;
            }
        }

        //totalPage, pageNumber, totalRecord, numRecord
        $totalRecord = $copyDb->getValue("xun_blog_post", "count(id)");
        
        $returnData["result"] = $return;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $return_message, 'data' => $returnData);

    }

    public function get_news_post_content($params){
        $db = $this->db;

        $record_id = trim($params["id"]);

        if ($record_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00006') /*ID cannot be empty.*/);
        };

        $db->where("id", $record_id);
        $db->where("status", 1);
        $blog_post = $db->getOne("xun_blog_post");

        if(!$blog_post){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00080') /*This record does not exist.*/);
        }

        unset($blog_post["status"]);

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00161')/*News content.*/, 'data' => $blog_post);
    }

    public function get_article_post_content($params){
        $db = $this->db;

        $record_id = trim($params["id"]);

        if ($record_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00006') /*ID cannot be empty.*/);
        };

        $db->where("id", $record_id);
        $db->where("status", 1);
        $blog_post = $db->getOne("xun_blog_post");

        if(!$blog_post){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00080') /*This record does not exist.*/);
        }

        $tag = explode(",",$blog_post["tag"]);
        $relatedArticle = array();
        
        
        foreach($tag as $data){
            $data = "%$data%";
            $db->orWhere('tag', $data, 'like');
            $db->where('media_type', "article");
            $db->where('id', $record_id, '!=');  
            
        }
         $db->orderBy("created_at" ,"DESC");

        $result = $db->get ("xun_blog_post", null, "id,media_type,title, tag,media_url,content, meta_title, meta_description, created_at, updated_at");
        
        $relatedArticle [] = $result; 

        $returnData["article_content"] = $blog_post;
        $returnData["related_article"] = $result;

        unset($blog_post["status"]);

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00162') /*Article content.*/, 'data' => $returnData);
    }

    public function get_qr_details($params){
        global $xunCurrency, $config;

        $db = $this->db;

        $qr = trim($params["qr"]);

        if ($qr == ''){
        return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00277') /*Invalid QR code*/);
        }

        $decoded_qr = base64_decode($qr);
     
        parse_str($decoded_qr, $decoded_arr);
        $address = $decoded_arr["address"];

        $return_data = [];

        if(empty($address)){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00277') /*Invalid QR code*/);
        }

        // get from external address
        $db->where("address", $address);
        $db->orderBy("id", "DESC");
        $address_data = $db->getOne("xun_crypto_user_external_address", "id, user_id, address, amount, wallet_type, description, subject");

        $amount = $decoded_arr["amount"];

        if(!$address_data){
        return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00278') /*Invalid address*/);
        }

        // get user nickname, usd equivalent, wallet info
        $user_id = $address_data["user_id"];
        $db->where("id", $user_id);
        $xun_user = $db->getOne("xun_user", "id, username, nickname");
        
        $nickname = $xun_user["nickname"] ? $xun_user["nickname"] : "";

        $wallet_type = $address_data["wallet_type"];

        $currency_id = $wallet_type == "bitcoincash" ? "bitcoin-cash" : $wallet_type;
        $db->where("cryptocurrency_id", $currency_id);
        $crypto_data = $db->getOne("xun_cryptocurrency_rate", "id, name, cryptocurrency_id, image, value");

        if(!$crypto_data){
            $db->where("currency_id", $currency_id);
            $marketplace_currency = $db->getOne("xun_marketplace_currencies", "currency_id, fiat_currency_id, type, image, symbol");

            if($marketplace_currency && $marketplace_currency["fiat_currency_id"] != ''){
                $db->where("currency", $currency_id);
                $currency_data = $db->getOne("xun_currency_rate", "currency, exchange_rate");

                $exchange_rate = $currency_data["exchange_rate"];
                $rate = bcdiv("1", (string)$exchange_rate, 2);
            }
            $image = $marketplace_currency["image"];
            $symbol = $marketplace_currency["symbol"];
            $coin_name = $marketplace_currency["name"];
        }else{
            $image = $crypto_data["image"];
            $coin_name = $crypto_data["name"];
            $rate = $crypto_data["value"];
            switch($currency_id){
                case "bitcoin":
                    $symbol = "btc";
                    break;

                case "ethereum":
                    $symbol = "eth";
                    break;

                case "ripple":
                    $symbol = "xrp";
                    break;
                
                default:
                    $symbol = '';
                    break;
            }
        }

        if(!empty($amount)){
            $usd_value = bcmul((string)$amount, (string)$rate, 2);
        }

        $encode_data = array(
            "address" => $address,
            "amount" => $amount,
            "description" => $address_data["description"],
            "subject" => $address_data["subject"],
            "wallettype" => $address_data["wallet_type"]
        );

        $query_string = http_build_query($encode_data);
        $decoded_data = base64_encode($query_string);

        $server = $config["server"];
        $server_url = "https://" . $server . '/qr?qr=';

        $return_data["qr_code"] = $server_url . $decoded_data;
        $return_data["usd_value"] = $usd_value ? $usd_value : '';
        $return_data["usd_unit"] = 'USD';
        $return_data["address"] = $address;
        $return_data["amount"] = $amount;
        $return_data["amount_unit"] = strtoupper($symbol);
        $return_data["description"] = $address_data["description"];
        $return_data["subject"] = $address_data["subject"];
        $return_data["wallet_type"] = $address_data["wallet_type"];
        $return_data["wallet_image"] = $image ? $image : '';
        $return_data["username"] = $nickname ? $nickname : '';
    
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00163') /*QR details*/, "data" => $return_data);
    }

    public function app_business_register($params){
        global $config;

        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $business_email = trim($params["business_email"]);
        $password = trim($params["business_password"]);
        $password_retype = trim($params["business_password_retype"]);
        $business_name = trim($params["business_name"]);
        $business_profile_picture = $params["business_profile_picture"];

        // Param validations
        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }
        if ($business_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00015') /*Business name cannot be empty.*/);
        }

        if ($password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00014') /*Password cannot be empty*/);
        }

        if ($password_retype == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00014') /*Password cannot be empty*/);
        }

        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        if ($password != $password_retype){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00280') /*Password does not match.*/, "developer_msg" => "password does not match.");
        }

        if(strlen($business_name) > 25){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00281') /*Business name must be at most 25 characters.*/, "developer_msg" => "password does not match.");
        }
        // Password validation
        $validate_password = $this->validate_password($password);

        if ($validate_password['code'] == 0) {
            $error_message = $validate_password['error_message'];
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00042') /*Invalid password combination.*/, "developer_msg" => "business_password has an invalid character combination", "error_message" => $error_message);

        }

        $hashed_password = $validate_password["hashed_password"];

        $business_profile_picture_base64 = "";
        if(!empty($business_profile_picture["binval"]) && !empty($business_profile_picture["type"])){
            //  data:image/jpeg;base64,/9j/4AAQ
            $image_binval = $business_profile_picture["binval"];
            $image_type = $business_profile_picture["type"];
            $business_profile_picture_base64 = "data:" . $image_type . ";base64," . $image_binval;
        }

        // need to get to model
        // business service -> business model
        $xunBusinessService = new XunBusinessService($db);
        $result = $xunBusinessService->createBusinessApp($username, $business_email, $business_name, $hashed_password, $business_profile_picture_base64);

        if($result["code"] === 0){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message($result["message_code"]) /*An account already exists with this email. Please select another email address.*/);
        }

        $business_id = $result["business_id"];

        //  init xmpp related actions
        $businessObj = new stdClass();
        $businessObj->businessEmail = $business_email;
        $businessObj->businessName = $business_name;
        $businessObj->businessID = $result["business_id"];
        $businessObj->businessFollowID = $result["business_follow_id"];
        $businessObj->employeeID = $result["employee_id"];
        $businessObj->ownerMobile = $username;
        $businessObj->businessProfilePicture = $business_profile_picture_base64;

        $erlang_server = $config["erlang_server"];
        $erlang_return = $this->xmpp_create_business($businessObj, $erlang_server);

        $returnData = array(
            "business_id" => (string)$business_id
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00013') /*Business successfully registered.*/, "data" => $returnData);
    }


    public function app_business_register_v1($params){
        global $config, $setting, $xunCurrency, $xunCrypto;

        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $business_email = trim($params["business_email"]);
        $password = trim($params["business_password"]);
        $password_retype = trim($params["business_password_retype"]);
        $business_name = trim($params["business_name"]);
        $business_profile_picture = $params["business_profile_picture"];
        $reward_symbol = strtolower(trim($params['reward_symbol']));
        $token_fiat_currency_id = strtolower(trim($params['token_fiat_currency_id']));
        $token_ratio = $params['token_ratio'];
        $fiat_ratio = $params['fiat_ratio'];
        $card_background_url = $params['card_background_url'];
        $font_color = $params['font_color'];

        $date = date("Y-m-d H:i:s");

        // Param validations
        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }
        if ($business_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00015') /*Business name cannot be empty.*/);
        }

        if ($password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00014') /*Password cannot be empty*/);
        }

        if ($password_retype == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00014') /*Password cannot be empty*/);
        }

        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        if ($password != $password_retype){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00280') /*Password does not match.*/, "developer_msg" => "password does not match.");
        }

        if(strlen($business_name) > 25){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00281') /*Business name must be at most 25 characters.*/, "developer_msg" => "password does not match.");
        }

        if($reward_symbol == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00459') /*Reward Symbol cannot be empty.*/, "developer_msg" => "reward symbol cannot be empty.");
        }

        if($token_fiat_currency_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00460') /*Fiat Currency ID cannot be empty.*/, "developer_msg" => "fiat currency id cannot be empty.");
        }

        if($token_ratio == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00462') /*Token Ratio cannot be empty.*/, "developer_msg" => "Token Ratio cannot be empty.");
        }

        if($fiat_ratio == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00463') /*Fiat Ratio cannot be empty.*/, "developer_msg" => "Fiat Ratio cannot be empty.");
        }
        
        if($card_background_url == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00464') /*Card Background URL cannot be empty.*/, "developer_msg" => "Card Background Url cannot be empty.");
        }

        if($font_color == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00469') /*Font Color cannot be empty.*/, "developer_msg" => "Font Color cannot be empty.");
        }

        if($token_ratio <=0){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00479') /*Token ratio cannot be less than 0.*/, "developer_msg" => "Token ratio cannot be less than 0.");
        }

        if($fiat_ratio <=0){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00480') /*Fiat Ratio cannot be less than 0.*/, "developer_msg" => "Fiat Ratio cannot be less than 0.");
        }

        if(strlen($reward_symbol) != 3){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00467') /*Symbol must be 3 characters*/, "developer_msg" => "Symbol must be 3 characters.");
        }

        if(preg_match('/\s/', $reward_symbol)){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00483') /*Reward symbol cannot contain whitespace.*/, "developer_msg" => "reward_symbol cannot contain whitespace.");
        }
        $verify_fiat_result = $xunCurrency->verify_fiat_currency($token_fiat_currency_id);

        if(!$verify_fiat_result){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00482') /*Fiat Currency not supported.*/, "developer_msg" => "Fiat Currency not supported.");
        }

        // Password validation
        $validate_password = $this->validate_password($password);

        if ($validate_password['code'] == 0) {
            $error_message = $validate_password['error_message'];
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00042') /*Invalid password combination.*/, "developer_msg" => "business_password has an invalid character combination", "error_message" => $error_message);

        }

        $hashed_password = $validate_password["hashed_password"];

        $business_profile_picture_base64 = "";
        if(!empty($business_profile_picture["binval"]) && !empty($business_profile_picture["type"])){
            //  data:image/jpeg;base64,/9j/4AAQ
            $image_binval = $business_profile_picture["binval"];
            $image_type = $business_profile_picture["type"];
            $business_profile_picture_base64 = "data:" . $image_type . ";base64," . $image_binval;
        }

        // need to get to model
        // business service -> business model
        $xunBusinessService = new XunBusinessService($db);
        $result = $xunBusinessService->createBusinessApp($username, $business_email, $business_name, $hashed_password, $business_profile_picture_base64);

        if($result["code"] === 0){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message($result["message_code"]) /*An account already exists with this email. Please select another email address.*/);
        }

        $business_id = $result["business_id"];

        //  init xmpp related actions
        $businessObj = new stdClass();
        $businessObj->businessEmail = $business_email;
        $businessObj->businessName = $business_name;
        $businessObj->businessID = $result["business_id"];
        $businessObj->businessFollowID = $result["business_follow_id"];
        $businessObj->employeeID = $result["employee_id"];
        $businessObj->ownerMobile = $username;
        $businessObj->businessProfilePicture = $business_profile_picture_base64;

        $erlang_server = $config["erlang_server"];
        $erlang_return = $this->xmpp_create_business($businessObj, $erlang_server);

        $total_supply = $setting->systemSetting['theNuxRewardTotalSupply'];
        $reference_price = bcdiv($fiat_ratio, $token_ratio, '8');
        $businessObj->rewardSymbol = $reward_symbol;
        $businessObj->fiatCurrencyID = $token_fiat_currency_id;
        $businessObj->totalSupply = $total_supply;
        $businessObj->referencePrice = $reference_price;
        $businessObj->cardBackgroundUrl = $card_background_url;
        $businessObj->fontColor = $font_color;
        $businessObj->type = "reward";

        $rewardTokenDecimalPlaces = $setting->systemSetting['rewardTokenDecimalPlaces'];
        $fiat_currency_arr = array($token_fiat_currency_id);
        $fiat_currency_price = $xunCurrency->get_latest_fiat_price($fiat_currency_arr);
        
        $fiat_currency_value = $fiat_currency_price[$token_fiat_currency_id]['exchange_rate'];
        $usd_value = bcdiv(1, $fiat_currency_value, '8');
        $value = bcmul($usd_value, $reference_price, '8');

        $business_coin_id = $xunBusinessService->createBusinessCoin($businessObj);
        
        $xun_user_service = new XunUserService($db);
        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00202')/*User does not exist.*/);
        }
        $user_id = $xun_user['id'];

        // default add business owner as follower
        $insert_user_coin = array(
            "user_id" => $user_id,
            "business_coin_id" => $business_coin_id,
            "created_at" => $date
        );

        $user_coin_id = $db->insert("xun_user_coin", $insert_user_coin);
        if(!$user_coin_id){
            return array("code" => 0,  "message" => "FAILED", "message_d" => $this->get_translation_message('E00341') /*Something went wrong. Please try again.*/, "error_message" => $db->getLastError() );
        }

        $crypto_user_address = $xun_user_service->getActiveAddressByUserIDandType($user_id, "personal");

        $internal_address = $crypto_user_address['address'];

        $coin_name_prefix = $setting->systemSetting["rewardCoinNamePrefix"];
        $new_token_params = array(
            "name" => $coin_name_prefix . $business_name,
            "symbol" => $reward_symbol,
            "decimalPlaces" => $rewardTokenDecimalPlaces,
            "totalSupply" => $total_supply,
            "totalSupplyHolder" => $internal_address,
            "exchangeRate" => array(
                "usd" => $value,
                $token_fiat_currency_id => $reference_price,
            ),
            "referenceID" => $business_coin_id
        );

        $return = $xunCrypto->add_reward_token($new_token_params);

        if($return['status'] == 'error'){
            return array("code" => 0, "message" => "FAILED", "message_d" => $return['statusMsg']);
        }
        
        $returnData = array(
            "business_id" => (string)$business_id
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00013') /*Business successfully registered.*/, "data" => $returnData);
    }

    public function app_business_edit_details($params){
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $business_website = trim($params["business_website"]);
        $business_phone_number = trim($params["business_phone_number"]);
        $business_address1 = trim($params["business_address1"]);
        $business_address2 = trim($params["business_address2"]);
        $business_city = trim($params["business_city"]);
        $business_state = trim($params["business_state"]);
        $business_postal = trim($params["business_postal"]);
        $business_country = trim($params["business_country"]);
        $business_info = trim($params["business_info"]);
        $business_company_size = trim($params["business_company_size"]);
        $business_email_address = trim($params["business_email_address"]);
        $contact_us_url = trim($params["contact_us_url"]);

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $xunBusinessService = new XunBusinessService($db);

        $business_data = $xunBusinessService->getBusinessDetails($business_id);

        if (!$business_data) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }
        $business_owner = $this->get_business_owner($business_id);

        if(!$business_owner || $username != $business_owner["mobile"]){
        // if($username != $business_data["main_mobile"]){
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00268') /*You're not allowed to edit properties of this business.*/);
        }

        $businessUserID = $business_data["user_id"];
        $businessObj = new stdClass();
        $businessObj->userID = $businessUserID;
        $businessObj->website = $business_website;
        $businessObj->phone_number = $business_phone_number;
        $businessObj->address1 = $business_address1;
        $businessObj->address2 = $business_address2;
        $businessObj->city = $business_city;
        $businessObj->state = $business_state;
        $businessObj->postal = $business_postal;
        $businessObj->country = $business_country;
        $businessObj->info = $business_info;
        $businessObj->company_size = $business_company_size;
        $businessObj->display_email = $business_email_address;
        $businessObj->contact_us_url = $contact_us_url;

        $xunBusinessService->updateBusinessDetails($businessObj);

        $this->send_business_update_profile_message($business_id, "details");

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00026') /*Business profile updated.*/);
    }

    public function app_business_update_image($params){
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $business_profile_picture = $params["business_profile_picture"];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $xunBusinessService = new XunBusinessService($db);

        $business_data = $xunBusinessService->getBusinessDetails($business_id);

        if (!$business_data) {
            return array('code' => 0, 'message' => FAILED, 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $business_owner = $this->get_business_owner($business_id);

        if(!$business_owner || $username != $business_owner["mobile"]){
        return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00268') /*You're not allowed to edit properties of this business.*/);
        }

        $business_profile_picture_base64 = "";
        if(!empty($business_profile_picture["binval"]) && !empty($business_profile_picture["type"])){
            //  data:image/jpeg;base64,/9j/4AAQ
            $image_binval = $business_profile_picture["binval"];
            $image_type = $business_profile_picture["type"];
            $business_profile_picture_base64 = "data:" . $image_type . ";base64," . $image_binval;
        }

        $uploadImageRet = $xunBusinessService->updateBusinessProfilePicture($business_id, $business_profile_picture_base64);

        $this->update_business_profile_picture($business_id, $business_profile_picture_base64);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00029') /*Updated business profile picture.*/);

    }

    public function get_erlang_server()
    {
        global $config;
        return $config["erlang_server"];
    }

    public function get_return_error_json($error_message, $data_arr = null)
    {
        $return_arr = array('code' => 0, 'message' => FAILED, 'message_d' => $error_message);
        if ($data_arr) {
            $return_arr = array_merge($return_arr, $data_arr);
        }
        return $return_arr;
    }

    public function get_translation_message($message_code)
    {
        // Language Translations.
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;
    }

    public function is_employee_confirmed($employee_username, $business_id)
    {
        global $xunErlang;

        $xun_employee = $xunErlang->is_employee_confirmed($employee_username, $business_id);
        return $xun_employee;
    }

    private function xmpp_create_business($businessObj, $erlang_server){
        global $xunXmpp;

        $post = $this->post;
        $general = $this->general;

        $business_email = $businessObj->businessEmail;
        $business_id = $businessObj->businessID;
        $business_name = $businessObj->businessName;
        $employee_id = $businessObj->employeeID;
        $business_follow_id = $businessObj->businessFollowID;
        $owner_mobile = $businessObj->ownerMobile;
        $business_profile_picture = $businessObj->businessProfilePicture;

        $default_business_tag = "General";

        $xmpp_password = $general->generateAlpaNumeric(8);

        $xmpp_message = "Email: " . $business_email . "\nBusiness Name: " . $business_name . "\n";

        $erlang_params["business_id"] = (string) $business_id;
        $erlang_params["business_name"] = $business_name;
        $erlang_params["message_tag"] = "New Business Registration";
        $erlang_params["message"] = $xmpp_message;
        $erlang_params["business_follow_id"] = (string)$business_follow_id;
        $erlang_params["mobile"] = $owner_mobile;
        $erlang_params["employee_id"] = (string) $employee_id;
        $erlang_params["password"] = $xmpp_password;
        $erlang_params["default_tag"] = $default_business_tag;
        $erlang_params["user_server"] = $erlang_server;
        $erlang_params["profile_picture"] = $business_profile_picture;

        $erlangReturn = $post->curl_post("business/register", $erlang_params);

        if ($erlangReturn["code"] == 1) {
            $xunXmpp->create_xmpp_user($business_id, $erlang_server, $xmpp_password);
        }
        return $erlangReturn;
    }

    private function update_business_profile_picture($business_id, $business_profile_picture){
        $post = $this->post;
        //upload and get url from s3

        //call api to update vcard
        //call api to send iq and update message
        $updateProfilePicture["business_id"] = $business_id;
        $updateProfilePicture["profile_picture"] = $business_profile_picture;

        $url_string = "business/vcard/image/update";
        $erlangUpdatePicture = $post->curl_post($url_string, $updateProfilePicture);
        $erlangReturn = $this->send_business_update_profile_message($business_id, "image");

        return $erlangReturn;
    }

    // xun/app/business/callback_url/update
    public function set_business_callback_url($params)
    {   
        $db = $this->db;
        $date = date("Y-m-d H:i:s");
        
        $username = trim($params["username"]);
        $callback_url = trim($params["callback_url"]);
        $business_id = trim($params["business_id"]);
        
        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        
        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("id", $business_id);
		$db->where("disabled", 0);
        $xun_business = $db->getOne("xun_user");
        
        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }
        
        if ($callback_url != '') {
            if (!filter_var($callback_url, FILTER_VALIDATE_URL)) {
            return array('code' => 0, 'message' => "FAILED", "errorCode" => -100, 'message_d' => $this->get_translation_message('E00288') /*Please enter a valid URL.*/);
            }
        }


        $db->where("user_id", $business_id);
        $db->where("name", "businessCallbackURL");
		$user_setting = $db->getOne("xun_user_setting");

		if ($user_setting) {

	        $update_data = [];
    	    $update_data["value"] = $callback_url;
        	$update_data["updated_at"] = $date;

            $db->where("user_id", $business_id);
	        $db->update("xun_user_setting", $update_data);

		} else {

			$fields = array("user_id", "name", "value");
            $values = array($business_id, "businessCallbackURL", $callback_url);
            $arrayData = array_combine($fields, $values);
            $db->insert("xun_user_setting", $arrayData);

		}
       
    return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00169') /*Callback URL Updated.*/);
    }

	// xun/app/business/callback_url/get
    public function get_business_callback_url($params)
    {   
        $db = $this->db;
        
        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        
        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        
        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("id", $business_id);
        $db->where("disabled", 0);
        $xun_business = $db->getOne("xun_user");

        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }


        $db->where("user_id", $business_id);
		$db->where("name", "businessCallbackURL");
        $user_setting = $db->getOne("xun_user_setting");
        
        if (!$user_setting) {
            $callback_url = "";
        } else {
            $callback_url = $user_setting["value"];
        }
        
        $returnData["callback_url"] = $callback_url;
        
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00170') /*Callback URL.*/, "data" => $returnData);
    }

	//xun/app/business/weblogin
	public function business_web_login($params, $ip, $user_agent) {
        global $xunPaymentGateway;
		$db = $this->db;
		$post = $this->post;

        $username = trim($params["username"]);
        $raw_url = trim($params["raw_url"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($raw_url == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00289') /*Raw URL cannot be empty.*/);
        }

        $db->where("id", $business_id);
        $db->where("disabled", 0);
        $xun_business = $db->getOne("xun_user");

        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }else {
			$nickname = $xun_user["nickname"];
			$user_id = $xun_user["id"];
		}


        $db->where("user_id", $business_id);
        $db->where("name", "businessCallbackURL");
        $xun_setting = $db->getOne("xun_user_setting");

        $xunPaymentGateway->update_user_setting($business_id, $ip, $user_agent);

        if (!$xun_setting) {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00290') /*Callback URL does not exist.*/);
        } else {
			
			$callback_url = $xun_setting["value"];

			if ($callback_url == '') {
            	return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00290') /*Callback URL does not exist.*/);
            } else {


				$db->where("mobile_number", $username);
                $xun_device = $db->getOne("xun_user_device");

                if ($xun_device) {
                	$device_os = $xun_device["os"] == 2 ? "IOS" : "Android";
                    $device_model = $xun_device["device_model"];
                    $os_version = $xun_device["os_version"];
                    $app_version = $xun_device["app_version"];
                }

                $db->where("user_id", $user_id);
                $db->where("name", "lastLoginIP");
                $ip = $db->getValue("xun_user_setting", "value");

                $new_params = [];
                $new_params["raw_url"] = $raw_url;
                $new_params["mobile"] = $username;
                $new_params["nickname"] = $nickname;
                $new_params["device_os"] = $device_os;
                $new_params["device_model"] = $device_model;
                $new_params["os_version"] = $os_version;
                $new_params["app_version"] = $app_version;
				$new_params["ip_address"] = $ip;

                $post_return = $post->curl_post($callback_url, $new_params, 0, 1);
                $result_status = strtolower($post_return["status"]);
                $result_message = $post_return["message"];

                if($result_status == "ok") {
                	if($result_message == "") {
                    return array("code" => 1, "message" => "You've logged in successfully", "message_d" => $this->get_translation_message('B00173')/*You've logged in successfully*/);
                    } else {
                        return array("code" => 1, "message" => $result_message, "message_d" => $result_message);
                    }
                } else {
	                if($result_message == "") {
                    	return array("code" => 0, "message" => "Failed to login", "message_d" => $this->get_translation_message('E00292') /*Failed to login*/);
                    } else {
                        return array("code" => 0, "message" => $result_message, "message_d" => $result_message);
                    }

                }

			}
		}

    }

    public function verify_web_login($params) {

	$db = $this->db;

        $login_code = trim($params["login_code"]);

        if ($login_code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Login code cannot be empty.");
        }


	$db->where('created_at', date("Y-m-d H:i:s", strtotime("-5  minutes")), '>=');
	$db->where('login', 0);
	$db->where('token', $login_code);
        $result = $db->getOne('web_login_detail');

	if ($result) {

            $update_web_login = [];
            $update_web_login["login_at"] = date("Y-m-d H:i:s");
            $update_web_login["login"] = 1;

            $db->where("id", $result["id"]);
	    $db->update("web_login_detail", $update_web_login);

            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Success", "user_detail" => array("mobile"=>$result["mobile"], "nickname"=>$result["nickname"]));
        } else {
	    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid login code.");
	}


    }

    public function app_attempt_login($params) {

	$db = $this->db;

	$username = trim($params["username"]);
	$business_id = trim($params["business_id"]);
	$return_url = urldecode(trim($params["return_url"]));

	if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty.");
	}

	if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business ID cannot be empty.");
	}

	if ($return_url == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Return URL cannot be empty.");
        }

	$db->where("username", $username);
	$xun_user = $db->getOne("xun_user", "id, username, nickname");

	if(!$xun_user) {
	    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid user.");
	} else {

	    //generate login code
            $flag = true;
            while ($flag) {

                $random_number = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $value = substr(str_shuffle($random_number), 0, 32);

                $db->where('token', $value);
                $result = $db->get('web_login_detail');

                if (!$result) {
                    $flag = false;
                    $login_code = $value;
                }
            }

	    if (strpos($return_url, "?") !== false) {
    		$returnUrl = $return_url."&login_code=".$login_code;
	    } else {
		$returnUrl = $return_url."?login_code=".$login_code;
	    }

	    $web_login_detail = array(
		"token" => $login_code,
		"business_id" => $business_id,
		"user_id" => $xun_user["id"],
                "mobile" => $xun_user["username"],
                "nickname" => $xun_user["nickname"],
                "return_url" => $returnUrl,
                "created_at" => date("Y-m-d H:i:s")
            );

	    $db->insert("web_login_detail", $web_login_detail);

	    return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Success.", "return_url"=>$returnUrl);

	}
    }

    public function get_user_country_info($username_arr){
        $db = $this->db;
        $general = $this->general;

        if(!is_array($username_arr)){
            $username_arr = [$username_arr];
        }
        
        $username_arr_len = count($username_arr);
        $region_code_arr = [];
        $user_country_arr = [];
        for($i = 0; $i < $username_arr_len; $i++){
            $mobileNumberInfo = $general->mobileNumberInfo($username_arr[$i], null);
            $region_code = $mobileNumberInfo["regionCode"];

            $region_code_arr[] = $region_code;
            $user_country_arr[$username_arr[$i]] = $region_code;
        }
        
        if (!empty($region_code_arr)){
            $region_code_arr = array_unique($region_code_arr);
            $db->where("iso_code2", $region_code_arr, "in");
            $country_arr = $db->map("iso_code2")->ObjectBuilder()->get("country");
        }

        $final_arr = array();
        foreach($user_country_arr as $key => $value){
            $final_arr[$key] = (array)$country_arr[$value];
        }

        return $final_arr;
    }

    public function change_business_owner($params){
        global $config;
        $db = $this->db;
        $post = $this->post;

        $business_id = trim($params["business_id"]);
        $new_owner_username = trim($params["new_owner_username"]);

        $db->where("username", $new_owner_username);
        $xun_user = $db->getOne("xun_user", "id, username, nickname");

        if(!$xun_user){
        return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00295') /*Invalid user.*/);
        }

        $db->where("user_id", $business_id);
        $xun_business_account = $db->getOne("xun_business_account", "id, user_id, main_mobile");
        
        if(!$xun_business_account){
        return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00296') /*Invalid business account.*/);
        }

        $current_owner_username = $xun_business_account["main_mobile"];

        $update_xun_business_account_data = [];
        $update_xun_business_account_data["main_mobile"] = $new_owner_username;

        $db->where("id", $xun_business_account["id"]);
        $db->update("xun_business_account", $update_xun_business_account_data);

        $db->where("business_id", $business_id);
        $db->where("role", "owner");

        $business_owner = $db->getOne("xun_employee", "id, mobile, old_id");

        $owner_employee_old_id = $this->get_employee_old_id($business_id, $new_owner_username);

        $date = date("Y-m-d H:i:s");

        $server_host = $config["erlang_server"];

        if($business_owner){
            $current_employee_old_id = $business_owner["old_id"];
            $update_employee_data = [];
            $update_employee_data["status"] = '0';
    

            $db->where("id", $business_owner["id"]);
            $db->update("xun_employee", $update_employee_data);
        }

        $db->where("business_id", $business_id);
        $db->where("mobile", $new_owner_username);
        $new_owner_employee_data = $db->getOne("xun_employee");
        if($new_owner_employee_data){
            $update_employee_data = [];
            $update_employee_data["status"] = 1;
            $update_employee_data["employment_status"] = "confirmed";
            $update_employee_data["role"] = "owner";

            $db->where("id", $new_owner_employee_data["id"]);
            $db->update("xun_employee", $update_employee_data);
        }
        else{
            $insert_employee_data = array(
                "business_id" => $business_id,
                "mobile" => $new_owner_username,
                "name" => $xun_user["nickname"],
                "status" => 1,
                "employment_status" => "confirmed",
                "created_at" => $date,
                "updated_at" => $date,
                "old_id" => $owner_employee_old_id,
                "role" => "owner"
            );

            $db->insert("xun_employee", $insert_employee_data);
        }
        //call api to send new employee message

        $newParams["business_id"] = $business_id;
        $newParams["employee_mobile"] = $new_owner_username;
        $newParams["employee_id"] = $owner_employee_old_id;
        $newParams["employee_role"] = "owner";

        $erlangReturn = $post->curl_post("business/employee/add", $newParams);

        $update_tag_employee_data = [];
        $update_tag_employee_data["employee_id"] = $owner_employee_old_id;
        $update_tag_employee_data["username"] = $new_owner_username;

        if($current_employee_old_id){
            $db->where("employee_id", $current_employee_old_id);
            $db->where("status", 1);
            $business_tag_list = $db->get("xun_business_tag_employee");

            // $db->where("employee_id", $current_employee_old_id);
            // $db->update("xun_business_tag_employee", $update_tag_employee_data);

            $new_employee_list = [];
            $new_employee_list[] = array('employee_mobile' => $new_owner_username,
            'employee_server' => $server_host, 'employee_role' => "owner");

            foreach($business_tag_list as $data){
                $tag = $data["tag"];

                $db->where("tag", $tag);
                $db->where("business_id", $business_id);
                $db->where("username", $new_owner_username);
                $tag_employee_data = $db->getOne("xun_business_tag_employee");

                if($tag_employee_data && $tag_employee_data["status"] == 0){
                    $update_data = [];
                    $update_data["status"] = 1;
                    $update_data["updated_at"] = $date;

                    $db->where("id", $tag_employee_data["id"]);
                    $db->update("xun_business_tag_employee", $update_data);
                }else if(!$tag_employee_data){
                    $insert_data = array(
                        "employee_id" => $owner_employee_old_id,
                        "username" => $new_owner_username,
                        "business_id" => $business_id,
                        "tag" => $tag,
                        "created_at" => $date,
                        "updated_at" => $date,
                        "status" => 1
                    );

                    $db->insert("xun_business_tag_employee", $insert_data);
                }

                $update_data = [];
                $update_data["status"] = 0;
                $db->where("id", $data["id"]);
                $db->update("xun_business_tag_employee", $update_data);

                $db->where("tag", $tag);
                $db->where("business_id", $business_id);
                $db->where("status", 1);
                $tag_employee_list = $db->getValue("xun_business_tag_employee", "username", null);

                $subscriber_list = [];
                foreach ($tag_employee_list as $value) {
                    $employee_username = $value . "@" . $server_host;
                    $subscriber_list[] = $employee_username;
                }
                
                $removed_employee_list = [];
                $erlangReturn = $this->send_xmpp_business_tag_event($business_id, $tag, $new_employee_list, $removed_employee_list, $subscriber_list);
            }
        }

        $db->where("business_id", $business_id);
        $db->where("username", $new_owner_username);
        $follow_business_data = $db->getOne("xun_business_follow", "id, business_id, username");

        if(!$follow_business_data){
            $insert_follow_business_data = array(
                "business_id" => $business_id,
                "username" => $new_owner_username,
                "server_host" => $server_host,
                "created_at" => $date,
                "updated_at" => $date
            );

            $db->insert("xun_business_follow", $insert_follow_business_data);
        }
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/, "erlang_return" => $erlangReturn);
    }

public function get_business_encrypted_wallet_private_key($params) {

        $db = $this->db;
        global $config;

        $server_host = $config["erlang_server"];
        $crypto_host = "crypto." . $server_host;

        $username = trim($params["username"]);
        $employee_id = trim($params["employee_id"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($employee_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00011') /*Employee ID cannot be empty*/);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("old_id", $employee_id);
        $db->where("status", 1);
        $xun_employee = $db->getOne("xun_employee");

        $share_mode = "";
        $is_share_key = false;

        if (!$xun_employee) {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00297') /*Employee does not exist.*/);
        } else {
            $share_mode = $xun_employee["share_mode"];
            $is_share_key = $xun_employee["is_share_key"];
        }


        $db->where("business_id", $business_id);
        $db->where("role", "owner");
        $xun_employee_owner = $db->getOne("xun_employee");

        $business_owner_mobile = "";

        if ($xun_employee_owner) {
            $business_owner_mobile = $xun_employee_owner["mobile"];
        }


        $db->where("key_user_id", $employee_id);
        $db->where("key_host", $crypto_host);
        $db->where("status", 1);
        $business_wallet_encrypted_key = $db->getOne("xun_public_key");

        $encrypted_key = $business_wallet_encrypted_key["key"] ? $business_wallet_encrypted_key["key"] : "";

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00174') /*Encrypted wallet key*/, "encrypted_private_key" => $encrypted_key, "business_owner_id" => $business_owner_mobile, "share_mode" => $share_mode, "is_share_key" => $is_share_key);

    }

    public function update_business_encrypted_wallet_private_key($params) {

        $db = $this->db;
        global $config;

        $server_host = $config["erlang_server"];
        $crypto_host = "crypto." . $server_host;

        $username = trim($params["username"]);
        $employee_id = trim($params["employee_id"]);
        $encrypted_wallet_key = trim($params["encrypted_wallet_key"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($employee_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00011') /*Employee ID cannot be empty*/);
        }

        if ($encrypted_wallet_key == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00299') /*Encrypted wallet key cannot be empty.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("old_id", $employee_id);
        $db->where("status", 1);
        $xun_employee = $db->getOne("xun_employee");

        if (!$xun_employee) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00297') /*Employee does not exist.*/);
        }


        $db->where("key_user_id", $employee_id);
        $db->where("key_host", $crypto_host);
        $db->where("status", 1);
        $business_wallet_encrypted_key = $db->getOne("xun_public_key");

        $created_at = date("Y-m-d H:i:s");

        if (!$business_wallet_encrypted_key) {

            $fields = array("old_id", "key_user_id", "key_host", "key", "status", "created_at", "updated_at");
            $values = array($employee_id, $employee_id, $crypto_host, $encrypted_wallet_key, 1, $created_at, $created_at);
            $arrayData = array_combine($fields, $values);
            $db->insert("xun_public_key", $arrayData);

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00175') /*Encrypted wallet key updated.*/);

        } else {

            $row_id = $business_wallet_encrypted_key["id"];

            $updateData["key"] = $encrypted_wallet_key;
            $updateData["updated_at"] = $created_at;
            $db->where("id", $row_id);
            $db->update("xun_public_key", $updateData);

            return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00175') /*Encrypted wallet key updated.*/);
        }

    }

    public function app_generate_api_key($params) {

        $db = $this->db;
        $general = $this->general;

        $username = $params["username"];
        $business_id = $params["business_id"];
        $expire_date = $params["apikey_expiry_date"];
        $apikey_name = $params["apikey_name"];

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($expire_date == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00085') /*Api key expiry date cannot be empty.*/);
        }

        $created_at = date("Y-m-d H:i:s");
        $apikey_is_enabled = "1";

        $db->where('username', $username);
        $db->where('disabled', 0);
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        //validate the business id got record in xun_business
        $db->where('user_id', $business_id);
        $check_business = $db->getOne('xun_business');
        if (empty($check_business)) {
            return array('code' => 0, 'message' => FAILED, 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/, 'developer_msg' => "");
        };

        //generate the apikey
        $flag = true;
        while ($flag) {

            $random_number = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $value = substr(str_shuffle($random_number), 0, 32);

            $db->where('apikey', $value);
            $result = $db->get('xun_business_api_key');

            if (!$result) {

                $flag = false;
                $api_key = $value;
            }
        }

        if($apikey_name == '') {
            $apikey_name = $api_key;
        }

		$expire_date = $expire_date." 23:59:59";
        $fields = array("apikey", "business_id", "apikey_name", "apikey_expire_datetime", "is_enabled", "created_at");
        $values = array($api_key, $business_id, $apikey_name, $expire_date, $apikey_is_enabled, $created_at);
        $arrayData = array_combine($fields, $values);
        $db->insert("xun_business_api_key", $arrayData);

        $api_list = array(
            "api_key" => $api_key,
            "apikey_name" => $apikey_name,
            "business_id" => $business_id,
            "apikey_expire_date" => $general->formatDateTimeToIsoFormat($expire_date),
            "apikey_is_enabled" => $apikey_is_enabled,
            "apikey_created_date" => $general->formatDateTimeToIsoFormat($created_at)
        );

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00037') /*Business API key created.*/, "result" => $api_list);

    }

    public function app_update_apikey($params) {

        $db = $this->db;

        $username = $params["username"];
        $business_id = $params["business_id"];
        $api_key = $params["api_key"];
        $apikey_name = $params["apikey_name"];
        $apikey_expiry_date = $params["apikey_expiry_date"];
		$apikey_is_enabled = $params["apikey_is_enabled"] ? $params["apikey_is_enabled"] : false;

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($api_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00086') /*Api key cannot be empty.*/);
        }

        if ($apikey_name == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00301') /*Api name cannot be empty*/);
        }

        if ($apikey_expiry_date == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00302') /*Expiry date cannot be empty.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->get("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $now = date("Y-m-d H:i:s");

        $db->where("apikey", $api_key);
        $db->where("business_id", $business_id);

        $api_key_record = $db->getOne("xun_business_api_key");

        if (!$api_key_record) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00044') /*Invalid record.*/);
        }

		$apikey_expiry_date = $apikey_expiry_date." 23:59:59";
        $updateData = [];
		$updateData["is_enabled"] = $apikey_is_enabled;
        $updateData["apikey_expire_datetime"] = $apikey_expiry_date;
        $updateData["apikey_name"] = $apikey_name;
        $updateData["updated_at"] = $now;
        $db->where("apikey", $api_key);
        $db->update("xun_business_api_key", $updateData);

    return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00176') /*Business API Key updated.*/);

    }

    public function app_delete_apikey($params) {

        $db = $this->db;

        $username = $params["username"];
        $business_id = $params["business_id"];
        $api_key = $params["api_key"];

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($api_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00086') /*Api key cannot be empty.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->get("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $now = date("Y-m-d H:i:s");

        $db->where("apikey", $api_key);
        $db->where("business_id", $business_id);

        $api_key_record = $db->getOne("xun_business_api_key");

        if (!$api_key_record) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00044') /*Invalid record.*/);
        }

        $updateData = [];
        $updateData["status"] = "deleted";
        $updateData["updated_at"] = $now;
        $db->where("apikey", $api_key);
        $db->update("xun_business_api_key", $updateData);

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00040') /*Business API key revoked.*/);

    }

    public function app_api_key_listing($params) {

        $db = $this->db;
        $general = $this->general;

        $username = $params["username"];
        $business_id = $params["business_id"];

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $db->where('username', $username);
        $xun_user = $db->getOne('xun_user');
        if (!$xun_user) {
            return array('code' => 0, 'message' => 'FAILED', 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if ($business_id == '') {
            $return_message = $this->get_translation_message('E00002');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where('business_id', $business_id);
        $db->where('status', 'active');
        $result = $db->get('xun_business_api_key');

        $xun_business_api_key = [];
        if ($result) {

            foreach ($result as $key) {

                $businessID = $key['business_id'];
                $apikey = $key['apikey'];
                $apikey_name = $key['apikey_name'];
                $apikey_expire_date = $key['apikey_expire_datetime'];
                $apikey_is_enabled = $key['is_enabled'];
                $created_datetime = $key['created_at'];

                $api_list[] = array(
                    "api_key" => $apikey,
                    "apikey_name" => $apikey_name,
                    "business_id" => $businessID,
                    "apikey_expire_date" => $general->formatDateTimeToIsoFormat($apikey_expire_date),
                    "apikey_is_enabled" => $apikey_is_enabled,
                    "apikey_created_date" => $general->formatDateTimeToIsoFormat($created_datetime),
                );

            }
            $xun_business_api_key = $api_list;

        }
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00038') /*Business API key listing.*/, "xun_business_api_key" => $xun_business_api_key);

    }

    public function business_request_money($params){
        global $xunCurrency, $xunXmpp;

        $db = $this->db;
        $general = $this->general;
        $language = $general->getCurrentLanguage();
        $translations = $general->getTranslations();

        $business_id = trim($params["business_id"]);
        $api_key = trim($params["api_key"]);
        $username = trim($params["username"]);
        $amount = trim($params["amount"]);
        $wallet_type = trim($params["currency"]);
        $destination_address = trim($params["destination_address"]);
        $reference_id = trim($params["reference_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($api_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00086') /*Api key cannot be empty.*/);
        }

        if ($amount == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00305') /*Amount cannot be empty.*/);
        }

        if ($wallet_type == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00306') /*Currency cannot be empty.*/);
        }

        if ($destination_address == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00307') /*Destination address cannot be empty.*/);
        }

        if ($reference_id == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00308') /*Reference ID cannot be empty.*/);
        }

        $xun_business_service = new XunBusinessService($db);
        if (!$xun_business_service->validateApiKey($business_id, $api_key)) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => $translations['E00148'][$language]/*Invalid Apikey.*/);
        }

        $wallet_type = strtolower($wallet_type);

        if(!$xunCurrency->is_supported_currency($wallet_type)){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00310') /*Invalid currency.*/, "errorCode" => -100);
        }

        if(bccomp((string)$amount, "0", 8) < 1){
        return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00311') /*Please enter a valid amount.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }


        $business_obj = new stdClass();
        $business_obj->businessID = $business_id;
        $business_obj->username = $username;
        $business_obj->amount = $amount;
        $business_obj->walletType = $wallet_type;
        $business_obj->destinationAddress = $destination_address;
        $business_obj->referenceID = $reference_id;

        $row_id = $xun_business_service->insertBusinessRequestMoney($business_obj);

        if($row_id){
            //  call xmpp, pass business id, amount, wallet type, dest address, id
            $xun_business = $xun_business_service->getBusinessDetails($business_id);
            $business_name = $xun_business["name"];

            $erlang_params = array(
                "business_id" => $business_id,
                "business_name" => $business_name,
                "id" => (string)$row_id,
                "username" => $username,
                "amount" => (string)$amount,
                "wallet_type" => $wallet_type,
                "destination_address" => $destination_address
            );

            $xunXmpp->send_business_request_money_message($erlang_params);
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);
        }else{
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00141'][$language]/*"Internal server error. Please try again.")*/);
        }
    }

    public function business_request_money_callback($business_id, $params)
    {
        $post = $this->post;
        $db = $this->db;

        $xun_business_service = new XunBusinessService($db);

        $transaction_hash = $params["transaction_hash"];
        $reference_id = $params["reference_id"];

        $business_user_data = $xun_business_service->getUserByID($business_id);
        $wallet_callback_url = $business_user_data["wallet_callback_url"];

        if($wallet_callback_url){
            // post
            $callback_params = array(
                "transaction_hash" => $transaction_hash,
                "reference_id" => $reference_id
            );

            $post_result = $post->curl_crypto("requestMoneyCallback", $callback_params, 0, $wallet_callback_url);
        }
        return $post_result;
    }

    public function business_my_followers($params){
        $db = $this->db;
        $post = $this->post;
        $general = $this->general;

        global $xunCrypto, $setting;

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number           = $params["page"];
        $page_size             = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order                 = $params["order"] ? $params["order"] :"DESC";
        $business_id = trim($params['business_id']);
        $phone_number = trim($params['mobile']);
        $from_date = trim($params['from_date']);
        $to_date = trim($params['to_date']);

        if ($page_number < 1) {
            $page_number = 1;
        }
        //Get the limit.
        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $db->where("business_id", $business_id);
        $business_coin_info = $db->getOne("xun_business_coin"); 
        if (!$business_coin_info){
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        }
        $business_coin_id = $business_coin_info['id'];
        $business_coin_wallet_type = strtolower($business_coin_info['wallet_type']);

        if ($from_date){
            $from_date = date("Y-m-d H:i:s", $from_date);
            $db->where("b.created_at", $from_date, ">=");
        }
        if ($to_date){
            $to_date = date("Y-m-d H:i:s", $to_date);
            $db->where("b.created_at", $to_date, "<=");
        }
        if ($phone_number){
            $phone_number = "%$phone_number%";
            $db->where("a.username", $phone_number, "LIKE");
        }

        $db->orderBy("b.created_at", $order);
        $db->where("b.business_coin_id", $business_coin_id);
        $db->where("a.type", "user");
        $db->join('xun_user a', 'a.id= b.user_id', 'LEFT');
        $copyDb = $db->copy();
        $follower_array = $db->get("xun_user_coin b", $limit, "b.*, a.username, a.nickname");

        if (!$follower_array){
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        }
        foreach ($follower_array as $follower){
            $follower_username[] = $follower['username'];
            $follower_since[$follower['username']] = $follower['created_at'];
            $user_ids[] = $follower['user_id'];
        }
        
        $totalRecord = $copyDb->getValue("xun_user_coin b", "count(b.id)");

        // $db->where("username", $follower_username, "IN");
        // $user = $db->get("xun_user", null, "id, username as phone, nickname");

        // get user_ids
        // foreach($user as $x){
        //     $user_ids[] = $x['id'];
        // }

        // print_r($db->getLastQuery());
        // get business_coin_id from xun_business_coin

        $db->where("currency_id", $business_coin_wallet_type);
        $unit_conversion = $db->getValue("xun_coins", "unit_conversion");

        $db->where('user_id', $business_id);
        $db->where('address_type', 'reward');
        $db->where('active', 1);
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        $company_pool_address = $crypto_user_address['external_address'];

        // get internal address by using user_ids
        if(!empty($user_ids)){
            $db->where("user_id", $user_ids, "IN");
            $db->where("active", "1");
            $db->where("address_type", "personal");
            $internal_address = $db->get("xun_crypto_user_address", null, "id, user_id, address");
        }else{
            $internal_address = [];
        }

        // business_get_wallet_info
        foreach($internal_address as $user_address){
            foreach($user_address as $key => $value){
                if ($key == "user_id"){
                    $user_id = $value;
                    // print_r($value);
                }
                if ($key == "address"){
                    $wallet_info[$user_id] = $xunCrypto->get_wallet_info($value);
                }
            }
        }

        if(!empty($user_ids)){
            $db->where("a.address_type", "reward");
            $db->where("a.wallet_type", $business_coin_wallet_type);
            $db->where("a.sender_user_id", $user_ids, "IN");
           // $db->where("a.sender_user_id", array("283", "11391"), "IN");
            $db->where("a.status", "completed");
            $db->where('b.reference_address', $company_pool_address, '!=');
            $db->where('b.type', 'send');
            $db->orderBy("a.updated_at", "DESC");
            $db->join('xun_crypto_callback b', 'a.transaction_hash = b.transaction_hash', 'LEFT');
            $redeem_info = $db->get("xun_wallet_transaction a", null, "a.user_id, a.sender_user_id, a.amount, a.updated_at, b.reference_address, b.type");
        }
        // if (!$redeem_info){
        //     return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        // }

        // print_r($redeem_info);
        $redeem_array = [];
        if($redeem_info){
            foreach($redeem_info as $redeem){
                $redeem_array[$redeem['sender_user_id']][] = $redeem;
            }
            unset($redeem);
        }
    
        $total_amount = 0;

        foreach($follower_array as $x){
            $user_id = $x['user_id'];
            foreach($x as $key => $value){
                if ($key == "user_id"){
                    $id = $value;
                    $return_data[$id]['user_id'] = $x['user_id'];
                    $return_data[$id]['phone'] = $x['username'];
                    $return_data[$id]['name'] = $x['nickname'];
                    // print_r($id);
                    $return_data[$id]['reward_balance'] = bcdiv((string)$wallet_info[$id][$business_coin_wallet_type]["balance"], (string)$unit_conversion, "8");
                    $return_data[$id]['total_redeemed'] = "";
                    foreach($redeem_array[$id] as $redeem){
                        $return_data[$id]['total_redeemed'] += $redeem["amount"];
                    }
                    $return_data[$id]['last_redeem_date'] = $redeem_array[$id][0]['updated_at'] ? $redeem_array[$id][0]['updated_at'] : '';
                    $return_data[$id]['last_redeem'] = $redeem_array[$id][0]['amount'] ? $redeem_array[$id][0]['amount'] : '';
                }
                if ($key == "username"){
                    $return_data[$id]['follow_since'] = $follower_since[$value];
                }

            }
        }

        $return_data = array_values($return_data);

        $data["result"] = $return_data;
        $data["totalRecord"] = $totalRecord;
        $data["numRecord"] = $page_size;
        $data["totalPage"] = ceil($totalRecord/$page_size);
        $data["pageNumber"] = $page_number;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/, "data" => $data);
    }

    public function validate_business_details($params){
        global $setting, $xunCrypto;
        $db= $this->db;

        $username = trim($params["username"]);
        $business_email = trim($params["business_email"]);
        $password = trim($params["business_password"]);
        $password_retype = trim($params["business_password_retype"]);
        $business_name = trim($params["business_name"]);
        $reward_symbol = trim($params['reward_symbol']);

        // Param validations
        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($business_email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00013') /*Business email cannot be empty*/);
        }
        if ($business_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00015') /*Business name cannot be empty.*/);
        }

        if ($password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00014') /*Password cannot be empty*/);
        }

        if ($password_retype == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00014') /*Password cannot be empty*/);
        }

        if($reward_symbol == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00459') /*Reward Symbol cannot be empty.*/, "developer_msg" => "reward symbol cannot be empty.");
        }

        if (filter_var($business_email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/, "developer_msg" => "business_email is not a valid email.");
        }

        if ($password != $password_retype){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00280') /*Password does not match.*/, "developer_msg" => "password does not match.");
        }

        if(strlen($business_name) > 25){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00281') /*Business name must be at most 25 characters.*/, "developer_msg" => "password does not match.");
        }

        if(strlen($reward_symbol) != 3){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00467') /*Symbol must be 3 characters*/, "developer_msg" => "Symbol must be 3 characters.");
        }

        if(preg_match('/\s/', $reward_symbol)){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>$this->get_translation_message('E00483') /*Reward symbol cannot contain whitespace..*/, "developer_msg" => "reward_symbol cannot contain whitespace.");
        }

        $xunBusinessService = new XunBusinessService($db);

        $businessResult = $xunBusinessService->validateBusinessName($business_name);

        if($businessResult){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00466') /*An account already exists with this business name. Please select another business name.*/, "developer_msg" => "An account already exists with this business name Please select another business name.");
        }

        // Password validation
        $validate_password = $this->validate_password($password);

        if ($validate_password['code'] == 0) {
            $error_message = $validate_password['error_message'];
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00042') /*Invalid password combination.*/, "developer_msg" => "business_password has an invalid character combination", "error_message" => $error_message);

        }
        
        $db->where('email', $business_email);
        $business_account = $db->get('xun_business_account', null, 'id,email');
        if($business_account){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00043') /*An account already exists with this email. Please select another email address.*/, "developer_msg" => "An account already exists with this email. Please select another email address.");
        }

        $name_checking_params = array(
            "name" => $business_name,
            "symbol" => $reward_symbol
        );
        $name_checking_res = $xunCrypto->check_token_name_availability($name_checking_params);

        if($name_checking_res["code"] == 1){
            $name_checking_data = $name_checking_res["data"];

            if($name_checking_data["errorCode"] == "E10004"){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00466') /*An account already exists with this business name. Please select another business name.*/, "developer_msg" => $name_checking_data["errorMessage"]);
            }
            
            if($name_checking_data["errorCode"] == "E10005"){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00471') /*This symbol has been taken. Please select another symbol.*/, "developer_msg" => $name_checking_data["errorMessage"]);
            }

            if($name_checking_data["errorCode"] == "E10007"){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00501') /*Special characters are not allowed for business name.*/, "developer_msg" => $name_checking_data["errorMessage"]);
            }

            // E10006 Missing parameter
            // E10007 Invalid characters detected
            // E10008 Parameter too long
            // E10009 Parameter too short
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00502') /*Invalid business name.*/, "developer_msg" => $name_checking_data["errorMessage"]); 
        }

        $total_supply = $setting->systemSetting['theNuxRewardTotalSupply'];
        $background_list = $setting->systemSetting['theNuxRewardCardBackground'];
        $background_list = json_decode($background_list, true);

        $button_setting_list = $setting->systemSetting["theNuxRewardButton"];
        $button_setting_list = json_decode($button_setting_list, true);

        foreach($background_list as $key => $value){
            $color = $key;
            $url = $value;

            $background_arr = array(
                "color" => $color,
                "url" => $url
            );

            $bg_list[] = $background_arr;
        }

        $button_list = [];
        foreach($button_setting_list as $key => $value){
            $button_setting = array(
                "color" => $key,
                "url" => $value["url"],
                "md5" => $value["md5"]
            );

            $button_list[] = $button_setting;
        }
 
        $data['total_supply'] = $total_supply;
        $data['card_background_list'] = $bg_list;
        $data['button_list'] = $button_list;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00248') /*Verify Business Details Success.*/, "data" => $data);
        
    }

    public function get_business_detail($params) {
    
        $db = $this->db;

        $user_id = $params["user_id"];

        if ($user_id=='') {
            
            return array('code' => 0, 'message' => "FAILED", 'message_d' => 'User ID cannot be empty.', 'developer_msg' => "User ID cannot be empty");
        };

        $db->where("u.id", $user_id);
        $db->where("u.type", "business");
        $db->join("xun_business_account ba", "ba.user_id=u.id", "INNER");
        $db->join("xun_business b", "b.user_id=u.id", "INNER");
        $detail = $db->getOne("xun_user u", "u.id, u.username as mobile, u.email, u.register_site, b.name");

        if($detail) {

            $register_site = $detail['register_site'];
            $mobile = $detail['mobile'];
            $email = $detail['email'];
            $id = $detail['id'];
            $name = $detail['name'];

            $db->where("source", $register_site);
            $source = $db->getValue("site", "source");

            return array("code" => 1, "message" => "SUCCESS", "message_d" => 'Get Business Detail', "data" => array("id"=>$id, "mobile"=>$mobile, "name"=>$name, "email"=>$email, "source"=>$source));

        } else {

            return array("code" => 0, "message" => "FAILED", "message_d" => 'No Record Found', "data" => $db->getLastQuery());

        }

    }

    public function whitelist_forward_broadcast($params) {

        $post = $this->post;
        
        $result = $post->curl_post($params['ws_url'], $params);

        return $result;
    }

    public function get_user_bc_external_address_list($params) {

        $db = $this->db;

        $user_id = $params['user_id'];
        $wallet_type = $params['wallet_type'];


        if($user_id=="") {
            return array("code" => 0, "message" => "FAILED", "message_d" => 'User id cannot be empty.');
        }

        if($wallet_type=="") {
            return array("code" => 0, "message" => "FAILED", "message_d" => 'Wallet type cannot be empty.');
        }

        $db->where('e.wallet_type', $wallet_type);
        $db->where('a.user_id', $user_id);
        $db->where('e.external_address', '', '!=');
        $db->where('a.address_type', 'nuxpay_wallet');
        $db->where('a.active', 1);
        $db->join('xun_crypto_external_address e', 'e.internal_address=a.address', 'INNER');
        $external_address_detail = $db->get('xun_crypto_user_address a', null, 'e.external_address');

        $arr_external_address = array();
        foreach($external_address_detail as $ext) {
            $arr_external_address[] = $ext['external_address'];
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => 'External address list', 'data' => $arr_external_address);

    }

    public function get_developer_log_command_list($params) {

        $command_list['payment_gatewaybuysellpaymentrequest'] = "Buy/Sell Payment Request";
        $command_list['payment_gatewaymerchanttransactionrequest'] = "Merchant Transaction Requesat";
        $command_list['buySellCryptoCallback'] = "Buy/Sell Callback";
        $command_list['paymentGatewayCallback'] = "Payment Gateway Callback";

        return array("code" => 1, "message" => "SUCCESS", "message_d" => 'Developer API Command List', 'data' => array("command_list"=>$command_list) );
    }

    public function get_developer_log($params, $user_id) {

        global $setting;
        $db     = $this->db;

        $member_page_limit  = $setting->getMemberPageLimit();
        $page_number        = $params["page"];
        $page_size          = $params["page_size"] ? $params["page_size"] : $member_page_limit;
        $from               = $params["from"];
        $to                 = $params["to"];
        $ip                 = $params["ip"];
        $callback_url       = $params["callback_url"];
        $command_name       = $params["command_name"];
        $direction          = $params["direction"];


        $db->where('id', $user_id);
        $user_result = $db->getOne('xun_user');

        if(!$user_result){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/, 'developer_msg' => 'Invalid User');
        }


        if($from) {
            $from = date("Y-m-d H:i:s", $from);
            $db->where("created_at", $from, ">=");
        }

        if($to) {
            $to = date("Y-m-d H:i:s", $to);
            $db->where("created_at", $to, "<=");
        }

        if($ip) {
            $db->where("ip LIKE '%".$ip."%' ");
        }

        if($callback_url) {
            $db->where("webservice_url LIKE '%".$callback_url."%' ");
        }

        if($command_name) {
            $db->where("command LIKE '%".$command_name."%' ");
        }

        if($direction) {
            $db->where("direction", $direction);
        }

        $db->where("user_id", $user_id);



        if ($page_number < 1){
            $page_number = 1;
        }

        $start_limit = ($page_number - 1) * $page_size;
        $limit       = array($start_limit, $page_size);


        $copyDb = $db->copy();
        $totalRecordData = $copyDb->get('developer_activity_log', null, 'id');
        $totalRecord = count($totalRecordData);


        $db->orderBy('id', "DESC");
        $result = $db->get("developer_activity_log", $limit, "id, direction, command, webservice_url, data_in, data_out, ip, created_at");

        if (!$result) {
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'result' => "");
        }

        $arr_list = array();
        foreach($result as $key => $value) {

            $data["id"] = $value['id'];
            $data["direction"] = $value['direction'];
            $data["command"] = $value['command'];
            $data["callback_url"] = $value['webservice_url'] ? $value['webservice_url'] : "-";
            $data["data_in"] = $value['data_in'];
            $data["data_out"] = $value['data_out'];
            $data["ip"] = $value['ip'] ? $value['ip'] : "-";
            $data["created_at"] = $value['created_at'];

            $arr_list[] = $data;
        }


        $returnData["apiList"] = $arr_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord/$page_size);
        $returnData["pageNumber"] = $page_number;
        
        return array("status" => "ok", "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00154') /*Transaction History.*/, "code" => 1, "result" => $returnData);      


    }


}


