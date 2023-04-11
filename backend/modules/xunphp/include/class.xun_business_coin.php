<?php
class XunBusinessCoin{
    private $business_id;
    private $xun_business_service;
    private $business_coin;

    function __construct($db, $general, $setting, $log, $xunCrypto, $xunCurrency){
        $this->db = $db;
        $this->general = $general;
        $this->setting = $setting;
        $this->log = $log;
        $this->xunCrypto = $xunCrypto;
        $this->xunCurrency = $xunCurrency;
    }

    //  API
    public function get_business_coin($params){
        $db = $this->db;
        $general = $this->general;

        $business_id = $params["business_id"];
        $type = $params['type'];
        if($business_id == ''){
            return $general->getResponseArr(0, "E00002");
        }

        $xun_business_service = new XunBusinessService($db);
        $columns = "id, business_id, wallet_type, type";

        $business_coin = new XunBusinessCoinModel($db);
        $business_coin->businessID = $business_id;
        $business_coin->type = $type;
        $business_coin_data = $xun_business_service->getBusinessCoin($business_coin, $columns);

        $business_coin_arr = $xun_business_service->mapBusinessCoinToArray($business_coin_data, $columns);
        
        
        return $general->getResponseArr(1, "", "Business Credit Coin.", $business_coin_arr);
    }

    public function create_business_credit_coin($params){
        global $xunCompanyWalletAPI;

        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $xunCrypto = $this->xunCrypto;
        $xunCurrency = $this->xunCurrency;
        $xun_business_service = new XunBusinessService($db);
        $this->xun_business_service = $xun_business_service;
        
        $business_id = trim($params['business_id']);
        $api_key = trim($params['api_key']);

        if($business_id == ''){
            return $general->getResponseArr(0, 'E00002');/*Business ID cannot be empty*/
        }
        if($api_key == ''){
            return $general->getResponseArr(0, 'E00086');/*Api key cannot be empty.*/
        }

        $crypto_api_key_validation = $xunCrypto->validate_crypto_api_key($api_key, $business_id);

        if(isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0){
            if (!$xun_business_service->validateApiKey($business_id, $api_key)) {
                return $general->getResponseArr(0, 'E00148');/*Invalid Apikey.*/
            }
        }

        $coin_type = "credit";

        $business_coin = new XunBusinessCoinModel($db);
        $business_coin->businessID = $business_id;
        $business_coin->type = $coin_type;
        
        $business = $xun_business_service->getBusinessByBusinessID($business_id, "name, user_id");
        
        if(!$business){
            return $general->getResponseArr(0, 'E00032');/*Invalid business id.*/
        }
        
        $columns = "id, business_id, wallet_type, type";

        $business_coin_data = $xun_business_service->getBusinessCoin($business_coin, $columns);

        if($business_coin_data){
            return $general->getResponseArr(0, '', 'Business already have existing credit coin.');
        }

        $business_name = $business['name'];
        $coin_name_symbol_params = array('name' => $business_name, 'symbol_suffix' => 'c');
        $verified_coin_name_symbol_res = $this->get_verified_coin_name_symbol($coin_name_symbol_params);

        $credit_coin_symbol = strtolower($verified_coin_name_symbol_res['symbol']);
        $credit_coin_name = $verified_coin_name_symbol_res['name'];
        $token_fiat_currency_id = 'myr';
        $reference_price = 1;
        $credit_token_decimal_places = 2;
        $total_supply = $setting->systemSetting['theNuxRewardTotalSupply'];
        $theNuxRewardCardBackground = $setting->systemSetting['theNuxRewardCardBackground'];
        $theNuxRewardCardBackground_arr = json_decode($theNuxRewardCardBackground, true);
        
        $card_image_url = $theNuxRewardCardBackground_arr['white'];
        $card_font_color = 'black';

        $business_coin2 = new XunBusinessCoinModel($db);
        $business_coin2->businessID = $business_id;
        $existing_business_coin_arr = $xun_business_service->getBusinessCoinArr($business_coin2, "id,type,card_image_url,font_color");

        foreach($existing_business_coin_arr as $existing_business_coin){
            if($existing_business_coin->getCardImageUrl() != ''){
                $card_image_url = $existing_business_coin->getCardImageUrl();
                $card_font_color = $existing_business_coin->getFontColor();
                break;
            }
        }

        $business_coin->businessName = $business_name;
        $business_coin->coinName = $credit_coin_name;
        $business_coin->symbol = $credit_coin_symbol;
        $business_coin->fiatCurrencyID = $token_fiat_currency_id;
        $business_coin->totalSupply = $total_supply;
        $business_coin->referencePrice = $reference_price;
        $business_coin->cardImageUrl = $card_image_url;
        $business_coin->type = $coin_type;
        $business_coin->fontColor = $card_font_color;
        $business_coin->decimalPlaces = $credit_token_decimal_places;
        
        //  call bc to create token
        $address_type = $coin_type;

        $business_address_params = array(
            "user_id" => $business_id,
            "address_type" => $address_type
        );

        $credit_address_result = $xunCompanyWalletAPI->getUserCompanyAddress($business_address_params);

        if(!$credit_address_result['code'] === 1){
            return $credit_address_result;
        }

        $credit_internal_address = $credit_address_result['data']['internal_address'];

        if(!$credit_internal_address){
            return $general->getResponseArr(0, 'E00141'/*"Internal server error. Please try again.")*/, '', $credit_address_result);
        }

        $fiat_currency_arr = array($token_fiat_currency_id);
        $fiat_currency_price = $xunCurrency->get_latest_fiat_price($fiat_currency_arr);
        
        $fiat_currency_value = $fiat_currency_price[$token_fiat_currency_id]['exchange_rate'];
        $usd_value = bcdiv(1, $fiat_currency_value, '8');
        $usd_exchange_rate = bcmul($usd_value, $reference_price, '8');

        $create_token_result = $this->process_create_new_token($business_coin, $credit_internal_address, $usd_exchange_rate);

        $crypto_response = $create_token_result['crypto_response'];
        if($crypto_response['status'] != "ok"){
            $db->where("id", $business_coin->id);
            $db->delete("xun_business_coin");
            return $general->getResponseArr(0, '', $crypto_response["statusMsg"], $crypto_response);
        }

        return $general->getResponseArr(1, '', "Coin successfully created");
    }

