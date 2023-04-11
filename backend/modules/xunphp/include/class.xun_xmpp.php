<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file contains the xmpp functions.
 * Date  29/06/2017.
 **/
class XunXmpp
{

    public function __construct($db, $post)
    {
        $this->db = $db;
        $this->post = $post;
    }

    public function send_xmpp_notification($params, $grouping = null)
    {
        $post = $this->post;
        global $config, $xun_numbers, $message, $xun_recipient_telegram;
        if ($grouping == null) {
            $url_string = "notification";
        } else {
            $url_string = $config["broadcast_url_string"];
        }
        //Grouping API Keys
        switch ($grouping) {
            case "thenux_escrow":
                $params["api_key"] = $config["thenux_escrow_API"];
                $params["business_id"] = $config["thenux_escrow_bID"];
                $params["mobile_list"] = $xun_numbers;
                $erlangReturn = $post->curl_post($url_string, $params, 0);

                $recipient =  $xun_recipient_telegram["xmpp_thenux_escrow"];
                $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");
                break;

            case "thenux_referral_and_master_dealer":
                $params["api_key"] = $config["thenux_referral_and_master_dealer_API"];
                $params["business_id"] = $config["thenux_referral_and_master_dealer_bID"];
                $params["mobile_list"] = $xun_numbers;
                $erlangReturn = $post->curl_post($url_string, $params, 0);


                $recipient =  $xun_recipient_telegram["xmpp_thenux_referral_and_master_dealer"];
                $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");
                break;

            case "thenux_wallet_transaction":
                $params["api_key"] = $config["thenux_wallet_transaction_API"];
                $params["business_id"] = $config["thenux_wallet_transaction_bID"];
                $params["mobile_list"] = $xun_numbers;
                $erlangReturn = $post->curl_post($url_string, $params, 0);


                $recipient =  $xun_recipient_telegram["xmpp_thenux_wallet_transaction"];
                $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");
                break;

            case "thenux_xchange":
                $params["api_key"] = $config["thenux_xchange_API"];
                $params["business_id"] = $config["thenux_xchange_bID"];
                $params["mobile_list"] = $xun_numbers;
                $erlangReturn = $post->curl_post($url_string, $params, 0);


                $recipient =  $xun_recipient_telegram["xmpp_thenux_xchange"];
                $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");
                break;

            case "thenux_story":
                $params["api_key"] = $config["thenux_story_API"];
                $params["business_id"] = $config["thenux_story_bID"];
                $params["mobile_list"] = $xun_numbers;
                $erlangReturn = $post->curl_post($url_string, $params, 0);


                $recipient =  $xun_recipient_telegram["xmpp_thenux_story"];
                $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");
                break;

            case "thenux_pay":
                $params["api_key"] = $config["thenux_pay_API"];
                $params["business_id"] = $config["thenux_pay_bID"];
                $params["mobile_list"] = $params["mobile_list"] ? $params["mobile_list"] : $xun_numbers;
                $erlangReturn = $post->curl_post($url_string, $params, 0);


                $recipient =  $xun_recipient_telegram["xmpp_thenux_pay"];
                $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");
                break;

            case "thenux_pay_marketing":
                $params["api_key"] = $config["thenux_pay_marketing_API"];
                $params["business_id"] = $config["thenux_pay_marketing_bID"];
                $params["mobile_list"] = $params["mobile_list"] ? $params["mobile_list"] : $xun_numbers;
                $erlangReturn = $post->curl_post($url_string, $params, 0);


                $recipient =  $xun_recipient_telegram["xmpp_thenux_pay_marketing"];
                $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");
                break;

            case "thenux_marketing":
                $params["api_key"] = $config["thenux_marketing_API"];
                $params["business_id"] = $config["thenux_marketing_bID"];
                $params["mobile_list"] = $xun_numbers;
                $erlangReturn = $post->curl_post($url_string, $params, 0);


                $recipient =  $xun_recipient_telegram["xmpp_thenux_marketing"];
                $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");
                break;

            default:
                $erlangReturn = $post->curl_post($url_string, $params);


                $recipient =  $xun_recipient_telegram["xmpp_Other_issue"];
                $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");
                break;
        }

        return $erlangReturn;
    }

    public function send_xmpp_business_tag_event($business_id, $tag, $new_employee_list, $removed_employee_list, $subscriber_jid_list)
    {
        $post = $this->post;

        if ($subscriber_jid_list == null || empty($subscriber_jid_list)) {
            return array("code" => 1);
        }

        if ($new_employee_list || $removed_employee_list) {
            // call erlang
            $newParams["business_id"] = (string) $business_id;
            $newParams["tag"] = $tag;
            $newParams["subscribers_jid"] = $subscriber_jid_list;
            $newParams["new_employee_list"] = $new_employee_list;
            $newParams["removed_employee_list"] = $removed_employee_list;

            $url_string = "business/tag";
            $erlangReturn = $post->curl_post($url_string, $newParams);
            return $erlangReturn;
        }
    }

