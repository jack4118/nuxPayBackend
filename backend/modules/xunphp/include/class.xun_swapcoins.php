<?php
class XunSwapcoins
{

    public function __construct($db, $general, $setting, $post, $binance, $account = null, $xunPaymentGateway = null)
    {
        $this->db = $db;
        $this->general = $general;
        $this->setting = $setting;
        $this->post = $post;
        $this->binance = $binance;
        $this->account = $account;
        $this->xunPaymentGateway = $xunPaymentGateway;

        include('config.php');
        // Swapcoin uses a different binance account
        // $this->binance = new Binance($config['swapcoins']['binanceAPIKey'], $config['swapcoins']['binanceAPISecret'], $config['swapcoins']['binanceAPIURL'], $config['swapcoins']['binanceWAPIURL']);

    }

    public function swapcoinsLogin($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $username = trim($params["username"]);

        if ($username == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Username is required.");
        }

        $xunUserService = new XunUserService($db);
        $xunUser = $xunUserService->getUserByUsername($username);

        if (!$xunUser) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist");
        }

        $nickname = $xunUser["nickname"];
        $accessToken = $setting->systemSetting["swapcoinsAccessToken"];

        $url = "https://swapcoins.net/forwarder.php";

        $command = "getMemberAppsLogin";
        $curlParams = array(
            "command" => $command,
            "parameters" => array(
                "username" => $username,
                "nickname" => $nickname,
                "accessToken" => $accessToken,
            ),
        );

        $curlResponse = $post->curl_post($url, $curlParams, 0, 1);

