<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
// include_once $currentPath . "/../include/class.xun_currency.php";
include_once $currentPath . "/../include/class.xun_xmpp.php";

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

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$log = new Log($logPath, $logBaseName);
$xunXmpp = new XunXmpp($db, $post);

$processWalletServerSendingQueue = $setting->systemSetting["processWalletServerSendingQueue"];
$log->write(date('Y-m-d H:i:s') . " \t Starting process\n");

try {
    while ($processWalletServerSendingQueue == 1) {
        $db->where("processed", 0);
        $wallet_sending_queue = $db->get("wallet_server_sending_queue");

        if (!empty($wallet_sending_queue)) {
            $sender_crypto_user_address_ids = array_column($wallet_sending_queue, "sender_crypto_user_address_id");
            $receiver_crypto_user_address_ids = array_column($wallet_sending_queue, "receiver_crypto_user_address_id");

            $merged_crypto_user_address_ids = array_merge($sender_crypto_user_address_ids, $receiver_crypto_user_address_ids);

            $db->where("id", $merged_crypto_user_address_ids, 'IN');
            $xun_crypto_user_address_arr = $db->map("id")->ArrayBuilder()->get("xun_crypto_user_address");

            $date = date("Y-m-d H:i:s");
            foreach ($wallet_sending_queue as $queue_data) {
                $sender_crypto_user_address_id = $queue_data["sender_crypto_user_address_id"];
                $receiver_crypto_user_address_id = $queue_data["receiver_crypto_user_address_id"];

                $pg_crypto_address_id = $queue_data["pg_crypto_address_id"];
                $receiver_address = $queue_data["receiver_address"];
                
                $sender_crypto_user_address_data = $xun_crypto_user_address_arr[$sender_crypto_user_address_id];
                
                if($receiver_crypto_user_address_id){
                    $receiver_crypto_user_address_data = $xun_crypto_user_address_arr[$receiver_crypto_user_address_id];
                    $receiver_address = $receiver_crypto_user_address_data["address"];
                    $receiver_user_id = $receiver_crypto_user_address_data["user_id"];
                }

                if($pg_crypto_address_id){
                    $db->where("a.id", $pg_crypto_address_id);
                    $db->where("a.type", "out");
                    $db->join("xun_crypto_fund_out_destination_address b","a.id=b.address_id","LEFT");
                    $pg_address_data = $db->getOne("xun_crypto_address a", "a.crypto_address, b.destination_address");
                }

                $sender_user_id = $sender_crypto_user_address_data["user_id"];
                $sender_address_type = $sender_crypto_user_address_data["address_type"];

                $sender_address = $sender_crypto_user_address_data["address"];

                $amount = $queue_data["amount"];
                $satoshi_amount = $queue_data["amount_satoshi"];
                $wallet_type = $queue_data["wallet_type"];
                $wallet_transaction_id = $queue_data["wallet_transaction_id"];

                $transaction_token = $queue_data["transaction_token"];

                if (!$sender_address || !$receiver_address) {
                    continue;
                }

                $new_params = array(
                    "sender_user_id" => $sender_user_id,
                    "sender_address" => $sender_address,
                    "receiver_address" => $receiver_address,
                    "receiver_user_id" => $receiver_user_id,
                    "destination_address" => $pg_address_data ? $pg_address_data["destination_address"] : "",
                    "amount" => $amount,
                    "satoshi_amount" => $satoshi_amount,
                    "transaction_token" => $transaction_token,
                    "wallet_type" => $wallet_type,
                    "sender_address_type" => $sender_address_type,
                    "wallet_transaction_id" => $wallet_transaction_id,
                    "pg_address" => $pg_address_data ? $pg_address_data["crypto_address"] : ""
                );

                $post_status = fund_out($new_params);
                $update_wallet_queue = [];
                $update_wallet_queue['processed'] = 1;
                $update_wallet_queue["status"] = $post_status;
                $update_wallet_queue["updated_at"] = $date;

                $db->where("id", $queue_data["id"]);
                $db->update("wallet_server_sending_queue", $update_wallet_queue);
            }
        }

        update_monitoring();

        $db->where("name", "processWalletServerSendingQueue");
        $processWalletServerSendingQueue = $db->getValue("system_settings", "value");
    }
} catch (Exception $e) {
    $msg = $e->getMessage();

    $message = $logBaseName . "\n";
    $message .= "Time : " . date("Y-m-d H:i:s");
    $message .= $msg;

    $erlang_params["tag"] = "Process Error";
    $erlang_params["message"] = $message;
    $erlang_params["mobile_list"] = ["+60124466833","+60122590231"];
    $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_wallet_transaction");
}

