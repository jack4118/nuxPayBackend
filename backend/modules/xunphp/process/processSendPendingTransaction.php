<?php

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.post.php";
include_once $currentPath . "/../include/class.log.php";
include_once $currentPath . "/../include/class.xun_story.php";
include_once $currentPath . "/../include/class.xun_xmpp.php";
include_once $currentPath . "/../include/class.xun_currency.php";

include_once $currentPath . "/../include/class.abstract_xun_user.php";
include_once $currentPath . "/../include/class.xun_user_model.php";
include_once $currentPath . "/../include/class.xun_user_service.php";
include_once $currentPath . "/../include/class.xun_business_model.php";
include_once $currentPath . "/../include/class.xun_business_service.php";
include_once $currentPath . "/../include/class.xun_livechat_model.php";

$logPath = $currentPath . '/../log/';
$logBaseName = basename(__FILE__, '.php');
$path = realpath($logPath);

$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$setting = new Setting($db);
$general = new General($db, $setting);
$log = new Log($logPath, $logBaseName);
$post = new post();
$xunStory = new XunStory($db, $post, $general, $setting);
$xunXmpp = new XunXmpp($db, $post);
$xunCurrency = new XunCurrency($db);

try{
    $date = date("Y-m-d", strtotime('-7 days', strtotime(date("Y-m-d"))));
    $date2 = date("Y-m-d", strtotime('-14 days', strtotime(date("Y-m-d"))));

    $col = "a.id, a.user_id, a.story_id, b.story_transaction_id, a.value, a.currency_id, b.platform, b.payment_method_id, b.payment_method_type";
    $db->where('a.created_at', "%$date%", 'LIKE');
    $db->where('a.status', 'pending');
    $db->where('a.transaction_type', 'donation');
    $db->join('xun_story_donation b', 'a.id = b.story_transaction_id', 'LEFT');
    $tx_result1 = $db->get('xun_story_transaction a', null, $col);

    $db->where('a.created_at', "%$date2%", 'LIKE');
    $db->where('a.status', 'pending');
    $db->where('a.transaction_type', 'donation');
    $db->join('xun_story_donation b', 'a.id = b.story_transaction_id', 'LEFT');
    $tx_result2 = $db->get('xun_story_transaction a', null, $col);

    $story_id_arr = [];
    $user_id_arr = [];
    $user_pm_id_arr = [];
    if($tx_result1){
        foreach($tx_result1 as $tx1_key => $tx1_value){
            $user_id = $tx1_value['user_id'];
            $story_id = $tx1_value["story_id"];
            $user_pm_id = $tx1_value["payment_method_id"];
        
            if($user_id != 0){
                if (!in_array($user_id, $user_id_arr)) {
                    array_push($user_id_arr, $user_id);
                } 
            }
        
            if (!in_array($story_id, $story_id_arr)) {
                array_push($story_id_arr, $story_id);
            }
        
            if(!in_array($user_pm_id, $user_pm_id_arr)){
                array_push($user_pm_id_arr, $user_pm_id);
            }
        }
        
    }

    if($tx_result2){
        foreach($tx_result2 as $tx2_key=> $tx2_value){
            $user_id = $tx2_value["user_id"];
            $story_id = $tx2_value["story_id"];
            $user_pm_id = $tx2_value["payment_method_id"];

            if($user_id != 0){
                if (!in_array($user_id, $user_id_arr)) {
                    array_push($user_id_arr, $user_id);
                } 
            }

            if (!in_array($story_id, $story_id_arr)) {
                array_push($story_id_arr, $story_id);
            }

            if(!in_array($user_pm_id, $user_pm_id_arr)){
                array_push($user_pm_id_arr, $user_pm_id);
            }
            
        }
    }

    if($tx_result1 || $tx_result2){
        $db->where('story_id', $story_id_arr, 'IN');
        $db->where('story_type', 'story');
        $story_updates = $db->map('story_id')->ArrayBuilder()->get('xun_story_updates');

        $db->where('id', $user_id_arr, 'IN');
        $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user');

        $db->where('id', $user_pm_id_arr, 'IN');
        $story_pm = $db->map('id')->ArrayBuilder()->get('xun_story_payment_method');
        $pm_id_arr = [];
        foreach($story_pm as $key => $value){
            $pm_id = $value["payment_method_id"];

            if(!in_array($pm_id, $pm_id_arr)){
                array_push($pm_id_arr, $pm_id);
            }
        }
        $db->where('id', $pm_id_arr, 'IN');
        $marketplace_pm = $db->map('id')->ArrayBuilder()->get('xun_marketplace_payment_method');
    }

    if($tx_result1){
        foreach($tx_result1 as $key => $value){
            $story_id = $value['story_id'];
            $user_id = $value['user_id'];
            $amount = $value['value'];
            $currency_id =$value["currency_id"];

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
            $creditType = $decimal_place_setting["credit_type"];
            $amount = $setting->setDecimal($amount, $creditType);

            $uc_currency_name = $xunStory->get_fiat_currency_name($currency_id, 1);
            $display_amount = $amount ." ".$uc_currency_name;

            $platform = $value["platform"] ? $value["platform"] : 'App';
            //$payment_method =  $value["payment_method_type"] ? $value["payment_method_type"] : 'Cryptocurrency';
            $user_pm_id = $value["payment_method_id"];
            $pm_id = $story_pm[$user_pm_id]["payment_method_id"];
            $pm_name = $marketplace_pm[$pm_id]["name"] ? $marketplace_pm[$pm_id]["name"] : 'Cryptocurrency';

            $title = $story_updates[$story_id]["title"];

            $nickname = $xun_user[$user_id]['nickname'] ? $xun_user[$user_id]['nickname'] : 'Anonymous';
            $mobile =  $xun_user[$user_id]['username'] ? $xun_user[$user_id]['username'] : '';
            if($xun_user[$user_id]["register_site"] == 'nuxstory' || $xun_user[$user_id]['email']){
                $user_type = 'NuxStory';
            }elseif($user_id == 0){
                $user_type  = "";
            }
            else{
                $user_type = 'TheNux';
            }

            $device = get_user_device($user_id, $mobile);
            send_pending_tx_notification($nickname, $mobile, $device, $user_type, $platform, $title, $pm_name, $display_amount, '7');

        }
    }

    if($tx_result2){
        foreach($tx_result2 as $key => $value){
            $story_id = $value['story_id'];
            $user_id = $value['user_id'];
            $amount = $value['value'];
            $currency_id = $value['currency_id'];

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
            $creditType = $decimal_place_setting["credit_type"];
            $amount = $setting->setDecimal($amount, $creditType);

            $uc_currency_name = $xunStory->get_fiat_currency_name($currency_id, 1);
            $display_amount = $amount ." ".$uc_currency_name;
            $platform = $value['platform'] ? $value['platform'] : 'App';
            $user_pm_id = $value["payment_method_id"];
            $pm_id = $story_pm[$user_pm_id]["payment_method_id"];
            $pm_name = $marketplace_pm[$pm_id]["name"] ? $marketplace_pm[$pm_id]["name"] : 'Cryptocurrency';

            $title = $story_updates[$story_id]["title"];

            $nickname = $xun_user[$user_id]['nickname'] ? $xun_user[$user_id]['nickname'] : 'Anonymous';
            $mobile =  $xun_user[$user_id]['username'] ? $xun_user[$user_id]['username'] : '';
            if($xun_user[$user_id]["register_site"] == 'nuxstory' || $xun_user[$user_id]['email']){
                $user_type = 'NuxStory';
            }elseif($user_id == 0){
                $user_type  = "";
            }
            else{
                $user_type = 'TheNux';
            }

            $device = get_user_device($user_id, $mobile);
            send_pending_tx_notification($nickname, $mobile, $device, $user_type, $platform, $title, $pm_name, $display_amount, '14');
        }
    }
} catch (Exception $e) {
    $msg = $e->getMessage();

    $message = "processSendPendingTransaction\n";
    $message .= "Time : " . date("Y-m-d H:i:s");
    $message .= $msg;

    $erlang_params["tag"] = "Process Error";
    $erlang_params["message"] = $message;
    $erlang_params["mobile_list"] = ["+60124466833"];
    $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_story");
}

