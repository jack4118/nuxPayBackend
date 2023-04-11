<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/PHPExcel.php";
include_once $currentPath . "/../include/libphonenumber-for-php-master-v7.0/vendor/autoload.php";

include_once $currentPath . "/../include/class.xun_sales.php";

$process_id = getmypid();

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php');
$path = realpath($logPath);

$db         = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$partnerDB  = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], "thenuxPartner");
$setting    = new Setting($db);
$general    = new General($db, $setting);
$log        = new Log($logPath, $logBaseName);
$post       = new post();

$xunSales = new XunSales($db, $partnerDB, $post, $general, $setting);

$systemLanguage = "english";
// // Set current language. Call $general->getCurrentLanguage() to retrieve the current language
$general->setCurrentLanguage($systemLanguage);
// // Include the language file for mapping usage
include_once $currentPath . "/../language/lang_all.php";
// // Set the translations into general class. Call $general->getTranslations() to retrieve all the translations
$general->setTranslations($translations);

$process_name = $logBaseName;
$file_path = basename(__FILE__);
$output_path = $process_name . '.log';

$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Start $process_name\n");

try {
    while(1) {
        // get schedule successed upload. Remark: Must have upload_id
        $db->where("status", "scheduled");
        $scheduled_list = $db->get("xun_import_data USE INDEX (scheduledUpload)", null, "id, upload_id, type, creator_id");
        foreach ($scheduled_list as $scheduled_row) {
            $scheduled_id = $scheduled_row["id"];
            $scheduled_upload_id = $scheduled_row["upload_id"];
            $scheduled_type = $scheduled_row["type"];

            if(empty($scheduled_upload_id)){
                $update_data["status"] = "failed";
                $update_data["reason"] = "fileNotFound";
                $update_data["updated_at"] = date("Y-m-d H:i:s");

                $db->where("id", $scheduled_id);
                $db->update("xun_import_data", $update_data);
                unset($update_data);
                continue;
            }

            // get uploaded file
            $db->where("id", $scheduled_upload_id);
            $uploaded_file_row = $db->getOne("uploads", "data");

            if(empty($uploaded_file_row)){
                $update_data["status"] = "failed";
                $update_data["reason"] = "fileNotFound";
                $update_data["updated_at"] = date("Y-m-d H:i:s");

                $db->where("id", $scheduled_id);
                $db->update("xun_import_data", $update_data);
                unset($update_data);
                continue;
            }

            // update status to processing
            $db->where("id", $scheduled_id);
            $db->update("xun_import_data", array("status" => "processing", "updated_at" => date("Y-m-d H:i:s")));

            // open file
            $file = $uploaded_file_row["data"]; // already decode when insert
            $tmp_file_name = "process_import_" . $scheduled_id . "_" . time();
            $tmp_handle = tempnam(sys_get_temp_dir(), $tmp_file_name); // create empty file
            $handle = fopen($tmp_handle, 'r+'); // open file
            fwrite($handle, $file); // put content into file
            rewind($handle);
            $tmp_file_size = filesize($tmp_handle); // Returns the size of the file in bytes 1kb = 1000bytes (100kb = 100000bytes)

            switch ($scheduled_type) {
                case 'customerSales':
                    $import_id = $scheduled_row["id"];
                    $business_id = $scheduled_row["creator_id"];

                    // read file
                    $file_type = PHPExcel_IOFactory::identify($tmp_handle);
                    $objReader = PHPExcel_IOFactory::createReader($file_type);

                    $excel_obj = $objReader->load($tmp_handle);
                    $worksheet = $excel_obj->getSheet(0);
                    $lastRow = $worksheet->getHighestRow();
                    $lastCol = $worksheet->getHighestColumn();

                    // Loop file content
                    $recordCount = 0;
                    $processedCount = 0;
                    $failedCount = 0;

                    for ($row = 2; $row <= $lastRow; $row++) {

                        $mobile = trim($worksheet->getCell('A' . $row)->getValue());
                        $amount = trim($worksheet->getCell('B' . $row)->getValue());

                        $errorMessage = "";

                        if (empty($mobile) && empty($amount)) {
                            continue;
                        }
                        $recordCount++;

                        unset($sales_info);
                        $sales_info[] = array(
                            "mobile" => $mobile,
                            "amount" => $amount,
                        );

                        unset($params2);
                        $params2["business_id"] = $business_id;
                        $params2["sales_info"] = $sales_info;

                        unset($temp_res);
                        $temp_res = $xunSales->add_customer_sales($params2);
                        $status = $temp_res["message"];

                        if ($temp_res["code"] == 1) {
                            $reason = "";
                            $processedCount++;
                        } else {
                            $reason = $temp_res["message_d"];
                            $debug_msg = $temp_res["developer_msg"];
                            $failedCount++;
                        }

                        unset($json);
                        $json = array(
                            'mobile' => $mobile,
                            'amount' => $amount,
                            'status' => $status,
                            'reason' => $reason ?: "",
                            'developer_msg' => $debug_msg ?: "",
                        );
                        $json = json_encode($json);

                        unset($dataInsert);
                        $dataInsert = array(
                            'import_data_id' => $import_id,
                            'data' => $json,
                            'processed' => "1",
                            'status' => $status,
                            'error_message' => $reason ?: "",
                        );
                        $import_details_id = $db->insert('xun_import_data_details', $dataInsert);

                        if (empty($import_details_id)) {
                            $update_data["status"] = "failed";
                            $update_data["reason"] = "insertImportDetailsFailed";
                            break;
                        }

                    }

                    if($update_data["status"] == "") $update_data["status"] = "completed";
                    if($update_data["reason"] == "") $update_data["reason"] = "";
                    $update_data["total_records"] = $recordCount;
                    $update_data["total_processed"] = $processedCount;
                    $update_data["total_failed"] = $failedCount;
                    $update_data["updated_at"] = date("Y-m-d H:i:s");
                    $db->where('id', $import_id);
                    $db->update('xun_import_data', $update_data);
                    unset($update_data);
                    unset($json);
                    unset($worksheet);
                    unset($params2);
                    unset($temp_res);
                    unset($excel_obj);
                    unset($sales_info);
                    break;
                
                default:
                    $log->write(date('Y-m-d H:i:s') . " ID: $scheduled_id type: $scheduled_type Not Found.\n");
                    break;
            }

            // close file & remove opened file in temp dir
            fclose($handle); unlink($tmp_handle);
        }
    }
} catch (Exception $e) {
    $msg = $e->getMessage();

    $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t $process_name Dead. Reason: $msg\n");

    $message = $process_name . "\n";
    $message .= "Time : " . date("Y-m-d H:i:s");
    $message .= $msg;

    $erlang_params["tag"] = "Process Error";
    $erlang_params["message"] = $message;
    $erlang_params["mobile_list"] = ["+60124466833", "+60102208361"];
    // $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_wallet_transaction");
}

