<?php

class XunServiceCharge
{
    public function __construct($db, $setting, $general)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
    }

    public function insert_service_charge($params)
    {
        $db = $this->db;

        $status = "pending";
        $date = date("Y-m-d H:i:s");
        $ori_tx_wallet_type = $params["ori_tx_wallet_type"];
        $ori_tx_wallet_type = $ori_tx_wallet_type ? $ori_tx_wallet_type : $params["wallet_type"];
        $insert_data = array(
            "user_id" => $params["user_id"],
            "wallet_transaction_id" => $params["wallet_transaction_id"],
            "fund_out_table" => $params['fund_out_table'],
            "fund_out_id" => $params['fund_out_id'],
            "amount" => $params["amount"],
            "wallet_type" => $params["wallet_type"],
            "service_charge_type" => $params["service_charge_type"],
            "transaction_type" => $params["transaction_type"],
            "received_transaction_hash" => $params['received_transaction_hash'] ?: "",
            "transaction_hash" => $params["transaction_hash"] ?: "",
            "service_charge_transaction_hash" => $params['service_charge_transaction_hash'] ?: "",
            "ori_tx_wallet_type" => $ori_tx_wallet_type,
            "ori_tx_amount" => $params["ori_tx_amount"],
            "status" => $status,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $row_id = $db->insert("xun_service_charge_audit", $insert_data);
        return $row_id;
    }

    public function update_service_charge($wallet_transaction_id, $status = null, $pgTransactionHash = '', $transactionHash = '')
    {
        $db = $this->db;

        $data = $this->get_service_charge_by_wallet_transaction_id($wallet_transaction_id);
        if ($data) {
            $date = date("Y-m-d H:i:s");

            $update_data = [];
            if($pgTransactionHash){
                $update_data["transaction_hash"] = $pgTransactionHash;
            }
            if($status){
                $update_data["status"] = $status;
            }
            $update_data["updated_at"] = $date;
            if($transactionHash){
                $update_data['service_charge_transaction_hash'] = $transactionHash;
            }

            $db->where("id", $data["id"]);
            $ret_val = $db->update("xun_service_charge_audit", $update_data);

        }
        return $data;
    }

    public function get_service_charge_by_wallet_transaction_id($wallet_transaction_id)
    {
        $db = $this->db;
        $db->where("wallet_transaction_id", $wallet_transaction_id);
        $data = $db->getOne("xun_service_charge_audit");

        return $data;
    }

    public function get_service_charge_by_id($id)
    {
        $db = $this->db;
        $db->where("id", $id);
        $data = $db->getOne("xun_service_charge_audit");

        return $data;
    }

    public function calculate_upline_trading_fee_quantity($params, $decimal_place_setting)
    {
        global $xunTree, $xunCrypto;
        $db = $this->db;
        $setting = $this->setting;

        $trading_fee_quantity = $params["amount"];
        $user_id = $params["user_id"];
        $transaction_callback_user_id = $params["transaction_callback_user_id"];
        $wallet_type = $params["wallet_type"];
        // $upline_id = $xunTree->getSponsorUplineIDByUserID($user_id);

        //check service charge rate
        $db->where('id', $transaction_callback_user_id);
        $user_service_charge_result = $db->getOne('xun_user', 'id, username, service_charge_rate');
        $decimal_places = $decimal_place_setting["decimal_places"];

        // $xun_user_service = new XunUserService($db);

        // if (!$upline_id) {
        $company_pool_pct = 100;
        $upline_pct = 0;
        // } else {
        //     // check if upline is eligible for bonus trading fee

        //     $upline_address_data = $xun_user_service->getActiveInternalAddressByUserID($upline_id, "id, user_id, address");

        //     $has_upline_commission = false;
        //     if ($setting->systemSetting["tradingFeeUplineStatus"] == 1) { //  setting to on/off upline commission
        //         $has_upline_commission = true;
        //     } else {
        //         $exception_upline_commission = $setting->systemSetting["tradingFeeUplineExceptionUser"];
        //         $exception_upline_commission_list = explode(",", $exception_upline_commission);
        //         if (in_array($transaction_callback_user_id, $exception_upline_commission_list)) {
        //             $has_upline_commission = true;
        //         }
        //     }

        //     if ($has_upline_commission === true) {
        //         if ($upline_address_data) {
        //             $upline_address = $upline_address_data["address"];

        //             try {
        //                 $upline_wallet_balance = $xunCrypto->get_wallet_balance($upline_address, "thenuxcoin");
        //             } catch (exception $e) {
        //                 $upline_wallet_balance = 0;
        //             }

        //             $upline_wallet_tnc_min = $setting->systemSetting["tradingFeeUplineBonusTNCAmount"];

        //             if (bccomp((string) $upline_wallet_balance, (string) $upline_wallet_tnc_min, 8) >= 0) {
        //                 $upline_pct = $setting->systemSetting["tradingFeeUplineBonusPercentage"];
        //             } else {
        //                 $upline_pct = $setting->systemSetting["tradingFeeUplinePercentage"];
        //             }

        //         }
        //     } else {
        //         $upline_pct = 0;
        //     }

        //     $company_pool_pct = bcsub("100", (string) $upline_pct);
        // }

        $company_pool_pct = bcdiv((string) $company_pool_pct, "100", 8);
        $upline_pct = bcdiv((string) $upline_pct, "100", 8);

        $upline_quantity = bcmul((string) $upline_pct, $trading_fee_quantity, $decimal_places);

        $company_pool_quantity = bcsub((string) $trading_fee_quantity, (string) $upline_quantity, $decimal_places);
        
        $marketplaceTradingFee = $setting->systemSetting["marketplaceTradingFee"];

        if ($user_service_charge_result['service_charge_rate'] == $marketplaceTradingFee ) {
            $company_pool_address = $setting->systemSetting["marketplaceCompanyPoolWalletAddress2"];
        } else {
            $company_pool_address = $setting->systemSetting["marketplaceCompanyPoolWalletAddress"];
        }

        $fund_out_arr = [];

        $company_pool_fund_out_data = [];
        $company_pool_fund_out_data["destination_address"] = $company_pool_address;
        $company_pool_fund_out_data["amount"] = $company_pool_quantity;
        $company_pool_fund_out_data["wallet_type"] = $wallet_type;
        $company_pool_fund_out_data["address_type"] = "company_pool";

        $fund_out_arr["company_pool"] = $company_pool_fund_out_data;

        // if ($upline_quantity > 0) {
        //     $upline_fund_out_data = [];
        //     $upline_fund_out_data["destination_address"] = $upline_address;
        //     $upline_fund_out_data["amount"] = $upline_quantity;
        //     $upline_fund_out_data["wallet_type"] = $wallet_type;
        //     $upline_fund_out_data["user_id"] = $upline_id;
        //     $upline_fund_out_data["address_type"] = "upline";
        //     $fund_out_arr["upline"] = $upline_fund_out_data;
        // }

        return $fund_out_arr;

    }

    public function calculate_master_upline_trading_fee_quantity($params, $decimal_place_setting, $has_master_upline_fee = 1)
    {
        global $setting, $xunTree;
        $db = $this->db;

        $xun_user_service = new XunUserService($db);
        $trading_fee_quantity = $params["amount"];
        $user_id = $params["user_id"]; // service charge user
        $wallet_type = $params["wallet_type"];
        // if ($has_master_upline_fee == 1) {
        //     $master_upline_id = $xunTree->getSponsorMasterUplineIDByUserID($user_id);
        // }

        // if (!$master_upline_id) {
        $company_acc_pct = 100;
            // $master_upline_pct = 0;
        // } else {
        //     $company_acc_pct = $setting->systemSetting["tradingFeeCompanyAccPercentage"];
        //     // $master_upline_pct = bcsub("100", (string)$company_acc_pct);
        // }

        $decimal_places = $decimal_place_setting["decimal_places"];

        $company_acc_pct = bcdiv((string) $company_acc_pct, "100", 8);
        $master_upline_pct = bcsub("1", (string) $company_acc_pct, 8);

        $master_upline_quantity = bcmul((string) $master_upline_pct, $trading_fee_quantity, $decimal_places);
        $company_acc_quantity = bcsub((string) $trading_fee_quantity, (string) $master_upline_quantity, $decimal_places);

        $company_acc_address = $setting->systemSetting["marketplaceCompanyAccWalletAddress"];

        $fund_out_arr = [];
        $company_acc_fund_out_data = [];
        $company_acc_fund_out_data["destination_address"] = $company_acc_address;
        $company_acc_fund_out_data["amount"] = $company_acc_quantity;
        $company_acc_fund_out_data["wallet_type"] = $wallet_type;
        $company_acc_fund_out_data["address_type"] = "company_acc";

        $fund_out_arr["company_acc"] = $company_acc_fund_out_data;


        // if ($master_upline_id) {
        //     $master_upline_address_data = $xun_user_service->getActiveInternalAddressByUserID($master_upline_id, "id, user_id, address");
        //     $master_upline_address = $master_upline_address_data["address"];

        //     $master_upline_fund_out_data = [];
        //     $master_upline_fund_out_data["destination_address"] = $master_upline_address;
        //     $master_upline_fund_out_data["amount"] = $master_upline_quantity;
        //     $master_upline_fund_out_data["wallet_type"] = $wallet_type;
        //     $master_upline_fund_out_data["user_id"] = $master_upline_id;
        //     $master_upline_fund_out_data["address_type"] = "master_upline";
        //     $fund_out_arr["master_upline"] = $master_upline_fund_out_data;
        // }

        return $fund_out_arr;
    }

    public function getBusinessMarketerCommissionScheme($business_id, $wallet_type)
    {
        $db = $this->db;

        $lc_wallet_type = strtolower($wallet_type);
        $db->where('business_id', $business_id);
        $db->where('wallet_type', $lc_wallet_type);
        $db->where('disabled', 0);
        $db->where("commission_rate", 0, ">");
        $business_marketer_commission_scheme = $db->get('xun_business_marketer_commission_scheme');

        return $business_marketer_commission_scheme;
    }

    public function getServiceChargeByTxHash($transaction_hash){
        $db = $this->db;

        $db->where('service_charge_transaction_hash', $transaction_hash);
        $service_charge_result = $db->getOne('xun_service_charge_audit');

        return $service_charge_result;
    }

    public function updateServiceChargeTransaction($params, $padded_amount){
        $db= $this->db;
        
        $transactionHash = trim($params["exTransactionHash"]) ?  trim($params["exTransactionHash"]) : $params["transactionHash"];

        $service_charge_result = $this->getServiceChargeByTxHash($transactionHash);

        if(!$service_charge_result){
            return;
        }

        if($service_charge_result['status'] == 'completed'){

            $service_charge_result['is_completed'] = 1;
            return $service_charge_result;
        }

        $fund_out_table = $service_charge_result['fund_out_table'];
        $fund_out_id = $service_charge_result['fund_out_id'];

        $senderAddress = $params['sender'];
        $recipientAddress = $params['recipient'];
        $amount = $params['amount'];
        $transactionToken = $params["transactionToken"];
        $walletType = strtolower($params["wallet_type"]);
        $transactionType = $params["type"];
        $target = trim($params["target"]);
        // $fee = $params['fee'];
        // $feeUnit = $params["feeUnit"];
        // $feeRate = $params['feeRate'];
        $status = $params["status"];
        $minerFeeExchangeRate = $params['minerFeeExchangeRate'];
        $exchangeRate =  implode(":",  $params['exchangeRate']);

        $db->where('symbol', $feeUnit);
        $marketplaceCurrencies = $db->getOne('xun_marketplace_currencies', 'id, currency_id');

        $minerFeeWalletType = $marketplaceCurrencies['currency_id'];
        $updateData["sender_address"] = $senderAddress;
        $updateData["recipient_address"] = $recipientAddress;
        $updateData["transaction_id"] = $transactionHash ? $transactionHash : '';
        $updateData["status"] = $status == 'completed' || $status == 'confirmed' ? 'success' : $status;
        $updateData["amount"] = $padded_amount;
        $updateData["wallet_type"] = $walletType;
        $updateData["updated_at"] = $date;
        // $updateData['fee_amount'] = $fee;
        // $updateData['fee_wallet_type'] = $minerFeeWalletType;
        $updateData["exchange_rate"] = $exchangeRate;
        $updateData["miner_fee_exchange_rate"] = $minerFeeExchangeRate;
        $updateData['updated_at'] = date("Y-m-d H:i:s");

        $db->where('id', $fund_out_id);
        $row_id = $db->update($fund_out_table, $updateData);
        return $row_id;
    }
}