        if ($curlResponse["code"] === 0) {
            $token = $curlResponse["data"]["token"];

            $returnData = array(
                "token" => $token,
            );
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "You've logged in to SwapCoins.", "data" => $returnData);
        } else if ($curlResponse["code"] === 999) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "dev_msg" => $curlResponse["statusMsg"]);
        } else {
            return array("code" => 0, "message" => "FAILED", "message_d" => $curlResponse["statusMsg"]);
        }
    }

    public function getSupportedSwapCoinsListing($params) {
        global $xunCrypto;

        $walletType = trim($params['wallet_type']);
        
        $columns = "a.to_symbol, a.to_wallet_type, a.common_symbol, a.from_symbol, a.from_wallet_type, b.display_symbol as to_display_symbol, c.display_symbol as from_display_symbol";

        $this->db->where('name', "binance");
        $provider_source = $this->db->getValue('provider', 'company');
        // Retrieve the swap settings
        if ($walletType)
        // YF1
        $this->db->where('a.to_wallet_type', $walletType);
        $this->db->where('a.method', "buy");
        $this->db->where('a.disabled', 0);
        $this->db->groupBy('a.common_symbol');
        $this->db->join('xun_marketplace_currencies b', 'binary a.to_wallet_type=binary b.currency_id', 'LEFT');
        $this->db->join('xun_marketplace_currencies c', 'binary a.from_wallet_type=binary c.currency_id', 'LEFT');
        $resultRes = $this->db->get('xun_swap_setting a', null, $columns);



        // YF1.1
        foreach ($resultRes as $resultRow) {
            $result['symbol'] = $resultRow['to_symbol'];
            $result['from_symbol'] = $resultRow['from_symbol'];
            $result['display_symbol'] = strtoupper($resultRow['to_display_symbol']);
            $result['display_from_symbol'] = strtoupper($resultRow['from_display_symbol']);
            $result['wallet_type'] = $resultRow['to_wallet_type'];
            $result['from_wallet_type'] = $resultRow['from_wallet_type'];
            $result['common_symbol'] = $resultRow['common_symbol'];

            $this->db->where('currency_id', $resultRow['to_wallet_type']);
            $result['image'] = $this->db->getValue('xun_marketplace_currencies', "image");

            $this->db->where('currency_id', $resultRow['from_wallet_type']);
            $result['from_image'] = $this->db->getValue('xun_marketplace_currencies', "image");
            
            // $binanceResult = $this->binance->getPrice24hr($resultRow['common_symbol']);
            // if (!empty($binanceResult)) {
            //     $result['last_price'] = number_format($binanceResult['lastPrice'],2);
            //     $result['change_percent'] = number_format($binanceResult['priceChangePercent'],2)."%";
            //     $result['high'] = number_format($binanceResult['highPrice'],2);
            //     $result['low'] = number_format($binanceResult['lowPrice'],2);
            //     $result['volume'] = $binanceResult['volume'];
            // }
            $crypto_params = array(
                "providerSource" => $provider_source,
                "symbol" => $resultRow['common_symbol']
            );

            $binanceResult = $xunCrypto->crypto_get_price_24h($crypto_params);

            if(!empty($binanceResult)){
                $result['last_price'] = number_format($binanceResult['lastPrice'],2);
                $result['change_percent'] = number_format($binanceResult['priceChangePercent'],2);
                $result['high'] = number_format($binanceResult['highPrice'],2);
                $result['low'] = number_format($binanceResult['lowPrice'],2);
                $result['volume'] = $binanceResult['volume'];
            }
            $coinsList[] = $result;
        }    

        $all_wallet_type = array_merge(array_column($resultRes,'to_wallet_type'), array_column($resultRes,'from_wallet_type'));
        $all_wallet_type = array_unique($all_wallet_type); // remove repeated values
        $this->db->where('currency_id', $all_wallet_type, 'in');
        $currency_records = $this->db->get('xun_marketplace_currencies',null, array('currency_id', 'image', 'symbol', 'display_symbol'));

        foreach($currency_records as $currency_record) {
            $walletTypes[$currency_record['currency_id']] =$currency_record;
        }

        $data['coinsList'] = $coinsList;
        $data['walletTypes'] = $walletTypes;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "", "data" => $data);

    }

    public function getPreviewSwapCoinRate($params) {
        global $xunCrypto;

        $fromWalletType = trim($params['from_wallet_type']);
        $toWalletType = trim($params['to_wallet_type']);

        // Validation
        if (strlen($fromWalletType) == 0) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "From wallet type must not be empty.");
        }

        if (strlen($toWalletType) == 0) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "To wallet type must not be empty.");
        }

        // Retrieve the swap settings
        $result = $this->getSwapSetting($fromWalletType, $toWalletType);
        if ($result['code'] == "0") {
            return $result;
        }

        $settingRes = $result['data'];
        $fromSymbol = $settingRes['from_symbol'];
        $toSymbol = $settingRes['to_symbol'];
        $commonSymbol = $settingRes['common_symbol'];
        $method = $settingRes['method'];
        $marginPercentage = $settingRes['margin_percentage'];

        // Fetch the 24hr data
        // $binanceResult = $this->binance->getPrice24hr($commonSymbol);
        // if (!empty($binanceResult)) {
        //     $data['last_price'] = number_format($binanceResult['lastPrice'],2);
        //     $data['change_percent'] = $binanceResult['priceChangePercent'];
        //     $data['high'] = number_format($binanceResult['highPrice'],2);
        //     $data['low'] = number_format($binanceResult['lowPrice'],2);
        //     $data['volume'] = $binanceResult['volume'];

        //     if($data['change_percent']>0){
        //         $data['color'] = 'green';
        //     } else if ($data['change_percent'] ==0) {
        //         $data['color'] = 'black';
        //     } else {
        //         $data['color'] = 'red';
        //     }
        // }


        $this->db->where('name', "binance");
        $provider_source = $this->db->getValue('provider', 'company');

        $crypto_params = array(
            "providerSource" => $provider_source,
            "symbol" => $commonSymbol
        );

        $binanceResult = $xunCrypto->crypto_get_price_24h($crypto_params);

        if(!empty($binanceResult)){
            $data['last_price'] = number_format($binanceResult['lastPrice'],2);
            $data['change_percent'] = number_format($binanceResult['priceChangePercent'],2);
            $data['high'] = number_format($binanceResult['highPrice'],2);
            $data['low'] = number_format($binanceResult['lowPrice'],2);
            $data['volume'] = $binanceResult['volume'];

            if($data['change_percent']>0){
                $data['color'] = 'green';
            } else if ($data['change_percent'] ==0) {
                $data['color'] = 'black';
            } else {
                $data['color'] = 'red';
            }
        }

        // Retrieve the provider settings
        $this->db->where('name', "binance");
        $providerID = $this->db->getValue('provider', 'id');

        $this->db->where('provider_id', $providerID);
        $this->db->where('type', $commonSymbol);
        $providerSettingRes = $this->db->get('provider_setting');
        foreach ($providerSettingRes as $row) {
            $providerSetting[$row['name']] = $row['value'];
        }

        $lotStepSize = rtrim(rtrim($providerSetting['lotStepSize'], "0"), '.');

        // Check how many decimal points are allowed in the exchange
        $decimals = $this->getNumberOfDecimals($lotStepSize);

        // Call to binance to get the ticker price
        $marketPrice = $this->binance->getPrice($commonSymbol)['price'];
        if (empty($marketPrice)) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Unable to obtain a price now. Please try again later.");
        }

        // Get the markup price based on the setting we set in DB
        $markupBuyPrice = bcmul($marketPrice, (string)((100 + $marginPercentage) / 100), 8);
        $markupSellPrice = bcmul($marketPrice, (string)((100 - $marginPercentage) / 100), 8);

        $markupBuyExchangeRate = bcdiv("1", $markupBuyPrice, 8);
        $markupSellExchangeRate = $markupSellPrice;

        if ($method == 'buy') {
            $exchangeRate = bcdiv("1", $marketPrice, 8); 
            
            
            //$data['ori'] = $exchangeRate;
            $data['system_buy_rate'] = $markupBuyExchangeRate;
            $data['system_sell_rate'] = $markupSellExchangeRate;

        }
        else if ($method == 'sell') {
            $exchangeRate = $marketPrice; 
            //$markupSellExchangeRate = $markupSellPrice;
            
            //$data['ori'] = $exchangeRate;
            $data['system_buy_rate'] = $markupSellExchangeRate;
            $data['system_sell_rate'] = $markupBuyExchangeRate;
        }

        $this->db->where('currency_id', $toWalletType);
        $data['image'] = $this->db->getValue('xun_marketplace_currencies', "image");
        $data['commonSymbol'] = $commonSymbol;
        
        $this->db->where('currency_id', $toWalletType);
        $data['symbol'] = $this->db->getValue('xun_marketplace_currencies', "display_symbol");

        $this->db->where('from_wallet_type', $fromWalletType);
        $swapSettings = $this->db->get('xun_swap_setting');
        $availableToSymbols = array_column($swapSettings, 'to_wallet_type');
        
        $this->db->where('currency_id', $availableToSymbols, 'IN');
        $availableToSymbolsCurrencies = $this->db->get('xun_marketplace_currencies');
        foreach(array_values($availableToSymbolsCurrencies) as $i => $currencyRecord) {
            $data['availableToCurrency'][$currencyRecord['currency_id']]['image'] = $currencyRecord['image'];
            $data['availableToCurrency'][$currencyRecord['currency_id']]['symbol'] = strtoupper($currencyRecord['symbol']);
            $data['availableToCurrency'][$currencyRecord['currency_id']]['display_symbol'] = strtoupper($currencyRecord['display_symbol']);
            $data['availableToCurrency'][$currencyRecord['currency_id']]['wallet_type'] = strtoupper($currencyRecord['wallet_type']);
        }
        // $data['asdf'] = $availableToSymbolsCurrencies;



        return array("code" => 1, "status" => "ok", "message" => "SUCCESS", "message_d" => "Success", "data" => $data);


    }

    public function estimateSwapCoinRate($params, $selectedProvider) {
        global $xunCrypto;
        // Basic validation
        $result = $this->validateSwapParams($params);
        if ($result['code'] == "0") {
            return $result;
        }
        if (strlen($selectedProvider) == 0) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Selected provider params is null.");
        }

        $businessID = trim($params['businessID']);
        $fromWalletType = trim($params['fromWalletType']);
        $toWalletType = trim($params['toWalletType']);
        $fromAmount = trim($params['fromAmount']);
        $toAmount = trim($params['toAmount']);

        // Retrieve the swap settings
        $result = $this->getSwapSetting($fromWalletType, $toWalletType);
        if ($result['code'] == "0") {
            return $result;
        }
        
        $settingRes = $result['data'];
        $fromSymbol = $settingRes['from_symbol'];
        $toSymbol = $settingRes['to_symbol'];
        $commonSymbol = $settingRes['common_symbol'];
        $method = $settingRes['method'];
        $marginPercentage = $settingRes['margin_percentage'];

        // Retrieve the provider settings
        $isCoins2 = false;
        $this->db->where('provider_id', $selectedProvider);
        $this->db->where('type', $commonSymbol);
        $providerSettingRes = $this->db->get('provider_setting');
        // should be a coins2 swapping
        if ($providerSettingRes == null) {
            $this->db->where('type', 'coins2');
            $providerSettingRes = $this->db->get('provider_setting');
            
            $this->db->where('cryptocurrency_id', $fromWalletType);
            $this->db->where('currency', 'usd');
            $conversionRate = $this->db->getValue('xun_cryptocurrency_rate', 'value');
            $convertedFromAmount = bcmul((string)$fromAmount, (string)$conversionRate, 8);
            $isCoins2 = true;
        } 
        foreach ($providerSettingRes as $row) {
            $providerSetting[$row['name']] = $row['value'];
        }

        // First round validate setting
        if ($isCoins2) {
            if ($convertedFromAmount < $providerSetting['minSwapUSD']) {
                return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "From amount must be at least ".$providerSetting['minSwapUSD']. " USD for coins2 swap.");
            }
        } else {
            if ($fromAmount < $providerSetting['minSwap'.$settingRes['from_symbol']]) {
                // Check for min swap amount
                return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "From amount must be at least ".$providerSetting['minSwap'.$settingRes['from_symbol']]." ".$settingRes['from_symbol'].".");
            }
        }

        $lotStepSize = rtrim(rtrim($providerSetting['lotStepSize'], "0"), '.');

        // Check how many decimal points are allowed in the exchange
        $decimals = $this->getNumberOfDecimals($lotStepSize);

        // Retrieve the user's balance
        $userBalance = $this->xunPaymentGateway->get_user_balance($businessID, $fromWalletType);
        //$fromAmount = $userBalance;
        $fromAmount = bcmul((string)$fromAmount, "1", $decimals);

        if ($userBalance <= 0 || $fromAmount > $userBalance) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Insufficient $fromSymbol balance.");
        }

        // Call to binance to get the ticker price
        // $marketPrice = $this->binance->getPrice($commonSymbol)['price'];
        // if (empty($marketPrice)) {
        //     return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Unable to obtain a price now. Please try again later.", "error_code" => -100);
        // }

        $crypto_params = array(
            "fromWalletType"=> $fromWalletType,
            "toWalletType" => $toWalletType,
            "marginPercentage" => $marginPercentage
        );
        $crypto_result = $xunCrypto->get_market_price($crypto_params);

        if($crypto_result['status'] == 'error'){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_result['statusMsg']);
        }
        $marketPrice = $crypto_result['data']['exchangeRate'];
        $markupPrice = $crypto_result['data']['markupExchangeRate'];

        // if ($method == 'buy') {
        //     // Get the markup price based on the setting we set in DB
        //     $markupPrice = bcmul($marketPrice, (string)((100 + $marginPercentage) / 100), 8);
        //     $exchangeRate = bcdiv("1", $marketPrice, 8); 
        //     $markupExchangeRate = bcdiv("1", $markupPrice, 8);
        // }
        // else if ($method == 'sell') {
        //     // Get the markup price based on the setting we set in DB
        //     $markupPrice = bcmul($marketPrice, (string)((100 - $marginPercentage) / 100), 8);
        //     $exchangeRate = $marketPrice; 
        //     $markupExchangeRate = $markupPrice;

        //     $fromAmount = bcmul((string)$fromAmount, "1", $decimals);
        //     // if ($fromAmount <= 0) {
        //     //     return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "From Amount to swap is less than minimum swap amount.", "data" => array('debug' => "Amount $fromAmount after conversion to $decimals decimals. Original amount $userBalance."));
        //     // }
        // }
        
        $exchangeRate = $marketPrice;
        $markupExchangeRate = $markupPrice;
        $toAmount = bcmul((string)$fromAmount, (string)$markupExchangeRate, $decimals);
        $submitAmount = bcmul((string)$fromAmount, (string)$exchangeRate, $decimals);

        if ($toAmount <= 0) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Amount is too small for swap.");
        }

        // Recalculate the markup price due to binance decimals limit
        // if ($method == 'buy') {
        //     if ($fromAmount > 0) {
        //         $markupPrice = bcdiv((string)$fromAmount, $toAmount, 8);
        //         $markupExchangeRate = bcdiv("1", $markupPrice, 8);
        //     }
        // }
        // else if ($method == 'sell') { 
        //     $markupExchangeRate = $markupPrice;
        // }


        // if ($submitAmount < $providerSetting['minQty']) {
        //     return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Quantity is below min quantity.");
        // }

        // if ($submitAmount > $providerSetting['maxQty']) {
        //     return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Quantity exceeded max quantity.");
        // }

        // if ($marketPrice  < $providerSetting['minPrice']) {
        //     return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Amount to swap is lower than min price.");
        // }

        // if ($marketPrice > $providerSetting['maxPrice']) {
        //     return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Amount to swap exceeded max price.");
        // }

        //$debug['marketPrice'] = $marketPrice;
        //$debug['markupPrice'] = $markupPrice;
        //$debug['markupExchangeRate'] = $markupExchangeRate;
        //$debug['providerSetting'] = $providerSetting;
        //$debug['lotDecimals'] = $decimals;
        //$debug['lotStepSize'] = $lotStepSize;

        $this->db->where('id', $businessID);
        $userRes = $this->db->getOne("xun_user");

        // Generate a unique reference id
        $referenceID = uniqid('', true);

        // Store the request
        $insertData = array(
            'business_id' => $userRes['id'],
            'business_name' => $userRes['nickname'],
            'reference_id' => $referenceID,
            'from_wallet_type' => $fromWalletType,
            'from_symbol' => $fromSymbol,
            'to_wallet_type' => $toWalletType,
            'to_symbol' => $toSymbol,
            'from_amount' => $fromAmount,
            'to_amount' => $submitAmount,
            'to_amount_display' => $toAmount,
            'price_market' => $marketPrice,
            'price_display' => $markupPrice,
            'exchange_rate_market' => $exchangeRate,
            'exchange_rate_display' => $markupExchangeRate,
            'margin_percentage' => $marginPercentage,
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s")
        );
        $id = $this->db->insert('xun_swap_request', $insertData);
        
        // Trim away extra 0s
        $markupExchangeRate = rtrim(rtrim($markupExchangeRate, "0"), '.');
        $markupExchangeRateDecimals = $this->getNumberOfDecimals($markupExchangeRate);
        $markupExchangeRate = bcmul((string)$markupExchangeRate, "1", $markupExchangeRateDecimals);

        $data['fromAmount'] = $fromAmount;
        $data['fromWalletType'] = $fromWalletType;
        $data['fromSymbol'] = $fromSymbol;
        $data['exchangeRate'] = $markupExchangeRate;
        $data['toAmount'] = $toAmount;
        $data['toWalletType'] = $toWalletType;
        $data['toSymbol'] = $toSymbol;
        $data['referenceID'] = $referenceID;
        //$data['debug'] = $markupExchangeRateDecimals;

        return array("code" => 1, "status" => "ok", "message" => "SUCCESS", "message_d" => "Success", "data" => $data);

    }

    public function swap($params) {
        global $config, $xun_numbers, $general, $xunCrypto, $xunWallet, $post, $xunPayment;

        $prepaidWalletServerURL = $config["giftCodeUrl"];

        $referenceID = trim($params['referenceID']);
        if (strlen($referenceID) == 0) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "ReferenceID cannot be empty.");
        }

        // Retrieve the stored request
        $this->db->where('reference_id', $referenceID);
        $requestRes = $this->db->getOne('xun_swap_request');
        if (empty($requestRes)) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Request not found.", "data" => array('debug' => "ReferenceID did not match with any swap request."));
        }
        $debug['requestRes'] = $requestRes;

        if ($requestRes['processed'] == 1) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Request has already been processed.");
        }

        // Retrieve the swap settings
        $result = $this->getSwapSetting($requestRes['from_wallet_type'], $requestRes['to_wallet_type']);
        if ($result['code'] == "0") {
            return $result;
        }

        $settingRes = $result['data'];
        $fromSymbol = $settingRes['from_symbol'];
        $toSymbol = $settingRes['to_symbol'];
        $commonSymbol = $settingRes['common_symbol'];
        $method = $settingRes['method'];
        $marginPercentage = $settingRes['margin_percentage'];
        $fromWalletType = $settingRes['from_wallet_type'];
        $toWalletType = $settingRes['to_wallet_type'];


        // $accountInfo = $this->binance->getAccountInfo();
        // foreach ($accountInfo['balances'] as $balanceRow){
        //     if ($balanceRow['asset'] != $requestRes['from_symbol']) continue;
        //     // Double check if binance enonugh balance to submit
        //     if ($balanceRow['free'] < $requestRes['from_amount']) {
        //         return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Could not swap the coins now. Please try again later.", "data" => array('debug' => $balanceRow), "error_code" => -100);
        //     }
        // }

        // Fetch Binance settings
             
        $this->db->where("disabled",0);
        $this->db->where("type","exchange_swap");
        $providerRes=$this->db->get("provider");

        if(empty($providerRes)){
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Please enable the right provider in provider table");
        }
        if(count($providerRes)>1){
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Please check the provider, type=exchange_swap, only allow one provider enable at a times");
        }
        $providerRes=$providerRes[0];

        $isCoins2 = false;
        $this->db->where('provider_id', $providerRes['id']);
        $this->db->where('type', $commonSymbol);
        $providerSettingRes = $this->db->get('provider_setting');
        // should be a coins2 swapping
        if (!$providerSettingRes) {
            $this->db->where('type', 'coins2');
            $providerSettingRes = $this->db->get('provider_setting');
            
            $db->where('name', $fromSymbol);
            $db->where('currency', 'usd');
            $conversionRate = $db->getValue('xun_cryptocurrency_rate', 'value');
            $convertedFromAmount = bcmul((string)$requestRes['from_amount'], (string)$conversionRate, 8);
            $isCoins2 = true;
        } 

        foreach ($providerSettingRes as $row) {
            $providerSetting[$row['name']] = $row['value'];
        }

        // First round validate setting
        if ($isCoins2) {
            if ($convertedFromAmount < $providerSetting['minSwapUSD']) {
                return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "From amount must be at least ".$providerSetting['minSwapUSD']. " USD for coins2 swap.");
            }
        } else {
            if ($requestRes['from_amount'] < $providerSetting['minSwap'.$requestRes['from_symbol']]) {
                // Check for min swap amount
                return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "From amount must be at least ".$providerSetting['minSwap'.$requestRes['from_symbol']]." ".$requestRes['from_symbol'].".");
            }
        }

        $lotStepSize = rtrim(rtrim($providerSetting['lotStepSize'], "0"), '.');

        // Check how many decimal points are allowed in the exchange
        $decimals = $this->getNumberOfDecimals($lotStepSize);

        // Call to binance to get the latest ticker price
        // $marketPrice = $this->binance->getPrice($commonSymbol)['price'];
        $crypto_params = array(
            "fromWalletType"=> $fromWalletType,
            "toWalletType" => $toWalletType,
            "marginPercentage" => $marginPercentage
        );
        $crypto_result = $xunCrypto->get_market_price($crypto_params);

        if($crypto_result['status'] == 'error'){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $crypto_result['statusMsg']);
        }
        
        $marketPrice = $crypto_result['data']['exchangeRate'];
        $markupPrice = $crypto_result['data']['markupExchangeRate'];

        $fromAmount = bcmul($requestRes['from_amount'], '1', $decimals);
        // if (empty($marketPrice)) {
        //     $submitAmount = $requestRes['to_amount'];
        //     $marketPrice = $requestRes['price_market'];
        //     $toAmount = $request['to_amount'];
        //     $exchangeRate = $request['exchange_rate_market'];

        // }
        // else {
        //     // If there's newer market price, do some checking
        //     // Format the data before sending to binance
        //     if ($method == 'buy') {
        //         $requestRes['to_amount'] = number_format((string)$requestRes['to_amount'], $decimals, ".", "");
        //         $exchangeRate = bcdiv("1", $marketPrice, 8);

        //         $submitAmount = bcmul((string)$requestRes['from_amount'], (string)$exchangeRate, $decimals);
        //         if ($submitAmount < $requestRes['to_amount_display']) {
        //         // If this amount is the same or higher than what we shown to customer, means we are losing money
        //             $submitAmount = $requestRes['to_amount'];

        //         }

        //         // Use for determining profit
        //         $toAmount = $submitAmount;

        //     }
        //     else if ($method == 'sell') {
        //         $requestRes['from_amount'] = number_format((string)$requestRes['from_amount'], $decimals, ".", "");
        //         $exchangeRate = $marketPrice; 

        //         $submitAmount = bcmul((string)$requestRes['from_amount'], "1", $decimals);

        //         $toAmount = bcmul((string)$requestRes['from_amount'], (string)$exchangeRate, $decimals);

        //     }

        // }

        $exchangeRate = $marketPrice;
        $markupExchangeRate = $markupPrice;
        $toAmount = bcmul((string)$fromAmount, (string)$markupExchangeRate, $decimals);
        $submitAmount = bcmul((string)$fromAmount, (string)$exchangeRate, $decimals);

        $debug['submitAmount'] = $submitAmount;
        $debug['marketPrice'] = $marketPrice;
        $debug['method'] = $method;

        // Validate quantity
        if ($submitAmount < $providerSetting['minQty']) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Quantity is below min quantity.");
        }

        if ($submitAmount > $providerSetting['maxQty']) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Quantity exceeded max quantity.");
        }

        $requestRes['price_market'] = number_format((string)$requestRes['price_market'], 8, ".", "");

        // Retrieve the user's balance for final validation
        $userBalance = $this->xunPaymentGateway->get_user_balance($requestRes['business_id'], $requestRes['from_wallet_type']);
        if ($userBalance <= 0) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Insufficient ".$requestRes['from_symbol']." balance.", "data" => array('debug' => $requestRes['from_wallet_type']." balance is less than 0."));
        }
        if ($userBalance < $requestRes['from_amount']) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Insufficient ".$requestRes['from_symbol']." balance.", "data" => array('debug' => $requestRes['from_wallet_type']." balance ($userBalance) is less than ".$requestRes['from_amount']."."));
        }


        // Submit order to binance
        // $orderRes = $this->binance->order(strtoupper($method), $commonSymbol, $submitAmount, $marketPrice, "LIMIT");
        // $debug['orderRes'] = $orderRes;
        // if ($orderRes['code'] > 0) {
        //     // Return error here and send notification
        //     return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Could not swap the coins now. Please try again later.", "data" => array('debug' => $debug), "error_code" => -100);

        // }

        // Success
        // Update the swap request
        $this->db->where('reference_id', $referenceID);
        $this->db->update('xun_swap_request', array('processed' => 1, 'updated_at' => date("Y-m-d H:i:s")));

        $orderID = $orderRes['orderId'];
        $orderStatus = $orderRes['status'];

        // Get conversion rate to determmine the profit
        $usdConversionRate = $this->xunPaymentGateway->get_cryptocurrency_rate($requestRes['to_wallet_type']);

        $this->db->where('id', $requestRes['business_id']);
        $userRes = $this->db->getOne("xun_user");


        // Calculate the profit
        $profit = bcsub((string)$toAmount, $requestRes['to_amount_display'], 8);
        $profitUSD = bcmul((string)$profit, (string)$usdConversionRate, 8);

        $debug['profit'] = $profit;

        $status = "processing";

        // Insert into swap history table
        $insertData = array(
            'business_id' => $userRes['id'],
            'business_name' => $userRes['nickname'],
            'reference_id' => $referenceID,
            'from_wallet_type' => $requestRes['from_wallet_type'],
            'from_symbol' => $requestRes['from_symbol'],
            'to_wallet_type' => $requestRes['to_wallet_type'],
            'to_symbol' => $requestRes['to_symbol'],
            'from_amount' => $requestRes['from_amount'],
            'to_amount' => $toAmount,
            'to_amount_display' => $requestRes['to_amount_display'],
            'price_market' => $marketPrice,
            'price_display' => $requestRes['price_display'],
            'exchange_rate_market' => $exchangeRate,
            'exchange_rate_display' => $requestRes['exchange_rate_display'],
            'margin_percentage' => $requestRes['margin_percentage'],
            'profit' => $profit,
            'profit_usd' => $profitUSD,
            'provider_id' => $providerRes['id'],
            'provider_name' => $providerRes['name'],
            // 'order_id' => $orderID,
            // 'order_status' => $orderStatus,
            'status' => $status,
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s")
        );
        $swapHistoryID = $this->db->insert('xun_swap_history', $insertData);

        // Retrieve the business' internal address
        $this->db->where('user_id', $userRes['id']);
        $this->db->where('address_type', 'nuxpay_wallet');
        $senderAddress = $this->db->getValue('xun_crypto_user_address', "address");

        // Retrieve the company address
        $this->db->where('name', "swapInternalAddress");
        $receiverAddress = $this->db->getValue('system_settings', "value");

        $satoshiAmount = $xunCrypto->get_satoshi_amount($requestRes['from_wallet_type'], $requestRes['from_amount']);

        // Insert into accounting table
        $insertTx = array(
                "businessID" => $userRes['id'],
                "senderAddress" => $senderAddress,
                "recipientAddress" => $receiverAddress,
                "amount" => $requestRes['from_amount'],
                "amountSatoshi" => $satoshiAmount,
                "walletType" => $requestRes['from_wallet_type'],
                "credit" => 0,
                "debit" => $requestRes['from_amount'],
                "transactionType" => 'swapcoin',
                "referenceID" => $swapHistoryID,
                "transactionDate" => date("Y-m-d H:i:s"),
            );
        $txID = $this->account->insertXunTransaction($insertTx);

        $txObj = new stdClass();
        $txObj->userID = $userRes['id'];
        $txObj->address = $senderAddress;

        $xunBusinessService = new XunBusinessService($this->db);
        $transactionToken = $xunBusinessService->insertCryptoTransactionToken($txObj);

        // Insert into wallet transaction table
        $transactionObj = new stdClass();
        $transactionObj->status = "pending";
        $transactionObj->transactionHash = "";
        $transactionObj->transactionToken = $transactionToken;
        $transactionObj->senderAddress = $senderAddress;
        $transactionObj->recipientAddress = $receiverAddress;
        $transactionObj->userID = $userRes['id'];
        $transactionObj->senderUserID = $userRes['id'];
        $transactionObj->recipientUserID = "swap_wallet";
        $transactionObj->walletType = $requestRes['from_wallet_type'];
        $transactionObj->amount = $requestRes['from_amount'];
        $transactionObj->addressType = "nuxpay_wallet";
        $transactionObj->transactionType = "receive";
        $transactionObj->escrow = 0;
        $transactionObj->referenceID = $swapHistoryID;
        $transactionObj->message = 'swap_coin';
        $transactionObj->escrowContractAddress = '';
        $transactionObj->createdAt = date("Y-m-d H:i:s");
        $transactionObj->updatedAt = date("Y-m-d H:i:s");
        $transactionObj->expiresAt = '';

        $xunWallet = new XunWallet($this->db);
        $walletTransactionID = $xunWallet->insertUserWalletTransaction($transactionObj);

        $txHistoryObj->status = "pending";
        $txHistoryObj->transactionID = "";
        $txHistoryObj->transactionToken = $transactionToken;
        $txHistoryObj->senderAddress = $senderAddress;
        $txHistoryObj->recipientAddress = $receiverAddress;
        $txHistoryObj->senderUserID = $userRes['id'];
        $txHistoryObj->recipientUserID = "swap_wallet";
        $txHistoryObj->walletType = strtolower($requestRes['from_wallet_type']);
        $txHistoryObj->amount =  $requestRes['from_amount'];
        $txHistoryObj->transactionType = 'swapcoin';
        $txHistoryObj->referenceID = $swapHistoryID;
        $txHistoryObj->createdAt = date("Y-m-d H:i:s");
        $txHistoryObj->updatedAt = $date;
        // $txHistoryObj->fee = $final_miner_fee;
        // $txHistoryObj->feeWalletType = $miner_fee_wallet_type;
        // $txHistoryObj->exchangeRate = $exchangeRate;
        // $txHistoryObj->minerFeeExchangeRate = $miner_fee_exchange_rate;
        $txHistoryObj->type = 'out';
        $txHistoryObj->gatewayType = "BC";

        $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);

        $transaction_history_id = $transaction_history_result['transaction_history_id'];
        $transaction_history_table = $transaction_history_result['table_name'];


        $updateWalletTx = array(
            "transaction_history_table" => $transaction_history_table,
            "transaction_history_id" => $transaction_history_id,
        );

        $xunWallet->updateWalletTransaction($walletTransactionID, $updateWalletTx);

        // Perform internal transfer
        $curlParams = array(
            "command" => "fundOutCompanyWallet",
            "params" => array(
                "senderAddress" => $senderAddress,
                "receiverAddress" => $receiverAddress,
                "amount" => $requestRes['from_amount'],
                "satoshiAmount" => $satoshiAmount,
                "walletType" => $requestRes['from_wallet_type'],
                "id" => $walletTransactionID,
                "transactionToken" => $transactionToken,
                "addressType" => "nuxpay_wallet",
                "transactionHistoryTable" => $transaction_history_table,
                "transactionHistoryID" => $transaction_history_id,
            ),
        );

        $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);
        if ($curlResponse['code'] == 1) {
            $updateData = array(
                "from_tx_id" => $walletTransactionID,
                "updated_at" => date("Y-m-d H:i:s"),
            );
            $this->db->where('id', $swapHistoryID);
            $this->db->update('xun_swap_history', $updateData);
            
            //$message = "Swap coins failed\n";
            $message = "Business Name:".$userRes['nickname']."\n";
            $message .= "From Amount:" .$requestRes['from_amount']." ".$requestRes['from_symbol']."\n";
            $message .= "To Amount:" .$requestRes['to_amount_display']." ".$requestRes['to_symbol']."\n";
            $message .= "Order ID: ".$orderID."\n";
            $message .= "\nTime: ".date("Y-m-d H:i:s")."\n";
            $message .= "Source: swapcoinswap(backend)\n";
            
            $thenux_params["tag"] = "Swapcoins Request Sent";
            $thenux_params["message"] = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");

        }
        else {

            // Handle failed case
            $updateData = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s"),
                );
            $this->db->where('id', $walletTransactionID);
            $this->db->update('xun_wallet_transaction', $updateData);

            //$message = "Swap coins failed\n";
            $message = "Business Name:".$userRes['nickname']."\n";
            $message .= "From Amount:" .$requestRes['from_amount']." ".$requestRes['from_symbol']."\n";
            $message .= "To Amount:" .$requestRes['to_amount_display']." ".$requestRes['to_symbol']."\n";
            $message .= "Reason: ".json_encode($curlResponse)."\n";
            $message .= "\nTime: ".date("Y-m-d H:i:s")."\n";
            $message .= "Source: swapcoinswap(backend)\n";
            
            $thenux_params["tag"] = "Swapcoins Request Failed";
            $thenux_params["message"] = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");
        }

        
        $data['fromAmount'] = $requestRes['from_amount'];
        $data['fromBalance'] = $this->xunPaymentGateway->get_user_balance($requestRes['business_id'], $requestRes['from_wallet_type']);
        $data['fromWalletType'] = $requestRes['from_wallet_type'];
        $data['fromSymbol'] = $requestRes['from_symbol'];
        $data['exchangeRate'] = $requestRes['exchange_rate_display'];
        $data['toAmount'] = $requestRes['to_amount_display'];
        $data['toWalletType'] = $requestRes['to_wallet_type'];
        $data['toSymbol'] = $requestRes['to_symbol'];

        $data['debug'] = $debug;

        return array("code" => 1, "status" => "ok", "message" => "SUCCESS", "message_d" => "Success", "data" => $data);

    }

    private function validateSwapParams($params) {

        $businessID = trim($params['businessID']);
        $fromWalletType = trim($params['fromWalletType']);
        $toWalletType = trim($params['toWalletType']);

        if (strlen($businessID) == 0)
            return array("code" => 0, "message" => "FAILED", "message_d" => "Business ID cannot be empty.");

        if (strlen($fromWalletType) == 0) 
            return array("code" => 0, "message" => "FAILED", "message_d" => "From wallet type cannot be empty.");

        if (strlen($toWalletType) == 0) 
            return array("code" => 0, "message" => "FAILED", "message_d" => "To wallet type cannot be empty.");

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
    }

    public function getSwapSetting($fromWalletType, $toWalletType) {
        $this->db->where('from_wallet_type', $fromWalletType);
        $this->db->where('to_wallet_type', $toWalletType);
        $this->db->where('disabled', 0);
        $settingRes = $this->db->getOne('xun_swap_setting');

        if (empty($settingRes)) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Chosen coins not supported.");
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "data" => $settingRes);
    }

    public function getNumberOfDecimals($str) {
        return strlen(substr(strrchr($str, "."), 1));
    }

    public function getSwapHistory($params) {
            
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $businessID = trim($params["business_id"]);
        $dateFrom = trim($params["from_datetime"]);
        $dateTo = trim($params["to_datetime"]);
        $status = trim($params["status"]);

        $pageLimit = $setting->systemSetting["memberBlogPageLimit"];
        $pageNumber = trim($params["page"]) ? trim($params["page"]) : 1;
        $pageSize = trim($params["page_size"]) ? trim($params["page_size"]) : $page_limit;
        $order = trim($params["order"]) ? trim($params["order"]) : "DESC";

        // check if the user has a valid id
        if (!$businessID) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $startLimit  = ($pageNumber-1) * $pageLimit;
        $limit = array($startLimit, $pageLimit);

        $db->where('business_id', $businessID);
        
        if($status) {
            $db->where("a.status", $status);
        }

        if($dateFrom){
            $dateFrom = date("Y-m-d 00:00:00", $dateFrom);
            $db->where("a.created_at", $dateFrom, ">=");
        }
        
        if($dateTo){
            $dateTo = date("Y-m-d 23:59:59", $dateTo);
            $db->where("a.created_at", $dateTo, "<=");
        }
        $copyDb = $db->copy();

        $db->orderBy("a.created_at", $order);
        $columns = "a.id, a.business_id, a.from_wallet_type, a.from_symbol, a.from_amount, a.to_wallet_type, a.to_symbol, a.to_amount_display, a.exchange_rate_display,a.order_status, a.status, a.created_at, b.display_symbol as from_display_symbol, c.display_symbol as to_display_symbol";

        $db->join('xun_marketplace_currencies b', 'binary a.from_wallet_type=binary b.currency_id', 'LEFT');
        $db->join('xun_marketplace_currencies c', 'binary a.to_wallet_type=binary c.currency_id', 'LEFT');
        $swapHistoryResult = $db->get("xun_swap_history a", $limit, $columns);
        
        foreach($swapHistoryResult as $row){
            if($row['order_status'] == 'REJECTED' || $row['order_status'] == 'CANCELLED'){
                $row['status'] = 'refunded';
            }
            $return[] = $row;

        }

        if (count($return) == 0) {
            return array('code' => 1, 'message' => SUCCESS, 'message_d' => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => "");
        }

        $copyDb->join('xun_marketplace_currencies b', 'binary a.from_wallet_type=binary b.currency_id', 'LEFT');
        $copyDb->join('xun_marketplace_currencies c', 'binary a.to_wallet_type=binary c.currency_id', 'LEFT');
        $totalRecords = $copyDb->getValue("xun_swap_history a", "count(a.id)");

        $returnData["swapHistory"] = $return;
        $returnData['totalPage']   = ceil($totalRecords / $limit[1]);
        $returnData['pageNumber']  = $pageNumber;
        $returnData['totalRecord'] = $totalRecords;
        $returnData['numRecord']   = $limit[1];

        return array("status" => "ok", "message" => "SUCCESS", "message_d" => "", "code" => 1, "data" => $returnData);
        
    }

    public function get_translation_message($message_code)
    {
        // Language Translations.
        global $general;
        $language = $general->getCurrentLanguage();
        $translations = $general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;
    }

    public function autoSwap($referenceID, $selectedProvider, $transactionToken) {
        global $config, $xun_numbers, $general, $xunCrypto, $xunWallet, $post, $xunPayment;

        $db = $this->db;
        $prepaidWalletServerURL = $config["giftCodeUrl"];
        
        // Validations
        if (strlen($prepaidWalletServerURL) == 0) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Wallet URL is null.");
        }
        if (strlen($selectedProvider) == 0) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Selected provider id is null.");
        }
        if (strlen($referenceID) == 0) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Reference id is null.");
        }

        // Retrieve the stored request
        $db->where('reference_id', $referenceID);
        $requestRes = $db->getOne('xun_swap_request');
        if (empty($requestRes)) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => "Request not found.", "data" => array('debug' => "ReferenceID did not match with any swap request."));
        }
        $toWalletType = $requestRes['to_wallet_type'];
        $fromWalletType = $requestRes['from_wallet_type'];
        $toAmount = $requestRes['to_amount'];
        $fromAmount = $requestRes['from_amount'];
        $businessID = $requestRes['business_id'];
        $toAmountDisplay = $requestRes['to_amount_display'];

        // Retrieve provider 
        $db->where('id', $selectedProvider);
        $provider = $db->getOne('provider');
        if ($provider['disabled'] == 1) {
            return array("code" => 0, "status" => "error", "message" => "FAILED", "message_d" => $selectedProvider." Provider is disabled.");
        }

        // Retrieve the swap settings
        $result = $this->getSwapSetting($fromWalletType, $toWalletType);
        if ($result['code'] == "0") {
            return $result;
        }
        $settingRes = $result['data'];
        $commonSymbol = $settingRes['common_symbol'];
        $method = $settingRes['method'];
        $marginPercentage = $settingRes['margin_percentage'];
        $fromSymbol = $settingRes['from_symbol'];
        $toSymbol = $settingRes['to_symbol'];

        // Validations Skipped as swap_request already validated

        // Update the swap request
        $db->where('reference_id', $referenceID);
        $db->update('xun_swap_request', array('processed' => 1, 'updated_at' => date("Y-m-d H:i:s")));

        // Get conversion rate to determmine the profit
        $usdConversionRate = $this->xunPaymentGateway->get_cryptocurrency_rate($toWalletType);

        $db->where('id', $businessID);
        $userRes = $db->getOne("xun_user");

        // Calculate the profit
        $profit = bcsub((string)$toAmount, $toAmountDisplay, 8);
        $profitUSD = bcmul((string)$profit, (string)$usdConversionRate, 8);

        $status = "processing";

        // Insert into swap history table
        $insertData = array(
            'business_id' => $userRes['id'],
            'business_name' => $userRes['nickname'],
            'reference_id' => $referenceID,
            'from_wallet_type' => $fromWalletType,
            'from_symbol' => $fromSymbol,
            'to_wallet_type' => $toWalletType,
            'to_symbol' => $toSymbol,
            'from_amount' => $fromAmount,
            'to_amount' => $toAmount,
            'to_amount_display' => $toAmountDisplay,
            'price_market' => $requestRes['price_market'],
            'price_display' => $requestRes['price_display'],
            'exchange_rate_market' => $requestRes['exchange_rate_market'],
            'exchange_rate_display' => $requestRes['exchange_rate_display'],
            'margin_percentage' => $requestRes['margin_percentage'],
            'profit' => $profit,
            'profit_usd' => $profitUSD,
            'provider_id' => $provider['id'],
            'provider_name' => $provider['name'],
            'order_id' => '',
            'order_status' => '',
            'status' => $status,
            'transaction_token' => $transactionToken,
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s")
        );
        $swapHistoryID = $db->insert('xun_swap_history', $insertData);

        // Retrieve the business' internal address
        $db->where('user_id', $userRes['id']);
        $db->where('address_type', 'nuxpay_wallet');
        $senderAddress = $db->getValue('xun_crypto_user_address', "address");

        // Retrieve the company address
        $db->where('name', "swapInternalAddress");
        $receiverAddress = $db->getValue('system_settings', "value");

        $satoshiAmount = $xunCrypto->get_satoshi_amount($requestRes['from_wallet_type'], $requestRes['from_amount']);

        // Insert into accounting table
        $insertTx = array(
                "businessID" => $userRes['id'],
                "senderAddress" => $senderAddress,
                "recipientAddress" => $receiverAddress,
                "amount" => $requestRes['from_amount'],
                "amountSatoshi" => $satoshiAmount,
                "walletType" => $requestRes['from_wallet_type'],
                "credit" => 0,
                "debit" => $requestRes['from_amount'],
                "transactionType" => 'swapcoin',
                "referenceID" => $swapHistoryID,
                "transactionDate" => date("Y-m-d H:i:s"),
            );
        $txID = $this->account->insertXunTransaction($insertTx);

        $txObj = new stdClass();
        $txObj->userID = $userRes['id'];
        $txObj->address = $senderAddress;

        $xunBusinessService = new XunBusinessService($this->db);
        $transactionToken = $xunBusinessService->insertCryptoTransactionToken($txObj);

        // Insert into wallet transaction table
        $transactionObj = new stdClass();
        $transactionObj->status = "pending";
        $transactionObj->transactionHash = "";
        $transactionObj->transactionToken = $transactionToken;
        $transactionObj->senderAddress = $senderAddress;
        $transactionObj->recipientAddress = $receiverAddress;
        $transactionObj->userID = $userRes['id'];
        $transactionObj->senderUserID = $userRes['id'];
        $transactionObj->recipientUserID = "swap_wallet";
        $transactionObj->walletType = $requestRes['from_wallet_type'];
        $transactionObj->amount = $requestRes['from_amount'];
        $transactionObj->addressType = "nuxpay_wallet";
        $transactionObj->transactionType = "receive";
        $transactionObj->escrow = 0;
        $transactionObj->referenceID = $swapHistoryID;
        $transactionObj->message = 'swap_coin';
        $transactionObj->escrowContractAddress = '';
        $transactionObj->createdAt = date("Y-m-d H:i:s");
        $transactionObj->updatedAt = date("Y-m-d H:i:s");
        $transactionObj->expiresAt = '';

        $xunWallet = new XunWallet($this->db);
        $walletTransactionID = $xunWallet->insertUserWalletTransaction($transactionObj);

        $txHistoryObj->status = "pending";
        $txHistoryObj->transactionID = "";
        $txHistoryObj->transactionToken = $transactionToken;
        $txHistoryObj->senderAddress = $senderAddress;
        $txHistoryObj->recipientAddress = $receiverAddress;
        $txHistoryObj->senderUserID = $userRes['id'];
        $txHistoryObj->recipientUserID = "swap_wallet";
        $txHistoryObj->walletType = strtolower($requestRes['from_wallet_type']);
        $txHistoryObj->amount =  $requestRes['from_amount'];
        $txHistoryObj->transactionType = 'swapcoin';
        $txHistoryObj->referenceID = $swapHistoryID;
        $txHistoryObj->createdAt = date("Y-m-d H:i:s");
        $txHistoryObj->updatedAt = $date;
        $txHistoryObj->type = 'out';
        $txHistoryObj->gatewayType = "BC";

        $transaction_history_result = $xunPayment->insert_payment_transaction_history($txHistoryObj);

        $transaction_history_id = $transaction_history_result['transaction_history_id'];
        $transaction_history_table = $transaction_history_result['table_name'];

        $updateWalletTx = array(
            "transaction_history_table" => $transaction_history_table,
            "transaction_history_id" => $transaction_history_id,
        );

        $xunWallet->updateWalletTransaction($walletTransactionID, $updateWalletTx);

        // Perform internal transfer
        $curlParams = array(
            "command" => "fundOutCompanyWallet",
            "params" => array(
                "senderAddress" => $senderAddress,
                "receiverAddress" => $receiverAddress,
                "amount" => $requestRes['from_amount'],
                "satoshiAmount" => $satoshiAmount,
                "walletType" => $requestRes['from_wallet_type'],
                "id" => $walletTransactionID,
                "transactionToken" => $transactionToken,
                "addressType" => "nuxpay_wallet",
                "transactionHistoryTable" => $transaction_history_table,
                "transactionHistoryID" => $transaction_history_id,
            ),
        );

        $curlResponse = $post->curl_post($prepaidWalletServerURL, $curlParams, 0);
        $internalStatus = '';
        if ($curlResponse['code'] == 1) {
            $updateData = array(
                "from_tx_id" => $walletTransactionID,
                "updated_at" => date("Y-m-d H:i:s"),
            );
            $this->db->where('id', $swapHistoryID);
            $this->db->update('xun_swap_history', $updateData);
            
            //$message = "Swap coins failed\n";
            $message = "Business Name:".$userRes['nickname']."\n";
            $message .= "From Amount:" .$requestRes['from_amount']." ".$requestRes['from_symbol']."\n";
            $message .= "To Amount:" .$requestRes['to_amount_display']." ".$requestRes['to_symbol']."\n";
            $message .= "Order ID: ".$orderID."\n";
            $message .= "\nTime: ".date("Y-m-d H:i:s")."\n";
            $message .= "Source: swapcoinswap(backend)\n";
            
            $thenux_params["tag"] = "Swapcoins Request Sent";
            $thenux_params["message"] = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_wallet_transaction");
            $internalStatus = 'success';
        }
        else {

            // Handle failed case
            $updateData = array(
                    "status" => 'failed',
                    "updated_at" => date("Y-m-d H:i:s"),
                );
            $db->where('id', $walletTransactionID);
            $db->update('xun_wallet_transaction', $updateData);

            //$message = "Swap coins failed\n";
            $message = "Business Name:".$userRes['nickname']."\n";
            $message .= "From Amount:" .$requestRes['from_amount']." ".$requestRes['from_symbol']."\n";
            $message .= "To Amount:" .$requestRes['to_amount_display']." ".$requestRes['to_symbol']."\n";
            $message .= "Reason: ".json_encode($curlResponse)."\n";
            $message .= "\nTime: ".date("Y-m-d H:i:s")."\n";
            $message .= "Source: swapcoinswap(backend)\n";
            
            $thenux_params["tag"] = "Swapcoins Request Failed";
            $thenux_params["message"] = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");
            $internalStatus = 'failed';
        }

        
        $data['fromAmount'] = $requestRes['from_amount'];
        $data['fromBalance'] = $this->xunPaymentGateway->get_user_balance($requestRes['business_id'], $requestRes['from_wallet_type']);
        $data['fromWalletType'] = $requestRes['from_wallet_type'];
        $data['fromSymbol'] = $requestRes['from_symbol'];
        $data['exchangeRate'] = $requestRes['exchange_rate_display'];
        $data['toAmount'] = $requestRes['to_amount_display'];
        $data['toWalletType'] = $requestRes['to_wallet_type'];
        $data['toSymbol'] = $requestRes['to_symbol'];
        $data['internalTransferStatus'] = $internalStatus;
        $data['swapHistoryID'] = $swapHistoryID;

        $data['debug'] = $debug;

        return array("code" => 1, "status" => "ok", "message" => "SUCCESS", "message_d" => "Success", "data" => $data);
    }

}
