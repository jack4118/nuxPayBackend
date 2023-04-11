<?php

    include_once '../include/config.php';
    include_once '../include/class.database.php';
    include_once '../include/class.post.php';
    include_once '../include/class.xun_currency.php';
    include_once '../include/class.general.php';
    include_once '../include/class.setting.php';
    include_once '../include/class.log.php';
    include_once '../include/class.xun_company_wallet.php';

    $process_id = getmypid();

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $post = new post();
    $xunCurrency   = new XunCurrency($db);
    $setting = new Setting($db);
    $general = new General($db, $setting);
    $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);

    $logPath =  '../log/';
    $logBaseName = basename(__FILE__, '.php');
    $log = new Log($logPath, $logBaseName);
    // echo "\n starting process";
    $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Start process Add User Internal Address\n");

    $db->orderBy('id', DESC);
    $xun_user = $db->get('xun_user', null, 'id');

    $user_id_arr = array_column($xun_user, 'id');

    // $user_id_arr = array_values($user_id_arr);

    $db->where('address_type', 'nuxpay_wallet');
    $db->where('user_id', $user_id_arr, 'IN');
    $xun_crypto_user_address = $db->get('xun_crypto_user_address', null, 'user_id, address');
    //xprint_r($db->getLastQuery());


    $crypto_user_id_arr = array_column($xun_crypto_user_address, 'user_id');

    $arr_diff = array_diff($user_id_arr,$crypto_user_id_arr);
    print_r($arr_diff);
    $wallet_return = $xunCompanyWallet->createUserServerWallet($user_id, 'nuxpay_wallet', '');
    foreach($arr_diff as $key => $value){
   
        $user_id = $value;

        $wallet_return = $xunCompanyWallet->createUserServerWallet($user_id, 'nuxpay_wallet', '');

        $internal_address = $wallet_return['data']['address'];

        $insertAddress = array(
            "address" => $internal_address,
            "user_id" => $user_id,
            "address_type" => 'nuxpay_wallet',
            "active" => '1',
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $inserted = $db->insert('xun_crypto_user_address', $insertAddress);

        if(!$inserted){
            $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t Insert Address Error UserID: $user_id Error: ".$db->getLastQuery()."\n");
        }

        $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t User ID : $user_id Address: $internal_address \n");
    }




    $log->write(date('Y-m-d H:i:s') . " PID: ". $process_id ." \t End process Add User Internal Address\n");

?>