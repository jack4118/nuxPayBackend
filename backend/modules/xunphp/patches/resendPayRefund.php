<?php
$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);

//$url_string = "thenuxescrow.com/wallet_webservices.php";
$url_string = "thenuxprepaidwallet.com/webservices.php";
echo "\n url_string $url_string\n";

// {\"command\":\"fundOutCompanyWallet\",\"params\":{\"senderAddress\":\"0x747f8209dc25e7cf393326de533c07df19638336\",\"receiverAddress\":\"0xbd52cda80582ae47429f3f396d1641f8f1ee5fa0\",\"amount\":\"10.00000000\",\"satoshiAmount\":\"1000\",\"walletType\":\"myr2\",\"id\":2023,\"transactionToken\":\"8c3097d3849cc8818f3b288f968f3e0a\",\"addressType\":\"Top Up\"}}

$new_params = array
    (
            "command" => "fundOutCompanyWallet",
            "params" => array(
                "senderAddress" => "0x747f8209dc25e7cf393326de533c07df19638336",
                "receiverAddress" => "0x894cf4b725f7b8ae542243f4c4cabb0b89268f45",
                "amount" => "0.00020150",
                "satoshiAmount" => "20150",
                "walletType" => "bitcoin",
                "id" => 1044,
                "transactionToken" => "85d130e6048a17195cf521df4cff358a",
                "addressType" => "Top Up"
            )
    );
print_r($new_params);
//$post_return = $post->curl_post($url_string, $new_params, 0, 1);
print_r($post_return);