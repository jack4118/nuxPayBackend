<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();

$process_id = getmypid();

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);

// echo "\n starting process";
$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Start process get currency rate\n");

$process_name = $logBaseName;
$file_path = basename(__FILE__);
$output_path = $process_name . '.log';

$db->where("name", $process_name);
$process = $db->getOne("processes");

$date = date("Y-m-d H:i:s");
if(!$process){
    $insertData = array(
        "name" => $process_name,
        "file_path" => $file_path,
        "output_path" => $output_path,
        "process_id" => $process_id,
        "created_at" => $date,
        "updated_at" => $date
    );

    $process_row_id = $db->insert("processes", $insertData);
}else{
    if($process["process_id"]){
        $last_update = $process["updated_at"];
        $current_timestamp = strtotime($date);
        $last_update_timestamp = strtotime($last_update);
        if(($current_timestamp - $last_update_timestamp) < 108000){
            $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t Previous process is still running\n");
    
            return;
        }
    }
    $updateData = [];
    $updateData["process_id"] = $process_id;
    $updateData["updated_at"] = $date;

    $process_row_id = $process["id"];

    $db->where("id", $process_row_id);
    $db->update("processes", $updateData);
}

$batch_id = $db->getNewID();

$table_name = create_cryptocurrency_table();

$db->where("disabled", 0);
$providers = $db->get("xun_cryptocurrency_provider");

$currency = "usd";

foreach ($providers as $provider){
    $provider_id = $provider["id"];
    $provider_name = $provider["name"];

    switch($provider_name){
        case "CoinGecko":
            $res = curl_coinGecko($table_name, $currency, $batch_id, $provider_id);
            break;

        default:
            $log->write(date('Y-m-d H:i:s')." PID: ". $process_id ." \t Message - No case for  provider - $provider_name\n");
    }
}
get_average($table_name, $batch_id);

$updateData = [];
$updateData["process_id"] = '';
$updateData["updated_at"] = date("Y-m-d H:i:s");

$db->where("id", $process_row_id);
$db->update("processes", $updateData);

// echo "\n end process";
$log->write(date('Y-m-d H:i:s')." PID: ". $process_id ." \t End process get currency rate\n\n");

function curl_coinGecko($table_name, $currency, $batch_id, $provider_id)
{
    global $post, $db, $log, $process_id;

    $page = 1;
    $page_size = 150;
    $url = "https://api.coingecko.com/api/v3/coins/markets";
    $params = [];
    $params["vs_currency"] = $currency;
    $params["order"] = "market_cap_desc";
    $params["per_page"] = $page_size;
    $params["sparkline"] = "false";

    $fetch_data = true;

    while ($fetch_data) {
        $params["page"] = $page;
        $result = $post->curl_get($url, $params, 0);

        if(isset($result["curl_error"])){
            $log->write(date('Y-m-d H:i:s')." Error - ".$result["curl_error"]."\n");
            $fetch_data = false;
            break;
        }

        $result = json_decode($result, true);
        
        if(isset($result["error"])){
            $log->write(date('Y-m-d H:i:s')." Error - ".$result["error"]."\n");
            $fetch_data = false;
            break;
        }
        elseif(is_array($result) && !empty($result)){
            $data_arr = array();
            
            foreach ($result as $data) {
                // save to db
                
                $insertData = array(
                    "cryptocurrency_id" => $data["id"],
                    "currency" => $currency,
                    "batch_id" => $batch_id,
                    "provider_id" => $provider_id,
                    "value" => $data["current_price"] ? $data["current_price"] : "",
                    "price_change_percentage_24h" => $data["price_change_percentage_24h"] ? $data["price_change_percentage_24h"] : "",
                    "name" => $data["name"] ? $data["name"] : "",
                    "image" => $data["image"] ? $data["image"] : "",
                    "unit" => $data["symbol"] ? $data["symbol"] : "",
                    "market_cap" => $data["market_cap"] ? $data["market_cap"] : '',
                    "market_cap_rank" => $data["market_cap_rank"] ? $data["market_cap_rank"] : '',
                    "created_at" => date('Y-m-d H:i:s'),
                );
                $data_arr[] = $insertData;
            }
            $ids = $db->insertMulti($table_name, $data_arr);
            if(sizeof($result) < $page_size){
                $fetch_data = false;    
            }
            $page += 1;
        } else {
            $fetch_data = false;
            break;
        }
    }
    return $result;
}

function get_average($table_name, $batch_id){
    global $post, $db, $log, $process_id;

    $db->where("batch_id", $batch_id);
    $db->groupBy("cryptocurrency_id");

    $result = $db->get($table_name, null, 'cryptocurrency_id, market_cap, market_cap_rank, avg(`value`) as value, price_change_percentage_24h, name, image, currency, unit');

    foreach($result as $data){
        $db->where("cryptocurrency_id", $data["cryptocurrency_id"]);
        $cryptoCurrency = $db->getOne("xun_cryptocurrency_rate");

        if($cryptoCurrency){
            $updateData = [];
            $updateData["updated_at"] = date('Y-m-d H:i:s');
            $updateData["value"] = $data["value"];
            $updateData["price_change_percentage_24h"] = $data["price_change_percentage_24h"];
            $updateData["name"] = $data["name"];
            $updateData["image"] = $data["image"];
            $updateData["unit"] = $data["unit"];
            $updateData["market_cap"] = $data["market_cap"];
            $updateData["market_cap_rank"] = $data["market_cap_rank"];

            $db->where("id", $cryptoCurrency["id"]);
            $db->update("xun_cryptocurrency_rate", $updateData);
        }else{
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
            "updated_at" => date('Y-m-d H:i:s')
            );
    
            $db->insert("xun_cryptocurrency_rate", $insertData);
        }
    }
}

function create_cryptocurrency_table(){
    global $db;

    $tblDate = date("Ymd");

    if(!trim($tblDate)) {
        $tblDate = date("Ymd");
    }

    $table_name = "xun_cryptocurrency_".$db->escape($tblDate);

    $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS ".$table_name." LIKE xun_cryptocurrency");
    
    return $table_name;
}