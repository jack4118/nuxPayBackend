<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.setting.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$setting = new Setting($db);

$notification_id = $argv[1];

if (!$notification_id) {
    echo "Error: notification id is empty";
    return;
}

$db->where("id", $notification_id);
$in_app_notification_data = $db->getOne("xun_in_app_notification");

if (!$in_app_notification_data) {
    echo "\n Error: Invalid notification ID";
    return;
}

switch ($notification_id) {
    case 1:
        process_no_create_wallet_message($in_app_notification_data);
        break;

    case 2:
        process_no_upline_message($in_app_notification_data);
        break;

    case 6:
        process_never_use_wallet_message($in_app_notification_data);
        break;

    default:
        break;

}

return;

function process_no_create_wallet_message($in_app_notification_data)
{
    global $db;

    $notification_id = $in_app_notification_data["id"];

    $date = date("Y-m-d H:i:s");
    $query_date = date("Y-m-d H:i:s", strtotime("-24 hours", strtotime($date)));

    $sq1 = $db->subQuery();
    $sq1->where("active", 1);
    $sq1->where("deleted", 0);
    $sq1->where("address_type", "personal");
    $sq1->getValue("xun_crypto_user_address", "user_id", null);

    $sq2 = $db->subQuery();
    $sq2->where("notification_id", $notification_id);
    $sq2->where("has_ended", 1);
    $sq2->getValue("xun_in_app_notification_recipient", "user_id", null);

    $db->where("id", $sq1, "not in");
    $db->where("id", $sq2, "not in");
    $db->where("type", "user");
    $db->where("created_at", $query_date, "<");
    $db->where("disabled", 0);
    $user_arr = $db->get("xun_user");

    send_message($in_app_notification_data, $user_arr);
}

function process_no_upline_message($in_app_notification_data)
{
    global $db;

    $notification_id = $in_app_notification_data["id"];

    $date = date("Y-m-d H:i:s");
    $query_date = date("Y-m-d H:i:s", strtotime("-24 hours", strtotime($date)));

    $sq1 = $db->subQuery();
    $sq1->getValue("xun_tree_referral", "user_id", null);

    $sq2 = $db->subQuery();
    $sq2->where("notification_id", $notification_id);
    $sq2->where("has_ended", 1);
    $sq2->getValue("xun_in_app_notification_recipient", "user_id", null);

    $db->where("id", $sq1, "not in");
    $db->where("id", $sq2, "not in");
    $db->where("type", "user");
    $db->where("created_at", $query_date, "<");
    $db->where("disabled", 0);
    $user_arr = $db->get("xun_user");

    send_message($in_app_notification_data, $user_arr);
}

function process_never_use_wallet_message($in_app_notification_data)
{
    global $db;

    $notification_id = $in_app_notification_data["id"];

    $date = date("Y-m-d H:i:s");
    $query_date = date("Y-m-d H:i:s", strtotime("-1 days", strtotime($date)));

    $db->where("notification_id", $notification_id);
    $db->where("has_ended", 1);
    $recipient_user_ids = $db->getValue("xun_in_app_notification_recipient", "user_id", null);

    $db->where("used", 1);
    $user_address_user_ids = $db->getValue("xun_crypto_user_address", "distinct(user_id)", null);

    $recipient_user_ids = $recipient_user_ids ? $recipient_user_ids : [];
    $user_address_user_ids = $user_address_user_ids ? $user_address_user_ids : [];

    $excluded_user_ids = array_unique(array_merge($recipient_user_ids, $user_address_user_ids));

    if (!empty($excluded_user_ids)) {
        $db->where("user_id", $excluded_user_ids, "NOT IN");
    }

    $db->where("active", 1);
    $db->where("deleted", 0);
    $db->where("address_type", "personal");
    $db->where("created_at", $query_date, "<");
    $excluded_crypto_user_address = $db->getValue("xun_crypto_user_address", "user_id", null);

    if (!empty($excluded_crypto_user_address)) {
        $db->where("id", $excluded_crypto_user_address, "IN");
    }

    $db->where("type", "user");
    $db->where("disabled", 0);
    $user_arr = $db->get("xun_user");

    send_message($in_app_notification_data, $user_arr);
}

