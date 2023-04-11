<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.xun_story.php";
include_once $currentPath . "/../include/class.xun_xmpp.php";
include_once $currentPath . "/../include/class.xun_currency.php";

include_once $currentPath . "/../include/class.abstract_xun_user.php";
include_once $currentPath . "/../include/class.xun_user_model.php";
include_once $currentPath . "/../include/class.xun_user_service.php";
include_once $currentPath . "/../include/class.xun_business_model.php";
include_once $currentPath . "/../include/class.xun_business_service.php";
include_once $currentPath . "/../include/class.xun_livechat_model.php";

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php');
$path = realpath($logPath);

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$setting = new Setting($db);
$general = new General($db, $setting);
$log = new Log($logPath, $logBaseName);
$post = new post();
$xunStory = new XunStory($db, $post, $general, $setting);
$xunXmpp = new XunXmpp($db, $post);
$xunCurrency = new XunCurrency($db);

$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Start processUpdateExpiredStory\n");

try {
    while (true) {
        $db->where('status', "active");
        $db->where('expires_at', date("Y-m-d H:i:s"), "<=");
        $xun_story = $db->get('xun_story');
        $story_id_arr = [];
        $category_id_arr = [];
        if ($xun_story) {
            foreach ($xun_story as $key => $value) {
                $story_id = $value["id"];
                $user_id = $value["user_id"];
                $category_id = $value["category_id"];

                if (!in_array($story_id, $story_id_arr)) {
                    array_push($story_id_arr, $story_id);
                }

                if (!in_array($category_id, $category_id_arr)) {
                    array_push($category_id_arr, $category_id);
                }

            }

            $db->where('id', $story_id_arr, "IN");
            $db->where('story_type', 'story');
            $story_updates = $db->map('id')->ArrayBuilder()->get('xun_story_updates');

            $db->where('id', $category_id_arr, 'IN');
            $story_category = $db->map('id')->ArrayBuilder()->get('xun_story_category');

            foreach ($xun_story as $story_key => $story_value) {
                $story_id = $story_value['id'];
                $category_id = $story_value['category_id'];
                $raised_amount = $story_value["fund_amount"];
                $verified_amount = $story_value["fund_collected"];
                $currency_id = $story_value["currency_id"];
                $user_id = $story_value["user_id"];

                $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
                $creditType = $decimal_place_setting["credit_type"];

                $raised_amount = $setting->setDecimal($raised_amount, $creditType);
                $verified_amount = $setting->setDecimal($verified_amount, $creditType);

                $uc_currency_name = $xunStory->get_fiat_currency_name($currency_id, 1);

                $updateStatus = array(
                    "status" => "expired",
                    "updated_at" => date("Y-m-d H:i:s"),
                );
                $db->where('id', $story_id);
                $updated = $db->update('xun_story', $updateStatus);

                $title = $story_updates[$story_id]['title'];
                $obj->story_title = $title;

                $category = $story_category[$category_id]['category'];

                $xunStory->insert_story_notification($story_id, 0, $user_id, "story_expired", $obj);

                $db->where('story_id', $story_id);
                $db->where('story_type', "updates");
                $total_updates = $db->getValue('xun_story_updates', 'count(id)');

                $db->where('story_id', $story_id);
                $total_comment = $db->getValue('xun_story_comment', 'count(id)');

                $db->where('story_id', $story_id);
                $total_shared = $db->getOne('xun_story_share', 'sum(count) as sum');

                $db->where('story_id', $story_id);
                $db->where('status', 'pending');
                $total_pending_amount = $db->getOne('xun_story_transaction', 'sum(value) as sum');
                $pending_amount = $total_pending_amount["sum"] ? $total_pending_amount["sum"] : 0;

                $nuxStoryUrl = $config['nuxStoryUrl'];
                $url = $nuxStoryUrl . "/fundRaisingDetails.php?no=" . $story_id;

                $message = "Title: " . $title . "\n";
                $message .= "Category: " . $category . "\n";
                $message .= "Raise Amount: " . $raised_amount . " " . $uc_currency_name . "\n";
                $message .= "Pending Amount: " . $pending_amount . " " . $uc_currency_name . "\n";
                $message .= "Verified Amount: " . $verified_amount . " " . $uc_currency_name . "\n";
                $message .= "Number of Updates: " . $total_updates . "\n";
                $message .= "Number of Shared: " . $total_shared["sum"] . "\n";
                $message .= "Number of Comment: " . $total_comment . "\n";
                $message .= "URL: " . $url . "\n";
                $message .= "Time: " . date("Y-m-d H:i:s") . "\n";

                $tag = "Story expired";

                $xunStory->send_story_notification($tag, $message);

                update_monitoring();

            }

        }
    }
} catch (Exception $e) {
    $msg = $e->getMessage();

    $message = "processUpdateExpiredStory\n";
    $message .= "Time : " . date("Y-m-d H:i:s");
    $message .= $msg;

    $erlang_params["tag"] = "Process Error";
    $erlang_params["message"] = $message;
    $erlang_params["mobile_list"] = ["+60124466833"];
    $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_story");
}

function update_monitoring()
{
    global $config;
    $env = $config["environment"];
    if ($env == "prod") {

        $targetUrl = "http://xunmonitoring.backend/server_process_record.php";
        $fields = array("SERVERNAME" => "SGPRODAPI_PHP_001",
            "SERVERID" => "i-0f35b94beb3ca6d16",
            "PUBLICIP" => "",
            "PRIVATEIP" => "10.2.0.193",
            "SERVERTYPE" => "t3.large",
            "PROCESS_NAME" => basename(__FILE__, '.php'),
            "STATUS" => "active",
            "URGENCY_LEVEL" => "Critical",
        );
        $dataString = json_encode($fields);

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($dataString))
        );
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}
