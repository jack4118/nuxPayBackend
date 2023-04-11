<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * Date  21/09/2019.
 **/
class XunCommission
{
    public function __construct($db, $setting, $general)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
    }
                                                                            //1.0
    public function calculate_commission_amount($amount, $wallet_type, $exchange_rate, $decimal_place_setting, $service_charge_rate)
    {
        $setting = $this->setting;
        $decimal_places = $decimal_place_setting["decimal_places"];

        $decimal_places = $decimal_places ? $decimal_places : 8;

        if($service_charge_rate){
            $theNuxCommissionFeePct = $service_charge_rate;
        }
        elseif(is_null($service_charge_rate)){     
            $theNuxCommissionFeePct = $setting->systemSetting["theNuxCommissionFeePct"];
        }
                                                    //0.2
        $min_commission = $setting->systemSetting["theNuxCommissionFeeMin"];
        //0.005
        $commission_percentage = bcdiv((string) $theNuxCommissionFeePct, "100", 8);

        $commission_pct_amount = bcmul((string) $amount, (string) $commission_percentage, $decimal_places);  
        if($wallet_type == "tronusdt" || $wallet_type == "tetherusd"){
                $min_in_currency = $min_commission;
            }else{
            //0.2  / 1
            $min_in_currency = bcdiv((string) $min_commission, (string) $exchange_rate, $decimal_places);
            }                                                    //0.2                                    //0.5
        $commission_amount = $commission_pct_amount < $min_in_currency ? $min_in_currency : $commission_pct_amount;

        return $commission_amount;
    }

    public function process_fund_signing_list($params)
    {
        $setting = $this->setting;
        $receiver_has_fee = $params["receiver_has_fee"];
        $sender_has_fee = $params["sender_has_fee"];
        $receiver_address = $params["receiver_address"];
        $amount = $params["amount"];
        $wallet_type = $params["wallet_type"];
        $exchange_rate = $params["exchange_rate"];
        $transaction_type = $params["transaction_type"];
        $decimal_place_setting = $params["decimal_place_setting"];
        $decimal_places = $decimal_place_setting["decimal_places"];
        $currency_unit = $params["currency_unit"];
        $sender_service_charge_pct = $params["sender_service_charge_pct"];
        $receiver_service_charge_pct = $params["receiver_service_charge_pct"];

        $trading_fee_wallet_address = $setting->systemSetting["marketplaceTradingFeeWalletAddress"];

        $sender_fee_amount = 0;
        if($sender_has_fee == true){
            $sender_fee_amount = $this->calculate_commission_amount($amount, $wallet_type, $exchange_rate, $decimal_place_setting, $sender_service_charge_pct);  
        }
        $receiver_fee_amount = 0;

        if ($receiver_has_fee == true) {
            $receiver_fee_amount = $this->calculate_commission_amount($amount, $wallet_type, $exchange_rate, $decimal_place_setting, $receiver_service_charge_pct);
            $fund_transfer_amount = bcsub((string) $amount, (string) $receiver_fee_amount, $decimal_places);
        } else {
            $fund_transfer_amount = $amount;
        }

        if(bccomp((string)$fund_transfer_amount, "0", 8) < 1)
        {
            return array("code" => 0, "message" => "FAILED", "message_d" => "The minimum transfer amount is " . $receiver_fee_amount . " " . strtoupper($currency_unit));
        }

        switch ($transaction_type) {
            case "escrow":
                $walletEscrowAgentAddress = $setting->systemSetting["walletEscrowAgentAddress"];
                $fund_transfer_destination_address = $walletEscrowAgentAddress;
                break;

            default:
                $fund_transfer_destination_address = $receiver_address;
                break;
        }

        $fund_signing_arr = [];

        $fund_transfer_data = $this->map_fund_transfer_object($fund_transfer_amount, $wallet_type, $fund_transfer_destination_address, $transaction_type);
        $fund_signing_arr[] = $fund_transfer_data;

        if ($receiver_fee_amount) {
            $receiver_fee_data = $this->map_fund_transfer_object($receiver_fee_amount, $wallet_type, $trading_fee_wallet_address, "service_charge", "recipient");
            $fund_signing_arr[] = $receiver_fee_data;
        }

        if ($sender_fee_amount) {
            $sender_fee_data = $this->map_fund_transfer_object($sender_fee_amount, $wallet_type, $trading_fee_wallet_address, "service_charge", "sender");
            $fund_signing_arr[] = $sender_fee_data;
        }
        return $fund_signing_arr;
    }
                                //example //9.9   //tronusdt     //0.5        
    public function get_commission_details($amount, $wallet_type, $service_charge_rate){
        global $xunCurrency;
        $setting = $this->setting;

        $exchange_rate = $xunCurrency->get_rate($wallet_type, "usd");

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
        $decimal_places = $decimal_place_setting["decimal_places"];

        if($service_charge_rate > 0 || $service_charge_rate == null){
            $commission_amount = $this->calculate_commission_amount($amount, $wallet_type, $exchange_rate, $decimal_place_setting, $service_charge_rate);
        }
        elseif($service_charge_rate == 0){
            $commission_amount = 0;
        }
        $trading_fee_wallet_address = $setting->systemSetting["marketplaceTradingFeeWalletAddress"];

        return array("amount" => $commission_amount, "unit" => $wallet_type, "address" => $trading_fee_wallet_address);
    }

    private function map_fund_transfer_object($amount, $wallet_type, $destination_address, $transaction_type, $charged_on = null)
    {
        $data = array();

        $data["amount"] = $amount;
        $data["wallet_type"] = $wallet_type;
        $data["destination_address"] = $destination_address;
        $data["transaction_type"] = $transaction_type;
        if ($charged_on) {
            $data["charged_on"] = $charged_on;
        }

        return $data;
    }

    public function get_fund_transfer_details($params)
    {
        global $xunCrypto;
        $db = $this->db;

        $wallet_type = $params["wallet_type"];
        $exchange_rate = $params["exchange_rate"];
        $exclude_service_charge = $params["exclude_service_charge"];
        $decimal_place_setting = $params["decimal_place_setting"];
        
        $decimal_places = $decimal_place_setting["decimal_places"];

        $address_fee = $this->check_address_has_fee($params);
        if(isset($address_fee["code"]) && $address_fee["code"] == 0){
            return $address_fee;
        }

        $address_data_arr = $address_fee["address_data_arr"];
        
        if($exclude_service_charge == 1){
            $sender_has_fee = false;
            $receiver_has_fee = false;
        }else{
            $sender_has_fee = $address_fee["sender_has_fee"];
            $receiver_has_fee = $address_fee["receiver_has_fee"];
            $sender_service_charge_pct = $address_fee["sender_service_charge_pct"];
            $receiver_service_charge_pct = $address_fee["receiver_service_charge_pct"];
        }


        $params["sender_has_fee"] = $sender_has_fee;
        $params["receiver_has_fee"] = $receiver_has_fee;
        $params["sender_service_charge_pct"] = $sender_service_charge_pct;
        $params["receiver_service_charge_pct"] = $receiver_service_charge_pct;


        $fund_signing_arr = $this->process_fund_signing_list($params);

        if(isset($fund_signing_arr["code"]) && $fund_signing_arr["code"] == 0){
            return $fund_signing_arr;
        }

        $total_amount = 0;
        $unit_conversion = $xunCrypto->get_wallet_unit_conversion($wallet_type);

        for ($i = 0; $i < count($fund_signing_arr); $i++) {
            $data_amount = $fund_signing_arr[$i]["amount"];
            $total_amount = bcadd((string) $total_amount, (string) $data_amount, 8);

            $satoshi_amount = bcmul((string) $data_amount, (string) $unit_conversion);
            $usd_amount = bcmul((string) $data_amount, (string) $exchange_rate, 2);
            $fund_signing_arr[$i]["converted_amount"] = $satoshi_amount;
            $fund_signing_arr[$i]["usd_amount"] = $usd_amount;
        }

        return array("fund_signing_arr" => $fund_signing_arr,
            "total_amount" => $total_amount,
            "address_data_arr" => $address_data_arr);

    }

    public function check_address_has_fee($params)
    {
        // //  temporary
        // //  start
        // global $config;
        // $swapcoins_business_id = $config["swapcoins_business_id"];
        // //  end

        global $xunCrypto;
        $db = $this->db;

        $sender_address = $params["sender_address"];
        $receiver_address = $params["receiver_address"];

        $transaction_type = $params["transaction_type"];

        $xun_user_service = new XunUserService($db);

        $address_arr = [$sender_address, $receiver_address];
        $address_data_arr = $xun_user_service->getAddressAndUserDetailsByAddressList($address_arr);
        $sender_address_data = $address_data_arr[$sender_address];
        $receiver_address_data = $address_data_arr[$receiver_address];


        if(empty($sender_address_data)){
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid sender address.");
        }

        $sender_has_fee = false;
        $receiver_has_fee = false;

        //if sender is business charge on sender
        if ($sender_address_data["type"] == "business" && ($sender_address_data["service_charge_rate"] > 0 || $sender_address_data["service_charge_rate"] == null)) {
            $sender_has_fee = true;
            $sender_service_charge_pct = $sender_address_data["service_charge_rate"];
        }

        if ($sender_address_data["type"] == "user" && $receiver_address_data["type"] == "business" && $transaction_type != "escrow" && ($receiver_address_data["service_charge_rate"] > 0 || $receiver_address_data["service_charge_rate"] == null)) {
            $receiver_has_fee = true;
            $receiver_service_charge_pct= $receiver_address_data["service_charge_rate"];
        }

        if(empty($receiver_address_data)){
            //  check if it's payment gateway address
            $company_address_data = $xunCrypto->check_company_wallet_address($receiver_address);
            if(isset($company_address_data["type"]) && $company_address_data["type"] == "payment_gateway"){
                $sender_has_fee = false;
                $receiver_has_fee = false;
            }
        }

        if ($transaction_type == "escrow") {
            $sender_has_fee = true;
            $sender_service_charge_pct = $sender_address_data["service_charge_rate"];
        }

        return array("sender_has_fee" => $sender_has_fee, "sender_service_charge_pct" => $sender_service_charge_pct, "receiver_has_fee" => $receiver_has_fee, "receiver_service_charge_pct"=> $receiver_service_charge_pct, "address_data_arr" => $address_data_arr);
    }

    public function send_received_commission_message($user_id)
    {
        $db = $this->db;
        $setting = $this->setting;

        $db->where("id", $user_id);
        $xun_user = $db->getOne("xun_user");

        if($xun_user && $xun_user["received_commission"] == 0){
            $update_data = [];
            $update_data["received_commission"] = 1;
            $update_data["updated_at"] = date("Y-m-d H:i:s");
            $db->where("id", $user_id);
            $db->update("xun_user", $update_data);
            //  send message out
            // $username = $xun_user["username"];
            // $notification_id = 5;

            // $xun_in_app_notification = new XunInAppNotification($db, $setting);
            // $xun_in_app_notification->send_message($username, $notification_id);
        }
    }
}
