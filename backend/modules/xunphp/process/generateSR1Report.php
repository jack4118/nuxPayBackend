<?php

$currentPath = __DIR__;
include_once $currentPath . '/../include/class.msgpack.php';
include_once $currentPath . '/../include/config.php';
include_once $currentPath . '/../include/class.admin.php';
include_once $currentPath . '/../include/class.database.php';
include_once $currentPath . '/../include/class.cash.php';
include_once $currentPath . '/../include/class.webservice.php';
include_once $currentPath . '/../include/class.user.php';
include_once $currentPath . '/../include/class.api.php';
include_once $currentPath . '/../include/class.message.php';
include_once $currentPath . '/../include/class.permission.php';
include_once $currentPath . '/../include/class.setting.php';
include_once $currentPath . '/../include/class.language.php';
include_once $currentPath . '/../include/class.provider.php';
include_once $currentPath . '/../include/class.journals.php';
include_once $currentPath . '/../include/class.country.php';
include_once $currentPath . '/../include/class.general.php';
include_once $currentPath . '/../include/class.tree.php';
include_once $currentPath . '/../include/class.activity.php';
include_once $currentPath . '/../include/class.invoice.php';
include_once $currentPath . '/../include/class.product.php';
include_once $currentPath . '/../include/class.client.php';
include_once $currentPath . '/../include/class.memo.php';
include_once $currentPath . '/../include/class.announcement.php';
include_once $currentPath . '/../include/class.document.php';
include_once $currentPath . '/../include/class.bonus.php';
include_once $currentPath . '/../include/PHPExcel.php';
include_once $currentPath . '/../include/class.log.php';
include_once $currentPath . '/../include/class.report.php';
include_once $currentPath . '/../include/class.dashboard.php';
include_once $currentPath . '/../include/class.ticketing.php';
include_once $currentPath . '/../include/class.excel.php';
include_once $currentPath . '/../include/PHPExcel.php';
include_once $currentPath . '/../include/PHPExcel/Writer/Excel2007.php';
include_once $currentPath . '/../language/lang_all.php';

include_once $currentPath . '/../include/class.ticketing.php';
include_once $currentPath . '/../include/class.xun_admin.php';
include_once $currentPath . '/../include/class.post.php';
include_once $currentPath . '/../include/class.xun_xmpp.php';
include_once $currentPath . '/../include/class.xun_email.php';
include_once $currentPath . '/../include/class.xun_business.php';
include_once $currentPath . '/../include/class.xun_aws.php';
include_once $currentPath . '/../include/class.xun_tree.php';
include_once $currentPath . '/../include/class.xun_announcement.php';
include_once $currentPath . '/../include/class.xun_livechat_model.php';
include_once $currentPath . '/../include/class.abstract_xun_user.php';
include_once $currentPath . '/../include/class.xun_user_model.php';
include_once $currentPath . '/../include/class.xun_user_service.php';

include_once $currentPath . '/../include/class.xun_crypto.php';
include_once $currentPath . '/../include/class.xun_giftcode.php';
include_once $currentPath . '/../include/class.xun_referral.php';
include_once $currentPath . '/../include/class.xun_currency.php';
include_once $currentPath . '/../include/class.xun_freecoin_payout.php';
include_once $currentPath . '/../include/class.xun_company_wallet.php';
include_once $currentPath . '/../include/class.xun_company_wallet_api.php';
include_once $currentPath . '/../include/class.push_notification.php';
include_once $currentPath . '/../include/class.xun_business_model.php';
include_once $currentPath . '/../include/class.xun_business_service.php';
include_once $currentPath . '/../include/class.xun_wallet_transaction_model.php';
include_once $currentPath . '/../include/class.xun_group_chat.php';
include_once $currentPath . '/../include/class.xun_payment_gateway_model.php';
include_once $currentPath . '/../include/class.xun_payment_gateway_service.php';

include_once $currentPath . '/../include/class.xun_kyc.php';
include_once $currentPath . '/../include/class.xun_wallet.php';
include_once $currentPath . '/../include/class.xun_ip.php';
include_once $currentPath . '/../include/class.xun_commission.php';
include_once $currentPath . '/../include/class.xun_service_charge.php';
include_once $currentPath . '/../include/class.xun_in_app_notification.php';
include_once $currentPath . '/../include/class.xun_pay.php';
include_once $currentPath . '/../include/class.xun_pay_provider.php';
include_once $currentPath . '/../include/class.reloadly.php';
include_once $currentPath . '/../include/class.group_chat_model.php';
include_once $currentPath . '/../include/class.group_chat_service.php';
include_once $currentPath . '/../include/class.xun_pay_model.php';
include_once $currentPath . '/../include/class.xun_pay_service.php';
include_once $currentPath . '/../include/class.giftnpay.php';
include_once $currentPath . '/../include/class.xun_coins.php';
include_once $currentPath . '/../include/class.account.php';
include_once $currentPath . '/../include/class.xun_story.php';
include_once $currentPath . '/../include/class.xun_payment_gateway.php';

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$setting = new Setting($db);
$general = new General($db, $setting);
$post = new post();
$log = new Log();

$msgpack = new msgpack();

$user = new User($db, $setting, $general);
// $graph           = new graph($db, $setting, $general);
$api = new Api($db, $general);
$provider = new Provider($db);
$message = new Message($db, $general, $provider);
$webservice = new Webservice($db, $general, $message);
$permission = new Permission($db, $general);
// $wallet          = new Wallet($db, $setting, $general);

