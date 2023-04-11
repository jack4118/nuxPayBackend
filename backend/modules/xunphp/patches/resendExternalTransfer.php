<?php
$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_marketplace.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$xunMarketplace = new XunMarketplace($db, $post, $general);

$url_string = $config["externalTransferCompanyPoolURL"];
echo "\n url_string $url_string\n";
//{\"receiverAddress\":\"0x8ba79cc04c3508d7b8450fd1ddf45697fe76de6e\",\"amount\":\"17604893.000000\",\"walletType\":\"tetherUSD\",\"walletTransactionID\":20653,\"transactionToken\":\"92d82c7c365bb6df4f417736147055f8\",\"senderAddress\":\"0x54727355ff8a407cd707b40bf6370b75f1df2681\"}

$new_params = array(
        "receiverAddress" => "0x8ba79cc04c3508d7b8450fd1ddf45697fe76de6e",
        "amount" => "17604893",
        "walletType" => "tetherUSD",
        "walletTransactionID" => 20653,
        "transactionToken" => "92d82c7c365bb6df4f417736147055f8",
        "senderAddress" => ""
);
print_r($new_params);

// $post_return = $post->curl_post($url_string, $new_params,0, 1, array(), 1, 1);
print_r($post_return);