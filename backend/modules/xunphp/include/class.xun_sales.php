<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/
class XunSales
{

    public function __construct($db, $partnerDB, $post, $general, $setting)
    {
        $this->db = $db;
        $this->partnerDB = $partnerDB;
        $this->post = $post;
        $this->general = $general;
        $this->setting = $setting;
    }

    public function get_translation_message($message_code)
    {
        // Language Translations.
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;
    }

    public function add_customer_sales($params)
    {
        $db = $this->db;
        $partnerDB = $this->partnerDB;
        $general = $this->general;

        $business_id = $params['business_id'];
        $sales_info = $params['sales_info'];

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot empty");
        }

        foreach ($sales_info as $key => $value) {
            $mobile = trim($value['mobile']);
            $amount = trim($value['amount']);

            if($mobile == "") return array('code' => 0, 'message' => "FAILED", 'message_d' => $mobile . " " . $this->get_translation_message('E00481') /*Mobile number cannot be empty.*/);

            $mobileNumberInfo = $general->mobileNumberInfo($mobile, "MY");
            if ($mobileNumberInfo["isValid"] == 1) {
                $mobile = "+" . $mobileNumberInfo["mobileNumberWithoutFormat"];
                $sales_info[$key]["mobile"] = $mobile;
                $mobile_arr[] = $mobile;
            } else {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $mobile . " " . $this->get_translation_message('B00253') /*xxx Invalid mobile number.*/);
            }

            if($amount == "") return array('code' => 0, 'message' => "FAILED", 'message_d' => $mobile . " " . $this->get_translation_message('E00477') /*xxx Amount cannot be empty.*/);