    public function send_xmpp_remove_employee_event($params)
    {
        $post = $this->post;

        $employee_list = $params["employee_list"];

        if (empty($employee_list)) {
            return array("code" => 1);
        }

        $url_string = "business/employee/delete";
        $erlangReturn = $post->curl_post($url_string, $params);
        return $erlangReturn;
    }

    public function send_business_update_profile_message($business_id, $event_type)
    {
        $db = $this->db;
        $post = $this->post;

        // mobile list = all followers, all business employees
        $db->where("business_id", $business_id);
        $follower_list = $db->get("xun_business_follow", null, ["username"]);

        $db->where("business_id", $business_id);
        $db->where("status", 1);
        $employee_list = $db->get("xun_employee", null, ["mobile"]);

        $follower_mobile_list = [];
        $employee_mobile_list = [];

        if (!empty($follower_list)) {
            function get_follower_mobile($rec)
            {
                return ($rec["username"]);
            }
            $follower_mobile_list = array_map("get_follower_mobile", $follower_list);
        }

        if (!empty($employee_list)) {
            function get_employee_mobile($rec)
            {
                return ($rec["mobile"]);
            }
            $employee_mobile_list = array_map("get_employee_mobile", $employee_list);
        }

        $final_mobile_list = array_unique(array_merge($follower_mobile_list, $employee_mobile_list));

        $newParams["business_id"] = $business_id;
        $newParams["event_type"] = $event_type;
        $newParams["mobile_list"] = array_values($final_mobile_list);

        $url_string = "business/profile/update";
        $erlangReturn = $post->curl_post($url_string, $newParams);
        return $erlangReturn;
    }

    public function leave_group_chat($username, $user_group_arr)
    {
        $post = $this->post;

        if (!$user_group_arr || empty($user_group_arr)) {
            return array("code" => 1);
        }

        $newParams["username"] = $username;
        $newParams["group_list"] = $user_group_arr;

        $url_string = "user/group_chat/leave";
        $erlangReturn = $post->curl_post($url_string, $newParams);
        return $erlangReturn;
    }

    public function send_xmpp_marketplace_event($params)
    {
        /**
         * params:
         * -    room_id
         * -    room_host
         * -    recipients
         * -    type
         * -    data
         */

        $post = $this->post;

        $url_string = "marketplace/event";
        $erlangReturn = $post->curl_post($url_string, $params);
        return $erlangReturn;
    }

    public function send_xmpp_crypto_event($params)
    {
        /**
         * params:
         * -    room_id
         * -    room_host
         * -    recipients
         * -    type
         * -    data
         */

        $post = $this->post;

        $url_string = "crypto/event";
        $erlangReturn = $post->curl_post($url_string, $params);
        return $erlangReturn;
    }

    public function send_group_chat_announcement($params)
    {
        /**
         * params:
         * -    group_id
         * -    message
         * -    recipients
         */

        $post = $this->post;

        $url_string = "group_chat/announcement";
        $erlangReturn = $post->curl_post($url_string, $params);
        return $erlangReturn;
    }

    public function send_business_request_money_message($params)
    {

        $post = $this->post;

        $url_string = "business/request/money";
        $erlangReturn = $post->curl_post($url_string, $params);
        return $erlangReturn;
    }

    public function get_xmpp_jid($jid)
    {

        $pos = stripos($jid, '@');

        if ($pos === false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Malformed JID");
        } else {
            $jid_arr = explode("@", $jid);
            $jid_user = $jid_arr[0];
            $jid_host = $jid_arr[1];
        }

        return array("code" => 1, "jid_user" => $jid_user, "jid_host" => $jid_host);
    }

    public function get_user_jid($user)
    {
        global $config;
        $erlang_server = $config["erlang_server"];
        $jid = $user . '@' . $erlang_server;
        return $jid;
    }

    public function get_decoded_jid($user, $server)
    {
        $jid = $user . '@' . $server;
        return $jid;
    }

    public function get_livechat_host()
    {
        global $config;

        $erlang_server = $config["erlang_server"];
        $prefix = "livechat";
        $host = $prefix . "." . $erlang_server;
        return $host;
    }

    public function get_livechat_jid($user)
    {
        $live_chat_host = $this->get_livechat_host();
        $jid = $user . '@' . $live_chat_host;
        return $jid;
    }

    public function get_marketplace_host()
    {
        global $config;
        $marketplace_host = "marketplace." . $config["erlang_server"];

        return $marketplace_host;
    }

    public function create_xmpp_user($username, $server_host, $password)
    {
        $db = $this->db;
        $insert_data = array(
            "username" => $username,
            "server_host" => $server_host,
            "password" => $password,
        );

        $row_id = $db->insert("xun_passwd", $insert_data);
        return $row_id;
    }
}
