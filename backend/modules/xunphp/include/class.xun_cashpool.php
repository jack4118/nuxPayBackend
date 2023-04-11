<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  05/02/2020.
 **/
class XunCashpool
{

    public function __construct($db, $general, $setting, $account)
    {
        $this->db = $db;
        $this->general = $general;
        $this->setting = $setting;
        $this->account = $account;
    }

    public function get_translation_message($message_code)
    {
        // Language Translations.
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;
    }

    public function cashpool_topup($params)
    {
        $db = $this->db;

        $business_id = $params['business_id'];
        // $bank_name = $params['bank_name'];
        // $account_number = $params['account_number'];
        $topup_amount = $params['topup_amount'];
        // $bankslip_url = $params['bankslip_url'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        // if ($bank_name == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00439') /*Bank Name cannot be empty.*/);
        // }

        // if ($account_number == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00440') /*Bank Account Number cannot be empty.*/);
        // }

        if ($topup_amount == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00441') /*Topup Amount cannot be empty.*/);
        }

        // if ($bankslip_url == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00442') /*Bankslip URL cannot be empty.*/);
        // }

        $db->where("user_id", $business_id);
        $xun_business = $db->getOne("xun_business", "id, user_id, reward_sending_limit");

        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        $db->where('business_id', $business_id);
        $business_coin = $db->getOne('xun_business_coin');

        if (!$business_coin) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00323') /*Business coin does not exist.*/);
        }

        $business_wallet_type = $business_coin['wallet_type'];

        $db->where('user_id', $business_id);
        $db->where('active', 1);
        $db->where('address_type', 'reward');
        $crypto_user_address = $db->getOne('xun_crypto_user_address');

