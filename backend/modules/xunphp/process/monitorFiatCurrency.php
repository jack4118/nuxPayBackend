<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.database.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();

$process_id = getmypid();
$pricingUrl = $config["pricingUrl"];
$monitorUrl = $config["xunMonitorUrl"];
$notificationUrl = $config["notificationUrl"];
$api_key = $config["notification_api_key"];
$business_id = $config["notification_business_id"];

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);

// echo "\n starting process";
$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Start process monitor fiat rate\n");

$process_name = $logBaseName;
$file_path = basename(__FILE__);
$output_path = $process_name . '.log';

$db->where("name", $process_name);
$process = $db->getOne("processes");
$date = date("Y-m-d H:i:s");
$test = $process["id"];
// if (!$process) {
//     $insertData = array(
//         "name" => $process_name,
//         "file_path" => $file_path,
//         "output_path" => $output_path,
//         "process_id" => $process_id,
//         "created_at" => $date,
//         "updated_at" => $date,
//     );

//     $process_row_id = $db->insert("processes", $insertData);
// } else {
//     if ($process["process_id"]) {
//         $last_update = $process["updated_at"];
//         $current_timestamp = strtotime($date);
//         $last_update_timestamp = strtotime($last_update);
//         if (($current_timestamp - $last_update_timestamp) < 108000) {
//             $log->write(date('Y-m-d H:i:s') . " PID: " . $process["process_id"] . "\t Previous process is still running\n");
//             $xunNumber = array('+60192135135','+60186757884');
//             $message = "Description: MonitorFiatRate process has been running for more than 1 minute \nProcess ID: " . $process["process_id"] . " \nTime: $date\n";
//             $tag = "Pricing Processes";
//             $response = sendNewXunGnrl($notificationUrl, $api_key, $business_id, $xunNumber, $message, $tag);
//             return;
//         }
//     }
//     $updateData = [];
//     $updateData["process_id"] = $process_id;
//     $updateData["updated_at"] = $date;

//     $process_row_id = $process["id"];

//     $db->where("id", $process_row_id);
//     $db->update("processes", $updateData);
// }

$date = date("Y-m-d H:i:s");

monitorFiatCurrency($notificationUrl, $api_key, $business_id);

$updateData = [];
$updateData["process_id"] = '';
$updateData["updated_at"] = date("Y-m-d H:i:s");

$process_row_id = $process["id"];

$db->where("id", $process_row_id);
$db->update("processes", $updateData);

// echo "\n end process";
$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t End process monitor fiat rate\n\n");

$monitorArray = array(
    "SERVERNAME" => gethostname(),
    "SERVERID" => gethostname(),
    "PUBLICIP" => getHostByName(getHostName()),
    "PRIVATEIP" => "",
    "SERVERTYPE" => "-",
    "PROCESS_NAME" => basename(__FILE__, '.php'),
    "STATUS" => "active",
    "URGENCY_LEVEL" => "Critical",
);

$postResult = $post->curl_post($monitorUrl, $monitorArray, 0);

function sendNewXunGnrl($notificationUrl, $api_key, $business_id, $xunNumber, $message, $tag)
{
    $targetUrl = $notificationUrl;
    $fields = array("api_key" => $api_key,
        "business_id" => $business_id,
        "message" => $message,
        "tag" => $tag,
        "mobile_list" => $xunNumber,
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

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

function monitorFiatCurrency($notificationUrl, $api_key, $business_id)
{
    global $post, $db, $xun_numbers;

    $db->where("updated_at < date_sub(now(), interval 5 minute)");
    $db->orderBy("currency");
    $result = $db->get('xun_currency_rate', null, 'currency');

    $fiatArray = array();
    foreach ($result as $data) {
        array_push($fiatArray, $data["currency"]);
    }
    $count = count($fiatArray);

    if ($count > 0) {
        $date = date("Y-m-d H:i:s");
        // $xunNumber = array('+60192135135','+60186757884');
        $xunNumber = $xun_numbers;
        $message = "(".gethostname().") \nDescription: Fiat Currency that is not updated for more than 5 minutes \nFiat currency: " . implode(", ", $fiatArray) . "\nTotal inactive: $count \nTime: $date\n";
        $tag = "Inactive Fiat Currency List";
        $response = sendNewXunGnrl($notificationUrl, $api_key, $business_id, $xunNumber, $message, $tag);
        echo $response;
    }

}
