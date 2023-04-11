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
$reloadly = new reloadly($db, $setting, $post);

// $reloadly->getAccessToken();
// $reloadly->getAccountBalance();
// $reloadly->getOperatorListing();
$reloadly->getCountries();
// $reloadly->addDefaultProductOption();
// $reloadly->detectPhoneNumber(array("phone_number" => "+60124466833", "country_code" => "my"));

// [6.96,13.88,41.58,83.17,138.63]
// $params = [];
// $params["amount"] = 6.96;
// $params["operatorId"] = 284;
// $params["referenceId"] = 'x0007';
// $params["senderPhone"] = array("countryCode" => "MY", "number" => "+60124466833");
// $params["recipientPhone"] = array("countryCode" => "MY", "number" => "+60124466833");
// $reloadly->topup($params);

// $params = [];
// $params["amount"] = 2;
// $params["operatorId"] = 433;
// $params["currencyCode"] = 'MYR';
// $reloadly->getFxRate($params);

/**
 * Array
(
    [id] => 434
    [name] => Singtel Singapore
    [fxRate] => 0.24332
    [currencyCode] => SGD
)
Array
(
    [id] => 433
    [name] => Starhub Singapore
    [fxRate] => 0.26479
    [currencyCode] => SGD
)
$params["amount"] = 20.33;
$params["operatorId"] = 434;
$params["currencyCode"] = 'myr';
+6581321162
Array
(
    [id] => 434
    [name] => Singtel Singapore
    [fxRate] => 4.94671
    [currencyCode] => SGD
)
 */