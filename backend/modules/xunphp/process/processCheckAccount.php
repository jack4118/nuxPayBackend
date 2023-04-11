<?php

    $currentPath = __DIR__;
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.general.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.webservice.php');
    include($currentPath.'/../include/class.post.php');
    include($currentPath.'/../include/class.log.php');
    include($currentPath.'/../include/class.binance.php'); 

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $webservice  = new Webservice($db, "", "");
    $setting = new Setting($db);
    $general = new General($db, $setting);
    $post = new Post($db, $webservice, $msgpack);
    $binance = new Binance(
        $config['binanceAPIKey'],
        $config['binanceAPISecret'],
        $config['binanceAPIURL'], //"https://api.binance.com/api/v3/",
        $config['binanceWAPIURL'] //"https://api.binance.com/wapi/v3/"
    );

    //$logPath = $currentPath.'/../log/';
    //$logBaseName = basename(__FILE__, '.php');
    //$log = new Log($logPath, $logBaseName);

    $accountInfo = $binance->getAccountInfo();
    echo "Account Info:\n";
    print_r($accountInfo);

    foreach ($accountInfo['balances'] as $balanceRow) {

        //$balanceArray[$balanceRow['asset']] = array('free' => $balanceRow['free'], 'locked' => $balanceRow['locked']);
        echo $balanceRow['asset']."\n";
        echo "Free: ".$balanceRow['free'].", Locked: ".$balanceRow['locked']."\n\n";

    }

    // echo "ETH Info\n";
    // print_r($balanceArray['ETH']);

    // echo "USDT Info:\n";
    // print_r($balanceArray['USDT']);


    
?>