<?php

$currentPath = __DIR__;
include_once $currentPath . '/../include/class.msgpack.php';
include_once $currentPath . '/../include/config.php';
include_once $currentPath . '/../include/class.database.php';
include_once $currentPath . '/../include/class.xun_email.php';
include_once $currentPath . '/../include/class.xun_business.php';
include_once $currentPath . '/../include/class.xun_user.php';
include_once $currentPath . '/../include/class.xun_erlang.php';
include_once $currentPath . '/../include/class.xun_crypto.php';
include_once $currentPath . '/../include/class.xun_livechat.php';
include_once $currentPath . '/../include/class.xun_marketplace.php';
include_once $currentPath . '/../include/class.xun_xmpp.php';
include_once $currentPath . '/../include/class.xun_sms.php';
include_once $currentPath . '/../include/class.xun_admin.php';
include_once $currentPath . '/../include/class.post.php';
include_once $currentPath . '/../include/class.webservice.php';
include_once $currentPath . '/../include/class.xun_webservice.php';
include_once $currentPath . '/../include/class.message.php';
include_once $currentPath . '/../include/class.setting.php';
include_once $currentPath . '/../include/class.general.php';
include_once $currentPath . '/../include/class.log.php';
include_once $currentPath . '/../include/libphonenumber-for-php-master-v7.0/vendor/autoload.php';
include_once $currentPath . '/../include/class.language.php';
include_once $currentPath . '/../include/class.provider.php';
include_once $currentPath . '/../include/class.ticketing.php';
include_once $currentPath . '/../include/class.country.php';
include_once $currentPath . '/../include/class.xun_aws.php';
include_once $currentPath . '/../include/class.xun_giftcode.php';
include_once $currentPath . '/../include/class.xun_tree.php';
include_once $currentPath . '/../include/class.xun_referral.php';
include_once $currentPath . '/../include/class.xun_currency.php';
include_once $currentPath . '/../include/class.xun_freecoin_payout.php';
include_once $currentPath . '/../include/class.xun_company_wallet.php';
include_once $currentPath . '/../include/class.xun_company_wallet_api.php';
include_once $currentPath . '/../include/class.push_notification.php';
include_once $currentPath . '/../include/class.abstract_xun_user.php';
include_once $currentPath . '/../include/class.xun_user_model.php';
include_once $currentPath . '/../include/class.xun_user_service.php';
include_once $currentPath . '/../include/class.xun_business_model.php';
include_once $currentPath . '/../include/class.xun_business_service.php';
include_once $currentPath . '/../include/class.xun_livechat_model.php';
include_once $currentPath . '/../include/class.xun_wallet_transaction_model.php';
include_once $currentPath . '/../include/class.xun_group_chat.php';
include_once $currentPath . '/../include/class.xun_payment_gateway_model.php';
include_once $currentPath . '/../include/class.xun_payment_gateway_service.php';

include_once $currentPath . '/../include/class.xun_kyc.php';
include_once $currentPath . '/../include/class.xun_wallet.php';
include_once $currentPath . '/../include/class.xun_ip.php';
include_once $currentPath . '/../include/class.xun_announcement.php';

include_once $currentPath . '/../include/class.xun_commission.php';
include_once $currentPath . '/../include/class.xun_service_charge.php';
include_once $currentPath . '/../include/class.xun_in_app_notification.php';
include_once $currentPath . '/../include/class.xun_pay.php';
include_once $currentPath . '/../include/class.xun_pay_provider.php';
include_once $currentPath . '/../include/class.reloadly.php';
include_once $currentPath . '/../include/class.group_chat_model.php';
include_once $currentPath . '/../include/class.group_chat_service.php';
include_once $currentPath . '/../include/class.xun_swapcoins.php';
include_once $currentPath . '/../include/class.xun_pay_model.php';
include_once $currentPath . '/../include/class.xun_pay_service.php';
include_once $currentPath . '/../include/class.giftnpay.php';
include_once $currentPath . '/../include/class.xun_coins.php';
include_once $currentPath . '/../include/class.account.php';
include_once $currentPath . '/../include/class.xun_story.php';
include_once $currentPath . '/../include/class.xun_aws_web_services.php';
include_once $currentPath . '/../include/class.xun_crowdfunding.php';
include_once $currentPath . '/../include/class.xun_payment_gateway.php';
include_once $currentPath . '/../include/class.xun_phone_approve.php';
include_once $currentPath . '/../include/class.xun_phone_approve_service.php';
include_once $currentPath . '/../include/class.xun_phone_approve_model.php';
include_once $currentPath . '/../include/class.business_partner.php';
include_once $currentPath . '/../include/class.xun_reward.php';
include_once $currentPath . '/../include/class.xun_cashpool.php';
include_once $currentPath . '/../include/class.cash.php';
include_once $currentPath . '/../include/class.xun_sales.php';
include_once $currentPath . '/../include/class.xun_marketer.php';
include_once $currentPath . "/../include/class.xun_miner_fee.php";
include_once $currentPath . "/../include/class.binance.php";
include_once $currentPath . "/../include/class.xun_payment.php";


