<?php

    include_once('../include/config.php');
    include_once('../include/class.database.php');
    include_once('../include/class.setting.php');
    include_once('../include/class.post.php');
    include_once('../include/class.xun_sms.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $post          = new post();

    $xunSms        = new XunSms($db, $post);

    $prefix = $setting->systemSetting["smsVerificationPrefix"] . ": ";

    $list = array(
        array("phone" => "14084066195", "code" => "60801"),
        array("phone" => "14084766514", "code" => "48263"),
        array("phone" => "989203303021", "code" => "59496"),
    );

    foreach($list as $data){
        $message = $prefix . $data["code"];
    
        $newParams["recipients"] = $data["phone"];
        $newParams["message"] = $message;
        $sms_result = $xunSms->send_sms($newParams);
    }
?>
