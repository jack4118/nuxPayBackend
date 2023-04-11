<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/
class XunErlang
{

    public function __construct($db, $post, $general)
    {
        $this->db = $db;
        $this->post = $post;
        $this->general = $general;
    }

    public function validate_access_token($access_token)
    {

        global $setting;

        if ($access_token != $setting->getErlangAccessToken()) {
            return false;
        }

        return true;

    }

    public function business_follow($params)
    {
        $db = $this->db;
        global $config;

        $business_id = trim($params["business_id"]);
        $mobile = trim($params["mobile"]);

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

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

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

        $business_id = trim($params["business_id"]);
        $mobile = trim($params["mobile"]);

        $db->where("business_id", $business_id);
        $db->where("username", $mobile);
        $result = $db->getOne("xun_business_follow");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00030') /*Invalid id. Record does not exist.*/, "errorCode" => -100);
        }

        $record_id = $result["id"];
        $db->where('id', $record_id);
        $db->delete('xun_business_follow');

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

    public function update_chat_room_ringtone($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $chat_room_id = trim($params["chat_room_id"]);
        $ringtone = trim($params["ringtone"]);
        $tag = trim($params["tag"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($chat_room_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00246') /*Chatroom ID cannot be empty*/);
        }

        if ($ringtone == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00247') /*Ringtone cannot be empty*/);
        }

        if (is_null($tag)) {
            $tag = "";
        }

        $db->where("username", $username);
        $db->where("chat_room_id", $chat_room_id);
        $db->where("tag", $tag);
        $xun_user_chat_preference = $db->getOne("xun_user_chat_preference");

        $now = date("Y-m-d H:i:s");
        if (!$xun_user_chat_preference) {
            // insert new record
            $fields = array("uuid", "username", "chat_room_id", "ringtone", "tag", "created_at", "updated_at");
            $values = array("", $username, $chat_room_id, $ringtone, $tag, $now, $now);

            $insertData = array_combine($fields, $values);

            $row_id = $db->insert("xun_user_chat_preference", $insertData);
        } else {
            $updateData["ringtone"] = $ringtone;
            $updateData["updated_at"] = $now;
            $db->where("id", $xun_user_chat_preference["id"]);
            $db->update("xun_user_chat_preference", $updateData);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00142') /*Updated user's chat room preference.*/);
    }

    public function update_chat_room_mute($params)
    {
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $chat_room_id = trim($params["chat_room_id"]);
        $tag = trim($params["tag"]);
        $mute_validity = trim($params["mute_validity"]);
        $show_notification = trim($params["show_notification"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($chat_room_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00246') /*Chatroom ID cannot be empty*/);
        }

        if ($mute_validity == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00248') /*Mute validity cannot be empty*/);
        }

        $tag = $tag ? $tag : "";
        // $show_notification = $show_notification ? $show_notification : 1;

        if ($show_notification == '') {
            $show_notification = 1;
        }

        $db->where("username", $username);
        $db->where("chat_room_id", $chat_room_id);
        $db->where("tag", $tag);
        $xun_user_chat_preference = $db->getOne("xun_user_chat_preference");

        $now = date("Y-m-d H:i:s");

        $mute_validity_date_time = $general->formatIsoDateTimeToLocalTime($mute_validity);

        if (!$xun_user_chat_preference) {
            // insert new record
            $fields = array("uuid", "username", "chat_room_id", "mute_validity", "tag", "show_notification", "created_at", "updated_at");
            $values = array("", $username, $chat_room_id, $mute_validity_date_time, $tag, $show_notification, $now, $now);

            $insertData = array_combine($fields, $values);

            $row_id = $db->insert("xun_user_chat_preference", $insertData);
        } else {
            $updateData["mute_validity"] = $mute_validity_date_time;
            $updateData["show_notification"] = $show_notification;

            $updateData["updated_at"] = $now;
            $db->where("id", $xun_user_chat_preference["id"]);
            $db->update("xun_user_chat_preference", $updateData);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00142') /*Updated user's chat room preference.*/);
    }

    public function reset_user_notification_settings($params)
    {
        $db = $this->db;

        $mobile = trim($params["mobile"]);

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("username", $mobile);
        $db->delete("xun_user_chat_preference");

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00143') /*User notification settings has been reset.*/);
    }

    public function user_incoming_livechat_message($params)
    {
        /**
         * Function: to query or create a live chat room id. Returns chat room information and
         *           message recipient list (business_tag_employee)
         *
         * @param string username
         * @param string user_host
         * @param string business_id
         * @param string tag
         *
         * @return array
         * string chatroom_id
         * string chatroom_host
         * string username
         * string user_host
         * string employee_list
         * string is_new_chatroom
         */

        $db = $this->db;
        global $config;
        global $xunXmpp;

        $username = trim($params["username"]);
        $user_host = trim($params["user_host"]);
        $business_id = trim($params["business_id"]);
        $tag = trim($params["tag"]);
        $message_body = trim($params["has_body"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        if ($user_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00249') /*User host cannot be empty*/);
        }
        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }
        if ($tag == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00003') /*Tag cannot be empty.*/);
        }
        if (is_null($params["has_body"])) {
            $message_body = true;
        }

        // check if business tag exists
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $xun_business_tag = $db->getOne("xun_business_tag");

        if (!$xun_business_tag) {
            return array('code' => 0, 'errorCode' => -100, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00250') /*Business tag does not exists.*/);
        }

        $now = date("Y-m-d H:i:s");
        // check if chatroom exists
        $db->where("username", $username);
        $db->where("username_host", $user_host);
        $db->where("business_id", $business_id);
        $db->where("business_tag", $tag);
        $xun_livechat_room = $db->getOne("xun_livechat_room");

        $erlang_server = $config["erlang_server"];
        $is_new_chatroom = 0;

        // if chat room doesn't exist and message is without body, then skip
        if (!$xun_livechat_room) {
            // create chatroom
            // id old_id host username username_host business_id business_tag employee_username employee_host status created_at updated_at

            if (!$message_body) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00252') /*Chat room doesn't exist and message is without body. Message will be dropped.*/);
            }

            $live_chatroom_id = $db->getNewID();
            $live_chatroom_host = $xunXmpp->get_livechat_host();

            $livechat_room_fields = array("old_id", "host", "username", "username_host", "business_id", "business_tag", "employee_username", "employee_host", "status", "created_at", "updated_at");
            $livechat_room_values = array($live_chatroom_id, $live_chatroom_host, $username, $user_host, $business_id, $tag, "", "", "open", $now, $now);

            $insertData = array_combine($livechat_room_fields, $livechat_room_values);

            $row_id = $db->insert("xun_livechat_room", $insertData);
            $is_new_chatroom = 1;
        } else {
            $live_chatroom_id = $xun_livechat_room["old_id"];
            $live_chatroom_host = $xun_livechat_room["host"];

            if ($message_body) {
                // update chat room status only if the message has a body
                // update chat room status to open if it's closed
                if ($xun_livechat_room["status"] == "closed") {
                    $updateLivechatRoom = [];
                    $updateLivechatRoom["status"] = "open";
                    $updateLivechatRoom["updated_at"] = $now;

                    $db->where("username", $username);
                    $db->where("username_host", $user_host);
                    $db->where("business_id", $business_id);
                    $db->where("business_tag", $tag);
                    $db->update("xun_livechat_room", $updateLivechatRoom);
                }
            }
        }

        // get confirmed employee list
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $xun_business_tag_employee = $db->get("xun_business_tag_employee");

        $employee_arr = [];

        foreach ($xun_business_tag_employee as $data) {
            // check employee status for confirmed
            $employee_username = $data["username"];
            $xun_employee = $this->is_employee_confirmed($employee_username, $business_id);

            if ($xun_employee) {
                // employee_id
                // username
                $employee = [];
                $employee["employee_id"] = $data["employee_id"];
                $employee["username"] = $employee_username;
                $employee_arr[] = $employee;
            }
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00145') /*Live chat room details.*/, "chatroom_id" => (string) $live_chatroom_id, "chatroom_host" => $live_chatroom_host, "username" => $username, "user_host" => $user_host, "employee_list" => $employee_arr, "is_new_chatroom" => $is_new_chatroom);
    }

    public function employee_incoming_livechat_message($params)
    {
        /**
         * Function: to check if incoming live chat message from employee is allowed. Reject message
         *           if user is not a business employee. Returns chat room information and recipient list.

         * @param string employee_username
         * @param string employee_host
         * @param string chatroom_id
         * @param string chatroom_host
         *
         * @return array
         * string chatroom_id
         * string chatroom_host
         * string username
         * string user_host
         * string employee_list
         * string sender_employee_id
         */
        $db = $this->db;
        global $config;

        $employee_username = trim($params["employee_username"]);
        $employee_host = trim($params["employee_host"]);
        $chatroom_id = trim($params["chatroom_id"]);
        $chatroom_host = trim($params["chatroom_host"]);

        if ($employee_username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00254') /*Employee username cannot be empty*/);
        }
        if ($employee_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00255') /*Employee Host cannot be empty*/);
        }
        if ($chatroom_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00246') /*Chatroom ID cannot be empty*/);
        }
        if ($chatroom_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00257') /*Chatroom host cannot be empty*/);
        }

        // check if chatroom exist
        $db->where("old_id", $chatroom_id);
        $db->where("host", $chatroom_host);
        $xun_livechat_room = $db->getOne("xun_livechat_room");

        if (!$xun_livechat_room) {
            return array('code' => 0, 'errorCode' => -100, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00259') /*Chatroom does not exist.*/);
        }

        // check if employee can send message
        $chatroom_status = $xun_livechat_room["status"];
        $chatroom_employee_username = $xun_livechat_room["employee_username"];

        $business_id = $xun_livechat_room["business_id"];
        $tag = $xun_livechat_room["business_tag"];
        $chatroom_user = $xun_livechat_room["username"];
        $chatroom_user_host = $xun_livechat_room["username_host"];

        // check if is tag employee
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $db->where("username", $employee_username);
        $xun_business_tag_employee = $db->getOne("xun_business_tag_employee");

        if (!$xun_business_tag_employee) {
            return array('code' => 0, 'errorCode' => -101, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00260') /*You're not allowed to send message to this chat room.*/);
        }

        $employee_id = $xun_business_tag_employee["employee_id"];

        $employee_list = $this->get_business_tag_employee_list($business_id, $tag);

        if ($employee_username == $chatroom_employee_username && $chatroom_status == "accepted") {
            $returnMessage = array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00147') /*Attending employee.*/);
        } else {
            $returnMessage = array('code' => 0, 'errorCode' => -102, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00261') /*Not attending employee*/);
        }

        $returnData = array("chatroom_id" => (string) $chatroom_id, "chatroom_host" => $chatroom_host, "username" => $chatroom_user, "user_host" => $chatroom_user_host, "employee_list" => $employee_list, "sender_employee_id" => $employee_id);

        return array_merge($returnMessage, $returnData);
    }

    public function accept_livechat($params)
    {
        $db = $this->db;

        $mobile = trim($params["mobile"]);
        $chat_room_id = trim($params["chat_room_id"]);
        $chat_room_host = trim($params["chat_room_host"]);
        // $business_id = trim($params["business_id"]);

        global $config;

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }
        if ($chat_room_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00246') /*Chatroom ID cannot be empty*/);
        }
        if ($chat_room_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00257') /*Chatroom host cannot be empty*/);
        }
        // if ($business_id == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "business_id cannot be empty");
        // }

        // only allow to accept if user is tag_employee and chatroom status is open/standup

        $db->where("old_id", $chat_room_id);
        $db->where("host", $chat_room_host);
        $xun_livechat_room = $db->getOne("xun_livechat_room");

        if (!$xun_livechat_room) {
            return array("code" => 0, "message" => "FAILED", "message_id" => $this->get_translation_message('E00256') /*This is not a valid chatroom id.*/);
        }

        $chat_room_status = $xun_livechat_room["status"];

        // chatroom must be either open or standup
        if (!($chat_room_status == "open" || $chat_room_status == "standup")) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00263') /*This is no longer an open ticket.*/);
        }

        // check if user is a tag_employee
        $business_id = $xun_livechat_room["business_id"];
        $tag = $xun_livechat_room["business_tag"];

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("username", $mobile);
        $db->where("status", 1);

        $xun_business_tag_employee = $db->getOne("xun_business_tag_employee");
        $confirmed_xun_employee = $this->is_employee_confirmed($mobile, $business_id);

        if (!($xun_business_tag_employee && $confirmed_xun_employee)) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00264') /*You are not allowed to accept this chat room.*/);
        }

        $now = date("Y-m-d H:i:s");
        $updateLivechatRoom = [];
        $updateLivechatRoom["status"] = "accepted";
        $updateLivechatRoom["employee_username"] = $mobile;
        $updateLivechatRoom["employee_host"] = $config["erlang_server"];
        $updateLivechatRoom["updated_at"] = $now;

        $db->where("old_id", $chat_room_id);
        $db->where("host", $chat_room_host);
        $db->update("xun_livechat_room", $updateLivechatRoom);

        // get employee_id
        // get employee_list

        $chat_room_username = $xun_livechat_room["username"];
        $chatroom_user_host = $xun_livechat_room["username_host"];
        $accepted_employee_id = $xun_business_tag_employee["employee_id"];
        $employee_list = $this->get_business_tag_employee_list($business_id, $tag);

        $live_chat_first_message = $this->get_livechat_setting_message($business_id);
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00151') /*Ticket accepted.*/, "welcome_message" => $live_chat_first_message, "return_data" => array("employee_id" => $accepted_employee_id, "employee_list" => $employee_list, "chat_room_username" => $chat_room_username, "chat_room_user_host" => $chatroom_user_host, "chat_room_id" => $chat_room_id, "chat_room_host" => $chat_room_host));
    }

    public function close_livechat($params)
    {
        $db = $this->db;

        $mobile = trim($params["mobile"]);
        $chat_room_id = trim($params["chat_room_id"]);
        $chat_room_host = trim($params["chat_room_host"]);

        global $config;

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }
        if ($chat_room_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00246') /*Chatroom ID cannot be empty*/);
        }
        if ($chat_room_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00257') /*Chatroom host cannot be empty*/);
        }

        $db->where("old_id", $chat_room_id);
        $db->where("host", $chat_room_host);
        $xun_livechat_room = $db->getOne("xun_livechat_room");

        if (!$xun_livechat_room) {
            return array("code" => 0, "message" => "FAILED", "message_id" => $this->get_translation_message('E00265') /*This is not a valid chatroom JID.*/);
        }

        $chat_room_status = $xun_livechat_room["status"];

        if ($chat_room_status != "accepted") {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00266') /*This action is not allowed in this chat room.*/);
        }

        $chat_room_employee_username = $xun_livechat_room["employee_username"];

        if ($chat_room_employee_username != $mobile) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00266') /*This action is not allowed in this chat room.*/);
        }

        $now = date("Y-m-d H:i:s");
        $updateLivechatRoom = [];
        $updateLivechatRoom["status"] = "closed";
        $updateLivechatRoom["employee_username"] = "";
        $updateLivechatRoom["employee_host"] = "";
        $updateLivechatRoom["updated_at"] = $now;
        $db->where("old_id", $chat_room_id);
        $db->where("host", $chat_room_host);
        $db->update("xun_livechat_room", $updateLivechatRoom);

        $business_id = $xun_livechat_room["business_id"];
        $tag = $xun_livechat_room["business_tag"];

        $chat_room_username = $xun_livechat_room["username"];
        $chatroom_user_host = $xun_livechat_room["username_host"];

        $employee_list = $this->get_business_tag_employee_list($business_id, $tag);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00152') /*Ticket closed.*/, "return_data" => array("employee_list" => $employee_list, "chat_room_username" => $chat_room_username, "chat_room_user_host" => $chatroom_user_host, "chat_room_id" => $chat_room_id, "chat_room_host" => $chat_room_host));
    }

    public function close_guest_livechat($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $user_host = trim($params["user_host"]);

        global $config;

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty*/);
        }
        if ($user_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00249') /*User host cannot be empty*/);
        }

        $db->where("username", $username);
        $db->where("username_host", $user_host);
        $xun_livechat_room = $db->getOne("xun_livechat_room");

        if (!$xun_livechat_room) {
            return array("code" => 0, "message" => "FAILED", "message_id" => $this->get_translation_message('E00267') /*This is not a valid guest JID.*/);
        }

        $chat_room_status = $xun_livechat_room["status"];

        if ($chat_room_status == "closed") {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00153') /*Chat room closed.*/);
        }

        $now = date("Y-m-d H:i:s");
        $updateLivechatRoom = [];
        $updateLivechatRoom["status"] = "closed";
        $updateLivechatRoom["employee_username"] = "";
        $updateLivechatRoom["employee_host"] = "";
        $updateLivechatRoom["updated_at"] = $now;
        $db->where("id", $xun_livechat_room["id"]);
        $db->update("xun_livechat_room", $updateLivechatRoom);

        $business_id = $xun_livechat_room["business_id"];
        $tag = $xun_livechat_room["business_tag"];

        $chat_room_id = $xun_livechat_room["old_id"];
        $chat_room_host = $xun_livechat_room["host"];

        $employee_list = $this->get_business_tag_employee_list($business_id, $tag);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00153') /*Chat room closed.*/, "return_data" => array("employee_list" => $employee_list, "chat_room_id" => $chat_room_id, "chat_room_host" => $chat_room_host));
    }

    public function standup_livechat($params)
    {
        $db = $this->db;

        $mobile = trim($params["mobile"]);
        $chat_room_id = trim($params["chat_room_id"]);
        $chat_room_host = trim($params["chat_room_host"]);

        global $config;

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }
        if ($chat_room_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00246') /*Chatroom ID cannot be empty*/);
        }
        if ($chat_room_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00257') /*Chatroom host cannot be empty*/);
        }

        $db->where("old_id", $chat_room_id);
        $db->where("host", $chat_room_host);
        $xun_livechat_room = $db->getOne("xun_livechat_room");

        if (!$xun_livechat_room) {
            return array("code" => 0, "message" => "FAILED", "message_id" => $this->get_translation_message('E00265') /*This is not a valid chatroom JID.*/);
        }

        $chat_room_status = $xun_livechat_room["status"];

        if ($chat_room_status != "accepted") {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00266') /*This action is not allowed in this chat room.*/);
        }

        $chat_room_employee_username = $xun_livechat_room["employee_username"];

