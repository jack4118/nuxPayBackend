<?php


$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.database.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
    $db->where('status', "completed");
    $db->where('address_type', array("upline", "master_upline"), "IN");
    $result = $db->get('xun_wallet_transaction');
    print_r($db);
    print_r($result);

    $service_charge_result = $db->map('id')->ArrayBuilder()->get('xun_service_charge_audit');
    //print_r($service_charge_result);
    foreach($result as $data){
       // print_r($data);
        //echo "reference id ".$data["reference_id"];
       
        $is_master_upline = 0;
        if($data["address_type"] == "master_upline")
        {
            $is_master_upline = 1;
        }
        $service_charge_user_id = $service_charge_result[$data["reference_id"]]["user_id"];

        $insertArray = array(
            "user_id" => $data["user_id"],
            "service_charged_user_id" => $service_charge_user_id,
            "wallet_transaction_id" => $data["id"],
            "quantity" => $data["amount"],
            "crypto_currency" => $data["wallet_type"],
            "master_upline" => $is_master_upline,
            "created_at" => $data["created_at"],
            "updated_at" => $data["updated_at"],
        );

        $db->insert('xun_referral_transaction', $insertArray);
        
    }

