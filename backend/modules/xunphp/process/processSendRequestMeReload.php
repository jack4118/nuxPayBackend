<?php
$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_company_wallet.php";
include_once $currentPath . "/../include/class.xun_crypto.php";
include_once $currentPath . "/../include/class.xun_xmpp.php";
include_once $currentPath . "/../include/class.abstract_xun_user.php";
include_once $currentPath . "/../include/class.xun_user_model.php";
include_once $currentPath . "/../include/class.xun_user_service.php";
include_once $currentPath . "/../include/class.xun_business_model.php";
include_once $currentPath . "/../include/class.xun_business_service.php";
include_once $currentPath . "/../include/class.xun_livechat_model.php";
include_once $currentPath . "/../include/class.xun_wallet.php";
include_once $currentPath . "/../include/class.xun_wallet_transaction_model.php";


$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$xunCrypto = new XunCrypto($db, $post, $general);
$xunXmpp = new XunXmpp($db, $post);
$xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);

$meReloadResellerAccount = $setting->systemSetting["meReloadResellerAccount"];

$db->where("status", "submitted");
$submitted_transaction_arr = $db->get("xun_pay_transaction");

$meReloadURL = 'http://uone.webhop.biz:2017/ereloadws/service.asmx?op=SendRequest';

$status_arr = [];
$user_id_arr = [];
$ref_id_arr = [];

if (!empty($submitted_transaction_arr)) {
    $product_id_arr = array_column($submitted_transaction_arr, "product_id");

    $db->where("id", $product_id_arr, "in");
    $xun_pay_product_arr = $db->map("id")->ArrayBuilder()->get("xun_pay_product");
}

for ($i = 0; $i < count($submitted_transaction_arr); $i++) {
    $data = $submitted_transaction_arr[$i];
    $reference_id = $data["id"];
    $product_id = $data["product_id"];
    $product_data = $xun_pay_product_arr[$product_id];

    $provider_id = $product_data["provider_id"];
    if ($provider_id == 1) {
        meReloadSendRequest($data);
    }
}

