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
    $currencyDecimalArr;
    $usdDecimalPlaces = 8;

    // ======== Begin Processing ========
    $crypto_summary_arr = $db->get('xun_crypto_history_summary');
    
    // If not empty, get process yesterday's transactions
    if (!empty($crypto_summary_arr)){
        // $db->orderBy('last_processed_date', 'Desc');
        // $processDate = $db->getValue('xun_crypto_history_summary', 'last_processed_date');
        
        // $processDate = date('Y-m-d 00:00:00', strtotime($processDate . ' +1 days'));
        // $endProcessDate = date( 'Y-m-d 23:59:59', strtotime($processDate));

        $processDate = date('Y-m-d 00:00:00', strtotime('yesterday'));
        $endProcessDate = date( 'Y-m-d 23:59:59', strtotime('yesterday'));

        $db->where('created_at', $processDate, '>=');
        $db->where('created_at', $endProcessDate, '<=');
    }
    
    $db->where('status', array('success', 'received', 'pending'), 'IN');
    $crypto_history_arr = $db->get('xun_crypto_history');

    foreach ($crypto_history_arr as $key => $crypto_history){
        $business_id = $crypto_history['business_id'];
        $wallet_type = $crypto_history['wallet_type'];
        $tx_fee_wallet_type = $crypto_history['tx_fee_wallet_type'];
        $actual_miner_fee_wallet_type = $crypto_history['actual_miner_fee_wallet_type'];
        $usdRate = $crypto_history['exchange_rate'];

        // get decimal places for currencies
        if (empty($currencyDecimalArr[$wallet_type])){
            $currencyDecimalArr[$wallet_type] = $xunCurrency->get_currency_decimal_places($wallet_type);
        }
        if (empty($currencyDecimalArr[$tx_fee_wallet_type])){
            $currencyDecimalArr[$tx_fee_wallet_type] = $xunCurrency->get_currency_decimal_places($tx_fee_wallet_type);
        }
        if (empty($currencyDecimalArr[$actual_miner_fee_wallet_type])){
            $currencyDecimalArr[$actual_miner_fee_wallet_type] = $xunCurrency->get_currency_decimal_places($actual_miner_fee_wallet_type);
        }

        unset($summary_exists);

        // Check if entry for business id and wallet type exists
        $db->where('business_id', $business_id);
        $db->where('wallet_type', $wallet_type);
        $db->where('transaction_date', date("Y-m-d", strtotime($crypto_history["created_at"])));
        $copyDb = $db->copy();
        $prev_summary = $db->getOne('xun_crypto_history_summary');

        if ($prev_summary){
            // update entry

            $updateData = array(
                "total_transaction" => $prev_summary['total_transaction'] + 1,
                "total_amount" => bcadd($prev_summary['total_amount'], $crypto_history['amount'], $currencyDecimalArr[$wallet_type]),
                "total_amount_usd" => bcadd($prev_summary['total_amount_usd'], bcmul($crypto_history['amount'], $usdRate, 2), 2),
                "total_amount_receive" => bcadd($prev_summary['total_amount_receive'], $crypto_history['amount_receive'], $currencyDecimalArr[$wallet_type]),
                "total_amount_receive_usd" => bcadd($prev_summary['total_amount_receive_usd'], bcmul($crypto_history['amount_receive'], $usdRate, 2), 2),
                "total_transaction_fee" => bcadd($prev_summary['total_transaction_fee'], $crypto_history['transaction_fee'], $currencyDecimalArr[$tx_fee_wallet_type]),
                "total_transaction_fee_usd" => bcadd($prev_summary['total_transaction_fee_usd'], bcmul($crypto_history['transaction_fee'], $usdRate, 2), 2),
                "total_miner_fee" => bcadd($prev_summary['total_miner_fee'], $crypto_history['miner_fee'], $currencyDecimalArr[$wallet_type]),
                "total_miner_fee_usd" => bcadd($prev_summary['total_miner_fee_usd'], bcmul($crypto_history['miner_fee'], $usdRate, $usdDecimalPlaces), $usdDecimalPlaces),
                "total_actual_miner_fee" => bcadd($prev_summary['total_actual_miner_fee'], $crypto_history['actual_miner_fee_amount'], $currencyDecimalArr[$actual_miner_fee_wallet_type]),
                "total_actual_miner_fee_usd" => bcadd($prev_summary['total_actual_miner_fee_usd'], bcmul($crypto_history['actual_miner_fee_amount'], $crypto_history['miner_fee_exchange_rate'], $usdDecimalPlaces), $usdDecimalPlaces),
                "last_processed_date" => $crypto_history["created_at"]

            );

            $copyDb->update('xun_crypto_history_summary', $updateData);

        } else {
            // create entry
            
            $insertData = array(
                "business_id" => $business_id,
                "wallet_type" => $wallet_type,
                "total_transaction" => '1',
                "total_amount" => $crypto_history['amount'],
                "total_amount_usd" => bcmul($crypto_history['amount'], $usdRate, 2),
                "total_amount_receive" => $crypto_history['amount_receive'],
                "total_amount_receive_usd" => bcmul($crypto_history['amount_receive'], $usdRate, 2),
                "total_transaction_fee" => $crypto_history['transaction_fee'],
                "total_transaction_fee_usd" => bcmul($crypto_history['transaction_fee'], $usdRate, 2),
                "tx_fee_wallet_type" => $tx_fee_wallet_type,
                "total_miner_fee" => $crypto_history['miner_fee'],
                "total_miner_fee_usd" => bcmul($crypto_history['miner_fee'], $usdRate, $usdDecimalPlaces),
                "miner_fee_wallet_type" => $crypto_history['miner_fee_wallet_type'],
                "total_actual_miner_fee" => $crypto_history['actual_miner_fee_amount'],
                "total_actual_miner_fee_usd" => bcmul($crypto_history['actual_miner_fee_amount'], $crypto_history['miner_fee_exchange_rate'], $usdDecimalPlaces),
                "actual_miner_fee_wallet_type" => $actual_miner_fee_wallet_type,
                "created_at" => date("Y-m-d H:i:s"),
                "last_processed_date" => $crypto_history["created_at"],
                "transaction_date" => date("Y-m-d", strtotime($crypto_history["created_at"]))
            );

            $db->insert('xun_crypto_history_summary', $insertData);
        }

    }
?>