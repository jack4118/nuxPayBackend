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
 
    
    // Get the closing period in days (Default 1 day)
    $closingPeriod = $setting->systemSetting['closingPeriod']? $setting->systemSetting['closingPeriod'] : 1;
    $closingDate = date("Y-m-d", strtotime("-$closingPeriod day"));
    
    if ($argv[1]) {
        // If a closing date is pass as argument, use the bonus date
        list($y, $m, $d) = explode("-", $argv[1]);
        if(checkdate($m, $d, $y)){
            $closingDate = $argv[1];
        }
    }
    
    // Call the closing function
    $account->closing($closingDate);
    
    
?>