    public function business_transfer_credit($params){
        $db = $this->db;
        $general = $this->general;
        $xunCrypto = $this->xunCrypto;

        $business_id = trim($params["business_id"]);
        $api_key = trim($params["api_key"]);
        $sender_username = trim($params["sender_username"]);
        $receiver_username = trim($params["receiver_username"]);
        $amount = trim($params["amount"]);
        $coin_type = trim($params["coin_type"]);

        if($business_id == ''){
            return $general->getResponseArr(0, 'E00002');/*Business ID cannot be empty*/
        }
        if($api_key == ''){
            return $general->getResponseArr(0, 'E00086');/*Api key cannot be empty.*/
        }
        if($sender_username == ''){
            return $general->getResponseArr(0, "", "Sender username is required.");/*Sender username is required.*/
        }
        if($receiver_username == ''){
            return $general->getResponseArr(0, "", "Receiver username is required.");/*Receiver username is required.*/
        }
        if($amount == ''){
            return $general->getResponseArr(0, "", "Amount is required.");/*Amount is required.*/
        }
        if($amount <= 0 || !is_numeric($amount)){
            return $general->getResponseArr(0, "E00320");/*Invalid amount.*/
        }
        if($coin_type == ''){
            return $general->getResponseArr(0, "", "Coin type is required.");/*Coin type is required.*/
        }
        $coin_type = strtolower($coin_type);
        if(!in_array($coin_type, ["credit"])){
            return $general->getResponseArr(0, "", "Invalid coin type.");/*Invalid coin type.*/
        }
        
        if($sender_username == $receiver_username){
            return $general->getResponseArr(0, "", "Invalid receiver.");/*Invalid receiver.*/
        }

        $crypto_api_key_validation = $xunCrypto->validate_crypto_api_key($api_key, $business_id);
        
        if(isset($crypto_api_key_validation["code"]) && $crypto_api_key_validation["code"] == 0){
            if (!$xun_business_service->validateApiKey($business_id, $api_key)) {
                return $general->getResponseArr(0, 'E00148');/*Invalid Apikey.*/
            }
        }

        //  check if business have credit type coin
        $xun_business_service = new XunBusinessService($db);

        $business_coin_params = new XunBusinessCoinModel($db);
        $business_coin_params->businessID = $business_id;
        $business_coin_params->type = $coin_type;

        $columns = "id, business_id, wallet_type, type, unit_conversion";

        $business_coin = $xun_business_service->getBusinessCoin($business_coin_params, $columns);

        if(!$business_coin){
            return $general->getResponseArr(0, "", "Please create a credit token before proceeding.");
        }

        $unit_conversion = $business_coin->getUnitConversion();

        //  add checking for amount decimal places
        $check_decimal_places_ret = $general->checkDecimalPlaces($amount, $unit_conversion);
        if (!$check_decimal_places_ret) {
            $no_of_decimals = log10($unit_conversion);

            return $general->getResponseArr(0, "", "Invalid amount. A maximum of $no_of_decimals decimals is allowed.");
        }

        $xun_user_service = new XunUserService($db);

        $username_arr = [$sender_username, $receiver_username];

        $db->where("username", $username_arr, "IN");
        $db->orWhere("id", $username_arr, "IN");
        $xun_user_arr = $db->get("xun_user", null, "id, username, nickname, type, register_site, disabled");

        $receiver_user;
        $sender_user;

        foreach($xun_user_arr as $xun_user){
            if($xun_user["type"] == "user"){
                if($xun_user["username"] == $receiver_username){

                    $receiver_user = $xun_user;
                    continue;
                }
    
                if($xun_user["username"] == $sender_username){
                    $sender_user = $xun_user;
                    continue;
                }
            }

            if($xun_user["type"] == "business" && $xun_user["id"] == $business_id){
                if($xun_user["id"] == $receiver_username){
                    $receiver_user = $xun_user;
                    continue;
                }

                if($xun_user["id"] == $sender_username){
                    $sender_user = $xun_user;
                    continue;
                }
            }
        }

        if(empty($receiver_user)){
            return $general->getResponseArr(0, "", "Invalid receiver.");
        }
        if(empty($sender_user)){
            return $general->getResponseArr(0, "", "Invalid sender.");
        }

        //  check if sender have address, if no address means no balance
        //  check sender balance
        //  check receiver has address
        $transfer_credit_params = array(
            "sender_user_id" => $sender_user["id"],
            "receiver_user_id" => $receiver_user["id"],
            "amount" => $amount,
            "business_coin" => $business_coin
        );

        $transfer_credit_result = $this->process_transfer_credit($transfer_credit_params);
        if(isset($transfer_credit_result["code"]) && $transfer_credit_result["code"] === 0){
            return $transfer_credit_result;
        }

        $return_data = array(
            "reference_id" => $transfer_credit_result["wallet_tx_id"]
        );
        
        return $general->getResponseArr(1, "", "Credit transfer has been successfully processed.", $return_data);
    }

