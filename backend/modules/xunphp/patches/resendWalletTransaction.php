<?php
$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_marketplace.php";
include_once $currentPath . '/../include/class.xun_crypto.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$xunMarketplace = new XunMarketplace($db, $post, $general);
$xunCrypto = new XunCrypto($db, $post, $general);

$wallet_transaction_id_arr = [20640];
//$db->where("recipient_address","0x54727355ff8a407cd707b40bf6370b75f1df2681");
//$db->where("address_type","marketer");
//$db->where("status", "pending");
//$db->where("created_at", "2020-05-30 00:00:00", ">");
//$wallet_transaction_id_arr = $db->getValue("xun_wallet_transaction","id",null);

print_r($wallet_transaction_id_arr);
//exit();
foreach ($wallet_transaction_id_arr as $wallet_transaction_id) {
    unset($url_string);
    unset($amount_satoshi);
    $db->where("id", $wallet_transaction_id);
    $wallet_transaction_data = $db->getOne("xun_wallet_transaction");

    if (!$wallet_transaction_data) {
        echo "\nInvalid wallet transaction ID: $wallet_transaction_id";
        continue;
    }

    if (in_array($wallet_transaction_data["status"], ["completed", "wallet_success"])) {
        echo "\nResend Not Allowed: Wallet TX ID: $wallet_transaction_id, Transaction Status is " . $wallet_transaction_data['status'];
        continue;
    }

    $sender_user_id = $wallet_transaction_data["sender_user_id"];
    $recipient_user_id = $wallet_transaction_data["recipient_user_id"];
    $recipient_address = $wallet_transaction_data["recipient_address"];
    $sender_address = $wallet_transaction_data["sender_address"];
    $address_type = $wallet_transaction_data["address_type"];

    $transaction_type = "internal";

    switch ($sender_user_id) {
        case "trading_fee":
            $url_string = $config["tradingFeeURL_walletTransaction"];
            break;
        case "company_pool":
            if ($address_type == "marketer") {
                $url_string = $config["externalTransferCompanyPoolURL"];
                $transaction_type = "external";
            } else {
                $url_string = $config["companyPoolURL_walletTransaction"];
            }
            break;

        default:
            break;
    }

    echo "\n url_string $url_string\n";
    if (!$url_string) {
        echo "\nError: Wallet TX ID: $wallet_transaction_id, Invalid URL string";
        //exit();
        continue;
    }

    $amount = $wallet_transaction_data["amount"];
    $wallet_type = $wallet_transaction_data["wallet_type"];

    $amount_satoshi = $xunCrypto->get_satoshi_amount($wallet_type, $amount);
    if ($amount_satoshi <= 0) {
        echo "\nInvalid amount: $amount_satoshi";
        continue;
    }

//exit();
    if ($transaction_type == "internal") {
        $curl_params = array(
            "walletTransactionID" => $wallet_transaction_data["id"],
            "receiverAddress" => $recipient_address,
            "amount" => $amount_satoshi,
            "walletType" => $wallet_type,
        );
        print_r($curl_params);
        $post_return = $post->curl_post($url_string, $curl_params, 0, 0);
    } else {
        $curl_params = array(
            "receiverAddress" => $recipient_address,
            "amount" => $amount_satoshi,
            "walletType" => $wallet_type,
            "walletTransactionID" => $wallet_transaction_data["id"],
            "transactionToken" => $wallet_transaction_data["transaction_token"],
            "senderAddress" => $sender_address,

        );
        print_r($curl_params);
        $post_return = $post->curl_post($url_string, $curl_params, 0, 1, array(), 1, 1);
    }
    print_r($post_return);
}