        if (!$crypto_user_address) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00324') /*Business Wallet Not Created.*/);
        }

        $db->where('currency_id', $business_wallet_type);
        $marketplace_currencies = $db->getOne('xun_marketplace_currencies');

        if (!$marketplace_currencies) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00399') /*No rewards coin found.*/);
        }
        $fiat_currency_id = $marketplace_currencies['fiat_currency_id'];

        $bank_details = $this->get_thenux_bank_details(array());

        $bank_name = $bank_details['data']['bank_name'];
        $account_number = $bank_details['data']['account_number'];

        $date = date("Y-m-d H:i:s");
        $insert_cashpool_topup = array(
            "business_id" => $business_id,
            "bank_name" => $bank_name,
            "account_number" => $account_number,
            "amount" => $topup_amount,
            "status" => 'pending',
            "bankslip_url" => '',
            "fiat_currency_id" => $fiat_currency_id,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $cashpool_topup_id = $db->insert('xun_cashpool_topup', $insert_cashpool_topup);

        if (!$cashpool_topup_id) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00442') /*Insert Cashpool Topup failed.*/, 'developer_msg' => $db->getLastError());
        }

        $insert_cashpool_transaction = array(
            "business_id" => $business_id,
            "fiat_currency_id" => $fiat_currency_id,
            "amount" => $topup_amount,
            "transaction_type" => 'topup',
            "recipient_user_id" => $business_id,
            "status" => 'pending',
            "reference_id" => $cashpool_topup_id,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $inserted = $db->insert('xun_cashpool_transaction', $insert_cashpool_transaction);

        if (!$inserted) {
            $update_status = array(
                "status" => 'failed',
                "updated_at" => $date,
            );

            $db->where('id', $cashpool_topup_id);
            $db->update('xun_cashpool_topup', $update_status);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00447') /*Insert Cashpool Transaction failed.*/, 'developer_msg' => $db->getLastError());
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00238') /*Cashpool Topup Success.*/);

    }

    private function get_bankslip_presign_url($file_name, $content_type, $content_size)
    {
        global $xunAws;

        $setting = $this->setting;

        // $file_name = trim($params["file_name"]);
        // $content_type = trim($params["content_type"]);
        // $content_size = trim($params["content_size"]);

        $bucket = $setting->systemSetting["awsS3ImageBucket"];
        $s3_folder = 'cashpool_bankslip';
        $timestamp = time();
        $presigned_url_key = $s3_folder . '/' . $timestamp . '/' . $file_name;
        $expiration = '+20 minutes';

        $newParams = array(
            "s3_bucket" => $bucket,
            "s3_file_key" => $presigned_url_key,
            "content_type" => $content_type,
            "content_size" => $content_size,
            "expiration" => $expiration,
        );

        $result = $xunAws->generate_put_presign_url($newParams);

        return $result;
        // if(isset($result["error"])){
        //     return array("code" => 1, "status" => "error", "statusMsg" => "Error generating AWS S3 presigned URL.", "errorMsg" => $result["error"]);
        // }

        // $return_message = "AWS presigned url.";
        // return array("code" => 0, "status" => "ok", "statusMsg" => $return_message, "data" => $result);

    }

    public function get_bankslip_url($params)
    {
        $db = $this->db;

        $business_id = trim($params["business_id"]);
        $file_name = trim($params["file_name"]);
        $content_type = trim($params["content_type"]);
        $content_size = trim($params["content_size"]);

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }
        if ($file_name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00443') /*Filename is required.*/);
        }
        if ($content_type == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00444') /*Content type is required.*/);
        }
        if ($content_size == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00445') /*Content size is required.*/);
        }

        $db->where("user_id", $business_id);
        $xun_business = $db->getOne("xun_business", "id, user_id, reward_sending_limit");

        if (!$xun_business) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00028') /*Business does not exist.*/);
        }

        $result = $this->get_bankslip_presign_url($file_name, $content_type, $content_size);

        if (isset($result["error"])) {
            return array("code" => 1, "status" => "error", "statusMsg" => $this->get_translation_message('E00446') /*Error generating AWS S3 presigned URL.*/, "errorMsg" => $result["error"]);
        }

        $return_message = $this->get_translation_message('B00239'); /*AWS presigned url.*/
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $return_message, "data" => $result);
    }

    public function cashpool_topup_list($params)
    {
        $db = $this->db;
        $setting = $this->setting;

        $business_id = trim($params['business_id']);
        $to_datetime = $params["date_to"];
        $from_datetime = $params["date_from"];
        $status = $params['status'];
        $status = strtolower($status);
        $see_all = trim($params["see_all"]);

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";

        $beneficiary_name = $setting->systemSetting['theNuxBankBeneficiaryName'];
        $bank_swiftcode = $setting->systemSetting['theNuxBankSwiftCode'];

        if ($page_number < 1) {
            $page_number = 1;
        }

        //Get the limit.
        if(!$see_all){
            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        }

        if ($from_datetime) {
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where('created_at', $from_datetime, '>=');
        }

        if ($to_datetime) {
            $to_datetime = date("Y-m-d H:i:s", $to_datetime);
            $db->where('created_at', $to_datetime, '<');
        }

        if ($status) {
            $db->where('status', $status);
        }
        $db->where('business_id', $business_id);
        $db->orderBy('id', 'DESC');
        $copyDb = $db->copy();
        //$copyDb2 = $db->copy();
        $cashpool_topup = $db->get('xun_cashpool_topup', $limit);

        if (!$cashpool_topup) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00001') /*No Results Found.*/, 'data' => '');
        }
        
//         $sq = $copyDb2->subQuery ();
//         $sq->orderBy('id', 'desc');
//         $sq->get ("xun_cashpool_topup", $limit, "amount");