// =========================================================================
exit();
$sender_list["service_charge"] = array(
                                        "system_setting" => array("marketplaceTradingFeeWalletAddress", "fundOutReceiverWalletAddress"),
                                        "fund_out_type" => "internal",
                                        "wallet_server" => "trading_fee",
                                        "left_in_add_bal" => 5, // USD
                                    );

if(!isset($sender_list[$fund_out_sender])){
    $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name '$fund_out_sender' fund out sender not in list.\n");
    exit();
}

$db->where("name", $process_name);
$db->where("arg1", $fund_out_sender);
$process = $db->getOne("processes");

// check process status
if(!$process){
    $insertData = array(
        "name" => $process_name,
        "file_path" => $file_path,
        "output_path" => $output_path,
        "process_id" => $process_id,
        "arg1" => $fund_out_sender,
        "created_at" => date("Y-m-d H:i:s"),
        "updated_at" => date("Y-m-d H:i:s")
    );

    $process_row_id = $db->insert("processes", $insertData);
}else{

    if($process["disabled"]){
        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name Disabled.\n");
        exit();
    }

    if($process["process_id"]){
        // check running or dead
        exec("ps ".$process["process_id"], $pidOutput, $pidResult);

        if(count($pidOutput) >= 2){
            $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name previous process is still running\n");
            exit();
        }

        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name previous process halfway dead.\n");
        exit();
    }

    $updateData = [];
    $updateData["process_id"] = $process_id;
    $updateData["updated_at"] = date("Y-m-d H:i:s");

    $process_row_id = $process["id"];

    $db->where("id", $process_row_id);
    $db->update("processes", $updateData);
}

