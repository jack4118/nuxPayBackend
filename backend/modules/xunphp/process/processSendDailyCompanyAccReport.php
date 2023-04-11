<?php

    $currentPath = __DIR__;
    include_once $currentPath . "/../include/config.php";
    include_once $currentPath . "/../include/class.database.php";
    include_once $currentPath . "/../include/class.setting.php";
    include_once $currentPath . "/../include/class.general.php";
    include_once $currentPath . "/../include/class.post.php";
    include_once $currentPath . "/../include/class.xun_xmpp.php";
    include_once $currentPath . "/../include/class.xun_crypto.php";

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $general = new General($db, $setting);
    $post = new post();
    $xunXmpp = new XunXmpp($db, $post);
    $xunCrypto = new XunCrypto($db, $post, $general);

    $date = date("Y-m-d H:i:s");

    $company_acc_wallet_address = $setting->systemSetting["marketplaceCompanyAccWalletAddress"];

    $wallet_info = $xunCrypto->get_wallet_info($company_acc_wallet_address);

    $msg = $date;
    $msg .= "\nCompany Account Balance\n\n";

    foreach($wallet_info as $wallet_type => $data){
        $unit_conversion = $data["unitConversion"];
        $balance = $data["balance"];

        $log10 = log10($unit_conversion);
        $balance_decimal = bcdiv((string)$balance, (string)$unit_conversion, $log10);

        $msg .= "$wallet_type | $balance_decimal\n";
    }

    $params["tag"] = "Daily Commission Report";
    $params["message"] = $msg;
    $params["api_key"] = $config["thenux_wallet_transaction_API"];
    $params["business_id"] = $config["thenux_wallet_transaction_bID"];
    $params["mobile_list"] = $xun_numbers;
    // $params["mobile_list"] = array("+60124466833");
    $url_string = $config["broadcast_url_string"];
    $result = $post->curl_post($url_string, $params, 0);
    return;
?>