function send_pending_tx_notification($nickname, $mobile, $device, $user_type, $platform, $title, $payment_method, $amount, $day){
    global $xunStory;

    if($mobile == ''){
        $mobile = '-';
    }

    if($user_type == ''){
        $user_type = '-';
    }
    
    $message = "Username: ".$nickname."\n";
    $message .= "Phone number: ".$mobile."\n";
    $message .= "Device: ".$device."\n";
    $message .= "Type of User: ".$user_type."\n";
    $message .= "Platform: ".$platform."\n";
    $message .= "Title: ".$title."\n";
    $message .= "Payment Method: ".$payment_method."\n";
    $message .= "Amount: ".$amount."\n";
    $message .= "Total day: ".$day." days\n";
    $message .= "Time: ".date("Y-m-d H:i:s")."\n";

    $tag = "Pending Transaction";
    $xunStory->send_story_notification($tag, $message);
    
}

function get_user_device($user_id, $username = null){
    global $db, $xunStory;
    if($username){
        $user_device_info = $xunStory->get_user_device_info($username);
        if ($user_device_info) {
            $device_os = $user_device_info["os"];
            
            if($device_os == 1){
                $device = "Android";
            }
            else if ($device_os == 2){
                $device = "iOS";
            }
            
            return $device;
        }
        
    }

    if($user_id){
        $db->where('user_id', $user_id);
        $db->where('name', 'device');
        $user_setting = $db->getOne('xun_user_setting');

        if($user_setting){
            $device = $user_setting["value"];
        }
        else{
            $device = "-";
        }
   
    }else{
        $device = "-";
    }

    return $device;

    // if(4)
   
}



