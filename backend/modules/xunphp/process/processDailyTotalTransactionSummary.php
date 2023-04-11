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

$startOfYesterday = date("Y-m-d 00:00:00", strtotime('-1 day'));
$endOfYesterday = date("Y-m-d 23:59:59", strtotime('-1 day'));

$db->where('status', 'success');
$db->where('created_at', $startOfYesterday, '>=');
$db->where('created_at', $endOfYesterday, '<=');
$copyDb = $db->copy();
$total_send_fund = $db->getValue('xun_payment_gateway_send_fund', 'count(id)');

$copyDb->where('status', array('failed', 'pending'), 'NOT IN');
$total_receive_fund = $copyDb->getValue('xun_payment_gateway_invoice_detail', 'count(id)');


$message =  date("Y-m-d", strtotime('-1 days'))."\n";
$message .= "-------------------\n";
$message .= "Total Receive Fund: ".$total_receive_fund."\n";
$message .= "Total Send Fund: ".$total_send_fund."\n"; 
$message .= "-------------------\n\n";

$thenux_params["tag"] = "Daily Total Transaction";
$thenux_params["message"] = $message;
$thenux_params["mobile_list"] = $action_tracking_number;
$thenux_result = $general->send_thenux_notification($thenux_params, "thenux_pay_marketing");


$db->groupBy('b.marketer_id, a.wallet_type');
$db->where('a.created_at', $startOfYesterday, '>=');
$db->where('a.created_at', $endOfYesterday, '<=');
$db->where('a.type', 'fund_in');
// $db->where('a.status', 'success');
$db->join('xun_business_marketer_commission_scheme b', 'a.business_id = b.business_id AND a.wallet_type = b.wallet_type COLLATE utf8_general_ci', 'INNER');
$db->join('xun_marketer c', 'b.marketer_id = c.id', 'INNER');
$reseller_fund_in_transaction = $db->get('xun_payment_gateway_fund_in a', null, 'b.marketer_id,c.name, a.wallet_type, sum(a.amount_receive) as total_amount, sum(a.transaction_fee) as total_transaction_fee');

$db->groupBy('b.marketer_id, a.wallet_type');
$db->where('a.transaction_type', 'api_integration');
$db->where('a.created_at', $startOfYesterday, '>=');
$db->where('a.created_at', $endOfYesterday, '<=');
$db->where('a.status', 'success');
$db->join('xun_business_marketer_commission_scheme b', 'a.business_id = b.business_id AND a.wallet_type = b.wallet_type COLLATE utf8_general_ci', 'INNER');
$db->join('xun_marketer c', 'b.marketer_id = c.id', 'INNER');
$reseller_fund_out_transaction = $db->get('xun_payment_gateway_withdrawal a', null, 'b.marketer_id,c.name, a.wallet_type, sum(a.amount_receive) as total_amount, sum(a.transaction_fee) as total_transaction_fee');


$yesterday = date("Y-m-d", strtotime('-1 days'));
$message = "";
$message =  "$yesterday\n";
$message .= "PG Fund In/Out\n\n";

if($reseller_fund_in_transaction){
    foreach($reseller_fund_in_transaction as $key => $value){
        $marketer_id = $value['marketer_id'];
        $wallet_type = $value['wallet_type'];
        $name = $value['name'];

        $fund_in_data = array(
            "total_fund_in_amount" => $value['total_amount']
        );

        $pg_transaction_list[$marketer_id]['name'] = $name;
        $pg_transaction_list[$marketer_id]['wallet_type'][$wallet_type] = $fund_in_data;
    }

   
}

