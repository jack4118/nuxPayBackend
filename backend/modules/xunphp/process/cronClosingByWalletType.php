<?php
    
    /**
     * Script to sum each and every client's balance at the given date and store the balance to be brought forward to the next day
     */

    $currentPath = __DIR__;
    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include_once $currentPath . "/../include/config.php";
    include_once $currentPath . "/../include/class.database.php";
    include_once $currentPath . "/../include/class.setting.php";
    include_once $currentPath . "/../include/class.general.php";
    include_once $currentPath . "/../include/class.account.php";
    include_once $currentPath . "/../include/class.provider.php";
    include_once $currentPath . "/../include/class.message.php";
    include_once $currentPath . "/../include/class.log.php";
    include_once $currentPath . "/../include/class.xun_crypto.php";
    include_once $currentPath . "/../include/class.post.php";
    include_once $currentPath . "/../include/class.xun_currency.php";

    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $post          = new post();
    $setting = new Setting($db);
    $general = new General($db, $setting);
    $log = new Log($logPath, $logBaseName);
    $provider = new Provider($db);
    $message = new Message($db, $general, $provider);
    $account = new Account($db, $setting, $message, $provider, $log);
    $xunCrypto     = new XunCrypto($db, $post, $general);
    $xunCurrency   = new XunCurrency($db);
 
    
    
    $marketplace_currencies = $xunCurrency->get_cryptocurrency_list('a.id, a.currency_id, a.symbol');

    $wallet_type_list = array_column($marketplace_currencies, 'currency_id');
    print_r($wallet_type_list);

    $closingPeriod = $setting->systemSetting['closingPeriod']? $setting->systemSetting['closingPeriod'] : 1;
    $closingDate = date("Y-m-d", strtotime("-$closingPeriod day"));

    $log->write(date("Y-m-d H:i:s")." Deleting closing date $closingDate onwards.\n");
            
    if ($account->deleteClosing($closingDate)) {
        $log->write(date("Y-m-d H:i:s")." Successfully deleted closing from $closingDate onwards.\n");
    }
    
    foreach($wallet_type_list as $wallet_type){
        echo "wallet type:".$wallet_type."\n";
        $process_name = "cronXunAccClosing.php";
        $cmd = "nohup php $currentPath/$process_name ".$wallet_type;
        $cmd .= " >> ".$currentPath."/../log/cronXunAccClosing_".$wallet_type.".log 2>&1 & echo $!;"; // echo $! to return pid
        $pid = exec($cmd, $output, $result);

        if ($result == 0) $log->write(date("Y-m-d H:i:s")." Success: $pid\n");
		else $log->write(date("Y-m-d H:i:s")." Failed to run process. cronXunAccClosing.php ".$wallet_type."\n");
    }

    
?>