        if ($chat_room_employee_username != $mobile) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00266') /*This action is not allowed in this chat room.*/);
        }

        $now = date("Y-m-d H:i:s");
        $updateLivechatRoom = [];
        $updateLivechatRoom["status"] = "standup";
        $updateLivechatRoom["employee_username"] = "";
        $updateLivechatRoom["employee_host"] = "";
        $updateLivechatRoom["updated_at"] = $now;

        $db->where("old_id", $chat_room_id);
        $db->where("host", $chat_room_host);
        $db->update("xun_livechat_room", $updateLivechatRoom);

        $business_id = $xun_livechat_room["business_id"];
        $tag = $xun_livechat_room["business_tag"];
        $chat_room_username = $xun_livechat_room["username"];
        $chatroom_user_host = $xun_livechat_room["username_host"];

        $employee_list = $this->get_business_tag_employee_list($business_id, $tag);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00154') /*Ticket standup.*/, "return_data" => array("employee_list" => $employee_list, "chat_room_username" => $chat_room_username, "chat_room_id" => $chat_room_id, "chat_room_user_host" => $chatroom_user_host, "chat_room_host" => $chat_room_host));
    }

    public function app_business_chatroom($params)
    {
        $db = $this->db;

        $mobile = trim($params["mobile"]);
        $chat_room_jid = trim($params["chat_room_jid"]);

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('B00019') /*Mobile cannot be empty.*/);
        }
        if ($chat_room_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00270') /*Chatroom JID cannot be empty*/);
        }

        $pos = stripos($chat_room_jid, '@');

        if ($pos === false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00271') /*Malformed Chat room JID*/);
        } else {
            $chat_room_jid_arr = explode("@", $chat_room_jid);
            $chat_room_id = $chat_room_jid_arr[0];
            $chat_room_host = $chat_room_jid_arr[1];
        }

        // get live chat room
        $db->where("old_id", $chat_room_id);
        $db->where("host", $chat_room_host);
        $xun_livechat_room = $db->getOne("xun_livechat_room");

        if (!$xun_livechat_room) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00272') /*This chat room does not exists.*/);
        }

        $return_result_arr = $this->get_livechat_room_details($xun_livechat_room, $chat_room_jid);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00155') /*Business chatroom details.*/, "result" => $return_result_arr);
    }

    public function business_tag_user_list($params)
    {
        $db = $this->db;

        $mobile = trim($params["mobile"]);

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }

        $db->where("mobile", $mobile);
        $db->where("status", 1);
        $db->where("employment_status", "confirmed");
        $xun_employee = $db->get("xun_employee");
        $returnData = [];
        $tag_array = [];

        foreach ($xun_employee as $employee) {
            $business_id = $employee["business_id"];
            $db->where("username", $mobile);
            $db->where("status", 1);
            $db->where("business_id", $business_id);
            $db->orderBy("tag", "ASC");
            $user_tag_employee = $db->getValue("xun_business_tag_employee", "tag", null);

            $user_tag_employee = $user_tag_employee ? $user_tag_employee : [];
            $tag_array[$business_id] = $user_tag_employee;
        }

        foreach ($tag_array as $business_id => $tags) {
            $returnData[] = array(
                "business_id" => (string) $business_id,
                "tags" => $tags,
            );
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00156') /*User's business tag listing.*/, "result" => $returnData);
    }

    public function app_get_employee_details($params)
    {
        $db = $this->db;

        $mobile = trim($params["mobile"]);
        $business_id = trim($params["business_id"]);

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("mobile", $mobile);
        $db->where("status", 1);

        $xun_employee = $db->getOne("xun_employee");

        if (!$xun_employee) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/);
        }

        $employee_info = [];
        $employee_info["employee_role"] = $xun_employee["role"];
        $employee_info["employee_mobile"] = $xun_employee["mobile"];
        $employee_info["employee_id"] = $xun_employee["old_id"];
        $employee_info["employee_employment_status"] = $xun_employee["employment_status"];
        $returnData = array("employee" => $employee_info, "business_id" => $business_id);
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00180') /*Employee details.*/, "result" => $returnData);
    }

    private function compose_xun_business($result)
    {
        $general = $this->general;

        $returnData["uuid"] = (string) $result["user_id"];
        $returnData["business_email"] = $result["email"];
        $returnData["business_name"] = $result["name"] ? $result["name"] : "";
        $returnData["business_phone_number"] = $result["phone_number"] ? $result["phone_number"] : "";
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
        $returnData["business_email_address"] = $result["display_email"] ? $result["display_email"] : "";
        $returnData["business_created_date"] = $result["created_at"] ? $general->formatDateTimeToIsoFormat($result["created_at"]) : "";

        return $returnData;
    }

    private function get_business_tag_employee_list($business_id, $tag)
    {
        $db = $this->db;
        // get employee list
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $xun_business_tag_employee = $db->get("xun_business_tag_employee");

        $employee_arr = [];

        foreach ($xun_business_tag_employee as $data) {
            $employee_username = $data["username"];
            $confirmed_xun_employee = $this->is_employee_confirmed($employee_username, $business_id);

            if ($confirmed_xun_employee) {
                // employee_id
                // username
                $employee = [];
                $employee["employee_id"] = $data["employee_id"];
                $employee["username"] = $employee_username;
                $employee_arr[] = $employee;
            }
        }

        return $employee_arr;
    }

    private function get_livechat_setting_message($business_id)
    {
        $db = $this->db;

        $db->where("business_id", $business_id);
        $xun_business_livechat_setting = $db->getOne("xun_business_livechat_setting");

        $default_live_chat_first_message = "Welcome";
        if ($xun_business_livechat_setting) {
            $live_chat_first_message = $xun_business_livechat_setting["live_chat_first_msg"];
        }

        return $live_chat_first_message ? $live_chat_first_message : $default_live_chat_first_message;
    }

    public function get_livechat_details($params)
    {
        /**
         * Function: To get live chat room details by business employee. Returns chat room status closed
         *           if user is not an active business tag employee

         * @param string mobile
         * @param string chat_room_id
         * @param string chat_room_host
         *
         * @return array
         */
        $db = $this->db;
        $general = $this->general;

        $mobile = trim($params["mobile"]);
        $chat_room_id = trim($params["chat_room_id"]);
        $chat_room_host = trim($params["chat_room_host"]);

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }
        if ($chat_room_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00246') /*Chatroom ID cannot be empty*/);
        }
        if ($chat_room_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00257') /*Chatroom host cannot be empty*/);
        }

        // get live chat room
        $db->where("old_id", $chat_room_id);
        $db->where("host", $chat_room_host);
        $xun_livechat_room = $db->getOne("xun_livechat_room");

        if (!$xun_livechat_room) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00272') /*This chat room does not exists.*/);
        }

        $return_result_arr = $this->get_livechat_room_details($xun_livechat_room);

        $business_id = $return_result_arr["business_id"];
        $tag = $return_result_arr["tag"];
        $db->where("username", $mobile);
        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $btag_employee = $db->getOne("xun_business_tag_employee");

        $confirmed_xun_employee = $this->is_employee_confirmed($mobile, $business_id);

        if (!($btag_employee && $confirmed_xun_employee)) {
            $return_result_arr["chat_room_status"] = "closed";
        }

        $db->where("business_id", $business_id);
        $livechat_setting = $db->getOne("xun_business_livechat_setting");

        $livechat_prompt = $livechat_setting["live_chat_prompt"] ? (string) $livechat_setting["live_chat_prompt"] : "0";

        $return_result_arr["live_chat_prompt"] = $livechat_prompt;
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00155') /*Business chatroom details.*/, "result" => $return_result_arr);
// #{chat_room_jid => jid:encode({LCName, LCHost, <<>>}), chat_room_status => ChatStatus,
        //         user_jid => UserJID1, attending_staff_jid => AttendingStaffJID, business_id => BusinessId,
        //         tag => Tag, created_date => xmpp_util:encode_timestamp(CreatedDate),
        //         modified_date => xmpp_util:encode_timestamp(ModifiedDate),
        //         attending_employee_id => EmployeeID, attending_employee_jid => AttendingEmpJID}.
    }
    public function get_livechat_prompt($params)
    {
        $db = $this->db;

        $business_id = trim($params["business_id"]);

        if ($business_id == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $db->where("business_id", $business_id);
        $livechat_setting = $db->getOne("xun_business_livechat_setting");

        $livechat_prompt = $livechat_setting["live_chat_prompt"] ? (string) $livechat_setting["live_chat_prompt"] : "0";

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00157') /*Live chat prompt*/, "result" => array("live_chat_prompt" => $livechat_prompt));
    }

    public function app_business_chatroom_user_details($params)
    {

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $mobile = $params["mobile"];
        $business_jid = $params["business_jid"];
        $tag = $params["tag"];
        $user_jid = $params["user_jid"];

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }
        if ($business_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00274') /*Business JID cannot be empty*/);
        }
        if ($tag == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00003') /*Tag cannot be empty*/);
        }
        if ($user_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00275') /*User JID cannot be empty*/);
        }

        $check_business_jid = $this->get_xmpp_jid($business_jid);

        if ($check_business_jid["code"] == 0) {
            return $check_business_jid;
        }

        $check_user_jid = $this->get_xmpp_jid($user_jid);

        if ($check_user_jid["code"] == 0) {
            return $check_user_jid;
        }

        $business_id = $check_business_jid["jid_user"];
        $user = $check_user_jid["jid_user"];

        $db->where("username", $user);
        $db->where("business_id", $business_id);
        $db->where("business_tag", $tag);
        $xun_livechat_room = $db->getOne("xun_livechat_room");

        if (!$xun_livechat_room) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00272') /*This chat room does not exists.*/);
        }

        $return_result_arr = $this->get_livechat_room_details($xun_livechat_room);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00155') /*Business chatroom details.*/, "result" => $return_result_arr);
    }

    public function app_business_chatroom_employee_all($params)
    {
        /**
         * Function: To get employee's business tag live chat rooms that are not closed.
         *           Used for reinstallation.

         * @param string mobile
         * @param string stream_jid
         *
         * @return array list of live chat room and it's details
         */

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $mobile = $params["mobile"];
        $stream_jid = $params["stream_jid"];

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }
        if ($stream_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00008') /*Stream jid cannot be empty*/);
        }

        $check_stream_jid = $this->get_xmpp_jid($stream_jid);

        if ($check_stream_jid["code"] == 0) {
            return $check_stream_jid;
        }

        $username = $check_stream_jid["jid_user"];

        // get all user's livechat room
        // get all user's xun_business_tag_employee then get xun_livechat_room which are not closed

        // get all confirmed business employee only
        $db->where("mobile", $username);
        $db->where("status", 1);
        $db->where("employment_status", "confirmed");
        $confirmed_xun_employee = $db->get("xun_employee");

        $employee_business_id = array();

        foreach ($confirmed_xun_employee as $xun_employee) {
            $employee_business_id[] = $xun_employee["business_id"];

        }

        $db->where("username", $username);
        $db->where("business_id", $employee_business_id, "in");
        $db->where("status", 1);
        $xun_business_tag_employee = $db->get("xun_business_tag_employee");

        $return_result_arr = array();

        foreach ($xun_business_tag_employee as $btemployee_data) {
            $business_id = $btemployee_data["business_id"];
            $tag = $btemployee_data["tag"];

            // get all livechat room belonging to business_id and tag and status != closed
            $db->where("business_id", $business_id);
            $db->where("business_tag", $tag);
            $db->where("status", "closed", "!=");
            $xun_livechat_room = $db->get("xun_livechat_room");

            foreach ($xun_livechat_room as $livechat_room_data) {
                $return_result_arr[] = $this->get_livechat_room_details($livechat_room_data);
            }
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00155') /*Business chatroom details.*/, "stream_jid" => $stream_jid, "result" => $return_result_arr);
    }

    public function app_livechat_group_create($params)
    {

        global $config;
        $server_host = $config["erlang_server"];
        $chatroom_host = "livegroupchat." . $server_host;
        $date = date("Y-m-d H:i:s");

        $db = $this->db;

        $employee_mobile = $params["employee_mobile"];
        $employee_host = $params["employee_host"];
        $user_mobile = $params["user_mobile"];
        $user_host = $params["user_host"];
        $business_id = $params["business_id"];

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00223') /*Invalid business.*/);
        }

        $db->where("username", $user_mobile);
        $user_result = $db->getOne("xun_user");

        if (!$user_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00157') /*Invalid user.*/);
        }

        $user_id = $user_result["id"];

        $db->where("business_id", $business_id);
        $db->where("mobile", $employee_mobile);
        $db->where("status", 1);
        $db->where("employment_status", "confirmed");
        $employee_result = $db->getOne("xun_employee");

        if (!$employee_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00276') /*Not an Employee.*/);
        }

        $xunUserService = new XunUserService($db);

        $chatroom_obj = new stdClass();
        $chatroom_obj->user_id = $user_id;
        $chatroom_obj->business_id = $business_id;

        $live_group_chat_result = $xunUserService->getLiveGroupChatRoomDetailsByBusinessIDUserID($chatroom_obj);

        if (!$live_group_chat_result) {

            $isNew = 1;
            // $chatroom_id = $db->getNewID();

            $chatroom_obj->chatroom_host = $chatroom_host;
            $chatroom_obj->user_mobile = $user_mobile;
            $chatroom_obj->user_host = $user_host;
            $chatroom_obj->employee_mobile = $employee_mobile;
            $chatroom_obj->created_at = $date;
            $chatroom_obj->updated_at = $date;

            $chatroom_data = $xunUserService->createLiveGroupChatRoom($chatroom_obj);

            if (is_null($chatroom_data["id"])) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00141') /*Internal server error. Please try again later.*/);
            }
            $chatroom_id = $chatroom_data["old_id"];

        } else {

            $isNew = 0;
            $chatroom_id = $live_group_chat_result["old_id"];

        }

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->where("employment_status", "confirmed");
        $employee_result = $db->get("xun_employee");

        foreach ($employee_result as $employee_data) {

            $employee["employee_mobile"] = $employee_data["mobile"];
            $employee["employee_host"] = $server_host;
            $employee["employee_id"] = $employee_data["old_id"];

            $employee_list[] = $employee;

        }

        $returnData["chatroom_id"] = (string) $chatroom_id;
        $returnData["chatroom_host"] = $chatroom_host;
        $returnData["employee_mobile"] = $employee_list;
        $returnData["is_new_chatroom"] = $isNew;
        $returnData["business_name"] = $business_result["name"];

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00164') /*Live group chatroom successfully created.*/, "result" => $returnData);

    }

    public function app_livechat_group_details($params)
    {

        global $config;
        $server_host = $config["erlang_server"];

        $db = $this->db;

        // $user_mobile     = $params["user_mobile"];
        $chatroom_id = $params["chatroom_id"];
        $chatroom_host = $params["chatroom_host"];

        $chatroom_obj = new stdClass();
        $chatroom_obj->chatroom_id = $chatroom_id;
        $chatroom_obj->chatroom_host = $chatroom_host;

        $xunUserService = new XunUserService($db);
        $chatroom_result = $xunUserService->getLiveGroupChatRoomDetailsByChatroomID($chatroom_obj);

        if (!$chatroom_result) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00279') /*Invalid chatroom*/);
        }

        $db->where("user_id", $chatroom_result["business_id"]);
        $business_result = $db->getOne("xun_business");

        $user_id = $chatroom_result["user_mobile"] ? $chatroom_result["user_mobile"] : $chatroom_result["user_id"];
        $user_jid = $user_id . "@" . $chatroom_result["user_host"];
        $chatroom_jid = $chatroom_result["old_id"] . "@" . $chatroom_result["host"];
        $business = $this->compose_xun_business($business_result);
        $tag = "Service Notification";

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00164') /*Live group chatroom successfully created.*/, "user_jid" => $user_jid, "chat_room_jid" => $chatroom_jid, "business" => $business, "tag" => $tag);

    }

    public function user_incoming_group_livechat_message($params)
    {

        global $config;
        $server_host = $config["erlang_server"];
        $chatroom_host = "livegroupchat." . $server_host;

        $db = $this->db;

        $chatroom_id = $params["chatroom_id"];
        $user_mobile = $params["user_mobile"];

        $db->where("old_id", $chatroom_id);
        $chatroom_result = $db->getOne("xun_live_group_chat_room");

        $is_valid = false;

        $user_id = $chatroom_result["user_id"];
        $db->where("id", $user_id);
        $xun_user = $db->getOne("xun_user");

        $user_type = $xun_user["type"];

        $is_business = $user_type == "business" ? true : false;
        $business_id = $chatroom_result["business_id"];

        if ($user_mobile == $chatroom_result["user_mobile"]) {
            $is_valid = true;
        } else {
            $db->where("business_id", [$business_id, $user_id], "in");
            $db->where("mobile", $user_mobile);
            $db->where("employment_status", "confirmed");
            $db->where("status", "1");
            $employee_result = $db->getOne("xun_employee");

            if ($employee_result) {
                $is_valid = true;
            }
        }

        if (!$is_valid) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00282') /*User mobile is neither a participant or employee.*/);
        }

        if ($is_business === true) {
            $db->where("business_id", [$user_id, $business_id], "in");
        } else {
            $db->where("business_id", $business_id);
        }
        $db->where("employment_status", "confirmed");
        $db->where("status", "1");
        $business_employee_result = $db->get("xun_employee");

        foreach ($business_employee_result as $employee_data) {

            $employee["employee_mobile"] = $employee_data["mobile"];
            $employee["employee_host"] = $server_host;
            $employee["employee_id"] = $employee_data["old_id"];

            $employee_list[] = $employee;

        }

        $returnData["user_mobile"] = $chatroom_result["user_mobile"];
        $returnData["user_host"] = $chatroom_result["user_host"];
        $returnData["employee_mobile"] = $employee_list;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00165') /*Employee Mobile.*/, "result" => $returnData);

    }

    public function group_chat_create($params)
    {

        global $config;
        $server_host = $config["erlang_server"];
        $group_host = "conference." . $server_host;
        $date = date("Y-m-d H:i:s");

        $db = $this->db;
        $general = $this->general;

        $participants = $params["participants"];
        $owner_mobile = $params["owner_mobile"];
        $group_type = $params["group_type"];
        $group_public_key = $params["group_public_key"];

        if (count($participants) <= 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00283')/*Participants cannnot be empty.*/);
        }

        if (!$owner_mobile) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00284')/*Owner mobile cannot be empty.*/);
        }

        if (!$group_type) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00285')/*Group type cannot be empty.*/);
        }

        if (!$group_public_key) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00286')/*Group Public key cannot be empty.*/);
        }

        $group_id = $db->getNewID();

        $fields = array("username", "group_id", "group_host", "created_at");
        $values = array($owner_mobile, $group_id, $group_host, $date);
        $insertData = array_combine($fields, $values);

        $db->insert("xun_muc_user", $insertData);

        foreach ($participants as $data) {

            $mobile = $data["mobile"];
            $encrypted_key = $data["encrypted_private_key"];
            //id    old_id    username    group_id    group_host    created_at

            $fields = array("username", "group_id", "group_host", "created_at");
            $values = array($mobile, $group_id, $group_host, $date);
            $insertData = array_combine($fields, $values);

            $db->insert("xun_muc_user", $insertData);

            //id    username    group_id    group_host    encrypted_key    status    created_at    updated_at
            $fields = array("username", "group_id", "group_host", "encrypted_key", "status", "created_at", "updated_at");
            $values = array($mobile, $group_id, $group_host, $encrypted_key, "1", $date, $date);
            $insertData = array_combine($fields, $values);

            $db->insert("xun_encrypted_key", $insertData);

        }

        while (1) {

            $alphanumberic  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $invitekey         = substr(str_shuffle($alphanumberic), 0, 32);

            $db->where('invite_key', $invitekey);
            $result = $db->get('xun_group_chat');

            if (!$result) {
                break;
            }

        }

        $fields = array("old_id", "host", "creator_id", "type", "invite_key", "created_at", "updated_at");
        $values = array($group_id, $group_host, $owner_mobile, $group_type, $invitekey, $date, $date);
        $insertData = array_combine($fields, $values);

        $db->insert("xun_group_chat", $insertData);

        $fields = array("key_user_id", "key_host", "key", "status", "created_at", "updated_at");
        $values = array($group_id, $group_host, $group_public_key, "1", $date, $date);
        $insertData = array_combine($fields, $values);

        $db->insert("xun_public_key", $insertData);

        $returnData["group_id"] = (string) $group_id;
        $returnData["group_host"] = $group_host;
        $returnData["created_at"] = $general->formatDateTimeToIsoFormat($date);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00166')/*Private group successfully created.*/, "result" => $returnData);

    }

    public function group_chat_add_participant($params)
    {

        global $config;
        $server_host = $config["erlang_server"];
        $group_host = "conference." . $server_host;
        $date = date("Y-m-d H:i:s");

        $db = $this->db;
        $general = $this->general;

        $participants = $params["participants"];
        $group_id = $params["group_id"];

        if (count($participants) <= 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00283')/*Participants cannnot be empty.*/);
        }

        if (!$group_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00113')/*Invalid Group ID.*/);
        }

        //add new participant
        $mobile = $participants["mobile"];
        $encrypted_key = $participants["encrypted_private_key"];

        $db->where("username", $mobile);
        $db->where("group_id", $group_id);
        $db->where("group_host", $group_host);
        $muc_user_result = $db->getOne("xun_muc_user");

        if ($muc_user_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00287')/*User is already a participant.*/);
        }

        $fields = array("username", "group_id", "group_host", "created_at");
        $values = array($mobile, $group_id, $group_host, $date);
        $insertData = array_combine($fields, $values);

        $db->insert("xun_muc_user", $insertData);

        $db->where("username", $mobile);
        $db->where("group_id", $group_id);
        $db->where("group_host", $group_host);
        $copyDb = $db->copy();
        $encrypted_key_result = $db->getOne("xun_encrypted_key");

        if (!$encrypted_key_result) {
            $fields = array("username", "group_id", "group_host", "encrypted_key", "status", "created_at", "updated_at");
            $values = array($mobile, $group_id, $group_host, $encrypted_key, "1", $date, $date);
            $insertData = array_combine($fields, $values);

            $db->insert("xun_encrypted_key", $insertData);
        } else {

            $updateData["encrypted_key"] = $encrypted_key;
            $updateData["updated_at"] = $date;

            $copyDb->update("xun_encrypted_key", $updateData);

        }

        //build return data
        $db->where("old_id", $group_id);
        $group_chat_result = $db->getOne("xun_group_chat");

        $db->where("key_user_id", $group_id);
        $public_key_result = $db->getOne("xun_public_key");

        $welcome_message = $group_chat_result["welcome_message_status"] === 1 ? $group_chat_result["welcome_message"] : "";

        $returnData["created_at"] = $general->formatDateTimeToIsoFormat($date);
        $returnData["group_host"] = $group_chat_result["host"];
        $returnData["group_creator"] = $group_chat_result["creator_id"];
        $returnData["public_key"] = $public_key_result["key"];
        $returnData["welcome_message"] = $welcome_message;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00167')/*Participant(s) Added.*/, "result" => $returnData);

    }

    public function group_chat_remove_participant($params)
    {

        global $config;
        $server_host = $config["erlang_server"];
        $group_host = "conference." . $server_host;
        $date = date("Y-m-d H:i:s");

        $db = $this->db;

        $participants = $params["participants"];
        $group_id = $params["group_id"];

        if (!participants) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00283')/*Participants cannnot be empty.*/);
        }

        if (!$group_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00113')/*Invalid Group ID.*/);
        }

        $mobile = $participants;

        $db->where("group_id", $group_id);
        $db->where("username", $mobile);
        $db->delete("xun_muc_user");

        $updateData["status"] = 0;
        $updateData["updated_at"] = $date;
        $db->where("group_id", $group_id);
        $db->where("username", $mobile);
        $db->update("xun_encrypted_key", $updateData);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00168') /*Participant Removed.*/, "result" => $returnData);

    }

    public function group_chat_leave_participant($params)
    {

        global $config;
        $server_host = $config["erlang_server"];
        $group_host = "conference." . $server_host;
        $date = date("Y-m-d H:i:s");

        $db = $this->db;

        $mobile = $params["mobile"];
        $group_id = $params["group_id"];

        if (!$mobile) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }

        if (!$group_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00113')/*Invalid Group ID.*/);
        }

        $db->where("group_id", $group_id);
        $db->where("username", $mobile);
        $db->delete("xun_muc_user");

        $updateData["status"] = 0;
        $updateData["updated_at"] = $date;
        $db->where("group_id", $group_id);
        $db->where("username", $mobile);
        $db->update("xun_encrypted_key", $updateData);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00237') /*Participant Left.*/, "result" => $returnData);

    }

    public function group_chat_add_admin($params)
    {

        global $config;
        $server_host = $config["erlang_server"];
        $group_host = "conference." . $server_host;
        $date = date("Y-m-d H:i:s");

        $db = $this->db;

        $mobile = $params["mobile"];
        $participant = $params["participant"];
        $group_id = $params["group_id"];

        if (!$mobile) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }

        if (!$participant) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00283') /*Participants cannot be empty.*/);
        }

        if (!$group_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00113')/*Invalid Group ID.*/);
        }

        $db->where("group_id", $group_id);
        $group_room_result = $db->get("xun_muc_user");

        if (!$group_room_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00113')/*Invalid Group ID.*/);
        }

        $db->where("group_id", $group_id);
        $db->where("username", $mobile);
        $mobile_participant_result = $db->getOne("xun_muc_user");

        if (!$mobile_participant_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00437') /*Assigner is not a group participant.*/);
        }

        $db->where("group_id", $group_id);
        $db->where("username", participant);
        $participant_result = $db->getOne("xun_muc_user");

        if (!$participant_result) {
           return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00438') /*Participant mobile is not in group.*/);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00237') /*Participant Left.*/, "result" => $returnData);

    }

    public function group_chat_user_list($params)
    {

        $db = $this->db;
        $setting = $this->setting;

        $mobile = $params["mobile"];

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("username", $mobile);
        $user_groups = $db->get("xun_muc_user");

        $user_groups_array = array();

        foreach ($user_groups as $group) {
            $user_groups_array[] = $group["group_id"] . '@' . $group["group_host"];
        }

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00171') /*User's group listing.*/, 'groups' => $user_groups_array);
    }

    public function group_chat_user_common_groups($params)
    {
        $db = $this->db;

        $mobile = trim($params["mobile"]);
        $contact_mobile = trim($params["contact_mobile"]);

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }

        if ($contact_mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00291') /*Contact mobile number cannot be empty*/);
        }

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        // SELECT count(group_id), group_id from xun_muc_user where username in ('+0000', '+1111') group by group_id HAVING COUNT(*) > 1
        $db->where("username", [$mobile, $contact_mobile], "in");
        $db->groupBy("group_id");
        $db->having('COUNT(*) > 1');

        $xun_muc_user = $db->get("xun_muc_user");

        $user_groups_array = array();
        foreach ($xun_muc_user as $group) {
            $user_groups_array[] = $group["group_id"] . '@' . $group["group_host"];
        }

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00172') /*Groups in common.*/, 'groups' => $user_groups_array);
    }

    public function group_chat_details($params)
    {
        $db = $this->db;
        $general = $this->general;

        $mobile = trim($params["mobile"]);
        $group_id = trim($params["group_id"]);
        $group_host = trim($params["group_host"]);

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }

        if ($group_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty*/);
        }

        if ($group_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00293') /*Group host cannot be empty*/);
        }

        $db->where("old_id", $group_id);
        $db->where("host", $group_host);
        $xun_group_chat = $db->getOne("xun_group_chat");

        if (!$xun_group_chat) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00294') /*This group does not exist.*/, "errorCode" => -101);
        }

        // get encrypted_private_key
        $db->where("username", $mobile);
        $db->where("group_id", $group_id);
        $db->where("group_host", $group_host);
        $db->where("status", 1);
        $xun_encrypted_key = $db->getOne("xun_encrypted_key");

        $encrypted_private_key = "";

        if ($xun_encrypted_key) {
            $encrypted_private_key = $xun_encrypted_key["encrypted_key"];
        }

        // get group_public_key
        $db->where("key_user_id", $group_id);
        $db->where("key_host", $group_host);
        $db->where("status", 1);
        $xun_public_key = $db->getOne("xun_public_key");

        $group_public_key = "";

        if ($xun_public_key) {
            $group_public_key = $xun_public_key["key"];
        }

        $returnData["group_id"] = $group_id;
        $returnData["group_host"] = $group_host;
        $returnData["created_at"] = $general->formatDateTimeToIsoFormat($xun_group_chat["created_at"]);
        $returnData["group_creator"] = $xun_group_chat["creator_id"];
        $returnData["group_type"] = $xun_group_chat["type"];
        $returnData["encrypted_private_key"] = $encrypted_private_key;
        $returnData["public_key"] = $group_public_key;
        $returnData["description"] = $xun_group_chat["description"];
        $returnData["callback_url"] = $xun_group_chat["callback_url"];
        $returnData["welcome_message"] = $xun_group_chat["welcome_message"];
        $returnData["welcome_message_status"] = $xun_group_chat["welcome_message_status"];
        $returnData["invite_key"] = $xun_group_chat["invite_key"];
        $returnData["api_key"] = $xun_group_chat["api_key"];

        $update_data = [];
        
        if ($returnData["invite_key"] == "") {
            while (1) {

                $alphanumberic  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $invitekey         = substr(str_shuffle($alphanumberic), 0, 32);

                $db->where('invite_key', $invitekey);
                $result = $db->get('xun_group_chat');

                if (!$result) {
                    break;
                }

            }

            $update_data["invite_key"] = $invitekey;
            $returnData["invite_key"] = $invitekey;
        }

        if ($returnData["api_key"] == "") {
            while (1) {
                $api_key = $general->generateAlpaNumeric(32);

                $db->where('api_key', $api_key);
                $result = $db->get('xun_group_chat');

                if (!$result) {
                    break;
                }
            }

            $update_data["api_key"] = $api_key;
            $returnData["api_key"] = $api_key;
        }
        
        if (!empty($update_data)){
            $date = date("Y-m-d H:i:s");
            $update_data["updated_at"] = $date;
    
            $db->where("old_id", $group_id);
            $db->where("host", $group_host);
            $db->update("xun_group_chat", $update_data);
        }

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00174') /*Group details.*/, "result" => $returnData);
    }

    public function update_group_chat_callback_url($params)
    {
        $db = $this->db;

        $group_jid = trim($params["group_jid"]);
        // $group_host = trim($params["group_host"]);
        $username = trim($params["username"]);
        $callback_url = trim($params["callback_url"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty*/);
        }

        if ($group_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00298') /*Group JID cannot be empty*/);
        }

        $group_jid_arr = $this->get_xmpp_jid($group_jid);
        if ($group_jid_arr["code"] == 0) {
            return $group_jid_arr;
        }
        $group_id = $group_jid_arr["jid_user"];
        $group_host = $group_jid_arr["jid_host"];

        if($callback_url && !filter_var($callback_url, FILTER_VALIDATE_URL)){
            return array('code' => 0, 'message' => "FAILED", "errorCode" => -100, 'message_d' => $this->get_translation_message('E00288')/*Please enter a valid URL.*/);
        }
        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("old_id", $group_id);
        $db->where("host", $group_host);
        $xun_group_chat = $db->getOne("xun_group_chat");

        if (!$xun_group_chat) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00294') /*This group does not exist.*/);
        }

        $date = date("Y-m-d H:i:s");
        $update_data = [];
        $update_data["callback_url"] = $callback_url;
        $update_data["updated_at"] = $date;

        $db->where("id", $xun_group_chat["id"]);
        $db->update("xun_group_chat", $update_data);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);
    }

    public function update_group_chat_description($params)
    {
        $db  = $this->db;

        $group_jid = trim($params["group_jid"]);
        // $group_host = trim($params["group_host"]);
        $username = trim($params["username"]);
        $description = trim($params["description"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty*/);
        }

        
        if ($group_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00298') /*Group JID cannot be empty*/);
        }

        $group_jid_arr = $this->get_xmpp_jid($group_jid);
        if ($group_jid_arr["code"] == 0) {
            return $group_jid_arr;
        }
        $group_id = $group_jid_arr["jid_user"];
        $group_host = $group_jid_arr["jid_host"];

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("old_id", $group_id);
        $db->where("host", $group_host);
        $xun_group_chat = $db->getOne("xun_group_chat");

        if (!$xun_group_chat) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00294') /*This group does not exist.*/);
        }

        $date = date("Y-m-d H:i:s");
        $update_data = [];
        $update_data["description"] = $description;
        $update_data["updated_at"] = $date;

        $db->where("id", $xun_group_chat["id"]);
        $db->update("xun_group_chat", $update_data);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);
    }

    public function update_group_chat_welcome_message($params)
    {
        $db  = $this->db;

        $group_jid = trim($params["group_jid"]);
        $username = trim($params["username"]);
        $status = trim($params["welcome_message_status"]);
        $welcome_message = trim($params["welcome_message"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty*/);
        }

        if ($group_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00298') /*Group JID cannot be empty*/);
        }

        $group_jid_arr = $this->get_xmpp_jid($group_jid);
        if ($group_jid_arr["code"] == 0) {
            return $group_jid_arr;
        }
        $group_id = $group_jid_arr["jid_user"];
        $group_host = $group_jid_arr["jid_host"];
        
        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("old_id", $group_id);
        $db->where("host", $group_host);
        $xun_group_chat = $db->getOne("xun_group_chat");

        if (!$xun_group_chat) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00294') /*This group does not exist.*/);
        }

        $date = date("Y-m-d H:i:s");

        $status_bool = filter_var($status,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
        
        if(is_null($status_bool)){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00221') /*Invalid status*/);
        }
        
        $update_data = [];
        $update_data["welcome_message_status"] = $status_bool;
        $update_data["welcome_message"] = $welcome_message;
        $update_data["updated_at"] = $date;

        $db->where("id", $xun_group_chat["id"]);
        $db->update("xun_group_chat", $update_data);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);
    }

    
    public function group_chat_join_invite_link($params)
    {
        global $config;
        $server_host = $config["erlang_server"];
        $group_host = "conference." . $server_host;
        $date = date("Y-m-d H:i:s");

        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $invite_key = trim($params["invite_link"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty*/);
        }

        if ($invite_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00300') /*Invite link cannot be empty.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025')/*User does not exist*/);
        }

        // get group details
        $group_chat_service = new GroupChatService($db);
        $xun_group_chat = $group_chat_service->getGroupChatByInviteKey($invite_key);

        if(!$xun_group_chat){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00303')/*Invalid invite link.*/, "errorCode" =>  -101);
        }

        // check if user is in group

        $group_id = $xun_group_chat["old_id"];
        // $groupHost = $xun_group_chat["group_host"];

        $groupChatObj = new stdClass();
        $groupChatObj->username = $username;
        $groupChatObj->groupId = $group_id;
        $groupChatObj->groupHost = $group_host;

        $xun_muc_user = $group_chat_service->getGroupChatParticipant($groupChatObj);

        if(!empty($xun_muc_user)){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00304')/*You're already in this group.*/, "errorCode" => -102);
        }

        //  add participant to muc
        $muc_row_id = $group_chat_service->insertMucUser($groupChatObj);

        if(!$muc_row_id){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200')/*Something went wrong. Please try again.*/, "errorCode" => -103);
        }
        
        //build return data
        $public_key_result = $group_chat_service->getGroupPublicKey($groupChatObj);
        
        $group_public_key = !empty($public_key_result) ?  $public_key_result["key"] : "";

        $group_created_at = $xun_group_chat["created_at"];
        $returnData = [];
        $returnData["created_at"] = $general->formatDateTimeToIsoFormat($group_created_at);
        $returnData["group_id"] = $group_id;
        $returnData["group_host"] = $group_host;
        $returnData["group_creator"] = $xun_group_chat["creator_id"];
        $returnData["group_type"] = $xun_group_chat["type"];
        $returnData["public_key"] = $group_public_key;
        $returnData["username"] = $username;
        $returnData["invite_key"] = $xun_group_chat["invite_key"];
        $returnData["api_key"] = $xun_group_chat["api_key"];
        $returnData["description"] = $xun_group_chat["description"];
        $returnData["callback_url"] = $xun_group_chat["callback_url"];
        $returnData["welcome_message"] = $xun_group_chat["welcome_message"];
        $returnData["welcome_message_status"] = $xun_group_chat["welcome_message_status"];
        
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00177')/*You've joined using this group's invite link*/, "result" => $returnData);
    }

    public function user_contactlist_update($params)
    {
        $db = $this->db;
        $general = $this->general;

        $mobile = trim($params["mobile"]);
        $contact_list = $params["contact_list"];
        $erlang_server = trim($params["server"]);

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }

        if (!is_array($contact_list)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00309') /*Contact list must be an array.*/);
        }

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $new_contact_list = array();
        $registered_contact_list = array();

        $now = date("Y-m-d H:i:s");

        //  delete xun_user_contact record
        $db->where("username", $mobile);
        $db->where("server_host", $erlang_server);
        $db->delete("xun_user_contact");

        foreach ($contact_list as $contact_mobile) {
            // check if it's a valid mobile
            $mobileNumberInfo = $general->mobileNumberInfo($contact_mobile, null);
            if ($mobileNumberInfo["isValid"] == 0) {
                continue;
            }

            $new_contact_mobile = str_replace("-", "", $mobileNumberInfo["phone"]);
            if ($mobile == $new_contact_mobile) {
                continue;
            }

            $db->where("username", $new_contact_mobile);

            $xun_user = $db->getOne("xun_user");

            if (!$xun_user) {
                continue;
            }

            $db->where("username", $mobile);
            $db->where("server_host", $erlang_server);
            $db->where("contact_mobile", $new_contact_mobile);
            $xun_user_contact = $db->getOne("xun_user_contact");

            if (!$xun_user_contact) {
                $new_contact_list[] = $new_contact_mobile;

                $user_contact_fields = array("username", "server_host", "contact_mobile", "created_at", "updated_at");
                $user_contact_values = array($mobile, $erlang_server, $new_contact_mobile, $now, $now);

                $insertData = array_combine($user_contact_fields, $user_contact_values);
                $db->insert("xun_user_contact", $insertData);
            }
        }

        $returnData["registered_contact"] = $new_contact_list;
        $returnData["mobile"] = $mobile;
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00178') /*Registered contact list.*/, "xun_user_contact" => $returnData);
    }

    public function get_employee_name($params)
    {
        $db = $this->db;

        $employee_id = trim($params["employee_id"]);

        if ($employee_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00011') /*Employee ID cannot be empty*/);
        }

        $db->where("old_id", $employee_id);
        $db->where("status", 1);

        $xun_employee = $db->getOne("xun_employee");

        $employee_name = "";

        if ($xun_employee) {
            $employee_name = $xun_employee["name"];
        }

        $returnData["employee_name"] = $employee_name;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00179') /*Employee name*/, "result" => $returnData);
    }

    public function get_employee_details($params)
    {
        $db = $this->db;

        $employee_id = trim($params["employee_id"]);

        if ($employee_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00011') /*Employee ID cannot be empty*/);
        }

        $db->where("old_id", $employee_id);
        $db->where("status", 1);

        $xun_employee = $db->getOne("xun_employee");

        if (!$xun_employee) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00312') /*Invalid Employee*/, "errorCode" => -100);
        }

        $returnData["xun_employee"] = $this->compose_xun_employee($xun_employee);
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00180') /*Employee details.*/, "result" => $returnData);
    }

    public function delete_user($params)
    {
        $db = $this->db;

        global $xunXmpp, $xunUser;
        global $xunBusiness;

        $mobile = $params["mobile"];

        $date = date("Y-m-d H:i:s");

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        }

        $db->where("username", $mobile);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/, "errorCode" => -100);
        }

        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];

        $device_ip_arr = $xunUser->get_device_os_ip($user_id, $mobile);
        $ip = $device_ip_arr["ip"];
        $device_os = $device_ip_arr["device_os"];
        $now = date("Y-m-d H:i:s");

        $updateXunUser["disabled"] = 1;
        $updateXunUser["disable_type"] = "Delete";
        $updateXunUser["updated_at"] = $now;
        $db->where("username", $mobile);
        $db->update("xun_user", $updateXunUser);

        //remove the user at user device
        $db->where("mobile_number", $mobile);
        $db->delete("xun_user_device");

        //remove user at xun business follow
        $db->where("username", $mobile);
        $db->delete("xun_business_follow");

        //remove user at xun business follow
        $db->where("username", $mobile);
        $db->delete("xun_business_follow_message");

        // public key
        $db->where("key_user_id", $mobile);
        $db->delete("xun_public_key");

        // encrypted_key
        $db->where("username", $mobile);
        $db->delete("xun_encrypted_key");

        // xun_user_chat_preference
        $db->where("username", $mobile);
        $db->delete("xun_user_chat_preference");

        // xun_user_contact
        $db->where("username", $mobile);
        $db->delete("xun_user_contact");

        // xun_crypto_user_address
        $updateData = [];
        $updateData["updated_at"] = $date;
        $updateData["deleted"] = 1;
        $updateData["active"] = 0;

        $db->where("user_id", $user_id);
        $db->update("xun_crypto_user_address", $updateData);

        //  remove xun employee
        //  remove xun_business_tag_employee
        //  call erlang -> send remove employee message

        $db->where("mobile", $mobile);
        $db->where("status", 1);

        $xun_employee = $db->get("xun_employee");

        // livechat
        // send remove business_tag_employee message
        foreach ($xun_employee as $employee) {
            $business_id = $employee["business_id"];
            $employee_id = $employee["old_id"];
            $employee_role = $employee["role"];

            // delete employee and business_tag_employee
            $erlangReturn = $xunBusiness->delete_business_employee($business_id, $employee_id, $mobile, $employee_role);
        }

        // remove from groupchat
        // call erlang to remove from group chat -> send notification
        $db->where("username", $mobile);
        $user_groups = $db->get("xun_muc_user");

        $user_groups_arr = [];
        foreach ($user_groups as $user_group) {
            $group["group_id"] = $user_group["group_id"];
            $group["group_host"] = $user_group["group_host"];

            $user_groups_arr[] = $group;
        }

        // call erlang API
        $erlangReturn = $xunXmpp->leave_group_chat($mobile, $user_groups_arr);

        $db->where("username", $mobile);
        $db->delete("xun_muc_user");

        
        $db->where("mobile", $mobile);
        $db->orderBy("id", "DESC");
        $user_type = $db->getValue("xun_user_verification", "user_type");

        $user_country_info_arr = $xunUser->get_user_country_info([$mobile]);
        $user_country_info = $user_country_info_arr[$mobile];
        $user_country = $user_country_info["name"];


        $content .= "Username: " . $nickname . "\n";
        $content .= "Phone number: " . $mobile . "\n";
        $content .= "IP: " . $ip . "\n";
        $content .= "Country: " . $user_country . "\n";
        $content .= "Device: " . $device_os . "\n";
        $content .= "Type Of User: " . $user_type . "\n";
        $content .= "Status: Success\n";
        $content .= "Time: " . date("Y-m-d H:i:s");

        $tag = "Delete Account";
        $erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = array();
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);  

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00182') /*User deleted*/);
    }

    public function update_profile_photo_privacy_setting($params)
    {

        $db = $this->db;
        $general = $this->general;

        $mobile = trim($params["mobile"]);
        $allowed_users = trim($params["allowed_users"]);
        $date = date("Y-m-d H:i:s");

        $mobile = trim($params["mobile"]);

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }

        if ($allowed_users == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00313') /*Allowed users cannot be empty*/);
        }

        switch ($allowed_users) {
            case "nobody":
                break;
            case "everyone":
                break;
            case "contacts":
                break;

            default:
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00315') /*Invalid value for allowed users.*/);
        }

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("mobile_number", $mobile);
        $privacy_result = $db->getOne("xun_user_privacy_settings");

        if (!$privacy_result) {
            $fields = array("mobile_number", "profile_picture", "created_at", "updated_at");
            $values = array($mobile, $allowed_users, $date, $date);
            $insertData = array_combine($fields, $values);

            $db->insert("xun_user_privacy_settings", $insertData);
        } else {
            $updateData["profile_picture"] = $allowed_users;
            $updateData["updated_at"] = $date;

            $db->where("id", $privacy_result["id"]);
            $db->update("xun_user_privacy_settings", $updateData);
        }

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00184') /*Privacy settings updated.*/);

    }

    public function get_privacy_setting($params)
    {

        $db = $this->db;
        $general = $this->general;

        $mobile = $params["mobile"];

        $mobile = trim($params["mobile"]);

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("mobile_number", $mobile);
        $privacy_result = $db->getOne("xun_user_privacy_settings");

        $privacy_settings = (object) [];
        if ($privacy_result) {
            $privacy_settings->profile_picture = $privacy_result["profile_picture"];
        }

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00185') /*Privacy setting.*/, "privacy" => $privacy_settings);

    }

    public function update_user_public_key($params)
    {

        global $config;

        $db = $this->db;
        $general = $this->general;

        $mobile = $params["mobile"];
        $public_key = $params["public_key"];
        $date = date("Y-m-d H:i:s");
        $server_host = $config["erlang_server"];

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }

        if ($public_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00286') /*Public Key cannot be empty*/);
        }

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("`key`", $public_key);
        $db->where("key_user_id", $mobile);
        $db->where("status", "1");
        $result = $db->getOne("xun_public_key");

        if ($result) {
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00186') /*Key already exist.*/);
        }

        $updateData["status"] = 0;
        $updateData["updated_at"] = $date;

        $db->where("username", $mobile);
        $db->where("status", "1");
        $db->update("xun_encrypted_key", $updateData);

        $db->where("key_user_id", $mobile);
        $db->where("status", "1");
        $db->update("xun_public_key", $updateData);

        $new_id = $db->getNewID();

        $fields = array("old_id", "key_user_id", "key_host", "key", "status", "created_at", "updated_at");
        $values = array($new_id, $mobile, $server_host, $public_key, "1", $date, $date);
        $insertData = array_combine($fields, $values);

        $db->insert("xun_public_key", $insertData);

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00187')/*User Public Key Updated.*/);

    }

    public function get_user_public_key($params)
    {

        $db = $this->db;
        $general = $this->general;

        $mobile = $params["mobile"];
        $jid = $params["jid"];
        $jid_array = explode("@", $jid);
        $key_user_id = $jid_array[0];
        $key_host = $jid_array[1];

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }

        if ($jid == '') {
           return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00316') /*JID cannot be empty*/);
        }

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $updateData["status"] = 0;
        $updateData["updated_at"] = $date;

        $db->where("key_user_id", $key_user_id);
        $db->where("key_host", $key_host);
        $db->where("status", "1");
        $key_result = $db->getOne("xun_public_key");

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00188')/*User Public Key.*/, "jid" => $jid, "public_key" => $key_result["key"]);

    }

    public function update_user_encrypted_key($params)
    {

        global $config;

        $db = $this->db;
        $general = $this->general;

        $mobile = $params["mobile"];
        $chat_room_jid = $params["chat_room_jid"];
        $jid_array = explode("@", $chat_room_jid);
        $group_id = $jid_array[0];
        $group_host = $jid_array[1];
        $encrypted_key = $params["encrypted_private_key"];
        $date = date("Y-m-d H:i:s");

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }

        if ($group_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty*/);
        }

        if ($encrypted_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00286')/*Group Public key cannot be empty.*/);
        }

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("encrypted_key", $encrypted_key);
        $db->where("group_id", $group_id);
        $db->where("group_host", $group_host);
        $result = $db->getOne("xun_encrypted_key");
        if ($result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('B00186') /*Key already exist.*/);
        }

        $updateData["status"] = 0;
        $updateData["updated_at"] = $date;

        //update current key status to 0
        $db->where("username", $mobile);
        $db->where("status", "1");
        $db->where("group_id", $group_id);
        $db->where("group_host", $group_host);
        $db->update("xun_encrypted_key", $updateData);

        $fields = array("username", "group_id", "group_host", "encrypted_key", "status", "created_at", "updated_at");
        $values = array($mobile, $group_id, $group_host, $encrypted_key, "1", $date, $date);
        $insertData = array_combine($fields, $values);

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00189') /*User Encrypted Key Updated.*/);

    }

    public function get_user_encrypted_key($params)
    {

        $db = $this->db;
        $general = $this->general;

        $mobile = $params["mobile"];
        $chat_room_jid = $params["chat_room_jid"];
        $jid_array = explode("@", $chat_room_jid);
        $group_id = $jid_array[0];
        $group_host = $jid_array[1];

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }

        if ($group_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty*/);
        }

        $db->where("username", $mobile);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("username", $mobile);
        $db->where("status", "1");
        $db->where("group_id", $group_id);
        $db->where("group_host", $group_host);
        $key_result = $db->getOne("xun_encrypted_key");

        $db->where("key_user_id", $group_id);
        $db->where("key_host", $group_host);
        $db->where("status", "1");
        $public_key_result = $db->getOne("xun_public_key");

        $encrypted_private_key = $key_result["encrypted_key"] ? $key_result["encrypted_key"] : "";
        $public_key = $public_key_result["key"] ? $public_key_result["key"] : "";

        return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('B00190') /*User Encrypted key.*/, "encrypted_private_key" => $encrypted_private_key, "group_public_key" => $public_key, "chat_room_jid" => $chat_room_jid);

    }

    public function check_request_vcard_permission($params)
    {
        $db = $this->db;

        $vcard_requestor = trim($params["vcard_requestor"]);
        $vcard_requestee = trim($params["vcard_requestee"]);

        if ($vcard_requestor == '') {
           return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00321')/*VCard Requestor cannot be empty*/);
        }
        if ($vcard_requestee == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00322')/*VCard Requestee cannot be empty*/);
        }

        $db->where("username", $vcard_requestor);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("mobile_number", $vcard_requestee);
        $xun_user_privacy_settings = $db->getOne("xun_user_privacy_settings");

        $profile_picture_setting = $xun_user_privacy_settings ? $xun_user_privacy_settings["profile_picture"] : "";

        if ($profile_picture_setting == "contacts") {
            // check if requestor is a contact of requestee
            $db->where("username", $vcard_requestee);
            $db->where("contact_mobile", $vcard_requestor);

            $xun_contact = $db->getOne("xun_user_contact");
        }

        $is_contact = $xun_contact ? true : false;

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00191') /*Profile Picture settings.*/, "result" => array("profile_picture" => $profile_picture_setting, "is_contact" => $is_contact));
    }

    public function business_message_forward($params)
    {
        $db = $this->db;
        $post = $this->post;

        $business_id = trim($params["business_id"]);
        $username = trim($params["username"]);
        $tag = trim($params["tag"]);
        $message = trim($params["message"]);
        $reply_message = trim($params["reply_message"]);
        $hidden_message = trim($params["hidden_message"]);

        $db->where("username", $username);
        $result = $db->getOne("xun_user");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("user_id", $business_id);
        $business_result = $db->getOne("xun_business");

        if (!$business_result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        $db->where("business_id", $business_id);
        $db->where("tag", $tag);
        $db->where("status", 1);
        $xun_busines_forward_message = $db->getOne("xun_business_forward_message");

        $forward_url = $xun_busines_forward_message["forward_url"];
        if ($forward_url) {
            $callback_url = $forward_url;
        }else{
            $xun_business_service = new XunBusinessService($db);
            $xun_business = $xun_business_service->getBusinessByBusinessID($business_id, "id, message_callback_url");

            if($xun_business && $xun_business["message_callback_url"]){
                $callback_url = $xun_business["message_callback_url"];
            }
        }

        if($callback_url){
            $new_params = [];
            $new_params["business_id"] = (string) $business_id;
            $new_params["reply_from"] = $username;
            $new_params["tag"] = $tag;
            $new_params["message"] = $message;
            $new_params["reply_message"] = $reply_message;
            $new_params["extra_message"] = $hidden_message;

            $command = "messageCallback";
            $post_params = array(
                "command" => $command,
                "params" => $new_params
            );

            $postReturn = $post->curl_post($callback_url, $post_params, 0);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00144') /*Success.*/, "postReturn" => $postReturn);
    }

    public function archive_message($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $server_host = trim($params["server_host"]);
        $bare_peer = trim($params["bare_peer"]);
        $peer = trim($params["peer"]);
        $xml = trim($params["xml"]);
        $body = trim($params["body"]);
        $kind = trim($params["kind"]);
        $timestamp = trim($params["timestamp"]);
        $nick = trim($params["nick"]);
        $direction = trim($params["direction"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty*/);
        }
        if ($server_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00326') /*Server host cannot be empty*/);
        }
        if ($bare_peer == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00327') /*Bare peer cannot be empty*/);
        }
        if ($peer == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00328') /*Peer cannot be empty*/);
        }
        if ($xml == '') {
           return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00330') /*XML cannot be empty*/);
        }
        if ($timestamp == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00331') /*Timestamp cannot be empty*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $insertData = array(
            "username" => $username,
            "server_host" => $server_host,
            "bare_peer" => $bare_peer,
            "peer" => $peer,
            "xml" => $xml,
            "txt" => $body,
            "kind" => $kind,
            "nick" => $nick,
            "direction" => $direction,
            "timestamp" => $timestamp,
            "created_at" => date("Y-m-d H:i:s"),
        );

        $db->insert("xun_message_archive", $insertData);

       return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00192') /*Message archived*/);
    }

    public function get_chatroom_message_archive($params)
    {
        global $setting;
        $db = $this->db;
        $general = $this->general;

        $page_limit = $setting->systemSetting["appsPageLimit"];

        $id = trim($params["last_id"]);
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = strtoupper(trim($params["order"]));

        $username = trim($params["username"]);
        $server_host = trim($params["server_host"]);
        $bare_peer = trim($params["chatroom_jid"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty*/);
        }
        if ($server_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00326') /*Server host cannot be empty*/);
        }
        if ($bare_peer == '') {
           return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00270') /*Chatroom JID cannot be empty*/);
        }

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        if ($id) {
            if ($order == 'DESC') {
                $db->where("id", $id, '<');
            } else {
                $db->where("id", $id, '>');
            }
        }

        $db->where("username", $username);
        $db->where("server_host", $server_host);
        $db->where("bare_peer", $bare_peer);

        $start_limit = 0;
        $limit = array($start_limit, $page_size);

        $copyDb = $db->copy();
        $db->orderBy("created_at", $order);

        $result = $db->get("xun_message_archive", $limit, "id, timestamp, xml, direction");
        $return_message = "Advertisement listing.";
        $result = $result ? $result : array();

        $totalRecord = $copyDb->getValue("xun_message_archive", "count(id)");

        $returnData["result"] = $result;
        // $returnData["totalRecord"] = $totalRecord;
        // $returnData["numRecord"] = (int) $page_size;
        // $returnData["totalPage"] = ceil($totalRecord / $page_size);
        // $returnData["pageNumber"] = $page_number;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00193') /*Chat room message archive*/, "data" => $returnData);

    }

    public function request_wallet_address_otp($params)
    {
        global $xunXmpp;   
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $email = trim($params["email"]);
        $address = trim($params["crypto_address"]);
        $encrypted_key = trim($params["encrypted_private_key"]);
        $business_id = trim($params["business_id"]);
        $resend_count = 0;

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        if ($email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00211') /*Email cannot be empty.*/);
        }
        if ($address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00314') /*address cannot be empty.*/);
        }
        if ($encrypted_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00332') /*Encrypted private key cannot be empty.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");
        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];
        if (!empty($business_id)) {
            $user_id = $business_id;
            $db->where("user_id", $user_id);
            $xun_business = $db->getOne("xun_business");
            $business_name = $xun_business["name"];
        }

        $db->where("user_id", $user_id);
        $db->where("address", $address);
        $crypto_address_data = $db->getOne("xun_crypto_user_address_verification", "id, code, verified");

        $notification_tag = "Request Email Verification";

        $resend_count = $db->where("id", $crypto_address_data["id"])->getValue("xun_crypto_user_address_verification", "resend_times");
        if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
            $status = "Failed";
            $message = "Invalid email address";
            $this->send_wallet_backup_notification($resend_count, $notification_tag, $xun_user, $xun_business, $email, $status, $message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00041') /*Please enter a valid email address.*/);
        }


        $otp_code = $general->generateRandomNumber(5);

        $date = date("Y-m-d H:i:s");
        if (!$crypto_address_data) {
            $insertData = array(
                "user_id" => $user_id,
                "address" => $address,
                "email" => $email,
                "code" => $otp_code,
                "verified" => 0,
                "resend_times" => 0,
                "created_at" => $date,
                "updated_at" => $date,
            );

            $db->insert("xun_crypto_user_address_verification", $insertData);

        } else if ($crypto_address_data["verified"] == 1) {
            $this->send_wallet_backup_notification($resend_count, $notification_tag, $xun_user, $xun_business, $email, "Success");
            // return array('code' => 0, 'message' => "FAILED", 'message_d' => "This address is verified.", "errorCode" => -100);
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/);

        } else {
            $updateData = [];
            $updateData["email"] = $email;
            $updateData["code"] = $otp_code;
            $updateData["updated_at"] = $date;
            $resend_count++;
            $updateData["resend_times"] = $resend_count;
            $db->where("id", $crypto_address_data["id"]);
            $db->update("xun_crypto_user_address_verification", $updateData);
        }

        $emailParams = $this->get_backup_wallet_email_content($username, $encrypted_key, $otp_code, $business_name);
        // TODO:
        // send email via AWS SNS
        // $this->send_wallet_otp_email($email, $encrypted_key, $otp_code);
        $email_res = $this->send_wallet_otp_email_ses($email, $emailParams);

        if ($email_res["code"] == 1) {
            $status = "Success";

        } else {
            $status = "Failed";
            $message = "Error sending email";
        }
        // send notification
        $this->send_wallet_backup_notification($resend_count, $notification_tag, $xun_user, $xun_business, $email, $status, $message, $otp_code);

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00012') /*Success*/);
    }

    public function verify_wallet_address_otp($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $address = trim($params["crypto_address"]);
        $code = trim($params["verification_code"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        if ($address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00314') /*address cannot be empty.*/);
        }
        if ($code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00060') /*Verification code cannot be empty.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");
        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];
        $notification_tag = "Email Verification";

        // $db->where("user_id", $user_id);
        $db->where("address", $address);
        $crypto_address_data = $db->getOne("xun_crypto_user_address_verification", "id, user_id, code, resend_times, verified, email");
        
        $db->where("user_id", $crypto_address_data["user_id"]);
        $xun_business = $db->getOne("xun_business");

        if (empty($crypto_address_data)) {
            $notification_message = "Invalid address.";
            $status = "Invalid";
            $this->send_wallet_backup_notification($resend_count, $notification_tag, $xun_user, $xun_business, $email, $status, $notification_message, $code);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00278') /*Invalid address.*/, "errorCode" => -100);
        }

        $email = $crypto_address_data["email"];

        if ($crypto_address_data["verified"] == 1) {
            $return_arr = array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00194') /*Address verified.*/);

            $status = "Valid";

            $this->send_wallet_backup_notification($resend_count, $notification_tag, $xun_user, $xun_business, $email, $status, $notification_message, $code);

            return $return_arr;
        }

        if ($crypto_address_data["code"] == $code) {
            $update_data = [];
            $update_data["verified"] = 1;
            $update_data["updated_at"] = date("Y-m-d H:i:s");

            $db->where("id", $crypto_address_data["id"]);
            $db->update("xun_crypto_user_address_verification", $update_data);
            $return_arr = array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00194') /*Address verified.*/);
        } else {
            $notification_message = "Incorrect code.";
            $return_arr = array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00333') /*Incorrect code.*/, "errorCode" => -100);
        }

        $status = $return_arr["code"] == 1 ? "Valid" : "Invalid";

        $this->send_wallet_backup_notification($resend_count, $notification_tag, $xun_user, $xun_business, $email, $status, $notification_message, $code);

        return $return_arr;
    }

    public function get_wallet_verification_status($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if (empty($business_id)) {
            $user_id = $xun_user["id"];
        } else {
            $user_id = $business_id;
        }

        $db->where("a.user_id", $user_id);
        $db->where("a.active", 1);
        $db->where("a.address_type", "personal");
        // $db->where("b.user_id", $user_id);
        $db->where("b.verified", 1);
        $db->join("xun_crypto_user_address_verification b", "a.address=b.address", "LEFT");
        $address_verification = $db->getValue("xun_crypto_user_address a", "count(a.id)");

        $has_verified = $address_verification === 1 ? true : false;

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00195') /*Wallet backup status.*/, "data" => array("has_verified" => $has_verified));
    }

    private function send_wallet_backup_notification($resend_count, $tag, $xun_user, $xun_business = null, $email, $status, $message = null, $input_code = null)
    {
        global $xunXmpp, $xunUser, $xun_numbers;
        $db = $this->db;
        $general = $this->general;

        $username = $xun_user["username"];
        $user_id = $xun_user["id"];
        $user_nickname = $xun_user["nickname"];

        $business_id = $xun_business["user_id"];
        $business_name = $xun_business["name"];

        $db->where("mobile_number", $username);
        $user_device_info = $db->getOne("xun_user_device", "id, mobile_number, os");

        if ($user_device_info) {
            $device_os = $user_device_info["os"];
            if($device_os == 1){$device_os = "Android";}
            else if ($device_os == 2){$device_os = "iOS";}

        } else {
            $device_os = "";
        }

        $user_country_info_arr = $xunUser->get_user_country_info([$username]);
        $user_country_info = $user_country_info_arr[$username];
        $user_country = $user_country_info["name"];

        $db->where("user_id", $user_id);
        $db->where("name", "lastLoginIP");
        $ip = $db->getValue("xun_user_setting", "value");

        $db->where("mobile", $username);
        $db->orderBy("id", "DESC");
        $user_type = $db->getValue("xun_user_verification", "user_type");

        if (!$xun_business){
            $content .= "User\n";
            $content .= "Username: " . $user_nickname . "\n";
            $content .= "Phone number: " . $username . "\n";
            $content .= "IP: " . $ip . "\n";
            $content .= "Country: " . $user_country . "\n";
            $content .= "Device: " . $device_os . "\n";
        }else{
            $content .= "Business\n";
            $content .= "Business ID: " . $business_id ."\n";
            $content .= "Business Name: " . $business_name . "\n";
        }
        $resend_count = $resend_count ? $resend_count : 0;
        if($tag == "Request Email Verification"){
            $content .= "\nResend Count: " . $resend_count . "\n";
            if ($email) {
                $content .= "Email Address: " . $email . "\n";
            }
            if ($input_code){
                $content .= "Code: " . $input_code . "\n";
            }
            $content .= "Status: " . $status . "\n";
        }
        // $content .= "Version: " . $user_app_version . "\n";
        if ($tag == "Email Verification"){
            if(!$xun_business){
                $content .= "Type of User: " . $user_type . "\n";
            }
            $content .= "\nCode Entered: " . $input_code . "\n";
            if ($message) {
                $content .= "Message: " . $message . "\n";
            }
            $content .= "Validity: " . $status . "\n";
        }
        $content .= "Time: " . date("Y-m-d H:i:s") . "\n";

        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);
        return $xmpp_result;
    }

    // xun/crypto/user
    public function update_user_external_address($params)
    {
        $general = $this->general;
        $db = $this->db;

        $username = trim($params["username"]);
        $address = trim($params["address"]);
        $subject = trim($params["subject"]);
        $description = trim($params["description"]);
        $amount = trim($params["amount"]);
        $wallet_type = trim($params["wallet_type"]);
        $type = trim($params["type"]);
        $minimum = trim($params["minimum"]);
        $maximum = trim($params["maximum"]);
        $ends_on = trim($params["ends_on"]);
        $business_id = trim($params["business_id"]);

        /**
         * group
         * - minimum deal
         * - maximum deal
         * - ends on
         */

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        if ($address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00314') /*address cannot be empty.*/);
        }
        // if ($description == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "description cannot be empty");
        // }
        // if ($subject == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "subject cannot be empty");
        // }
        // if ($amount == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "amount cannot be empty");
        // }
        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
        }
        if ($type == '') {
            $type = "normal";
        }

        if (!in_array($type, ["normal", "group", "bid"])) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00334') /*Invalid type*/);
        }

        if ($type == "group") {
            if ($minimum == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00335') /*Minimum cannot be empty*/);
            }
            if ($maximum == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00336') /*Maximum cannot be empty*/);
            }
            if ($ends_on == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00337') /*Ends on cannot be empty*/);
            }
        }

        if ($business_id == '') {
            $db->where("username", $username);
        } else {
            $db->where("id", $business_id);
        }

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }
        $user_id = $xun_user["id"];

        $db->where("user_id", $user_id);
        $db->where("active", "1");
        $db->where("address_type", "personal");
        $user_address = $db->getOne("xun_crypto_user_address");

        $date = date("Y-m-d H:i:s");

        $wallet_type = strtolower($wallet_type);

        $db->where("currency_id", $wallet_type);
        $db->orWhere("symbol", $wallet_type);
        $currency_data = $db->getOne("xun_marketplace_currencies", "id, currency_id, type");

        if(!$currency_data || $currency_data["type"] != 'cryptocurrency'){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00340') /*Invalid wallet type.*/, "errorCode" => -100);
        }

        $wallet_type = $currency_data["currency_id"];

        $db->where("currency_id", $wallet_type);
        $db->orWhere("symbol", $wallet_type);
        $currency_data = $db->getOne("xun_marketplace_currencies", "id, currency_id, type");

        if(!$currency_data || $currency_data["type"] != 'cryptocurrency'){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00340') /*Invalid wallet type.*/, "errorCode" => -100);
        }

        $wallet_type = $currency_data["currency_id"];

        $insert_data = array(
            "user_id" => $user_id,
            "address" => $address,
            "internal_address" => $user_address["address"],
            "amount" => $amount,
            "wallet_type" => $wallet_type,
            "description" => $description,
            "subject" => $subject,
            "type" => $type,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $address_id = $db->insert("xun_crypto_user_external_address", $insert_data);

        if (!$address_id) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200')/*Something went wrong. Please try again.*/);
        }

        if ($type == "group") {
            $insert_group_data = array(
                "address_id" => $address_id,
                "minimum" => $minimum,
                "maximum" => $maximum,
                "ends_on" => $ends_on,
                "created_at" => $date,
                "updated_at" => $date,
            );

            $row_id = $db->insert("xun_crypto_user_group_address_details", $insert_group_data);
        }

        $returnData = array("created_at" => $general->formatDateTimeToIsoFormat($date));
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00196') /*Address updated*/, "data" => $returnData);
    }

    public function get_user_external_address_listing($params)
    {
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $type = trim($params["type"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($business_id == '') {
            $db->where("username", $username);
        } else {
            $db->where("id", $business_id);
        }

        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }
        $user_id = $xun_user["id"];

        $db->where("user_id", $user_id);
        $db->where("active", "1");
        $db->where("address_type", "personal");
        $user_address = $db->getOne("xun_crypto_user_address");

        // select * from xun_php.xun_crypto_user_external_address p join (SELECT max(created_at) as created_at, id, description, address FROM xun_php.xun_crypto_user_external_address where user_id = 9 group by address) as a on p.address=a.address and p.created_at = a.created_at;

        $sq = $db->subQuery("sq");
        $sq->where("user_id", $user_id);
        $sq->where("internal_address", $user_address["address"]);
        if ($type != '') {
            $sq->where("type", $type);
        }
        $sq->groupBy("address");
        $sq->get("xun_crypto_user_external_address", null, "max(created_at) as created_at, address");

        $db->join($sq, "a.address=sq.address and a.created_at = sq.created_at", "INNER");
        $db->orderBy("a.id", "DESC");
        $user_external_addresses = $db->get("xun_crypto_user_external_address a", null, "a.id, a.address, a.amount, a.wallet_type, a.description, a.subject, a.created_at, a.type");

        $normal_address_arr = array();
        $group_address_arr = array();

        for ($i = 0; $i < sizeof($user_external_addresses); $i++) {
            $data = $user_external_addresses[$i];
            $data["created_at"] = $general->formatDateTimeToIsoFormat($data["created_at"]);

            $data_amount = $data["amount"];
            if (bccomp((string) $data_amount, "1", 8) >= 0) {
                $trimmed_amount = (float) $data_amount;
            } else if ($data_amount == 0) {
                $trimmed_amount = 0;
            } else {
                $trimmed_amount = rtrim(sprintf("%0.8f", $data_amount), "0");
            }
            $data["amount"] = (string) $trimmed_amount;

            if ($data["type"] == "normal") {
                $normal_address_arr[] = $data;
            } elseif ($data["type"] == "group") {
                $group_address_arr[] = $data;
            }
        }

        if (!empty($group_address_arr)) {
            $group_address_ids = array_column($group_address_arr, "id");
            $db->where("address_id", $group_address_ids, "in");
            $group_address_details = $db->map("address_id")->ObjectBuilder()->get("xun_crypto_user_group_address_details", null, "address_id, minimum, maximum, ends_on");

            for ($i = 0; $i < sizeof($group_address_arr); $i++) {
                $address_data = $group_address_arr[$i];
                $record_id = $address_data["id"];

                $details_data = $group_address_details[$record_id];
                $ends_on = $details_data->ends_on;
                $ends_on_date = date("Y-m-d H:i:s", $ends_on);
                $ends_on_iso_date = $general->formatDateTimeToIsoFormat($ends_on_date);

                $address_data["minimum"] = $details_data->minimum;
                $address_data["maximum"] = $details_data->maximum;
                $address_data["ends_on"] = $ends_on_iso_date;
                $group_address_arr[$i] = $address_data;
            }
        }

        if ($type == "group") {
            $return_data = array(
                "group_addresses" => $group_address_arr,
            );
        } elseif ($type == "normal") {
            $return_data = array(
                "addresses" => $normal_address_arr,
            );
        } else {
            $return_data = array(
                "addresses" => $normal_address_arr,
                "group_addresses" => $group_address_arr,
            );
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00197') /*Address listing.*/, "data" => $return_data);
    }

    public function get_user_external_address_details($params)
    {
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $address = trim($params["address"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00314') /*address cannot be empty.*/);
        }

        $db->where("username", $username);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if ($business_id == '') {
            $user_id = $xun_user["id"];
        } else {
            $user_id = $business_id;
        }

        $db->where("user_id", $user_id);
        $db->where("address", $address);
        $db->orderBy("created_at", "DESC");
        $user_external_address = $db->getOne("xun_crypto_user_external_address", "id, address, description, subject, amount, wallet_type, created_at, type");

        if (!empty($user_external_address)) {
            $user_external_address["created_at"] = $general->formatDateTimeToIsoFormat($user_external_address["created_at"]);
            $data_amount = $user_external_address["amount"];
            if (bccomp((string) $data_amount, "1", 8) >= 0) {
                $trimmed_amount = (float) $data_amount;
            } else if ($data_amount == 0) {
                $trimmed_amount = 0;
            } else {
                $trimmed_amount = rtrim(sprintf("%0.8f", $data_amount), "0");
            }
            $user_external_address["amount"] = (string) $trimmed_amount;

            if ($user_external_address["type"] == "group") {
                $db->where("address_id", $user_external_address["id"]);
                $group_data = $db->getOne("xun_crypto_user_group_address_details", "minimum, maximum, ends_on");
                $user_external_address["minimum"] = $group_data["minimum"];
                $user_external_address["maximum"] = $group_data["maximum"];

                $ends_on = $group_data["ends_on"];
                $ends_on_date = date("Y-m-d H:i:s", $ends_on);
                $ends_on_iso_date = $general->formatDateTimeToIsoFormat($ends_on_date);

                $user_external_address["ends_on"] = $ends_on_iso_date;
            }

        } else {
            $user_external_address = (object) [];
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00198') /*Address details.*/, "data" => $user_external_address);
    }

    public function get_user_external_address_history_description($params)
    {
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $address_arr = $params["addresses"];

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if (!is_array($address_arr)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00342') /*addresses must be a list*/);
        }

        $db->where("username", $username);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }
        $user_id = $xun_user["id"];

        $distinct_address_arr = array_unique(array_column($address_arr, "address"));

        // $db->where("user_id", $user_id);
        $db->where("address", $distinct_address_arr, "in");
        $db->orderBy("created_at", "DESC");
        $external_address_arr = $db->get("xun_crypto_user_external_address");

        $arr_len = count($external_address_arr);
        $external_address_data_arr = array();
        $user_id_arr = array();
        for ($i = 0; $i < $arr_len; $i++) {
            $address_data = $external_address_arr[$i];
            $address = $address_data["address"];
            $user_id_arr[] = $address_data["user_id"];
            $external_address_data_arr[$address][] = $address_data;
        }

        $user_id_arr = array_unique($user_id_arr);
        // $db->where("id", $user_id_arr, "in");
        // $xun_user_arr = $db->map("id")->ObjectBuilder()->get("xun_user", null, "nickname, id, username");
        if (!empty($user_id_arr)) {
            $db->where("id", $user_id_arr, "in");
            $xun_user_arr = $db->map("id")->ObjectBuilder()->get("xun_user", null, "nickname, id, username");
        } else {
            $xun_user_arr = [];
        }

        $address_arr_len = count($address_arr);
        for ($i = 0; $i < $address_arr_len; $i++) {
            $address_data = $address_arr[$i];
            $transaction_ts = $address_data["timestamp"];
            $transaction_dt = date("Y-m-d H:i:s", $transaction_ts);
            $address = $address_data["address"];
            $external_address_data = $external_address_data_arr[$address];

            $external_address_data_len = count($external_address_data);
            for ($j = 0; $j < $external_address_data_len; $j++) {
                if ($transaction_dt >= $external_address_data[$j]["created_at"]) {
                    $data = $external_address_data[$j];
                    $address_user_id = $data["user_id"];
                    $address_user_data = $xun_user_arr[$address_user_id];
                    $address_data["description"] = $data["description"];
                    $address_data["subject"] = $data["subject"];
                    $address_data["username"] = $address_user_data->username;
                    $address_data["nickname"] = $address_user_data->nickname;

                    break;
                }
            }

            $address_arr[$i] = $address_data;
        }

        $return_data = array("addresses" => $address_arr);
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00199') /*Address description list.*/, "data" => $return_data);
    }

    public function apps_notification($params)
    {
        global $xunXmpp;

        $username = trim($params["username"]);
        $content = trim($params["content"]);
        $tag = trim($params["tag"]);

        if ($content == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00344') /*Content cannot be empty*/);
        }

        if ($tag == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00003') /*Tag cannot be empty*/);
        }

        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = array();
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);

       return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);
    }

    // xun/app/user/callback_url/update
    public function set_wallet_callback_url($params)
    {
        $db = $this->db;
        $date = date("Y-m-d H:i:s");

        $username = trim($params["username"]);
        $callback_url = trim($params["callback_url"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $db->where("username", $username);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if ($callback_url != '') {
            if (!filter_var($callback_url, FILTER_VALIDATE_URL)) {
                return array('code' => 0, 'message' => "FAILED", "errorCode" => -100, 'message_d' => $this->get_translation_message('E00288')/*Please enter a valid URL.*/);
            }
        }

        $update_data = [];
        $update_data["wallet_callback_url"] = $callback_url;
        $update_data["updated_at"] = $date;

        if ($business_id == '') {
            $db->where("id", $xun_user["id"]);
        } else {
            $db->where("id", $business_id);
        }

        $db->update("xun_user", $update_data);

       return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00169') /*Callback URL Updated.*/);
    }

    public function get_wallet_callback_url($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($business_id == '') {
            $db->where("username", $username);
        } else {
            $db->where("id", $business_id);
        }

        $xun_user = $db->getOne("xun_user", "id, username, wallet_callback_url");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $returnData["callback_url"] = $xun_user["wallet_callback_url"];

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00170') /*Callback URL.*/, "data" => $returnData);
    }

    public function get_live_price_listing($params)
    {
        // app/crypto/live_price
        global $xunCurrency;

        $currency_list = $params["currency_list"];

        if (!is_array($currency_list)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00345') /*Currency list must be an array.*/);
        }

        $currency_list_len = count($currency_list);
        $currency_lower_list = [];
        for ($i = 0; $i < $currency_list_len; $i++) {
            $currency_lower_list[] = strtolower($currency_list[$i]);
        }
        $currency_rate_list = $xunCurrency->get_live_price_by_currency_list($currency_lower_list);
        /**
         * currency_id
         * value
         * unit
         */
        $result_arr = [];
        $price_unit = "USD";
        for ($i = 0; $i < $currency_list_len; $i++) {
            $key = $currency_list[$i];
            $key_lower = strtolower($key);

            $data = $currency_rate_list[$key_lower];
            if (is_array($data)) {
                $currency_data = $data;
                $currency_data["unit"] = $price_unit;
            } else {
                $currency_data = array(
                    "value" => $data,
                    "unit" => $price_unit,
                );
            }
            $result_arr[$key] = $currency_data;
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00202') /*Live pricing.*/, "data" => $result_arr);
    }

    public function get_user_upline_freecoin_payout_status($params)
    {
        global $xunTree, $setting, $language;
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];

        $upline_id = $xunTree->getSponsorUplineIDByUserID($user_id);

        $has_upline = is_null($upline_id) ? false : true;

        $xunFreecoin = new XunFreecoinPayout($db, $setting, $general);

        $freecoin_transaction = $xunFreecoin->getFreecoinTransactionByUser($user_id);

        $has_received_freecoin = is_null($freecoin_transaction) ? false : true;

        $return_data = array(
            "has_upline" => $has_upline,
            "has_received_freecoin" => $has_received_freecoin,
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/, "data" => $return_data);
    }

    public function get_user_primary_address($params)
    {
        global $xunUser;
        $db = $this->db;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $user_columns = "id, username, nickname, disabled";
        $xun_user = $xunUser->get_user_data_by_username($username, $user_columns);
        if (!$xun_user || $xun_user["disabled"] == 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];

        if (!empty($business_id)) {
            $user_id = $business_id;
        }

        $db->where("user_id", $user_id);
        $db->where("active", 1);
        $db->where("address_type", "personal");
        $primary_address = $db->getValue("xun_crypto_user_address", "external_address");

        $return_data = array(
            "primary_address" => $primary_address ? $primary_address : "",
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00203') /*User's primary address.*/, "data" => $return_data);
    }

    public function update_user_primary_address($params)
    {
        global $xunUser;
        $db = $this->db;

        $username = trim($params["username"]);
        $primary_address = trim($params["primary_address"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $user_columns = "id, username, nickname, disabled";
        $xun_user = $xunUser->get_user_data_by_username($username, $user_columns);
        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if ($xun_user["disabled"] == 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];

        $date = date("Y-m-d H:i:s");
        // bind primary_address to current active internal address

        $update_data = [];
        $update_data["external_address"] = $primary_address;
        $update_data["updated_at"] = $date;

        $db->where("user_id", $user_id);
        $db->where("active", 1);
        $db->where("address_type", "personal");

        $db->update("xun_crypto_user_address", $update_data);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00204') /*Primary address updated.*/);
    }

    public function update_user_primary_address_v2($params)
    {
        global $xunUser;
        $db = $this->db;

        $username = trim($params["username"]);
        $primary_address = trim($params["primary_address"]);
        $internal_address = trim($params["internal_address"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        /*
        if ($internal_address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Internal address cannot be empty.");
        }
        */

        $user_columns = "id, username, nickname, disabled";
        $xun_user = $xunUser->get_user_data_by_username($username, $user_columns);
        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if ($xun_user["disabled"] == 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];
        $date = date("Y-m-d H:i:s");
        // bind primary_address to current active internal address

        if (!empty($business_id)) {
            $user_id = $business_id;
        }

        if($internal_address == ''){
            $db->where("user_id", $user_id);
            $db->where("active", 1);
            $internal_address_data = $db->getOne("xun_crypto_user_address");
            if(!$internal_address_data){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00348') /*Please create a wallet before proceeding.*/);
            }
            $internal_address = $internal_address_data["address"];
        }

        $update_data = [];
        $update_data["external_address"] = $primary_address;
        $update_data["updated_at"] = $date;

        $db->where("user_id", $user_id);
        $db->where("address", $internal_address);
        // $db->where("active", 1);
        $db->update("xun_crypto_user_address", $update_data);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00204') /*Primary address updated.*/);
    }

        public function get_user_accepted_currency_v1($params)
    {
        global $xunCurrency, $setting;
        $db = $this->db;

        $address = trim($params["address"]);
        $username = trim($params["username"]);
        $amount = trim($params["amount"]);

        if ($address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00314') /*address cannot be empty.*/);
        }

        $xunUserService = new XunUserService($db);


        $db->where("address", $address);
        $crypto_address_detail = $db->getOne("xun_crypto_user_external_address");

        if ($crypto_address_detail) {
            $wallet_type = $crypto_address_detail["wallet_type"];
            $user_id = $crypto_address_detail["user_id"];

            $user_currency_setting = $xunUserService->getUserAccecptedCurrencyListAndPrimaryCurrency($user_id);

            if (empty($user_currency_setting["acceptedCurrency"])){
                $currency_list = $db->get("xun_coins", null, "currency_id");
                foreach($currency_list as $currency){
                    $default_currency_arr[] = $currency['currency_id'];
                }
            }

            $acceptedCurrency = $user_currency_setting["acceptedCurrency"] ? $user_currency_setting["acceptedCurrency"] : $default_currency_arr;
            $acceptedCurrencyFloatingRatio = (array)($user_currency_setting["acceptedCurrencyFloatingRatio"] ? $user_currency_setting["acceptedCurrencyFloatingRatio"] : []);

            if (!in_array(strtolower($wallet_type), array_map("strtolower", $acceptedCurrency))) {
                array_push($acceptedCurrency, $wallet_type);
            }

            $return_data["accepted_currencies"] = $acceptedCurrency;

            $currency_amount_arr = [];
            for ($i = 0; $i < count($acceptedCurrency); $i++) {
                    $currency = $acceptedCurrency[$i];
                    $currency_ratio = $acceptedCurrencyFloatingRatio[$currency] ? $acceptedCurrencyFloatingRatio[$currency] : 0;

                    $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency, true);
                    $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

                    $converted_amount = $xunCurrency->get_conversion_amount($currency, $wallet_type, $amount);
                    $converted_amount_ratio = $converted_amount + ($converted_amount * $currency_ratio / 100);
                    //$currency_amount_arr[$currency] = $converted_amount;
                    $currency_amount_arr[$currency] = $setting->setDecimal($converted_amount_ratio, $crypto_dp_credit_type);
            }

            $return_data["wallet_type"] = $wallet_type;
            $return_data["amount"] = $amount;
            $return_data["accepted_currencies"] = $acceptedCurrency;


            $crypto_decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
            $crypto_dp_credit_type = $crypto_decimal_place_setting["credit_type"];

            $currency_ratio = $acceptedCurrencyFloatingRatio[$wallet_type] ? $acceptedCurrencyFloatingRatio[$wallet_type] : 0;
            $usd_amount = $xunCurrency->get_conversion_amount("usd2", $wallet_type, $amount);
            $usd_amount_ratio = $usd_amount + ($usd_amount * $currency_ratio / 100);
            $return_data["usd_amount"] = $setting->setDecimal($usd_amount_ratio, $crypto_dp_credit_type);

            if ($amount != "") {
                $return_data["currency_amount"] = $currency_amount_arr ? $currency_amount_arr : [];
            }

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00206') /*User accepted currency listing*/, "data" => $return_data);

        } else {

           return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00349') /*Address type not found.*/);
        }

    }

    public function get_user_accepted_currency($params)
    {
        global $xunCurrency;
        $db = $this->db;

        $address = trim($params["address"]);
        $username = trim($params["username"]);
        $amount = trim($params["amount"]);

        if ($address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00314') /*address cannot be empty.*/);
        }

        $xunUserService = new XunUserService($db);

        $xun_user = $xunUserService->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        //  check if address is primary address, get user id
        $addressObj = new stdClass();
        $addressObj->externalAddress = $address;
        $addressObj->addressType = "personal";
        $user_address_data = $xunUserService->getAddressByExternalAddressAndAddressType($addressObj);

        $return_data = array(
            "is_primary_address" => false,
        );
        if (!$user_address_data) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00206') /*User accepted currency listing*/, "data" => $return_data);
        }

        $address_user_id = $user_address_data["user_id"];

        $user_currency_setting = $xunUserService->getUserAccecptedCurrencyListAndPrimaryCurrency($address_user_id);

        $primary_currency = $user_currency_setting["primaryCurrency"];
        // $accepted_currency_arr = $user_currency_setting["acceptedCurrency"];

        //  11/02/2020 update
        $accepted_currency_arr = [];

        $db->where("business_id", $address_user_id);
        $business_coin_wallet_type_arr = $db->getValue("xun_business_coin", "wallet_type", null);

        if(!empty($business_coin_wallet_type_arr)){
            $accepted_currency_arr = $business_coin_wallet_type_arr;
        }

        if ($primary_currency && $amount) {
            // get rate of accepted_currency
            $currency_arr = $accepted_currency_arr;
            $currency_arr[] = "usd";
            $currency_arr_len = count($currency_arr);

            $currency_amount_arr = [];
            for ($i = 0; $i < $currency_arr_len; $i++) {
                $currency = $currency_arr[$i];
                $converted_amount = $xunCurrency->get_conversion_amount($currency, $primary_currency, $amount);
                $currency_amount_arr[$currency] = $converted_amount;
            }
        }


        $device_info_obj = new stdClass();
        $device_info_obj->username = $username;

        $xun_device_info = $xunUserService->getDeviceInfo($device_info_obj);

        $is_exception = false;
        if($xun_device_info){
            $os = $xun_device_info["os"];
            $app_version = $xun_device_info["app_version"];
            $app_version = str_replace("(", ".", $app_version);
            $app_version = str_replace(")", "", $app_version);

            $min_ios_version = '1.0.162';

            if($os == 2 && version_compare($min_ios_version, $app_version) > 0){
                $is_exception = true;
            }
        }

        $primary_currency = $primary_currency ? $primary_currency : '';
        if($is_exception == true){
            $is_primary_address = $primary_currency != '' ? true : false;
        }else{
            $is_primary_address = true;
        }

        /*
        if (empty($accepted_currency_arr)){
            $currency_list = $db->get("xun_coins", null, "currency_id");
            foreach($currency_list as $currency){
                $default_currency_arr[] = $currency['currency_id'];
            }
        }
        */
        $default_currency_arr = [];

        $return_data["is_primary_address"] = $is_primary_address;
        $return_data["accepted_currencies"] = $accepted_currency_arr ? $accepted_currency_arr : $default_currency_arr;
        $return_data["primary_currency"] = $primary_currency;
        $return_data["currency_amount"] = $currency_amount_arr ? $currency_amount_arr : [];

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00206') /*User accepted currency listing*/, "data" => $return_data);
    }

    public function set_user_accepted_currency_setting($params) {

        $db = $this->db;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $currency = trim($params["currency"]);
        $ratio = trim($params["ratio"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }


        $xunUserService = new XunUserService($db);
        $xun_user = $xunUserService->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if (!empty($business_id)) {
            $xun_business_service = new XunBusinessService($db);
            $isBusinessEmployee = $xun_business_service->isBusinessEmployee($business_id, $username);

            if(!$isBusinessEmployee){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00350') /*You're not an employee in this business.*/, "errorCode" => -100);
            }

            $user_id = $business_id;
        }else{
            $user_id = $xun_user["id"];
        }

        if ($currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00306') /*Currency cannot be empty.*/);
        }

        if ($ratio == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00351') /*Ratio cannot be empty.*/);
        }

        if (!is_numeric($ratio)){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00352') /*Ratio must be a numeric value.*/);
        }

        $db->where("user_id", $user_id);
        $db->where("name", "acceptedCurrencyFloatingRatio");
        $xun_user_setting = $db->getOne("xun_user_setting");

        $current_timestamp = date("Y-m-d H:i:s");

        if(!$xun_user_setting){

            $currency_ratio = json_encode(array($currency => $ratio));
            $fields = array("user_id", "name", "value", "created_at", "updated_at");
            $values = array($user_id, "acceptedCurrencyFloatingRatio", $currency_ratio, $current_timestamp, $current_timestamp);

            $insertData = array_combine($fields, $values);
            $row_id = $db->insert("xun_user_setting", $insertData);

        } else {

            $currency_ratio = $xun_user_setting["value"];

            if ($currency_ratio == "") {
                $currency_ratio = json_encode(array($currency => $ratio));
            } else {
                $currency_ratio = json_decode($currency_ratio, true);
                $currency_ratio[$currency] = $ratio;
                $currency_ratio = json_encode($currency_ratio);

            }

            $updateData = [];
            $updateData["value"] = $currency_ratio;
            $updateData["updated_at"] = $current_timestamp;

            $db->where("user_id", $user_id);
            $db->where("name", "acceptedCurrencyFloatingRatio");
            $db->update("xun_user_setting", $updateData);

        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);

    }

    public function get_user_accepted_currency_setting_v1($params) {

        global $xunCurrency;
        $db = $this->db;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $xunUserService = new XunUserService($db);

        $xun_user = $xunUserService->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if (!empty($business_id)) {
            $xun_business_service = new XunBusinessService($db);
            $isBusinessEmployee = $xun_business_service->isBusinessEmployee($business_id, $username);

            if(!$isBusinessEmployee){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00350') /*You're not an employee in this business.*/, "errorCode" => -100);
            }

            $user_id = $business_id;
        }else{
            $user_id = $xun_user["id"];
        }

        $user_currency_setting = $xunUserService->getUserAccecptedCurrencyListAndPrimaryCurrency($user_id);
        $accepted_currency_arr = $user_currency_setting["acceptedCurrency"];
        $floating_ratio_arr = $user_currency_setting["acceptedCurrencyFloatingRatio"];

        if (empty($accepted_currency_arr)){
            $currency_list = $db->get("xun_coins", null, "currency_id");
            foreach($currency_list as $currency){
                $accepted_currency_arr[] = $currency['currency_id'];
            }
        }

        $return_data = [];
        $return_data["accepted_currencies"] = $accepted_currency_arr;
        $return_data["floating_ratio"] = $floating_ratio_arr;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00206') /*User accepted currency listing*/, "data" => $return_data);

    }

    public function get_user_accepted_currency_setting($params)
    {
        global $xunCurrency;
        $db = $this->db;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $xunUserService = new XunUserService($db);

        $xun_user = $xunUserService->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if (!empty($business_id)) {
            $xun_business_service = new XunBusinessService($db);
            $isBusinessEmployee = $xun_business_service->isBusinessEmployee($business_id, $username);

            if(!$isBusinessEmployee){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00350') /*You're not an employee in this business.*/, "errorCode" => -100);
            }

            $user_id = $business_id;
        }else{
            $user_id = $xun_user["id"];
        }

        $user_currency_setting = $xunUserService->getUserAccecptedCurrencyListAndPrimaryCurrency($user_id);

        $primary_currency = $user_currency_setting["primaryCurrency"];
        $accepted_currency_arr = $user_currency_setting["acceptedCurrency"];
        $floating_ratio_arr = $user_currency_setting["acceptedCurrencyFloatingRatio"];

        $final_accepted_currency_arr = [];
        for($i = 0; $i < count($accepted_currency_arr); $i++){
            $currency_data = new stdclass();
            $currency = $accepted_currency_arr[$i];
            $currency_data->currency = $currency;
            $currency_data->floating_ratio = (string)($floating_ratio_arr->$currency ? $floating_ratio_arr->$currency : 0);

            $final_accepted_currency_arr[] = $currency_data;
        }

        $return_data = [];
        $return_data["accepted_currencies"] = $final_accepted_currency_arr;
        $return_data["primary_currency"] = $primary_currency ? $primary_currency : '';

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00206') /*User accepted currency listing*/, "data" => $return_data);
    }

    public function update_user_accepted_currency_setting($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $accepted_currency_arr = $params["accepted_currencies"];
        $primary_currency = trim($params["primary_currency"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($accepted_currency_arr == '') {
           return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00353') /*Accepted currencies cannot be empty.*/);
        }

        $xunUserService = new XunUserService($db);

        $xun_user = $xunUserService->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if (!empty($business_id)) {
            $xun_business_service = new XunBusinessService($db);
            $isBusinessEmployee = $xun_business_service->isBusinessEmployee($business_id, $username);

            if(!$isBusinessEmployee){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00350') /*You're not an employee in this business.*/, "errorCode" => -100);
            }

            $user_id = $business_id;
        }else{
            $user_id = $xun_user["id"];
        }


        $user_obj = new stdClass();
        $user_obj->userID = $user_id;

        if (!is_array($accepted_currency_arr)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00354')/*Accepted currencies must be an array.*/);
        }

        if($primary_currency && sizeof($accepted_currency_arr) > 0){
            if(!in_array($primary_currency, $accepted_currency_arr)){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00355') /*Primary currency must be in the list of accepted currencies.*/, "errorCode" => -101);
            }
        }

        for($i = 0; $i < count($accepted_currency_arr); $i++){
            $accepted_currency_arr[$i] = strtolower($accepted_currency_arr[$i]);
        }

        $user_obj->acceptedCurrencyArr = $accepted_currency_arr;
        $user_obj->primaryCurrency = $primary_currency;

        $xunUserService->updateUserAcceptedCurrency($user_obj);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);

    }

    public function update_accepted_currency_floating_ratio($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $currency = $params["currency"];
        $floating_ratio = $params["floating_ratio"];

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($currency == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00306') /*Currency cannot be empty.*/);
        }
        $currency = strtolower($currency);

        if (!is_numeric($floating_ratio)){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00356') /*Floating ratio must be a numeric value.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if (!empty($business_id)) {
            $xun_business_service = new XunBusinessService($db);
            $isBusinessEmployee = $xun_business_service->isBusinessEmployee($business_id, $username);

            if(!$isBusinessEmployee){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00350') /*You're not an employee in this business.*/, "errorCode" => -100);
            }

            $user_id = $business_id;
        }else{
            $user_id = $xun_user["id"];
        }

        $user_currency_setting = $xun_user_service->getUserSettingByUserID($user_id, ["acceptedCurrency", "acceptedCurrencyFloatingRatio"]);
        $accepted_currency_value = $user_currency_setting["acceptedCurrency"]["value"];
        $accepted_currency_arr = empty($accepted_currency_value) ? [] : json_decode($accepted_currency_value);
        
        if(!in_array($currency, $accepted_currency_arr)){
        return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00357') /*The selected coin is not an accepted coin.*/);
        }

        $accepted_currency_floating_ratio_value = $user_currency_setting["acceptedCurrencyFloatingRatio"]["value"];
        $accepted_currency_floating_ratio_arr = empty($accepted_currency_floating_ratio_value) ? new stdClass() : json_decode($accepted_currency_floating_ratio_value);

        $accepted_currency_floating_ratio_arr->$currency = $floating_ratio;

        $user_obj = new stdClass();
        $user_obj->userID = $user_id;
        $user_obj->name = "acceptedCurrencyFloatingRatio";
        $user_obj->value = json_encode($accepted_currency_floating_ratio_arr);

        $xun_user_service->updateUserSetting($user_obj);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);
    }

    public function update_wallet_transaction($params)
    {
        global $setting;
        $db = $this->db;

        $username = trim($params["username"]);
        $transactionHash = trim($params["transaction_hash"]);
        $senderAddress = trim($params["sender_address"]);
        $recipientAddress = trim($params["recipient_address"]);
        $isEscrow = trim($params["escrow"]);
        $batchID = trim($params["batch_id"]); // only for escrow transaction
        $escrowContractAddress = trim($params["escrow_contract_address"]);

        if ($username == '') {
           return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        if ($transactionHash == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00358') /*Transaction hash is required.*/);
        }

        if ($senderAddress == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00359') /*Sender address is required.*/);
        }

        if ($recipientAddress == '') {
           return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00360') /*Recipient address is required.*/);
        }

        if ($isEscrow == 1 && ($batchID == '' || $escrowContractAddress == '')) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00361') /*Batch ID and escrow contract address is required for escrow transaction.*/);
        }

        $xunUserService = new XunUserService($db);
        $senderAddressData = $xunUserService->getAddressDetailsByAddress($senderAddress);

        // get user id from sender address
        if (!$senderAddressData || $senderAddressData["active"] == 0) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00362') /*Inactive sender address.*/);
        }

        $userID = $senderAddressData["user_id"];

        $transactionStatus = "pending";

        $db->where("transaction_hash", $transactionHash);
        $cryptoTransactionRecord = $db->getOne("xun_crypto_transaction_hash");

        $date = date("Y-m-d H:i:s");
        if ($isEscrow == 1) {
            $expired_duration = $setting->systemSetting["walletEscrowExpiredDuration"];
            $expires_at = date("Y-m-d H:i:s", strtotime("+$expired_duration", strtotime($date)));
        }

        if ($cryptoTransactionRecord) {
            $transactionStatus = "completed";
            $amount = $cryptoTransactionRecord["amount"];
            $walletType = $cryptoTransactionRecord["wallet_type"];
            $transactionToken = $cryptoTransactionRecord["transaction_token"];
            // $senderAddress = $cryptoTransactionRecord["sender_address"];
            // $recipientAddress = $cryptoTransactionRecord["recipient_address"];
        }

        $addressType = "personal";
        $transactionType = "send";

        $transactionObj = new stdClass();
        $transactionObj->status = $transactionStatus;
        $transactionObj->transactionHash = $transactionHash;
        $transactionObj->transactionToken = $transactionToken ? $transactionToken : '';
        $transactionObj->senderAddress = $senderAddress ? $senderAddress : '';
        $transactionObj->recipientAddress = $recipientAddress ? $recipientAddress : '';
        $transactionObj->userID = $userID ? $userID : '';
        $transactionObj->senderUserID = $userID ? $usedID : '';
        $transactionObj->recipientUserID = $recipientUserID ? $recipientUserID : '';
        $transactionObj->walletType = $walletType ? $walletType : '';
        $transactionObj->amount = $amount ? $amount : '';
        $transactionObj->addressType = $addressType;
        $transactionObj->transactionType = $transactionType;
        $transactionObj->escrow = $isEscrow ? $isEscrow : '0';
        $transactionObj->referenceID = $batchID ? $batchID : '';
        $transactionObj->escrowContractAddress = $escrowContractAddress ? $escrowContractAddress : '';
        $transactionObj->createdAt = $date;
        $transactionObj->updatedAt = $date;
        $transactionObj->expiresAt = $expires_at ? $expires_at : '';

        $xunWallet = new XunWallet($db);
        $res = $xunWallet->updateUserWalletTransaction($transactionObj);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00144') /*Success.*/);
    }

    public function update_wallet_transaction_v2($params)
    {
        global $setting, $xunCrypto, $xunPhoneApprove;
        $db = $this->db;

        $username = trim($params["username"]);
        $recordID = trim($params["id"]);
        $transactionHash = trim($params["transaction_hash"]);
        $isEscrow = trim($params["escrow"]);
        $batchID = trim($params["batch_id"]); // only for escrow transaction and phone approval
        $escrowContractAddress = trim($params["escrow_contract_address"]);
        $status = trim($params["status"]); // failed/success
        $receiverUsername = trim($params["receiver_username"]);
        $message = trim($params["error_message"]);
        $externalAddress = trim($params["external_address"]);
        //  if insufficient fund, status = failed, id = '', batch_id = 'xxx', txHash = ''

        $status = $status == '' ? "success" : $status;
        $status = strtolower($status);

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        if($status == '' || $status == "success"){
            if ($transactionHash == '') {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00358') /*Transaction hash is required.*/);
            }
            if ($recordID == '') {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00363') /*ID is required.*/);
            }
        }else if(!in_array($status, ["failed", "success"])){
            //  if no status, transactionHash is required,
            //  if status, status must be failed/success => invalid status
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00221') /*Invalid status.*/);
        }

        if ($isEscrow == 1 && ($batchID == '' || $escrowContractAddress == '')) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00361') /*Batch ID and escrow contract address is required for escrow transaction.*/);
        }

        $xunUserService = new XunUserService($db);

        $xunUser = $xunUserService->getUserByUsername($username);
        if(!$xunUser){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }
        
        $userID = $xunUser["id"];

        $date = date("Y-m-d H:i:s");

        /**
         * if failed, no transaction hash, no record id (optional)
         *  if have record id, update
         * if there's batch id
         *  update phone approval => callback to business
         * else 
         * 
         */
        $xunWallet = new XunWallet($db);

        if($status == "failed"){
            if ($recordID != ''){
                //  update wallet transaction table
                $transactionData = $xunWallet->getWalletTransactionByID($recordID);

                if (!$transactionData || $transactionData["status"] != "pending"){
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => $this->get_translation_message('E00364') /*Invalid ID.*/
                    );
                }

                $batchID = $transactionData["batch_id"];
                $receiverUserID = $transactionData["recipient_user_id"];
                $updateData = [];
                $updateData["status"] = $status;
                $updateData["message"] = $message;
                $updateData["updated_at"] = $date;

                $db->where("id", $recordID);
                $retVal = $db->update("xun_wallet_transaction", $updateData);
            }

            if ($batchID != ''  && !$isEscrow)
            {
                if($recordID == '' && $receiverUsername == '' && $externalAddress == ''){
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => $this->get_translation_message('E00365') /*Receiver username is required.*/
                    );
                }
                //  phone approval transaction
                //  callback to business
                $phoneApprovalTransactionData = array(
                    "status" => $status,
                    "batch_id" => $batchID,
                    "receiver_username" => $receiverUsername,
                    "receiver_user_id" => $receiverUserID,
                    "external_address" => $externalAddress,
                    "message" => $message,
                    "wallet_transaction_id" => $recordID
                );

                try{
                    $xunPhoneApprove->transaction_signing_update($phoneApprovalTransactionData);
                }catch(Exception $e){
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => $e->getMessage()
                    );
                }
            }

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00144') /*Success.*/);
        }else{
            $transactionRecord = $xunWallet->getWalletTransactionByTxHash($transactionHash);
            if($transactionRecord){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00366') /*Duplicated transaction hash.*/);
            }
            $transactionData = $xunWallet->getWalletTransactionByID($recordID);
            $receiverUserID = $transactionData["recipient_user_id"];
            $transactionEscrow = $transactionData["escrow"];
    
            if ($transactionEscrow == 1) {
                $expired_duration = $setting->systemSetting["walletEscrowExpiredDuration"];
                $expires_at = date("Y-m-d H:i:s", strtotime("+$expired_duration", strtotime($date)));
            }
    
            $db->where("transaction_hash", $transactionHash);
            $cryptoTransactionRecord = $db->getOne("xun_crypto_transaction_hash");
    
            if($cryptoTransactionRecord){
                if($transactionData["amount"] == 0){
                    $transactionStatus = "completed";
                }else if($transactionData["amount"] != 0 && $transactionData["amount"] == $cryptoTransactionRecord["amount"]){
                    $transactionStatus = "completed";
                }
            }
            $referenceID = $transactionData["reference_id"];
    
            $transactionObj = new stdClass();
    
            $transactionObj->id = $recordID;
            $transactionObj->transactionHash = $transactionHash;
            $transactionObj->referenceID = $batchID ? $batchID : ($referenceID ? $referenceID : '');
            $transactionObj->escrowContractAddress = $escrowContractAddress ? $escrowContractAddress : '';
            $transactionObj->updatedAt = $date;
            $transactionObj->expiresAt = $expires_at ? $expires_at : '';
            $transactionObj->status = $transactionStatus;
            
            $xunWallet = new XunWallet($db);
            $res = $xunWallet->updateWalletTransactionHash($transactionObj);
    
            $finalTransactionStatus = $transactionStatus ? $transactionStatus : $transactionData["status"];
    
            $transactionAddressType = $transactionData["address_type"];
            if($transactionAddressType == "pay"){
                if($finalTransactionStatus == "completed"){
                    //  call pay process
                    $xunCrypto->process_pay_transaction($recordID);
                }
            }else if($transactionAddressType == "story"){
                if($finalTransactionStatus == "completed"){
                    //  call story process
                    $xunCrypto->process_story_transaction($transactionData);
                }
            }else if($transactionAddressType == "reward"){
                if($finalTransactionStatus == "completed"){
                    $transactionData["status"] = $finalTransactionStatus;
                    $xunCrypto->process_business_reward_redemption($transactionData, 'redemption');
                }
            }

            if($transactionData["batch_id"] != '' && $transactionEscrow != 1){
                //  phone approve transaction
                //  update table, callback to business
                $phoneApprovalTransactionData = array(
                    "status" => $status,
                    "batch_id" => $transactionData["batch_id"],
                    "receiver_user_id" => $receiverUserID,
                    "message" => $message,
                    "wallet_transaction_id" => $recordID,
                    "transaction_hash" => $transactionHash,
                    // "address" => $externalAddress
                );

                try{
                    $xunPhoneApprove->transaction_signing_update($phoneApprovalTransactionData);
                }catch(Exception $e){
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => $e->getMessage()
                    );
                }
            }

            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00144') /*Success.*/);
        }
    }

    public function get_escrow_details($params)
    {
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $transaction_hash = trim($params["transaction_hash"]);

        if ($transaction_hash == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00358') /*Transaction hash is required.*/);
        }

        $xunWallet = new XunWallet($db);
        $wallet_transaction_record = $xunWallet->getWalletTransactionByTxHash($transaction_hash);

        if (!$wallet_transaction_record) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00367') /*Invalid transaction hash*/, "errorCode" => -100);
        }

        if ($wallet_transaction_record["escrow"] == 0) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00368') /*Transaction is not an escrow contract.*/, "errorCode" => -101);
        }

        $expires_at = $wallet_transaction_record["expires_at"];
        $expires_at_iso = $general->formatDateTimeToIsoFormat($expires_at);

        $reference_id = $wallet_transaction_record["reference_id"];
        $escrow_contract_account = $wallet_transaction_record["escrow_contract_address"];
        $sender_address = $wallet_transaction_record["sender_address"];
        $recipient_address = $wallet_transaction_record["recipient_address"];

        $return_data = array(
            "batch_id" => (string) $reference_id,
            "escrow_contract_address" => $escrow_contract_account,
            "sender_address" => $sender_address,
            "recipient_address" => $recipient_address,
            "expires_at" => $expires_at_iso,
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00207') /*Escrow transaction details.*/, "data" => $return_data);
    }

    public function escrow_transaction_report_user($params)
    {
        $db = $this->db;
        
        $username = trim($params["username"]);
        $transaction_hash = trim($params["transaction_hash"]);
        $transaction_type = trim($params["transaction_type"]);// sender/recipient
        $reason = trim($params["reason"]);

        $reason = $reason ? $reason : '';
        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($transaction_hash == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00358') /*Transaction hash is required.*/);
        }

        if ($transaction_type == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00369') /*Transaction type cannot be empty.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);
        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }
        
        $user_id = $xun_user["id"];
        $date = date("Y-m-d H:i:s");

        //  get transaction hash data
        $xun_wallet = new XunWallet($db);
        $wallet_transaction_record = $xun_wallet->getWalletTransactionByTxHash($transaction_hash);
        if(!$wallet_transaction_record){
           return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00367') /*Invalid transaction hash.*/);
        }

        if(!$wallet_transaction_record["escrow"]){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00370') /*This transaction cannot be reported.*/);
        }

        $sender_address = $wallet_transaction_record["sender_address"];
        $recipient_address = $wallet_transaction_record["recipient_address"];

        $address_user_arr = $xun_user_service->getAddressAndUserDetailsByAddressList([$sender_address, $recipient_address]);

        $sender_data = $address_user_arr[$sender_address];
        $recipient_data = $address_user_arr[$recipient_address];

        switch($transaction_type){
            case "sender": 
                $transaction_type_enum = 1;
                break;
            case "recipient": 
                $transaction_type_enum = 2;
                break;

            default:
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00371') /*Invalid transaction type*/);
        }

        $xun_business_service = new XunBusinessService($db);

        $sender_user_id = $sender_data["user_id"];
        $recipient_user_id = $recipient_data["user_id"];

        if ($transaction_type_enum === 1){
            if($sender_data["type"] == "user" && $sender_user_id != $user_id)
            {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00372') /*You are not allowed to report in this transaction.*/);
            }else if($sender_data["type"] == "business"){
                $business_employee_arr = $xun_business_service->getBusinessActiveEmployee($sender_user_id);

                $employee_mobile = array_column($business_employee_arr, "mobile");
                if(!in_array($username, $employee_mobile)){
                    return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00372') /*You are not allowed to report in this transaction.*/);
                }
            }
            $wallet_user_id = $sender_user_id;

            $reported_username = $recipient_data["username"];
            $reported_user_type = $recipient_data["type"];
            if($reported_user_type == "business"){
                $business_details = $xun_business_service->getBusinessByBusinessID($recipient_user_id, "name");
                $reported_nickname = $business_details["name"];
                $reported_username = $recipient_user_id;
            }else{
                $reported_nickname = $recipient_data["nickname"];
            }
        }else{
            if($recipient_data["type"] == "user" && $recipient_user_id != $user_id)
            {
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00372') /*You are not allowed to report in this transaction.*/);
            }else if($recipient_data["type"] == "business"){
                $business_employee_arr = $xun_business_service->getBusinessActiveEmployee($recipient_user_id);

                $employee_mobile = array_column($business_employee_arr, "mobile");
                if(!in_array($username, $employee_mobile)){
                    return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00372') /*You are not allowed to report in this transaction.*/);
                }
            }
            $wallet_user_id = $recipient_user_id;

            $reported_username = $sender_data["username"];
            $reported_user_type = $sender_data["type"];
            if($reported_user_type == "business"){
                $business_details = $xun_business_service->getBusinessByBusinessID($sender_user_id, "name");
                $reported_username = $sender_user_id;
                $reported_nickname = $business_details["name"];
            }else{
                $reported_nickname = $sender_data["nickname"];
            }
        }
        
        $wallet_transaction_id = $wallet_transaction_record["id"];

        $escrow_report_obj = new stdClass();
        $escrow_report_obj->wallet_transaction_id = $wallet_transaction_id;
        $escrow_report_obj->transaction_type = $transaction_type_enum;
        $escrow_report = $xun_wallet->getEscrowReport($escrow_report_obj);

        if($escrow_report){
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);
        }

        $escrow_report_obj->user_id = $user_id;
        $escrow_report_obj->created_at = $date;
        $escrow_report_obj->updated_at = $date;
        $escrow_report_obj->reason = $reason;
        $escrow_report_obj->wallet_user_id = $wallet_user_id;
        $escrow_report_id = $xun_wallet->insertEscrowReport($escrow_report_obj);

        $nickname = $xun_user["nickname"];
        $subject = "Ticket No. ${escrow_report_id}. TheNux wallet escrow: User Report. Transaction Hash: ${transaction_hash}";
        $content = "Transaction Hash: ${transaction_hash}\n";
        $content .= "Reported By: \n";
        $content .= "&nbsp;&nbsp;Username: ${username}\n";
        $content .= "&nbsp;&nbsp;Nickname: ${nickname}\n";
        $content .= "User reported: \n";
        $content .= "&nbsp;&nbsp;Username / Business ID: ${reported_username}\n";
        $content .= "&nbsp;&nbsp;Nickname / Business Name: ${reported_nickname}\n";
        $content .= "&nbsp;&nbsp;User Type: ". ucfirst($reported_user_type) ."\n";
        $content .= "Transaction Type: ". ucfirst($transaction_type) . "\n";
        $content .= "Reason: ${reason}\n";

        $ticket_params = array(
            "username" => $username,
            "nickname" => $nickname,
            "subject" => $subject,
            "content" => $content
        );

        $this->send_report_ticket($ticket_params);
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);
    }
    
    public function escrow_transaction_request_money($params)
    {
        /**
         * data in: transaction hash, username
         * 
         * 
         */
        global $config, $xunXmpp;
        $db = $this->db;

        $username = trim($params["username"]);
        $transaction_hash = trim($params["transaction_hash"]);
        $transaction_type = trim($params["transaction_type"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($transaction_hash == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00373') /*Transaction hash cannot be empty.*/);
        }

        if ($transaction_type == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00369') /*Transaction type cannot be empty.*/);
        }

        /**
         * only seller/receiver can request money
         */

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);
        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }
        
        $user_id = $xun_user["id"];
        $date = date("Y-m-d H:i:s");

        //  get transaction hash data
        $xun_wallet = new XunWallet($db);
        $wallet_transaction_record = $xun_wallet->getWalletTransactionByTxHash($transaction_hash);
        if(!$wallet_transaction_record){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00367') /*Invalid transaction hash.*/);
        }

        if(!$wallet_transaction_record["escrow"]){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00374') /*Invalid action.*/);
        }

        $sender_address = $wallet_transaction_record["sender_address"];
        $recipient_address = $wallet_transaction_record["recipient_address"];

        $address_user_arr = $xun_user_service->getAddressAndUserDetailsByAddressList([$sender_address, $recipient_address]);

        $sender_data = $address_user_arr[$sender_address];
        $recipient_data = $address_user_arr[$recipient_address];

        switch($transaction_type){
            case "sender": 
                $transaction_type_enum = 1;
                break;
            case "recipient": 
                $transaction_type_enum = 2;
                break;

            default:
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00371') /*Invalid transaction type*/);
        }

        $xun_business_service = new XunBusinessService($db);

        $sender_user_id = $sender_data["user_id"];
        $recipient_user_id = $recipient_data["user_id"];

        if ($transaction_type_enum === 1){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00375') /*Sender is not allowed to request money.*/);
        }else{
            $recipient_type = "user";
            if($recipient_data["type"] == "user" && $recipient_user_id != $user_id)
            {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00376') /*You are not allowed to request money in this transaction.*/);
            }else if($recipient_data["type"] == "business"){
                $business_employee_arr = $xun_business_service->getBusinessActiveEmployee($recipient_user_id);

                $employee_mobile = array_column($business_employee_arr, "mobile");
                $recipient_type = "business";
                if(!in_array($username, $employee_mobile)){
                    return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00376') /*You are not allowed to request money in this transaction.*/);
                }
            }

            if($recipient_type == "user"){
                $sender_nickname = $xun_user["nickname"];
            }else{
                $xun_business = $xun_business_service->getBusinessByBusinessID($recipient_user_id, "id, name");
                $sender_nickname = $xun_business["name"];
            }

        }

        $xmpp_recipient_arr = [];

        if($sender_data["type"] == "user"){
            $xmpp_recipient_obj = new stdClass();
            $xmpp_recipient_obj->username = $sender_data["username"];
            $xmpp_recipient_obj->recipient_id = $sender_data["username"];
            $xmpp_recipient_arr[] = $xmpp_recipient_obj;
        }else{
            //  get all employee list
            $sender_business_employee_arr = $xun_business_service->getBusinessActiveEmployee($sender_user_id, "mobile, old_id");

            for($i = 0; $i < count($sender_business_employee_arr); $i++){
                $sender_employee = $sender_business_employee_arr[$i];
                $xmpp_recipient_obj = new stdClass();
                $xmpp_recipient_obj->username = $sender_employee["mobile"];
                $xmpp_recipient_obj->recipient_id = $sender_employee["old_id"];
                $xmpp_recipient_arr[] = $xmpp_recipient_obj;
            }
        }

        //  send xmpp message
        $server_host = $config["erlang_server"];

        $sender_jid = $username . '@' . $server_host;
        $erlang_data = new stdClass();
        $erlang_data->sender_jid = $sender_jid;
        $erlang_data->sender_nickname = $sender_nickname;
        $erlang_params = array(
            "type" => "request",
            "chatroom_id" => $transaction_hash,
            "chatroom_host" => "crypto." . $server_host,
            "data" => $erlang_data,
            "recipients" => $xmpp_recipient_arr
        );

        $xunXmpp->send_xmpp_crypto_event($erlang_params);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);
    }

    private function send_report_ticket($params)
    {
        global $setting, $ticket;

        $username = $params["username"];
        $nickname = $params["nickname"];
        $subject = $params["subject"];
        $content = $params["content"];

        $clientName = $nickname;
        $clientEmail = $setting->systemSetting["systemEmailAddress"];

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

    private function get_backup_wallet_email_content($username, $private_key, $verification_code, $business_name)
    {
        global $xunEmail, $setting;

        $date = date("Y-m-d H:i:s");
        $newDateTime = date('d/m/Y h:i A', strtotime($date));

        $companyName = $setting->systemSetting["companyName"];

        $email_content =
            "<p style=\"font-size: 22px; letter-spacing: .5px; margin-bottom: 10px;\">Email Verification</p>
            </div>
            <div style=\"background:#fff;padding: 20px;border:1px solid #e8eaf1;border-top: none;border-bottom-left-radius:  5px;border-bottom-right-radius: 5px;\">
            <p style=\"font-size:20px;text-align: center;\">Your One Time Password</p>
            <p style=\"text-align: center;font-size: 25px;padding: 0;margin-top: -20px;font-weight: bold;\">" . $verification_code . "</p>
            <div style=\"margin: 45px auto;\">
            <p style=\"font-size: 12px;\">Please keep your Wallet Key in a safe place and do not share your Wallet Key to anyone, " . $companyName . " will not request the Wallet Key from you.</p>
            <p stype=\"font-size:12px\">Your TheNux registered phone number:<br>" . $username . "</p>";
            if($business_name){
                $email_content .= "<p stype=\"font-size:12px\">Business: " . $business_name . "</p>";
            }
            $email_content .= "<p style=\"font-size: 12px;font-weight: bold;\">Your TheNux Wallet Key: <br> " . $private_key . "</p>
            <p>Date: " . $newDateTime . "</p>
            <p>How to restore:</p>
            <ol>
            <li>Download and install TheNux from App Store or Play Store.</li>
            <li>Login TheNux with your registered phone number.<br>(**Your Wallet Key must match with your registered phone number when restoring wallet.)</li>
            <li>You can skip or restore chat from iCloud, it will not affect your wallet.</li>
            <li>After you login to TheNux, tab on \"Wallets\".</li>
            <li>Choose \"Restore Wallet\", then choose \"Restore from Wallet Key\".</li>
            <li>Copy the Wallet Key above and paste in the text box and \"Restore\".</li>
            <li>Wait for several minute for the system to restore your wallet.</li>
            </ol>
            <p>Take Note:</p>
            <p>Please backup your wallet every time when you change your TheNux phone number, our system WILL NOT store your Wallet Key. Your Wallet Key will bind with your current TheNux registered phone number, you need to have BOTH in order to successfully restore your wallet.</p>
            <p>You will not success restore your wallet when:</p>
            <ol>
            <li>Your Wallet Key / TheNux registered phone number is not correct</li>
            <li>Your Wallet Key do not match with your TheNux registered phone number</li>
            <li>You only have Wallet Key</li>
            <li>You only have TheNux registered phone number</li>
            </ol>
            </div>
            <p style=\"font-size:12px;\">If you did not request this, please contact us.</p>";

        $email_body = $xunEmail->getEmailHtml($email_content);

        $translations_message = "%%companyName%% - OTP and Wallet Backup";
        $subject = str_replace("%%companyName%%", $companyName, $translations_message);

        $emailParams["subject"] = $subject;
        $emailParams["body"] = $email_body;

        return $emailParams;
    }

    private function send_wallet_otp_email($emailAddress, $emailParams)
    {
        global $xunEmail, $setting;
        $general = $this->general;

        $emailParams["recipients"] = array($emailAddress);

        $result = $general->sendEmail($emailParams);
        if ($result == 1) {
            $return_result = array("code" => 1);
        } else {
            $return_result = array("code" => 0);
        }
        return $return_result;
    }

    private function send_wallet_otp_email_ses($emailAddress, $emailParams)
    {
        global $xunAws, $xunEmail, $setting;

        $emailParams["recipient_emails"] = array($emailAddress);
        $emailParams["html_body"] = $emailParams["body"];
        unset($emailParams["body"]);

        $result = $xunAws->send_ses_email($emailParams);
        return $result;
    }

    private function get_livechat_room_details($xun_livechat_room, $chat_room_jid = null)
    {

        $db = $this->db;
        $general = $this->general;

        $chat_room_user = $xun_livechat_room["username"];
        $chat_room_user_host = $xun_livechat_room["username_host"];
        $chat_room_id = $xun_livechat_room["old_id"];
        $chat_room_host = $xun_livechat_room["host"];
        $chat_room_status = $xun_livechat_room["status"];
        $business_id = $xun_livechat_room["business_id"];
        $tag = $xun_livechat_room["business_tag"];
        $chat_room_created_date = $general->formatDateTimeToIsoFormat($xun_livechat_room["created_at"]);
        $chat_room_modified_date = $general->formatDateTimeToIsoFormat($xun_livechat_room["updated_at"]);

        if (is_null($chat_room_jid)) {
            $chat_room_jid = $chat_room_id . "@" . $chat_room_host;
        }
        $chat_room_user_jid = $chat_room_user . "@" . $chat_room_user_host;

        $attending_employee_id = "";
        $attending_employee_jid = "";
        $attending_staff_jid = "";

        if ($chat_room_status == "standup") {
            $chat_room_status = "open";
        }

        if ($chat_room_status == "accepted") {
            // get employee id
            $attending_employee_username = $xun_livechat_room["employee_username"];
            $attending_employee_host = $xun_livechat_room["employee_host"];

            $db->where("mobile", $attending_employee_username);
            $db->where("business_id", $business_id);
            $db->where("status", 1);

            $xun_employee = $db->getOne("xun_employee");

            $attending_employee_id = $xun_employee["old_id"];
            $attending_staff_jid = $attending_employee_username . "@" . $attending_employee_host;
            $attending_employee_jid = $attending_employee_id . "@" . $chat_room_host;
        }

        $return_result_arr = array(
            "user_jid" => $chat_room_user_jid,
            "tag" => $tag,
            'business_id' => (string) $business_id,
            "created_date" => $chat_room_created_date,
            "modified_date" => $chat_room_modified_date,
            "chat_room_status" => $chat_room_status,
            "chat_room_jid" => $chat_room_jid,
            "attending_staff_jid" => $attending_staff_jid,
            "attending_employee_id" => $attending_employee_id,
            "attending_employee_jid" => $attending_employee_jid);

        return $return_result_arr;
    }

    public function user_incoming_wallet_transaction_chatroom_message($params)
    {
        global $config;
        $server_host = $config["erlang_server"];

        $db = $this->db;

        $xun_user_service = new XunUserService($db);

        $chatroom_id = trim($params["chatroom_id"]);
        $chatroom_host = trim($params["chatroom_host"]);
        $username = trim($params["username"]);
        $user_host = trim($params["user_host"]);
        $sender_jid = trim($params["sender_jid"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        if ($user_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00249') /*User host cannot be empty*/);
        }
        if ($chatroom_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00246') /*Chatroom ID cannot be empty*/);
        }
        if ($chatroom_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00257') /*Chatroom host cannot be empty*/);
        }
        // if ($sender_jid == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "sender_jid cannot be empty");
        // }

        $xun_user_service = new XunUserService($db);

        $wallet_transaction_record = $xun_user_service->getWalletTransactionByTxHash($chatroom_id);

        if (!$wallet_transaction_record) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00272') /*This chat room does not exists.*/);
        }

        $sender_address = $wallet_transaction_record["sender_address"];
        $recipient_address = $wallet_transaction_record["recipient_address"];

        $user_address_data = $xun_user_service->getAddressAndUserDetailsByAddressList([$sender_address, $recipient_address]);
        $sender_user_data = $user_address_data[$sender_address];
        $recipient_user_data = $user_address_data[$recipient_address];

        $xun_user = $xun_user_service->getUserByUsername($username);

        $user_id = (string)$xun_user["id"];
        $sender_user_id = (string)$sender_user_data["user_id"];
        $recipient_user_id = (string)$recipient_user_data["user_id"];

        $is_valid = false;
        $is_business = false;
        $is_sender = false;
        $business_employee_arr = [];
        $user_mobile_arr = [];

        $xun_business_service = new XunBusinessService($db);

        if ($user_id === $sender_user_id) {
            $is_valid = true;
            $is_sender = true;
        }
        else if($user_id === $recipient_user_id){
            $is_valid = true;
        } 
        else if($sender_user_data["type"] == "business" || $recipient_user_data["type"] == "business"){
            $db->where("business_id", [$sender_user_id, $recipient_user_id], "in");
            $db->where("employment_status", "confirmed");
            $db->where("status", "1");
            $employee_result = $db->get("xun_employee");

            foreach($employee_result as $employee_data){
                if($employee_data["mobile"] == $username){
                    $is_valid = true;
                    $is_business = true;
                    $user_business_id = (string)$employee_data["business_id"];

                    $is_sender = $user_business_id === $sender_user_id ? true : false;
                    break;
                }
            }
        }

        if (!$is_valid) {
           return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00377') /*User is not allowed to send message to this chat room.*/);
        }

        if($is_sender === true){
            if($recipient_user_data["type"] == "business"){
                //get business employee
                if($is_business === false){
                    $employee_result = $xun_business_service->getBusinessActiveEmployee($recipient_user_id);
                }
            }else{
                $user_mobile_arr[] = $recipient_user_data["username"];
            }
        }else{
            if($sender_user_data["type"] == "business"){
                //get business employee
                if($is_business === false){
                    $employee_result = $xun_business_service->getBusinessActiveEmployee($sender_user_id);
                }
            }else{
                $user_mobile_arr[] = $sender_user_data["username"];
            }
        }

        $business_employee_result = !empty($employee_result) ? $employee_result : [];

        foreach ($business_employee_result as $employee_data) {

            $employee["employee_mobile"] = $employee_data["mobile"];
            $employee["employee_host"] = $server_host;
            $employee["employee_id"] = $employee_data["old_id"];

            $employee_list[] = $employee;
        }

        $returnData["employee_list"] = $employee_list ? $employee_list : [];
        $returnData["user_list"] = $user_mobile_arr;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00224') /*Wallet transaction chat room recipient list.*/, "result" => $returnData);

    }
    public function group_chat_msg_forward($params) {
        $db = $this->db;
        $post = $this->post;

        $xun_user_service = new XunUserService($db);

        $group_jid = $params["group_jid"];
        $group_id = explode("@", $group_jid)[0];
        $forward_msg = $params["forward_msg"];
        $username = $params["username"];
        $xun_user = $xun_user_service->getUserByUsername($username);

        //get callback url
        $db->where("old_id", $group_id);
        $group_detail = $db->getOne("xun_group_chat");
        $callback_url = $group_detail["callback_url"];

        if ($callback_url != '') {

            $db->where("name", array("groupCryptoCallbackUser", "groupCryptoCallbackAPIKey"), "IN");
                $callbackAuth = $db->get("system_settings");

            foreach ($callbackAuth as $auth){

                    if($auth["name"] == "groupCryptoCallbackUser") {
                        $authUser = $auth["value"];
                    } else if($auth["name"] == "groupCryptoCallbackAPIKey") {
                        $authAPIKey = $auth["value"];
                    }
            }

            if ($authUser != "" && $authAPIKey != "") {
                $new_params = [];
                $new_params["command"] = "forwardMsg";
                $new_params["linkedRef"] = $group_id;
                $new_params["msg"] = $forward_msg;
                $new_params["username"] = $authUser;
                $new_params["apiKey"] = $authAPIKey;
                $new_params["phoneNumber"] = $username;
                $new_params["name"] = $xun_user["nickname"];
                
            }
            
            $post_return = $post->curl_post($callback_url, $new_params, 0, 1);
            $return_data = $post_return["data"];

            $arr_return = [];
            if($return_data["action"] != ""){
                if($return_data["action"] == "payment"){
                    $arr_return["action"] = $return_data["action"];
                    $arr_return["amount"] = (String)$return_data["amount"];
                    $arr_return["currency"] = $return_data["currency"];
                    $arr_return["destinationAddress"] = $return_data["destinationAddress"];
                    $arr_return["referenceID"] = (String)$return_data["referenceID"];
                }
                

                //return array("code" => 1, "message" => "SUCCESS", "message_d" => "SUCCESS.", "result" => $arr_return);
            }
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00144') /*Success.*/, "result" => $arr_return);
        }


    }

	public function group_chat_update_crypto_hash($params) {

        $db = $this->db;
		$post = $this->post;

        $xun_user_service = new XunUserService($db);
        $username = $params["username"];
        $group_jid = $params["group_jid"];
		$address = $params["address"];
		$command_message = $params["command_message"] ? $params["command_message"] : "";
		$transaction_hash = trim($params["transaction_hash"]) ? trim($params["transaction_hash"]) : "";
		$wallet_type = $params["wallet_type"] ? $params["wallet_type"] : "";
        $reference_id = $params["reference_id"];
		$reason = $params["reason"] ? $params["reason"] : "";

        if ($group_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00298') /*Group JID cannot be empty*/);
        }

		if ($address == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00314') /*address cannot be empty.*/);
        }

        /*if ($command_message == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Command message cannot be empty.");
        }*/

        //if ($transaction_hash == '') {
        //    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction hash cannot be empty.");
        //}

        /*if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Wallet type cannot be empty.");
        }*/

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $db->where("username", $username);
        $xun_user = $db->getOne("xun_user");
		
        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }
		
		$user_id = $xun_user["id"];
		$group_id = explode("@", $group_jid)[0];
		$current_timestamp = date("Y-m-d H:i:s");


        //$db->where("transaction_hash", $transaction_hash);
        //$xun_crypto_transaction = $db->getOne("xun_group_crypto_transaction");		

        //if (!$xun_crypto_transaction) {

            $fields = array("group_id", "user_id", "address", "command_message", "transaction_hash", "wallet_type", "created_at", "reason");
            $values = array($group_id, $user_id, $address, $command_message, $transaction_hash, $wallet_type, $current_timestamp, $reason);

            $insertData = array_combine($fields, $values);
            $row_id = $db->insert("xun_group_crypto_transaction", $insertData);
            //print(">>>>".$row_id);
            
			//callback
            $db->where("old_id", $group_id);
            $group_detail = $db->getOne("xun_group_chat");
            $callback_url = $group_detail["callback_url"];

            if ($callback_url != '') {

                $db->where("name", array("groupCryptoCallbackUser", "groupCryptoCallbackAPIKey"), "IN");
                $callbackAuth = $db->get("system_settings");

                foreach ($callbackAuth as $auth){

                        if($auth["name"] == "groupCryptoCallbackUser") {
                            $authUser = $auth["value"];
                        } else if($auth["name"] == "groupCryptoCallbackAPIKey") {
                            $authAPIKey = $auth["value"];
                        }
                }

                $new_params = [];
                $new_params["command"] = "completeTransaction";
                $new_params["linkedRef"] = $group_id;
                $new_params["walletAddress"] = $address;
                $new_params["transactionHash"] = $transaction_hash;
                $new_params["referenceID"] = $reference_id;
                $new_params["phoneNumber"] = $username;
                $new_params["name"] = $xun_user["nickname"];
				$new_params["reason"] = $reason;

				if ($transaction_hash == "") {
					$new_params["status"] = "failed";
				} else {
					$new_params["status"] = "success";
				}

                if ($authUser != "" && $authAPIKey != "") {
                        $new_params["username"] = $authUser;
                        $new_params["apiKey"] = $authAPIKey;
                }
                
                $post_return = $post->curl_post($callback_url, $new_params, 0, 1);

            }
        //}

		return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00144') /*Success.*/);

	}

    public function group_chat_command_detail($params)
    {

		$db = $this->db;

        $command_message = $params["command_message"];
        $username = $params["username"];
		$group_jid = $params["group_jid"];

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $db->where("username", $username);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if ($command_message == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00379') /*Command message cannot be empty.*/);
        }

        if ($group_jid == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00298') /*Group jid cannot be empty.*/);
        }

		$group_id = explode("@", $group_jid)[0];

        $db->where("old_id", $group_id);
        $xun_group = $db->getOne("xun_group_chat");

        if (!$xun_group) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00380') /*Invalid Group*/);
        }
	
		$crypto_address = $xun_group["crypto_address"];
		$crypto_currency = $xun_group["crypto_currency"];	
		$callback_url = $xun_group["callback_url"];

		if ($crypto_address == "") {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00127') /*Crypto Address cannot be empty.*/);
		}

        if ($crypto_currency == "" ) { 
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00381') /*Crypto Currency is empty.*/);
        }

        if ($callback_url == "" ) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00382') /*Callback url is empty.*/);
        }

		if (substr($command_message, 0, 2) == "*#") {			
			$arr_command_message = explode("##", substr($command_message, 2));
			//print(substr($command_message, 2));
			//print_r($arr_command_message);

			if (count($arr_command_message) > 0) {

				for ($i=0; $i<count($arr_command_message); $i++) {
					$command_text = $arr_command_message[$i];
					$arr_command_text = explode("-", $command_text);
					
					if (count($arr_command_text) == 2) {
						$command_prefix = strtolower($arr_command_text[0]);
						$command_amount = $arr_command_text[1];

						if( ($command_prefix == "p" || $command_prefix == "b" || $command_prefix == "t") && is_numeric($command_amount)){
							//print("prefix: " . $command_prefix);
							//print("amount: " . $command_amount);
							$sum_amount += $command_amount;

						} else {
                            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00383') /*Invalid command format.*/);
						}

					} else {
						return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00383') /*Invalid command format.*/);
					}
				}

				//print("Sum amount: " . $sum_amount);
				$arr_return["crypto_address"] = $crypto_address;
				$arr_return["crypto_currency"] = $crypto_currency;
				$arr_return["amount"] = (String)$sum_amount;

				return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00144') /*Success.*/, "result" => $arr_return);

			} else {
				return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00383') /*Invalid command format.*/);
			}

		} else {
			return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00383') /*Invalid command format.*/);
		}

	}

    public function get_fund_transfer_signing_details($params){
        $db = $this->db;
        $general = $this->general;

        global $setting, $xunCurrency, $xunCrypto, $xunServiceCharge, $xunPay, $xunStory;
        $username = trim($params["username"]);
        $recipient_address = trim($params["recipient_address"]);
        $reference_address = trim($params["reference_address"]);
        $sender_address = trim($params["sender_address"]);
        $amount = trim($params["amount"]);
        $wallet_type = trim($params["wallet_type"]);
        $transaction_type = trim($params["transaction_type"]);
        $type = trim($params["type"]);
        $check_service_charge = trim($params["check_service_charge"]);
        $batch_id = trim($params["batch_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        // if ($recipient_address == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Recipient address cannot be empty.");
        // }
        if ($sender_address == '') {
           return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00359') /*Sender address is required.*/);
        }
        // if ($amount == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Amount cannot be empty.");
        // }
        if ($wallet_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00150') /*Wallet Type cannot be empty*/);
        }
        if ($transaction_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00369') /*Transaction type cannot be empty.*/);
        }

        if($amount != ''){
            $amount = str_replace(",", "", $amount);
        }
        $wallet_type = strtolower($wallet_type);

        $currency_data = $xunCurrency->get_currency_info($wallet_type);
        if(!$currency_data || $currency_data["type"] != 'cryptocurrency'){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00340') /*Invalid wallet type.*/, "errorCode" => -100);
        }

        $xun_user_service = new XunUserService($db);
        
        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];
        $service_charge_rate = $xun_user["service_charge_rate"];

        $maintenance_start_time = $setting->systemSetting["bcMaintenanceStartTime"];
        $maintenance_end_time = $setting->systemSetting["bcMaintenanceEndTime"];

        $maintenance_coins = $setting->systemSetting["bcMaintenanceCoins"];

        $maintenance_coins_arr = explode(",", $maintenance_coins);
        
        $date = date("Y-m-d H:i:s");

        if($date >= $maintenance_start_time && $date <= $maintenance_end_time && in_array($wallet_type, $maintenance_coins_arr)){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00383') /*Service for this coin is currently unavailable.*/);
        }

        $coin_type = $currency_data["coin_type"];
        if($coin_type == "credit" && !in_array($transaction_type, ["internal_transfer"])){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00383') /*Service for this coin is currently unavailable.*/);
        }

        switch ($transaction_type){
            case "escrow":
                if ($recipient_address == '') {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00360') /*Recipient address is required.*/);
                }
                if ($amount == '') {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00305') /*Amount cannot be empty.*/);
                }
                $type = 0;
                break;

            case "internal_transfer": 
                if ($recipient_address == '') {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00360') /*Recipient address is required.*/);
                }
                if ($amount == '') {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00305') /*Amount cannot be empty.*/);
                }
                $type = 1;

                if($batch_id != ''){
                    //  validate batch id
                    $db->where("batch_id", $batch_id);
                    $phone_approve_request = $db->getOne("xun_business_phone_approve_request", "id, status, batch_id");

                    if(!$phone_approve_request){
                        return array(
                            "code" =>  0,
                            "message" => "FAILED",
                            "message_d" => $this->get_translation_message('E00384') /*Invalid batch ID.*/
                        );
                    }
                }

                //  check if address is business reward address (redemption)
                $db->where("address", $recipient_address);
                $recipient_crypto_address = $db->getOne("xun_crypto_user_address", "id, user_id, address, address_type");

                if($coin_type == "credit"){
                    $check_service_charge = 1;
                }
                if($recipient_crypto_address["address_type"] == "reward"){
                    $transaction_type = "reward";

                    if ($reference_address){
                        $db->where("external_address", $reference_address);
                        $crypto_user_address_id = $db->getValue("xun_user_crypto_external_address", "crypto_user_address_id");
    
                        if ($recipient_crypto_address['id'] == $crypto_user_address_id){
                            $db->where("user_id", $recipient_crypto_address['user_id']);
                            $min_max_amount = $db->getOne("xun_business_reward_setting", "min_amount, max_amount");
                            $min_amount = $min_max_amount['min_amount'];
                            $max_amount = $min_max_amount['max_amount'];
                            if ($min_amount != 0){
                                if ($amount < $min_amount){
                                    $error_message = $this->get_translation_message('E00449') /*Amount cannot be less than %%minimum%%.*/;
                                    $error_message = str_replace("%%minimum%%", (int)$min_amount, $error_message);
                                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                                }
                            }
                            if ($max_amount != 0){
                                if($amount > $max_amount){
                                    $error_message = $this->get_translation_message('E00450') /*Amount cannot be more than %%maximum%%.*/;
                                    $error_message = str_replace("%%maximum%%", (int)$max_amount, $error_message);
                                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                                }
                            }
    
                        }
                    }
                }
                break;
            
            case "external_transfer":
                if ($recipient_address == '') {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00360') /*Recipient address is required.*/);
                }
                if ($amount == '') {
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00305') /*Amount cannot be empty.*/);
                }
                $type = 1;

                if($batch_id != ''){
                    //  validate batch id
                    $db->where("batch_id", $batch_id);
                    $phone_approve_request = $db->getOne("xun_business_phone_approve_request", "id, status, batch_id");

                    if(!$phone_approve_request){
                        return array(
                            "code" =>  0,
                            "message" => "FAILED",
                            "message_d" => $this->get_translation_message('E00384') /*Invalid batch ID.*/
                        );
                    }
                }
                break;

            case "pay": 
                $pay_params = $params;
                $pay_params["sender_address"] = $sender_address;
                $pay_params["wallet_type"] = $wallet_type;
                $pay_params["transaction_type"] = $transaction_type;
                $pay_params["type"] = $type;
                $pay_signing_return = $xunPay->get_signing_details($pay_params);

                if(isset($pay_signing_return["code"]) && $pay_signing_return["code"] == 0){
                    return $pay_signing_return;
                }
                $type = 1;

                $pay_signing_details = $pay_signing_return["data"];
                $recipient_address = $pay_signing_details["recipient_address"];
                $amount = $pay_signing_details["amount"];

                break;
            
            case "story": 
                $story_params = $params;
                $signing_return = $xunStory->get_donation_signing_details($story_params);

                if(isset($signing_return["code"]) && $signing_return["code"] == 0){
                    return $signing_return;
                }
                $type = 1;

                $signing_details = $signing_return["data"];
                $amount = $signing_details["amount"];
                $wallet_type = $signing_details["wallet_type"];
                $recipient_address = $signing_details["recipient_address"];

                break;

            default:
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00371') /*Invalid transaction type*/);
        }

        $rate = $xunCurrency->get_rate($wallet_type, "usd");
        
        $xun_commission = new XunCommission($db, $setting, $general);
        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
        $decimal_places = $decimal_place_setting["decimal_places"];
        $formatted_amount = (string)bcmul((string) $amount, 1, $decimal_places);
        
        $currency_info = $xunCurrency->get_currency_info($wallet_type);
        $currency_unit = $currency_info["symbol"];

        $exclude_service_charge = 0;
   
        if($type == 1){
            $exclude_service_charge = 1;
        }

        $newParams["receiver_address"] = $recipient_address;
        $newParams["sender_address"] = $sender_address;
        $newParams["amount"] = $formatted_amount;
        $newParams["wallet_type"] = $wallet_type;
        $newParams["exchange_rate"] = $rate;
        $newParams["transaction_type"] = $transaction_type;
        $newParams["currency_unit"] = $currency_unit;
        $newParams["decimal_place_setting"] = $decimal_place_setting;
        $newParams["exclude_service_charge"] = $exclude_service_charge;
        
        // TODO: check sender address
        // TODO: check batch ID and receiver address
        $fund_transfer_details = $xun_commission->get_fund_transfer_details($newParams);
        if(isset($fund_transfer_details["code"]) && $fund_transfer_details["code"] == 0){
            return $fund_transfer_details;
        }
        
        $fund_transfer_details_arr = $fund_transfer_details["fund_signing_arr"];
        $total_amount = $fund_transfer_details["total_amount"];
        $address_data_arr = $fund_transfer_details["address_data_arr"];

        try{
            $user_wallet_balance = $xunCrypto->get_wallet_balance($sender_address, $wallet_type);
        }catch(exception $e){
            $error_message = $e->getMessage();
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200')/*Something went wrong. Please try again.*/, "errorCode" => -109, "developer_msg" => $error_message);
        }

        if($user_wallet_balance < $total_amount){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00338') /*Insufficient balance.*/, "errorCode" => -101);
        }

        $xunWallet = new XunWallet($db);

        $company_wallet_address_list = $xunCrypto->company_wallet_address();

        for($i = 0; $i < count($fund_transfer_details_arr); $i++){
            $arr_data = $fund_transfer_details_arr[$i];
            $data_amount = $arr_data["amount"];
            $address_type = $arr_data["transaction_type"];
            $isEscrow = $address_type == "escrow" ? 1 : 0;
            $wallet_transaction_type = "send";
            $transaction_status = "pending";
            $escrow_contract_address = '';

            if ($isEscrow){
                $destination_address = $recipient_address;
                $escrow_contract_address = $arr_data["destination_address"];
            }else{
                $destination_address = $arr_data["destination_address"];
            }

            $destination_address_data = $address_data_arr[$destination_address];
            $sender_address_data = $address_data_arr[$sender_address];

            if($destination_address_data){
                $recipient_user_id = $destination_address_data["user_id"];
            }else if(isset($company_wallet_address_list[$destination_address])){
                $recipient_user_id = $company_wallet_address_list[$destination_address]["type"];
            }else{
                $recipient_user_id = "";
            }

            if($sender_address_data){
                $sender_user_id = $sender_address_data["user_id"];
            }else if(isset($company_wallet_address_list[$sender_address])){
                $sender_user_id = $company_wallet_address_list[$sender_address]["type"];
            }else{
                $sender_user_id = "";
            }

            $reference_id = $address_type == "service_charge" ? ($transaction_id ? $transaction_id : "") : "";
            $transactionObj = new stdClass();
            $transactionObj->status = $transaction_status;
            $transactionObj->transactionHash = "";
            $transactionObj->transactionToken = "";
            $transactionObj->senderAddress = $sender_address;
            $transactionObj->recipientAddress = $destination_address;
            $transactionObj->senderUserID = $sender_user_id;
            $transactionObj->recipientUserID = $recipient_user_id;
            $transactionObj->userID = $user_id;
            $transactionObj->walletType = $wallet_type;
            $transactionObj->amount = $data_amount;
            $transactionObj->addressType = $address_type;
            $transactionObj->transactionType = $wallet_transaction_type;
            $transactionObj->escrow = $isEscrow;
            $transactionObj->referenceID = $reference_id;
            $transactionObj->escrowContractAddress = $escrow_contract_address ? $escrow_contract_address : '';
            $transactionObj->batchID = $batch_id;
            $transactionObj->createdAt = $date;
            $transactionObj->updatedAt = $date;
            $transactionObj->expiresAt = '';

            if(!$check_service_charge){
                $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);
            }

            if($address_type == "service_charge"){
                // add to service charge table
                $charged_on = $arr_data["charged_on"];
                $service_charge_type = $transaction_type;
                if($charged_on == "recipient"){
                    $service_charge_transaction_type = "receive";
                    $destination_address_data = $address_data_arr[$destination_address];

                    $service_charge_user_data = $address_data_arr[$recipient_address];
                }else{
                    $service_charge_transaction_type = "send";
                    $service_charge_user_data = $sender_address_data;
                }

                // $service_charge_user_id = $this->get_service_charge_user_id($service_charge_user_data);

                $service_charge_user_id = $service_charge_user_data["user_id"];

                $new_params = array(
                    "user_id" => $service_charge_user_id,
                    "wallet_transaction_id" => $transaction_id,
                    "amount" => $data_amount,
                    "wallet_type" => $arr_data["wallet_type"],
                    "service_charge_type" => $service_charge_type,
                    "transaction_type" => $service_charge_transaction_type,
                    "ori_tx_wallet_type" => $arr_data["wallet_type"],
                    "ori_tx_amount" => $formatted_amount
                );

                $xunServiceCharge->insert_service_charge($new_params);
            }
            
            $arr_data["id"] = $transaction_id;
            $arr_data["short_name"] = $currency_unit;
            $tx_transaction_type = $address_type == "reward" ? "internal_transfer" : $address_type;
            $tx_transaction_type = $coin_type == "credit" ? "credit" : $tx_transaction_type;
            $arr_data["transaction_type"] = $tx_transaction_type;
            $fund_transfer_details_arr[$i] = $arr_data;
            
        }

        $return_data = array("sign_data_list" => $fund_transfer_details_arr);

        $sender_address_user_data = $address_data_arr[$sender_address];
        $sender_address_user_id = $sender_address_user_data["user_id"];
        
        if($transaction_type == "pay"){
            //  fix user id for pay transaction
            try{
                $xunPay->insert_pay_transaction($sender_address_user_id, $pay_signing_details, $fund_transfer_details_arr);
            }catch(exception $e){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200')/*Something went wrong. Please try again.*/, "error_message" => $e->getMessage());
            }
        }
        // else if($transaction_type == "story"){
        //     try{
        //         $xunStory->insert_donation_transaction($sender_address_user_id, $signing_details, $fund_transfer_details_arr, $rate);
        //     }catch(exception $e){
        //         return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $e->getMessage());
        //     }
        // }

        if($phone_approve_request && $transaction_type == "external_transfer"){
            //  update wallet transaction id
            $update_phone_request_data = array(
                "wallet_transaction_id" => $transaction_id,
                "updated_at" => $date
            );
            $db->where("address", $recipient_address);
            $db->where("request_id", $phone_approve_request["id"]);

            $db->update("xun_business_phone_approve_request_detail", $update_phone_request_data);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00208') /*Fund transfer signing details.*/, "data" => $return_data);
    }

    public function get_service_charge_details($params){
        global $setting, $xunCurrency;
        $db = $this->db;
        
        $username = trim($params["username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username, "id, nickname, disabled");

        if (!$xun_user || $xun_user["disabled"] == 1) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $theNuxCommissionFeePct = $setting->systemSetting["theNuxCommissionFeePct"];
        $min_commission = $setting->systemSetting["theNuxCommissionFeeMin"];


        $currency_list = $db->getValue("xun_coins", "currency_id", null);
        $currency_rate_list = $xunCurrency->get_live_price_by_currency_list($currency_list);
        $result_arr = [];
        $price_unit = "USD";
        $currency_list_len = count($currency_rate_list);
        for ($i = 0; $i < $currency_list_len; $i++) {
            $key = $currency_list[$i];
            $key_lower = strtolower($key);

            $data = $currency_rate_list[$key_lower];
            if (is_array($data)) {
                $currency_data = $data;
                $currency_data["unit"] = $price_unit;
            } else {
                $currency_data = array(
                    "value" => $data,
                    "unit" => $price_unit,
                );
            }
            $data = [];
            $data["value"] = $currency_data["value"];
            $data["unit"] = $currency_data["unit"];
            $result_arr[$key] = $data;
        }

        $return_data = array(
            "service_charge_percentage" => $theNuxCommissionFeePct,
            "min_service_charge" => array("amount" => $min_commission, "unit" => "USD"),
            "price_list" => $result_arr
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00209') /*Service charge details.*/, "data" => $return_data);

    }

    public function get_service_charge_user_id($user_address_data)
    {
        $db = $this->db;
        $user_id = $user_address_data["user_id"];
        if($user_address_data["type"] == "user"){
            $service_charge_user_id = $user_id;
        }else{
            $xun_business_service = new XunBusinessService($db);
            $xun_user_service = new XunUserService($db);

            $xun_business_account = $xun_business_service->getBusinessDetails($user_id);

            $owner_username = $xun_business_account["main_mobile"];

            $owner_user_data = $xun_user_service->getUserByUsername($owner_username, "id, nickname, disabled");

            $service_charge_user_id = $owner_user_data["id"];
        }

        return $service_charge_user_id;
    }
    
    private function get_xmpp_jid($jid)
    {

        $pos = stripos($jid, '@');

        if ($pos === false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00034') /*Malformed JID*/);
        } else {
            $jid_arr = explode("@", $jid);
            $jid_user = $jid_arr[0];
            $jid_host = $jid_arr[1];
        }

        return array("code" => 1, "jid_user" => $jid_user, "jid_host" => $jid_host);
    }

    private function compose_xun_employee($result)
    {
        $general = $this->general;

        $returnData["id"] = (string) $result["id"];
        $returnData["business_id"] = (string) $result["business_id"];
        $returnData["mobile"] = $result["mobile"] ? $result["mobile"] : "";
        $returnData["name"] = $result["name"] ? $result["name"] : "";
        $returnData["status"] = $result["status"] ? $result["status"] : "";
        $returnData["employment_status"] = $result["employment_status"] ? $result["employment_status"] : "";
        $returnData["employee_id"] = $result["old_id"] ? $result["old_id"] : "";
        $returnData["role"] = $result["role"] ? $result["role"] : "";
        $returnData["created_at"] = $result["created_at"] ? $general->formatDateTimeToIsoFormat($result["created_at"]) : "";
        $returnData["updated_at"] = $result["updated_at"] ? $general->formatDateTimeToIsoFormat($result["updated_at"]) : "";

        return $returnData;
    }

    public function is_employee_confirmed($username, $business_id)
    {
        $db = $this->db;

        $db->where("mobile", $username);
        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $db->where("employment_status", "confirmed");
        $xun_employee = $db->getOne("xun_employee");

        return $xun_employee;
    }

    public function get_translation_message($message_code)
    {
        // Language Translations.
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;
    }

    public function group_chat_key_revoke($params) {

        global $config;
        $server_host = $config["erlang_server"];
        $group_host = "conference." . $server_host;
        $date = date("Y-m-d H:i:s");

        $db = $this->db;
        $general = $this->general;

        $group_id = $params["group_id"];

        if (!$group_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00094') /*Group ID cannot be empty*/);
        }

        $db->where("old_id", $group_id);
        $group_result = $db->getOne("xun_group_chat");

        if (!$group_result) {
           return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00385') /*Group not found.*/);
        } else {

            while (1) {

                $alphanumberic  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $invitekey         = substr(str_shuffle($alphanumberic), 0, 32);

                $db->where('invite_key', $invitekey);
                $result = $db->get('xun_group_chat');

                if (!$result) {
                    break;
                }

            }

            $updateData = [];
            $updateData["invite_key"] = $invitekey;
            $updateData["updated_at"] = $date;

            $db->where("old_id", $group_id);
            $db->update("xun_group_chat", $updateData);

            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00210') /*The previous invite link is now revoked and a new invite link has been created.*/, "invite_key" => $invitekey);

        }

    }

	public function group_chat_key_detail($params) {

        $db = $this->db;

        $invite_key = $params["invite_key"];
        $username = $params["username"];

        if ($invite_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00387') /*Invite key cannot be empty.*/);
        }

        $db->where("username", $username);
        $xun_user = $db->getOne("xun_user");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $db->where("invite_key", $invite_key);
        $xun_group = $db->getOne("xun_group_chat");

        if (!$xun_group) {
           return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00388') /*Invalid invite key.*/);
        } else {
				$data["group_id"] = $xun_group["old_id"];
				$data["group_host"] = $xun_group["host"];
                $data["group_name"] = "Group Name";
                $data["group_image"] = "abcd1234";
                return array('code' => 1, 'message' => "Success", 'message_d' => $this->get_translation_message('B00012') /*Success*/, "result" => $data);
        }

    }

    public function business_request_money_update_tx_hash($params){
        global $xunBusiness;
        $db = $this->db;
        $general = $this->general;
        $language = $general->getCurrentLanguage();
        $translations = $general->getTranslations();

        $username = trim($params["username"]);
        $id = trim($params["id"]);
        $transaction_hash = trim($params["transaction_hash"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($transaction_hash == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00373') /*Transaction hash cannot be empty.*/);
        }

        if ($id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00006') /*ID cannot be empty.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $xun_business_service = new XunBusinessService($db);
        $business_request_money_data = $xun_business_service->getBusinessRequestMoneyByID($id);
        if(!$business_request_money_data){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00364') /*Invalid ID.*/);
        }
        
        if($business_request_money_data["username"] != $username){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00044') /*Invalid record.*/);
        }

        $business_obj = new stdClass();
        $business_obj->id = $id;
        $business_obj->transactionHash = $transaction_hash;

        $ret_val = $xun_business_service->updateBusinessRequestMoneyTxHashByID($business_obj);

        if($ret_val){
            //  callback to business
            $business_id = $business_request_money_data["business_id"];
            $callback_params = array(
                "transaction_hash" => $transaction_hash,
                "reference_id" => $business_request_money_data["reference_id"]
            );
            
            $xunBusiness->business_request_money_callback($business_id, $callback_params);
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);
        }else{
            return array("code" => 0, "message" => "FAILED", "message_d" => $translations['E00141'][$language]/*"Internal server error. Please try again.")*/);
        }
    }

    public function set_fiat_currency($params){
        $db = $this->db;

        $username = trim($params["username"]);
        $fiat_currency_id = $params["fiat_currency_id"];
        $business_id = $params["business_id"];
       
    
        if($username == ''){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        
        if($fiat_currency_id == ''){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00391') /*Fiat Currency ID is empty.*/);
        }

       
        if($business_id){
            
            $db->where('id', $business_id);
            $db->where('type', "business");
            $xun_user_result = $db->getOne('xun_user');
    
            if(!$xun_user_result){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00069') /*This business account does not exists.*/);
            }
            $user_id = $business_id;
        }
        else{
            $db->where('username', $username);
            $xun_user_result = $db->getOne('xun_user');
    
            if(!$xun_user_result){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00025') /*User does not exist.*/);
            }
            $user_id = $xun_user_result["id"];
        }
       

        $db->where('name', "selectedFiatCurrency");
        $db->where('user_id', $user_id);
        $user_setting_result = $db->getOne('xun_user_setting');

        if(!$user_setting_result){
            $insertUserSetting = array(
                "user_id" => $user_id,
                "name" => "selectedFiatCurrency",
                "value" => $fiat_currency_id,
                "created_at" =>  date("Y-m-d H:i:s"),
                "updated_at" =>  date("Y-m-d H:i:s"),
            );


            $id = $db->insert('xun_user_setting', $insertUserSetting);
        }
        else{
            $updateUserSetting = array(
                "value" => $fiat_currency_id,
                "updated_at" =>  date("Y-m-d H:i:s"),
            );

            $db->where('user_id', $user_id);
            $db->where('name', "selectedFiatCurrency");
            $db->update('xun_user_setting', $updateUserSetting);
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00214') /*Set Fiat Currency Setting Success.*/, "selectedFiat" => $fiat_currency_id);
    }

    public function get_fiat_currency_listing($params){
        global $country;
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $business_id = $params["business_id"];

        if($username == ''){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if($business_id){
            $db->where('id', $business_id);
            $db->where('type', "business");
            $xun_user_result = $db->getOne('xun_user');
    
            if(!$xun_user_result){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00069') /*This business account does not exists.*/);
            }
            $user_id = $business_id;
        }
        else{
            $db->where('username', $username);
            $xun_user_result = $db->getOne('xun_user');

            if(!$xun_user_result){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00025') /*User does not exist.*/);
            }
            $user_id = $xun_user_result["id"];
        }
       
        $db->where('status', 1);
        $db->where('type', "currency");
        $currency_list = $db->map('symbol')->ObjectBuilder()->get("xun_marketplace_currencies",null, "symbol, currency_id, image, image_md5, created_at");

        foreach($currency_list as $key=>$value){
            $currency_id = strtoupper($value->currency_id);
            $name = strtoupper($value->symbol);
            $image = $value->image;
            $image_md5 = $value->image_md5;
            $created_at = $value->created_at;

            $currencyArr[$currency_id]["name"] = $name;
            $currencyArr[$currency_id]["currency_id"] = $currency_id; 
            $currencyArr[$currency_id]["image"] = $image; 
            $currencyArr[$currency_id]["image_md5"] = $image_md5; 
            $currencyArr[$currency_id]["created_at"] = $created_at;

        }

        $db->where('name', "selectedFiatCurrency");
        $db->where('user_id', $user_id);
        $user_setting_result = $db->getOne('xun_user_setting');

        if($user_setting_result){
            $user_fiat_choice = $user_setting_result["value"];
        }
        else{
            $mobile_number_info = $general->mobileNumberInfo($username, null);
            if($mobile_number_info){
                $region_code = $mobile_number_info["regionCode"];
                $country_params = array("iso_code2_arr" => [$region_code]);
                $country_data_arr = $country->getCountryDataByIsoCode2($country_params);

                if (!empty($country_data_arr)) {
                    $country_data = $country_data_arr[strtoupper($region_code)];
                    $currency_code = $country_data["currency_code"];
                }
            }
            $user_fiat_choice = $currency_code ? (isset($currencyArr[$currency_code]) ?  strtolower($currency_code) : "usd")  : "usd";
        }

        $data["user_fiat_choice"] = $user_fiat_choice;
        $data["currency_list"] = $currencyArr;
        
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00215') /*Fiat Currency List.*/, "data" => $data);
        
    }

    public function get_live_price_listing_v1($params)
    {
        // app/crypto/live_price
        global $xunCurrency, $xunBusinessPartner, $xunCoins;
        global $setting;
        global $xunCompanyWalletAPI;
        $db = $this->db;
        $post = $this->post;

        $username = trim($params["username"]);
        $currency_list = $params["currency_list"];
        $business_id = trim($params["business_id"]);

        $xun_user_service = new XunUserService($db);

        if($business_id)
        {
            $db->where('type', "business");
            $db->where('id', $business_id);
            $xun_user = $db->getOne('xun_user');

            if(!$xun_user){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00082') /*Business not found.*/);
            }

            $user_id = $business_id;
        }else{
            $xun_user = $xun_user_service->getUserByUsername($username);
            $user_id = $xun_user["id"];
        }
        $nickname = $xun_user["nickname"];

        if (!is_array($currency_list)) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00345') /*Currency list must be an array.*/);
        }

        $currency_list_len = count($currency_list);
        $currency_lower_list = [];
        for ($i = 0; $i < $currency_list_len; $i++) {
            $currency_lower_list[] = strtolower($currency_list[$i]);
        }
        $currency_rate_list = $xunCurrency->get_live_price_by_currency_list_v1($currency_lower_list);
        
        $search_business_coins_obj = new StdClass();
        $search_business_coins_obj->walletType = $currency_lower_list;
        $business_coins_arr = $xunCoins->getBusinessCoinInfo($search_business_coins_obj, null, "wallet_type");

        /**
         * currency_id
         * value
         * unit
         */

        $businessDefaultImageUrl = $setting->systemSetting["businessDefaultImageUrl"];

        $user_coin_list = $xunBusinessPartner->get_user_coin($user_id);
        
        $result_arr = [];
        $price_unit = "USD";

        //  generate credit wallet if user doesnt have it yet
        $user_credit_address_params = array(
            "user_id" => $user_id,
            "address_type" => "credit"
        );

        $credit_address_result = $xunCompanyWalletAPI->getUserCompanyAddress($user_credit_address_params);
        
        $credit_wallet_internal_address = "";
        if($credit_address_result['code'] === 1){
            $credit_address_data = $credit_address_result["data"];
    
            $credit_wallet_internal_address = $credit_address_data['internal_address'];
        }

        for ($i = 0; $i < $currency_list_len; $i++) {
            $key = $currency_list[$i];
            $key_lower = strtolower($key);

            $data = $currency_rate_list["crypto_usd_rate"][$key_lower];

            $user_coin = $user_coin_list[$key_lower];

            if (is_array($data)) {
                $currency_data = $data;
                $currency_data["unit"] = $price_unit;

                if($user_coin && $user_coin["default_show"] == 1){
                    $currency_data["is_show_new_coin"] = 1;
                }

                if($currency_data["image"] == ''){
                    $currency_data["image"] = $businessDefaultImageUrl;
                }

                $wallet_type_group_arr = $this->map_wallet_type_group($business_coins_arr, $data);

                $currency_data = array_merge($currency_data, $wallet_type_group_arr);
            } else {
                $currency_data = array(
                    "value" => $data,
                    "unit" => $price_unit,
                    "image" => $businessDefaultImageUrl
                );
            }

            //  return credit internal wallet address
            if($currency_data["coin_type"] == "credit"){
                $currency_data["internal_address"] = $credit_wallet_internal_address;
            }

            $result_arr["crypto_usd_rate"][$key] = $currency_data;
        }
        $result_arr["crypto_exchange_rate"] = $currency_rate_list["crypto_exchange_rate"];

        if(!empty($currency_lower_list)){
            $db->where("a.user_id", $user_id);
            $db->where("b.wallet_type", $currency_lower_list, "IN");
            $db->join("xun_business_coin b", "a.business_coin_id=b.id", "LEFT");
            $user_coin_arr = $db->get("xun_user_coin a", null, "a.*, b.business_id, b.wallet_type");
        }

        if(!empty($user_coin_arr)){
            $business_ids = array_column($user_coin_arr, "business_id");

            $db->where('name', 'businessGetUserDetailsCallbackURL');
            $db->where('user_id', $business_ids, "IN");
            $partner_url = $db->map("user_id")->ArrayBuilder()->get("xun_user_setting");
        }

        $new_params["mobile"] = $username;
        $command = "retrieveSMSUserData";
        $partner_params = [];
        $partner_params["command"] = $command;
        $partner_params["params"] = $new_params;

        $user_info = [];
        foreach($user_coin_arr as $user_coin){
            $coin_business_id = $user_coin["business_id"];
            $sign_up_date = $user_coin["created_at"];
            $sign_up_date = strtotime($sign_up_date);
            $wallet_type = $user_coin["wallet_type"];
            $partner_url_data = $partner_url[$coin_business_id];

            if($partner_url_data && $partner_url_data["value"] != ''){
                $callback_url = $partner_url_data['value'];
                $partner_result = $post->curl_post($callback_url, $partner_params, 0);
                
                if($partner_result["code"] === 0){
                    $partner_user_info = $partner_result["data"];
                    $balance = $partner_user_info["balance"];
                    $balance = (string)$balance;

                    $user_partner_name = $partner_user_info["name"];
                    $sign_up_date = $partner_user_info["signUpDate"];
                    $partner_currency = $partner_user_info["currency"];
                }
            }

            $user_partner_name = $user_partner_name ? $user_partner_name : $nickname;
            $sign_up_date = date("m/y", $sign_up_date);
            $partner_currency = $partner_currency ? $partner_currency : "";
            $balance = $balance ? $balance : '0';

            $user_info_data = array(
                "name" => $user_partner_name,
                "signUpDate" => $sign_up_date,
                "currency" => $partner_currency,
                "balance" => $balance
            );

            unset($user_partner_name);
            unset($sign_up_date);
            unset($partner_currency);
            unset($balance);

            $user_info[$wallet_type] = $user_info_data;
        }

        $result_arr['user_info'] = $user_info;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00202') /*Live pricing.*/, "data" => $result_arr);
    }

    public function get_external_address($params){
        $db = $this->db;

        $internalAddress = $params["internal_address"];
        $username = trim($params["username"]);

        if($internalAddress == ''){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00173') /*Internal Address is empty.*/);
        }

        $db->where('internal_address', $internalAddress);
        $address_result = $db->get('xun_crypto_external_address', null,"external_address, wallet_type");

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00216') /*External Address List.*/, "data" => $address_result);

    }

    public function set_external_address($params){
        $db = $this->db;

        $externalAddress = $params["external_address"];
        $walletType = $params["wallet_type"];
        $username = trim($params["username"]);
        $business_id = $params["business_id"];

        if($username == ''){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if($externalAddress == ''){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00393') /*External address cannot be empty.*/);
        }

        if($walletType == ''){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00207') /*Wallet type is required.*/);
        }

        //  check if business have credit type coin
        $xun_business_service = new XunBusinessService($db);

        $business_coin_params = new XunBusinessCoinModel($db);
        $business_coin_params->setWalletType($walletType);

        $columns = "id, business_id, wallet_type, type";

        $business_coin = $xun_business_service->getBusinessCoin($business_coin_params, $columns);

        if($business_id)
        {
            $db->where('type', "business");
            $db->where('id', $business_id);
            $business_result = $db->getOne('xun_user');

            if(!$business_result){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00082') /*Business not found.*/);
            }

            $db->where('active', 1);
            $db->where('user_id', $business_result["id"]);
        }
        else{
            $db->where('username', $username);
            $db->where("type", "user");
            $user_result = $db->getOne('xun_user');
    
            if(!$user_result){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00202') /*User does not exist.*/);
            }

            $db->where('user_id', $user_result["id"]);
            $db->where('active', 1);
        }
        
        $address_type = "personal";

        if(!is_null($business_coin) && $business_coin->getType() == "credit"){
            $address_type = "credit";
        }
        $db->where('address_type', $address_type);
        $user_address_result = $db->getOne('xun_crypto_user_address');

        if(!$user_address_result){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00395') /*User internal address not found.*/);
        }

        $internalAddress = $user_address_result["address"];

        
        $db->where("(internal_address = ? and wallet_type = ?)", array($internalAddress, $walletType));
        $db->orWhere("(internal_address != ? and external_address = ?)", array($internalAddress, $externalAddress));
        $checkAddress = $db->get('xun_crypto_external_address');

        if($checkAddress){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00398') /*Address already exists.*/);
        }

        $insertArray = array(
            "internal_address" => $internalAddress,
            "external_address" => $externalAddress,
            "wallet_type" => $walletType,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->insert('xun_crypto_external_address', $insertArray);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00219') /*Set External Address Successful.*/);

    }

    public function get_system_settings($params){
        global $setting;
        $db = $this->db;

        $username = trim($params["username"]);

        $xunUserService = new XunUserService($db);

        $device_info_obj = new stdClass();
        $device_info_obj->username = $username;
        
        $xun_device_info = $xunUserService->getDeviceInfo($device_info_obj);
        
        $show_story = $setting->systemSetting["showStory"];
        $story_setting = $show_story == 1 ? 1 : 0;

        $show_swapcoins = $setting->systemSetting["showSwapcoins"];
        $swapcoins_setting = $show_swapcoins == 1 ? 1 : 0;

        if ($xun_device_info) {
            $os = $xun_device_info["os"];
            
            $app_version = $xun_device_info["app_version"];
            $app_version = str_replace("(", ".", $app_version);
            $app_version = str_replace(")", "", $app_version);
            
            $ex_android_version = '1.0.239.1';
            if($os == 1 && $app_version == $ex_android_version){
                $story_setting = 1;
            }
        }

        if ($username == "+60192135135" || $username == "+60122590231" || $username == "+601116578248" || $username == "+60173177319" || $username == "+60173690829" || $username == "+601118593487" || $username == "+6582511977" || $username == "+60126355646") {
                $story_setting = 1;
	}

        $return_data = [];
        $return_data["show_story"] = $story_setting;
        $return_data["show_swapcoins"] = $swapcoins_setting;
        
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00220') /*System settings.*/, "data" => $return_data);
    }

    public function update_erlang_vcard($params)
    {
        global $log;
        $db = $this->db;

        $username = trim($params["username"]);
        $server_host = trim($params["server"]);
        $nickname = trim($params["nickname"]);
        $photo = $params["photo"];
        $binval = trim($photo["binval"]);
        $type = trim($photo["type"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        if ($server_host == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00253') /*Server cannot be empty*/);
        }
        
        if ($binval == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00400') /*Binval cannot be empty*/);
        }
        
        if ($type == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00238') /*Type cannot be empty*/);
        }

        $xun_user_service = new XunUserService($db);
        
        $xun_user = $xun_user_service->getUserByUsername($username);

        //  check md5 in ejabberd if image changed
        $user_id = $xun_user["id"];
        $user_type = $xun_user["type"];

        if (!$xun_user) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00157') /*Invalid user*/);
        }

        //  upload photo binval to aws s3 to get the url
        $profile_picture_result = $xun_user_service->uploadProfilePictureBinval($user_id, $binval, $type, $user_type);

        $picture_url = '';
        if($profile_picture_result){
            $picture_url = $profile_picture_result["object_url"];
        }

        $date = date("Y-m-d H:i:s");

        // $updateData = [];
        // $updateData["nickname"] = $nickname;
        // $updateData["updated_at"] = $date;

        // $db->where("username", $username);
        // $db->where("server_host", $server_host);
        // $db->update("xun_user", $updateData);

        //  update xun_user_details
        $insert_user_details_data = Array (
            "user_id" => $user_id,
            "picture_url" => $picture_url,
            "created_at" => $date,
            "updated_at" => $date
        );
        $update_columns = Array ("picture_url", "updated_at");
        $last_insert_id = "id";
        $db->onDuplicate($update_columns, $last_insert_id);
        $user_details_id = $db->insert ('xun_user_details', $insert_user_details_data);
        if(!$user_details_id){
            $log->write("\n " . $db->getLastError());
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/);
    }

    public function generate_payment_address($params){
        $db = $this->db;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        
        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $return_data = array(
            "internal_address" => "0x123456789",
            "external_address" => "0x987654321",
            "wallet_type" => "sms123rewards",
            "unit" => "sr1"
        );

        return array(
            "code" => 1,
            "message" => "SUCCESS",
            "message_d" => $this->get_translation_message('B00217') /*Payment address details*/,
            "data" => $return_data
        );
    }

    public function get_user_profile($params){
        $db = $this->db;

        $username = trim($params["username"]);
        $profile_username = trim($params["profile_username"]);

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        if ($profile_username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00401') /*Profile Username is required.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $profile_xun_user = $xun_user_service->getUserByUsername($profile_username);

        if(!$profile_xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }
        $profile_user_id = $profile_xun_user["id"];
        $db->where("user_id", $profile_user_id);
        $profile_user_details = $db->getOne("xun_user_details");

        $picture_url = $profile_user_details["picture_url"];

        $return_data = [];
        $return_data["username"] = $profile_username;
        $return_data["picture_url"] = $picture_url ? $picture_url : "";
        $return_data["nickname"] = $profile_xun_user["nickname"];

        return array("code" => 1,
            "message" => "SUCCESS",
            "message_d" => $this->get_translation_message('B00012') /*Success*/,
            "data" => $return_data
        );

    }

    public function add_coins($params){
        global $xunReward;

        $db = $this->db;

        $username = trim($params["username"]);
        $business_id = trim($params['business_id']);
        // $wallet_type = trim($params['wallet_type']);
        $wallet_type = $params["wallet_type"];

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        if($wallet_type == ''){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00207') /*Wallet type is required.*/);
        }

        $wallet_type = strtolower($wallet_type);
        // if(is_array($wallet_type)){
            // if(empty($wallet_type)){
            //     return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00207') /*Wallet type is required.*/);
            // }

        //     $wallet_type_arr = array_map(function($v){
        //         return strtolower($v);
        //     }, $wallet_type);
        // }else{
        //     $wallet_type = trim($wallet_type);
        //     if($wallet_type == ''){
        //         return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00207') /*Wallet type is required.*/);
        //     }

        //     $wallet_type_arr = [strtolower($wallet_type)];
        // }

        if($business_id)
        {
            $db->where('type', "business");
            $db->where('id', $business_id);
            $business_result = $db->getOne('xun_user');

            if(!$business_result){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00082') /*Business not found.*/);
            }

            $user_id = $business_id;

        }
        else{
            $xun_user_service = new XunUserService($db);

            $xun_user = $xun_user_service->getUserByUsername($username);
    
            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
            }

            $user_id = $xun_user['id'];
        }

        // $db->where('a.currency_id', $wallet_type_arr, "IN");
        // $db->where('a.type', ["reward", "cash_token"], "IN");
        // $db->join("xun_business_coin b", "a.currency_id=b.wallet_type", "LEFT");
        // $xun_coins = $db->get('xun_coins a', null, "b.*");

        $subq = $db->subQuery();
        $subq->where("wallet_type", $wallet_type);
        $subq->get("xun_business_coin", null, "business_id");

        $db->where("business_id", $subq, "IN");
        $db->where("type", ["reward", "cash_token"], "IN");
        $xun_coins = $db->get("xun_business_coin");

        if(!$xun_coins){
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00221') /*Add Coin Success*/, "developer_msg" => "Invalid rewards / cash token");
        }

        $send_reward_bool = 0;
        
        foreach($xun_coins as $value){
            $business_coin_id = $value['id'];

            $coin_business_id = $value["business_id"];
            
            // if($coin_business_id != $business_id){
                $db->where('business_coin_id', $business_coin_id);
                $db->where('user_id', $user_id);
                $check_user_exist = $db->getOne('xun_user_coin');

                if(!$check_user_exist){
                    $insert_follower = array(
                        "user_id" => $user_id,
                        "business_coin_id" => $business_coin_id,
                        "created_at" => date("Y-m-d H:i:s")
                    );
        
                    $inserted = $db->insert('xun_user_coin', $insert_follower);
        
                    if(!$inserted){
                        return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200')/*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
                    }

                    $send_reward_bool = 1;
                }
            // }
        }

        if($send_reward_bool === 1){
            //  send reward coin
            try{
                $send_reward_params = array(
                    "user_id" => $user_id
                );
                $xunReward->new_follower_send_welcome_reward($send_reward_params);
            }catch(Exception $e){
                $error_msg = $e->getMessage();
            }
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00221') /*Add Coin Success*/, "developer_msg" => $error_msg);
    }

    public function set_user_language($params){
        $db = $this->db;

        $username = trim($params["username"]);
        $business_id  = trim($params["business_id"]);
        $language_id = trim($params['language_id']);

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        if($language_id == ''){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00403') /*Language ID is required.*/);
        }

        $xunUserService = new XunUserService($db);
        $user_result = $xunUserService->getUserByUsername($username);

        if(!$user_result){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if($business_id){
            $xunBusinessService = new XunBusinessService($db);

            $business_result = $xunBusinessService->getBusinessByBusinessID($business_id);

            if(!$business_result){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00082') /*Business not found.*/);
            }

            $user_id = $business_result['user_id'];
        }
        else{
            $user_id = $user_result['id'];
        }

        $db->where('id', $language_id);
        $languages = $db->getOne('languages');

        if(!$languages){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00378') /*Language does not exist.*/);
        }

        $update_language = array(
            "language" => $language_id,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('id', $user_id);
        $updated = $db->update('xun_user', $update_language);

        if(!$updated){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00200')/*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00222') /*Set Language Success*/);

    }

    public function get_user_info_by_address($params){
        global $xunCrypto;
        $db = $this->db;

        $username = trim($params["username"]);
        $external_address = trim($params["address"]);
        $wallet_type = trim($params["wallet_type"]);

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }
        if ($external_address == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00465') /*Address is required.*/);
        }
        if ($wallet_type == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00179') /*Wallet type is required.*/);
        }

        //  validate external address
        $validate_address_result = $xunCrypto->crypto_validate_address($external_address, $wallet_type, "external");

        if($validate_address_result["code"] == 1){
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00156') /*Invalid address*/, "errorCode" => -100);
        }

        $validate_address_data = $validate_address_result["data"];

        $address_type = $validate_address_data["addressType"];
        $validate_status = $validate_address_data["status"];

        $xun_user_service = new XunUserService($db);
        if($address_type == "internal" && $validate_status == "valid"){
            $internal_address = $validate_address_data["address"];
            if($internal_address){
                $db->where("address", $internal_address);
                $crypto_user_address = $db->getOne("xun_crypto_user_address");
                $address_user_id = $crypto_user_address["user_id"];

                $xun_user = $xun_user_service->getUserDetailsByID($address_user_id);

                $user_type = $xun_user["type"];
                $name = $xun_user["nickname"];

                if($user_type == "user"){
                    $db->where("user_id", $address_user_id);
                    $user_details = $db->getOne("xun_user_details");

                    $picture_url = $user_details ? $user_details["picture_url"] : "";
                    $address_user_mobile = $xun_user["username"];
                }else if($user_type == "business"){
                    $db->where("user_id", $address_user_id);
                    $xun_business = $db->getOne("xun_business", "id, name, profile_picture_url");
                    $picture_url = $xun_business ? $xun_business["profile_picture_url"] : '';
                }

                $return_data = [];
                $return_data["name"] = $name ? $name : '';
                $return_data["picture_url"] = $picture_url ? $picture_url : "";
                $return_data["phone_number"] = $address_user_mobile ? $address_user_mobile : "";

                return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00012') /*Success*/, "data" => $return_data);
            }
        }

        return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00278') /*Invalid address.*/);
    }

    public function get_language_list($params){
        $db = $this->db;

        $username = trim($params['username']);
        $business_id = trim($params['business_id']);

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        if($business_id){
            $xun_business_service = new XunBusinessService($db);
            $business_result = $xun_business_service->getBusinessByBusinessID($business_id, $username);
            
            if(!$business_result){
                return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00082') /*Business not found.*/);
            }
        }


        $db->where('disabled', 0);
        $languages = $db->get('languages', null, 'id, language_name, native_language_name, iso_code as language_code, iso_code2 as country_code');

        $return_data['language_list'] = $languages;
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00223') /*Language List*/, 'data' => $return_data);

    }

    private function get_wallet_background_presign_url($file_name, $content_type, $content_size)
    {
        global $xunAws, $setting;

        // $file_name = trim($params["file_name"]);
        // $content_type = trim($params["content_type"]);
        // $content_size = trim($params["content_size"]);

        $bucket = $setting->systemSetting["awsS3ImageBucket"];
        $s3_folder = 'wallet_background';
        $timestamp = time();
        $presigned_url_key = $s3_folder . '/' . $timestamp . '/' . $file_name;
        $expiration = '+20 minutes';

        $newParams = array(
            "s3_bucket" => $bucket,
            "s3_file_key" => $presigned_url_key,
            "content_type" => $content_type,
            "content_size" => $content_size,
            "expiration" => $expiration,
        );

        $result = $xunAws->generate_put_presign_url($newParams);

        return $result;
        // if(isset($result["error"])){
        //     return array("code" => 1, "status" => "error", "statusMsg" => "Error generating AWS S3 presigned URL.", "errorMsg" => $result["error"]);
        // }

        // $return_message = "AWS presigned url.";
        // return array("code" => 0, "status" => "ok", "statusMsg" => $return_message, "data" => $result);

    }

    public function get_wallet_background_url($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $file_name = trim($params["file_name"]);
        $content_type = trim($params["content_type"]);
        $content_size = trim($params["content_size"]);

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00199') /*Username is required.*/);
        }
        if ($file_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00443') /*Filename is required.*/);
        }
        if ($content_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00444') /*Content type is required.*/);
        }
        if ($content_size == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00445') /*Content size is required.*/);
        }

        $result = $this->get_wallet_background_presign_url($file_name, $content_type, $content_size);

        if (isset($result["error"])) {
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00446') /*Error generating AWS S3 presigned URL.*/, "errorMsg" => $result["error"]);
        }

        $return_message = $this->get_translation_message('B00239'); /*AWS presigned url.*/
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $return_message, "data" => $result);
    }

    private function map_wallet_type_group($business_coins_arr, $wallet_info){
        $wallet_type = $wallet_info["wallet_type"];
        $business_coin = $business_coins_arr[$wallet_type];

        if($business_coin){
            $business_id = $business_coin["business_id"];
            $business_name = $business_coin["business_name"];
            $group_id = $business_id;
            $group_name = $business_name;
        }else{
            $group_id = $wallet_type;
            $group_name = $wallet_info["name"];
        }

        $return_data = array(
            "group_id" => (string)$group_id,
            "group_name" => (string)$group_name
        );
        return $return_data;
    }

    // /xun/app/qr/request
	public function app_qr_request($params, $ip, $user_agent) {
        global $xunPaymentGateway;
		$db = $this->db;
		$post = $this->post;

        $username = trim($params["username"]);
        $raw_url = trim($params["raw_url"]);
        $business_id = trim($params["business_id"]);
        $tn_type = trim($params["tn_type"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($raw_url == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00289') /*Raw URL cannot be empty.*/);
        }

        if ($tn_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00513') /*tn type cannot be empty.*/);
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
        
        $date = date("Y-m-d H:i:s");
        $insert_qr_data = array(
            "user_id" => $user_id,
            "business_id" => $business_id,
            "tn_type" => $tn_type,
            "raw_url" => $raw_url,
            "created_at" => $date,
            "updated_at" => $date
        );

        $qr_row_id = $db->insert("xun_qr_request", $insert_qr_data);

        $db->where("user_id", $business_id);
        $db->where("name", "businessCallbackURL");
        $xun_setting = $db->getOne("xun_user_setting");

        $xunPaymentGateway->update_user_setting($business_id, $ip, $user_agent);

        if (!$xun_setting) {
            $update_qr_data = array(
                "status" => "failed",
                "message" => "Callback URL does not exist.",
                "updated_at" => date("Y-m-d H:i:s")
            );
            $db->where("id", $qr_row_id);
            $db->update("xun_qr_request", $update_qr_data);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00514') /*Request failed.*/);
        } else {
			$callback_url = $xun_setting["value"];

			if ($callback_url == '') {
                $update_qr_data = array(
                    "status" => "failed",
                    "message" => "Callback URL does not exist.",
                    "updated_at" => date("Y-m-d H:i:s")
                );
                $db->where("id", $qr_row_id);
                $db->update("xun_qr_request", $update_qr_data);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00514') /*Request failed.*/);
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
                $new_params["tn_type"] = $tn_type;
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
                        $update_qr_data = array(
                            "status" => "success",
                            "updated_at" => date("Y-m-d H:i:s")
                        );
                        $db->where("id", $qr_row_id);
                        $db->update("xun_qr_request", $update_qr_data);
                        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00294')/*Request successful*/);
                    } else {
                        $update_qr_data = array(
                            "status" => "success",
                            "updated_at" => date("Y-m-d H:i:s")
                        );
                        $db->where("id", $qr_row_id);
                        $db->update("xun_qr_request", $update_qr_data);
                        return array("code" => 1, "message" => $result_message, "message_d" => $result_message);
                    }
                } else {
	                if($result_message == "") {
                        $update_qr_data = array(
                            "status" => "failed",
                            "message" => "Request failed.",
                            "updated_at" => date("Y-m-d H:i:s")
                        );
                        $db->where("id", $qr_row_id);
                        $db->update("xun_qr_request", $update_qr_data);
                    	return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00514') /*Request failed.*/);
                    } else {
                        $update_qr_data = array(
                            "status" => "failed",
                            "message" => $result_message,
                            "updated_at" => date("Y-m-d H:i:s")
                        );
                        $db->where("id", $qr_row_id);
                        $db->update("xun_qr_request", $update_qr_data);
                        return array("code" => 0, "message" => $result_message, "message_d" => $result_message);
                    }

                }

			}
		}
    }
}
