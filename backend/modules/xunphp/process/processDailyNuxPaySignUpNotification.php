<?php 

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.xun_crypto.php";
include_once $currentPath . "/../include/class.xun_xmpp.php";
include_once $currentPath . "/../include/class.xun_wallet.php";
include_once $currentPath . "/../include/class.xun_company_wallet.php";
include_once $currentPath . "/../include/class.xun_wallet_transaction_model.php";

include_once $currentPath . "/../include/class.abstract_xun_user.php";
include_once $currentPath . "/../include/class.xun_user_model.php";
include_once $currentPath . "/../include/class.xun_user_service.php";
include_once $currentPath . "/../include/class.xun_business_model.php";
include_once $currentPath . "/../include/class.xun_business_service.php";
include_once $currentPath . "/../include/class.xun_livechat_model.php";
include_once $currentPath . "/../include/class.xun_miner_fee.php";
include_once $currentPath . "/../include/class.xun_currency.php";

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$log = new Log($logPath, $logBaseName);
$xunXmpp = new XunXmpp($db, $post);
$xunCrypto = new XunCrypto($db, $post, $general);
$xunWallet = new XunWallet($db);
$xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);
$xunMinerFee = new XunMinerFee($db, $general, $setting, $log);
$xun_business_service = new XunBusinessService($db);
$xunCurrency = new XunCurrency($db);

$yesterday = date("Y-m-d 00:00:00", strtotime("-1 days"));
$end_of_yesterday = date("Y-m-d 23:59:59", strtotime("-1 days"));

$db->where('type', 'reseller');
$db->where('status', 'approved');
$db->where('deleted', 0);
$reseller = $db->get('reseller');

$db->where('created_at', $yesterday, '>=');
$db->where('created_at', $end_of_yesterday, '<=');
$db->where('type', array('business', 'reseller'), 'IN');
$db->groupBy('type');
$user_list = $db->get('xun_user', null, 'count(id) as total_user, type');

$total_sign_up =0;
if($user_list){
    foreach($user_list as $k1 => $v1){
        if($v1['type']== 'business'){
            $total_sign_up = $v1['total_user'];
        }
    }
}

$db->where('created_at', $yesterday, '>=');
$db->where('created_at', $end_of_yesterday, '<=');
$db->where('action_type', '%Homepage%', 'LIKE');
$db->groupBy('device_id');
$nuxpay_visit_data = $db->get('utm_tracking', null, 'device_id');

$total_nuxpay_visit = count($nuxpay_visit_data);

$message =  date("Y-m-d", strtotime('-1 days'))."\n";
$message .= "NuxPay\n";
$message .= "-------------------\n";
$message .= "Total Visit: ".$total_nuxpay_visit."\n";
$message .= "Total Sign Up: ".$total_sign_up."\n"; 
$message .= "-------------------\n\n";


foreach($reseller as $key=>$value){
    $username = $value['username'];
    $reseller_name = $value['name'];
    $referral_code = $value['referral_code'];

    $content_str = "username=".$username.'|code='.$referral_code;

    $db->where('created_at', $yesterday, '>=');
    $db->where('created_at', $end_of_yesterday, '<=');
    $db->where('action_type', 'KOL Landing Page');
    $db->where('content', $content_str, 'REGEXP');
    $db->groupBy('device_id');
    $visit_kol_page = $db->get('utm_tracking', null, 'device_id');

    $total_visit = count($visit_kol_page);

    if($visit_kol_page){
        $device_ids = array_column($visit_kol_page, 'device_id');

        $db->where('device_id', $device_ids, 'IN');
        $db->where('action_type', '%NuxPay Account successfully registered.%', 'LIKE');
        $db->where('created_at', $yesterday, '>=');
        $db->where('created_at', $end_of_yesterday, '<=');
        $copyDb= $db->copy();
        $db->where('content', "$content_str", 'REGEXP');
    
        $landing_page_tracking  = $db->get('utm_tracking', null, 'id');
        $total_landing_page_sign_up = count($landing_page_tracking);
    
    
        $copyDb->where('content', "$content_str", 'REGEXP');
        $sign_up_page_tracking  = $copyDb->get('utm_tracking', null, 'id');
    
        $total_signup_page_sign_up = count($sign_up_page_tracking);
    
    
        $message .= $reseller_name." (".$username.")\n";
        $message .= "-------------------\n";
        $message .= "Total Visit: ".$total_visit."\n";
        $message .= "Total Signed Up: ".$total_landing_page_sign_up."\n";
        $message .= "Sign Up at NuxPay Page: ".$total_signup_page_sign_up."\n";
        $message .=  "-------------------\n\n";
    
    }


}


$thenux_params["tag"] = "Daily Signup Summary";
$thenux_params["message"] = $message;
$thenux_params["mobile_list"] = $action_tracking_number;
$thenux_result = $general->send_thenux_notification($thenux_params, "thenux_pay_marketing");




?>