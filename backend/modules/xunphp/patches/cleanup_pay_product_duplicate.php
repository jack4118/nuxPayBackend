<?php
echo "\n ###";
$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.giftnpay.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);

echo "\n ##1";
$sq = $db->subQuery();
$sq->groupBy("provider_id");
$sq->groupBy("product_code");
$sq->having("count(*)", 1, ">");
$sq->getValue("xun_pay_product", "product_code", null);

$db->where("product_code", $sq, "in");
$db->where("provider_id", 3);
$db->orderBy("id", "ASC");
$productList = $db->get("xun_pay_product");

$sortedProductList = [];
foreach ($productList as $data) {
    $productId = $data["id"];
    $productCode = $data["product_code"];

    $sortedProductList[$productCode][] = $data;
}

foreach ($sortedProductList as $productCodeDataList) {
    $finalData = $productCodeDataList[0];

    $finalDataId = $finalData["id"];
    $updateData = [];
    $updateData["active"] = 1;

    $db->where("id", $finalDataId);
    $db->update("xun_pay_product", $updateData);

    unset($productCodeDataList[0]);
    foreach ($productCodeDataList as $productData) {
        $productId = $productData["id"];

        $db->where("product_id", $productId);
        if ($db->delete("xun_pay_product_option")) {
            echo "\n deleted product id: $productId from xun_pay_product_option";
        }
        
        $db->where("product_id", $productId);
        if ($db->delete("xun_pay_product_product_type_map")) {
            echo "\n deleted product id: $productId from xun_pay_product_product_type_map";
        }

        $db->where("id", $productId);
        if ($db->delete("xun_pay_product")) {
            echo "\n deleted product id: $productId from xun_pay_product";
        }
    }
}
