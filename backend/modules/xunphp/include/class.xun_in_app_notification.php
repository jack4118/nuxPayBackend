<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/

class XunInAppNotification
{

    public function __construct($db, $setting)
    {
        $this->db = $db;
        $this->setting = $setting;
    }

    public function get_message_info($notification_id)
    {
        $db = $this->db;

        $db->where("notification_id", $notification_id);
        $data = $db->get("xun_in_app_notification_message");

        return $data;
    }

    public function get_notification_tag($notification_id)
    {
        $setting = $this->setting;
        $company_name = $setting->systemSetting["companyName"];

        $tag = "Welcome to " . $company_name;

        return $tag;
    }

    public function send_message($username, $notification_id)
    {
        $message_arr = $this->get_message_info($notification_id);
        $tag = $this->get_notification_tag($notification_id);

        if (count($message_arr) == 1) {
            $message_data = $message_arr[0];
            $message = $message_data["message"];
        }

        if ($message){
            $data = $this->build_business_sending_queue_data($username, $tag, $message);
            $this->insert_to_business_sending_queue([$data]);
        }
    }

    public function build_business_sending_queue_data($username, $tag, $message)
    {
        $date = date("Y-m-d H:i:s");
        $message_type = "business";

        $insert_params = array(
            "business_id" => "1",
            "mobile_list" => [$username],
            "tag" => $tag,
            "message" => $message,
        );
        $xun_business_sending_queue_insertData = array(
            "data" => json_encode($insert_params),
            "message_type" => $message_type,
            "created_at" => $date,
            "updated_at" => $date,
        );

        return $xun_business_sending_queue_insertData;
    }

    public function insert_to_business_sending_queue($insert_data_arr)
    {
        global $db;

        $ids = $db->insertMulti('xun_business_sending_queue', $insert_data_arr);
        return $ids;
    }
}
