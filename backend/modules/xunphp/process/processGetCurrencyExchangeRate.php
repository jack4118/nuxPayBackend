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
$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Start process get currency exchange rate\n");

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
        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t Previous process is still running\n");

        return;
    }
    $updateData = [];
    $updateData["process_id"] = $process_id;
    $updateData["updated_at"] = $date;

    $process_row_id = $process["id"];

    $db->where("id", $process_row_id);
    $db->update("processes", $updateData);
}
$batch_id = $db->getNewID();

$table_name = create_daily_table();

$db->where("disabled", 0);
$providers = $db->get("xun_currency_provider");

foreach ($providers as $provider) {
    $provider_id = $provider["id"];
    $provider_name = $provider["name"];

    switch ($provider_name) {
        case "exchangeratesapi":
            $res = curl_exchangeratesapi($table_name, $batch_id, $provider_id);
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

$log->write(date('Y-m-d H:i:s')." PID: ". $process_id ." \t End process get currency exchange rate\n\n");


function curl_exchangeratesapi($table_name, $batch_id, $provider_id)
{
    global $post, $db, $log, $process_id;

    $url = "https://api.exchangeratesapi.io/latest";
    $params = [];
    $params["base"] = "USD";

    $result = $post->curl_get($url, $params, 0);

    if (isset($result["curl_error"])) {
        $log->write(date('Y-m-d H:i:s') ." PID: ". $process_id ." \t Error - " . $result["curl_error"] . "\n");
        return $result;
    }

    $result = json_decode($result, true);

    if (isset($result["error"])) {
        $log->write(date('Y-m-d H:i:s') ." PID: ". $process_id ." \t  Error - " . $result["error"] . "\n");
        $fetch_data = false;
        return $result;
    } 
    elseif (is_array($result) && !empty($result)) {
        if(isset($result["rates"])){
            $rates = $result["rates"];
            $date = date('Y-m-d H:i:s');
            foreach ($rates as $key => $value) {
                // save to db
                $currency = strtolower($key);

                $insertData = array(
                    "currency" => $currency,
                    "exchange_rate" => $value,
                    "batch_id" => $batch_id,
                    "provider_id" => $provider_id,
                    "created_at" => date('Y-m-d H:i:s')
                );
                $row = $db->insert($table_name, $insertData);     
            }
        }
    } 
    return $result;
}

function get_average($table_name, $batch_id){
    global $db, $log, $process_id;

    $db->where("batch_id", $batch_id);
    $db->groupBy("currency");

    $result = $db->get($table_name, null, 'currency, avg(`exchange_rate`) as value');

    foreach($result as $data){
        $db->where("currency", $data["currency"]);
        $currency = $db->getOne("xun_currency_rate");

        if($currency){
            $updateData = [];
            $updateData["updated_at"] = date('Y-m-d H:i:s');
            $updateData["exchange_rate"] = $data["value"];

            $db->where("id", $currency["id"]);
            $db->update("xun_currency_rate", $updateData);
        }else{
            $insertData = array(
            "currency" => $data["currency"],
            "exchange_rate" => $data["value"],
            "created_at" => date('Y-m-d H:i:s'),
            "updated_at" => date('Y-m-d H:i:s')
            );
    
            $db->insert("xun_currency_rate", $insertData);
        }
    }
}

function create_daily_table(){
    global $db;

    $tblDate = date("Ymd");

    if(!trim($tblDate)) {
        $tblDate = date("Ymd");
    }

    $table_name = "xun_currency_".$db->escape($tblDate);

    $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS ".$table_name." LIKE xun_currency");
    
    return $table_name;
}