function send_message($in_app_notification_data, $user_arr)
{
    global $db, $setting;
    $notification_id = $in_app_notification_data["id"];
    $date = date("Y-m-d H:i:s");
    $current_date = date("Y-m-d");
    
    if (!empty($user_arr)) {
        $current_datetime = new DateTime($current_date);
        // $tag = $in_app_notification_data["tag"];
        $company_name = $setting->systemSetting["companyName"];
        $tag = "Welcome to " . $company_name;

        $notification_message_arr = get_in_app_notification_message($notification_id);

        $recipient_arr = get_in_app_notification_recipient_list($notification_id);

        $max_notification_days = $in_app_notification_data["max_days"];

        $insert_queue_data_arr = [];
        $insert_recipient_data_arr = [];

        $user_arr_len = count($user_arr);
        for ($i = 0; $i < $user_arr_len; $i++) {
            $user_data = $user_arr[$i];
            $user_id = $user_data["id"];
            unset($message);

            if (isset($recipient_arr[$user_id])) {
                //  existing data
                //  get number of days
                $recipient_data = $recipient_arr[$user_id];

                $created_at = $recipient_data["created_at"];
                $created_date = date("Y-m-d", strtotime($created_at));

                $created_datetime = new DateTime($created_date);
                $date_diff = $current_datetime->diff($created_datetime)->format('%a');

                $no_of_days = $date_diff + 1;

                if (isset($notification_message_arr[$no_of_days])) {
                    $message = $notification_message_arr[$no_of_days]["message"];
                }

                if ($no_of_days >= $max_notification_days) {
                    $update_data = [];
                    $update_data["has_ended"] = 1;
                    $update_data["updated_at"] = $date;

                    $db->where("id", $recipient_data["id"]);
                    $db->update("xun_in_app_notification_recipient", $update_data);
                }
            } else {
                //  new record, insert to recipient table
                $no_of_days = 1;

                $insert_recipient_data = array(
                    "user_id" => $user_id,
                    "notification_id" => $notification_id,
                    "has_ended" => 0,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $insert_recipient_data_arr[] = $insert_recipient_data;
            }

            if (isset($notification_message_arr[$no_of_days])) {
                $message = $notification_message_arr[$no_of_days]["message"];
                $username = $user_data["username"];
                $insert_data = build_business_sending_queue_data($username, $tag, $message);
                $insert_queue_data_arr[] = $insert_data;
            }
        }

        if (!empty($insert_queue_data_arr)) {
            insert_to_business_sending_queue($insert_queue_data_arr);
        }

        if (!empty($insert_recipient_data_arr)) {
            insert_to_in_app_notification_recipient($insert_recipient_data_arr);
        }
    }
}

function get_in_app_notification_message($notification_id)
{
    global $db;

    $db->where("notification_id", $notification_id);
    $res = $db->map("no_of_days")->ArrayBuilder()->get("xun_in_app_notification_message", null, "id, message, no_of_days");

    return $res;
}

function get_in_app_notification_recipient_list($notification_id)
{
    global $db;

    $db->where("notification_id", $notification_id);
    $db->where("has_ended", 0);
    $res = $db->map("user_id")->ArrayBuilder()->get("xun_in_app_notification_recipient", null, "user_id, id, created_at");

    return $res;
}

function build_business_sending_queue_data($username, $tag, $message)
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

function insert_to_business_sending_queue($insert_data_arr)
{
    global $db;

    $ids = $db->insertMulti('xun_business_sending_queue', $insert_data_arr);
    return $ids;
}

function insert_to_in_app_notification_recipient($insert_data_arr)
{
    global $db;

    $ids = $db->insertMulti('xun_in_app_notification_recipient', $insert_data_arr);
    return $ids;
}
