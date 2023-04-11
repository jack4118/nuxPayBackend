<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$db->where("type", "cryptocurrency");
$cryptocurrency_arr = $db->get("xun_marketplace_currencies");

foreach($cryptocurrency_arr as $currency){
    $currency_id = $currency["currency_id"];
    $unit_conversion = $currency["unit_conversion"];
    $is_show_new_coin = $currency["is_show_new_coin"];
    $is_marketplace = $currency["status"];

    $update_data = [];
    $update_data["unit_conversion"] = $unit_conversion;
    $update_data["is_show_new_coin"] = $is_show_new_coin;
    $update_data["is_marketplace"] = $is_marketplace;
    $update_data["updated_at"] = date("Y-m-d H:i:s");

    $db->where("currency_id", $currency_id);
    $db->update("xun_coins", $update_data);
}

?>