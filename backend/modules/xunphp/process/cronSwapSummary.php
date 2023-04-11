<?php
    
    /**
     * Script to notify a summary of swap transactions
     */

    $currentPath = __DIR__;
    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include_once $currentPath . "/../include/config.php";
    include_once $currentPath . "/../include/class.database.php";
    include_once $currentPath . "/../include/class.setting.php";
    include_once $currentPath . "/../include/class.general.php";
    include_once $currentPath . "/../include/class.post.php";
    include_once $currentPath . "/../include/class.provider.php";
    include_once $currentPath . "/../include/class.message.php";
    include_once $currentPath . "/../include/class.log.php";
    include_once $currentPath . "/../include/class.binance.php"; 
    include_once $currentPath . "/../include/class.xun_crypto.php";
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $general = new General($db, $setting);
    $log = new Log($logPath, $logBaseName);
    $provider = new Provider($db);
    $post = new post();
    $message = new Message($db, $general, $provider);
    $xunCrypto = new XunCrypto($db, $post, $general);
    $binance = new Binance(
        $config['swapcoins']['binanceAPIKey'],
        $config['swapcoins']['binanceAPISecret'],
        $config['swapcoins']['binanceAPIURL'], //"https://api.binance.com/api/v3/",
        $config['swapcoins']['binanceWAPIURL'] //"https://api.binance.com/wapi/v3/"
    );
    
    if ($argv[1]) {
        // If a closing date is pass as argument, use the bonus date
        list($y, $m, $d) = explode("-", $argv[1]);
        if(checkdate($m, $d, $y)){
            $processDate = $argv[1];
        }
    }

    if (!$processDate) $processDate = date("Y-m-d", strtotime("-1 day"));
    
    // Check for Swap Transactions
    $db->where('created_at', $processDate." 00:00:00", ">=");
    $db->where('created_at', $processDate." 23:59:59", "<=");
    $swapHistoryRes = $db->get('xun_swap_history');
    
    //print_r($swapHistoryRes);

    foreach ($swapHistoryRes as $swapHistoryRow) {

        $pair = $swapHistoryRow['from_symbol']." - ".$swapHistoryRow['to_symbol'];

        if ($swapHistoryRow['status'] == "completed") {
            $swapRecords[$pair]['completed']['totalCount']++;
            $swapRecords[$pair]['completed']['fromAmount'] += $swapHistoryRow['from_amount'];
            $swapRecords[$pair]['completed']['toAmount'] += $swapHistoryRow['to_amount'];
            $swapRecords[$pair]['completed']['fromSymbol'] = $swapHistoryRow['from_symbol'];
            $swapRecords[$pair]['completed']['toSymbol'] = $swapHistoryRow['to_symbol'];
            $swapRecords[$pair]['completed']['profit'] += $swapHistoryRow['profit'];
            $swapRecords[$pair]['completed']['profitUSD'] += $swapHistoryRow['profit_usd'];
        }
        else {
            $swapRecords[$pair]['pending']['totalCount']++;
            $swapRecords[$pair]['pending']['fromAmount'] += $swapHistoryRow['from_amount'];
            $swapRecords[$pair]['pending']['toAmount'] += $swapHistoryRow['to_amount'];
            $swapRecords[$pair]['pending']['fromSymbol'] = $swapHistoryRow['from_symbol'];
            $swapRecords[$pair]['pending']['toSymbol'] = $swapHistoryRow['to_symbol'];
            $swapRecords[$pair]['pending']['profit'] += $swapHistoryRow['profit'];
            $swapRecords[$pair]['pending']['profitUSD'] += $swapHistoryRow['profit_usd'];

        }

    }

    print_r($swapRecords);

    $content .= "Date ".date("d/m/Y", strtotime($processDate))."\n\n";
    
    // Fetch Binance account balance for Swap
    $accountInfo = $binance->getAccountInfo();
    foreach ($accountInfo['balances'] as $balanceRow) {
        //$balanceArray[$balanceRow['asset']] = array('free' => $balanceRow['free'], 'locked' => $balanceRow['locked']);
        //echo $balanceRow['asset']."\n";
        //echo "Free: ".$balanceRow['free'].", Locked: ".$balanceRow['locked']."\n\n";
        $walletBalance[$balanceRow['asset']]['free'] = $balanceRow['free'];
        $walletBalance[$balanceRow['asset']]['locked'] = $balanceRow['locked'];

    }

    $db->where('disabled', 0);
    $db->groupBy('from_symbol');
    $swapSettingRes = $db->get('xun_swap_setting');
    foreach ($swapSettingRes as $swapSettingRow) {
        $walletTypeArray[$swapSettingRow['from_wallet_type']] = $swapSettingRow['from_symbol'];
    }

    $content .= "===== External Balances =====\n";

    print_r($walletTypeArray);

    foreach ($walletTypeArray as $walletType => $symbol) {

        $content .= "Free: ".number_format($walletBalance[$symbol]['free'], 6, ".", ",")." $symbol\n";
        $content .= "Locked: ".number_format($walletBalance[$symbol]['locked'], 6, ".", ",")." $symbol\n";

    }

    $content .= "\n";

    // Retrieve internal balance from Blockchain
    $content .= "===== Internal Balances =====\n";

    //print_r($walletTypeArray);

    $db->where('name', "swapInternalAddress");
    $swapInternalAddress = $db->getValue('system_settings', "value");

    foreach ($walletTypeArray as $walletType => $symbol) {

        $db->where('currency_id', strtolower($walletType));
        $unitConversion = $db->getValue('xun_marketplace_currencies', "unit_conversion");

        $walletInfo = $xunCrypto->get_wallet_info($swapInternalAddress, $walletType);
        $balance = bcdiv((string)$walletInfo[strtolower($walletType)]['balance'], $unitConversion, 6);

        $content .= number_format($balance, 6, ".", ",")." $symbol\n";

    }

    $content .= "\n";

    if (count($swapRecords) > 0) {

        foreach ($swapRecords as $pair => $swapData) {

            $content .= "===== ($pair) =====\n";

            foreach ($swapData as $status => $swapRow) {

                $content .= "Status: $status\n";
                $content .= "Total Transactions: ".number_format($swapRow['totalCount'], 0, "", ",")."\n";
                $content .= "Total From: ".number_format($swapRow['fromAmount'], 6, ".", ",")." ".$swapRow['fromSymbol']."\n";
                $content .= "Total To: ".number_format($swapRow['toAmount'], 6, ".", ",")." ".$swapRow['toSymbol']."\n";
                $content .= "Total Profit: ".number_format($swapRow['profitUSD'], 2, ".", ",")." USD\n";
                $content .= "\n";

            }

        }

    }
    else {

        $content .= "No swap transactions\n";

    }

    echo $content;

    $thenux_params["tag"] = "Swapcoins Summary";
    $thenux_params["message"] = $content;
    $thenux_params["mobile_list"] = $xun_numbers;
    $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_pay");

    
?>