if (!empty($status_arr)) {
    $insert_data_arr = [];
    $recipient_list = $xun_numbers;

    $db->where("id", $user_id_arr, "in");
    $xun_user_arr = $db->map("id")->ArrayBuilder()->get("xun_user", null, "id, username, nickname, type");

    for ($i = 0; $i < count($status_arr); $i++) {
        $data = $status_arr[$i];
        $pay_transaction = $data["pay_transaction"];
        $result_data = $data["result"];
        $user_id = $pay_transaction["user_id"];
        $ref_id = $pay_transaction["id"];
        $product_id = $pay_transaction["product_id"];
        $product_amount = $pay_transaction["amount_currency"];
        $product_currency = $pay_transaction["currency"];

        $user_data = $xun_user_arr[$user_id];
        $username = $user_data["username"];
        $nickname = $user_data["nickname"];

        $product_data = $xun_pay_product_arr[$product_id];
        $product_name = $product_data["name"];

        $balance = $result_data["balance"];

        if ($result_data["status"] == "failed") {
            $status = "Failed";
            $message = $result_data["message"];
        } else {
            $status = "Success";
            $message = '';
        }

        $notification_message = "Username: " . $username;
        $notification_message .= "\nNickname: " . $nickname;
        $notification_message .= "\nReference ID: " . $ref_id;
        $notification_message .= "\nAmount: " . $product_amount . ' ' . $product_currency;
        $notification_message .= "\nProduct: " . $product_name;
        $notification_message .= "\nStatus: " . $status;
        $notification_message .= "\nMessage: " . $message;
        $notification_message .= "\nProvider: MeReload";
        $notification_message .= "\nBalance: " . $balance;
        $notification_message .= "\nTime: " . date("Y-m-d H:i:s");

        $json_params = array(
            "business_id" => "1",
            "tag" => "Pay TopUp",
            "message" => $notification_message,
            "mobile_list" => $recipient_list,
        );

        $insert_data = array(
            "data" => json_encode($json_params),
            "message_type" => "business",
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $insert_data_arr[] = $insert_data;

    }
    $ids = $db->insertMulti('xun_business_sending_queue', $insert_data_arr);
}

function meReloadSendRequest($data)
{
    global $meReloadResellerAccount, $meReloadURL, $user_id_arr, $ref_id_arr, $status_arr;
    global $db, $log, $post, $xunCompanyWallet;
    $id = $data["id"];
    $reference_id = $data["reference_id"];

    $created_at = $data["created_at"];
    $created_date = date("Ymd", strtotime($created_at));

    $user_id = $data["user_id"];
    $user_id_arr[] = $user_id;
    $ref_id_arr[] = $reference_id;

    ############################# SOAP v1.2################################
    $xml_post_string = '<?xml version="1.0" encoding="utf-8"?>';
    $xml_post_string .= '    <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">';
    $xml_post_string .= '    <soap12:Body>';
    $xml_post_string .= '        <SendRequest xmlns="http://tempuri.org/">';
    $xml_post_string .= '            <ResellerAccount>' . $meReloadResellerAccount . '</ResellerAccount>';
    $xml_post_string .= '            <RefNum>' . $reference_id . '</RefNum>';
    $xml_post_string .= '        </SendRequest>';
    $xml_post_string .= '    </soap12:Body>';
    $xml_post_string .= '    </soap12:Envelope>';

    $headers = array(
        "POST  /ereloadws/service.asmx HTTP/1.1",
        "Host: uone.webhop.biz",
        "Content-Type: application/soap+xml; charset=utf-8",
        "Content-Length: " . strlen($xml_post_string),
        "SOAPAction: http://tempuri.org/SendRequest",
    );
    #########################################################################

    // ##### POST METHOD #####
    $curl = curl_init($meReloadURL);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $xml_post_string);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_PORT, "2017");

    $response = curl_exec($curl);

    /* Curl Error */
    $curlErrorNo = curl_errno($curl);
    $curlErrorDesc = curl_error($curl);
    if (curl_errno($curl)) {
        $log->write(date('Y-m-d H:i:s') . " ID: " . $reference_id . ", curlErrorNo: " . $curlErrorNo . ", curlErrorDesc: " . $curlErrorDesc . "\n");
    }
    curl_close($curl);

    $response1 = str_replace("<soap:Body>", "", $response);
    $response2 = str_replace("</soap:Body>", "", $response1);

    $parser = simplexml_load_string($response2);
    $parser1 = explode("<|>", $parser->SendRequestResponse->SendRequestResult);
    
    if (count($parser1) == 1 && $parser1[0] == 1) {
        return;
    } else {
        if ($created_date == $parser1[0]) {
            if ($parser1[3] == "F") {
                //  failed
                $update_data = [];
                $update_data["status"] = "failed";
                $update_data["message"] = $parser1[6];
                $update_data["updated_at"] = date("Y-m-d H:i:s");

                $db->where("id", $id);
                $db->update("xun_pay_transaction", $update_data);

                $result_data = array(
                    "status" => "failed",
                    "message" => $parser1[6],
                    "balance" => $parser1[5],
                );

                $transaction_result = array("pay_transaction" => $data, "result" => $result_data);
                $status_arr[] = $transaction_result;
                $user_id_arr[] = $data["user_id"];
                $product_id_arr[] = $data["product_id"];

                // refund
                $xunCompanyWallet->payTransactionRefund($data);

            } else if ($parser1[3] == "S") {
                // success

                $update_data = [];
                $update_data["status"] = "success";
                $update_data["updated_at"] = date("Y-m-d H:i:s");

                $db->where("id", $id);
                $db->update("xun_pay_transaction", $update_data);

                $result_data = array(
                    "status" => "success",
                    "message" => $parser1[6],
                    "balance" => $parser1[5],
                );

                $transaction_result = array("pay_transaction" => $data, "result" => $result_data);
                $status_arr[] = $transaction_result;
                $user_id_arr[] = $data["user_id"];
            }
        }
    }
}
