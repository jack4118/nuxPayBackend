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
$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Start process get pricing rate\n");

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
//             $message = "Description: GetPricingRate process has been running for more than 1 minute \nProcess ID: ".$process["process_id"]." \nTime: $date\n";
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


$url = $pricingUrl;
$requestParams = [];
$page = 1;
$page_size = 250;
$params = [];

$fetch_data = true;

while ($fetch_data) {


    $params["page_size"] = $page_size;
    $params["order"] = "ASC";
    $command = "partnerGetPricing";
    $params["page"] = $page;
    $params["partner_name"] = "nuxpay2";
    $params["access_token"] = "brv9xV9roK9E43lQD3llFsnbMSFnIuQA";
    $requestParams = array("command" => $command, "params" => $params);

    $result = $post->curl_post($url, $requestParams, 0, 1, [], 0);
    $numRecord = $result["data"]["numRecord"];

    $rate = $db->get('xun_cryptocurrency_rate');
    
    $rate = array_column($rate, null, 'cryptocurrency_id');

    $result = array_column($result["data"]["pricingData"], null, 'cryptocurrency_id');

    $diff = array_diff_assoc($result, $rate);

    if (empty($rate)) {
        foreach ($result as $value) {
            if($value["price_change_percentage_24h"] == null){
                $value["price_change_percentage_24h"] = "";
            }
            if($value["market_cap"] == null){
                $value["market_cap"] = "";
            }
            if($value["market_cap_rank"] ==  null){
                $value["market_cap_rank"] = "";
            }
            $insertData = array(
                "cryptocurrency_id" => $value["cryptocurrency_id"],
                "value" => $value["value"],
                "price_change_percentage_24h" => $value["price_change_percentage_24h"],
                "name" => $value["name"],
                "image" => $value["image"],
                "unit" => $value["unit"],
                "currency" => $value["currency"],
                "market_cap" => $value["market_cap"],
                "market_cap_rank" => $value["market_cap_rank"],
                "created_at" => date('Y-m-d H:i:s'),
                "updated_at" => date('Y-m-d H:i:s'),
                "live_price" => 1,
                "is_gift_code_coin" => 0,
            );
            $appendData[] = $insertData;
            $db->insert("xun_cryptocurrency_rate", $insertData);
        }
    } else {
        
        if ($diff) {
            
            foreach ($diff as $data) {
                if($data["price_change_percentage_24h"] == null){
                    $data["price_change_percentage_24h"] ="";
                }
                if($data["market_cap"] == null){
                    $data["market_cap"] = "";
                }
                if($data["market_cap_rank"] ==  null){
                    $data["market_cap_rank"] = "";
                }
                $insertData = array(
                    "cryptocurrency_id" => $data["cryptocurrency_id"],
                    "value" => $data["value"],
                    "price_change_percentage_24h" => $data["price_change_percentage_24h"],
                    "name" => $data["name"],
                    "image" => $data["image"],
                    "unit" => $data["unit"],
                    "currency" => $data["currency"],
                    "market_cap" => $data["market_cap"],
                    "market_cap_rank" => $data["market_cap_rank"],
                    "created_at" => date('Y-m-d H:i:s'),
                    "updated_at" => date('Y-m-d H:i:s'),
                    "live_price" => 1,
                    "is_gift_code_coin" => 0,
                );
                $appendData[] = $insertData;
                $db->insert("xun_cryptocurrency_rate", $insertData);
                print_r($db);

            }
        } else {
            //echo "No Data\n";
        }
    }

    if (isset($result["curl_error"])) {
        $log->write(date('Y-m-d H:i:s') . " Error - " . $result["curl_error"] . "\n");
        $fetch_data = false;
        break;
    }

    if (isset($result["error"])) {
        $log->write(date('Y-m-d H:i:s') . " Error - " . $result["error"] . "\n");
        $fetch_data = false;
        break;
    } elseif (is_array($result) && !empty($result)) {
        
        $appendData = array();
        
        foreach ($result as $value) {
            if($value["price_change_percentage_24h"] == null){
                $value["price_change_percentage_24h"] = "";
            }
            if($value["market_cap"] == null){
                $value["market_cap"] = "";
            }
            if($value["market_cap_rank"] ==  null){
                $value["market_cap_rank"] = "";
            }
            $updateData = [];
            $updateData["updated_at"] = date('Y-m-d H:i:s');
            $updateData["value"] = $value["value"];
            $updateData["price_change_percentage_24h"] = $value["price_change_percentage_24h"];
            $updateData["name"] = $value["name"];
            $updateData["image"] = $value["image"];
            $updateData["unit"] = $value["unit"];
            $updateData["market_cap"] = $value["market_cap"];
            $updateData["market_cap_rank"] = $value["market_cap_rank"];

            $db->where('cryptocurrency_id', $value["cryptocurrency_id"]);
            $db->update("xun_cryptocurrency_rate", $updateData);
            
            //print_r($insertData);
            
        }
        

        if ($numRecord < $page_size) {
            $fetch_data = false;
            break;
        }

    } else {
        $fetch_data = false;
        break;
    }
    $page += 1;
    

}


$updateData = [];
$updateData["process_id"] = '';
$updateData["updated_at"] = date("Y-m-d H:i:s");

$process_row_id = $process["id"];

$db->where("id", $process_row_id);
$db->update("processes", $updateData);

// echo "\n end process";
$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t End process get pricing rate\n\n");

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

$postResult = $post->curl_post($monitorUrl, $monitorArray,0);

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
