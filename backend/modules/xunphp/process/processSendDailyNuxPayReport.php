<?php

    $currentPath = __DIR__;
    include_once $currentPath . "/../include/config.php";
    include_once $currentPath . "/../include/class.database.php";
    include_once $currentPath . "/../include/class.setting.php";
    include_once $currentPath . "/../include/class.general.php";
    include_once $currentPath . "/../include/class.post.php";
    include_once $currentPath . "/../include/class.xun_xmpp.php";

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    $post          = new post();
    $setting	   = new Setting($db);
    $general       = new General($db, $setting);

    $date = date("Y-m-d H:i:s");
    $yesterday = date("Y-m-d H:i:s", strtotime('-1 days', strtotime($date)));

    $db->where('created_at', $yesterday, '>=');
    $db->where('created_at', $date, '<');
    $db->where("username<>''");
    $copyDb= $db->copy();
    $xun_user = $db->get('xun_user');

    $total_new_user = $copyDb->getValue('xun_user', 'count(id)');

    $db->where("username<>''");
    $total_overall_user = $db->getValue('xun_user', 'count(id)');

    $campaign_arr = [];
    foreach($xun_user as $key=> $value){
        $campaign_type = $value['register_through'];

        if(!$campaign_arr[$campaign_type]){
            $campaign_arr[$campaign_type]['user_amount'] = 1;
        }
        else{
            $total_user = $campaign_arr[$campaign_type]['user_amount'];
            $total_user = $total_user + 1;
            echo "total_user".$total_user."\n";
            $campaign_arr[$campaign_type]['user_amount'] = $total_user;
            
        }
         
    }


    $message .= "Users(New): ".$total_new_user."\n";
    $message .= "Users(Total): ".$total_overall_user."\n\n";
    $message .= "Campaign: \n";
    foreach($campaign_arr as $key => $value){
        $type = $key;
        $user_amount = $value['user_amount'];

        $message .= "[$type] - $user_amount\n";

    }

    $tag = "Daily Summary";
    $params["message"] = $message;
    $params["tag"] = $tag;
    $general->send_thenux_notification($params, "thenux_pay");
    