    public function app_transfer_credit($params){
        /**
         * - user / app:
         *  -   username
         *  -   wallet_type:
         *  -   receiver account
         *  -   amount: check decimal
         */

        $db = $this->db;
        $general = $this->general;
        $xunCrypto = $this->xunCrypto;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $receiver_business_id = trim($params["receiver_business_id"]);
        $receiver_username = trim($params["receiver_username"]);
        $amount = trim($params["amount"]);
        $wallet_type = trim($params["wallet_type"]);

        if($username == ''){
            return $general->getResponseArr(0, 'E00130'); /*username cannot be empty.*/
        }

        if($receiver_username == '' && $receiver_business_id == ''){
            return $general->getResponseArr(0, "", "Receiver is required.");/*Receiver is required.*/
        }

        if(!empty($receiver_username) && !empty($receiver_business_id)){
            return $general->getResponseArr(0, "", "Only one receiver is allowed");
        }

        if($amount == ''){
            return $general->getResponseArr(0, "", "Amount is required.");/*Amount is required.*/
        }
        if($amount <= 0 || !is_numeric($amount)){
            return $general->getResponseArr(0, "E00320");/*Invalid amount.*/
        }
        if($wallet_type == ''){
            return $general->getResponseArr(0, "E00207");/*Wallet type is required.*/
        }

        $wallet_type = strtolower($wallet_type);
        $sender_username = $username;
        if($sender_username == $receiver_username){
            return $general->getResponseArr(0, "", "You are not allowed to transfer to your own account.");
        }

        $xun_user_service = new XunUserService($db);

        $user_columns = "id, nickname, username, type";
        $xun_user = $xun_user_service->getUserByUsername($username, $user_columns, "user");

        if (!$xun_user) {
            return $general->getResponseArr(0, 'E00202') /*User does not exist.*/;
        }

        $coin_type = "credit";
         //  check if business have credit type coin
        $xun_business_service = new XunBusinessService($db);

        $business_coin_params = new XunBusinessCoinModel($db);
        $business_coin_params->setWalletType($wallet_type);
        $business_coin_params->setType($coin_type);

        $columns = "id, business_id, wallet_type, type, unit_conversion, status";

        $business_coin = $xun_business_service->getBusinessCoin($business_coin_params, $columns);

        if(!$business_coin){
            return $general->getResponseArr(0, "", "Invalid wallet type");
        }
        
        if($business_coin->getStatus() != "success"){
            return $general->getResponseArr(0, "", "Invalid wallet type");
        }

        $unit_conversion = $business_coin->getUnitConversion();
        //  add checking for amount decimal places
        $check_decimal_places_ret = $general->checkDecimalPlaces($amount, $unit_conversion);
        if (!$check_decimal_places_ret) {
            $no_of_decimals = log10($unit_conversion);

            return $general->getResponseArr(0, "", "Invalid amount. A maximum of $no_of_decimals decimals is allowed.");
        }

        if(!empty($receiver_username)){
            $receiver_user = $xun_user_service->getUserByUsername($receiver_username, "id, nickname, username, disabled, type", "user");
        }else{
            $receiver_user = $xun_business_service->getBusinessByBusinessID($receiver_business_id, "name, user_id as id");
        }
    
        if(!empty($business_id)){
            $sender_user = $xun_user_service->getUserByID($business_id, $user_columns);
        }else{
            $sender_user = $xun_user;
        }

        if(empty($receiver_user)){
            return $general->getResponseArr(0, "", "Invalid receiver.");
        }
        if(empty($sender_user)){
            return $general->getResponseArr(0, "", "Invalid sender.");
        }

        //  check if sender have address, if no address means no balance
        //  check sender balance
        //  check receiver has address
        $transfer_credit_params = array(
            "sender_user_id" => $sender_user["id"],
            "receiver_user_id" => $receiver_user["id"],
            "amount" => $amount,
            "business_coin" => $business_coin
        );

        $transfer_credit_result = $this->process_transfer_credit($transfer_credit_params);
        if(isset($transfer_credit_result["code"]) && $transfer_credit_result["code"] === 0){
            return $transfer_credit_result;
        }

        return $general->getResponseArr(1, "", "Credit transfer has been successfully processed.");
    }