//         $db->where('amount', $sq, 'in');
//         $totalAmount = $db->get('xun_cashpool_topup',null, 'sum(amount)');
//         print_r($limit);
//         //$totalAmount = $copyDb2->get('xun_cashpool_topup', $limit, 'sum(amount) as totalAmount');
// print_r($totalAmount);
//         echo "totalAmount".$totalAmount;
        $totalRecord = $copyDb->getValue('xun_cashpool_topup', 'count(id)');

        $totalAmount = 0;
        foreach ($cashpool_topup as $key => $value) {
            $status = ucfirst($value['status']);
            $topup_amount = $value['amount'];

            $cashpool_topup[$key]['topup_amount'] = $topup_amount;
            $cashpool_topup[$key]['status'] = $status;
            $cashpool_topup[$key]['beneficiary_name'] = $beneficiary_name;
            $cashpool_topup[$key]['bank_swiftcode']= $bank_swiftcode;
            unset($cashpool_topup[$key]['amount']);
            $totalAmount = bcadd($totalAmount , $topup_amount, 8);
        }

        $num_record = !$see_all ? count($cashpool_topup) : $totalRecord;
        $total_page = ceil($totalRecord/$num_record);

        $returnData['data'] = $cashpool_topup;
        $returnData['total_amount'] = $totalAmount;
        $returnData['totalPage'] = $total_page;
        $returnData['pageNumber'] = $page_number;
        $returnData['totalRecord'] = $totalRecord;
        $returnData['numRecord'] = $num_record;
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00240') /*Cashpool Topup Listing.*/, 'data' => $returnData);

    }

    public function cashpool_transaction_list($params)
    {
        $db = $this->db;
        $setting = $this->setting;

        $business_id = $params['business_id'];
        $to_datetime = $params["date_to"];
        $from_datetime = $params["date_from"];
        $transaction_type = $params['transaction_type'];
        $status = $params['status'];
        $status = strtolower($status);
        $see_all = trim($params["see_all"]);

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";

        $tx_type_list = array("Topup", "Send Reward");

        if ($page_number < 1) {
            $page_number = 1;
        }

        //Get the limit.
        if(!$see_all){
            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        }

        if ($from_datetime) {
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where('created_at', $from_datetime, '>=');
        }

        if ($to_datetime) {
            $to_datetime = date("Y-m-d H:i:s", $to_datetime);
            $db->where('created_at', $to_datetime, '<');
        }

        if ($transaction_type == 'Topup') {
            $db->where('transaction_type', 'topup');
        } elseif ($transaction_type == 'Send Reward') {
            $db->where('transaction_type', 'send_reward');
        }

        if ($status) {
            $db->where('status', $status);
        }

        $db->orderBy('id', 'DESC');
        $db->where('business_id', $business_id);
        $copyDb = $db->copy();
        $cashpool_tx = $db->get('xun_cashpool_transaction', $limit);

        if (!$cashpool_tx) {
            $error_tx_list['tx_type_list'] = $tx_type_list;
            $data['data'] = $error_tx_list;
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00001') /*No Results Found.*/, "data" => $data);
        }
        $user_id_arr = [];

        $user_id_arr[] = $business_id;
        foreach ($cashpool_tx as $key => $value) {
            $recipient_user_id = $value['recipient_user_id'];

            if (!in_array($recipient_user_id, $user_id_arr)) {
                array_push($user_id_arr, $recipient_user_id);
            }
        }

        $db->where('id', $user_id_arr, 'IN');
        $xun_user = $db->map('user_id')->ArrayBuilder()->get('xun_user');

        foreach ($xun_user as $key => $value) {
            if ($value['type'] == 'business') {
                $business_id_arr[] = $value['id'];
            }
        }

        $db->where('user_id', $business_id_arr, 'IN');
        $xun_business_account = $db->map('user_id')->ArrayBuilder()->get('xun_business_account', null, 'id, user_id, main_mobile');

        $db->where('user_id', $business_id_arr, 'IN');
        $xun_business = $db->map('user_id')->ArrayBuilder()->get('xun_business', null, 'id, user_id, name');

        $total_amount_in = 0;
        $total_amount_out = 0;
        foreach ($cashpool_tx as $key => $value) {
            $status = ucfirst($value['status']);
            $transaction_type = $value['transaction_type'];
            $topup_amount = $value['amount'];
            $amount_in = "0";
            $amount_out = "0";
            $recipient_user_id = $value['recipient_user_id'];
            $name = $xun_business[$recipient_user_id]['name'];
            $phone_number = $xun_business_account[$recipient_user_id]['main_mobile'];
            $reference_id = $value['reference_id'];

            // if ($transaction_type == 'topup') {
            //     $transaction_type = ucfirst($transaction_type);
            //     $amount_in = $topup_amount;
            //     if ($xun_user[$business_id]['nickname']) {
            //         $to_from = $xun_user[$business_id]['nickname'];
            //     } elseif ($xun_business[$business_id]['name']) {
            //         $to_from = $xun_business[$business_id]['name'];
            //     } else {
            //         $to_from = '';
            //     }

            // } elseif ($transaction_type == 'send_reward') {
            //     $transaction_type = "Send Reward";
            //     $amount_out = $topup_amount;
            //     if ($xun_user[$recipient_user_id]['nickname']) {
            //         $to_from = $xun_user[$recipient_user_id]['nickname'];
            //     } elseif ($xun_business[$recipient_user_id]['name']) {
            //         $to_from = $xun_business[$recipient_user_id]['name'];
            //     } else {
            //         $to_from = '';
            //     }

            // }

            //$cashpool_tx[$key]['status'] = $status;
            $cashpool_tx[$key]['transaction_type'] = $transaction_type;
            $cashpool_tx[$key]['amount_in'] = $amount_in;
            $cashpool_tx[$key]['amount_out'] = $amount_out;
            $cashpool_tx[$key]['name'] = $name;
            $cashpool_tx[$key]['phone_number'] = $phone_number;
            // $cashpool_tx[$key]['to_from'] = $to_from;
            unset($cashpool_tx[$key]['amount']);
            $total_amount_in = bcadd($total_amount_in, $amount_in, 8);
            $total_amount_out = bcadd($total_amount_out , $amount_out, 8);

        }

        $totalRecord = $copyDb->getValue('xun_cashpool_transaction', 'count(id)');

        $num_record = !$see_all ? count($cashpool_tx) : $totalRecord;
        $total_page = ceil($totalRecord/$num_record);

        $data['tx_type_list'] = $tx_type_list;
        $data['cashpool_tx'] = $cashpool_tx;
        $returnData['data'] = $data;
        $returnData['total_amount_in'] = $total_amount_in;
        $returnData['total_amount_out'] = $total_amount_out;
        $returnData['totalPage'] = $total_page;
        $returnData['pageNumber'] = $page_number;
        $returnData['totalRecord'] = $totalRecord;
        $returnData['numRecord'] = $num_record;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00241') /*Cashpool Transaction Listing.*/, "data" => $returnData);

    }

    public function get_thenux_bank_details($params)
    {
        $db = $this->db;

        $db->where('name', array('thenuxBankName', 'thenuxBankAccount', 'theNuxBankBeneficiaryName', 'theNuxBankSwiftCode','theNuxBankCountry'), 'IN');
        $system_settings = $db->get('system_settings');

        foreach ($system_settings as $key => $value) {
            $name = $value['name'];

            if ($name == 'thenuxBankName') {
                $bank_name = $value['value'];
            } elseif ($name == 'thenuxBankAccount') {
                $account_number = $value['value'];
            } elseif($name == 'theNuxBankBeneficiaryName') {
                $beneficiary_name = $value['value'];
            } elseif($name == 'theNuxBankSwiftCode') {
                $bank_swiftcode = $value['value'];
            } elseif($name == 'theNuxBankCountry') {
                $bank_country = $value['value'];
            }

        }

        $bank_details = array(
            "bank_name" => $bank_name,
            "account_number" => $account_number,
            "beneficiary_name" => $beneficiary_name,
            "bank_swiftcode" => $bank_swiftcode,
            "bank_country" => $bank_country
        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00243') /*TheNux Bank Details.*/, "data" => $bank_details);
    }

    public function update_bankslip_url($params)
    {
        $db = $this->db;

        $id = $params['id'];
        $bankslip_url = $params['bankslip_url'];

        if ($id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00006') /*ID cannot be empty.*/);
        }

        if ($bankslip_url == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00442') /*Bankslip URL cannot be empty.*/);
        }

        $db->where('id', $id);
        $cashpool_topup = $db->getOne('xun_cashpool_topup');

        if (!$cashpool_topup) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00448') /*Topup Transaction not found.*/);
        }

        $update_bankslip = array(
            "bankslip_url" => $bankslip_url,
            "status" => 'processing',
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $db->where('id', $id);
        $updated = $db->update('xun_cashpool_topup', $update_bankslip);

        if (!$updated) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, 'developer_msg' => $db->getLastError());
        }

        $update_status = array(
            "status" => 'processing',
            "updated_at" => date("Y-m-d H:i:s"),
        );
        $db->where('reference_id', $id);
        $updated = $db->update('xun_cashpool_transaction', $update_status);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00244') /*Update Bankslip Success.*/);

    }

}