$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$currentPath = __DIR__;
$logPath = $currentPath . '/log/';
$logBaseName = basename(__FILE__, '.php');
$path = realpath($logPath);

$setting = new Setting($db);
$general = new General($db, $setting);
//$log           = new Log();
$log = new Log($logPath, $logBaseName);
$provider = new Provider($db);
$message = new Message($db, $general, $provider);
$webservice = new Webservice($db, $general, $message);
$xunWebservice = new XunWebservice($db);
$language = new Language($db, $general, $setting);
$post = new post();
$country = new Country($db, $general);
$xunEmail = new XunEmail($db, $post);
$xunBusiness = new XunBusiness($db, $post, $general, $xunEmail);
$xunGroupChat = new xunGroupChat($db, $post, $general, $xunEmail);
$xunUser = new XunUser($db, $post, $general);
$xunErlang = new XunErlang($db, $post, $general);
$xunCrypto = new XunCrypto($db, $post, $general);
$xunXmpp = new XunXmpp($db, $post);
$xunSms = new XunSms($db, $post);
$xunLivechat = new XunLivechat($db, $post);
$xunAdmin = new XunAdmin($db, $setting, $general, $post);
$xunMarketplace = new XunMarketplace($db, $post, $general);
$ticket = new Ticket($db, $general, $setting, $message, $log);
$xunAws = new XunAws($db, $setting);
$xunGiftCode = new XunGiftCode($db, $post, $general);
$xunTree = new XunTree($db, $setting, $general);
$xunReferral = new XunReferral($db, $setting, $general, $xunTree);
$xunCurrency = new XunCurrency($db);
$xunCompanyWalletAPI = new XunCompanyWalletAPI($db, $setting, $general, $post);

$xunKYC = new XunKYC($db, $setting, $general);
$xunAnnouncement = new XunAnnouncement($db, $setting, $general);
$xunServiceCharge = new XunServiceCharge($db, $setting, $general);
$account = new Account($db, $setting, $message, $provider, $log);
$xunPay = new XunPay($db, $setting, $general, $account);
$xunSwapcoins = new XunSwapcoins($db, $setting, $post);
$giftnpay = new GiftnPay($db, $setting, $post);
$xunCoins = new XunCoins($db, $setting);
$xunStory = new XunStory($db, $post, $general, $setting);
$xunAWSWebservices = new XunAWSWebservices($db);
$xunCrowdfunding = new XunCrowdfunding($db, $post, $general, $setting);
$xunPaymentGateway = new XunPaymentGateway($db, $post, $general, $setting, $xunCrypto, $xunCoins);
$xunPhoneApprove = new XunPhoneApprove($db, $setting, $general, $post, $account);
$xunReward = new XunReward($db, $partnerDB, $post, $general, $setting);
$xunCashpool = new XunCashpool($db, $general, $setting, $account);
$xunMarketer = new XunMarketer($db, $setting, $general);
$xunMinerFee = new XunMinerFee($db, $general, $setting, $log);
$binance = new Binance($config['binanceAPIKey'], $config['binanceAPISecret'], $config['binanceAPIURL'], $config['binanceWAPIURL']);
$xunPayment = new XunPayment($db, $post, $general, $setting, $xunCrypto, $xunCoins);

try {
    /**
     * check xun_wallet_transaction for wallet_success and pending
     */

    $date = date("Y-m-d H:i:s");
    // $start_time = date("Y-m-d H:i:s", strtotime("-2 hours", strtotime($date)));
    // $end_time = date("Y-m-d H:i:s", strtotime("-1 hour", strtotime($date)));

    $start_time = date("2021-04-01"); //Start checking transaction from this date

    $params = array(
        "end_dt" => $date,
        "start_dt" => $start_time
    );
    $xunCrypto->check_wallet_transaction_status($params);

} catch (Exception $e) {
    $msg = $e->getMessage();

    $message = $logBaseName . "\n";
    $message .= "Time : " . date("Y-m-d H:i:s");
    $message .= $msg;

    $erlang_params["tag"] = "Process Error";
    $erlang_params["message"] = $message;
    $erlang_params["mobile_list"] = ["+60124466833", "+60122590231"];
    $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_wallet_transaction");
}