    private function process_transfer_credit($params){
        $db = $this->db;
        $general = $this->general;
        $xunCrypto = $this->xunCrypto;

        $xunWallet = new XunWallet($db);

        $sender_user_id = $params["sender_user_id"];
        $receiver_user_id = $params["receiver_user_id"];
        $amount = $params["amount"];
        $business_coin = $params["business_coin"]; // business_id, wallet_type

        $sender_credit_res = $this->validate_transfer_credit($sender_user_id, "sender", $business_coin, $amount);

        if(isset($sender_credit_res["code"]) && $sender_credit_res["code"] === 0){
            return $sender_credit_res;
        }

        $receiver_credit_res = $this->validate_transfer_credit($receiver_user_id, "receiver", $business_coin, $amount);
        if(isset($receiver_credit_res["code"]) && $receiver_credit_res["code"] === 0){
            return $receiver_credit_res;
        }

        $sender_internal_address = $sender_credit_res["internal_address"];
        $sender_address_id = $sender_credit_res["address_id"];
        
        $receiver_internal_address = $receiver_credit_res["internal_address"];
        $receiver_address_id = $receiver_credit_res["address_id"];

        $address_type = $business_coin->getType();
        $wallet_type = $business_coin->getWalletType();

        $date = date("Y-m-d H:i:s");

        $wallet_transaction = new XunWalletTransaction();
        $wallet_transaction->setStatus("pending");
        $wallet_transaction->setSenderAddress($sender_internal_address);
        $wallet_transaction->setRecipientAddress($receiver_internal_address);
        $wallet_transaction->setUserID($sender_user_id);
        $wallet_transaction->setSenderUserID($sender_user_id);
        $wallet_transaction->setRecipientUserID($receiver_user_id);
        $wallet_transaction->setWalletType($wallet_type);
        $wallet_transaction->setAmount($amount);
        $wallet_transaction->setAddressType($address_type);
        $wallet_transaction->setTransactionType("send");
        $wallet_transaction->setCreatedAt($date);
        $wallet_transaction->setUpdatedAt($date);

        $wallet_transaction_id = $xunWallet->insertUserWalletTransaction($wallet_transaction);

        $amount_satoshi = $xunCrypto->get_satoshi_amount($wallet_type, $amount);
    
        $insert_wallet_sending_queue = array(
            "sender_crypto_user_address_id" => $sender_address_id,
            "receiver_crypto_user_address_id" => $receiver_address_id,
            "receiver_user_id" => $receiver_user_id,
            "amount" => $amount,
            "amount_satoshi" => $amount_satoshi,
            "wallet_type" => $wallet_type,
            "status" => 'pending',
            "wallet_transaction_id" => $wallet_transaction_id,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $wallet_queue_id = $db->insert('wallet_server_sending_queue', $insert_wallet_sending_queue);

        if(!$wallet_queue_id){
            return $general->getResponseArr(0, 'E00141'/*"Internal server error. Please try again.")*/, '', $db->getLastError());
        }

        return array("row_id" => $wallet_queue_id, "wallet_tx_id" => $wallet_transaction_id);
    }

    /**
     * @param int $user_id
     * @param string $user_type: sender / receiver
     */
    private function validate_transfer_credit($user_id, $user_type, $business_coin, $amount){
        global $xunCompanyWalletAPI;
        $general = $this->general;
        $xunCrypto = $this->xunCrypto;

        $address_type = $business_coin->getType();
        if(!in_array($user_type, ["sender", "receiver"])){
            return $general->getResponseArr(0, '', 'Invalid user type');
        }

        $user_address_params = array(
            "user_id" => $user_id,
            "address_type" => $address_type
        );

        $credit_address_result = $xunCompanyWalletAPI->getUserCompanyAddress($user_address_params);

        if(!$credit_address_result['code'] === 1){
            return $credit_address_result;
        }

        $credit_address_data = $credit_address_result["data"];

        $credit_internal_address = $credit_address_data['internal_address'];

        if(!$credit_internal_address){
            return $general->getResponseArr(0, 'E00141'/*"Internal server error. Please try again.")*/, '', $credit_address_result);
        }

        //  check balance for sender
        if($user_type == "sender"){
            if($credit_address_data["is_new_address"] === 1){
                return $general->getResponseArr(0, "E00338"); /*Insufficient Balance*/
            }

            try{
                $wallet_type = $business_coin->getWalletType();
                $unit_conversion = $business_coin->getUnitConversion();
                $sender_balance = $xunCrypto->get_wallet_balance($credit_internal_address, $wallet_type);

            }catch(exception $e){
                $error_message = $e->getMessage();

                return $general->getResponseArr(0, "", $error_message);
            }

            if(bccomp((string)$sender_balance, (string)$amount, log10($unit_conversion)) < 0){
                return $general->getResponseArr(0, "E00338"); /*Insufficient Balance*/
            }
        }

        return $credit_address_data;
    }

    private function generate_coin_symbol($params){
        $db = $this->db;
        $general = $this->general;
        $xunCrypto = $this->xunCrypto;
        
        $name = trim($params['name']);
        $symbol_suffix = trim($params['symbol_suffix']);

        $length = 3;

        $symbol = substr($name, 0, $length);
        $symbol = $symbol . $symbol_suffix;

        return $symbol;
    }

    public function get_verified_coin_name_symbol($params){
        $xunCrypto = $this->xunCrypto;

        $coin_symbol = $this->generate_coin_symbol($params);
        $coin_name = $params["name"];

        $result = $xunCrypto->check_crypto_token_name($coin_name, $coin_symbol);
        
        return $result;
    }

    public function process_create_new_token($business_coin, 
        $token_receiver_internal_address, $usd_exchange_rate){
        $xunCrypto = $this->xunCrypto;
        $xun_business_service = $this->xun_business_service;

        $business_coin_id = $xun_business_service->createBusinessCoin($business_coin);

        $business_coin->id = $business_coin_id;
        $coin_name = $business_coin->coinName;

        //  call bc to create token
        if($business_coin_id){
            //  prefix for dev
            $coin_name_prefix = $setting->systemSetting["rewardCoinNamePrefix"];
            $new_token_params = array(
                "name" => $coin_name_prefix . $coin_name,
                "symbol" => $business_coin->symbol,
                "decimalPlaces" => $business_coin->decimalPlaces,
                "totalSupply" => $business_coin->totalSupply,
                "totalSupplyHolder" => $token_receiver_internal_address,
                "exchangeRate" => array(
                    "usd" => $usd_exchange_rate,
                    $business_coin->fiatCurrencyID => $business_coin->referencePrice,
                ),
                "referenceID" => $business_coin_id
            );
    
            $crypto_response = $xunCrypto->add_reward_token($new_token_params);
        }
        
        $return_data = array(
            "business_coin" => $business_coin,
            "crypto_response" => $crypto_response
        );

        return $return_data;
    }
}

class XunBusinessCoinModel{
    public $id;
    public $businessID;
    public $walletType;
    public $businessName;
    public $coinName;
    public $symbol;
    public $fiatCurrencyID;
    public $totalSupply;
    public $referencePrice;
    public $unitConversion;
    public $cardImageUrl;
    public $fontColor;
    public $status;
    public $type;
    public $defaultShow;
    public $createdAt;
    public $updatedAt;
    public $decimalPlaces;

