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
include_once  $currentPath . "/../include/class.xun_livechat_model.php";
include_once  $currentPath . "/../include/class.xun_wallet_transaction_model.php";
include_once  $currentPath . "/../include/class.xun_group_chat.php";
include_once  $currentPath . "/../include/class.xun_payment_gateway_model.php";
include_once  $currentPath . "/../include/class.xun_payment_gateway_service.php";
include_once  $currentPath . "/../include/class.xun_payment_gateway.php";


$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

$post = new post();
$setting = new Setting($db);
$general = new General($db, $setting);
$xunCrypto = new XunCrypto($db, $post, $general);
$xunCurrency   = new XunCurrency($db);
$xunPaymentGateway = new XunPaymentGateway($db, $post, $general, $setting, $xunCrypto, $xunCoins);


$logPath =  '../log/';
$logBaseName = basename(__FILE__, '.php');
$log = new Log($logPath, $logBaseName);

$setting_type = $argv[1];
echo "setting type:".$setting_type."\n";

if($setting_type != 'showWallet' && $setting_type != 'showNuxpayWallet'){
    $log->write(date('Y-m-d H:i:s') . " \t ERROR: Invalid Setting Type\n");
    exit();
}


$db->where('type', 'business');
$xun_user = $db->get('xun_user', null, 'id, username, nickname');

$db->where('name', $setting_type);
$user_setting_data = $db->map('user_id')->ArrayBuilder()->get('xun_user_setting', null, 'user_id, name, value');

print_r($user_setting_data);

$db->where("a.is_default", 1);
$default_coin = $db->get('xun_coins a', null, 'a.currency_id');

$default_coin_list = array_column($default_coin,'currency_id');

if($xun_user){
    foreach($xun_user as $key => $value){
        $user_id = $value['id'];
    
        $log->write(date('Y-m-d H:i:s') . " \t Processing User ID: $user_id \n");
    
        if($user_setting_data[$user_id]){
            $showWalletArr = json_decode($user_setting_data[$user_id]['value']);
    
            $updatedShowWalletArr = array_unique(array_merge($showWalletArr, $default_coin_list));
            
            $updatedShowWalletArr = array_values($updatedShowWalletArr);
    
            $updateSetting = array(
                "value" => json_encode($updatedShowWalletArr),
                "updated_at" => date("Y-m-d H:i:s")
            );
    
            $db->where('user_id', $user_id);
            $db->where('name', $setting_type);
            $updated = $db->update('xun_user_setting', $updateSetting);
    
            if(!$updated){
                $log->write(date('Y-m-d H:i:s') . " \t ERROR: User ID: $user_id  NOT UPDATED: ".$db->getLastQuery()."\n");
            }
    
        }
        else{
            $insertDefaultCoinList = array_values($default_coin_list);
            $insertWallet = array(
                "user_id"=> $user_id,
                "name" => $setting_type,
                "value" => json_encode($insertDefaultCoinList),
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            );
    
            $inserted = $db->insert('xun_user_setting', $insertWallet);
    
            if(!$inserted){
                $log->write(date('Y-m-d H:i:s') . " \t ERROR: User ID: $user_id  NOT INSERTED: ".$db->getLastQuery()."\n");
            }
        }
    
        $log->write(date('Y-m-d H:i:s') . " \t Done Process User ID: $user_id \n");
    
    
    }
}
else{
    $log->write(date('Y-m-d H:i:s') . " \t No user found!!! \n");

}


?>