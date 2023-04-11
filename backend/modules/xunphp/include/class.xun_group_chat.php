<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Group Chat code.
 * Date  29/06/2017.
 **/
class XunGroupChat
{
    public function __construct($db, $post, $general, $xunEmail)
    {
        $this->db = $db;
        $this->post = $post;
        $this->general = $general;
        $this->xunEmail = $xunEmail;
    }

    public function validate_access_token($group_id, $api_key)
    {

        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $db->where("old_id", $group_id);
        $db->where("api_key", $api_key);
        $token_result = $db->getOne("xun_group_chat");

        if (!$token_result) {
            return false;
        }

        return true;

    }

    public function group_chat_send_announcement($params){
        global $xunXmpp;
        $db = $this->db;

        $announcement_msg = trim($params["msg"]);
        $old_group_id = trim($params["group_id"]);

        ## call to erlang ##

        $erlang_params = [];
        $erlang_params["message"] = $announcement_msg;
        $erlang_params["group_id"] = $old_group_id;
        $erlang_return = $xunXmpp->send_group_chat_announcement($erlang_params);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
    }
}