    public function __construct($db){
        // $this->db = $db;
    }

    /**
     * Get the value of id
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return  self
     */ 
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the value of businessID
     */ 
    public function getBusinessID()
    {
        return $this->businessID;
    }

    /**
     * Set the value of businessID
     *
     * @return  self
     */ 
    public function setBusinessID($businessID)
    {
        $this->businessID = $businessID;

        return $this;
    }

    /**
     * Get the value of walletType
     */ 
    public function getWalletType()
    {
        return $this->walletType;
    }

    /**
     * Set the value of walletType
     *
     * @return  self
     */ 
    public function setWalletType($walletType)
    {
        $this->walletType = $walletType;

        return $this;
    }

    /**
     * Get the value of businessName
     */ 
    public function getBusinessName()
    {
        return $this->businessName;
    }

    /**
     * Set the value of businessName
     *
     * @return  self
     */ 
    public function setBusinessName($businessName)
    {
        $this->businessName = $businessName;

        return $this;
    }

    /**
     * Get the value of coinName
     */ 
    public function getCoinName()
    {
        return $this->coinName;
    }

    /**
     * Set the value of coinName
     *
     * @return  self
     */ 
    public function setCoinName($coinName)
    {
        $this->coinName = $coinName;

        return $this;
    }

    /**
     * Get the value of symbol
     */ 
    public function getSymbol()
    {
        return $this->symbol;
    }

