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

$db->where("type", [1,2], "in");
$productArr = $db->get("xun_pay_product");

$insertDataArr = [];
for($i = 0; $i < count($productArr); $i++){
    $productData = $productArr[$i];
    $productId = $productData["id"];
    $typeId = $productData["type"];

    $insertData = array(
        "product_id" => $productId,
        "type_id" => $typeId
    );
    
    $insertDataArr[] = $insertData;
}

$count = 0;
$c = 100;
while(true){
    echo "\n count $count";
    $count++;
    $newArr = array_splice($insertDataArr, $c);

    print_r($insertDataArr);

    $db->insertMulti("xun_pay_product_product_type_map", $insertDataArr);
    if(count($insertDataArr) < $c){
        break;
    }
    $insertDataArr = $newArr;
}

?>