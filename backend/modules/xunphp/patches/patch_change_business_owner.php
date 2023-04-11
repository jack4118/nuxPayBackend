<?php

    include_once('../include/config.php');
    include_once('../include/class.database.php');
    include_once('../include/class.general.php');
    include_once('../include/class.setting.php');
    include_once('../include/class.post.php');
    include_once('../include/class.xun_business.php');
    include_once('../include/class.xun_email.php');
    include_once('../include/class.xun_xmpp.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting       = new Setting($db);
    $general       = new General($db, $setting);
    $post          = new post();
    $xunEmail      = new XunEmail($db, $post);
    $xunXmpp       = new XunXmpp($db, $post);
    $xunBusiness   = new XunBusiness($db, $post, $general, $xunEmail);

    $params = array(
        "business_id" => "15212",
        "new_owner_username" => '+60123456780'
    );

    $res = $xunBusiness->change_business_owner($params);
    print_r($res);
?>