    /**
     * Set the value of symbol
     *
     * @return  self
     */ 
    public function setSymbol($symbol)
    {
        $this->symbol = $symbol;

        return $this;
    }

    /**
     * Get the value of fiatCurrencyID
     */ 
    public function getFiatCurrencyID()
    {
        return $this->fiatCurrencyID;
    }

    /**
     * Set the value of fiatCurrencyID
     *
     * @return  self
     */ 
    public function setFiatCurrencyID($fiatCurrencyID)
    {
        $this->fiatCurrencyID = $fiatCurrencyID;

        return $this;
    }

    /**
     * Get the value of totalSupply
     */ 
    public function getTotalSupply()
    {
        return $this->totalSupply;
    }

    /**
     * Set the value of totalSupply
     *
     * @return  self
     */ 
    public function setTotalSupply($totalSupply)
    {
        $this->totalSupply = $totalSupply;

        return $this;
    }

    /**
     * Get the value of referencePrice
     */ 
    public function getReferencePrice()
    {
        return $this->referencePrice;
    }

    /**
     * Set the value of referencePrice
     *
     * @return  self
     */ 
    public function setReferencePrice($referencePrice)
    {
        $this->referencePrice = $referencePrice;

        return $this;
    }

    /**
     * Get the value of unitConversion
     */ 
    public function getUnitConversion()
    {
        return $this->unitConversion;
    }

