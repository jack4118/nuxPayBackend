<?php
    
    $config = array(
                    'environment'       => "dev",

                    // Connectivity
                    'dBHost'			=> "127.0.0.1",
                    'dB'				=> "databasename",
                    'dBUser'			=> "username",
                    'dBPassword'		=> "password",
                    
                    
                    // Routes
                    'root'				=> realpath(dirname(__FILE__))."/../",
                    
                    // Erlang URL
                    'erlangUrl'         => "https://dev.xun.global:5281/xun/xmpp/",

                    'server'            => "dev.xun.global",

                    'xunMonitoring'     => "http://xunb.backend/modules/xun/xun_webservices.php",
        
                    //crypto
                    'cryptoUrl'         => "https://crypto.thenux.com/webservices.php",
                    'cryptoPartnerName' => "theNux",
                    'cryptoSite'        => "crypto.thenux.com",
                    'cryptoApiKey'      => "",
                    
                    'erlang_server'     => "dev.xun.global",
                    'webserviceURL'     => "https://dev.xun.global:5281/modules/xunphp/webservices.php",
                    'utm_tracking_sending_method' => 'business_chat',

                    // exchange
                    'escrowURL'                 => "dev.thenuxescrow.com/wallet_webservices.php",
                    'tradingFeeURL'             => "dev.thenuxtradingfee.com/wallet_webservices.php",
                    'companyPoolURL'            => "dev.thenuxcompanypool.com/wallet_webservices.php",
                    'freecoinURL'               => "dev.thenuxfreecoin.com/wallet_webservices.php",

                    'tradingFeeURL_walletTransaction'     => "",
                    'companyPoolURL_walletTransaction'     => "",
                    
                    //pricing
                    'pricingUrl' => "",

                    //API key and business id
                    "broadcast_url_string" => "https://dev.xun.global:5281/xun/business/broadcast",
                    "thenux_API" => "yvZuDCAt9iU0LQ5GYwdc3oI6R8jSWNF4",
                    "thenux_bID" => "15650",

                    "thenux_escrow_API" => "W5Qp0hS48BgVELjK1td9YuRAOCG3wcZJ",
                    "thenux_escrow_bID" => "15651",

                    "thenux_referral_and_master_dealer_API" => "uGzSNi6LvaUwp5lRKe7H4Cb9DXqdYjfI",
                    "thenux_referral_and_master_dealer_bID" => "15652",

                    "thenux_wallet_transaction_API" => "ZJpx0GkvDdCVn62gwuMzrS9cPN5qR7ys",
                    "thenux_wallet_transaction_bID" => "15653",

                    "thenux_xchange_API" => "oSdUNlFCIEQxn0L3cTYArfbJtVBp7haG",
                    "thenux_xchange_bID" => "15654",

                     //monitoring
                     'xunMonitorUrl' => "",

                     //notification
                     'notificationUrl' => "",
                     "notification_api_key" => "",
                     "notification_business_id" => "",

                     "binanceAPIKey"    => "",
                     "binanceAPISecret" => "",
                     "binanceAPIURL"    => "",
                     "binanceWAPIURL"   => "",
                );

    $xun_numbers = array(
                    "+60124466833",
                    "+601155090561",
                    "+60172530001",
                    "+60123583158",
                    "+60192135135",
                    "+60176063453",
                    "+601160532481",
                    "+60173356759",
                    "+60122590231",
                    "+60176195037",
                    "+601110358968",
                    "+60146716784",
                 #- "+601128655291",
                    "+60186757884",
                    "+60172106386",
                    "+60166185357", #jonathan
                    "+60173066026",
                    "+60128131918", #fuchin
                    "+60103844117",
                    "+601159251011", #ong
                    "+60162637873", #chunwen
                    "+60184709181", #huiwen
                    "+60102211704" #martin
                );
    
    ?>
