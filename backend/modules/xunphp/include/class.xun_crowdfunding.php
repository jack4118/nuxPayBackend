<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/
class XunCrowdfunding
{

    public function __construct($db, $post, $general, $setting)
    {
        $this->db = $db;
        $this->post = $post;
        $this->general = $general;
        $this->setting = $setting;
    }

    public function get_profit_sharing_details($params){
        global $xunAdmin;
        $db = $this->db;
        $setting = $this->setting;

        $last_id = $params["last_id"] ? $params["last_id"] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 10;

        $limit = array($last_id, $page_size);

        $coin_list = $xunAdmin->get_coins_list();

        $serviceChargeUserID = $setting->systemSetting["serviceChargeUserID"];

        // $xun_coins = $db->get('xun_coins');

        // foreach($xun_coins as $key => $value){
        //     $currency_id = $value["currency_id"];

        //     $type = $currency_id ."ServiceChargeCredit";

        //     echo "type".$type."\n";
        //     $type_list [] = $type;
        // }
        $db->where('user_id', $serviceChargeUserID);
        $db->where('type', "bitcoinServiceChargeCredit");
        $db->orderBy('id', 'desc');
        $acc_closing = $db->getOne('xun_acc_closing');
        
        $date = $acc_closing["date"] ? $acc_closing["date"] : "1970-01-01";
        $nextDay = date("Y-m-d", strtotime("$date + 1 days"));
        
        $db->where('status', "completed");
        $db->orderBy('created_at', "DESC");
        $copyDb = $db->copy();
        $sc_audit = $db->get('xun_service_charge_audit', $limit, "wallet_type, amount, service_charge_type, transaction_type, created_at");

        $totalRecord = $copyDb->getValue('xun_service_charge_audit', "count(id)");

        $db->where('created_at', $nextDay, ">=");
        $db->where('status', "completed");
        $sc_monthly_data = $db->get('xun_service_charge_audit', null, "id, wallet_type, amount ");
        $id_arr = [];
        foreach ($sc_monthly_data as $sc_key => $sc_value){
            $id = $sc_value["id"];
            array_push($id_arr, $id);
        }

        $address_type = array("master_upline", "upline");
        $db->where('reference_id', $id_arr, "IN");
        $db->where('status', "completed");
        $db->where('address_type', $address_type, "IN");
        $wallet_tx = $db->get('xun_wallet_transaction', null, "amount, wallet_type, address_type");

        $db->where('cryptocurrency_id', $coin_list, "IN");
        $xun_crypto = $db->get('xun_cryptocurrency_rate', null, "cryptocurrency_id, unit, value");

        foreach($xun_crypto as $crypto_key => $crypto_value){
            $cryptocurrency_id = $crypto_value["cryptocurrency_id"];
            $lower_cryptocurrency_id = strtolower($cryptocurrency_id);
            $unit = $crypto_value["unit"];
            $value = $crypto_value["value"];

            $crypto_array[$lower_cryptocurrency_id]["cryptocurrency_id"] = $lower_cryptocurrency_id;
            $crypto_array[$lower_cryptocurrency_id]["unit"] = $unit;
            $crypto_array[$lower_cryptocurrency_id]["value"] = $value;

        }

        $sc_list = [];
        foreach($sc_audit as $key=> $value){
            $wallet_type = $value["wallet_type"];
            $amount = $value["amount"];
            $service_charge_type = $value["service_charge_type"];
            $transaction_type = $value["transaction_type"];
            $created_at = $value["created_at"];

            $crypto_fiat_price = $crypto_array[$wallet_type]["value"];
            $coin_type = $crypto_array[$wallet_type]["unit"];
            $upper_coin_type = strtoupper($coin_type);
            $converted_sc_amount = bcmul($amount, $crypto_fiat_price, "2");

            $new_tx_type = $xunAdmin->map_transaction_type($service_charge_type, $transaction_type);

            $sc_array = array(
                "tx_type" => $new_tx_type,
                "coin_type" => $upper_coin_type,
                "amount" => $amount,
                "charges" => $converted_sc_amount,
                "created_at" => $created_at,
            );

            $sc_list[] = $sc_array;
        }

        $total_sc_amount = 0;
        $total_referral_amount = 0;
        $total_profit_amount = 0;
        foreach($sc_monthly_data as $month_key=> $month_value){
            $wallet_type = $month_value["wallet_type"];
            $amount = $month_value["amount"];

            $crypto_fiat_price = $crypto_array[$wallet_type]["value"];
            $coin_type = $crypto_array[$wallet_type]["unit"];
            $upper_coin_type = strtoupper($coin_type);
            $converted_sc_amount = bcmul((string)$amount, (string)$crypto_fiat_price, "2");

            $total_sc_amount = bcadd((string)$total_sc_amount, (string)$converted_sc_amount, "2");

        }

        foreach($wallet_tx as $wallet_key => $wallet_value){
            $wallet_type = $wallet_value["wallet_type"];
            $amount = $wallet_value["amount"];
            $address_type = $wallet_value["address_type"];

            $crypto_fiat_price = $crypto_array[$wallet_type]["value"];
            $coin_type = $crypto_array[$wallet_type]["unit"];
            $upper_coin_type = strtoupper($coin_type);

            if($address_type == "upline" || $address_type == "master_upline"){
                $converted_referral_amount = bcmul((string)$amount, (string)$crypto_fiat_price, "2");
                $total_referral_amount = bcadd((string)$total_referral_amount,(string) $converted_referral_amount, "2");
            }
        }
        $total_profit_amount = bcsub($total_sc_amount ,$total_referral_amount, 2);
        $thenuxcoin_price = number_format($crypto_array["thenuxcoin"]["value"], 2);

        $numRecord = count($sc_list);
        $data["service_charge_list"] = $sc_list;
        $data["totalRecord"] = $totalRecord;
        $data["numRecord"] = $numRecord;
        $data["totalPage"] = ceil($totalRecord / $page_size);
        $data["last_id"] = $last_id + $numRecord;
        
        $returnData["thenuxcoin_price"] = $thenuxcoin_price;
        $returnData["monthly_total_sc_amount"] = $total_sc_amount;
        $returnData["referral_cost"] = $total_referral_amount;
        $returnData["profile_sharing_amount"] = $total_profit_amount;
        $returnData["data"] = $data;
        
        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => "Profit Sharing Details.", 'data' => $returnData);
    }

}