            if(!is_numeric($amount)) return array('code' => 0, 'message' => "FAILED", 'message_d' => $mobile . " " . $this->get_translation_message('E00478') /*xxx Only digits acceptable.*/);
        }

        $partnerDB->where('mobile', $mobile_arr, 'IN');
        $partnerDB->where('business_id', $business_id);
        $business_user = $partnerDB->get('business_user', null, 'mobile');

        $business_user = array_column($business_user, 'mobile');
        $new_user = array_diff($mobile_arr, $business_user);

        $db->where('type', 'user');
        $db->where('username', $mobile_arr, 'IN');
        $xun_user = $db->map('username')->ArrayBuilder()->get('xun_user');

        foreach ($new_user as $new_mobile) {
            $is_registered = $xun_user[$new_mobile] ? 1 : 0;
            $insert_partner = array(
                "business_id" => $business_id,
                "mobile" => $new_mobile,
                "is_registered" => $is_registered,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $insert_partner_arr[] = $insert_partner;
        }

        if ($insert_partner_arr) {
            $partnerDB->insertMulti('business_user', $insert_partner_arr);
        }

        foreach ($sales_info as $sales_key => $sales_value) {
            $mobile = $sales_value['mobile'];
            $amount = $sales_value['amount'];
            $user_id = $xun_user[$mobile]['id'] ? $xun_user[$mobile]['id'] : '';

            $insert_sales = array(
                "business_id" => $business_id,
                "user_id" => $user_id,
                "mobile" => $mobile,
                "amount" => $amount,
                "created_at" => date("Y-m-d H:i:s"),
            );

            $sales_arr[] = $insert_sales;
        }

        if ($sales_arr) {
            $sales_tx = $db->insertMulti('xun_sales_transaction', $sales_arr);

            if (!$sales_tx) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }
        }

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00249') /*Add Customer Sales Success.*/);
    }

    // Remark 1 : File large 100kb run in process (processImportUploadedFile.php)
    // Remark 2 : Excel content more then 100 row run in process
    // Remark 3 : else import on the spot
    public function import_customer_sales($params)
    {
        $db = $this->db;
        $partnerDB = $this->partnerDB;
        $general = $this->general;

        $business_id = $params['business_id'];
        $attachment_name = $params["attachment_name"];
        $attachment_data = $params["attachment_data"];
        $attachment_type = $params["attachment_type"];

        if ($business_id == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/, "developer_msg" => "business_id cannot empty");
        }

        if ($attachment_name == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00473') /*Attachment name cannot be empty.*/);
        }

        if ($attachment_type == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00474') /*Attachment type cannot be empty.*/);
        }

        if ($attachment_data == "") {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00474') /*Attachment data cannot be empty.*/);
        }

        // get creator type
        $db->where("id", $business_id);
        $account_type = $db->getValue("xun_user", "type");

        $tmp_file_name = "customer_sales_" . $business_id . "_" . time();
        $file_data = explode(",", $attachment_data);
        $file = base64_decode($file_data[1]);
        $tmp_handle = tempnam(sys_get_temp_dir(), $tmp_file_name); // create empty file
        $handle = fopen($tmp_handle, 'r+'); // open file
        fwrite($handle, $file); // put content into file
        rewind($handle);
        $tmp_file_size = filesize($tmp_handle); // Returns the size of the file in bytes 1kb = 1000bytes (100kb = 100000bytes)

        // 2MB = 200000bytes
        if($tmp_file_size > 200000){
            // close file & remove opened file in temp dir
            fclose($handle); unlink($tmp_handle);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00487') /*Uploaded file larger than 2MB.*/);
        }

        // File large 100kb run in process
        if($tmp_file_size > 100000){
            // insert import_data
            $dataInsert = array(
                'type' => "customerSales",
                'file_name' => $attachment_name,
                'creator_id' => $business_id,
                'creator_type' => $account_type,
                'created_at' => date("Y-m-d H:i:s"),
            );
            $import_id = $db->insert('xun_import_data', $dataInsert);
            if (empty($import_id)) {
                // close file & remove opened file in temp dir
                fclose($handle); unlink($tmp_handle);

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "insert import fail. Error:" . $db->getLastError());
            }

            // insert upload table
            $dataInsert2 = array(
                'file_type' => $attachment_type,
                'file_name' => $attachment_name,
                'data' => $file,
                'type' => "customerSales",
                'reference_id' => $import_id,
                'created_at' => date("Y-m-d H:i:s"),
            );
            $upload_id = $db->insert('uploads', $dataInsert2);
            if (empty($upload_id)) {
                // close file & remove opened file in temp dir
                fclose($handle); unlink($tmp_handle);

                //update upload file id
                $db->where("id", $import_id);
                $db->update("xun_import_data", array("reason" => "uploadFailed", "status" => "failed"));

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "Insert upload fail. Error:" . $db->getLastError());
            }

            //update upload file id
            $db->where("id", $import_id);
            $db->update("xun_import_data", array("upload_id" => $upload_id, "status" => "scheduled"));

            // close file & remove opened file in temp dir
            fclose($handle); unlink($tmp_handle);

            return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00252') /*Upload Customer Sales Success.*/);
        }

        include_once 'PHPExcel.php';
        $file_type = PHPExcel_IOFactory::identify($tmp_handle);
        $objReader = PHPExcel_IOFactory::createReader($file_type);

        $excel_obj = $objReader->load($tmp_handle);
        $worksheet = $excel_obj->getSheet(0);
        $lastRow = $worksheet->getHighestRow();
        $lastCol = $worksheet->getHighestColumn();

        if ($lastRow <= 1) {
            // close file & remove opened file in temp dir
            fclose($handle); unlink($tmp_handle);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00470') /*File content empty.*/);
        }

        // Excel content more then 100 row run in process
        if($lastRow > 100){
            // insert import_data
            $dataInsert = array(
                'type' => "customerSales",
                'file_name' => $attachment_name,
                'creator_id' => $business_id,
                'creator_type' => $account_type,
                'created_at' => date("Y-m-d H:i:s"),
            );
            $import_id = $db->insert('xun_import_data', $dataInsert);
            if (empty($import_id)) {
                // close file & remove opened file in temp dir
                fclose($handle); unlink($tmp_handle);

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "insert import fail. Error:" . $db->getLastError());
            }

            // insert upload table
            $dataInsert2 = array(
                'file_type' => $attachment_type,
                'file_name' => $attachment_name,
                'data' => $file,
                'type' => "customerSales",
                'reference_id' => $import_id,
                'created_at' => date("Y-m-d H:i:s"),
            );
            $upload_id = $db->insert('uploads', $dataInsert2);
            if (empty($upload_id)) {
                // close file & remove opened file in temp dir
                fclose($handle); unlink($tmp_handle);

                //update upload file id
                $db->where("id", $import_id);
                $db->update("xun_import_data", array("reason" => "uploadFailed", "status" => "failed"));

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "Insert upload fail. Error:" . $db->getLastError());
            }

            //update upload file id
            $db->where("id", $import_id);
            $db->update("xun_import_data", array("upload_id" => $upload_id, "status" => "scheduled"));

            // close file & remove opened file in temp dir
            fclose($handle); unlink($tmp_handle);

            return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00252') /*Upload Customer Sales Success.*/);
        }

        // insert import_data
        $dataInsert = array(
            'type' => "customerSales",
            'file_name' => $attachment_name,
            'creator_id' => $business_id,
            'creator_type' => $account_type,
            'created_at' => date("Y-m-d H:i:s"),
        );
        $import_id = $db->insert('xun_import_data', $dataInsert);
        if (empty($import_id)) {
            // close file & remove opened file in temp dir
            fclose($handle); unlink($tmp_handle);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "insert import fail. Error:" . $db->getLastError());
        }

        // insert upload table
        $dataInsert2 = array(
            'file_type' => $attachment_type,
            'file_name' => $attachment_name,
            'data' => $file,
            'type' => "customerSales",
            'reference_id' => $import_id,
            'created_at' => date("Y-m-d H:i:s"),
        );
        $upload_id = $db->insert('uploads', $dataInsert2);
        if (empty($upload_id)) {
            // close file & remove opened file in temp dir
            fclose($handle); unlink($tmp_handle);

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "Insert upload fail. Error:" . $db->getLastError());
        }

        //update upload file id
        $db->where("id", $import_id);
        $db->update("xun_import_data", array("upload_id" => $upload_id));

        // Loop file content
        $recordCount = 0;
        $processedCount = 0;
        $failedCount = 0;

        for ($row = 2; $row <= $lastRow; $row++) {

            $mobile = trim($worksheet->getCell('A' . $row)->getValue());
            $amount = trim($worksheet->getCell('B' . $row)->getValue());

            $errorMessage = "";

            if (empty($mobile) && empty($amount)) {
                continue;
            }
            $recordCount++;

            unset($sales_info);
            $sales_info[] = array(
                "mobile" => $mobile,
                "amount" => $amount,
            );

            unset($params2);
            $params2["business_id"] = $business_id;
            $params2["sales_info"] = $sales_info;

            unset($temp_res);
            $temp_res = $this->add_customer_sales($params2);
            $status = $temp_res["message"];

            if ($temp_res["code"] == 1) {
                $reason = "";
                $processedCount++;
            } else {
                $reason = $temp_res["message_d"];
                $debug_msg = $temp_res["developer_msg"];
                $failedCount++;
            }

            unset($json);
            $json = array(
                'mobile' => $mobile,
                'amount' => $amount,
                'status' => $status,
                'reason' => $reason ?: "",
                'developer_msg' => $debug_msg ?: "",
            );
            $json = json_encode($json);

            unset($dataInsert);
            $dataInsert = array(
                'import_data_id' => $import_id,
                'data' => $json,
                'processed' => "1",
                'status' => $status,
                'error_message' => $reason ?: "",
            );
            $import_details_id = $db->insert('xun_import_data_details', $dataInsert);

            if (empty($import_details_id)) {
                // close file & remove opened file in temp dir
                fclose($handle); unlink($tmp_handle);

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00472') /*Fail to import.*/, "developer_msg" => "Insert import details fail.");
            }

        }

        $dataUpdate = array(
            'total_records' => $recordCount,
            'total_processed' => $processedCount,
            'total_failed' => $failedCount,
            'status' => "completed"
        );
        $db->where('id', $import_id);
        $db->update('xun_import_data', $dataUpdate);

        $handle = fclose($handle);

        // close file & remove opened file in temp dir
        unlink($tmp_handle);

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00252') /*Upload Customer Sales Success.*/);
    }

    public function import_customer_sales_listing($params)
    {
        $db = $this->db;
        $setting = $this->setting;

        $business_id = $params['business_id'];
        $from_datetime = $params['from_datetime'];
        $to_datetime = $params['to_datetime'];
        $type = $params['type'];

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;

        if($page_number < 1) $page_number = 1;

        //Get the limit.
        $start_limit = ($page_number - 1) * $page_size;
        $limit = array($start_limit, $page_size);

        if ($business_id == '') return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);

        if ($from_datetime) {
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where('created_at', $from_datetime, '>=');
        }

        if ($to_datetime) {
            $to_datetime = date("Y-m-d H:i:s", $to_datetime);
            $db->where('created_at', $to_datetime, '<');
        }

        if($type == 'Customer Sales'){
            $db->where('type', 'customerSales');
        }
        elseif($type == 'Reward Point'){
            $db->where('type', 'rewardPoint');
        }
        elseif($type == 'Cash Reward'){
            $db->where('type', 'sendCoinToken');
        }

        $db->where('creator_id', $business_id);
        $db->orderBy('id', 'DESC');
        $copyDb = $db->copy();
        $import_tx = $db->get('xun_import_data USE INDEX (creatorListing)', $limit, 'id, file_name, total_records, total_processed, total_failed, created_at, status, type, reason');

        if (!$import_tx) return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);

        foreach ($import_tx as &$value) {
            if($value["reason"] == "fileNotFound") $value["reason"] = "Content Not Found.";
            elseif($value["reason"] == "insertImportDetailsFailed") $value["reason"] = "Invalid Content.";
            elseif($value["reason"] == "uploadFailed") $value["reason"] = "Upload Failed.";

            if($value["status"] == "scheduled") $value["status"] = "processing";
            if($value['type'] == 'sendCoinToken'){
                $value['type'] = 'Cash Reward';
            }elseif($value['type'] == 'rewardPoint'){
                $value['type'] = 'Reward Point';
            }
            elseif($value['type'] == 'customerSales'){
                $value['type'] = 'Customer Sales';

            }
        }

        $totalRecord = $copyDb->getValue('xun_import_data USE INDEX (creatorListing)', 'count(id)');

        $returnData["data"] = $import_tx;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        $returnData["pageNumber"] = $page_number;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00254') /* Import Listing*/, 'data' => $returnData);
    }

    public function import_customer_sales_details_listing($params)
    {
        $db = $this->db;
        $setting = $this->setting;

        $import_id = $params['import_id'];

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;

        if($page_number < 1) $page_number = 1;

        //Get the limit.
        $start_limit = ($page_number - 1) * $page_size;
        $limit = array($start_limit, $page_size);

        if ($import_id == '') return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00476') /*Import ID cannot be empty*/);

        $db->where('import_data_id', $import_id);
        $db->orderBy('id', 'ASC');
        $copyDb = $db->copy();
        $import_details_tx = $db->get('xun_import_data_details USE INDEX (importDataID)', $limit, 'id, data, status, error_message');

        if (!$import_details_tx) return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);

        foreach ($import_details_tx as &$value) {
            $json = json_decode($value["data"], 1);
            unset($value["data"]);

            $value["mobile"] = $json["mobile"];
            $value["amount"] = $json["amount"];
        }

        $totalRecord = $copyDb->getValue('xun_import_data_details USE INDEX (importDataID)', 'count(id)');

        $returnData["data"] = $import_details_tx;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $page_size;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        $returnData["pageNumber"] = $page_number;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00254') /* Import Listing*/, 'data' => $returnData);
    }

    public function get_customer_listing($params)
    {
        global $xunCrypto;
        $db = $this->db;
        $partnerDB = $this->partnerDB;
        $setting = $this->setting;

        $business_id = $params['business_id'];
        $reward_from_datetime = $params["reward_date_from"];
        $reward_to_datetime = $params["reward_date_to"];
        $redemption_from_datetime = $params['redemption_date_from'];
        $redemption_to_datetime = $params['redemption_date_to'];
        $mobile = $params['mobile'];
        $is_thenux_user = $params['is_thenux_user'];
        $see_all = $params['see_all'];
        $user_id = $params['user_id'];

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;
        $order = $params["order"] ? $params["order"] : "DESC";

        if ($page_number < 1) {
            $page_number = 1;
        }
        //Get the limit.
        $rawLimit = '';
        if(!$see_all){
            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);
            $limit = implode(",", $limit);
            $rawLimit = "LIMIT $limit";
        }
      
        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $result = $db->getOne("xun_business");

        if (!$result) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00032') /*Invalid business id.*/);
        }

        $partnerDbName = $setting->systemSetting['thenuxPartnerDBName'];
        $backendDbName = $setting->systemSetting['thenuxBackendDBName'];

        $db->where("business_id", $business_id);
        $business_coin_info = $db->map('type')->ArrayBuilder()->get("xun_business_coin");
        if (!$business_coin_info) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        }
        $business_coin_id = $business_coin_info['reward']['id'];
        $business_coin_wallet_type = strtolower($business_coin_info['reward']['wallet_type']);
        if($business_coin_info['cash_token']){
            $cash_reward_wallet_type = $business_coin_info['cash_token']['wallet_type'];
        }

        $db->where('user_id', $business_id);
        $db->where('address_type', 'reward');
        $db->where('active', 1);
        $xun_crypto_user_address = $db->getOne('xun_crypto_user_address');

        $crypto_user_address_id = $xun_crypto_user_address['id'];
        $business_cp_address = $xun_crypto_user_address['address'];
        $business_deposit_address = $xun_crypto_user_address['external_address'];

        if ($redemption_from_datetime && $redemption_to_datetime) {

            $db->where("a.address_type", "reward");
            $db->where("a.wallet_type", $business_coin_wallet_type);
            $db->where("a.status", "completed");
            $db->where('a.recipient_user_id', $business_id);
            $db->where('b.reference_address', $business_deposit_address, '!=');
            $db->where("b.type", "send");
            $db->join('xun_user c', 'c.id = a.sender_user_id', 'LEFT');
            $db->join('xun_crypto_callback b', 'b.transaction_hash = a.transaction_hash', 'LEFT');

            $copyDb = $db->copy();
            $redemption_from_time = date("Y-m-d H:i:s", $redemption_from_datetime);
            $db->where('a.created_at', $redemption_from_time, '>=');

            $redemption_to_time = date("Y-m-d H:i:s", $redemption_to_datetime);
            $db->where('a.created_at', $redemption_to_time, '<');
            $db->orderBy('a.id', 'DESC');

            $redeem_info = $db->get("xun_wallet_transaction a", null, "a.id, a.user_id, a.sender_user_id, a.recipient_user_id, a.amount, a.receiver_reference, a.created_at, b.reference_address, c.username");
            //print_r($db);
            foreach ($redeem_info as $key => $value) {
                $sender_user_id = $value['sender_user_id'];

                $sender_user_id_arr[] = $sender_user_id;
            }

            $sender_user_id_arr = array_unique($sender_user_id_arr);
        }

        //if filter by redemption date
        if($sender_user_id_arr){
            $sender_user_id_str = implode(',', $sender_user_id_arr);
            if ($is_thenux_user == 'Yes' && $mobile) {
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered , c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ? and d.id IN ($sender_user_id_str)
                union ( select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ?  and d.id IN ($sender_user_id_str))  $rawLimit", array("%$mobile%", 1, "%$mobile%", "%$mobile%", 1, "%$mobile%"));
    
                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ? and d.id IN ($sender_user_id_str)
                union ( select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ? and d.id IN ($sender_user_id_str))", array("%$mobile%", 1, "%$mobile%", "%$mobile%", 1, "%$mobile%"));
            }
            elseif($is_thenux_user == 'No' && $mobile) {
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ?  and d.id IN ($sender_user_id_str)
                union ( select d.id, a.mobile, a.is_registered ,d.nickname, d.type from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ?  and d.id IN ($sender_user_id_str)) $rawLimit", array("%$mobile%", 0, "%$mobile%", "%$mobile%", 0, "%$mobile%"));
    
                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ? and and d.id IN ($sender_user_id_str)
                union ( select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ? and and d.id IN ($sender_user_id_str))", array("%$mobile%", 0, "%$mobile%", "%$mobile%", 0, "%$mobile%"));
            }
            elseif($is_thenux_user == 'Yes') {
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered , d.nickname, d.type from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?  and d.id IN ($sender_user_id_str)
                union (select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?  and d.id IN ($sender_user_id_str)) $rawLimit", array(1, 1));
    
                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered , c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and and d.id IN ($sender_user_id_str)
                union (select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and and d.id IN ($sender_user_id_str))", array(1, 1));
    
            } elseif ($is_thenux_user == 'No') {
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?  and d.id IN ($sender_user_id_str)
                union ( select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?  and d.id IN ($sender_user_id_str))  limit 0,30", array(0, 0));
    
                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and and d.id IN ($sender_user_id_str)
                union ( select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and and d.id IN ($sender_user_id_str)) ", array(0, 0));
    
            }
            elseif($mobile) {
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered, d.nickname, d.type, d.register_site from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered , c.nickname, c.type, c.register_site from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.register_site = '' and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.mobile LIKE ?  and d.id IN ($sender_user_id_str)
                union ( select d.id, a.mobile, a.is_registered, d.nickname, d.type, d.register_site from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type, c.register_site from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.register_site = '' and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.mobile LIKE ?  and d.id IN ($sender_user_id_str))  $rawLimit", array("%$mobile%", "%$mobile%", "%$mobile%", "%$mobile%"));
    
                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.mobile LIKE ?  and d.id IN ($sender_user_id_str)
                union ( select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.mobile LIKE ? and d.id IN ($sender_user_id_str))", array("%$mobile%", "%$mobile%", "%$mobile%", "%$mobile%"));
    
            }
            else{
                $sender_user_id_str = implode(",", $sender_user_id_arr);
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id  and d.id IN ($sender_user_id_str)
                union ( select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id  and d.id IN ($sender_user_id_str) ) $rawLimit");
                
                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id  and d.id IN ($sender_user_id_str)
                union ( select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id  and d.id IN ($sender_user_id_str) )");
    
            }
        }
        else{

            if ($is_thenux_user == 'Yes' && $mobile) {
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered , c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ?
                union ( select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ?)  $rawLimit", array("%$mobile%", 1, "%$mobile%", "%$mobile%", 1, "%$mobile%"));

                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ?
                union ( select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ?)", array("%$mobile%", 1, "%$mobile%", "%$mobile%", 1, "%$mobile%"));
            }
            elseif($is_thenux_user == 'No' && $mobile) {
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ?
                union ( select d.id, a.mobile, a.is_registered ,d.nickname, d.type from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ?) $rawLimit", array("%$mobile%", 0, "%$mobile%", "%$mobile%", 0, "%$mobile%"));

                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ?
                union ( select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ? and a.mobile LIKE ?)", array("%$mobile%", 0, "%$mobile%", "%$mobile%", 0, "%$mobile%"));
            }
            elseif($is_thenux_user == 'Yes') {
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered , d.nickname, d.type from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?
                union (select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?) $rawLimit", array(1, 1));

                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered , c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?
                union (select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?)", array(1, 1));

            } elseif ($is_thenux_user == 'No') {
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?
                union ( select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?)  limit 0,30", array(0, 0));

                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?
                union ( select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id and a.is_registered = ?) ", array(0, 0));

            }
            elseif($mobile) {
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered, d.nickname, d.type, d.register_site from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered , c.nickname, c.type, c.register_site from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.register_site = '' and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.mobile LIKE ?
                union ( select d.id, a.mobile, a.is_registered, d.nickname, d.type, d.register_site from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type, c.register_site from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.register_site = '' and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.mobile LIKE ?)  $rawLimit", array("%$mobile%", "%$mobile%", "%$mobile%", "%$mobile%"));

                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.mobile LIKE ?
                union ( select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id and c.username LIKE ?) d
                on a.mobile = d.username where business_id = $business_id and a.mobile LIKE ?)", array("%$mobile%", "%$mobile%", "%$mobile%", "%$mobile%"));

            }
            else{
                $sender_user_id_str = implode(",", $sender_user_id_arr);
                $user_result = $db->rawQuery("select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id
                union ( select d.id, a.mobile, a.is_registered, d.nickname, d.type from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered, c.nickname, c.type from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id ) $rawLimit");
                
                $totalRecord = $db->rawQueryValue("select count(a.id), a.mobile, a.is_registered from $partnerDbName.business_user a
                left join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id
                union ( select d.id, a.mobile, a.is_registered from $partnerDbName.business_user a
                right join (select c.id, c.username, 1 as is_registered from $backendDbName.xun_user_coin b
                join $backendDbName.xun_user c on b.user_id = c.id where business_coin_id  = $business_coin_id  and b.user_id != $business_id) d
                on a.mobile = d.username where business_id = $business_id )");

            }
        }

        if (!$user_result) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        }

        $totalRecord = $totalRecord[0];

        foreach ($user_result as $user_key => $user_value) {
            $user_id = $user_value['id'];
            $mobile = $user_value['mobile'];
            if($user_id){
                $user_id_arr[] = $user_id;
            }
           
            if($mobile){
                $mobile_arr[] = $mobile;
            }
        }

        $db->where('business_id', $business_id);
        $sales_tx = $db->get('xun_sales_transaction');

        //each user latest sales transaction
        $db->where('mobile', $mobile_arr, 'IN');
        $db->orderBy('id', 'DESC');
        $user_latest_sales = $db->map('mobile')->ArrayBuilder()->get('xun_sales_transaction', null, '');

        if ($user_id_arr) {
            $db->where('user_id', $user_id_arr, 'IN');
            $db->where('active', 1);
            $db->where('address_type', 'personal');
            $crypto_user_address = $db->map('user_id')->ArrayBuilder()->get('xun_crypto_user_address');

            if($cash_reward_wallet_type){
                $db->where('user_id', $user_id_arr, 'IN');
                $db->where('wallet_type', $cash_reward_wallet_type);
                $db->orderBy('id', 'DESC');
                $user_last_cash_reward_transaction = $db->map('user_id')->ArrayBuilder()->get('xun_wallet_transaction');

                $db->where('sender_user_id', $business_id);
                $db->where('recipient_user_id', $user_id_arr, 'IN');
                $db->orderBy('id', 'DESC');
                $user_last_cash_reward_received = $db->map('recipient_user_id')->ArrayBuilder()->get('xun_wallet_transaction');
            }
          
        }

        $overall_total_sales = '0.0000';
        $overall_reward_point = '0.0000';
        $overall_last_reward = '0.0000';
        $overall_total_redemption = '0.0000';
        $overall_last_redemption = '0.0000';

        foreach ($user_result as $key => $value) {
            $mobile = $value['mobile'];
            $nickname = $value['nickname'];
            $type = $value['type'];
            $is_thenux_user = $value['is_registered'] == 1 ? 'Yes' : 'No';
            $converted_balance = '0.0000';
            $unit_conversion = '0';
            $balance = '0';
            $last_reward_received = '0.0000';
            $last_redemption_amount = '0.0000';
            $last_reward_date = '';
            $last_redemption_date = '';
            $total_sales = "0.0000";
            $total_redemption = "0.0000";
            $cash_reward_converted_balance = '0.0000';
            
            $last_cash_reward_received_date = '';
            $last_cash_reward_received_amount = '0.0000';
            $last_purchased_amount = '0.0000';
            $last_purchased_date = '';

            $last_sales_amount = '0.0000';
            $last_sales_date = '';

            $user_id = $value['id'];

            if ($user_id) {
                $internal_address = $crypto_user_address[$user_id]['address'];
                if ($internal_address) {
                
                    $wallet_info = $xunCrypto->get_wallet_info($internal_address, $business_coin_wallet_type);

                    $wallet_data = $wallet_info[$business_coin_wallet_type];

                    $unit_conversion = $wallet_data['unitConversion'];
                    $balance = $wallet_data['balance'];

                    $converted_balance = bcdiv($balance, $unit_conversion, 4);

                    if($business_coin_info['cash_token']){
                        $cash_reward_wallet_info = $xunCrypto->get_wallet_info($internal_address, $cash_reward_wallet_type);
                        $wallet_data = $cash_reward_wallet_info[$cash_reward_wallet_type];
                        $cash_reward_unit_conversion = $wallet_data['unitConversion'];
                        $cash_reward_balance = $wallet_data['balance'];
    
                        $cash_reward_converted_balance = bcdiv($cash_reward_balance, $cash_reward_unit_conversion, 4);
                    }
                }
            }

            foreach ($sales_tx as $sales_key => $sales_value) {
                if ($mobile == $sales_value['mobile']) {
                    $total_sales = bcadd($total_sales, $sales_value['amount'], '4');
                }
            }

            $reward_tx = null;
            if($user_id){

                if ($reward_from_datetime) {
                    $reward_from_timestamp = '';
                    $reward_from_timestamp = date("Y-m-d H:i:s", $reward_from_datetime);
                    $db->where('created_at', $reward_from_timestamp, '>=');
                }

                if ($reward_to_datetime) {
                    $reward_from_timestamp = '';
                    $reward_to_timestamp = date("Y-m-d H:i:s", $reward_to_datetime);
                    $db->where('created_at', $reward_to_timestamp, '<');
                }

                $db->where('address_type', 'reward');
                $db->where('recipient_user_id', $user_id);
                $db->where('wallet_type', $business_coin_wallet_type);
                $db->where('transaction_type', 'send');
                $db->orderBy('id', 'DESC');
                $reward_tx = $db->getOne('xun_wallet_transaction');
                if ($reward_tx) {

                    $last_reward_received = number_format($reward_tx['amount'], 4, ".", "");
                    $last_reward_date = $reward_tx['created_at'];
                }
                else{
                    $totalRecord = bcsub($totalRecord, 1);
                }

                if($user_last_cash_reward_received[$user_id]){
                    $last_cash_reward_received_amount = $user_last_cash_reward_received[$user_id]['amount'];
                    $last_cash_reward_received_amount = number_format($last_cash_reward_received_amount, 4, ".", "");
                    $last_cash_reward_received_date = $user_last_cash_reward_received[$user_id]['created_at'];
                    
                }
               
                if($user_last_cash_reward_transaction[$user_id]){
                    $last_purchased_amount = $user_last_cash_reward_transaction[$user_id]['amount'];
                    $last_purchased_amount = number_format($last_purchased_amount, 4, ".", "");
                    $last_purchased_date = $user_last_cash_reward_transaction[$user_id]['created_at'];
                }
               
            }

            if (!$redemption_to_datetime && !$redemption_from_datetime) {
                $db->where("a.address_type", "reward");
                $db->where("a.wallet_type", $business_coin_wallet_type);
                $db->where("a.status", "completed");
                $db->where('a.recipient_user_id', $business_id);
                $db->where('b.reference_address', $business_deposit_address, '!=');
                $db->where("b.type", "send");
                $db->join('xun_user c', 'c.id = a.sender_user_id', 'LEFT');
                $db->join('xun_crypto_callback b', 'b.transaction_hash = a.transaction_hash', 'LEFT');
                $db->orderBy('a.id', 'DESC');
                $copyDb = $db->copy();
                $redeem_info = $db->get("xun_wallet_transaction a", null, "a.id, a.user_id, a.sender_user_id, a.recipient_user_id, a.amount, a.receiver_reference, a.created_at, b.reference_address, c.username");
            }
            if ($redemption_from_datetime) {
                $redemption_from_time = '';
                $redemption_from_time = date("Y-m-d H:i:s", $redemption_from_datetime);
                $db->where('a.created_at', $redemption_from_time, '>=');
            }

            if ($redemption_to_datetime) {
                $redemption_to_time = '';
                $redemption_to_time = date("Y-m-d H:i:s", $redemption_to_datetime);
                $db->where('a.created_at', $redemption_to_time, '<');
            }

            $db->where("a.address_type", "reward");
            $db->where("a.wallet_type", $business_coin_wallet_type);
            $db->where("a.status", "completed");
            $db->where('a.recipient_user_id', $business_id);
            $db->where('b.reference_address', $business_deposit_address, '!=');
            $db->where("b.type", "send");
            $db->join('xun_user c', 'c.id = a.sender_user_id', 'LEFT');
            $db->join('xun_crypto_callback b', 'b.transaction_hash = a.transaction_hash', 'LEFT');
            $db->where('a.sender_user_id', $user_id);
            $db->orderBy('a.id', 'desc');
            $last_redemption_data = $db->getOne('xun_wallet_transaction a');

            if ($last_redemption_data) {
                $last_redemption_amount = number_format($last_redemption_data['amount'], 4, ".", "");
                $last_redemption_date = $last_redemption_data['created_at'];
            }

            foreach ($redeem_info as $redeem_key => $redeem_value) {

                $sender_user_id = $redeem_value['sender_user_id'];
                $redemption_amount = $redeem_value['amount'];

                if ($user_id == $sender_user_id) {
                    $total_redemption = bcadd($total_redemption, $redemption_amount, 4);
                }
            }

            if ($reward_from_datetime && $reward_to_datetime && !$reward_tx) {
                continue;
            }

            if ($redemption_from_datetime && $redemption_to_datetime && !$last_redemption_data) {
                continue;
            }

            $reward_cash_data = array(
                "last_reward_cash_received_amount" => $last_cash_reward_received_amount,
                "last_reward_cash_received_date" => $last_cash_reward_received_date,
                "last_reward_cash_purchased_date" => $last_purchased_date,
                "last_reward_cash_purchased_amount" => $last_purchased_amount,
            );

            if($user_latest_sales[$mobile]){
                $last_sales_amount = $user_latest_sales[$mobile]['amount'];
                $last_sales_date = $user_latest_sales[$mobile]['created_at'];
            }
          

            $cust_arr = array(
                "mobile" => $mobile,
                "user_id" => $user_id ? $user_id : '',
                "nickname" => $nickname ? $nickname : '',
                "user_type" => $type ? $type : '',
                "is_thenux_user" => $is_thenux_user,
                "total_sales" => $total_sales,
                "last_sales_date" => $last_sales_date,
                "last_sales_amount" => $last_sales_amount,
                "reward_point_balance" => $converted_balance,
                "cash_reward_balance" => $cash_reward_converted_balance,
                "last_reward_received" => $last_reward_received,
                "last_reward_date" => $last_reward_date,
                "total_redemption" => $total_redemption,
                "last_redemption" => $last_redemption_amount,
                "last_redemption_date" => $last_redemption_date,
                "reward_cash_data" => $reward_cash_data,
            );

            $cust_listing[] = $cust_arr;

            $overall_total_sales = bcadd($overall_total_sales, $total_sales, 4);
            $overall_reward_point = bcadd($overall_reward_point, $converted_balance, 4);
            $overall_last_reward = bcadd($overall_last_reward, $last_reward_received, 4);
            $overall_total_redemption = bcadd($overall_total_redemption, $total_redemption, 4);
            $overall_last_redemption = bcadd($overall_last_redemption, $last_redemption_amount, 4);

        }

        if (!$cust_listing) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        }

        $returnData["data"] = $cust_listing;
        $returnData['overall_total_sales'] = $overall_total_sales;
        $returnData['overall_reward_point'] = $overall_reward_point;
        $returnData['overall_last_reward'] = $overall_last_reward;
        $returnData['overall_total_redemption'] = $overall_total_redemption;
        $returnData['overall_last_redemption'] = $overall_last_redemption;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $see_all ? $totalRecord : $page_size;
        $returnData["totalPage"] = $see_all ? 1 : ceil($totalRecord / $page_size);
        $returnData["pageNumber"] = $page_number;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00250') /* Customer Listing*/, 'data' => $returnData);

    }

    public function get_sales_listing($params)
    {
        $db = $this->db;
        $setting = $this->setting;

        $business_id = $params['business_id'];
        $from_datetime = $params['date_from'];
        $to_datetime = $params['date_to'];
        $mobile = $params['mobile'];
        $see_all = $params['see_all'];

        $page_limit = $setting->systemSetting["memberBlogPageLimit"];
        $page_number = $params["page"];
        $page_size = $params["page_size"] ? $params["page_size"] : $page_limit;

        if ($page_number < 1) {
            $page_number = 1;
        }

        if(!$see_all){
            //Get the limit.
            $start_limit = ($page_number - 1) * $page_size;
            $limit = array($start_limit, $page_size);
        }

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        if ($from_datetime) {
            $from_datetime = date("Y-m-d H:i:s", $from_datetime);
            $db->where('created_at', $from_datetime, '>=');
        }

        if ($to_datetime) {
            $to_datetime = date("Y-m-d H:i:s", $to_datetime);
            $db->where('created_at', $to_datetime, '<');
        }

        if ($mobile) {
            $db->where('mobile', "%$mobile%", 'LIKE');
        }
        $db->where('business_id', $business_id);
        $db->orderBy('id', 'DESC');
        $copyDb = $db->copy();
        $sales_tx = $db->get('xun_sales_transaction', $limit, 'mobile, amount, created_at');

        if (!$sales_tx) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('E00103') /*No Results Found.*/, 'data' => []);
        }

        $totalRecord = $copyDb->getValue('xun_sales_transaction', 'count(id)');

        $total_amount = '0.00';
        foreach ($sales_tx as $key => $value) {
            $amount = $value['amount'];

            $total_amount = bcadd($total_amount, $amount, '2');
        }

        $returnData["data"] = $sales_tx;
        $returnData['total_amount'] = $total_amount;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $see_all ? $totalRecord :$page_size;
        $returnData["totalPage"] = $see_all ? 1 : ceil($totalRecord / $page_size);
        $returnData["pageNumber"] = $page_number;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => $this->get_translation_message('B00251') /* Sales Listing*/, 'data' => $returnData);
    }

}
