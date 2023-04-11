<?php

	$currentPath = __DIR__;

    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.general.php');
    include($currentPath.'/../include/class.provider.php');
    include($currentPath.'/../include/class.message.php');
    include($currentPath.'/../include/class.webservice.php');
    include($currentPath.'/../include/class.msgpack.php');
    include($currentPath.'/../include/class.post.php');
    include($currentPath.'/../include/class.log.php');
    include($currentPath.'/../include/class.xun_currency.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $general = new General($db, $setting);
    $provider = new Provider($db);
    $message = new Message($db, $general, $provider);
    $webservice = new Webservice($db, $general, $message);
    $msgpack = new msgpack();
    $post = new Post($db, $webservice, $msgpack);
    $xunCurrency   = new XunCurrency($db);

    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    $log = new Log($logPath, $logBaseName);

    $userIDArray = $db->get('xun_user', null, 'id');
    
    foreach ($userIDArray as $key => $userDetail){
        $userID = $userDetail['id'];
        // Check if entry exists
        $db->where('name', 'hasChangedPassword');
        $db->where('user_id', $userID);
        $entryExists = $db->getOne('xun_user_setting');

        if (!$entryExists){
            $insertData = array(
                'user_id' => $userID,
                'name' => 'hasChangedPassword',
                'value' => '1',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            );

            $db->insert('xun_user_setting', $insertData);
        }
    }

?>