<?php
$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.reloadly.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);

$db->where("provider_id", 1);
$db->where("type", 2);

$mereload_utility = $db->get("xun_pay_product");
$insert_data_arr = [];
for($i = 0; $i < count($mereload_utility); $i++){
    $data = $mereload_utility[$i];
    $product_id = $data["id"];
    $product_name = $data["name"];

    $db->where("product_id", $product_id);
    $product_option = $db->get("xun_pay_product_option");

    if(!$product_option){
        echo "\n product_id: $product_id, name: $product_name";
        $insert_min_data = array(
            "product_id" => $product_id,
            "amount_type" => "min",
            "amount" => 10,
            "status" => 1
        );
        $insert_max_data = array(
            "product_id" => $product_id,
            "amount_type" => "max",
            "amount" => 1000,
            "status" => 1
        );

        $insert_data_arr[] = $insert_min_data;
        $insert_data_arr[] = $insert_max_data;
    }
}

print_r($insert_data_arr);
if(!empty($insert_data_arr)){
    $ids = $db->insertMulti("xun_pay_product_option", $insert_data_arr);
    print_r($ids);
}


?>