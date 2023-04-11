<?php
    
    /**
     * Script to summarize xun_crypto_history table
     */

    $currentPath = __DIR__;
    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include_once $currentPath . "/../include/config.php";
    include_once $currentPath . "/../include/class.database.php";
    include_once $currentPath . "/../include/class.log.php";
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $log = new Log($logPath, $logBaseName);

    // get date from argument if exist
    $startdate = '';
    if (!is_null($argv[1])) {
        list($y, $m, $d) = explode("-", $argv[1]);
        if(checkdate($m, $d, $y)){
            $startdate = $argv[1];
        } else {
            echo "Start Date ".$argv[1]." is not appropriate.\n";
            exit;
        }
    }

    if ($startdate == '') {
        $startdate = date('Y-m-d', strtotime("yesterday"));
    }

    $startTimestamp = $startdate." 00:00:00";
    $endTimestamp = $startdate." 23:59:59";
    $log->write(date('Y-m-d H:i:s') . " Message - cron xun user payments starts. StartDate: ". $startTimestamp. " EndDate: ".$endTimestamp ."\n");
    echo "Start Date: ".$startTimestamp."\n";
    echo "End Date: ".$endTimestamp."\n";

    // check if data exit, remove all if there is existing records
    $db->where('date', $startdate);
    if ($db->has('xun_user_payments_summary')) {
        $db->where('date', $startdate);
        $db->delete('xun_user_payments_summary');
        $log->write(date('Y-m-d H:i:s') . " Message - Records exist on ". $startdate. ". Deleting before reinsert new data.\n");
        echo "Records exist on ".$startdate.". Deleting...\n";
    }

    // start
    // Notes: all records will have its own exchange_rate at that specific time, therefore we have to 
    //        query all the data and convert it one by one, using SUM() is not approriate.
    //        the process will first get all the unique business_id together with unique credit type,
    //        and then convert it and sum up using correctly before insert into xun_user_payments_summary.
    //
    // Flows: 1. get unique business_id
    //        2. get unique wallet_type for every business_id
    //        3. query all the amount and exchange rate and calculate correctly
    //        4. build insertData and insert into xun_user_payments_summary
    
    // 1. get unique business_id
    $db->where('created_at', $startTimestamp, '>=');
    $db->where('created_at', $endTimestamp, '<=');
    $db->where('status', 'failed', '!=');
    $db->where('gw_type', 'PG');
    $allUsers = $db->getValue('xun_crypto_history', 'DISTINCT business_id', null);

    if (is_null($allUsers)) {
        $log->write(date('Y-m-d H:i:s') . " Message - There is no records on ". $startdate .".\n");
        echo "No records for ".$startdate.".\n";
        exit;
    }

    $log->write(date('Y-m-d H:i:s') . " Message - Number of users on ". $startdate . ": ". count($allUsers) ."\n");
    echo "Number of users on ".$startdate .": ".count($allUsers)."\n";

    foreach($allUsers as $user) {
        // 2. get unique wallet_type for this business_id
        $db->where('created_at', $startTimestamp, '>=');
        $db->where('created_at', $endTimestamp, '<=');
        $db->where('status', 'failed', '!=');
        $db->where('business_id', $user);
        $db->where('gw_type', 'PG');
        $allCreditTypes = $db->getValue('xun_crypto_history', 'DISTINCT wallet_type', null);

        foreach($allCreditTypes as $credit) {
            // 3. query all amount and exchange rate and calculate correctly
            $db->where('created_at', $startTimestamp, '>=');
            $db->where('created_at', $endTimestamp, '<=');
            $db->where('status', 'failed', '!=');
            $db->where('business_id', $user);
            $db->where('wallet_type', $credit);
            $db->where('gw_type', 'PG');
            $allCreditRecords = $db->get('xun_crypto_history', null, 'amount, amount_receive, exchange_rate');
            
            $totalAmount = "0";
            $totalAmountReceived = "0";
            $totalAmountUSD = "0";
            $totalAmountReceivedUSD = "0";
            foreach($allCreditRecords as $record) {
                $amountUSD = bcmul($record['amount'], (string)$record['exchange_rate'], 6);
                $amountReceivedUSD = bcmul($record['amount_receive'], (string)$record['exchange_rate'], 6);

                $totalAmount = bcadd($totalAmount, $record['amount'], 18);
                $totalAmountReceived = bcadd($totalAmountReceived, $record['amount_receive'], 18);
                $totalAmountUSD = bcadd($totalAmountUSD, $amountUSD, 6);
                $totalAmountReceivedUSD = bcadd($totalAmountReceivedUSD, $amountReceivedUSD, 6);
            }

            $log->write(date('Y-m-d H:i:s') . " Debug - ". $user . " (". $credit .") - Gross: ". $totalAmountReceivedUSD . " | Net: ". $totalAmountUSD."\n");
            echo $user . " (". $credit .") - Gross: ". $totalAmountReceivedUSD . " | Net: ". $totalAmountUSD."\n";

            // 4. build data and insert
            $insertData = array(
                'date' => $startdate,
                'user_id' => $user,
                'wallet_type' => $credit,
                'transaction_count' => count($allCreditRecords),
                'gross_profit' => $totalAmountReceived,
                'gross_profit_usd' => $totalAmountReceivedUSD,
                'net_profit' => $totalAmount,
                'net_profit_usd' => $totalAmountUSD,
                'created_at' => date('Y-m-d H:i:s')
            );
            $db->insert('xun_user_payments_summary', $insertData);
        }
    }

    $log->write(date('Y-m-d H:i:s') . " Message - Process end.\n\n");
    echo "End\n\n";
    
?>
