<?php

    include_once('../include/config.php');
    include_once('../include/class.database.php');

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    $crypto_history_arr = $db->get("xun_crypto_history");
    
    foreach ($crypto_history_arr as $crypto_history){
        $amount = $crypto_history["amount"];
        $amount_receive = $crypto_history["amount_receive"];
        $transaction_fee = $crypto_history["transaction_fee"];
        $miner_fee = $crypto_history["miner_fee"];

        $amount_arr = explode(" ", $amount);
        $amount_receive_arr = explode(" ", $amount_receive);
        $transaction_fee_arr = explode(" ", $transaction_fee);
        $miner_fee_arr = explode(" ", $miner_fee);

        $new_amount = $amount_arr[0];
        $new_amount_receive = $amount_receive_arr[0];
        $new_transaction_fee = $transaction_fee_arr[0];
        $new_miner_fee = $miner_fee_arr[0];

        print_r($crypto_history);
        echo "\n amount $amount new_amount $new_amount";
        echo "\n amount_receive $amount_receive new_amount_receive $new_amount_receive";
        echo "\n transaction_fee $transaction_fee new_transaction_fee $new_transaction_fee";
        echo "\n miner_fee $miner_fee new_miner_fee $new_miner_fee";

        $update_data = [];
        $update_data["amount"] = $new_amount;
        $update_data["amount_receive"] = $new_amount_receive;
        $update_data["transaction_fee"] = $new_transaction_fee;
        $update_data["miner_fee"] = $new_miner_fee;

        $db->where("id", $crypto_history["id"]);
        $db->update("xun_crypto_history", $update_data);
    }

?>