if($reseller_fund_out_transaction){
    foreach($reseller_fund_out_transaction as $key => $value){
        $marketer_id = $value['marketer_id'];
        $wallet_type = $value['wallet_type'];
        $name = $value['name'];
        

        $tx_arr = $pg_transaction_list[$marketer_id]['wallet_type'][$wallet_type];

        if($tx_arr){
            $tx_arr['total_fund_out_amount'] = $value['total_amount'];
            $tx_arr['total_transaction_fee'] = $value['total_transaction_fee'];
        }
        else{
            $tx_arr = array(
                "total_fund_out_amount" => $value['total_amount'],
                "total_transaction_fee" => $value['total_transaction_fee']
            );
        }
     
        $pg_transaction_list[$marketer_id]['name'] = $name;
        $pg_transaction_list[$marketer_id]['wallet_type'][$wallet_type] = $tx_arr;
    }

}

if($pg_transaction_list){
    foreach($pg_transaction_list as $key => $value){
   
        $marketer_name = $value['name'];
        $wallet_type_list = $value['wallet_type'];
        $message .= "$marketer_name\n";
        $message .= "=======================\n";
 
        foreach($wallet_type_list as $k1 => $v1){
     
            $total_fund_in = $v1['total_fund_in_amount'] ? $v1['total_fund_in_amount'] : '0';
            $total_fund_out = $v1['total_fund_out_amount'] ? $v1['total_fund_out_amount'] : '0';

            $total_transaction_fee = $v1['total_transaction_fee'] ? $v1['total_transaction_fee'] : '0';
            $wallet_type = $k1;
           
            $message .= "Wallet Type: $wallet_type\n";
            $message .= "Total Fund In Amount: $total_fund_in\n";
            $message .= "Total Fund Out Amount: $total_fund_out\n";
            $message .= "Total Service Charge: $total_transaction_fee\n\n";
          
        }
        $message .= "=======================\n\n";
    }
}
else{
    $message .= "\nNo Fund In Transaction\n";

}

$db->groupBy('b.marketer_id, a.wallet_type');
$db->where('a.created_at', $startOfYesterday, '>=');
$db->where('a.created_at', $endOfYesterday, '<=');
$db->where('a.status', 'confirmed');
$db->join('xun_business_marketer_commission_scheme b', 'a.business_id = b.business_id AND a.wallet_type = b.wallet_type COLLATE utf8_general_ci', 'INNER');
$db->join('xun_marketer c', 'b.marketer_id = c.id', 'INNER');
$reseller_auto_fund_out_tx = $db->get('xun_crypto_fund_out_details a', null, 'b.marketer_id,c.name, a.wallet_type, sum(a.amount) as total_amount, sum(a.service_charge_amount) as total_transaction_fee');


$message .= "\nAuto Fund Out \n\n";
if($reseller_auto_fund_out_tx){
    foreach($reseller_auto_fund_out_tx as $key => $value){
        $marketer_id = $value['marketer_id'];
        $wallet_type = $value['wallet_type'];
        $name = $value['name'];

        $fund_out_transaction_list[$marketer_id]['name'] = $name;
        $fund_out_transaction_list[$marketer_id]['wallet_type'][] = $value;
    }

    foreach($fund_out_transaction_list as $fund_out_key => $fund_out_value){
   
        $marketer_name = $fund_out_value['name'];
        $wallet_type_list = $fund_out_value['wallet_type'];
        $message .= "$marketer_name\n";
        $message .= "=======================\n";
 
        foreach($wallet_type_list as $k1 => $v1){
     
            $total_amount = $v1['total_amount'];
            $total_transaction_fee = $v1['total_transaction_fee'];
            $wallet_type = $v1['wallet_type'];
           
            $message .= "Wallet Type: $wallet_type\n";
            $message .= "Total Amount: $total_amount\n";
            $message .= "Total Service Charge: $total_transaction_fee\n\n";
          
        }
        $message .= "=======================\n\n";
    }
}
else{
    $message .= "\nNo Fund Out Transaction\n";

}

$thenux_params["tag"] = "Daily Reseller Transaction";
$thenux_params["message"] = $message;
$thenux_params["mobile_list"] = $action_tracking_number;
$thenux_result = $general->send_thenux_notification($thenux_params, "thenux_pay_marketing");



?>