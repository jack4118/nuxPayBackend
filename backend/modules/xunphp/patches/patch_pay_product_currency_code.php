<?php

include_once '../include/config.php';
include_once '../include/class.database.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$countries = $db->map("iso_code2")->ArrayBuilder()->get("country", null, "id, LOWER(iso_code2) as iso_code2, LOWER(currency_code) as currency_code");

$db->where("currency_code", "");
$product_list = $db->get("xun_pay_product");

foreach($product_list as $data){
    $id = $data["id"];
    $country_code = $data["country_iso_code2"];
    $country_data = $countries[$country_code];
    if(!$country_data){
        echo "\n $country_code not found.";
    }

    $country_currency_code = $country_data["currency_code"];

    $update_data = [];
    $update_data["currency_code"] = $country_currency_code;

    $db->where("id", $id);
    $db->update("xun_pay_product", $update_data);
}

?>