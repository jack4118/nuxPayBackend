<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include_once 'include/class.msgpack.php';
    include_once 'include/config.php';
    include_once 'include/class.admin.php';
    include_once 'include/class.reseller.php';    
    include_once 'include/class.database.php';
    include_once 'include/class.cash.php';
    include_once 'include/class.webservice.php';
    include_once 'include/class.user.php';
    include_once 'include/class.api.php';
    include_once 'include/class.message.php';
    include_once 'include/class.permission.php';
    include_once 'include/class.setting.php';
    include_once 'include/class.language.php';
    include_once 'include/class.provider.php';
    include_once 'include/class.journals.php';
    include_once 'include/class.country.php';
    include_once 'include/class.general.php';
    include_once 'include/class.tree.php';
    include_once 'include/class.activity.php';
    include_once 'include/class.invoice.php';
    include_once 'include/class.product.php';
    include_once 'include/class.client.php';
    include_once 'include/class.memo.php';
    include_once 'include/class.announcement.php';
    include_once 'include/class.document.php';
    include_once 'include/class.bonus.php';
    include_once 'include/PHPExcel.php';
    include_once 'include/class.log.php';
    include_once 'include/class.report.php';
    include_once 'include/class.dashboard.php';
    include_once 'include/class.ticketing.php';
    include_once 'include/class.xun_admin.php';
    include_once 'include/class.xun_reseller.php';
    include_once 'include/class.post.php';
    include_once 'include/class.xun_xmpp.php';
    include_once 'include/class.xun_email.php';
    include_once 'include/class.xun_business.php';
    include_once 'include/class.xun_aws.php';
    include_once 'include/class.xun_tree.php';
    include_once 'include/class.xun_announcement.php';
    include_once 'include/class.xun_livechat_model.php';
    include_once 'include/class.abstract_xun_user.php';
    include_once 'include/class.xun_user_model.php';
    include_once 'include/class.xun_user_service.php';

    include_once 'include/class.xun_crypto.php';
    include_once 'include/class.xun_giftcode.php';
    include_once 'include/class.xun_referral.php';
    include_once 'include/class.xun_currency.php';
    include_once 'include/class.xun_freecoin_payout.php';
    include_once 'include/class.xun_company_wallet.php';
    include_once 'include/class.xun_company_wallet_api.php';
    include_once 'include/class.push_notification.php';
    include_once 'include/class.xun_business_model.php';
    include_once 'include/class.xun_business_service.php';
    include_once 'include/class.xun_wallet_transaction_model.php';
    include_once 'include/class.xun_group_chat.php';
    include_once 'include/class.xun_payment_gateway_model.php';
    include_once 'include/class.xun_payment_gateway_service.php';

    include_once 'include/class.xun_kyc.php';
    include_once 'include/class.xun_wallet.php';
    include_once 'include/class.xun_ip.php';
    include_once 'include/class.xun_commission.php';
    include_once 'include/class.xun_service_charge.php';
    include_once 'include/class.xun_in_app_notification.php';
    include_once 'include/class.xun_pay.php';
    include_once 'include/class.xun_pay_provider.php';
    include_once 'include/class.reloadly.php';
    include_once 'include/class.group_chat_model.php';
    include_once 'include/class.group_chat_service.php';
    include_once 'include/class.xun_pay_model.php';
    include_once 'include/class.xun_pay_service.php';
    include_once 'include/class.giftnpay.php';
    include_once 'include/class.xun_coins.php';
    include_once 'include/class.account.php';
    include_once 'include/class.xun_story.php';
    include_once 'include/class.xun_payment_gateway.php';
    include_once 'include/class.xun_payment.php';
    include_once 'include/class.message.php';
    include_once 'include/class.excel.php';
    include_once 'include/class.xun_user.php';
    include_once 'include/class.business_partner.php';
    include_once 'include/class.xun_sms.php';
    include_once 'include/libphonenumber-for-php-master-v7.0/vendor/autoload.php';
    include_once 'include/class.campaign.php';
    include_once 'include/class.whoisserver.php'; 
    include_once 'include/class.xun_marketer.php';
    include_once 'include/class.xun_miner_fee.php';
    include_once 'include/class.xanpool.php';

    $whoisserver      = new WhoisServer();

    $db      = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $partnerDB = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], "thenuxPartner");
    $setting = new Setting($db);
    $general = new General($db, $setting);
    $log     = new Log();

    $msgpack = new msgpack();

    $user       = new User($db, $setting, $general);
    $api        = new Api($db, $general);
    $provider   = new Provider($db);
    $message    = new Message($db, $general, $provider);
    $webservice = new Webservice($db, $general, $message);
    $permission = new Permission($db, $general);

    $cash     = new Cash($db, $setting, $message, $provider, $log);
    $language = new Language($db, $general, $setting);
    $activity = new Activity($db, $general);

    // $journals        = new Journals($db, $general);
    $country      = new Country($db, $general);
    $tree         = new Tree($db, $setting, $general);
    $invoice      = new Invoice($db, $setting);
    $product      = new Product($db, $setting, $general);
    $bonus        = new Bonus($db, $general, $setting, $cash, $log);
    $client       = new Client($db, $setting, $general, $country, $tree, $cash, $activity, $product, $invoice, $bonus);    
    $admin        = new Admin($db, $setting, $general, $cash, $invoice, $product, $country, $activity, $client, $bonus);    
    $reseller     = new Reseller($db, $setting, $general, $cash, $invoice, $product, $country, $activity, $client, $bonus);
    $memo         = new Memo($db, $general, $setting);
    $announcement = new Announcement($db, $general, $setting);
    $document     = new Document($db, $general, $setting);
    $report       = new Report($db, $general, $setting);

    $dashboard = new Dashboard($db, $announcement, $cash, $admin);
    //function __construct($db, $general, $setting, $message, $log="") {
    $ticket = new Ticket($db, $general, $setting, $message, $log);

    $post = new post();
    $xunAdmin  = new XunAdmin($db, $setting, $general, $post);
    $xunReseller  = new XunReseller($db, $setting, $general, $post);
    $xunCrypto     = new XunCrypto($db, $post, $general);
    $xunXmpp       = new XunXmpp($db, $post);
    $xunEmail      = new XunEmail($db, $post);
    $xunBusiness   = new XunBusiness($db, $post, $general, $xunEmail);
    $xunAws        = new XunAws($db, $setting);
    $xunTree       = new XunTree($db, $setting, $general);
    $xunAnnouncement  = new XunAnnouncement($db, $setting, $general);
    $campaign      = new Campaign($db, $setting, $general, $post, $whoisserver);

    $xunReferral   = new XunReferral($db, $setting, $general, $xunTree);
    $xunCurrency   = new XunCurrency($db);
    $xunCompanyWalletAPI   = new XunCompanyWalletAPI($db, $setting, $general, $post);
    $xunKYC   = new XunKYC($db, $setting, $general);
    $xunAnnouncement  = new XunAnnouncement($db, $setting, $general);
    $xunServiceCharge  = new XunServiceCharge($db, $setting, $general);
    $account = new Account($db, $setting, $message, $provider, $log);
    $xunPay  = new XunPay($db, $setting, $general, $account);
    $giftnpay = new GiftnPay($db, $setting, $post);
    $xunCoins = new XunCoins($db, $setting);
    $xunStory = new XunStory($db, $post, $general, $setting);
    $xunPaymentGateway = new XunPaymentGateway($db, $post, $general, $setting, $xunCrypto, $xunCoins);
    $xunPayment = new XunPayment($db, $post, $general, $setting, $xunCrypto, $xunCoins);
    $xunUser       = new XunUser($db, $post, $general, $whoisserver);
    $xunBusinessPartner = new XunBusinessPartner($db, $post, $general, $partnerDB, $xunCrypto);
    $xunSms        = new XunSms($db, $post);
    $xunMarketer = new XunMarketer($db, $setting, $general);
    $xunMinerFee = new XunMinerFee($db, $general, $setting, $log);
    $xanpool = new Xanpool($db, $general, $setting, $post);

    // $excel = new Excel($db, $setting, $message, $provider, $log, $general, $client, $cash, $admin, $dashboard, $document, $activity, $tree, $report, $bonus);
    $excel = new Excel($db, $setting, $message, $log, $general, $cash, $xunAdmin);

    $msgpackData = $msgpack->msgpack_unpack(file_get_contents('php://input'));

    $timeStart  = time();
    $tblDate    = date("Ymd");
    $createTime = date("Y-m-d H:i:s");

    $command        = $msgpackData['command'];
    $sessionID      = $msgpackData['sessionID'];
    $userID         = $msgpackData['userID'];
    $sessionTimeOut = $msgpackData['sessionTimeOut'];
    $source         = $msgpackData['source'];
    $site           = $msgpackData['site'];
    $userAgent      = $msgpackData['userAgent'];
    $ip             = $msgpackData['ip'];
    // $source         = $msgpackData['source'];
    $systemLanguage = trim($msgpackData['language']) ? trim($msgpackData['language']) : "english"; // default to english

    // Set current language. Call $general->getCurrentLanguage() to retrieve the current language
    $general->setCurrentLanguage($systemLanguage);
    // Include the language file for mapping usage
    include_once 'language/lang_all.php';
    // Set the translations into general class. Call $general->getTranslations() to retrieve all the translations
    $general->setTranslations($translations);

    if ($command != "getWebservices") {
        $webserviceID = $webservice->insertWebserviceData($msgpackData, $tblDate, $createTime, $command);
    }
    $filterCommands = array("adminLogin", "resellerLogin","memberLogin", "contactUs", "getCountriesList", "resellerRequestResetPasswordOTP", "resellerResetPassword", "resellerRequestUsernameOTP", "resellerRequestUsername");

    if ($source == "Xamarin") {
        // We must not use this to bypass session checking, Apps should go through normal session checking as well
        //$msgpackData['params']['appsBypass'] = true;
    } else if (!in_array($command, $filterCommands)) {

        if ($command == "testAPI") {
            // If it's test API, no need to validate session
            $userData = $user->getTestUserData($msgpackData['params']['userID'], $site);

            // Replace the command with the command that we are going to test
            $command = trim($msgpackData['params']['testCommand']);
            unset($msgpackData['params']['testCommand']);

            // Remove from params object, so that checkApiParams will not block it
            // Assign to another variable, just in case need to use it again
            $testApiUserID = trim($msgpackData['params']['userID']);
            unset($msgpackData['params']['userID']);
        } else {
            $userData = $user->checkSession($userID, $sessionID, $site);
            //print_r($userData);
        }

        if (!$userData || !$user->checkSessionTimeOut($sessionTimeOut, $site)) {
            // If sessionID is invalid, we return as session timeout
            $outputArray = array('status' => "error", 'code' => 3, 'statusMsg' => "Session expired.", 'data' => $userData);

            $webservice->updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

            echo $msgpack->msgpack_pack($outputArray);
            exit;
        }
    }

    $db->userID   = $userID;
    $db->userType = $site;

    $getApiResult = $api->getOneApi($command);
    // Temporary comment till all APIs are added into API table
    // if($getApiResult['code'] == 1) {
    //     $updateWebservice = $webservice->updateWebserviceData($webserviceID, $getApiResult, $getApiResult["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

    //     echo $msgpack->msgpack_pack($outputArray);
    //     exit;
    // }
    $apiSetting = $getApiResult['data'];

    $apiID             = $apiSetting['id'];
    $apiDuplicate      = $apiSetting['check_duplicate'];
    $duplicateInterval = $apiSetting['check_duplicate_interval'];
    $isSample          = $apiSetting['sample'];

    // Check api parameters type
    $checker = $api->checkApiParams($apiID, $msgpackData['params']);

    if ($checker['code'] == 1) {
        $updateWebservice = $webservice->updateWebserviceData($webserviceID, $checker, $checker["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

        echo $msgpack->msgpack_pack($checker);
        exit;
    }

    // Check duplicate parameters of api
    if ($apiDuplicate == 1) {
        $duplicate = $api->checkApiDuplicate($tblDate, $createTime, $userID, $sessionID, $site, $command, $duplicateInterval);

        if ($duplicate['code'] == 1) {
            $updateWebservice = $webservice->updateWebserviceData($webserviceID, $duplicate, $duplicate["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

            echo $msgpack->msgpack_pack($duplicate);
            exit;
        }
    }

    // Check whether to use sample output for this api
    if ($isSample == 1) {
        $outputArray = $api->getSampleOutput($apiID);

        $webservice->updateWebserviceData($webserviceID, $outputArray, $outputArray["status"], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

        echo $msgpack->msgpack_pack($outputArray);
        exit;
    }

    // Set creator id and type
    $cash->setCreator($userID, $site);
    $activity->setCreator($userID, $site);

    $db->queryNumber = 0;

    //check whether client is being locked out from api
    $params = $msgpackData['params'];
    $result = 0;

    if ($activity->creatorType == "Member") {
        $sq  = $db->subQuery();
        $sq2 = $db->subQuery();

        $sq2->where("name", $params['creditType']);
        $sq2->getOne("credit", "id");

        // tableIndex = command, credit_id
        $sq->where("command", $command);
        $sq->where("credit_id", $sq2);
        $sq->getOne("mlm_client_rights", "id");

        $db->where("client_id", $activity->creatorID);
        $db->where("rights_id", $sq);
        $result = $db->getValue("mlm_client_blocked_rights", "count(*)");
    }

    //result equals to 0 means the client is not blocked from using the api
    if ($result == 0) {

        switch ($command) {

            // START OF RESELLER API
            case "resellerLogin":
                $outputArray = $reseller->resellerLogin($msgpackData['params']);
                break;

            case "resellerNuxpayLatestTransactionList":
                $outputArray = $xunReseller->reseller_nuxpay_latest_transaction_list($msgpackData['params'],$userID);                
                break;
                
            case "resellerNuxpayDashboardStatistics":
                // $outputArray = $xunAdmin->admin_nuxpay_dashboard_statistics($msgpackData['params']);
                $outputArray = $xunReseller->reseller_nuxpay_dashboard_statistics($msgpackData['params'],$userID);
                break;

            case "resellerNuxpayMerchantList":
                // $outputArray = $xunAdmin->admin_nuxpay_merchant_list($msgpackData['params']);
                $outputArray = $xunReseller->reseller_nuxpay_merchant_list($msgpackData['params'],$userID);
                break;

            case "resellerNuxpayMerchantDetails":
                // $outputArray = $xunAdmin->admin_nuxpay_merchant_details($msgpackData['params']);
                $outputArray = $xunReseller->reseller_nuxpay_merchant_details($msgpackData['params']);
                break;    
            
            case "resellerNuxpayTransactionHistoryList":
                // $outputArray = $xunAdmin->admin_nuxpay_transaction_history_listing($msgpackData['params']);
                $outputArray = $xunReseller->reseller_nuxpay_transaction_history_listing($msgpackData['params'],$userID, $site);
                break;

            case "resellerChangePassword" : 
                // $outputArray = $xunAdmin->admin_change_password($msgpackData['params']);
                $outputArray = $xunReseller->reseller_change_password($msgpackData['params']);
                break;

            case "resellerNuxpayGetMinerFeeReport":
                // $outputArray = $xunAdmin->admin_nuxpay_get_miner_fee_report($msgpackData['params']);
                $outputArray = $xunReseller->reseller_nuxpay_get_miner_fee_report($msgpackData['params'],$userID);
                break;

            case "resellerNuxpayGetMinerFeeDetails":
                // $outputArray = $xunAdmin->nuxpay_get_miner_fee_details($msgpackData['params']);
                $outputArray = $xunReseller->nuxpay_get_miner_fee_details($msgpackData['params'],$userID);
                break;

            case "resellerGetFundOutListing":
                // $outputArray = $xunAdmin->admin_get_fund_out_listing($msgpackData['params']);
                $outputArray = $xunReseller->reseller_get_fund_out_listing($msgpackData['params'],$userID);
                break; 
            
            case "resellerFundOutDetails":
                // $outputArray = $xunAdmin->admin_get_fund_out_listing($msgpackData['params']);
                $outputArray = $xunReseller->reseller_fund_out_details($msgpackData['params'],$userID);
                break; 

            case "resellerMerchantListing" :
                // $outputArray = $xunAdmin->admin_listing($msgpackData['params']);
                $outputArray = $xunReseller->reseller_merchant_listing($msgpackData['params'],$userID);
                break; 

            case "resellerGetMerchantDetails" :
                // $outputArray = $xunAdmin->get_admin_details($msgpackData['params']);
                $outputArray = $xunReseller->get_reseller_merchant_details($msgpackData['params']);
                break;

            case "resellerEditMerchantDetails" :
                // $outputArray = $xunAdmin->edit_admin_details($msgpackData['params']);
                $outputArray = $xunReseller->edit_reseller_merchant_details($msgpackData['params'], $userID);
                break;

            case "resellerGetDetails" :
                $outputArray = $xunReseller->get_reseller_details($msgpackData['params']);
                break;

            case "resellerEditDetails" :
                $outputArray = $xunReseller->edit_reseller_details($msgpackData['params']);
                break;

            case "distributorGetDetails" :
                $outputArray = $xunReseller->get_distributor_details($msgpackData['params']);
                break;

            case "distributorEditDetails" :
                $outputArray = $xunReseller->edit_distributor_details($msgpackData['params']);
                break;
                
            case "resellerCreateMerchant":
                // $outputArray = $xunAdmin->create_admin($msgpackData['params']);
                $outputArray = $xunReseller->create_reseller_merchant($msgpackData['params'],$userID);
                break;

            case "loadResellerOptions":
                // $outputArray = $xunAdmin->create_admin($msgpackData['params']);
                $outputArray = $xunReseller->load_reseller_options($msgpackData['params']);
                break;

            case "resellerRequestResetPasswordOTP":
                $outputArray = $xunReseller->reseller_request_reset_password_otp($msgpackData['params']);
                break;

            case "resellerResetPassword":
                $outputArray = $xunReseller->reseller_reset_password($msgpackData['params'], $ip, $userAgent);                
                break;

            case "resellerRequestUsernameOTP":
                $outputArray = $xunReseller->reseller_request_username_otp($msgpackData['params']);
                break;

            case "resellerRequestUsername":
                $outputArray = $xunReseller->reseller_request_username($msgpackData['params']);
                break;

            case "resellerWithdrawal":
                $outputArray = $xunReseller->reseller_withdraw($msgpackData['params'], $userID);
                break;

            case "resellerCreateLandingPage":
                $outputArray = $campaign->create_landing_page($msgpackData['params'], $userID);
                break;

            case "resellerGetCreateLandingPageDetails":
                $outputArray = $campaign->get_create_landing_page_details($msgpackData['params']);
                break;

            case "resellerLandingPagePresignURL":
                $outputArray = $campaign->get_landing_page_presign_url($msgpackData['params']);
                break;

            case "resellerGetCreateCampaignDetails":
                $outputArray = $campaign->get_create_campaign_details($msgpackData['params'], $userID);
                break;

            case "resellerVerifyLandingPageURL":
                $outputArray = $campaign->verify_landing_page_url($msgpackData['params'], $userID);
                break;
            
            case "resellerGetLandingPageListing":
                $outputArray = $campaign->get_landing_page_listing($msgpackData['params'], $userID);
                break;

            case "resellerEditLandingPage":
                $outputArray = $campaign->edit_landing_page($msgpackData['params'], $userID);
                break;

            case "resellerAddUsername":
                $outputArray = $xunReseller->reseller_add_username($msgpackData['params'], $userID);
                break;

            #DISTRIBUTOR
            case "resellerListing" :
                // $outputArray = $xunAdmin->admin_listing($msgpackData['params']);
                $outputArray = $xunReseller->reseller_listing($msgpackData['params'],$userID);
                break; 

            case "distributorListing" :
                $outputArray = $xunReseller->distributor_listing($msgpackData['params'],$userID);
                break; 

            case "resellerCreateReseller":
                // $outputArray = $xunAdmin->create_admin($msgpackData['params']);
                $outputArray = $xunReseller->create_reseller_user($msgpackData['params'],$userID);
                break;

            case "resellerCreateDistributor":
                // $outputArray = $xunAdmin->create_admin($msgpackData['params']);
                $outputArray = $xunReseller->create_distributor_user($msgpackData['params'],$userID);
                break;

            case "resellerGetWithdrawalHistory":
                $outputArray = $xunReseller->reseller_get_withdrawal_history($msgpackData['params'],$userID);
                break; 

            case "resellerWithdrawalHistoryDetails":
                $outputArray = $xunReseller->reseller_withdrawal_history_details($msgpackData['params'],$userID);
                break; 

            case "resellerApplicationListing":
                $outputArray = $xunReseller->reseller_application_listing($msgpackData['params'], $userID);
                break;
            
            case "createCampaign":
                $outputArray = $campaign->create_campaign($msgpackData['params'], $userID);
                break;

            case "campaignListing":
                $outputArray = $campaign->campaign_listing($msgpackData['params'], $userID);
                break;
                
            case "campaignListingDetails":
                $outputArray = $campaign->campaign_listing_details($msgpackData['params'], $userID);
                break;

            case "campaignGetShortUrlDetails":
                $outputArray = $campaign->get_short_url_details($msgpackData['params']);
                break;
             
            case "createShortUrl":
                $outputArray = $campaign->create_short_url($msgpackData['params']);
            break;

            // END OF RESELLER API
            case "adminResellerCreateMerchant":
                $outputArray = $admin->create_reseller_merchant($msgpackData['params'],$userID);
                break;

            case "adminResellerCreateReseller":
                // $outputArray = $xunAdmin->create_admin($msgpackData['params']);
                $outputArray = $admin->create_reseller_user($msgpackData['params'],$userID);
                break;

            case "adminResellerCreateDistributor":
                $outputArray = $admin->create_distributor_user($msgpackData['params'],$userID);
                break;

            case "adminDistributorGetDetails" :
                $outputArray = $admin->get_distributor_details($msgpackData['params']);
                break;

            case "adminDistributorEditDetails" :
                $outputArray = $admin->edit_distributor_details($msgpackData['params']);
                break;

            case "adminDistributorListing" :
                $outputArray = $admin->admin_distributor_listing($msgpackData['params'],$userID);
                break; 

            case "adminResellerGetDetails" :
                $outputArray = $xunReseller->get_reseller_details($msgpackData['params']);
                break;

            case "adminResellerEditDetails" :
                $outputArray = $xunReseller->edit_reseller_details($msgpackData['params']);
                break;
            
            case "getResellerClicksInfo":
                $outputArray = $xunReseller->get_reseller_clicks_info($msgpackData['params'], $userID);
                break;
            
            case "getTopDistributors":
                $outputArray = $xunReseller->reseller_get_top_distributors($msgpackData['params'], $userID);
                break;

            case "getTopResellers":
                $outputArray = $xunReseller->reseller_get_top_resellers($msgpackData['params'], $userID);
                break;

            case "getCommissionBalance":
                $outputArray = $xunReseller->reseller_get_commission_balance($msgpackData['params'], $userID);
                break;                
            
            case "getCommissionTransactionHistory":
                $outputArray = $xunReseller->reseller_get_commission_transaction_history($msgpackData['params'], $userID);
                break;

            case "requestCommissionWithdrawalOTP":
                $outputArray = $xunReseller->reseller_request_commission_withdrawal_otp($msgpackData['params'], $userID);
                break;
            
            case "getCommissionWithdrawalHistory":
                $outputArray = $xunReseller->reseller_get_commission_withdrawal_history($msgpackData['params'], $userID);
                break;
            case "getResellerSalesListing":
                $outputArray = $xunReseller->get_reseller_sales_listing($msgpackData['params']);
                break;    
            case "saleListingDetails":
                $outputArray = $xunReseller->get_reseller_sales_Detail($msgpackData['params']);
                break;   
            case "adminResellerListing" :
                // $outputArray = $xunAdmin->admin_listing($msgpackData['params']);
                $outputArray = $admin->admin_reseller_listing($msgpackData['params'],$userID);
                break; 

            case "adminLogin":
                $outputArray = $admin->adminLogin($msgpackData['params']);
                break;

            case "getRoles":
                $outputArray = $user->getRoles($msgpackData['params']);
                break;

            case "getAdminList":
                $outputArray = $admin->getAdminList($msgpackData['params']);
                break;

            case "getAdminDetails":
                $outputArray = $admin->getAdminDetails($msgpackData['params']);
                break;

            case "addAdmins":
                $outputArray = $admin->addAdmins($msgpackData['params']);
                break;

            case "editAdmins":
                $outputArray = $admin->editAdmins($msgpackData['params']);
                break;

            case "getPortfolioList":
                $outputArray = $admin->getPortfolioList($msgpackData['params']);
                break;

            case "getClientPortfolioList":
                $outputArray = $admin->getClientPortfolioList($msgpackData['params']);
                break;

            case "getMemberList":
                $outputArray = $admin->getMemberList($msgpackData['params']);
                break;

            case "getMemberDetails":
                $outputArray = $admin->getMemberDetails($msgpackData['params']);
                break;

            case "editMemberDetails":
                $outputArray = $admin->editMemberDetails($msgpackData['params']);
                break;

            case "changeMemberPassword":
                $outputArray = $admin->changeMemberPassword($msgpackData['params']);
                break;

            case "getInvoiceList":
                $outputArray = $admin->getInvoiceList($msgpackData['params']);
                break;

            case "getRankMaintain":
                $outputArray = $admin->getRankMaintain($msgpackData['params']);
                break;

            case "updateRankMaintain":
                $outputArray = $admin->updateRankMaintain($msgpackData['params']);
                break;

            case "getInvoiceDetail":
                $outputArray = $admin->getInvoiceDetail($msgpackData['params']);
                break;

            case "memberRegistrationAdmin":
                $outputArray = $client->memberRegistration($msgpackData['params']);
                break;

            case "memberRegistrationConfirmationAdmin":
                $outputArray = $client->memberRegistrationConfirmation($msgpackData['params']);
                break;

            case "verifyPaymentAdmin":
                $outputArray = $client->verifyPayment($msgpackData['params']);
                break;

            case "getCreditTransactionList":
                $outputArray = $client->getCreditTransactionList($msgpackData['params']);
                break;

            case "getPinList":
                $outputArray = $client->getPinList($msgpackData['params']);
                break;

            case "enquiry":
                $outputArray = $ticket->enquiry($msgpackData['params']);
                break;
            case "getPinDetail":
                $outputArray = $client->getPinDetail($msgpackData['params']);
                break;

            case "updatePinDetail":
                $outputArray = $client->updatePinDetail($msgpackData['params']);
                break;

            case "getPinPurchaseFormDetail":
                $outputArray = $client->getPinPurchaseFormDetail($msgpackData['params']);
                break;

            case "purchasePin":
                $outputArray = $client->purchasePin($msgpackData['params']);
                break;

            case "reentryPin":
                $outputArray = $client->reentryPin($msgpackData['params']);
                break;

            case "getRepurchasePackagePaymentDetailAdmin":
                $outputArray = $client->getRepurchasePackagePaymentDetail($msgpackData['params']);
                break;

            case "reentryPackageAdmin":
                $outputArray = $client->reentryPackage($msgpackData['params']);
                break;

            case "getProductDetail":
                $outputArray = $admin->getProductDetail($msgpackData['params']);
                break;

            case "getActivityLogList":
                $outputArray = $admin->getActivityLogList($msgpackData['params']);
                break;

            case "getLanguageTranslationList":
                $outputArray = $admin->getLanguageTranslationList($msgpackData['params']);
                break;

            case "getLanguageTranslationData":
                $outputArray = $admin->getLanguageTranslationData($msgpackData['params']);
                break;

            case "editLanguageTranslationData":
                $outputArray = $admin->editLanguageTranslationData($msgpackData['params']);
                break;

            case "getExchangeRateList":
                $outputArray = $admin->getExchangeRateList($msgpackData['params']);
                break;

            case "editExchangeRate":
                $outputArray = $admin->editExchangeRate($msgpackData['params']);
                break;

            case "getUnitPriceList":
                $outputArray = $admin->getUnitPriceList($msgpackData['params']);
                break;

            case "addUnitPrice":
                $outputArray = $admin->addUnitPrice($msgpackData['params']);
                break;

            case "getAdminWithdrawalList":
                $outputArray = $admin->getAdminWithdrawalList($msgpackData['params']);
                break;

            case "adminCancelWithdrawal":
                $outputArray = $admin->adminCancelWithdrawal($msgpackData['params']);
                break;

            case "getAdminClientWithdrawalDetail":
                $outputArray = $admin->getAdminClientWithdrawalDetail($msgpackData['params']);
                break;

            case "approveWithdrawal":
                $outputArray = $admin->approveWithdrawal($msgpackData['params']);
                break;

            case "editAdjustmentDetailAdmin":
                $outputArray = $admin->editAdjustmentDetail($msgpackData['params']);
                break;

            case "checkProductAndGetClientCreditType":
                $outputArray = $client->checkProductAndGetClientCreditType($msgpackData['params']);
                break;

            case "getTreeSponsor":
                $outputArray = $client->getTreeSponsor($msgpackData['params']);
                break;

            case "getTreePlacement":
                $outputArray = $client->getTreePlacement($msgpackData['params']);
                break;

            case "getSponsorTreeTextView":
                $outputArray = $client->getSponsorTreeTextView($msgpackData['params']);
                break;

            case "getSponsorTreeVerticalView":
                $outputArray = $tree->getSponsorTree($msgpackData['params']);
                break;

            case "getPlacementTreeVerticalView":
                $outputArray = $client->getPlacementTreeVerticalView($msgpackData['params']);
                break;

            case "getUpline":
                $outputArray = $client->getUpline($msgpackData['params']);
                break;

            case "getSponsor":
                $outputArray = $client->getSponsor($msgpackData['params']);
                break;

            case "getPlacement":
                $outputArray = $client->getPlacement($msgpackData['params']);
                break;

            case "changeSponsor":
                $outputArray = $client->changeSponsor($msgpackData['params']);
                break;

            case "changePlacement":
                $outputArray = $client->changePlacement($msgpackData['params']);
                break;

            case "getAnnouncementList":
                $outputArray = $announcement->getAnnouncementList($msgpackData['params']);
                break;

            case "addAnnouncement":
                $outputArray = $announcement->addAnnouncement($msgpackData['params'], $site);
                break;

            case "getAnnouncement":
                $outputArray = $announcement->getAnnouncement($msgpackData['params']);
                break;

            case "editAnnouncement":
                $outputArray = $announcement->editAnnouncement($msgpackData['params'], $site);
                break;

            case "removeAnnouncement":
                $outputArray = $announcement->removeAnnouncement($msgpackData['params']);
                break;

            case "getMemoList":
                $outputArray = $memo->getMemoList($msgpackData['params']);
                break;

            case "addMemo":
                $outputArray = $memo->addMemo($msgpackData['params'], $site);
                break;

            case "getMemo":
                $outputArray = $memo->getMemo($msgpackData['params']);
                break;

            case "editMemo":
                $outputArray = $memo->editMemo($msgpackData['params'], $site);
                break;

            case "removeMemo":
                $outputArray = $memo->removeMemo($msgpackData['params']);
                break;

            case "getDocumentList":
                $outputArray = $document->getDocumentList($msgpackData['params']);
                break;

            case "addDocument":
                $outputArray = $document->addDocument($msgpackData['params'], $site);
                break;

            case "getDocument":
                $outputArray = $document->getDocument($msgpackData['params']);
                break;

            case "editDocument":
                $outputArray = $document->editDocument($msgpackData['params'], $site);
                break;

            case "removeDocument":
                $outputArray = $document->removeDocument($msgpackData['params']);
                break;

            case "getTicketList":
                $outputArray = $admin->getTicketList($msgpackData['params']);
                break;

            case "getTicketDetail":
                $outputArray = $admin->getTicketDetail($msgpackData['params']);
                break;

            case "replyTicket":
                $outputArray = $admin->replyTicket($msgpackData['params'], $site);
                break;

            case "updateTicketStatus":
                $outputArray = $admin->updateTicketStatus($msgpackData['params']);
                break;

            case "massChangePassword":
                $outputArray = $client->massChangePassword($msgpackData['params'], $site);
                break;

            case "getImportData":
                $outputArray = $client->getImportData($msgpackData['params']);
                break;

            case "getImportDataDetails":
                $outputArray = $client->getImportDataDetails($msgpackData['params']);
                break;

            case "getCountriesList":
                $outputArray = $country->getCountriesList($msgpackData['params']);
                break;

            case "getRegistrationDetailAdmin":
                $outputArray = $client->getRegistrationDetails($msgpackData['params']);
                break;

            case "getRegistrationPackageDetailAdmin":
                $outputArray = $client->getRegistrationPackageDetails($msgpackData['params']);
                break;

            case "getRegistrationPaymentDetailAdmin":
                $outputArray = $client->getRegistrationPaymentDetails($msgpackData['params']);
                break;

            case "paymentPackageRegistration":
                $outputArray = $client->paymentPackageRegistration($msgpackData['params']);
                break;

            case "getViewMemberDetails":
                $outputArray = $client->getViewMemberDetails($msgpackData['params']);
                break;

            case "getClientRepurchasePinDetail":
                $outputArray = $client->getClientRepurchasePinDetail($msgpackData['params']);
                break;

            case "getClientRepurchasePackageDetailAdmin":
                $outputArray = $client->getClientRepurchasePackageDetail($msgpackData['params']);
                break;

            case "getRepurchasePackageSuccessDetailAdmin":
                $outputArray = $client->getRepurchasePackageSuccessDetail($msgpackData['params']);
                break;

            case "getMemberAccList":
                $outputArray = $admin->getMemberAccList($msgpackData['params']);
                break;

            case "getMemberDetailsListAdmin":
                $outputArray = $admin->getMemberDetailsList($msgpackData['params']);
                break;

            case "getMemberCreditsTransaction":
                $outputArray = $admin->getMemberCreditsTransaction($msgpackData['params']);
                break;

            case "getMemberBalanceAdmin":
                $outputArray = $admin->getMemberBalance($msgpackData['params']);
                break;

            case "transferCreditAdmin":
                $outputArray = $admin->transferCredit($msgpackData['params']);
                break;

            case "transferCreditConfirmationAdmin":
                $outputArray = $admin->transferCreditConfirmation($msgpackData['params']);
                break;

            case "getWithdrawalBankList":
                $outputArray = $admin->getWithdrawalBankList($msgpackData['params']);
                break;

            case "getWithdrawalDetailAdmin":
                $outputArray = $admin->getWithdrawalDetail($msgpackData['params']);
                break;

            case "addNewWithdrawalAdmin":
                $outputArray = $admin->addNewWithdrawal($msgpackData['params']);
                break;

            case "getBankAccountListAdmin":
                $outputArray = $client->getBankAccountList($msgpackData['params']);
                break;

            case "updateBankAccStatusAdmin":
                $outputArray = $client->updateBankAccStatus($msgpackData['params']);
                break;

            case "getLeaderGroupSalesReport":
                $outputArray = $report->getLeaderGroupSalesReport($msgpackData['params']);
                break;

            case "getSalesPlacementReport":
                $outputArray = $report->getSalesPlacementReport($msgpackData['params']);
                break;

            case "getSalesPurchaseReport":
                $outputArray = $report->getSalesPurchaseReport($msgpackData['params']);
                break;

            case "getCustomerServiceMemberDetails":
                $outputArray = $client->getCustomerServiceMemberDetails("", $msgpackData['params']);
                break;

            case "getLanguageCodeList":
                $outputArray = $language->getLanguageCodeList($msgpackData['params']);
                break;

            case "getLanguageCodeData":
                $outputArray = $language->getLanguageCodeData($msgpackData['params']);
                break;

            case "editLanguageCodeData":
                $outputArray = $language->editLanguageCodeData($msgpackData['params']);
                break;

            // Member Site
            case "memberLogin":
                $outputArray = $client->memberLogin($msgpackData['params']);
                break;

            case "getDashboard":
                $outputArray = $dashboard->getDashboard($msgpackData['params']);
                break;

            case "getTransactionHistory":
                $outputArray = $admin->getMemberDetailsList($msgpackData['params']);
                break;

            case "memberTransferCredit":
                $outputArray = $admin->transferCredit($msgpackData['params']);
                break;

            case "memberTransferCreditConfirmation":
                $outputArray = $admin->transferCreditConfirmation($msgpackData['params']);
                break;

            case "getMemberBankList":
                $outputArray = $client->getMemberBankList($msgpackData['params']);
                break;

            case "memberAddNewWithdrawal":
                $outputArray = $admin->addNewWithdrawal($msgpackData['params']);
                break;

            case "getWithdrawalListing":
                $outputArray = $client->getWithdrawalListing($msgpackData['params']);
                break;

            case "documentDownloadList":
                $outputArray = $document->documentDownloadList($msgpackData['params']);
                break;

            case "documentDownload":
                $outputArray = $document->documentDownload($msgpackData['params']);
                break;

            case "newsDisplay":
                $outputArray = $announcement->newsDisplay($msgpackData['params']);
                break;

            case "newsDownload":
                $outputArray = $announcement->newsDownload($msgpackData['params']);
                break;

            case "getInboxListing":
                $outputArray = $ticket->getInboxListing($msgpackData['params']);
                break;

            case "getInboxMessages":
                $outputArray = $ticket->getInboxMessages($msgpackData['params']);
                break;

            case "addInboxMessages":
                $outputArray = $ticket->addInboxMessages($msgpackData['params'], $site);
                break;

            case "getPackageDetail":
                $outputArray = $client->getPackageDetail($msgpackData['params']);
                break;

            case "getRepurchasePackagePaymentDetailClient":
                $outputArray = $client->getRepurchasePackagePaymentDetail($msgpackData['params']);
                break;

            case "reentryPackageClient":
                $outputArray = $client->reentryPackage($msgpackData['params']);
                break;

            case "getRepurchasePackageSuccessDetailClient":
                $outputArray = $client->getRepurchasePackageSuccessDetail($msgpackData['params']);
                break;

            case "getPin":
                $outputArray = $client->getPin($msgpackData['params']);
                break;

            case "transferPin":
                $outputArray = $client->transferPin($msgpackData['params']);
                break;

            case "getSponsorBonusList":
                $outputArray = $client->getSponsorBonusList($msgpackData['params']);
                break;

            case "getRegistrationDetailMember":
                $outputArray = $client->getRegistrationDetails($msgpackData['params']);
                break;

            case "memberRegistrationMember":
                $outputArray = $client->memberRegistration($msgpackData['params']);
                break;

            case "getRegistrationPaymentDetailMember":
                $outputArray = $client->getRegistrationPaymentDetails($msgpackData['params']);
                break;

            case "verifyPaymentMember":
                $outputArray = $client->verifyPayment($msgpackData['params']);
                break;

            case "getRegistrationPackageDetailMember":
                $outputArray = $client->getRegistrationPackageDetails($msgpackData['params']);
                break;

            case "memberRegistrationConfirmationMember":
                $outputArray = $client->memberRegistrationConfirmation($msgpackData['params']);
                break;

            case "memberChangePassword":
                $outputArray = $client->memberChangePassword($msgpackData['params']);
                break;

            case "memberChangeTransactionPassword":
                $outputArray = $client->memberChangePassword($msgpackData['params']);
                break;

            case "getBankAccountListMember":
                $outputArray = $client->getBankAccountList($msgpackData['params']);
                break;

            case "getBankAccountDetailMember":
                $outputArray = $client->getBankAccountDetail($msgpackData['params']);
                break;

            case "addBankAccountDetailMember":
                $outputArray = $client->addBankAccountDetail($msgpackData['params']);
                break;

            case "updateBankAccStatusMember":
                $outputArray = $client->updateBankAccStatus($msgpackData['params']);
                break;

            case "getMemberDetailMember":
                $outputArray = $admin->getMemberDetails($msgpackData['params']);
                break;

            case "editMemberDetailMember":
                $outputArray = $admin->editMemberDetails($msgpackData['params']);
                break;

            case "getMemberLoginDetail":
                $outputArray = $admin->getMemberLoginDetail($msgpackData['params']);
                break;

            case "getWhoIsOnlineList":
                $outputArray = $admin->getWhoIsOnlineList($msgpackData['params']);
                break;

            case "getClientRightsList":
                $outputArray = $admin->getClientRightsList($msgpackData['params']);
                break;

            case "lockAccount":
                $outputArray = $admin->lockAccount($msgpackData['params']);
                break;

            case "getWallets":
                $outputArray = $dashboard->getWallets($msgpackData['params']);
                break;

            case "addRole":
                $outputArray = $user->addRole($msgpackData['params']);
                break;

            case "getRoleDetails":
                $outputArray = $user->getRoleDetails($msgpackData['params']);
                break;

            case "getPermissions":
                $outputArray = $permission->getPermissions($msgpackData['params']);
                break;

            case "getRoleNames":
                $outputArray = $permission->getRoleNames();
                break;

            case "getPermissionNames":
                $outputArray = $permission->getPermissionNames($msgpackData['params']);
                break;

            case "getRolePermissionData":
                $outputArray = $permission->getRolePermissionData($msgpackData['params']);
                break;

            case "deleteRole":
                $outputArray = $user->deleteRole($msgpackData['params']);
                break;

            case "editRolePermission":
                $outputArray = $permission->editRolePermission($msgpackData['params']);
                break;

            case "getPaymentMethodList":
                $outputArray = $admin->getPaymentMethodList($msgpackData['params']);
                break;

            case "getPaymentMethodDetails":
                $outputArray = $admin->getPaymentMethodDetails($msgpackData['params']);
                break;

            case "editPaymentMethod":
                $outputArray = $admin->editPaymentMethod($msgpackData['params']);
                break;

            case "deletePaymentMethod":
                $outputArray = $admin->deletePaymentMethod($msgpackData['params']);
                break;

            case "getPaymentSettingDetails":
                $outputArray = $admin->getPaymentSettingDetails();
                break;

            case "addPaymentMethod":
                $outputArray = $admin->addPaymentMethod($msgpackData['params']);
                break;

            case "getSponsorBonusReport":
                $outputArray = $bonus->getSponsorBonusReport($msgpackData['params']);
                break;

            case "getPairingBonusReport":
                $outputArray = $bonus->getPairingBonusReport($msgpackData['params']);
                break;

            case "getMatchingBonusReport":
                $outputArray = $bonus->getMatchingBonusReport($msgpackData['params']);
                break;

            case "getRebateBonusReport":
                $outputArray = $bonus->getRebateBonusReport($msgpackData['params']);
                break;

            case "getWaterBucketBonusReport":
                $outputArray = $bonus->getWaterBucketBonusReport($msgpackData['params']);
                break;

            case "getPlacementBonusReport":
                $outputArray = $bonus->getPlacementBonusReport($msgpackData['params']);
                break;

            case "getPairingBonusList":
                $outputArray = $client->getPairingBonusList($msgpackData['params']);
                break;

            case "getRebateBonusList":
                $outputArray = $client->getRebateBonusList($msgpackData['params']);
                break;

            case "getWaterBucketBonusList":
                $outputArray = $client->getWaterBucketBonusList($msgpackData['params']);
                break;

            case "getMatchingBonusList":
                $outputArray = $client->getMatchingBonusList($msgpackData['params']);
                break;

            case "getLanguageVersion":
                $outputArray = array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => array('languageVersion' => $setting->getLanguageVersion()));
                break;

            case "getLanguageTranslations":
                $outputArray = $language->getLanguageTranslations();
                break;

            case "verifyRepurchasePackageDetail":
                $outputArray = $client->verifyRepurchasePackageDetail($msgpackData['params']);
                break;

            case "getPlacementBonusList":
                $outputArray = $client->getPlacementBonusList($msgpackData['params']);
                break;

            case "getLanguageList":
                $outputArray = $language->getLanguageList($msgpackData['params']);
                break;

            case "cancelSale":
                $outputArray = $admin->cancelSale($msgpackData['params']);
                break;

            //XUN Admin APIs//
                
            case "adminGetUserDetails":
                $outputArray = $xunAdmin->adminGetUserDetails($msgpackData['params']);
                break;

            case "adminEditUser":
                $outputArray = $xunAdmin->adminEditUser($msgpackData['params']);
                break;

            case "adminGetUserListing":
                $outputArray = $xunAdmin->adminGetUserListing($msgpackData['params']);
                break;

            case "adminGetUserTopupHistory":
                $outputArray = $xunAdmin->adminGetUserTopupHistory($msgpackData['params']);
                break;

            case "adminGetUserUsageHistory":
                $outputArray = $xunAdmin->adminGetUserUsageHistory($msgpackData['params']);
                break;

            case "adminUserAddTeamMember":
                $outputArray = $xunAdmin->admin_business_employee_add($msgpackData['params']);
                break;

            case "adminUserEditTeamMember":
                $outputArray = $xunAdmin->admin_business_employee_edit($msgpackData['params']);
                break;

            case "adminUserAddCategory":
                $outputArray = $xunAdmin->admin_business_tag_add($msgpackData['params']);
                break;

            case "adminUserEditCategory":
                $outputArray = $xunAdmin->admin_business_tag_edit($msgpackData['params']);
                break;

            case "adminGetUserFollow":
                $outputArray = $xunAdmin->adminGetUserFollow($msgpackData['params']);
                break;

            case "adminGetTopupHistory":
                $outputArray = $xunAdmin->adminGetTopupHistory($msgpackData['params']);
                break;

            case "adminGetUsageHistory":
                $outputArray = $xunAdmin->adminGetUsageHistory($msgpackData['params']);
                break;

            case "adminGetUserTeamMember":
                $outputArray = $xunAdmin->admin_business_employee_get($msgpackData['params']);
                break;

            case "adminGetUserFreezedListing":
                $outputArray = $xunAdmin->adminGetUserFreezedListing($msgpackData['params']);
                break;

            case "adminGetUserCategory":
                $outputArray = $xunAdmin->admin_business_tag_list($msgpackData['params']);
                break;

            case "adminGetUserConfirmedMember":
                $outputArray = $xunAdmin->admin_business_employee_confirm_list($msgpackData['params']);
                break;

            case "adminGetInvoiceDetails":
                $outputArray = $xunAdmin->adminGetInvoiceDetails($msgpackData['params']);
                break;

            case "adminGetCategoryDetails":
                $outputArray = $xunAdmin->admin_business_tag_get($msgpackData['params']);
                break;

            case "adminGetTeamMemberDetails":
                $outputArray = $xunAdmin->admin_business_employee_details_get($msgpackData['params']);
                break;

            case "getXunCountryList":
                $outputArray = $xunAdmin->getXunCountryList($msgpackData['params']);
                break;

            case "adminDeleteAllUserTeamMember":
                $outputArray = $xunAdmin->admin_business_employee_delete_all($msgpackData['params']);
                break;

            case "adminDeleteUserTeamMember":
                $outputArray = $xunAdmin->admin_business_employee_delete($msgpackData['params']);
                break;

            case "adminDeleteAllUserCategory":
                $outputArray = $xunAdmin->admin_business_tag_delete_all($msgpackData['params']);
                break;

            case "adminDeleteUserCategory":
                $outputArray = $xunAdmin->admin_business_tag_delete($msgpackData['params']);
                break;

            case "adminGetReferralList":
                $outputArray = $xunAdmin->get_business_listing($msgpackData['params']);
                break;

            case "contactUs":
                $outputArray = $xunAdmin->contactUs($msgpackData, strtolower($source));
                break;

            case "adminGetUserVerificationCodeListing":
                $outputArray = $xunAdmin->adminGetUserVerificationCodeListing($msgpackData['params']);
                break;

            case "adminAddNews":
                $outputArray = $xunAdmin->add_news($msgpackData['params']);
                break;

            case "adminAddArticle":
                $outputArray = $xunAdmin->add_article($msgpackData['params']);
                break;

            case "adminAddVideo":
                $outputArray = $xunAdmin->add_video($msgpackData['params']);
                break;

            case "adminEditNews":
                $outputArray = $xunAdmin->edit_news($msgpackData['params']);
                break;

            case "adminEditArticle":
                $outputArray = $xunAdmin->edit_article($msgpackData['params']);
                break;
       
            case "adminEditVideo":
                $outputArray = $xunAdmin->edit_video($msgpackData["params"]);
                break;

            case "adminGetNewsDetails":
                $outputArray = $xunAdmin->get_news_details($msgpackData["params"]);
                break;

            case "adminGetArticleDetails":
                $outputArray = $xunAdmin->get_article_details($msgpackData['params']);
                break;

            case "adminGetVideoDetails":
                $outputArray = $xunAdmin->get_video_details($msgpackData['params']);
                break;

            case "adminGetNewsListing":
                $outputArray = $xunAdmin->get_news_listing($msgpackData['params']);
                break;

            case "adminGetArticleListing":
                $outputArray = $xunAdmin->get_article_listing($msgpackData['params']);
                break;

            case "adminGetWalletType":
                $outputArray = $xunAdmin->get_wallet_type($msgpackData['params']);
                break;

            case "adminGetVideoListing":
                $outputArray = $xunAdmin->get_video_listing($msgpackData['params']);
                break;

            case "adminDeleteNews":
                $outputArray = $xunAdmin->delete_news($msgpackData['params']);
                break;

            case "adminDeleteArticle":
                $outputArray = $xunAdmin->delete_article($msgpackData['params']);
                break;

            case "adminDeleteVideo":
                $outputArray = $xunAdmin->delete_video($msgpackData['params']);
                break;

            case "adminGetBlogImagePresignedUrl":
                $outputArray = $xunAdmin->get_blog_image_presign_url($msgpackData['params']);
                break;

            case "adminGetCommissionListing":
                $outputArray = $xunAdmin->get_commission_listing($msgpackData['params']);
                break;

            //Ticketing
            case "addTicket":
                $outputArray = $ticket->addTicket($msgpackData['params']);
                break;

            case "getTicketItemAttachment":
                $outputArray = $ticket->getTicketItemAttachment($msgpackData['params']);
                break;

            case "getTicketDefaultData":
                $outputArray = $ticket->getTicketDefaultData($msgpackData['params']);
                break;

            case "unassignTickets":
                $outputArray = $ticket->unassignTickets($msgpackData['params']);
                break;

            case "updateTicket":
                $outputArray = $ticket->updateTicket($msgpackData['params']);
                break;

            case "getTicketDetails":
                $outputArray = $ticket->getTicketDetails($msgpackData['params']);
                break;

            case "deleteTicket":
                $outputArray = $ticket->deleteTicket($msgpackData['params']);
                break;

            case "getTicket":
                $outputArray = $ticket->getTicket($msgpackData['params']);
                break;
            //End Ticketing
                
            //XUN Admin APIs//

            case "adminLivechatSettingGet":
                $outputArray = $xunAdmin->get_livechat_setting_admin($msgpackData['params']);
                break;

            case "adminLivechatSettingAdd":
                $outputArray = $xunAdmin->add_edit_setting_admin($msgpackData['params']);
                break;

            case "adminGetUserList":
                $outputArray = $xunAdmin->adminGetUserList($msgpackData['params']);
                break;

            case "adminGetKYCList":
                $outputArray = $xunAdmin->get_user_kyc_listing($msgpackData['params']);
                break;
                
            case "adminAnnouncementCreate":
                $outputArray = $xunAnnouncement->announcement_create($msgpackData['params']);
                break;
                
            case "adminAnnouncementAudienceGet":
                $outputArray = $xunAnnouncement->audience_get($msgpackData['params']);
                break;
                
            case "adminAnnouncementS3UrlGet":
                $outputArray = $xunAnnouncement->get_announcement_image_presign_url($msgpackData['params']);
                break;
                
            case "adminAnnouncementEdit":
                $outputArray = $xunAnnouncement->announcement_edit($msgpackData['params']);
                break;
                
            case "adminAnnouncementHistory":
                $outputArray = $xunAnnouncement->announcement_list($msgpackData['params']);
                break;
                
            case "adminAnnouncementRecipientHistory":
                $outputArray = $xunAnnouncement->announcement_recipient_list($msgpackData['params']);
                break;
                
            case "adminAnnouncementDetails":
                $outputArray = $xunAnnouncement->announcement_details($msgpackData['params']);
                break;
                
            case "adminAnnouncementDelete":
                $outputArray = $xunAnnouncement->announcement_delete($msgpackData['params']);
                break;
                
            case "adminGetDisputeList":
                $outputArray = $xunAdmin->get_dispute_listing($msgpackData['params']);
                break;

            case "adminGetSpecificDisputeDetails":
                $outputArray = $xunAdmin->get_specific_dispute_details($msgpackData['params']);
                break;

            case "adminDisputeActionPerform":
                $outputArray = $xunAdmin->specific_dispute_details_action($msgpackData['params']);
                break;

            case "adminGetSpecificEscrowInbox":
                    $outputArray = $xunAdmin->get_specific_escrow_inbox($msgpackData['params']);
                    break;
    
            //  Start crowdfunding APIs
            case "adminGetCrowdfundingListing":
                $outputArray = $xunAdmin->adminGetCrowdfundingListing($msgpackData['params']);
                break;
            //  End crowdfunding APIs
            case "adminGetBusinessCommission":
                $outputArray = $xunAdmin->adminGetBusinessCommission($msgpackData['params']);
                break;

            case "adminGetCommissionDetails":
                $outputArray = $xunAdmin->adminGetCommissionDetails($msgpackData['params']);
                break;

            // Start Story APIs
            case "adminGetStoryDetails":
                $outputArray = $xunAdmin->adminGetStoryDetails($msgpackData['params']);
                break;

            case "adminGetStoryListing":
                $outputArray = $xunAdmin->adminGetStoryListing($msgpackData['params']);
                break;

            case "adminEditStoryDetails":
                $outputArray = $xunAdmin->admin_story_details_edit($msgpackData['params']);
                break;
        
            // End Story APIs

            //Start Admin Commission and Transaction APIs//

            case "adminGetTransactionListing":
                $outputArray = $xunAdmin->admin_get_transaction_list($msgpackData['params']);
                break;

            case "adminGetCommissionContributeReceiveListing":
                $outputArray = $xunAdmin->admin_get_commission_contribute_receive_list($msgpackData['params']);
                break;

            case "adminGetCommissionContributedDetails":
                $outputArray = $xunAdmin->admin_get_commission_contributed_details($msgpackData['params']);
                break;

            case "adminGetCommissionReceivedDetails":
                $outputArray = $xunAdmin->admin_get_commission_received_details($msgpackData['params']);
                break;

            case "adminGetTxSummary":
                $outputArray = $xunAdmin->admin_get_tx_summary($msgpackData['params']);
                break;

            case "adminMobileSubmitList":
                $outputArray = $xunAdmin->admin_mobile_submit_listing($msgpackData['params']);
                break;

            //End Admin Commission and Transaction APIs//

            // Start Admin Cashpool API//
            case "adminApproveCashpoolTopup":
                $outputArray = $xunAdmin->admin_approve_cashpool_topup($msgpackData['params']);
                break;

            case "adminCashpoolTopupList":
                $outputArray = $xunAdmin->admin_cashpool_topup_list($msgpackData['params']);
                break;

            //  Start Admin Export APIs //
            case "addExcelReq":
                $outputArray = $excel->addExcelReq($msgpackData['params']);
                break;
            case "getExcelReqList":
                $outputArray = $excel->getExcelReqList($msgpackData['params']);
                break;
            //  End Admin Export APIs //

            // Start Nuxpay Admin APIs //
            case "adminNuxpayTotalCoinTransactionList":
                $outputArray = $xunAdmin->nuxpay_admin_total_coin_transaction_amount_list($msgpackData['params']);
                break;

            case "adminNuxpayTopTenTransactionList":
                $outputArray = $xunAdmin->admin_nuxpay_top_ten_transaction_list($msgpackData['params']);
                break;

            case "adminNuxpayTopTenMerchantList":
                $outputArray = $xunAdmin->admin_nuxpay_top_ten_merchants_list($msgpackData['params']);
                break;

            case "adminNuxpayLatestTransactionList":
                $outputArray = $xunAdmin->admin_nuxpay_latest_transaction_list($msgpackData['params']);
                break;

            case "adminNuxpayTopMerchantServiceFee":
                $outputArray = $xunAdmin->admin_nuxpay_top_merchant_service_fee($msgpackData['params']);
                break;

            case "adminNuxpayTransactionHistoryList":
                $outputArray = $xunAdmin->admin_nuxpay_transaction_history_listing($msgpackData['params']);
                break;
		
	        case "adminNuxpayAuditSummaryReport":
                $outputArray = $xunAdmin->admin_nuxpay_audit_summary_report($msgpackData['params']);
	    	    break;

            case "adminNuxpayMerchantList":
                $outputArray = $xunAdmin->admin_nuxpay_merchant_list($msgpackData['params'], $site, $userID);
                break;

            case "adminNuxpayMerchantDetails":
                $outputArray = $xunAdmin->admin_nuxpay_merchant_details($msgpackData['params']);
                break;

            case "adminNuxpayTransactionHistoryDetails":
                $outputArray = $xunAdmin->admin_nuxpay_transaction_history_details($msgpackData['params']);
                break;

            case "adminNuxpayDashboardStatistics":
                $outputArray = $xunAdmin->admin_nuxpay_dashboard_statistics($msgpackData['params']);
                break;
            
            case "adminNuxpayGetMinerFeeReport":
                $outputArray = $xunAdmin->admin_nuxpay_get_miner_fee_report($msgpackData['params']);
                break;

            case "adminNuxpayGetMinerFeeDetails":
                $outputArray = $xunAdmin->nuxpay_get_miner_fee_details($msgpackData['params']);
                break;

            case "adminCreate":
                $outputArray = $xunAdmin->create_admin($msgpackData['params']);
                break;

            case "adminRolesListing" : 
                $outputArray = $xunAdmin->admin_roles_listing($msgpackData['params']);
                break;

            case "adminListing" :
                $outputArray = $xunAdmin->admin_listing($msgpackData['params']);
                break; 

            case "adminPermissionListing":
                $outputArray = $xunAdmin->admin_permission_listing($msgpackData['params']);
                break;
            
            case "adminCreateRoles":
                $outputArray = $xunAdmin->create_role($msgpackData['params']);
                break;

            case "adminChangePassword" : 
                $outputArray = $xunAdmin->admin_change_password($msgpackData['params']);
                break;

            case "adminNuxpayCreateUser" : 
                $outputArray = $xunAdmin->create_nuxpay_user($msgpackData['params']);
                break;

            case "adminEditAdminDetails" :
                $outputArray = $xunAdmin->edit_admin_details($msgpackData['params']);
                break;

            case "adminGetAdminDetails" :
                $outputArray = $xunAdmin->get_admin_details($msgpackData['params']);
                break;

            case "adminGetFundOutListing":
                $outputArray = $xunAdmin->admin_get_fund_out_listing($msgpackData['params']);
                break;

            case "adminbuySellCryptoListing":
                $outputArray = $xunAdmin->admin_buy_sell_crypto_listing($msgpackData['params']);
                break;

            case "sellCryptoConfirmation":
                $outputArray = $xunAdmin->sell_crypto_confirmation($msgpackData['params']);
                break;
            
            case "adminGetWithdrawalHistory":
                $outputArray = $xunAdmin->admin_get_withdrawal_history($msgpackData['params'], $userID, $site);
                break;

            case "adminGetWithdrawalHistory2":
                $outputArray = $xunAdmin->admin_get_withdrawal_history2($msgpackData['params'], $userID, $site);
                break;

            case "adminGetSites":
                $outputArray = $xunAdmin->get_sites($msgpackData['params']);
                break;

            case "adminAddressListing":
                $outputArray = $xunAdmin->admin_address_listing($msgpackData['params']);
                break;

            case "adminChangeMerchantPassword":
                $outputArray = $xunAdmin->admin_change_merchant_password($msgpackData['params']);
                break;

            case "adminChangeMerchantDetails":
                $outputArray = $xunAdmin->admin_change_merchant_details($msgpackData['params']);
                break;
                
            case "adminWithdrawalAddress":
                $outputArray = $xunAdmin->admin_withdrawal_address($msgpackData['params']);
                break;

            case "adminGetApiKey":
                $outputArray = $xunAdmin->admin_get_api_key($msgpackData['params']);
                break;
                
            case "adminDisplayMerchantCallbackUrl":
                $outputArray = $xunAdmin->admin_display_merchant_callback_url($msgpackData['params']);
                break;
            
            case "adminGetApikeyDetails":
                $outputArray = $xunAdmin->admin_get_api_key($msgpackData['params']);
                break;

            case "adminFundOutDetails":
                $outputArray = $xunAdmin->admin_fund_out_details($msgpackData['params']);
                break;

            case "adminWithdrawalHistoryDetails":
                $outputArray = $xunAdmin->admin_withdrawal_history_details($msgpackData['params']);
            break;

            case "adminResellerApplicationListing":
                $outputArray = $xunAdmin->admin_reseller_application_listing($msgpackData['params']);
                break;

            case "adminResellerApprove":
                $outputArray = $xunAdmin->admin_reseller_approve($msgpackData['params'], $userID);
                break;

            // End Nuxpay Admin APIs //
            default:
                $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "Command not found.", 'data' => '');
                $find        = array("%%apiName%%");
                $replace     = array($command);
                $message->createMessageOut('90003', null, null, $find, $replace); //Send notification if Invalid Command.
                break;
        }
        
    } else {
        $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "You have been blocked from using this transaction", 'data' => "");
    }

    /***** For sending the Notifications. *****/
    $queries = $db->getQueryNumber(); // Need to add the Executed queries count.
    //For sending the Notification - API executes the no of queries.
    if ($queries > $apiSetting['no_of_queries']) {
        $find    = array("%%apiName%%", "%%apiAllowed%%", "%%apiCurrent%%");
        $replace = array($command, $apiSetting['no_of_queries'], $queries);
        $message->createMessageOut('90002', null, null, $find, $replace);
    }
    /***** For sending the Notification. *****/

    $completedTime = date("Y-m-d H:i:s");
    $processedTime = time() - $timeStart;

    $dataOut = $outputArray;
    $status  = $dataOut['status'];

    //For sending the Notification - API takes longer time.
    if ($processedTime > $apiSetting['duration']) {
        $find    = array("%%apiName%%", "%%apiTime%%", "%%seconds%%");
        $replace = array($command, $apiSetting['duration'], $processedTime);
        $message->createMessageOut('(90001', null, null, $find, $replace);
    }

    if ($command != "getWebservices") {
        $updateWebservice = $webservice->updateWebserviceData($webserviceID, $dataOut, $status, $completedTime, $processedTime, $tblDate, $queries);
    }

    ob_get_clean();
    echo $msgpack->msgpack_pack($outputArray);
}