// Process start
$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name Start.\n");
try {
    // get fund out receiver account info
    // get fund out sender account info (wallet address, wallet server info)
    $sender_wallet_server_webservices = $config["tradingFeeURL_walletTransaction"];
    $fund_out_type = $sender_list[$fund_out_sender]["fund_out_type"]; // internal / external
    $fund_out_wallet_server = $sender_list[$fund_out_sender]["wallet_server"];
    $left_in_add_bal = $sender_list[$fund_out_sender]["left_in_add_bal"];

    $db->where("name", $sender_list[$fund_out_sender]["system_setting"], "IN");
    $sender_setting_res = $db->get("system_settings", null, "name, value, reference");
    foreach ($sender_setting_res as $key => $value) {
        if($key == "fundOutReceiverWalletAddress"){
            $sender_setting_row["fundOutReceiverWalletAddress"][$value["reference"]] = $value["value"];
        }else{
            $sender_setting_row[$value["name"]] = $value["value"];
        }
    }

    switch ($fund_out_sender) {
        case 'service_charge':
            $sender_wallet_address = $sender_setting_row["marketplaceTradingFeeWalletAddress"];
            $receiver_wallet_address_list = $sender_setting_row["fundOutReceiverWalletAddress"]["usd"];
            break;
        
        default:
            $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t sender account $fund_out_sender info/addresss not found.\n");
            exit();
            break;
    }

    // get all coin
    // check USD value by each coin
    $coin_list = $xunCrypto->get_wallet_info($sender_wallet_address);
    // print_r($coin_list);
    foreach ($coin_list as $key => &$value) {
        // will return how many 0 E.g 100000000,10 get 8
        $decimal = log($value["unitConversion"], 10);

        // Formula : (balance / unitConversion) x USD exchangeRate = USD value
        $usd_value = bcmul(bcdiv($value["balance"], $value["unitConversion"], $decimal), $value["exchangeRate"]["usd"], $decimal);
        $value["decimal"] = $decimal;
        $value["usd_value"] = $usd_value;

        // build fund out list
        if($usd_value > 20){
            // calculate Fund out amount Remark: keep 5 USD in address 
            // Formula : (USD / USD exchangeRate) * unitConversion = Coin Value
            // Formula : balance - Coin Value = fund out amount
            $fund_out_amount = $value["balance"] - bcmul(bcdiv($left_in_add_bal / $value["exchangeRate"]["usd"], $decimal), $value["unitConversion"], $decimal);
            $value["fund_out_amount"] = 1;//$fund_out_amount;

            $fund_out_list[$key] = $value;
        }

        $log->write(date("Y-m-d H:i:s")." ---------------------------------------------------\n");
        $log->write(date("Y-m-d H:i:s")." Name: ".$value["walletName"]." Unit: ".$value["unit"]."\n");
        $log->write(date("Y-m-d H:i:s")." Balance: ".$value["balance"]." USD_Rate: ".$value["exchangeRate"]["usd"]." UnitConversion: ".$value["unitConversion"]." Decimal: ".$decimal."\n");
        $log->write(date("Y-m-d H:i:s")." USD_Value:".$usd_value."\n");
    }

    // loop fund out coin
    // print_r($fund_out_list);
    if(!count($fund_out_list)) 
        $log->write(date("Y-m-d H:i:s")." No coin meet fund out condition.\n");
    else
        $log->write(date("Y-m-d H:i:s")." Perform fund out.\n");

    foreach ($fund_out_list as $key => $value) {
        $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." Unit: ".$value["unit"]." USD_Value: ".$value["usd_value"]."\n");

        // insert transaction 
        $transactionObj = new stdClass();
        $transactionObj->status = "pending";
        $transactionObj->transactionHash = "";
        $transactionObj->transactionToken = "";
        $transactionObj->senderAddress = $sender_wallet_address;
        $transactionObj->recipientAddress = $receiver_wallet_address_list;
        $transactionObj->userID = "";
        $transactionObj->senderUserID = "";
        $transactionObj->recipientUserID = "";
        $transactionObj->walletType = $value["walletType"];
        $transactionObj->amount = $value["fund_out_amount"];
        $transactionObj->addressType = "internal_transfer";
        $transactionObj->transactionType = "send";
        $transactionObj->escrow = 0;
        $transactionObj->referenceID = "";
        $transactionObj->escrowContractAddress = "";
        $transactionObj->createdAt = date("Y-m-d H:i:s");
        $transactionObj->updatedAt = date("Y-m-d H:i:s");
        $transactionObj->expiresAt = '';

        $transaction_id = $xunWallet->insertUserWalletTransaction($transactionObj);
        if(empty($transaction_id))
            $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." insert transaction failed.\n");
        else
            $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." transaction_id: $transaction_id\n");

        // Internal Fund out
        // call wallet server sign transaction
        // call blockchain
        $post_params = [];
        $post_params["walletTransactionID"] = $transaction_id;
        $post_params["receiverAddress"] = $receiver_wallet_address_list;
        $post_params["amount"] = $value["fund_out_amount"];
        $post_params["walletType"] = $value["walletType"];
        $fund_out_response = $xunCompanyWallet->fundOut($fund_out_wallet_server, $post_params);

        if($fund_out_response["code"] == "1"){
            $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." Success.\n");
        }else{
            $log->write(date("Y-m-d H:i:s")." ".print_r($fund_out_response, 1)."\n");
            $log->write(date("Y-m-d H:i:s")." Fund out Coin: ".$value["walletName"]." Failed.\n");
        }
    }

} catch (Exception $e) {
    $msg = $e->getMessage();

    $message = $process_name . "\n";
    $message .= "Time : " . date("Y-m-d H:i:s");
    $message .= $msg;

    $erlang_params["tag"] = "Process Error";
    $erlang_params["message"] = $message;
    $erlang_params["mobile_list"] = ["+60124466833", "+60102208361"];
    // $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_wallet_transaction");
}

$updateData = [];
$updateData["process_id"] = '';
$updateData["updated_at"] = date("Y-m-d H:i:s");

$db->where("id", $process_row_id);
$db->update("processes", $updateData);

$log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ."\t $process_name End.\n");