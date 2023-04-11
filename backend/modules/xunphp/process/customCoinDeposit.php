<?php
$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.provider.php";
include_once $currentPath . "/../include/class.message.php";
include_once $currentPath . "/../include/class.account.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$provider = new Provider($db);
$message = new Message($db, $general, $provider);

$logPath = $currentPath . '/log/';
$logBaseName = basename(__FILE__, '.php');
$path = realpath($logPath);
$log = new Log($logPath, $logBaseName);
$account = new Account($db, $setting, $message, $provider, $log);

$wallet_type = "gsc";
$credit_type = "coinCredit";
$amount = "100";
$reference_id = '';
$db->where("username", $wallet_type);
$coin_user = $db->getOne("xun_user");
$userID = $coin_user["id"];

exit();
$res = $account->insertDebitTransaction($userID, $credit_type, $amount, $reference_id, null);
echo "\n res $res";

$account_balance = $account->getClientCacheBalance($userID, $credit_type);
echo "\n account_balance $account_balance";
exit();
?>