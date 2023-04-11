<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_crypto.php";
include_once $currentPath . "/../include/class.xun_freecoin_payout.php";
include_once $currentPath . "/../include/class.xun_currency.php";
include_once $currentPath . "/../include/class.xun_wallet.php";
include_once $currentPath . "/../include/class.xun_wallet_transaction_model.php";
include_once $currentPath . "/../include/class.abstract_xun_user.php";
include_once $currentPath . "/../include/class.xun_user_model.php";
include_once $currentPath . "/../include/class.xun_user_service.php";
include_once $currentPath . "/../include/class.xun_business_model.php";
include_once $currentPath . "/../include/class.xun_business_service.php";
include_once $currentPath . "/../include/class.xun_livechat_model.php";
include_once $currentPath . "/../include/class.xun_wallet_transaction_model.php";
include_once $currentPath . "/../include/class.xun_group_chat.php";
include_once $currentPath . "/../include/class.xun_payment_gateway_model.php";
include_once $currentPath . "/../include/class.xun_payment_gateway_service.php";
include_once $currentPath . "/../include/class.xun_payment_gateway.php";
include_once $currentPath . "/../include/class.provider.php";
include_once $currentPath . "/../include/class.message.php";
include_once $currentPath . "/../include/class.account.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$xunCrypto = new XunCrypto($db, $post, $general);
$xunCurrency = new XunCurrency($db);
$xunPaymentGateway = new XunPaymentGateway($db, $post, $general, $setting, $xunCrypto, $xunCoins);
$provider = new Provider($db);
$message = new Message($db, $general, $provider);

$logPath = '../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);

$account = new Account($db, $setting, $message, $provider, $log);

$user_id = $argv[1];
$user_address = $argv[2];
$creditType = $argv[3];
$close_from_date = $argv[4];
$isWithholding = $argv[5];

$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Start Reclose Accounting.\n");

if (!$user_id) {
    echo "User ID cannot be empty.\n";
    $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t User ID cannot be empty.\n");
    exit();
}

if (!$user_address) {
    echo "User Address cannot be empty.\n";
    $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t User Address not found.\n");
    exit();
}

if (!$creditType) {
    echo "Credit Type cannot be empty.\n";
    $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Credit Type not found.\n");
    exit();
}

if (!$close_from_date) {
    echo "Closing From Date cannot be empty.\n";
    $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Closing From Date cannot be empty.\n");
    exit();
}

if ($isWithholding) {
    $creditType = $creditType . 'Withholding';
}

$db->where('id', $user_id);
$userRow = $db->getOne('xun_user');

if (!$userRow) {
    $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t User ID: $user_id User not found.\n");

    $company_address_list = $xunCrypto->company_wallet_address();
    echo "user_address:".$user_address."\n";
    print_r($company_address_list);
    if (!$company_address_list[$user_address]) {
        $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t  Compnay Address $user_id not found.\n");
        exit();
    }
    $recipient_address_info = $company_address_list[$user_address];
    $recipient_address_type = $recipient_address_info ? $recipient_address_info["type"] : null;

    $internal_address = $user_address;
 
} else {
       
    $db->where('user_id', $user_id);
    $db->where('address_type', 'nuxpay_wallet');
    $db->where('active', 1);
    $crypto_address_data = $db->getOne('xun_crypto_user_address');

    if (!$crypto_address_data) {
        $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t User ID: $user_id address not found.\n");

    } else {
        $internal_address = $crypto_address_data['address'];
        if ($internal_address != $user_address) {
            echo "internal address:" . $internal_address . "\n";
            echo "user address:" . $user_address . "\n";
            $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t User ID: $user_id Address not match.\n");
            exit();
        }

    }
}

$db->where('user_id', $user_id);
$db->where('type', $creditType);
$db->orderBy('date', 'ASC');
$first_closing_date = $db->getValue('xun_acc_closing', 'date');

$first_closing_timestamp = strtotime($first_closing_date);

$close_from_date_timestamp = strtotime($close_from_date);

echo "close date:" . $close_from_date . "\n";
if ($close_from_date_timestamp <= $first_closing_timestamp) {
    $close_from_date = date("Y-m-d", strtotime("+1 day", strtotime($first_closing_date)));

    echo "closing_from_date:" . $close_from_date . "\n";
}

$db->where('user_id', $user_id);
$db->where('type', $creditType);
$db->where('date', $close_from_date, ">=");
$deleted = $db->delete('xun_acc_closing');

echo "Optimizing Table\n";
$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Optimizing Table\n");

// Optmize the table after deletion
$db->optimize('xun_acc_closing');

$db->where('user_id', $user_id);
$db->where('type', $creditType);
$db->orderBy('date', 'DESC');
$accClosingResults = $db->getOne('xun_acc_closing');

$lastClosingDate = $accClosingResults['date'] ? date("Y-m-d", strtotime("+1 day", strtotime($accClosingResults['date']))) : "2021-03-26";
$lastClosingTimestamp = strtotime($lastClosingDate);
$lastBalance = $accClosingResults['balance'];

// Convert to timestamp for comparison
$lastClosingTimestamp = strtotime($lastClosingDate);

// Get the closing period in days (Default 1 day)
$closingPeriod = $setting->systemSetting['closingPeriod'] ? $setting->systemSetting['closingPeriod'] : 1;
$closingDate = date("Y-m-d", strtotime("-$closingPeriod day"));

$closingTimestamp = strtotime($closingDate);

if ($closingTimestamp <= $lastClosingTimestamp) {
    $log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t Already Close up to the latest date. | Last Closing Date: $last_closing_date, | Closing Date: $closingDate \n");
    exit();
}

$log->write(date("Y-m-d H:i:s") . " Last closing date for user " . $userRow["username"] . " Credit Type: $creditType is $lastClosingDate.  LQ: $lq\n");

$external_address = $xunCrypto->get_external_address($internal_address, $creditType);

$addressArr = array($internal_address, $external_address);

while ($lastClosingTimestamp <= $closingTimestamp) {

    echo "isWithholding:" . $isWithholding . "\n";
    echo "credit type :" . $creditType . "\n";
    echo "last balance :" . $lastBalance . "\n";

    if ($isWithholding) {
        $lastBalance = $account->closeNuxPayWithholdingClientAccount($userRow["id"], $lastClosingDate, $lastBalance, $creditType, $addressArr);
    } else {
        $lastBalance = $account->closeNuxPayClientAccount($userRow["id"], $lastClosingDate, $lastBalance, $creditType, $addressArr);
    }

    // Increment by 1 day for next iteration
    $lastClosingDate = date("Y-m-d", strtotime("+1 day", strtotime($lastClosingDate)));
    $lastClosingTimestamp = strtotime($lastClosingDate);

}

$log->write(date('Y-m-d H:i:s') . " PID: " . $process_id . " \t End Reclose Accounting.\n");
