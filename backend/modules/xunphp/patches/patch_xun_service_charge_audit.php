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
// SELECT * FROM xun_wallet_transaction a left join `xun_service_charge_audit` b on a.id = b.wallet_transaction_id where a.address_type = 'service_charge' 
$db->where("a.address_type", "service_charge");
$db->join("xun_service_charge_audit b", "a.id=b.wallet_transaction_id", "LEFT");
$tx_arr = $db->get("xun_wallet_transaction a", null, "a.*, b.id as b_id");

$insert_data_arr = [];
foreach($tx_arr as $data){
    $b_id = $data["b_id"];

    if(is_null($b_id)){
        $wallet_transaction_id = $data["id"];
        $created_at = $data["created_at"];
        $updated_at = $data["updated_at"];
        $amount = $data["amount"];
        $wallet_type = $data["wallet_type"];
        $status = $data["status"];
        $user_id = $data["user_id"];
        $insert_data = array(
            "user_id" => $user_id,
            "wallet_transaction_id" => $wallet_transaction_id,
            "amount" => $amount,
            "wallet_type" => $wallet_type,
            "status" => $status,
            "service_charge_type" => "",
            "transaction_type" => "",
            "created_at" => $created_at,
            "updated_at" => $updated_at
        );

        unset($user_id);
        $insert_data_arr[] = $insert_data;
    }
}

if(!empty($insert_data_arr)){
    $row_ids = $db->insertMulti("xun_service_charge_audit", $insert_data_arr);

    if(!$row_ids){
        print_r($db);
    }
}

?>