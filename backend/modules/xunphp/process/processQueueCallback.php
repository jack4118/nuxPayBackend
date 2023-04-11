<?php

    $currentPath = __DIR__;
    
    include_once $currentPath.'/../include/config.php';
    include_once $currentPath.'/../include/class.database.php';
    include_once $currentPath.'/../include/class.xun_email.php';
    include_once $currentPath.'/../include/class.xun_business.php';
    include_once $currentPath.'/../include/class.xun_user.php';
    include_once $currentPath.'/../include/class.xun_erlang.php';
    include_once $currentPath.'/../include/class.xun_crypto.php';
    include_once $currentPath.'/../include/class.xun_livechat.php';
    include_once $currentPath.'/../include/class.xun_marketplace.php';
    include_once $currentPath.'/../include/class.xun_xmpp.php';
    include_once $currentPath.'/../include/class.xun_sms.php';
    include_once $currentPath.'/../include/class.xun_admin.php';
    include_once $currentPath.'/../include/class.post.php';
    include_once $currentPath.'/../include/class.webservice.php';
    include_once $currentPath.'/../include/class.xun_webservice.php';
    include_once $currentPath.'/../include/class.message.php';
    include_once $currentPath.'/../include/class.setting.php';
    include_once $currentPath.'/../include/class.general.php';
    include_once $currentPath.'/../include/class.log.php';
    include_once $currentPath.'/../include/libphonenumber-for-php-master-v7.0/vendor/autoload.php';
    include_once $currentPath.'/../include/class.language.php';
    include_once $currentPath.'/../include/class.provider.php';
    include_once $currentPath.'/../include/class.ticketing.php';
    include_once $currentPath.'/../include/class.country.php';
    include_once $currentPath.'/../include/class.xun_aws.php';
    include_once $currentPath.'/../include/class.xun_giftcode.php';
    include_once $currentPath.'/../include/class.xun_tree.php';
    include_once $currentPath.'/../include/class.xun_referral.php';
    include_once $currentPath.'/../include/class.xun_currency.php';
    include_once $currentPath.'/../include/class.xun_freecoin_payout.php';
    include_once $currentPath.'/../include/class.xun_company_wallet.php';
    include_once $currentPath.'/../include/class.xun_company_wallet_api.php';
    include_once $currentPath.'/../include/class.push_notification.php';
    include_once $currentPath.'/../include/class.abstract_xun_user.php';
    include_once $currentPath.'/../include/class.xun_user_model.php';
    include_once $currentPath.'/../include/class.xun_user_service.php';
    include_once $currentPath.'/../include/class.xun_business_model.php';
    include_once $currentPath.'/../include/class.xun_business_service.php';
    include_once $currentPath.'/../include/class.xun_livechat_model.php';
    include_once $currentPath.'/../include/class.xun_wallet_transaction_model.php';
    include_once $currentPath.'/../include/class.xun_group_chat.php';
    include_once $currentPath.'/../include/class.xun_payment_gateway_model.php';
    include_once $currentPath.'/../include/class.xun_payment_gateway_service.php';

    include_once $currentPath.'/../include/class.xun_kyc.php';
    include_once $currentPath.'/../include/class.xun_wallet.php';
    include_once $currentPath.'/../include/class.xun_ip.php';
    include_once $currentPath.'/../include/class.xun_announcement.php';

    include_once $currentPath.'/../include/class.binance.php';

    include_once $currentPath.'/../include/class.xun_commission.php';
    include_once $currentPath.'/../include/class.xun_service_charge.php';
    include_once $currentPath.'/../include/class.xun_in_app_notification.php';
    include_once $currentPath.'/../include/class.xun_pay.php';
    include_once $currentPath.'/../include/class.xun_pay_provider.php';
    include_once $currentPath.'/../include/class.reloadly.php';
    include_once $currentPath.'/../include/class.group_chat_model.php';
    include_once $currentPath.'/../include/class.group_chat_service.php';
    include_once $currentPath.'/../include/class.xun_swapcoins.php';
    include_once $currentPath.'/../include/class.xun_pay_model.php';
    include_once $currentPath.'/../include/class.xun_pay_service.php';
    include_once $currentPath.'/../include/class.giftnpay.php';
    include_once $currentPath.'/../include/class.xun_coins.php';
    include_once $currentPath.'/../include/class.account.php';
    include_once $currentPath.'/../include/class.xun_story.php';
    include_once $currentPath.'/../include/class.xun_aws_web_services.php';
    include_once $currentPath.'/../include/class.xun_crowdfunding.php';
    include_once $currentPath.'/../include/class.xun_payment_gateway.php';
    include_once $currentPath.'/../include/class.xun_payment.php';
    include_once $currentPath.'/../include/class.xun_phone_approve.php';
    include_once $currentPath.'/../include/class.xun_phone_approve_service.php';
    include_once $currentPath.'/../include/class.xun_phone_approve_model.php';
    include_once $currentPath.'/../include/class.business_partner.php';
    include_once $currentPath.'/../include/class.xun_reward.php';
    include_once $currentPath.'/../include/class.xun_cashpool.php';
    include_once $currentPath.'/../include/class.cash.php';
    include_once $currentPath.'/../include/class.xun_sales.php';
    include_once $currentPath.'/../include/class.xun_marketer.php';
    include_once $currentPath.'/../include/class.xun_miner_fee.php';
    include_once $currentPath.'/../include/class.xun_business_coin.php';
    include_once $currentPath.'/../include/class.excel.php';
    include_once $currentPath.'/../include/class.xun_reseller.php';  
    include_once $currentPath.'/../include/class.whoisserver.php'; 
    include_once $currentPath.'/../include/class.campaign.php'; 

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $db2 = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $partnerDB = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], "thenuxPartner");

    $logPath = $currentPath . '/../log/';
    $path = realpath($logPath);
    $logBaseName = basename(__FILE__, '.php');
    
    $whoisserver      = new WhoisServer();
    $setting       = new Setting($db);
    $general       = new General($db2, $setting);
    $log  = new Log($logPath, $logBaseName);

    $provider      = new Provider($db);
    $message       = new Message($db2, $general, $provider);
    $webservice    = new Webservice($db2, $general, $message);
    $xunWebservice = new XunWebservice($db2);
    $language      = new Language($db, $general, $setting);
    $post          = new post();
    $country       = new Country($db, $general);
    $xunEmail      = new XunEmail($db, $post);
    $xunBusiness   = new XunBusiness($db, $post, $general, $xunEmail);
    $xunGroupChat   = new xunGroupChat($db, $post, $general, $xunEmail);
    $xunUser       = new XunUser($db, $post, $general, $whoisserver);
    $xunErlang     = new XunErlang($db, $post, $general);
    $xunCrypto     = new XunCrypto($db, $post, $general);
    $xunXmpp       = new XunXmpp($db, $post);
    $xunSms        = new XunSms($db, $post);
    $xunLivechat   = new XunLivechat($db, $post);
    $xunAdmin      = new XunAdmin($db, $setting, $general, $post);
    $xunMarketplace = new XunMarketplace($db, $post, $general);
    $ticket        = new Ticket($db, $general, $setting, $message, $log);
    $xunAws        = new XunAws($db, $setting);
    $xunGiftCode   = new XunGiftCode($db, $post, $general);
    $xunTree       = new XunTree($db, $setting, $general);
    $xunReferral   = new XunReferral($db, $setting, $general, $xunTree);
    $xunCurrency   = new XunCurrency($db);
    $xunCompanyWalletAPI   = new XunCompanyWalletAPI($db, $setting, $general, $post);

    $binance = new Binance($config['binanceAPIKey'], $config['binanceAPISecret'], $config['binanceAPIURL'], $config['binanceWAPIURL']);

    $xunKYC   = new XunKYC($db, $setting, $general);
    $xunAnnouncement  = new XunAnnouncement($db, $setting, $general);
    $xunServiceCharge  = new XunServiceCharge($db, $setting, $general);
    $account = new Account($db, $setting, $message, $provider, $log);
    $xunPay  = new XunPay($db, $setting, $general, $account);

    $giftnpay = new GiftnPay($db, $setting, $post);
    $xunCoins = new XunCoins($db, $setting);
    $xunStory = new XunStory($db, $post, $general, $setting);
    $xunAWSWebservices = new XunAWSWebservices($db);
    $xunCrowdfunding = new XunCrowdfunding($db, $post, $general, $setting);
    $xunPaymentGateway = new XunPaymentGateway($db, $post, $general, $setting, $xunCrypto, $xunCoins);
    $xunPayment = new XunPayment($db, $post, $general, $setting, $xunCrypto, $xunCoins);
    $xunSwapcoins  = new XunSwapcoins($db, $general, $setting, $post, $binance, $account, $xunPaymentGateway);

    $xunPhoneApprove = new XunPhoneApprove($db, $setting, $general, $post, $account);
    $xunBusinessPartner = new XunBusinessPartner($db, $post, $general, $partnerDB, $xunCrypto);
    $xunReward = new XunReward($db, $partnerDB, $post, $general, $setting);
    $cash = new Cash($db, $setting, $message, $provider);
    $xunCashpool = new XunCashpool($db, $general, $setting, $account);
    $xunSales = new XunSales($db, $partnerDB, $post, $general, $setting);
    $xunMarketer = new XunMarketer($db, $setting, $general);
    $xunMinerFee = new XunMinerFee($db, $general, $setting, $log);
    $xunBusinessCoin = new XunBusinessCoin($db, $general, $setting, $log, $xunCrypto, $xunCurrency);
    $excel = new Excel($db, $setting, $message, $log, $general, $cash, $xunAdmin);
    $xunReseller  = new XunReseller($db, $setting, $general, $post);
    $campaign      = new Campaign($db, $setting, $general, $post, $whoisserver);

    $systemLanguage = $languages ? trim($languages['language']) : "english";
    //$systemLanguage = trim($json_data["language"]) ? trim($json_data["language"]) : "english"; // default to english

    // // Set current language. Call $general->getCurrentLanguage() to retrieve the current language
    $general->setCurrentLanguage($systemLanguage);
    // // Include the language file for mapping usage
    include_once $currentPath.'/../language/lang_all.php';
     // // Set the translations into general class. Call $general->getTranslations() to retrieve all the translations
    $general->setTranslations($translations);


    $sleepTime = (strlen($argv[1]) > 0) ? $argv[1] : 1; // Sleep time

    $currentProcessID = getmypid();


    $log->write(date("Y-m-d H:i:s") . " Script started... pid: ".$currentProcessID." \n");

    $db->where("name", "processCryptoCallbackQueueFlag");
    $processDetail = $db->getOne("system_settings", "value, reference, type");
    $processFlag = $processDetail['value'];
    $lastProcessID = $processDetail['reference'];
    $monitorDelayMinute = $processDetail['type'];

    $db->where("name", "processCryptoCallbackQueueMonitoring");
    $monitoringMinute = $db->getValue("system_settings", "value");


    $totalPendingDetail = Array();
    $totalPending = 0;
    $totalOther = 0;
    $totalFailed = 0;
    $totalSuccessNoUpdate = 0;
    $totalSuccessNoCallback = 0;
    $totalQueueSlow = 0;

    if($monitoringMinute>0) {
        if((date("i") % $monitoringMinute) == 0){
            monitorSlowQueue($monitorDelayMinute);
            monitorWalletSuccessStatus();
            monitorOtherNotSuccessStatus();
            monitorWalletStatusSummary();
        }
    }

    if($processFlag || $lastProcessID!="") {
        
        $log->write(date("Y-m-d H:i:s") . " Process still running? Last pid: ".$lastProcessID." | Flag: ".$processFlag." | current pid: ".$currentProcessID." \n");

    	if(!checkOldProcessExist()) {

		if(!checkMyProcessExist($currentProcessID)) {
		    $log->write(date("Y-m-d H:i:s") . " My process not exist, exit. \n");
		    exit;
		} else {
    		    $log->write(date("Y-m-d H:i:s") . " Old process not exist, updating flag. \n");

    		    $db->where("name", "processCryptoCallbackQueueFlag");
    	            $db->update("system_settings", array("value"=>1,"reference"=>$currentProcessID));

    		    $log->write(date("Y-m-d H:i:s") . " Old process not exist, flag updated, proceed. \n");
    		}

    	} else {

    		$log->write(date("Y-m-d H:i:s") . " Old process still exist, direct exit. \n");
    		exit;

    	}

    } else {

	    $log->write(date("Y-m-d H:i:s") . " Process not exist, continue. \n");

        $db->where("name", "processCryptoCallbackQueueFlag");
        $db->update("system_settings", array("value"=>1, "reference"=>$currentProcessID));
    }

    while (1) {

        unset($processEnable);
        $db->where("name", "processCryptoCallbackQueue");
        $processEnable = $db->getValue("system_settings", "value");

        $db->where("name", "processCryptoCallbackQueueFlag");
        $processDetail2 = $db->getOne("system_settings", "value, reference");
        $processFlag2 = $processDetail2['value'];
        $lastProcessID2 = $processDetail2['reference'];

        $log->write(date("Y-m-d H:i:s") . " Enable Flag is: " . $processEnable . " | lastProcessID: ".$lastProcessID2." | processFlag: ".$processFlag2." \n");

        if($lastProcessID2!=$currentProcessID || !$processFlag2) {

            $log->write(date("Y-m-d H:i:s") . " Duplicate process detected, exit: " . $processEnable . " | lastProcessID: ".$lastProcessID2." | processFlag: ".$processFlag2." | CurrentPid: ".$currentProcessID." \n");


            $notificationUrl = $config["notificationUrl"];
            $api_key = $config["thenux_callback_queue_API"];
            $business_id = $config["thenux_callback_queue_bID"];

            $message = "Duplicate process detected, process killed.";
            $tag = "Duplicate Callback Queue Process";
            sendNewXunNotification($notificationUrl, $api_key, $business_id, $xun_monitoring_alert_numbers, $message, $tag);

            exit;
        }

        if ($processEnable == 1) {

            $db->where("processed", 0);
            $db->orderBy("id", "ASC");
            $callbackQueue = $db->get("crypto_callback_queue");

            foreach($callbackQueue as $queue) {

                $queueId = $queue['id'];
                $queueType = strtolower($queue['type']);
                $queueJsonString = $queue['json_string'];

                $log->write(date("Y-m-d H:i:s") . " Before Process => Queue ID: " . $queueId . " | " . $queueType . " | " . $queueJsonString . " \n");

                unset($outputArray);
                unset($status);
                if($queueType=="cryptocallback") {

                    $outputArray = $xunCrypto->save_crypto_callback(json_decode($queueJsonString, true));
                    $status = "processed";

                } else if($queueType=="cryptotransactioncallback") {

                    $outputArray = $xunCrypto->transaction_callback(json_decode($queueJsonString, true));
                    $status = "processed";

                } else if($queueType=="cryptoexternalfundoutcallback") {

                    $outputArray = $xunCrypto->crypto_external_fund_out_callback(json_decode($queueJsonString, true));
                    $status = "processed";

                } else if($queueType=="cryptoupdatetransactionhash") {

                    $outputArray = $xunCrypto->crypto_update_transaction_hash(json_decode($queueJsonString, true));
                    $status = "processed";

                } else if($queueType=="wallettransactionupdate") {

                    $outputArray = $xunCompanyWalletAPI->updateWalletTransaction(json_decode($queueJsonString, true));
                    $status = "processed";

                } else {

                    $outputArray = "";
                    $status = "skip";
                }

                $log->write(date("Y-m-d H:i:s") . " After Process => Queue ID: " . $queueId . " | " . $queueType . " | " . $status . " | " . $outputArray . " \n");

                if($status=="processed") {
                    $callbackData['result_string'] = json_encode($outputArray);
                    $callbackData['processed'] = "1";
                    $callbackData['updated_at'] = date("Y-m-d H:i:s");
                    $db->where("id", $queueId);
                    $db->update("crypto_callback_queue", $callbackData);
                }
                
            }

            //$log->write(date("Y-m-d H:i:s") . " The process is going to sleep for: " . $sleepTime . "second(s)\n");

        }

        sleep($sleepTime);

    }

    function monitorSlowQueue($monitorDelayMinute) {

        global $log;
        global $db;
        global $config;
        global $xun_monitoring_numbers;
        global $xun_monitoring_alert_numbers;

        global $totalPending;
        global $totalPendingDetail;
        global $totalOther;
        global $totalFailed;
        global $totalSuccessNoUpdate;
        global $totalSuccessNoCallback;
        global $totalQueueSlow;


        $notificationUrl = $config["notificationUrl"];
        $api_key = $config["thenux_callback_queue_API"];
        $business_id = $config["thenux_callback_queue_bID"];

        $now = date('Y-m-d H:i:s');
        $delayTime = date("Y-m-d H:i:s", strtotime($now." - ".$monitorDelayMinute." MINUTES"));

        $db->where('processed', 0);
        $db->where('created_at', $delayTime, '<');
        $totalSlow = $db->getValue('crypto_callback_queue', 'COUNT(*)');

        $log->write(date("Y-m-d H:i:s") . " Slow detected: " . $totalSlow. " \n");

        $totalQueueSlow = 0;

        if($totalSlow > 0) {
            $message = "Total ".$totalSlow." records has been queue for more than ".$monitorDelayMinute." minutes - ".$now;
            $tag = "Slow Callback Queue";
            sendNewXunNotification($notificationUrl, $api_key, $business_id, $xun_monitoring_alert_numbers, $message, $tag);

            $totalQueueSlow = $totalSlow;
        }

        $message = "Alive - ".$now;
        $tag = "Monitor Callback Queue";
        sendNewXunNotification($notificationUrl, $api_key, $business_id, $xun_monitoring_numbers, $message, $tag);

    }

    function monitorWalletStatusSummary() {

        global $config;
        global $xun_monitoring_numbers;
        global $xun_monitoring_alert_numbers;

        global $totalPending;
        global $totalPendingDetail;
        global $totalOther;
        global $totalFailed;
        global $totalSuccessNoUpdate;
        global $totalSuccessNoCallback;
        global $totalQueueSlow;


        $notificationUrl = $config["notificationUrl"];
        $api_key = $config["thenux_callback_queue_API"];
        $business_id = $config["thenux_callback_queue_bID"];


        if($totalPending > 0 || $totalOther > 0 || $totalFailed > 0 || $totalSuccessNoUpdate > 0 || $totalSuccessNoCallback > 0 || $totalQueueSlow > 0) {

            $message = date("Y-m-d H:i:s");
            $message .= "\nPending: ".$totalPending;

            foreach($totalPendingDetail as $pendingDetail => $pendingVal) {
                $message .= "\n- ".$pendingDetail." => ".$pendingVal;
            }

            $message .= "\nFailed: ".$totalFailed;
            $message .= "\nOther: ".$totalOther;
            $message .= "\nSuccess No Update: ".$totalSuccessNoUpdate;
            $message .= "\nSuccess No Callback: ".$totalSuccessNoCallback;
            $message .= "\nSlow Queue: ".$totalQueueSlow;

            $tag = "Wallet transaction issue summary";

            sendNewXunNotification($notificationUrl, $api_key, $business_id, $xun_monitoring_alert_numbers, $message, $tag);

        } else {

            $message = "All Good - ".date("Y-m-d H:i:s");
            $tag = "Wallet transaction issue summary";

            sendNewXunNotification($notificationUrl, $api_key, $business_id, $xun_monitoring_numbers, $message, $tag);
        }

    }
    function monitorOtherNotSuccessStatus() {

        global $log;
        global $db;
        global $config;
        global $xun_monitoring_numbers;
        global $xun_monitoring_alert_numbers;

        global $totalPending;
        global $totalPendingDetail;
        global $totalFailed;
        global $totalSuccessNoUpdate;
        global $totalSuccessNoCallback;
        global $totalQueueSlow;

        $notificationUrl = $config["notificationUrl"];
        $api_key = $config["thenux_callback_queue_API"];
        $business_id = $config["thenux_callback_queue_bID"];

        $startTs = '2021-01-27 00:00:00';

        $db->where('updated_at', $startTs, '>=');
        $db->where('status', 'wallet_success', '!=');
        $db->where('status', 'completed', '!=');
        $db->where('status', 'confirmed', '!=');
        $db->where('status', 'block_not_found', '!=');
        $db->where('status', 'failed_no_retrigger', '!=');
        $successTransaction = $db->get('xun_wallet_transaction');
        
        $totalFailed = 0;
        $totalPending = 0;
        $totalPendingDetail = Array();

        foreach($successTransaction as $transaction) {

            $transactionHash = $transaction['transaction_hash'];
            $createdAt = $transaction['created_at'];
            $updatedAt = $transaction['updated_at'];
            $walletType = $transaction['wallet_type'];
            $addressType = $transaction['address_type'];
            $transactionId = $transaction['id'];
            $senderAddress = $transaction['sender_address'];
            $recipientAddress = $transaction['recipient_address'];
            $amount = $transaction['amount'];
            $transactionStatus = $transaction['status'];

            $message = "";
            if($transactionStatus=="failed") {

                //failed
                $message = "Address type: ".$addressType;
                $message .= "\nTransaction ID: ".$transactionId;
                $message .= "\nSender Address: ".$senderAddress;
                $message .= "\nReceiver Address: ".$recipientAddress;
                $message .= "\nAmount: ".$amount;
                $message .= "\nWallet Type: ".$walletType;
                $message .= "\nStatus: ".$transactionStatus;
                $message .= "\nCreated Ts: ".$createdAt;
                $message .= "\nUpdated Ts: ".$updatedAt;

                $tag = "Wallet transaction failed";
                sendNewXunNotification($notificationUrl, $api_key, $business_id, $xun_monitoring_alert_numbers, $message, $tag);

                $totalFailed += 1;

            } else {

                $toTs = date('Y-m-d H:i:s');
                $to_time = strtotime($toTs);
                $from_time = strtotime($updatedAt);
                $min_diff = round(abs($to_time - $from_time) / 60,2);

                if($min_diff > 60) {
                    //pending or other status

                    $message = "Address type: ".$addressType;
                    $message .= "\nTransaction ID: ".$transactionId;
                    $message .= "\nSender Address: ".$senderAddress;
                    $message .= "\nReceiver Address: ".$recipientAddress;
                    $message .= "\nAmount: ".$amount;
                    $message .= "\nWallet Type: ".$walletType;
                    $message .= "\nStatus: ".$transactionStatus;
                    $message .= "\nCreated Ts: ".$createdAt;
                    $message .= "\nUpdated Ts: ".$updatedAt;

                    $tag = "Wallet transaction ".$transactionStatus;

                    sendNewXunNotification($notificationUrl, $api_key, $business_id, $xun_monitoring_alert_numbers, $message, $tag);

                    if($transactionStatus=="pending") {

                        $pendingError = "";

                        $webservice_tbl = "web_services_".date("Ymd", strtotime($createdAt));

                        if($db->tableExists($webservice_tbl)) {

                            $db->where('data_in', '%'.$transactionId.'%', 'LIKE');
                            $db->where('data_out', '%Failed release escrow%', 'LIKE');
                            $db->orderBy('id', 'DESC');

                            $pendingDetailWs = $db->getOne($webservice_tbl);

                            if($pendingDetailWs) {
                                $pendingDetail = $pendingDetailWs['data_out'];

                                $pendingError = getEscrowDataOutBroadcastMessageDetail($pendingDetail);
                            }

                        } 

                        if($pendingError!="") {
                            $totalPendingDetail[$pendingError] += 1;
                        } else {
                            $totalPendingDetail['other'] += 1;
                        }
                      
                        $totalPending += 1;
                    } else {
                        $totalOther += 1;
                    }
                    
                }            
            }
        }
    }

    function monitorWalletSuccessStatus() {

        global $log;
        global $db;
        global $config;
        global $xun_monitoring_numbers;
        global $xun_monitoring_alert_numbers;

        global $totalPending;
        global $totalOther;
        global $totalFailed;
        global $totalSuccessNoUpdate;
        global $totalSuccessNoCallback;
        global $totalQueueSlow;

        $notificationUrl = $config["notificationUrl"];
        $api_key = $config["thenux_callback_queue_API"];
        $business_id = $config["thenux_callback_queue_bID"];

        $toTs = date('Y-m-d H:i:s');
        $startTs = '2021-01-27 00:00:00';

        $db->where('updated_at', $startTs, '>=');
        $db->where('status', 'wallet_success');
        $successTransaction = $db->get('xun_wallet_transaction');

        $totalSuccessNoUpdate = 0;
        $totalSuccessNoCallback = 0;

        foreach($successTransaction as $transaction) {

            $transactionHash = $transaction['transaction_hash'];
            $createdAt = $transaction['created_at'];
            $updatedAt = $transaction['updated_at'];
            $walletType = $transaction['wallet_type'];
            $addressType = $transaction['address_type'];
            $transactionId = $transaction['id'];
            $senderAddress = $transaction['sender_address'];
            $recipientAddress = $transaction['recipient_address'];
            $amount = $transaction['amount'];
            $transactionStatus = $transaction['status'];

            $db->where('json_string', '%'.$transactionHash.'%', 'LIKE');
            $db->where('json_string', '%confirmed%', 'LIKE');
            $db->where('created_at', $createdAt, '>=');
            $db->where('type', 'cryptocallback');
            $db->where('processed', 1);
            $queueDetail = $db->get('crypto_callback_queue');

            $message = "";
            if($queueDetail) {
                //trigger no update alert

                $message = "Address type: ".$addressType;
                $message .= "\nTransaction ID: ".$transactionId;
                $message .= "\nSender Address: ".$senderAddress;
                $message .= "\nReceiver Address: ".$recipientAddress;
                $message .= "\nAmount: ".$amount;
                $message .= "\nWallet Type: ".$walletType;
                $message .= "\nStatus: ".$transactionStatus;
                $message .= "\nCreated Ts: ".$createdAt;
                $message .= "\nUpdated Ts: ".$updatedAt;

                $tag = "Confirmed callback no update";
                sendNewXunNotification($notificationUrl, $api_key, $business_id, $xun_monitoring_alert_numbers, $message, $tag);

                $totalSuccessNoUpdate += 1;

            } else {

                $to_time = strtotime($toTs);
                $from_time = strtotime($updatedAt);
                $min_diff = round(abs($to_time - $from_time) / 60,2);

                if($min_diff > 60) {
                    //trigger no confirmed alert

                    $message = "Address type: ".$addressType;
                    $message .= "\nTransaction ID: ".$transactionId;
                    $message .= "\nSender Address: ".$senderAddress;
                    $message .= "\nReceiver Address: ".$recipientAddress;
                    $message .= "\nAmount: ".$amount;
                    $message .= "\nWallet Type: ".$walletType;
                    $message .= "\nStatus: ".$transactionStatus;
                    $message .= "\nCreated Ts: ".$createdAt;
                    $message .= "\nUpdated Ts: ".$updatedAt;

                    $tag = "No confirmed callback";
                    sendNewXunNotification($notificationUrl, $api_key, $business_id, $xun_monitoring_alert_numbers, $message, $tag);

                    $totalSuccessNoCallback += 1;
                }

            }
        }

    }

    function sendNewXunNotification($notificationUrl, $api_key, $business_id, $xunNumber, $message, $tag){
        $targetUrl = $notificationUrl;
        $fields = array("api_key" => $api_key,
            "business_id" => $business_id,
            "message" => $message,
            "tag" => $tag,
            "mobile_list" => $xunNumber,
        );
        $dataString = json_encode($fields);

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($dataString))
        );

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    function checkMyProcessExist($currentProcessID) {

        global $log;
        $processScript = __DIR__."/".basename(__FILE__);

        $cmd = "ps $currentProcessID";

        exec($cmd, $output, $result);

        // $log->write(date("Y-m-d H:i:s") . " OUTPUT " . count($output)." | ". json_encode($output) . " \n");
        
        // foreach($output as $out) {
        //     if (strpos($out, 'php '.$processScript) !== false) {
        //     	$arrProcess = explode(' ', str_replace(array("     ", "    ","   ", "  "), " ",$out));
        // 		if($arrProcess[1]==$currentProcessID) {
        // 		    return true;
        // 		}
        //     }
        // }

        if(count($output) >= 2){
			
			// the process is still alive
			return true;
		}

	   return false;
    }

    function checkOldProcessExist() {

    	global $log;
    	$processScript = __DIR__."/".basename(__FILE__);

    	$cmd = "ps -ef|grep ".basename(__FILE__);

        exec($cmd, $output, $result);

    	$log->write(date("Y-m-d H:i:s") . " Check old process exist " . count($output)." | ". json_encode($output) . " \n");

    	$counter = 0;
    	foreach($output as $out) {
    	    if (strpos($out, 'php '.$processScript) !== false) {
        		$counter += 1;
    	    }
    	}

    	$log->write(date("Y-m-d H:i:s") . " Check old process exist detected: " . $counter ." \n");

    	if($counter > 1){
            return true;
        } else {
            return false;
        }
        
    	$log->write(date("Y-m-d H:i:s") . " Check output" . json_encode($output) ." \n");
        // if(count($output) >= 2){
		// 	// the process is still alive
		// 	return true;
        // }
        return false;
    }

    function getEscrowDataOutBroadcastMessageDetail($data) {

        $arr_data = explode("\n", $data);

        foreach($arr_data as $line) {

            if (strpos(strtolower($line), 'status msg') !== false) {

                $arr_detail = explode("=", $line, 2);

                if(count($arr_detail) == 2) {
                    return trim($arr_detail[1]);
                }
            }
        }

        return "";
    }

?>