    /**
     * Set the value of unitConversion
     *
     * @return  self
     */ 
    public function setUnitConversion($unitConversion)
    {
        $this->unitConversion = $unitConversion;

        return $this;
    }

    /**
     * Get the value of cardImageUrl
     */ 
    public function getCardImageUrl()
    {
        return $this->cardImageUrl;
    }

    /**
     * Set the value of cardImageUrl
     *
     * @return  self
     */ 
    public function setCardImageUrl($cardImageUrl)
    {
        $this->cardImageUrl = $cardImageUrl;

        return $this;
    }

    /**
     * Get the value of fontColor
     */ 
    public function getFontColor()
    {
        return $this->fontColor;
    }

    /**
     * Set the value of fontColor
     *
     * @return  self
     */ 
    public function setFontColor($fontColor)
    {
        $this->fontColor = $fontColor;

        return $this;
    }

    /**
     * Get the value of status
     */ 
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the value of status
     *
     * @return  self
     */ 
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get the value of type
     */ 
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set the value of type
     *
     * @return  self
     */ 
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the value of defaultShow
     */ 
    public function getDefaultShow()
    {
        return $this->defaultShow;
    }

    /**
     * Set the value of defaultShow
     *
     * @return  self
     */ 
    public function setDefaultShow($defaultShow)
    {
        $this->defaultShow = $defaultShow;

        return $this;
    }

    /**
     * Get the value of createdAt
     */ 
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set the value of createdAt
     *
     * @return  self
     */ 
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get the value of updatedAt
     */ 
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set the value of updatedAt
     *
     * @return  self
     */ 
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get the value of decimalPlaces
     */ 
    public function getDecimalPlaces()
    {
        return $this->decimalPlaces;
    }

    /**
     * Set the value of decimalPlaces
     *
     * @return  self
     */ 
    public function setDecimalPlaces($decimalPlaces)
    {
        $this->decimalPlaces = $decimalPlaces;

        return $this;
    }
}
?>