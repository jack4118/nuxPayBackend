<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$xun_referral_transaction_arr = $db->get("xun_referral_transaction");

for ($i = 0; $i < count($xun_referral_transaction_arr); $i++){
    $data = $xun_referral_transaction_arr[$i];
    $rec_id = $data["id"];

    $advertisement_id = $data["advertisement_id"];

    $db->where("id", $advertisement_id);
    $ad_user_id = $db->getValue("xun_marketplace_advertisement", "user_id");

    echo "\n ad_id: $advertisement_id, ad_user_id $ad_user_id";
    $update_data = [];
    $update_data["service_charged_user_id"] = $ad_user_id;

    $db->where("id", $rec_id);
    $db->update("xun_referral_transaction", $update_data);
}