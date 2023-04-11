<?php

$currentPath = __DIR__;
include($currentPath.'/../include/config.php');
include($currentPath.'/../include/class.database.php');
include($currentPath.'/../include/class.general.php');
include($currentPath.'/../include/class.setting.php');
include($currentPath.'/../include/class.message.php');
include($currentPath.'/../include/class.webservice.php');
include($currentPath.'/../include/class.post.php');
include($currentPath.'/../include/class.provider.php');
include($currentPath.'/../include/class.account.php');
include($currentPath.'/../include/class.log.php');
include($currentPath.'/../include/class.binance.php');
include($currentPath.'/../include/class.xun_crypto.php');
include($currentPath.'/../include/class.abstract_xun_user.php');
include($currentPath.'/../include/class.xun_user_model.php');
include($currentPath.'/../include/class.xun_business_model.php');
include($currentPath.'/../include/class.xun_livechat_model.php');
include($currentPath.'/../include/class.xun_wallet_transaction_model.php');
include($currentPath.'/../include/class.xun_business_service.php');
include($currentPath.'/../include/class.xun_wallet.php');
include($currentPath.'/../include/class.xun_coins.php');
include($currentPath.'/../include/class.xun_swapcoins.php');
include($currentPath.'/../include/class.xun_payment_gateway.php');


$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$webservice  = new Webservice($db, "", "");
$setting = new Setting($db);
$general = new General($db, $setting);
$post = new Post($db, $webservice, $msgpack);
// $partner = new Partner($db, $post, $setting);
$binance = new Binance(
    $config['swapcoins']['binanceAPIKey'], 
    $config['swapcoins']['binanceAPISecret'], 
    $config['swapcoins']['binanceAPIURL'], 
    $config['swapcoins']['binanceWAPIURL']
);
$provider = new Provider($db);
$message = new Message($db, $general, $provider);
$xunCrypto = new XunCrypto($db, $post, $general);

$logPath     = $currentPath.'/../log/';
$logBaseName = basename(__FILE__, '.php');
$log         = new Log($logPath, $logBaseName);

$account = new Account($db, $setting, $message, $provider, $log);   
$xunCoins = new XunCoins($db, $setting);
$xunPaymentGateway = new XunPaymentGateway($db, $post, $general, $setting, $xunCrypto, $xunCoins);
$xunSwapcoins  = new XunSwapcoins($db, $general, $setting, $post, $binance, $account, $xunPaymentGateway);

$db->where('status', 'fail_swap');
$auto_swap_data = $db->get('xun_payment_gateway_withdrawal', null, 'business_id, wallet_type, amount, transaction_hash');

if($auto_swap_data){
    foreach($auto_swap_data as $key => $value){
        $business_id = $value['business_id'];
        $wallet_type = $value['wallet_type'];
        $amount = $value['amount'];
        $transaction_hash = $value['transaction_hash'];

        $db->where('user_id', $business_id);
        $db->where('name', 'toCurrency');
        $toCurrency = $db->getValue('xun_user_setting', 'value');

        if(!$toCurrency){
            $log->write("\n".date('Y-m-d')."Failed Swap Empty ToCurrency ");
        }

        $db->where('id', $business_id);
        $userSite = $db->getOne('xun_user', 'id, nickname, register_site');

        $source = $userSite["register_site"];
        $business_name = $userSite['nickname'];
        $swapData = array(
            'businessID' => $business_id,
            'fromWalletType' => $wallet_type,
            'toWalletType' => $toCurrency,
            'fromAmount' => $amount,
            'toAmount' => ''                            
        );

        $log->write("\n".date('Y-m-d')."swapData ".json_encode($swapData));
        $res = $xunSwapcoins->estimateSwapCoinRate($swapData, strtolower($source));
        $log->write("\n".date('Y-m-d')."estimateSwapCoinRate ".json_encode($res));
        if($res['status'] == 'ok') {
            $swapData2 = array(
                'referenceID' => $res['data']['referenceID']
            );

            $res = $xunSwapcoins->swap($swapData2, strtolower($source));
            $log->write("\n".date('Y-m-d')."Swap ".json_encode($res)."\n");
            
            if($res['status'] == 'ok'){
                $updateSwapStatus = array(
                    "status" => 'success'
                );
                
                $db->where('transaction_hash', $transaction_hash);
                $updated = $db->update('xun_payment_gateway_withdrawal', $updateSwapStatus);

                if(!$updated){
                    $log->write("\n".date('Y-m-d')."Failed Update Withdrawal Table: ".$db->getLastQuery());
                }

                $tag = "Success Auto Swap";

                $message = "Business Name: ".$business_name."\n";
                $message .= "TxID: ".$transaction_hash."\n";
                $message .= "Amount: ".$amount."\n";
                $message .= "Wallet Type: ".$wallet_type."\n";
                $message .= "Result: ".json_encode($res)."\n";
                $message .= "Created At: ".date("Y-m-d H:i:s")."\n";
    
                $notificationParams = array(
                    "tag"   => $tag,
                    "message" => $message
                );
    
                $general->send_thenux_notification($notificationParams, "thenux_issues");   
            }
            else{
                $tag = "Failed Auto Swap";

                $message = "Business Name: ".$business_name."\n";
                $message .= "TxID: ".$transaction_hash."\n";
                $message .= "Amount: ".$amount."\n";
                $message .= "Wallet Type: ".$wallet_type."\n";
                $message .= "Msg Return: ".json_encode($res)."\n";
                $message .= "Created At: ".date("Y-m-d H:i:s")."\n";
    
                $notificationParams = array(
                    "tag"   => $tag,
                    "message" => $message
                );
    
                $general->send_thenux_notification($notificationParams, "thenux_issues"); 
            }
           
        }
        else{
            $log->write("\n".date('Y-m-d')."Failed Swap: ".json_encode($res));
            
            $tag = "Failed Auto Swap";

            $message = "Business Name: ".$business_name."\n";
            $message .= "TxID: ".$transaction_hash."\n";
            $message .= "Amount: ".$amount."\n";
            $message .= "Wallet Type: ".$wallet_type."\n";
            $message .= "Msg Return Estimate Rate: ".json_encode($res)."\n";
            $message .= "Created At: ".date("Y-m-d H:i:s")."\n";

            $notificationParams = array(
                "tag"   => $tag,
                "message" => $message
            );

            $general->send_thenux_notification($notificationParams, "thenux_issues");   

        }
    }
}
else{
    $log->write("\n".date('Y-m-d')."No Auto Swap Record to Retrigger");
}

?>