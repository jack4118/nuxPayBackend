<?php
$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_crypto.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$xunCrypto = new XunCrypto($db, $post, $general);

$page_size = 100;
$page_number = 1;

$address_list = $db->map("address")->ArrayBuilder()->get("xun_crypto_user_address");
$company_wallet_address = $xunCrypto->company_wallet_address();
// print_r($company_wallet_address);
while(true){
    $start_limit = ($page_number - 1) * $page_size;
    $limit       = array($start_limit, $page_size);
    
    $xun_wallet_transaction = $db->get("xun_wallet_transaction", $limit, "id, user_id, sender_address, recipient_address");
    
    foreach($xun_wallet_transaction as $data){
        $sender_address = $data["sender_address"];
        $recipient_address = $data["recipient_address"];

        if(isset($address_list[$sender_address])){
            $sender_user_id = $address_list[$sender_address]["user_id"];
        }else if(isset($company_wallet_address[$sender_address])){
            $sender_user_id = $company_wallet_address[$sender_address]["type"];
        }else{
            $sender_user_id = '';
        }

        if(isset($address_list[$recipient_address])){
            $recipient_user_id = $address_list[$recipient_address]["user_id"];
        }else if(isset($company_wallet_address[$recipient_address])){
            $recipient_user_id = $company_wallet_address[$recipient_address]["type"];
        }else{
            $recipient_user_id = '';
        }

        echo "\n $sender_address : $sender_user_id; $recipient_address: $recipient_user_id";

        $update_data = [];
        $update_data["sender_user_id"] = $sender_user_id;
        $update_data["recipient_user_id"] = $recipient_user_id;

        $db->where("id", $data["id"]);
        $db->update("xun_wallet_transaction", $update_data);
    }

    if(count($xun_wallet_transaction) < $page_number){
        break;
    }
    $page_number += 1;
}

?>