function fund_out($params)
{
    global $config, $post, $db, $xunXmpp;

    $sender_user_id = $params["sender_user_id"];
    $sender_address = $params["sender_address"];
    $sender_address_type = $params["sender_address_type"];
    $receiver_address = $params["receiver_address"];
    $receiver_user_id = $params["receiver_user_id"];
    $amount = $params["amount"];
    $satoshi_amount = $params["satoshi_amount"];
    $wallet_type = $params["wallet_type"];
    $wallet_transaction_id = $params["wallet_transaction_id"];
    $transaction_token = $params["transaction_token"];
    $pg_address = $params["pg_address"];
    $destination_address = $params["destination_address"];

    $tx_obj = new stdClass();
    $tx_obj->userID = $sender_user_id;
    $tx_obj->address = $sender_address;

    $xun_user_service = new XunUserService($db);

    $transaction_token = $transaction_token ?: $xun_user_service->insertCryptoTransactionToken($tx_obj);
    
    $post_params = array(
        "command" => "fundOut",
        "params" => array(
            "senderAddress" => $sender_address,
            "receiverAddress" => $receiver_address,
            "amount" => $amount,
            "satoshiAmount" => $satoshi_amount,
            "walletType" => $wallet_type,
            "transactionToken" => $transaction_token,
            "addressType" => $sender_address_type,
            "id" => $wallet_transaction_id,
        ),
    );
    if($sender_address_type == "prepaid_payment_gateway" ){
        $pg_params = array(
            "address" => $pg_address,
            "destinationAddress" => $destination_address
        );
        $post_params["params"]["paymentGatewayParams"] = $pg_params;
    }

    $db->where("id", [$receiver_user_id, $sender_user_id], "IN");
    $xun_user_data = $db->map("id")->ArrayBuilder()->get("xun_user", null, "id, username, nickname, type");

    $url_string = $config["giftCodeUrl"];
    $post_return = $post->curl_post($url_string, $post_params, 0, 1);

    $sender_user = $xun_user_data[$sender_user_id];
    $sender_username = $sender_user["username"] ?: $sender_user["id"];
    $receiver_user = $xun_user_data[$receiver_user_id];
    $receiver_username = $receiver_user["username"] ?: $receiver_user["id"];

    if ($post_return["code"] == 0 || !isset($post_return["code"])) {
        // send notification
        // $error_message = $post_return_obj->result;

        $error_message = $post_return["message_d"];

        $tag = "Wallet Server Transfer Error";
        $content = "Error: " . $error_message;
        $content .= "\n\nSender Type: " . get_address_type($sender_address_type);
        if($xun_user_data[$sender_user_id]){
            $content .= "\n\nSender Username/ID: " . $sender_username;
            $content .= "\nSender Nickname: " . $sender_user["nickname"];
            $content .= "\nSender Type: " . $sender_user["type"];
        }
        if($xun_user_data[$receiver_user_id]){
            $content .= "\n\nReceiver Username/ID: " . $receiver_username;
            $content .= "\nReceiver Nickname: " . $receiver_user["nickname"];
            $content .= "\nReceiver Type: " . $receiver_user["type"];
        }
        if($destination_address){
            $content .= "\n\nDestination Address: " . $destination_address;
        }
        $content .= "\nAmount: " . $amount;
        $content .= "\nWallet Type: " . $wallet_type;
        $content .= "\nReceiver Address: " . $receiver_address;
        $content .= "\nID: " . $wallet_transaction_id;

        $erlang_params = [];
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = array();
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_wallet_transaction");
        $post_status = "failed";
    } else {
        $content = '';
        $content .= "\n\nFrom : " . $sender_address;
        $content .= "\n\nTo : " . $receiver_address;
        $content .= "\n\nSender Address  Type : " . get_address_type($sender_address_type);
        if($xun_user_data[$sender_user_id]){
            $content .= "\n\nSender Username/ID: " . $sender_username;
            $content .= "\nSender Nickname: " . $sender_user["nickname"];
            $content .= "\nSender Type: " . $sender_user["type"];
        }
        if($xun_user_data[$receiver_user_id]){
            $content .= "\n\nReceiver Username/ID: " . $receiver_username;
            $content .= "\nReceiver Nickname: " . $receiver_user["nickname"];
            $content .= "\nReceiver Type: " . $receiver_user["type"];
        }
        if($destination_address){
            $content .= "\n\nDestination Address: " . $destination_address;
        }
        $content .= "\nAmount: " . $amount;
        $content .= "\nWallet Type: " . $wallet_type;
        $content .= "\nStatus: Success";
        $erlang_params = [];
        $erlang_params["tag"] = "Wallet server transaction";
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = array();
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_wallet_transaction");
        $post_status = "success";
    }

    return $post_status;
}

function get_address_type($address_type){
    $return_address_type = $address_type;
    if($address_type == "prepaid_payment_gateway"){
        $return_address_type = "Prepaid payment gateway";
    }

    return $return_address_type;
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