$cash = new Cash($db, $setting, $message, $provider, $log, $general, $client, $wallet);
$language = new Language($db, $general, $setting);
$activity = new Activity($db, $general);

// $journals        = new Journals($db, $general);
$country = new Country($db, $general);
$tree = new Tree($db, $setting, $general, $activity);
$invoice = new Invoice($db, $setting, $general, $activity);
$product = new Product($db, $setting, $general);
// $otp             = new Otp($db, $setting, $general, $config);
$bonus = new Bonus($db, $general, $setting, $cash, $log, $otp, $tree);
// $validation      = new validation($db, $setting, $general, $country, $tree, $cash, $activity, $product, $invoice, $bonus, $otp, $config);
$client = new Client($db, $setting, $general, $country, $tree, $cash, $activity, $product, $invoice, $bonus, $otp, $config, $wallet, $validation, $log);
$admin = new Admin($db, $setting, $general, $cash, $invoice, $product, $country, $activity, $client, $otp, $tree, $bonus, $wallet);
$memo = new Memo($db, $general, $setting);
$announcement = new Announcement($db, $general, $setting);
$document = new Document($db, $general, $setting);
$report = new Report($db, $general, $setting, $bonus, $cash, $tree);

$dashboard = new Dashboard($db, $announcement, $cash, $admin, $setting, $general, $wallet, $product);
$ticket = new Ticket($db, $setting, $general, $otp);
$objPHPExcel = new PHPExcel();

$xunAdmin = new XunAdmin($db, $setting, $general, $post);
$xunCrypto = new XunCrypto($db, $post, $general);
$xunXmpp = new XunXmpp($db, $post);
$xunEmail = new XunEmail($db, $post);
$xunBusiness = new XunBusiness($db, $post, $general, $xunEmail);
$xunAws = new XunAws($db, $setting);
$xunTree = new XunTree($db, $setting, $general);
$xunAnnouncement = new XunAnnouncement($db, $setting, $general);

$xunReferral = new XunReferral($db, $setting, $general, $xunTree);
$xunCurrency = new XunCurrency($db);
$xunCompanyWalletAPI = new XunCompanyWalletAPI($db, $setting, $general, $post);
$xunKYC = new XunKYC($db, $setting, $general);
$xunAnnouncement = new XunAnnouncement($db, $setting, $general);
$xunServiceCharge = new XunServiceCharge($db, $setting, $general);
$account = new Account($db, $setting, $message, $provider, $log);
$xunPay = new XunPay($db, $setting, $general, $account);
$giftnpay = new GiftnPay($db, $setting, $post);
$xunCoins = new XunCoins($db, $setting);
$xunStory = new XunStory($db, $post, $general, $setting);
$xunPaymentGateway = new XunPaymentGateway($db, $post, $general, $setting, $xunCrypto, $xunCoins);

$excel = new Excel($db, $setting, $message, $log, $general, $cash, $xunAdmin);

$general->setCurrentLanguage("english");
$general->setTranslations($translations);

echo date("Y-m-d H:i:s") . " Process Starting!\n";

$sql_query = "select a.*, b.username, b.nickname, b.type from (SELECT sender_user_id as user_id, sender_address as address, address_type FROM `xun_wallet_transaction` where wallet_type = 'sms123rewards' union all SELECT recipient_user_id as user_id, recipient_address as address, address_type FROM `xun_wallet_transaction` where wallet_type = 'sms123rewards') a left join xun_user b on a.user_id = b.id where a.user_id != 'topup' and a.user_id != '' and a.user_id != 'trading_fee' and a.address != '' group by address";

$user_list = $db->rawQuery($sql_query);

$wallet_type = 'sms123rewards';

$output_list = [];
foreach ($user_list as $user) {
    $address = $user['address'];
    $nickname = $user['nickname'];
    $username = $user['username'];
    $address_type = $user['address_type'];
    $user_type = $user['type'];

    try {
        $balance = $xunCrypto->get_wallet_balance($address, $wallet_type);

    } catch (Exception $e) {
        echo "\nError. Address: $address. " . $e->getMessage();
        $balance = 'NULL';
    }

    $type = $user_type == "business" && $address_type == "reward" ? "Company Pool" : ucfirst($user_type);
    $user_data = array(
        "nickname" => $nickname,
        "phone_number" => $username,
        "address" => $address,
        "balance" => $balance,
        "type" => $type
    );
    $output_list[] = $user_data;
}

if (empty($output_list)) {
    echo "\nEmpty output list";
    exit();
}

$excelPath = realpath(dirname(__FILE__))."/../xlsx/";
$adminExcelPath = $setting->systemSetting["adminExcelPath"];
$frontendServerIP = $setting->systemSetting["frontendServerIP"];
$isLocalhost = $setting->systemSetting["isLocalhost"];

$headerAry = ["Name", "Phone Number", "Balance", "Type"];
$titleAry = ["nickname", "phone_number", "balance", "type"];
$fileName = "SR1_balance_".date("Ymd_His").".xlsx";

$finalPath = $excel->simpleExportExcel($output_list, $headerAry, $titleAry, $fileName);
echo date("Y-m-d H:i:s") . " Filename: " .$finalPath.  "\n";

// LIVE SCP
if(!$isLocalhost)
$cmd = "scp -r ". $excelPath .$row['file_name'] . " root@".$frontendServerIP.":".$adminExcelPath;

$result = exec($cmd);

echo date("Y-m-d H:i:s") . " Process End!\n";
