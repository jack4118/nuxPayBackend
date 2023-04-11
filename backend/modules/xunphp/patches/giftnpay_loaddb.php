<?php
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

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php');
$path = realpath($logPath);
$log = new Log($logPath, $logBaseName);

$giftnpay = new GiftnPay($db, $setting, $post);

// $giftnpay->getCategoryList();
// $giftnpay->getProductList();
//$giftnpay->patchProductOptionUtid();
$callbackParams = array(
    "command" => "updateProductList",
    "lastChangesAt" => "2019-11-03 12:14:16"
);
$giftnpay->giftnpayCallback($callbackParams);
?>