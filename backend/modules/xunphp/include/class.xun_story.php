<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/
class XunStory
{

    public function __construct($db, $post, $general, $setting)
    {
        $this->db = $db;
        $this->post = $post;
        $this->general = $general;
        $this->setting = $setting;
    }

    public function request_story_media_upload_link($params, $sourceName){
        global $xunAws,$setting;

        $db = $this->db;
        $username = trim($params["username"]);//for app
        $user_id = trim($params["user_id"]);//for web
        $request_link_array = $params["request_link_params"];
        $business_id = $params["business_id"];
        $type = trim($params["type"]);
        // $file_name = $params["file_name"];
        // $content_type = $params["content_type"];
        // $content_size = $params["content_size"];

        if ($username == '' && $sourceName == 'app') { 
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "app: username cannot be empty");
        }

        if($user_id == '' && $sourceName == 'web'){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "user_id is required.", 'developer_msg' => "web: user_id cannot be empty");
        }

    
        // if($business_id){
        //     $db->where('id', $business_id);
        // }
        // else{
        //     $db->where("username", $username);
        // }
       
        // $db->where("disabled", 0);

        // $xun_user = $db->getOne("xun_user", "id, username, nickname");
        // if(!$xun_user){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        // }

        if($user_id){
            $xun_user = $this->validate_user_id($user_id);
        }
        elseif($username){
            $xun_user = $this->validate_username($username, $business_id);
        }
        
        if(isset($xun_user["code"]) && $xun_user["code"] == 0){
            return $xun_user;
        }

        $user_id = $xun_user["id"];

        $s3_folder = 'story';
        $timestamp = time();
        $expiration = '+20 minutes';

        if($type == "transaction"){
            $value = $request_link_array[0];
            if(empty($value)){
                return array("code" => 1, "message" => "FAILED", "message_d" => "Request params is required.");
            }
            $file_name = $value["file_name"];
            $content_type = $value["content_type"];
            $content_size = $value["content_size"];

            if($file_name == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Filename is required.");
            }
            if($content_type == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Content type is required.");
            }
            if($content_size == ''){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Content size is required.");
            }
            $result = $this->get_donor_transaction_slip_presign_url($file_name, $content_type, $content_size);

            if(isset($result["error"])){
                return array("code" => 1, "status" => "error", "statusMsg" => "Error generating AWS S3 presigned URL.", "errorMsg" => $result["error"]);
            }
            $aws_url[] = $result;
        }else{

            $bucket = $setting->systemSetting["awsS3ImageBucket"];
            $uploadSizeLimit = $setting->systemSetting["storyS3UploadSizeLimit"];
    
            $image_count = 0;
            $video_count = 0;
    
            foreach($request_link_array as $key => $value){
                $file_name = $value["file_name"];
                $content_type = $value["content_type"];
                $content_size = $value["content_size"];
                $presigned_url_key = $s3_folder . '/' . $user_id . '/' . $timestamp . '/' . $file_name;

                if($file_name == ''){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Filename is required.");
                }
                if($content_type == ''){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Content type is required.");
                }
                if($content_size == ''){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Content size is required.");
                }

                //to convert scientific number to normal integer number ex:1e+7 to 10000000
                $converted_content_size = number_format($content_size, 0, '.', '');

                if($converted_content_size > $uploadSizeLimit){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "$file_name file is too large.");
                }

            $type = explode("/", $content_type);
                
            if($type[0] == "image"){
                $image_count++;
            }
            else{
                $video_count++;
            }
    
            if($image_count >5){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insert not more than 5 images.", 'developer_msg' => "Cannot insert more than 5 images");
            }
            
            if($video_count > 3){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insert not more than 3 videos.", 'developer_msg' => "Cannot insert more than 3 videos");
            }
                $newParams = array(
                    "s3_bucket" => $bucket,
                    "s3_file_key" => $presigned_url_key,
                    "content_type" => $content_type,
                    "content_size" => $converted_content_size,
                    "expiration" => $expiration
                );
    
                $result = $xunAws->generate_put_presign_url($newParams);
                if(isset($result["error"])){
                    return array("code" => 1, "message" => "FAILED", "message_d" => "Error generating AWS S3 presigned URL.", "errorMsg" => $result["error"]);
                }
                
                $aws_url[] = $result;
    
            }
        }
        $presign_array["presign_url"] = $aws_url;

        $return_message = "AWS presigned url.";
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $return_message, "data" => $presign_array);

    }

    public function create_story($params, $sourceName, $ip = null, $device = null){
        global $xunUser, $config;
        $db = $this->db;
        
        $username = trim($params["username"]);//for app
        $user_id = trim($params["user_id"]);//for web
        $category_id = $params["category_id"];
        $title = $params["title"];
        $description = $params["description"];
        $fund_amount = $params["fund_amount"];
        $currency_id = $params["currency_id"];
        $fund_period = $params['fund_period'];
        $media = $params["media"];
        $business_id = $params["business_id"];
        $user_bank_ids = $params["user_bank_ids"];
        $user_cryptocurrency_list = $params["user_cryptocurrency_list"];
        $country_id = trim($params['country_id']);

        if ($username == '' && $sourceName == 'app') { 
            //$error_message = $this->get_translation_message('E00027');
            $error_message = "Username is required.";

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }
        elseif($user_id == '' && $sourceName == 'web'){
            $error_message = "User_ID is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        if($title == ''){
            $error_message = "Title is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        if($category_id == ''){
            $error_message = "Category_id is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        if($description == ''){
            $error_message = "Description is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        if($currency_id == ''){
            $error_message = "Currency_id is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        if(!$media){
            $error_message = "Please insert at least 1 image.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        if($fund_period == ''){
            $error_message = "Funding period is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        if($fund_amount == ''){
            $error_message = "Funding amount is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        if($fund_period > 90 || $fund_period < 1){
            $error_message = "Funding period out of range.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $$error_message);
        }

        if($fund_amount > 1000000000 || $fund_amount < 2){
            $error_message = "Funding Amount out of range.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        if($user_bank_ids){
            if(!is_array($user_bank_ids)){
                $error_message = "Bank user IDs must be selected.";
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => "User Bank IDs need to be in array");
            }
    
            // if(count($user_bank_ids) < 1)
            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "User Bank IDs cannot be empty", 'developer_msg' => "User Bank IDs cannot be empty");
        }

        if($user_cryptocurrency_list){
            if(!is_array($user_cryptocurrency_list)){
                $error_message = "Cryptocurrency List must be selected.";
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => "User cryptocurrency list must be an array");
            }
        }

        $user_cryptocurrency_list_count = count($user_cryptocurrency_list);
        $user_bank_ids_count = count($user_bank_ids);
        if($user_bank_ids_count < 1 && $user_cryptocurrency_list_count < 1){
            $error_message = "Please select at least one payment method.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => "empty user_bank_ids and user_cryptocurrency_list");
        }

        if($user_cryptocurrency_list_count > 0){
            $user_cryptocurrency_list = array_map(function($v){
                return strtolower($v);
            }, $user_cryptocurrency_list);
        }

        $date = date("Y-m-d H:i:s");

        // $db->where('id', $fund_period_id);
        // $xun_story_setting = $db->getOne('xun_story_setting');
        // $fund_period = $xun_story_setting["value"];

        // if($fund_period == "0"){
        //     $fund_period = null;
        //     $expire_date = null;
        // }
        // else{
        
        // }
        $expire_date = date("Y-m-d H:i:s", strtotime("$date + $fund_period days"));
        
        // if($business_id){
        //     $db->where("id", $business_id);
        //     $db->where("disabled", 0);
        //     $xun_user = $db->getOne("xun_user", "id, username, nickname");
        // }
        // else{
        //     $db->where("username", $username);
        //     $db->where("disabled", 0);
        //     $xun_user = $db->getOne("xun_user", "id, username, nickname");     
        // }
        
        // if(!$xun_user){
        //    // $this->get_translation_message('E00025')
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        // }
        // $user_id = $xun_user["id"];

        if($user_id){
            $xun_user = $this->validate_user_id($user_id);
        }
        elseif($username){
            $xun_user = $this->validate_username($username, $business_id);
            $user_country_info_arr = $xunUser->get_user_country_info([$username]);
            $user_country_info = $user_country_info_arr[$username];
            $user_country = $user_country_info["name"];
      
        }
        
        if(isset($xun_user["code"]) && $xun_user["code"] == 0){
            $error_message = $xun_user["message_d"] ;
            return $xun_user;
        }

        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];
        $mobile = $xun_user["username"] ? $xun_user["username"] : '';
        $user_type = $xun_user["register_site"] == 'nuxstory' ? 'NuxStory' : 'TheNux';

        if($username){
            $user_setting = $this->get_user_ip_and_country($user_id);

            $ip = $user_setting["lastLoginIP"]["value"];
            $user_country = $user_setting["ipCountry"]["value"];

            $user_device_info = $this->get_user_device_info($username);
            if ($user_device_info) {
                $device_os = $user_device_info["os"];
                
                if($device_os == 1)
                {$device = "Android";}
                else if ($device_os == 2){$device = "iOS";}

            } else {
                $device = "";
            }
        }
       
        $db->where('id', $category_id);
        $db->where('status', 1);
        $xun_story_category = $db->get('xun_story_category');

        if(!$xun_story_category){
            $error_message = "Category not found.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        if ($user_bank_ids){
            $db->where('status', '1');
            $db->where('id', $user_bank_ids, 'IN');
            $db->where('user_id', $user_id);
            $payment_method_info = $db->get('xun_user_story_fiat_payment_method', null, 'payment_method_id, account, account_holder, qr_code');
            if (!$payment_method_info){
                $error_message = "Invalid Bank IDs.";
                $this->create_story_notification($nickname, $mobile, $device, $user_type, $sourceName, "FAILED", $error_message,  $title, $user_country);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Bank IDs.", 'developer_msg' => "Invalid Bank IDs.");
            
            }
            foreach($payment_method_info as $payment_method){
                $payment = array(
                    'payment_method_id' => $payment_method['payment_method_id'],
                    'bank_account' => $payment_method['account'],
                    'bank_holder' => $payment_method['account_holder'],
                    'qr_code' => $payment_method["qr_code"]
                );
                $payment_methods[] = $payment;
            }

        }

        // check cryptocurrency payment method
        $user_selected_crypto_payment_method = [];
        if($user_cryptocurrency_list){
            $user_crypto_payment_method = $this->get_user_crypto_payment_method_list($user_id, "wallet_type", null, 1);

            foreach($user_cryptocurrency_list as $user_cryptocurrency){
                $data = $user_crypto_payment_method[$user_cryptocurrency];
                if(is_null($data)){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "You have not set " . $user_cryptocurrency . " as your payment method.");
                }else{
                    $user_selected_crypto_payment_method[] = $data;
                }
            }
        }

        $insertStory = array(
            "user_id" => $user_id,
            "category_id" => $category_id,
            "fund_amount" => $fund_amount,
            "currency_id" => strtolower($currency_id),
            "fund_period" => $fund_period,
            "country_id" => $country_id,
            "status" => "active",
            "expires_at" => $expire_date,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $story_id = $db->insert('xun_story', $insertStory);

        if(!$story_id){
            $error_message = "Something went wrong. Please try again.";
            $this->create_story_notification($nickname, $mobile, $device, $user_type, $sourceName, "FAILED", $error_message, $title, $user_country);

            return array("code" => 0, "message" => "FAILED", "message_d" => $error_message, "developer_msg" => $db->getLastError());
        }

        if ($payment_methods){
            foreach($payment_methods as $payment_method){
                $insert_data = array(
                    "story_id" => $story_id,
                    "user_id" => $user_id,
                    "payment_method_id" => $payment_method['payment_method_id'],
                    "bank_account" => $payment_method['bank_account'],
                    "bank_holder" => $payment_method['bank_holder'],
                    "qr_code" => $payment_method["qr_code"],
                    "created_at" => date('Y-m-d H:i:s'),
                    "updated_at" => date('Y-m-d H:i:s')
                );
                $story_payment_insert[] = $insert_data;
            }
    
            $story_payment_id = $db->insertMulti('xun_story_payment_method', $story_payment_insert);
            if(!$story_payment_id){
                $error_message = "Something went wrong. Please try again.";
                $this->create_story_notification($nickname, $mobile, $device , $user_type, $sourceName, "FAILED", $error_message,  $title, $user_country);
                return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "developer_msg" => $db->getLastError());    
            }
               
        }

        // create pg address for story
        foreach($user_selected_crypto_payment_method as $data){
            $wallet_type = $data["wallet_type"];
            $external_address = $data["external_address"];
            $ret_result = $this->set_story_payment_gateway_address($story_id, $wallet_type, $external_address);

            if(isset($ret_result["code"]) && $ret_result["code"] == 0){
                $disable_story = array(
                    "disabled" => 1,
                );

                $db->where('id', $story_id);
                $update_story = $db->update('xun_story', $disable_story);

                $error_message = $ret_result["message_d"];
                $this->create_story_notification($nickname, $mobile, $device, $user_type, $sourceName, "FAILED", $error_message,  $title, $user_country);

                return $ret_result;
            }
        }
        
        $insertStoryUpdates = array(
            "title" => $title,
            "story_id" => $story_id,
            "description" => $description,
            "story_type" => "story",
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $story_updates_id = $db->insert('xun_story_updates', $insertStoryUpdates);
        
        if($media){
            foreach($media as $key=>$value){
                $insertMedia = array(
                    "media_url" => $value["media_url"],
                    "media_type" => $value["media_type"],
                    "story_updates_id" => $story_updates_id,
                    "created_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s"),
                );
                $appendMedia[] = $insertMedia;
            }
            $media_id = $db->insertMulti('xun_story_media', $appendMedia);
        }

        $this->insert_story_user_activity($user_id, $story_id, "story");
        
        $nuxStoryUrl = $config['nuxStoryUrl'];
        $url = $nuxStoryUrl."/fundRaisingDetails.php?no=".$story_id;

        $this->create_story_notification($nickname, $mobile, $device, $user_type, $sourceName, "SUCCESS", "", $title, $user_country, $url);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Story created.");
    }

    public function get_create_story_details($params){
        $db = $this->db;
        
        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }

        $xun_user = $db->getOne('xun_user');
        if (!$xun_user)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found.", 'developer_msg' => "User not found");

        $user_id = $xun_user["id"];

        $story_category_list = $this->get_story_category_list(null);

        $newParams['user_id'] = $user_id;
        $user_fiat_list = $this->get_user_fiat_payment_method_list($newParams);

        $user_crypto_list = $this->get_user_crypto_payment_method_list_display($user_id, "a.wallet_type, b.name, b.symbol, b.image");

        $currency_list = $this->get_fiat_currency_list("symbol, currency_id, image, image_md5");

        $returnData["category_list"] = $story_category_list;
        $returnData["bank_account_list"] = empty($user_fiat_list['data']) ? [] : $user_fiat_list['data'];
        $returnData["cryptocurrency_list"] = $user_crypto_list ? $user_crypto_list : [];
        $returnData["currency_list"] = $currency_list ? $currency_list : [];
    
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Create Story Details.", "data" => $returnData);
    }

    private function get_story_category_list($params){
        $db = $this->db;

        $db->where('status', 1);
        $xun_story_category = $db->get('xun_story_category', null, "id, category, created_at, updated_at");
        foreach($xun_story_category as $category){
            $story_category = array(
                "id" => $category['id'],
                "category" => $category['category']
            );
            $story_category_list[] = $story_category;
        }

        return $story_category_list;
    }

    private function get_country_list($params){
        $db = $this->db;

        $country = $db->get('country');
        foreach($country as $x){
            $country_info = array(
                "country_id" => $x['id'],
                "name" => $x['name'],
                "iso_code2" => $x['iso_code2'],
                "currency_id" => $x['currency_code']
            );

            $country_list[] = $country_info;
        }
        return $country_list;
    }

    public function get_my_story_list($params, $sourceName){
        
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $now = time();
        $username = trim($params["username"]);//for app validation
        $business_id = $params["business_id"];
        $user_id = trim($params["user_id"]);//for web validation
        $last_id = $params["last_id"] ? $params["last_id"] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 30;

        $limit = array($last_id, $page_size);

        $order = strtoupper(trim($params["order"]));
        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));
        
        if ($username == '' && $sourceName == 'app') { 
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "app: username cannot be empty");
        }

        if($user_id == '' && $sourceName == 'web'){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "user_id is required.", 'developer_msg' => "web: user_id cannot be empty");
        }

        if($user_id){
            $xun_user = $this->validate_user_id($user_id);
        }
        elseif($username){
            $xun_user = $this->validate_username($username, $business_id);
        }
            
        if(isset($xun_user["code"]) && $xun_user["code"] == 0){
            return $xun_user;
        }
    
        $user_id = $xun_user["id"];
        
        $db->where('user_id', $user_id);
        $db->where('disabled', 0);
        $db->orderBy('created_at', $order);
        $copyDb= $db->copy();
        $xun_story = $db->get('xun_story',$limit, "id, category_id, fund_amount, fund_collected, fund_period, currency_id, status, expires_at, created_at, updated_at");

        $totalRecord = $copyDb->getValue("xun_story", "count(id)");

        $fiat_result = $db->map('fiat_currency_id')->ArrayBuilder()->get('xun_fiat', null, 'id, name, fiat_currency_id');

        $db->where('story_type' , 'story');
        $xun_story_updates = $db->map('story_id')->ArrayBuilder()->get('xun_story_updates');
        
        $story_category = $db->map('id')->ArrayBuilder()->get("xun_story_category");

        $story_list = [];

        foreach($xun_story as $key => $value){
            $story_id = $value["id"];
            $currency_id = strtolower($value["currency_id"]);
            $fund_amount = $value["fund_amount"];
            $fund_collected = $value["fund_collected"];
            $status = $value["status"];
            $created_at = $value["created_at"];
            $category_id = $value["category_id"];
            $category_name = $story_category[$category_id]["category"];

            $currency_name = $fiat_result[$currency_id]["name"];
            
            $story_updates_id = $xun_story_updates[$story_id]["id"];
            $title = $xun_story_updates[$story_id]["title"];
            $description = $xun_story_updates[$story_id]["description"];
     
            $db->where('story_updates_id', $story_updates_id);
            $db->orderBy('id',"ASC");
            $media_result = $db->getOne('xun_story_media', "media_url, media_type");
           
            $expire_date = strtotime($value["expires_at"]);
            $diffSecond = $expire_date - $now;
            $diffDays = floor($diffSecond /86400);
            if($diffDays == 0){
                $hours=floor(($diffSecond-$diffDays*60*60*24)/(60*60));
            }
            else{
                $hours = 0;
            }
           
            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
            $creditType = $decimal_place_setting["credit_type"];
            $fund_amount = $setting->setDecimal($fund_amount, $creditType);
            $fund_collected = $setting->setDecimal($fund_collected, $creditType);
           
            $story_data = array(
                "id" => $story_id,
                "title" => $title,
                "category" => $category_name,
                "fund_amount" => $fund_amount,
                "fund_collected" =>  $fund_collected,
                "currency_name" => strtoupper($currency_name),
                "days_left" => $diffDays,
                "hours_left" => $hours,
                "status" => $status,
                "media" => $media_result,
                "created_at" => $created_at
            );

            $story_list[] = $story_data;
           
        }

        $returnData["story_list"] = $story_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = count($story_list);
        $returnData["totalPage"] = ceil($totalRecord / $page_size);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "My Story List.", "data" => $returnData);
        
    }

    public function get_my_story_details($params){
        global $xunCurrency;

        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        
        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $story_id = $params["story_id"];

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "story_id is required.", 'developer_msg' => "story_id cannot be empty");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }
        $db->where("disabled", 0);
        $xun_user = $db->getOne('xun_user');
        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];
        $user_type = $xun_user["type"];

        $db->where('id', $story_id);
        $db->where('user_id', $user_id);
        $xun_story = $db->getOne('xun_story');

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found!", 'developer_msg' => "Story not found!");
        }

        $db->where('story_id',$story_id);
        $db->orderBy('created_at', "DESC");
        $xun_story_updates = $db->get('xun_story_updates');
        
        $db->where('id', $xun_story["category_id"]);
        $xun_story_category = $db->getOne('xun_story_category','category');

        $user_id = $xun_story["user_id"];
        $total_supporters = $xun_story["total_supporters"];
        $story_currency_id = $xun_story["story_currency_id"];

        foreach($xun_story_updates as $key=>$value){
            $story_updates_id = $value["id"];
            $title = $value["title"];
            $description = $value["description"];
            $story_id = $value["story_id"];

            $db->where('story_updates_id', $story_updates_id);
            $db->orderBy('id', "ASC");
            $media_data = $db->get('xun_story_media', null, "media_url, media_type");

            if($value["story_type"] == "story"){
                $recommended = $xun_story["recommended"];
                $fund_amount = $xun_story["fund_amount"];
                $fund_collected = $xun_story["fund_collected"];
                $currency_id = strtolower($xun_story["currency_id"]);

                $currency_name = $this->get_fiat_currency_name($currency_id);
        
                $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
                $creditType = $decimal_place_setting["credit_type"];
                $fund_amount = $setting->setDecimal($fund_amount, $creditType);
                $fund_collected = $setting->setDecimal($fund_collected, $creditType);
                if($fund_amount){
                    $supportedPercentage = number_format((float)($fund_collected/$fund_amount) *100,2, '.', '');
                }

                $user_verified = 0;
                $story_saved = 0;
                $db->where("user_id", $user_id);
                $db->where("status", "approved");
                $db->orderby("id", "desc");	
                $kyc_record = $db->getOne("xun_kyc");
    
                if($kyc_record){
                    $user_verified = 1;
                }
    
                $db->where("story_id", $story_id);
                $db->where("user_id", $user_id);
                $saved_story = $db->getOne("xun_story_favourite");
    
                if($saved_story){
                    $story_saved = 1;
                }

                $db->where('story_id',$story_id);
                $totalComment = $db->getValue('xun_story_comment','count(id)');

                $story_data = array(
                    "id" => $story_updates_id,
                    "story_id" => $story_id,
                    "title" => $title,
                    "description" => $description,
                    "recommended" => $recommended,
                    "total_comment" =>  $totalComment,
                    "category" => $xun_story_category["category"],
                    "fund_amount" =>  $fund_amount,
                    "fund_collected" => $fund_collected,
                    "fund_collected_pct" => (string)$supportedPercentage,
                    "total_supporters" => $total_supporters,
                    "currency_name" =>  strtoupper($currency_name),
                    "nickname" => $nickname,
                    "user_type" => $user_type,
                    "username" => $username,
                    "user_verified" => $user_verified,
                    "story_saved" => $story_saved,
                    "media" => $media_data,
                    "story_currency" => $story_currency_id,
                    "currency_id" => $currency_id,

                );
                
                $main_story[] = $story_data;
            }
            else{
                $updates_data = array(   
                    "id" => $story_updates_id,
                    "title" => $title,
                    "description" =>$description,
                    "media" => $media_data,
                    "updated_at" => $general->formatDateTimeToIsoFormat($value["created_at"])    
                );
               
                $updates_data_arr[]= $updates_data;
            }
           
            if(!$updates_data_arr){
                $updates["details"] = [];
                
            }
            else{
                $updates["details"] = $updates_data_arr;
            }
        }

        $returnData["story"] = $story_data;
        $returnData["updates"] = $updates;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "My Story Details.", "data" => $returnData);
        
    }

    public function get_story_list($params){
        $db = $this->db;
        $general = $this->general;

        $now = time();
        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $last_id = $params["last_id"] ? $params["last_id"] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 30;
        $type = $params["type"];
        $search_content = trim($params["search_content"]);
        $type_array = ["recommended", "ending_soon", "popular", "newest"];
        
        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($type == "popular"){
            $db->where("id", $last_id);
            $offset = $db->getValue("xun_story", "popular_id");
            $offset = $offset ? $offset : 0;
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }

        $user_result = $db->getOne('xun_user', "id");
        $current_user_id = $user_result["id"];

        $limit = array($last_id, $page_size);
        $currentDate = date("Y-m-d H:i:s");
        $db->where('a.story_type', "story");
        $db->where('b.expires_at', $currentDate, ">");
        $db->where('b.disabled' , 0);

        if($type){
            if($type == "recommended"){
               // $limit = array($last_id, $page_size);
                $db->where('b.recommended' , 1);
                $db->orderBy('b.created_at', "DESC");
            }
            elseif($type == "popular"){
                $limit = $page_size;
                $db->where('b.popular_id', $offset, ">");
                $db->orderBy('b.popular_id', "ASC");
            }
            elseif($type == "newest"){
                //$limit = array($last_id, $page_size);
                $db->orderBy('b.created_at', "DESC");
            }
            elseif($type == "ending_soon"){ 
               // $limit = array($last_id, $page_size);
                $db->orderBy('b.expires_at', "ASC");
            }
        }

        if($search_content){
            //$limit = array($last_id, $page_size);
            $db->where("(a.title LIKE '%$search_content%' or a.description LIKE '%$search_content%')");
            $db->orderBy('b.created_at', "DESC");
        }
             
        $db->join('xun_story_updates a', "a.story_id = b.id", "LEFT");
        $copyDb = $db->copy();
        $result = $db->get('xun_story b',$limit);

        $user_id_array = [];
        $story_updates_array = [];
        $story_id_array = [];
        $story_listing = [];
        $totalRecord = 0;
        if($result){
            foreach($result as $result_key => $result_value){
                if(!in_array($result_value["user_id"], $user_id_array)){
                    array_push($user_id_array, $result_value["user_id"]);
                }

                if(!in_array($result_value["id"],$story_updates_array)){
                    array_push($story_updates_array, $result_value["id"]);
                }

                if(!in_array($result_value["story_id"], $story_id_array)){
                    array_push($story_id_array, $result_value["story_id"]);
                }
            }

            $totalRecord = $copyDb->getValue("xun_story b", "count(b.id)");

            $db->where('a.id', $user_id_array, "IN");
            $db->join("xun_user_details b", "a.id=b.user_id", "LEFT");
            $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user a', null, "a.*, b.picture_url");

            $xun_story_category = $db->map('id')->ArrayBuilder()->get('xun_story_category');

            $db->where('story_updates_id', $story_updates_array, "IN");
            $db->orderBy('id',"ASC");
            $media_result = $db->map('story_updates_id')->ArrayBuilder()->get('xun_story_media');
        
            //  print_r($result);
            foreach($result as $key => $value){
                $user_id = $value["user_id"];
                $story_updates_id = $value["id"];
                $category_id = $value["category_id"];
                $story_id = $value["story_id"];

                $user_data = $xun_user[$user_id];

                $media_url = $media_result[$story_updates_id]["media_url"];
                $media_type = $media_result[$story_updates_id]["media_type"];

                $user_verified = 0;
                $story_saved = 0;

                if($xun_kyc[$user_id]["user_id"]){
                    $user_verified = 1;
                }

                $db->where("story_id", $story_id);
                $db->where("user_id", $current_user_id);
                $saved_story = $db->getOne("xun_story_favourite");

                if($saved_story){
                    $story_saved = 1;
                }

                $expire_date = strtotime($value["expires_at"]);
                if($expire_date){
                    $diffSecond = $expire_date - $now;
                    $diffDays = floor($diffSecond /86400);
                    if($diffDays == 0){
                        $hours=floor(($diffSecond-$diffDays*60*60*24)/(60*60));
                    }
                    else{
                        $hours = 0;
                    }

                }
            
                $fund_amount = $value["fund_amount"];
                $fund_collected = $value["fund_collected"];
                if($fund_amount){
                    $supportedPercentage = number_format((float)($fund_collected/$fund_amount) *100,2, '.', '');
                }

                $user_type = $user_data["type"];
                if($user_type == "business"){
                    $username = $user_id;
                }
                else{
                    $username = $user_data["username"];
                }

                $return_result[$key]["id"] = $story_id;
                $return_result[$key]["total_supporters"] = $value["total_supporters"];
                $return_result[$key]["recommended"] = $value["recommended"];
                $return_result[$key]["title"] = $value["title"];
                $return_result[$key]["description"] = $value["description"];
                $return_result[$key]["days_left"] = $diffDays;
                $return_result[$key]["hours_left"] = $hours;
                $return_result[$key]["media_url"] = $media_url;
                $return_result[$key]["media_type"] = $media_type;
                $return_result[$key]["nickname"] =  $user_data["nickname"];
                $return_result[$key]["user_id"] = $user_id;
                $return_result[$key]["picture_url"] = $user_data["picture_url"];
                $return_result[$key]["user_type"] = $user_type;
                $return_result[$key]["username"] = $username;
                $return_result[$key]["category"] = $xun_story_category[$category_id]["category"]; 
                $return_result[$key]["fund_collected_pct"] = (string)$supportedPercentage;
                $return_result[$key]["user_verified"] = $user_verified;
                $return_result[$key]["story_saved"] = $story_saved;                
                
            }
            $story_listing = $return_result; 
        }

        $returnData["story_list"] = $story_listing;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = count($story_listing);
        // if($totalRecord < $page_size ){
        //     if($last_id && $totalRecord > 0){
        //         $totalRecord = $totalRecord - $last_id;
        //     }
        //     $returnData["numRecord"] = $totalRecord;
        // }
        // else{
        //     $returnData["numRecord"] = (int) $page_size;
        // }
        $returnData["totalPage"] = ceil($totalRecord / $page_size);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Story List.", "data" => $returnData);  
    }

    public function create_story_updates($params, $sourceName, $device = null){
        global $xunXmpp;
        $db = $this->db;

        $username = trim($params["username"]);//for app
        $user_id = trim($params["user_id"]);//for web
        $story_id = $params["story_id"];
        $title = $params["title"];
        $description = $params["description"];
        $media = $params["media"];
        $business_id = $params["business_id"];

        $date = date("Y-m-d H:i:s");

        if ($username == '' && $sourceName == 'app') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username is required.");
        }
        elseif($user_id == ''  && $sourceName == 'web'){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "user_id is required.", 'developer_msg' => "user_id is required.");
        }

        if($title == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Title is required.", 'developer_msg' => "Title is required.");
        }

        if($description == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Description is required.", 'developer_msg' => "Description is required.");
        }
       
        // if(!$media){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Please insert at least 1 image", 'developer_msg' => "Please insert at least 1 image");
        // }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story id is required.", 'developer_msg' => "- Story id is required.");
        }

        // if($business_id){
        //     $db->where("id", $business_id);
        //     $db->where("disabled", 0);
        //     $xun_user = $db->getOne("xun_user", "id, username, nickname");
        // }
        // else{
        //     $db->where("username", $username);
        //     $db->where("disabled", 0);
        //     $xun_user = $db->getOne("xun_user", "id, username, nickname");     
        // }
        
        // if(!$xun_user){
        //     //$this->get_translation_message('E00025')
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' =>  "User does not exist.");
        // }
        // $user_id = $xun_user["id"];

        if($user_id){
            $xun_user = $this->validate_user_id($user_id);
        }
        elseif($username){
            $xun_user = $this->validate_username($username, $business_id);
        }
        
        if(isset($xun_user["code"]) && $xun_user["code"] == 0){
            return $xun_user;
        }

        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];

        if($username){
            $user_device_info = $this->get_user_device_info($username);
            if ($user_device_info) {
                $device_os = $user_device_info["os"];
                
                if($device_os == 1)
                {$device = "Android";}
                else if ($device_os == 2){$device = "iOS";}

            } else {
                $device = "";
            }
        }

        $db->where('id', $story_id);
        $db->where('user_id', $user_id);
        $xun_story = $db->getOne('xun_story');

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found.");
        }

        $db->where('story_type', "story");
        $db->where('story_id', $story_id);
        $xun_story_updates = $db->getOne('xun_story_updates');

        $story_title = $xun_story_updates["title"];

        $insertUpdates = array(
            "story_id" => $story_id,
            "title" => $title,
            "description" => $description,
            "story_type" => "updates",
            "created_at" => $date,
            "updated_at" => $date ,
        );

        $story_updates_id = $db->insert('xun_story_updates', $insertUpdates);
        if($media){
            foreach($media as $key=>$value){
                $insertMedia = array(
                    "media_url" => $value["media_url"],
                    "media_type" => $value["media_type"],
                    "story_updates_id" => $story_updates_id,
                    "created_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s"),
                );
                $appendMedia[] = $insertMedia;
            }
            $media_id = $db->insertMulti('xun_story_media', $appendMedia);
        }

        $this->insert_story_user_activity($user_id, $story_id, "updates");

        $db->where('story_id', $story_id);
        $db->where('user_id', $user_id, "!=");
        $db->groupBy('user_id');
        $xun_story_transaction = $db->get('xun_story_transaction');
        foreach($xun_story_transaction as $key => $value){  
            $obj->story_title = $story_title;
            $to_id = $value["user_id"];
            
            $this->insert_story_notification($story_id, $user_id, $to_id, "updates", $obj);
        }        

        $this->send_create_updates_notification($nickname, $username, $device, "TheNux", $sourceName, "SUCCESS", $title, "");
        
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Updates created.");
    }

    public function get_main_story_details($params){
        
        global $xunCurrency;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $story_id = trim($params["story_id"]);
        $story_token = trim($params["token"]);

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == '' && $story_token == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "story_id is required.", 'developer_msg' => "story_id cannot be empty");
        }

        if($story_token != ''){
            // get story id
            $shared_story_data = $this->get_story_details_from_token($story_token);
            if(!$shared_story_data){
                return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid URL.");
            }

            $story_id = $shared_story_data["story_id"];
        }

        $db->where('id', $story_id);
        $copyDb = $db->copy();
        $xun_story = $db->getOne('xun_story');

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found.", 'developer_msg' => "Story not found.");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }

        $user_result = $db->getOne('xun_user', "id");
        $current_user_id = $user_result["id"];
 
        $user_id = $xun_story["user_id"];
        $currency_id = strtolower($xun_story["currency_id"]);
        $fund_amount = $xun_story["fund_amount"];
        $fund_collected = $xun_story["fund_collected"];
        $total_supporters = $xun_story["total_supporters"];
        $story_currency_id = $xun_story["story_currency_id"];

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
        $creditType = $decimal_place_setting["credit_type"];
        $fund_amount = $setting->setDecimal($fund_amount, $creditType);
        $fund_collected = $setting->setDecimal($fund_collected, $creditType);

        if($fund_amount){
            $supportedPercentage = number_format((float)($fund_collected/$fund_amount) *100,2, '.', '');
        }

        $db->where('story_id',$story_id);
        $db->orderBy('created_at', "DESC");
        $xun_story_updates = $db->get('xun_story_updates', null, "id, story_id, title, description, story_type, created_at, updated_at");

        $story_updates_array = [];
        foreach($xun_story_updates as $updates_key => $updates_value){
            if(!in_array($updates_value["id"], $story_updates_array)){
                array_push($story_updates_array, $updates_value["id"]);
            }
        }
        $db->where('id', $xun_story["category_id"]);
        $xun_story_category = $db->getOne('xun_story_category','category');

        $db->where('a.id', $user_id);
        $db->join("xun_user_details b", "a.id=b.user_id");
        $xun_user = $db->getOne('xun_user a', 'a.nickname, a.username, a.type, b.picture_url');
        $nickname = $xun_user["nickname"];
        $user_type = $xun_user["type"];
        $user_picture_url = $xun_user["picture_url"];

        if($user_type == "business"){
            $username = $user_id;
        }
        else{
            $username = $xun_user["username"];
        }

        foreach($xun_story_updates as $key=>$value){
            $story_updates_id = $value["id"];
            $media_listing = [];

            $db->where('story_updates_id', $story_updates_id);
            $db->orderBy('id', "ASC");
            $media_data = $db->get('xun_story_media', null, "media_url, media_type");

            $currency_name = $this->get_fiat_currency_name($currency_id);

            if($value["story_type"] == "story"){
                $user_verified = 0;
                $story_saved = 0;
                $db->where("user_id", $user_id);
                $db->where("status", "approved");
                $db->orderby("id", "desc");	
                $kyc_record = $db->getOne("xun_kyc");
    
                if($kyc_record){
                    $user_verified = 1;
                }
    
                $db->where("story_id", $story_id);
                $db->where("user_id", $current_user_id);
                $saved_story = $db->getOne("xun_story_favourite");
    
                if($saved_story){
                    $story_saved = 1;
                }

                $db->where('story_id',$story_id);
                $totalComment = $db->getValue('xun_story_comment','count(id)');
                
                $story_data = array(
                    "id" => $xun_story_updates[$key]["id"],
                    "story_id" => $xun_story_updates[$key]["story_id"],
                    "title" => $xun_story_updates[$key]["title"],
                    "description" => $xun_story_updates[$key]["description"],
                    "category" => $xun_story_category["category"],
                    "fund_amount" =>  $fund_amount,
                    "fund_collected" => $fund_collected,
                    "fund_collected_pct" => (string)$supportedPercentage,
                    "total_supporters" => $total_supporters,
                    "currency_name" =>  strtoupper($currency_name),
                    "nickname" => $nickname,
                    "user_type" => $user_type,
                    "username" => $username,
                    "picture_url" => $user_picture_url ? $user_picture_url : '',
                    "user_id" => $user_id,
                    "recommended" => $xun_story["recommended"],
                    "total_comment" =>  $totalComment,
                    "user_verified" => $user_verified,
                    "story_saved" => $story_saved,
                    "media" => $media_data,
                    "story_currency" => $story_currency_id,
                    "currency_id" => $currency_id
                );

                $main_story[] = $story_data;
            }
            else{
                $updates_data = array(   
                    "id" => $xun_story_updates[$key]["id"],
                    "title" => $xun_story_updates[$key]["title"],
                    "description" => $xun_story_updates[$key]["description"],
                    "media" => $media_data,
                    "updated_at" => $general->formatDateTimeToIsoFormat($value["created_at"])
                );

                $updates_data_arr[]= $updates_data;
              
     
            }

            if(!$updates_data_arr){
                $updates["details"] = [];  
            }
            else{
                $updates["details"] = $updates_data_arr;
            }
        }

        $returnData["story"] = $story_data;
        $returnData["updates"] = $updates;
        
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Main Story Details.", "data" => $returnData);
        
    }

    public function get_story_details($params){
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $story_updates_id = $params["id"];

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username cannot be empty", 'developer_msg' => "username cannot be empty");
        }

        if ($story_updates_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "id cannot be empty", 'developer_msg' => "id cannot be empty");
        }

        $db->where('id', $story_updates_id);
        $xun_story_updates = $db->getOne('xun_story_updates');

        if(!$xun_story_updates){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found.", 'developer_msg' => "Story not found.");
        }
        $title =  $xun_story_updates["title"];
        $description = $xun_story_updates["description"];
        $story_id = $xun_story_updates["story_id"];
        $story_type = $xun_story_updates["story_type"];
        $updated_at = $general->formatDateTimeToIsoFormat($xun_story_updates["created_at"]);

        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story');
       
        $user_id = $xun_story["user_id"];

        $db->where('id', $user_id);
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found.", 'developer_msg' => "User not found.");
        }

        $user_type = $xun_user["type"];

        if($user_type == "business"){
            $username = $user_id;
        }
        else{
            $username = $xun_user["username"];
        }
        
        $db->where('story_updates_id', $story_updates_id);
        $db->orderBy('id',"ASC");
        $image_result = $db->get('xun_story_media', null, "media_url, media_type");

        $story_data = array(
            "title" => $title,
            "description" =>  $description,
            "nickname" => $xun_user["nickname"],
            "user_type" => $user_type,
            "story_type" => $story_type,
            "username" => $username,
            "media" => $image_result,
            "updated_at" => $updated_at,
        );
        if($story_type == "story")
        {
            $user_verified = 0;
            $story_saved = 0;
            $db->where("user_id", $user_id);
            $db->where("status", "approved");
            $db->orderby("id", "desc");	
            $kyc_record = $db->getOne("xun_kyc");
    
            if($kyc_record){
                $user_verified = 1;
            }
    
            $db->where("story_id", $story_id);
            $db->where("user_id", $user_id);
            $saved_story = $db->getOne("xun_story_favourite");
    
            if($saved_story){
                $story_saved = 1;
            }

            $story_data["user_verified"] = $user_verified;
            $story_data["story_saved"] = $story_saved;
        }
        
        $returnData["details"] = $story_data;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Story Details.", "data" => $returnData);

    }

    public function save_story($params){
        $db = $this->db;

        $username = trim($params["username"]);
        $story_id = $params["story_id"];
        $business_id = $params["business_id"];

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required.", 'developer_msg' => "story id cannot be empty");
        }
        
        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }
        $xun_user = $db->getOne('xun_user');
        $user_id = $xun_user["id"];

        $now = date("Y-m-d H:i:s");
        $db->where('id', $story_id);
        $db->where("(expires_at > '$now' or expires_at IS NULL)");
        $xun_story = $db->get('xun_story');

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found.", 'developer_msg' => "Story not found.");
        }

        $db->where('user_id', $user_id);
        $db->where('story_id', $story_id);
        $xun_story_favourite = $db->get('xun_story_favourite');

        if($xun_story_favourite){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story saved.", 'developer_msg' => "Story already saved.");
        }
        
        $insertFavourite = array(
            "user_id" => $user_id,
            "story_id" => $story_id,
            "created_at" => date("Y-m-d H:i:s"),
        );

        $story_favourite_id = $db->insert('xun_story_favourite', $insertFavourite);

        if(!$story_favourite_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Failed to save story.", 'developer_msg' => "Save story failed.");
        }
        else{
            $this->insert_story_user_activity($user_id, $story_id, "save_story");
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Story Saved.");
    }

    public function unsave_story($params){
        $db = $this->db;

        $username = trim($params["username"]);
        $story_id = $params["story_id"];
        $business_id = $params["business_id"];

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required.", 'developer_msg' => "story id cannot be empty");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }
        $xun_user = $db->getOne('xun_user');
        $user_id = $xun_user["id"];

        $db->where('user_id', $user_id);
        $db->where('story_id', $story_id);
        $copyDb = $db->copy();
        $xun_story_favourite = $db->get('xun_story_favourite');
        
        if(!$xun_story_favourite){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not saved.", 'developer_msg' => "Story not saved.");
        }

        $copyDb->delete('xun_story_favourite');

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Story Unsaved.");

    }

    public function get_saved_story_list($params){
        $db = $this->db;
        
        $nowSecond = time();
        $now = date("Y-m-d H:i:s");
        
        $username = trim($params["username"]);
        $business_id = $params["business_id"];
        $last_id = $params["last_id"] ? $params["last_id"] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 30;

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }
        $limit = array($last_id, $page_size);

        $xun_user = $db->getOne('xun_user');
        $user_id = $xun_user["id"];

        $db->where('b.user_id', $user_id);
        $db->where('a.status', "active");
        $db->where("(a.expires_at > '$now' or a.expires_at IS NULL)");

        $db->join('xun_story a', "a.id = b.story_id", "LEFT");
        $copyDb = $db->copy();
        $result = $db->get('xun_story_favourite b', $limit, "b.id, a.user_id, b.story_id, b.created_at, a.category_id, a.fund_amount, a.fund_collected, a.total_supporters, a.recommended, a.expires_at ");

        $totalRecord = $copyDb->getValue('xun_story_favourite b', "count(b.id)");

        $story_updates_array = [];
        $user_id_array = [];
        $saved_story_list = [];
        if($result){
            foreach($result as $result_key => $result_value){
                if(!in_array($result_value["story_id"],$story_updates_array)){
                    array_push($story_updates_array, $result_value["story_id"]);
                }     

                if(!in_array($result_value["user_id"],$user_id_array)){
                    array_push($user_id_array, $result_value["user_id"]);
                }
            }

            $db->where('story_id', $story_updates_array, "IN");
            $db->where('story_type', "story");
            $xun_story_updates = $db->map('story_id')->ArrayBuilder()->get('xun_story_updates');

            $xun_story_category = $db->map('id')->ArrayBuilder()->get('xun_story_category');

            $db->where('id', $user_id_array, "IN");
            $all_user = $db->map('id')->ArrayBuilder()->get('xun_user');

            foreach($result as $key=>$value){
                $expire_date = '';
                $diffSecond = '';
                $diffDays = '';
                $hours = '';

                $story_id = $value["story_id"];
                $story_user_id = $value["user_id"];
                $story_updates_id = $xun_story_updates[$story_id]["id"];
                $title = $xun_story_updates[$story_id]["title"];
                $description = $xun_story_updates[$story_id]["description"];
                $category_id = $value["category_id"];
                $category = $xun_story_category[$category_id]["category"]; 
                $recommended = $value["recommended"];
                $total_supporters = $value["total_supporters"];

                $db->where('story_updates_id', $story_updates_id);
                $db->orderBy('id',"ASC");
                $media_result = $db->getOne('xun_story_media', "media_url, media_type");
    
                $expire_date = strtotime($value["expires_at"]);
                
                if($expire_date){
                    $diffSecond = $expire_date - $nowSecond;
                    $diffDays = floor($diffSecond /86400);
                    if($diffDays == 0){
                        $hours=floor(($diffSecond-$diffDays*60*60*24)/(60*60));
                    }
                    else{
                        $hours = 0;
                    }
                    
                }

                $fund_amount = $value["fund_amount"];
                $fund_collected = $value["fund_collected"];
                if($fund_amount){
                    $supportedPercentage = number_format((float)($fund_collected/$fund_amount) *100,2, '.', '');
                }
                
                $nickname = $all_user[$story_user_id]["nickname"];
                $user_type = $all_user[$story_user_id]["type"];
                if($user_type == "business"){
                    $username = $story_user_id;
                }
                else{
                    $username = $all_user[$story_user_id]["username"];
                }

                $user_verified = 0;
                $story_saved = 0;

                $db->where("user_id", $user_id);
                $db->where("status", "approved");
                $db->orderby("id", "desc");	
                $kyc_record = $db->getOne("xun_kyc");

                if($kyc_record){
                    $user_verified = 1;
                }

                $db->where("story_id", $story_id);
                $db->where("user_id", $user_id);
                $saved_story = $db->getOne("xun_story_favourite");

                if($saved_story){
                    $story_saved = 1;
                }
                
                $saved_story_data = array(
                    "id" => $story_id,
                    "title" => $title,
                    "description" => $description,
                    "nickname" => $nickname,
                    "user_type" => $user_type,
                    "username" => $username,
                    "category" => $category,
                    "fund_collected_pct" => (string)$supportedPercentage,
                    "days_left" => $diffDays,
                    "hours_left" => $hours,
                    "recommended" => $recommended,
                    "total_supporters" => $total_supporters,
                    "user_verified" => $user_verified,
                    "story_saved" => $story_saved,
                    "media_url" => $media_result["media_url"],
                    "media_type" => $media_result["media_type"],
                );

                $saved_story_list[] = $saved_story_data;
                
            }      
        }
        $returnData["story_list"] = $saved_story_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = count($saved_story_list);
        $returnData["totalPage"] = ceil($totalRecord / $page_size);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Saved Story List.", "data"=>$returnData);
    }

    public function add_story_comment($params){
        $db = $this->db;
        
        $username = trim($params["username"]);
        $business_id = $params["business_id"];
        $story_id = $params["story_id"];
        $comment = $params["comment"];

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required.", 'developer_msg' => "story_id cannot be empty");
        }

        if($comment == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Comment is required.", 'developer_msg' => "comment cannot be empty");
        }

        if(strlen($comment) > 8000){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Exceeded the limitation of comment.", 'developer_msg' => "Comment cannot be more than 8000 characters");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found.", 'developer_msg' => "User not found.");
        }

        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];

        $user_device_info = $this->get_user_device_info($username);
        if ($user_device_info) {
            $device_os = $user_device_info["os"];
            
            if($device_os == 1)
            {$device = "Android";}
            else if ($device_os == 2){$device = "iOS";}

        } else {
            $device = "";
        }

        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story');

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found.", 'developer_msg' => "Story not found.");
        }

        $story_user_id = $xun_story["user_id"];

        $db->where('id', $story_user_id);
        $story_user = $db->getOne('xun_user', "username, type");
        if($story_user["type"] == "user"){
            $story_username = $story_user["username"];
        }
        else{
            $story_username = $story_user_id;
        }

        $db->where('story_id', $story_id);
        $db->orderBy('created_at', "DESC");
        $xun_story_comment = $db->getOne('xun_story_comment');

        $insertComment = array(
            "user_id" => $user_id,
            "story_id" => $story_id,
            "comment" => $comment,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $story_comment_id = $db->insert('xun_story_comment', $insertComment);

        if(!$story_comment_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Insert Comment Failed", 'developer_msg' => "Insert Comment Failed.");
        }

        $last_comment_user_id = $xun_story_comment["user_id"];

        $db->where('story_id', $story_id);
        $db->where('story_type', "story");
        $story_updates = $db->getOne('xun_story_updates');
        $story_title = $story_updates["title"];
        $info->story_title =  $story_title ;
        $info->story_username = $story_username;

        $this->insert_story_user_activity($user_id, $story_comment_id, "comment", $info);

        if( $user_id != $last_comment_user_id ){
            $this->insert_story_notification($story_id, $user_id, $last_comment_user_id, "comment", $info);
        }

       $return =  $this->send_comment_notification($nickname, $username, $device, "TheNux", "App", $story_title, $comment);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Insert Comment Successful", 'return' => $return);

    }

    public function edit_story_comment($params){
        $db = $this->db;
        
        $username = trim($params["username"]);
        $comment_id = $params["comment_id"];
        $comment = $params["comment"];

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($comment_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Comment ID is required.", 'developer_msg' => "comment id cannot be empty");
        }

        if($comment == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Comment is required.", 'developer_msg' => "comment cannot be empty");
        }

        $db->where('id', $comment_id);
        $copyDb = $db->copy();
        $result = $db->getOne("xun_story_comment");

        if(!$result){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Comment not found.", 'developer_msg' => "Comment not found.");
        }
        $updateComment = array(
            "comment" => $comment,
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $copyDb->update("xun_story_comment", $updateComment);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Edit Comment Successful");

    }

    public function delete_story_comment($params){
        $db = $this->db;

        $username = trim($params["username"]);
        $comment_id = $params["comment_id"];


        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($comment_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Comment ID is required.", 'developer_msg' => "comment id cannot be empty");
        }

        $db->where('id', $comment_id);
        $copyDb = $db->copy();
        $result = $db->getOne("xun_story_comment");

        if(!$result){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Comment not found.", 'developer_msg' => "Comment not found.");
        }

        $delete_comment = $copyDb->delete('xun_story_comment');
        
        if(!$delete_comment){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Delete comment failed.", 'developer_msg' => "Delete comment failed.");
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Comment Deleted");

    }

    public function get_story_comment_list($params){
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $story_id = $params["story_id"];
        $last_id = $params["last_id"] ? $params["last_id"] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 30;

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required.", 'developer_msg' => "story id cannot be empty");
        }

        $limit = array($last_id, $page_size);
    
        $db->where('story_id', $story_id);
        $db->orderBy("id", "DESC");
        $copyDb = $db->copy();
        $xun_story_comment = $db->get('xun_story_comment',$limit);

        $totalRecord = $copyDb->getValue("xun_story_comment", "count(id)");
        $user_id_arr = [];
        $comment_list = [];
        foreach($xun_story_comment as $comment_key => $comment_value){
            $test[$key] = $comment_value["id"];
            $comment_user_id = $comment_value["user_id"];
            if(!in_array($comment_user_id, $user_id_arr)){
                array_push($user_id_arr, $comment_user_id);
            }
        }
        $columns = array_column($xun_story_comment, 'id');

        array_multisort($columns, SORT_ASC, $xun_story_comment);

        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story');

        if(!empty($user_id_arr)){

            $db->where('a.id', $user_id_arr, "IN");
            $db->join("xun_user_details b", "a.id=b.user_id","LEFT");
            $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user a', null, "a.*, b.picture_url");

            $db->where("user_id", $user_id_arr, "IN");
            $db->where("status", "approved");
            $kyc_arr = $db->map("user_id")->ArrayBuilder()->get("xun_kyc", null, "id, user_id, status");

            $db->where("story_id", $story_id);
            $db->groupBy('user_id');
            $transaction_arr = $db->map('user_id')->ArrayBuilder()->get('xun_story_transaction', null, "user_id");
        }

        foreach($xun_story_comment as $key=>$value){
            $comment_user_type = "normal";
            $user_id = $value["user_id"];
            $id = $value["id"];
            $comment = $value["comment"];
            $created_at = $general->formatDateTimeToIsoFormat($value["created_at"]);

            $nickname = $xun_user[$user_id]["nickname"];
            $user_type = $xun_user[$user_id]["type"];
            $username = $xun_user[$user_id]["username"];
            $user_picture_url = $xun_user[$user_id]["picture_url"];
            $kyc_is_verified = $kyc_arr[$user_id] ? 1 : 0;
            
            if($user_type == "business"){
                $username = $user_id;
            }

            if($transaction_arr[$user_id]){
                $comment_user_type = "supporter";
            }

            if($xun_story["user_id"] == $user_id){
                $comment_user_type = "creator";
            }

            $comment_data = array(
                "id" => $id,
                "comment"=>  $comment,
                "nickname" => $nickname,
                "user_type" => $user_type,
                "username" =>$username,
                "picture_url" => $user_picture_url ? $user_picture_url : '',
                "user_verified" => $kyc_is_verified,
                "comment_user_type" => $comment_user_type,
                "created_at" => $created_at,
            );

            $comment_list []= $comment_data;

        }

        $returnData["story_comment_list"] = $comment_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = count($comment_list);
        $returnData["totalPage"] = ceil($totalRecord / $page_size);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Story Comment List.", "data" => $returnData);
    }

    public function insert_story_user_activity($user_id, $reference_id, $activity_type, $info = ''){
        $db = $this->db;

        $insertActivity = array(
            "user_id" => $user_id,
            "reference_id" => $reference_id,
            "activity_type"  => $activity_type,
            "info" => json_encode($info),
            "created_at" => date("Y-m-d H:i:s"),
        );

        $activity_id = $db->insert('xun_story_user_activity', $insertActivity);

        return $activity_id;
    }

    public function insert_story_notification($story_id, $from_id, $to_id, $notification_type, $info = ''){
        global $xunXmpp, $xunBusiness, $config;
        $db = $this->db;

        $insertNotification = array(
            "story_id" => $story_id,
            "from_id" => $from_id,
            "to_id" => $to_id,
            "info" => json_encode($info),
            "notification_type" => $notification_type,
            "created_at" => date("Y-m-d H:i:s"),
        );

        $db->insert('xun_story_notification', $insertNotification);

        $db->where('id', array($from_id, $to_id), "IN");
        $user_result = $db->map('id')->ArrayBuilder()->get('xun_user',null, "id, username, nickname, type");

        $db->where('story_id', $story_id);
        $db->where('story_type', "story");
        $story_updates = $db->getOne('xun_story_updates');
        $title = $story_updates["title"];

        $user_id = $from_id;
        $nickname = $user_result[$from_id]["nickname"];
        $username = $user_result[$from_id]["username"];
        
        $from_user = $user_result[$from_id];
        $to_user = $user_result[$to_id];

        $user_type = $from_user["type"];
        if($user_type == "business"){
            $username = $from_id;
        }
        else{
            $username = $from_user["username"];
        }

        $story_title = $info->story_title;
        // $story_username = '';
        // $value = '';
        // $currency_name = '';

        $db->where('id', $story_id);
        $story_result = $db->getOne('xun_story');
        $story_user_id = $story_result["user_id"];

        switch($notification_type){ 
            case "comment":
                $story_username = $info->story_username;
                if($story_user_id == $to_id)
                {
                    $message = "<b>%username% commented</b> on your story $story_title.";
                }
                else{
                    $message = "<b>%username% also commented</b> on story $story_title.";
                }

                break;

            case "updates":
                // $info = json_decode($info);
                $message = "<b>$story_title</b> added a new update.";

                break;

            case "story_completed":
                if($story_user_id == $to_id){
                    $message = "<b>Your story $story_title</b> has received 100% support.";
                    $notification_type = "withdrawal";
                }
                else{
                    $message = "<b>$story_title</b> has received 100% support they need.";
                    $notification_type = "updates";
                }

                break;

            case "story_expired":
                $message = "<b>$story_title</b> story has expired!";
                
                $notification_type = "updates";

                break;

            case "donation":
                $value = $info->value;
                $currency_name = $info->name;

                $user_id = $from_id;
                $nickname = $user_result[$from_id]["nickname"];
                $username = $user_result[$from_id]["username"];
                $user_type = $user_result[$from_id]["type"];

                $message = "<b>%username% supported %value%%currency_name%</b> to $story_title.";

                break;

            case "withdrawal_processing":
                $value = $info->value;
                $currency_name = $info->name;

                $withdrawal_info = "$value$name";
                $message = "<b>Your withdrawal %value%%currency_name%</b> from $story_title is processing.";
                $notification_type = "withdrawal";

                break;

            case "withdrawal_transferring":
                $value = $info->value;
                $currency_name = $info->name;

                $message = "<b>Your withdrawal %value%%currency_name% from $story_title is transferring.";
                $notification_type = "withdrawal";

                break;

            case "withdrawal_completed":
                $value = $info->value;
                $currency_name = $info->name;
                
                $notification_type = "withdrawal";

                if($story_user_id == $to_id){
                    $message = "<b>Your withdrawal %value%%currency_name%</b> from $story_title is completed.";
                }
                else{
                    $user_id = $from_id;
                    $nickname = $user_result[$from_id]["nickname"];
                    $username = $user_result[$from_id]["username"];
                    $user_type = $user_result[$from_id]["type"];
                    $message = "<b>%username% withdraw %value%%currency_name%</b> from $story_title.";
                }

                break;
        }
        
        $sender_jid = $xunXmpp->get_user_jid($from_id);
        $erlang_server = $config["erlang_server"];

        if($user_result[$to_id]["type"] == 'user'){
            $user_info = [];
            $user_info["recipient_username"] = $user_result[$to_id]["username"];
            $user_info["recipient_jid"] =$user_result[$to_id]["username"] ."@".$erlang_server;
            $recipient_list[] = $user_info;
        }
        else{
            $db->where('business_id', $to_id);
            $db->where('employment_status', "confirmed");
            $xun_employee = $db->get('xun_employee');

            if ($xun_employee) {
                foreach($xun_employee as $emp_key => $emp_value){
                    $mobile = $emp_value["mobile"];
                    $livechat_jid = $emp_value["old_id"];
                    
                    $employee_info = [];
                    $employee_info["recipient_username"] = $mobile;
                    $employee_info["recipient_jid"] = $livechat_jid."@livechat.".$erlang_server;
                    $recipient_list[] = $employee_info;
                }
                
            }
        }
       
        $insertParams = array(
            "story_id" => $story_id,
            "user_id" => $user_id,
            "message" => $message,
            "username" => $username,
            "user_type" => $user_type,
            "nickname" => $nickname,
            "story_username" => $story_username,
            "value" => $value,
            "currency_name" => $currency_name,
            "notification_type" => $notification_type, 
            "recipients" => $recipient_list,
            
        );

        $insertSendingQueue = array(
            "data" => json_encode($insertParams),
            "message_type" => "story",
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $row_id = $db->insert('xun_business_sending_queue', $insertSendingQueue);

        // if(!$row_id){
            // print_r($db);
        // }

    }

    public function get_story_notification_list($params){
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $business_id = $params["business_id"];
        $last_id = $params["last_id"] ? $params["last_id"] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 30;

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found.", 'developer_msg' => "User not found.");
        }

        $user_id = $xun_user["id"];
        $limit = array($last_id, $page_size);

        $db->where('to_id', $user_id);
        $db->orderBy('created_at', "DESC");
        $copyDb = $db->copy();
        $xun_story_notification = $db->get('xun_story_notification',$limit);

        $totalRecord = $copyDb->getValue('xun_story_notification', "count(id)");
        $notification_list = [];
        $from_id_arr = [];
        $story_id_arr = [];
        if($xun_story_notification){
            foreach($xun_story_notification as $notification_key => $notification_value){
                $from_id = $notification_value["from_id"];
                $story_id = $notification_value["story_id"];
                if(!in_array($from_id, $from_id_arr)){
                    array_push($from_id_arr, $from_id);
                }

                if(!in_array($story_id, $story_id_arr)){
                    array_push($story_id_arr, $story_id);
                }

            }

            $db->where('a.id', $from_id_arr, "IN");
            $db->join("xun_user_details b", "a.id=b.user_id", "LEFT");
            $user_result = $db->map('id')->ArrayBuilder()->get('xun_user a', null, 'a.*, b.picture_url');

            $db->where('id', $story_id_arr, "IN");
            $xun_story = $db->map('id')->ArrayBuilder()->get('xun_story');

            $db->where('story_type', "story");
            $db->where('story_id', $story_id_arr, "IN");
            $xun_story_updates = $db->map('story_id')->ArrayBuilder()->get('xun_story_updates');

            foreach($xun_story_notification as $key => $value){
                $notification_type = $value["notification_type"];
                $story_id = $value["story_id"];
                $from_id = $value["from_id"];
                $to_id = $value["to_id"];
                $story_user_id = $xun_story[$story_id]["user_id"];
                $title = $xun_story_updates[$story_id]["title"];
                $created_at = $general->formatDateTimeToIsoFormat($value["created_at"]);

                $info = $value["info"];
                $info = json_decode($info);
                $user_id = "";
                $nickname = "";
                $username = "";
                $picture_url = "";
                $user_type = "";
                $value = "";
                $currency_name = "";
                $story_title = $info->story_title;
                
                switch($notification_type){ 
                    case "comment":
                        if($from_id != 0){
                            $user_id = $from_id;
                            $nickname = $user_result[$from_id]["nickname"];
                            $user_type = $user_result[$from_id]["type"];
                            $picture_url = $user_result[$from_id]["picture_url"];
                            
                            if($user_type == "business"){
                                $username = (string) $from_id;
                            }
                            else{
                                $username = $user_result[$from_id]["username"];
                            }
                        }
                        else{
                            $nickname = "Anonymous";
                        }
                        

                        if($story_user_id == $to_id)
                        {
                            $message = "<b>%username% commented</b> on your story $title.";
                        }
                        else{
                            $message = "<b>%username% also commented</b> on story $title.";
                        }

                        break;

                    case "updates":
                        
                        $message = "<b>$story_title</b> added a new update.";

                        break;
                    
                    case "story_completed":
                        if($story_user_id == $to_id){
                            $message = "<b>Your story $story_title</b> has received 100% support.";
                        }
                        else{
                            $message = "<b>$story_title</b> has received 100% support they need.";
                        }

                        break;

                    case "story_expired":
                        $message = "<b>$story_title</b> has expired!";

                        break;

                    case "donation":
                        $value = $info->value;
                        $currency_name = $info->name;

                        $user_id = $from_id;
                        $nickname = $user_result[$from_id]["nickname"];
                        $username = $user_result[$from_id]["username"];
                        $user_type = $user_result[$from_id]["type"];
                        $picture_url = $user_result[$from_id]["picture_url"];

                        $message = "<b>%username% supported %value%%currency_name%</b> to your story $story_title.";

                        break;

                    case "withdrawal_processing":
                        $value = $info->value;
                        $currency_name = $info->name;

                        $message = "<b>Your withdrawal %value%%currency_name%</b> from $story_title is processing.";

                        break;

                    case "withdrawal_transferring":
                        $value = $info->value;
                        $currency_name = $info->name;

                        $message = "<b>Your withdrawal %value%%currency_name%</b> from $story_title is transferring.";

                        break;

                    case "withdrawal_completed":
                        $value = $info->value;
                        $currency_name = $info->name;

                        if($story_user_id == $to_id){
                            $message = "<b>Your withdrawal %value%%currency_name%</b> from $story_title is completed.";
                        }
                        else{
                            $user_id = $from_id;
                            $nickname = $user_result[$from_id]["nickname"];
                            $username = $user_result[$from_id]["username"];
                            $user_type = $user_result[$from_id]["type"];
                            $picture_url = $user_result[$from_id]["picture_url"];

                            $message = "<b>%username% withdraw %value%%currency_name%</b> from $story_title.";
                        }
                        
                        break;
                        
                }

                $notification_data = array(
                    "notification_message" => $message,
                    "user_id" => $user_id,
                    "username" => $username,
                    "nickname" => $nickname,    
                    "user_type" => $user_type,
                    "picture_url" => $picture_url ? $picture_url : '',
                    "value" => $value,
                    "currency_name" => $currency_name,
                    "created_at" => $created_at,
                );
        
                $notification_list[] = $notification_data;
            }
        }

        $returnData["notification_list"] = $notification_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = count($notification_list);
        $returnData["totalPage"] = ceil($totalRecord / $page_size);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Story Notification List.", "data"=>$returnData);

    }

    public function get_story_my_activity_list($params){
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $business_id = $params["business_id"];
        $last_id = $params["last_id"] ? $params["last_id"] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 30;

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found.", 'developer_msg' => "User not found.");
        }

        $user_id = $xun_user["id"];
        $limit = array($last_id, $page_size);

        $db->where('user_id', $user_id);
        $db->orderBy('created_at', "DESC");
        $copyDb = $db->copy();
        $xun_story_user_activity = $db->get('xun_story_user_activity',$limit);

        $totalRecord = $copyDb->getValue('xun_story_user_activity', "count(id)");

        $db->where('story_type', "story");
        $xun_story_updates = $db->map('story_id')->ArrayBuilder()->get('xun_story_updates');

        $db->where('user_id', $user_id);
        $story_transaction = $db->map('id')->ArrayBuilder()->get('xun_story_transaction');

        $db->where('user_id',$user_id);
        $story_favourite = $db->map('id')->ArrayBuilder()->get('xun_story_favourite');

        $db->where('user_id', $user_id);
        $story_comment = $db->map('id')->ArrayBuilder()->get('xun_story_comment');

        $activity_list = [];
        foreach($xun_story_user_activity as $key=>$value){
            $activity_type = $value["activity_type"];
            $reference_id = $value["reference_id"];
            $created_at = $general->formatDateTimeToIsoFormat($value["created_at"]);
            $info = $value["info"];
            $info = json_decode($info);
            $value = '';
            $currency_name = '';
            $story_title = '';

            switch($activity_type){

                case "story":
                    $title = $xun_story_updates[$reference_id]["title"];
                    $message = "Your story <b>$title</b> was published. People can see your story now and start helping you!";

                    break;

                case "updates":
                    $title = $xun_story_updates[$reference_id]["title"];    
                    $message = "You posted a new update on <b>$title</b>.";

                    break;

                case "save_story":
                    //$story_id = $story_favourite[$reference_id]["story_id"];
                    $title = $xun_story_updates[$reference_id]["title"];
                    $message = "You liked <b>$title</b> story.";

                    break;

                case "comment":
                    $story_id = $story_comment[$reference_id]["story_id"];
                    $story_title = $info->story_title ? $info->story_title: $xun_story_updates[$story_id]["title"];
                    $message = "You leave a comment on <b>$story_title</b>.";

                    break;

                case "donation":
                    $story_id = $story_transaction[$reference_id]["story_id"];
                    $title = $xun_story_updates[$story_id]["title"];
                    $value = $info->value;
                    $currency_name = $info->name;

                    $message = "You supported %value%%currency_name% to <b>$title</b>.";

                    break;

                case "withdraw":
                    $story_id = $story_transaction[$reference_id]["story_id"];
                    $title = $xun_story_updates[$story_id]["title"];
                    
                    $value = $info->value;
                    $currency_name = $info->name;

                    $message = "You withdraw %value%%currency_name% from <b>$title</b>.";

                    break;
                
                case "share_story":
                    $story_title = $info->story_title;
                    $message = "You shared <b>$story_title</b>.";
                    
                    break;
                
                case "story_expired":
                    $story_title = $info->story_title;
                    $message = "Your story <b>$story_title</b> has expired!";
                    
                    break;

                default :
                    $message = '';
                    
                    break;
            }
            $activity_data = array(
                "activity_message" => $message,
                "value" => $value,
                "currency_name" =>  $currency_name,
                "created_at" => $created_at,
            );

            $activity_list[] = $activity_data;
           
        }

        $returnData["my_activity_list"] = $activity_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = count($activity_list);
        $returnData["totalPage"] = ceil($totalRecord / $page_size);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Story My Activity List.", "data"=>$returnData);
    }

    public function get_donation_signing_details($params){
        global $xunCoins, $xunCurrency, $xunCrypto;
        $db = $this->db;
        $setting = $this->setting;
        
        $amount = trim($params["amount"]);
        $wallet_type = trim($params["wallet_type"]);
        $story_id = trim($params["story_id"]);
        $description = trim($params["description"]);

        if ($amount == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Amount cannot be empty.");
        }

        if($amount <= 0){
            return array("code" => 0, "message" => "SUCCESS", "message_d" => "Please enter a valid amount.");
        }

        if ($wallet_type == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Wallet type is required.");
        }

        if ($story_id == '') {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Story ID is required.");
        }

        // check story id
        $db->where("id", $story_id);
        $xun_story = $db->getOne("xun_story");

        if(!$xun_story){
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid story", "errorCode" => -102);
        }

        $date = date("Y-m-d H:i:s");

        $fund_amount = $xun_story["fund_amount"];
        $fund_collected = $xun_story["fund_collected"];
        $story_status = $xun_story["status"];
        $story_expires_at = $xun_story["expires_at"];
        $story_currency = $xun_story["currency_id"];

        if($story_status != "active"){
            return array("code" => 0, "message" => "FAILED", "message_d" => "This story is no longer accepting donations.", "status" => $story_status, "errorCode" => -103);
        }

        if($fund_collected >= $fund_amount){
            return array("code" => 0, "message" => "FAILED", "message_d" => "This story is no longer accepting donations.", "status" => $story_status, "errorCode" => -103);
        }

        if($story_expires_at < $date){
            return array("code" => 0, "message" => "FAILED", "message_d" => "This story has ended.", "status" => $story_status, "errorCode" => -104);
        }

        $wallet_type = strtolower($wallet_type);

        $xun_coins_arr = $xunCoins->getCoinSetting("is_story");

        $xun_coin = $xun_coins_arr[$wallet_type];

        //  check from xun_coins
        if (is_null($xun_coin)) {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Donation is not available for this coin.", "errorCode" => -111);
        }

        $donation_value = $xunCurrency->get_conversion_amount($story_currency, $wallet_type, $amount);

        $new_fund_total = $donation_value + $fund_collected;
        // if($new_fund_total > $fund_amount){
        //     return array("code" => 0, "message" => "FAILED", "message_d" => "You cannot donate more than the required fund needed.");
        // }

        $is_custom_coin = $xun_coin["is_custom_coin"];

        $xun_user_service = new XunUserService($db);

        // $recipient_address = $setting->systemSetting["storyWalletAddress"];

        //  get story pg address
        try{
            $pg_external_address = $this->get_story_payment_gateway_address($story_id, $wallet_type);

            $validate_address_result = $xunCrypto->validate_payment_gateway_address($pg_external_address, $wallet_type);

            $pg_internal_address = $validate_address_result["address"];

        }catch(Exception $e){
            $error_message = $e->getMessage();

            return array("code" => 0, "message" => "FAILED", "message_d" => $error_message);
        }
        
        $return_data = [];
        $return_data["amount"] = $amount;
        $return_data["wallet_type"] = $wallet_type;
        $return_data["story_id"] = $story_id;
        $return_data["description"] = $description;
        $return_data["recipient_address"] = $pg_internal_address;

        return array("code" => 1, "data" => $return_data);
    }

    public function insert_donation_transaction($user_id, $signing_details, $wallet_transaction_arr, $currency_rate){
        $db = $this->db;

        $description = $signing_details["description"];
        $story_id = $signing_details["story_id"];

        $status = "pending";
        $date = date("Y-m-d H:i:s");

        for ($i = 0; $i < count($wallet_transaction_arr); $i++) {
            $data = $wallet_transaction_arr[$i];

            if ($data["transaction_type"] == "story") {
                $wallet_transaction_id = $data["id"];
                $amount = $data["amount"];
                $wallet_type = $data["wallet_type"];
                $usd_value = $data["usd_amount"];
                
                $db->where('id', $story_id);
                $xun_story = $db->getOne('xun_story', "currency_id");
                $currency_id = $xun_story["currency_id"];

                $insert_data = array(
                    "user_id" => $user_id,
                    "wallet_transaction_id" => $wallet_transaction_id,
                    "story_id" => $story_id,
                    "amount" => $amount,
                    "wallet_type" => $wallet_type,
                    "value" => $usd_value,
                    "currency_id" => $currency_id,
                    "currency_rate" => $currency_rate,
                    "transaction_type" => "donation",
                    "description" => $description,
                    "status" => $status,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $row_id = $db->insert("xun_story_transaction", $insert_data);
                if (!$row_id) {
                    throw new Exception($db->getLastError());
                }
            }
        }
    }

    public function update_donation_callback_from_pg($transaction_callback, $sender_user)
    {
        global $log;
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;
        

        /**
         * check if pg transaction id has beed added
         * if already exists, return
         * else add to table
         */

        $crypto_history_id = $transaction_callback["crypto_history_id"];
        $address = $transaction_callback["address"];
        $story_id = $transaction_callback["story_id"];
        $amount = $transaction_callback["amount"];
        $wallet_type = $transaction_callback["wallet_type"];
        $status = $transaction_callback["status"];
        $from_address = $transaction_callback["sender"]["internal"] ? $transaction_callback["sender"]["internal"] : $transaction_callback["sender"]["external"];
        $to_address = $transaction_callback["recipient"]["internal"] ? $transaction_callback["recipient"]["internal"] : $transaction_callback["recipient"]["external"];
        $user_type = $transaction_callback["sender"]["internal"] ? "TheNux"  : 'Non-member';

        $db->where("crypto_history_id", $crypto_history_id);
        $xun_story_transaction = $db->getOne("xun_story_transaction");

        if($xun_story_transaction){
            return;
        }

        $donator_user_id = $sender_user ? $sender_user["id"] : '';

        $db->where("id", $story_id);
        $xun_story = $db->getOne("xun_story");
        
        $currency_id = $xun_story["currency_id"];

        $currency_rate = $xunCurrency->get_rate($wallet_type, $currency_id);

        $decimal_places = $xunCurrency->get_currency_decimal_places($currency_id);
        $donation_value = bcmul((string)$amount, (string)$currency_rate, $decimal_places);
        // $donation_value = $xunCurrency->get_conversion_amount($currency_id, $wallet_type, $amount, null, $currency_rate);

        $date = date("Y-m-d H:i:s");

        $insert_transaction_data = array(
            "user_id" => $donator_user_id,
            "crypto_history_id" => $crypto_history_id,
            "story_id" => $story_id,
            "amount" => $amount,
            "wallet_type" => $wallet_type,
            "value" => $donation_value,
            "currency_id" => $currency_id,
            "currency_rate" => $currency_rate,
            "transaction_type" => "donation",
            "status" => $status,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $story_transaction_id = $db->insert("xun_story_transaction", $insert_transaction_data);

        if (!$story_transaction_id) {
            $log->write("\n" . date("Y-m-d H:i:s") . ": Story Donation Callback : Crypto history ID = $crypto_history_id. Error: " . $db->getLastError());
        }

        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story');
        $story_user_id = $xun_story["user_id"];

        $db->where('story_id', $story_id);
        $db->where('story_type', "story");
        $updates_result = $db->getOne('xun_story_updates', "title");
        $obj->story_title = $updates_result["title"];

        $uc_currency_name = $this->get_fiat_currency_name($currency_id, 1);
            
        $db->where('currency_id', $wallet_type);
        $marketplace_currencies = $db->getOne('xun_marketplace_currencies');
        
        $unit = strtoupper($marketplace_currencies["symbol"]);
        $crypto_amount_with_fiat = $amount ." ". $unit . " (" . $donation_value . " " . $uc_currency_name . ")";

        if($donator_user_id){
            $db->where('id', $donator_user_id);
            $donator_result = $db->getOne('xun_user');

            $donator_nickname = $donator_result["nickname"];
            $donator_mobile = $donator_result["username"] ? $donator_result["username"] : '';
            $donator_email = $donator_result["email"] ? $donator_result["email"] : '';

            $user_setting = $this->get_user_ip_and_country($donator_user_id);

            $ip = $user_setting["lastLoginIP"]["value"];
            $user_country = $user_setting["ipCountry"]["value"];

            $user_device_info = $this->get_user_device_info($donator_mobile);
            if ($user_device_info) {
                $device_os = $user_device_info["os"];
                
                if($device_os == 1)
                {$device = "Android";}
                else if ($device_os == 2){$device = "iOS";}

            } else {
                $db->where('name', 'device');
                $db->where('user_id', $donator_user_id);
                $device_info = $db->getOne('xun_user_setting');
                if($device_info){
                    $device = $device_info["value"];
                }
                else{
                    $device = "";
                }
            }
            
        }
        
        if($status == "success"){

            $fund_amount = $xun_story["fund_amount"];
            $fund_period = $xun_story["fund_period"];
            $story_status = $xun_story["status"];
    
            $total_donation = $this->get_story_total_donation($story_id);
            
            $updated_fund_collected = $total_donation + $donation_value;
    
            $new_story_status = $updated_fund_collected >= $fund_amount ? "completed" : $story_status;
    
            $this->update_story_donation_cache($story_id, $new_story_status, $donation_value, $donator_user_id);      

            $db->where('story_id', $story_id);
            $db->groupBy('user_id');
            $story_transaction = $db->get('xun_story_transaction', null, "user_id");

            if($new_story_status == "completed"){
                $this->insert_story_notification($story_id, 0, $story_user_id, "story_completed", $obj);
                foreach($story_transaction as $story_key=> $story_value){
                    $backers_user_id = $story_value["user_id"];
                    if($story_user_id != $backers_user_id){
                        $this->insert_story_notification($story_id, 0, $backers_user_id, "story_completed", $obj);
                    }
                    
                }
            }

            $currency_name = $this->get_fiat_currency_name($currency_id);

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
            $creditType = $decimal_place_setting["credit_type"];
            $final_donation_value = $setting->setDecimal($donation_value, $creditType);
            
            $obj->value = $final_donation_value;
            $obj->name = strtoupper($currency_name);
            $obj->story_title = $updates_result["title"];

            $to_id = $xun_story["user_id"];
                
            $this->insert_story_notification($story_id, $donator_user_id, $to_id, "donation", $obj);

            $this->insert_story_user_activity($donator_user_id, $story_transaction_id, "donation", $obj);

            $this->send_crypto_donation_notification($donator_nickname, $donator_mobile, $donator_email, $user_country, $device, $user_type, $status, $updates_result["title"], $from_address, $to_address, $wallet_type, $crypto_amount_with_fiat);
        }
        elseif($status == 'failed'){
            $this->send_crypto_donation_notification($donator_nickname, $donator_mobile, $donator_email, $user_country, $device, $user_type, $status, $updates_result["title"], $from_address, $to_address, $wallet_type, $crypto_amount_with_fiat);
        } 

    }

    public function update_donation_callback($wallet_transaction){
        global $log;
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;
        
        // #### ADD CREDIT SYSTEM FOR CUSTOM COINS

        /**
         * callback comes in, if receiver is story address, 
         * amount and wallet type tallies then success
         * 
         * call this function
         * check transaction table if the total == fund needed
         * update story cache amount
         */

        $wallet_transaction_id = $wallet_transaction["id"];
        $user_id = $wallet_transaction["user_id"];

        // check story_transaction status 
        // only update if it's not pending
        $db->where("wallet_transaction_id", $wallet_transaction_id);
        $story_transaction = $db->getOne("xun_story_transaction");

        if(!$story_transaction){
            $log->write("\n" . date("Y-m-d H:i:s") . ": Wallet transaction ID = $wallet_transaction_id. Error:  Invalid story wallet transaction id.");
            return;
        }

        if($story_transaction["status"] != "pending"){
            $log->write("\n" . date("Y-m-d H:i:s") . ": Wallet transaction ID = $wallet_transaction_id. Error:  Story transaction no longer in pending state.");
            return;
        }

        $story_transaction_id = $story_transaction["id"];
        $story_id = $story_transaction["story_id"];
        $donation_value = $story_transaction["value"];
        $currency_id = strtolower($story_transaction["currency_id"]);
        $donator_user_id = $story_transaction["user_id"];

        $db->where("id", $story_id);
        $xun_story = $db->getOne("xun_story");

        if(!$xun_story){
            $log->write("\n" . date("Y-m-d H:i:s") . ": Story ID = $story_id. Error:  Invalid story ID on donation callback.");
            return;
        }
        
        $date = date("Y-m-d H:i:s");
        $fund_amount = $xun_story["fund_amount"];
        $fund_period = $xun_story["fund_period"];
        $story_status = $xun_story["status"];

        $total_donation = $this->get_story_total_donation($story_id);
        
        $updated_fund_collected = $total_donation + $donation_value;

        //  if over limit, take what is needed, refund the rest
        if($total_donation >= $fund_amount){
            //  full refund
            //  update status to refund
            //  wallet_transaction reference original record id
            $status = "pending_refund";
            $update_transaction_data = [];
            $update_transaction_data["status"] = $status;
            $update_transaction_data["message"] = "fund limit reached";
            $update_transaction_data["updated_at"] = $date;
        }else{
            $updated_fund_collected = $total_donation + $donation_value;

            if($updated_fund_collected > $fund_amount){
                //  perform partial refund
                $refund_value = $updated_fund_collected - $fund_amount;
                $final_donation_value = $donation_value - $refund_value;

                $currency_rate = $story_transaction["currency_rate"];

                $decimal_places = $xunCurrency->get_currency_decimal_places($wallet_type);
                
                $refund_amount = bcdiv((string)$refund_value, (string)$currency_rate, $decimal_places);
                $final_donation_amount = $donation_amount - $refund_amount;

                //  insert refund transaction
                //  update initial status
                //  wallet transaction references refund record

                $insert_refund_transaction_data = array(
                    "user_id" => $story_transaction["user_id"],
                    "story_id" => $story_id,
                    "amount" => $refund_amount,
                    "wallet_type" => $wallet_type,
                    "value" => $refund_value,
                    "currency_id" => $currency_id,
                    "currency_rate" => $currency_rate,
                    "description" => "",
                    "transaction_type" => "donation_refund",
                    "status" => "pending_refund",
                    "story_transaction_id" => $story_transaction_id,
                    "created_at" => $date,
                    "updated_at" => $date
                );
            }else{
                $final_donation_value = $donation_value;
            }

            $new_story_status = $updated_fund_collected >= $fund_amount ? "completed" : $story_status;

            $update_transaction_data = [];
            $update_transaction_data["status"] = "success";

            $this->update_story_donation_cache($story_id, $new_story_status, $final_donation_value, $user_id);

        }
       
        $update_transaction_data["updated_at"] = $date;

        $db->where("id", $story_transaction_id);
        $update_res = $db->update("xun_story_transaction", $update_transaction_data);

        if(!$update_res){
            $log->write("\n" . date("Y-m-d H:i:s") . ": Wallet transaction ID = $wallet_transaction_id. Error: Fail to update xun_story_transaction.");
            return;
        }

        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story');
        $story_user_id = $xun_story["user_id"];

        $db->where('story_id', $story_id);
        $db->where('story_type', "story");
        $updates_result = $db->getOne('xun_story_updates', "title");
        $obj->story_title = $updates_result["title"];

        $db->where('story_id', $story_id);
        $db->groupBy('user_id');
        $story_transaction = $db->get('xun_story_transaction', null, "user_id");

        if($new_story_status == "completed"){
            $this->insert_story_notification($story_id, 0, $story_user_id, "story_completed", $obj);
            foreach($story_transaction as $story_key=> $story_value){
                $backers_user_id = $story_value["user_id"];
                if($story_user_id != $backers_user_id){
                    $this->insert_story_notification($story_id, 0, $backers_user_id, "story_completed", $obj);
                }
                
            }
        }

        $xun_company_wallet = new XunCompanyWallet($db, $setting, $post);

        if($status == "pending_refund"){
            $refund_res = $xun_company_wallet->storyTransactionRefund($story_transaction);
        }

        if($insert_refund_transaction_data){
            $row_id = $db->insert("xun_story_transaction", $insert_refund_transaction_data);
            
            if(!$row_id){
                $log->write("\n" . date("Y-m-d H:i:s") . ": Wallet transaction ID = $wallet_transaction_id. Error: " . $db->getLastError());
                return;
            }
            $insert_refund_transaction_data["id"] = $row_id;
             
            $refund_res = $xun_company_wallet->storyTransactionRefund($insert_refund_transaction_data);
        }

        $currency_name = $this->get_fiat_currency_name($currency_id);

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
        $creditType = $decimal_place_setting["credit_type"];
        $final_donation_value = $setting->setDecimal($final_donation_value, $creditType);
        
        $obj->value = $final_donation_value;
        $obj->name = strtoupper($currency_name);
        $obj->story_title = $updates_result["title"];

        $to_id = $xun_story["user_id"];
            
        $this->insert_story_notification($story_id, $donator_user_id, $to_id, "donation", $obj);

        $this->insert_story_user_activity($donator_user_id, $story_transaction_id, "donation", $obj);

        // if($new_story_status == "completed"){
        //     $xun_story["status"] = $new_story_status;
        //     $xun_story["fund_collected"] = $xun_story["fund_amount"];
        //     $this->process_withdrawal($xun_story);
        // }

    }

    public function update_transaction_status($obj){
        $db = $this->db;

        $id = $obj->id;
        $status = $obj->status;

        $date = date("Y-m-d H:i:s");
        $update_data = [];
        $update_data["status"] = $status;
        $update_data["updated_at"] = $date;

        $db->where("id", $id);
        $ret_val = $db->update("xun_story_transaction", $update_data);
        return $ret_val;
    }

    public function get_story_total_donation($story_id){
        $db = $this->db;

        $db->where("story_id", $story_id);
        $db->where("transaction_type", ["donation", "donation_refund"], "IN");
        $story_transaction_arr = $db->get("xun_story_transaction");

        $total_donation = 0;

        for($i = 0; $i < count($story_transaction_arr); $i++){
            $data = $story_transaction_arr[$i];
            $transaction_type = $data["transaction_type"];
            $status = $data["status"];
            $value = $data["value"];
            
            if($transaction_type == "donation" && $status == "success"){
                $total_donation += $value;
            }

            if($transaction_type == "donation_refund"){
                $total_donation -= $value;
            }
        }
        return $total_donation;
    }

    public function update_story_donation_cache($story_id, $status, $amount_donated, $user_id){
        $db = $this->db;

        $db->where('status', "success");
        $db->where('story_id' , $story_id);
        $db->where('user_id', $user_id);
        $story_transaction = $db->getValue('xun_story_transaction', 'count(id)');
        $update_data = [];
        if($story_transaction <= 1){
            $update_data["total_supporters"] = $db->inc(1); 
        }
        $update_data["fund_collected"] = $db->inc($amount_donated);
        $update_data["status"] = $status;
        $update_data["updated_at"] = date("Y-m-d H:i:s");
        $db->where("id", $story_id);
        $ret_val = $db->update("xun_story", $update_data);

        return $ret_val;
    }

    public function withdraw_fund($params){
        $db = $this->db;
        $setting = $this->setting;

        $username = trim($params["username"]);
        $story_id = trim($params["story_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID cannot be empty", 'developer_msg' => "story id cannot be empty");
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);
        
        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $db->where("id", $story_id);
        $xun_story = $db->getOne("xun_story");

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found.");
        }

        $story_status = $xun_story["status"];

        if($story_status == "active"){
            return array("code" => 0, "message" => "FAILED", "message_d" => "Withdrawal is not allowed for ongoing story.");
        }

        $story_user_id = $xun_story["user_id"];

        $story_user = $xun_user_service->getUserByID($story_user_id);

        $can_withdraw = false;
        if($story_user["type"] == "user" && $story_user_id == $user_id){
            $can_withdraw = true;
        }else if($story_user["type"] == "business"){
            //  check if user is business employee

            $xun_business_service = new XunBusinessService($db);
            
            $is_business_employee = $xun_business_service->isBusinessEmployee($story_user_id, $username);

            $can_withdraw = $is_business_employee == true ? true : false;
        }

        if($can_withdraw === true){

            $db->where("story_id", $story_id);
            $db->where("transaction_type", "withdraw");
            $withdraw_transaction = $db->getOne("xun_story_transaction");

            if($withdraw_transaction){
                return array("code" => 0, "message" => "FAILED", "message_d" => "You've requested for a withdrawal for this story.");
            }

            $nickname = $xun_user["nickname"];
            $story_amount = $xun_story["fund_amount"];
            $fund_collected = $xun_story["fund_collected"];
            $story_currency = $xun_story["currency_id"];

            $date = date("Y-m-d H:i:s");

            $insert_data = array(
                "story_id" => $story_id,
                "user_id" => $user_id,
                "amount" => "",
                "wallet_type" => "",
                "value" => $fund_collected,
                "currency_id" => $story_currency,
                "transaction_type" => "withdraw",
                "status" => "pending",
                "description" => "",
                "story_transaction_id" => "",
                "message" => "",
                "created_at" => $date,
                "updated_at" => $date
            );
            $row_id = $db->insert("xun_story_transaction", $insert_data);
            // if(!$row_id){
            //     print_r($db);
            // }

            $db->where("story_id", $story_id);
            $db->where("story_type", "story");
            $story_updates = $db->getOne("xun_story_updates", "id, story_id, title");

            $story_title = $story_updated["title"];

            $subject = "Story ID: ${story_id}. TheNux story: Fund Withdrawal.";
            $content = "Story ID: ${story_id}\n";
            $content .= "&nbsp;Title: ${story_title}\n";
            $content .= "Requested By: \n";
            $content .= "&nbsp;&nbsp;Username: ${username}\n";
            $content .= "&nbsp;&nbsp;Nickname: ${nickname}\n";
            $content .= "Withdrawal amount: $fund_collected \n" . strtoupper($story_currency);
    
            $ticket_params = array(
                "username" => $username,
                "nickname" => $nickname,
                "subject" => $subject,
                "content" => $content
            );
    
            $this->send_fund_withdrawal_ticket($ticket_params);
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");

        }else {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You are not allowed to withdraw funds of this story.");
        }
    }


    private function send_fund_withdrawal_ticket($params)
    {
        global $setting, $ticket;

        $username = $params["username"];
        $nickname = $params["nickname"];
        $subject = $params["subject"];
        $content = $params["content"];

        $clientName = $nickname;
        $clientEmail = $setting->systemSetting["systemEmailAddress"];

        $ticket_params = array(
            'clientID' => '',
            'clientName' => $clientName,
            'clientEmail' => $clientEmail,
            'clientPhone' => $username,
            'status' => "open",
            'priority' => 1,
            'type' => "incident",
            'subject' => $subject,
            'department' => "customerService",
            'reminderDate' => "",
            'assigneeID' => "",
            'assigneeName' => "",
            'creatorID' => '',
            'internal' => 1,
            'content' => $content,
        );

        $res = $ticket->addTicket($ticket_params);
        return $res;
    }

    public function get_manage_fund_listing($params){
        global $xunCurrency;

        $db = $this->db;
        $setting = $this->setting;

        $username = trim($params["username"]);
        $story_id = trim($params["story_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID cannot be empty", 'developer_msg' => "story id cannot be empty");
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);
        
        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $db->where("id", $story_id);
        $xun_story = $db->getOne("xun_story");

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found.");
        }

        $story_user_id = $xun_story["user_id"];
        $story_currency = $xun_story["currency_id"];

        $story_user = $xun_user_service->getUserByID($story_user_id);

        $can_access = false;
        if($story_user["type"] == "user" && $story_user_id == $user_id){
            $can_access = true;
        }else if($story_user["type"] == "business"){
            //  check if user is business employee

            $xun_business_service = new XunBusinessService($db);
            
            $is_business_employee = $xun_business_service->isBusinessEmployee($story_user_id, $username);

            $can_access = $is_business_employee == true ? true : false;
        }

        if($can_access !== false){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "You are not allowed to access this information.");
        }

        $donation_result = $this->get_donation_listing($story_id);
        $total_collected = $donation_result["total_collected"];
        $coin_total_listing = $donation_result["coin_listing"];

        $coin_listing = [];
        
        $fiatDecimalPlaceSetting = $xunCurrency->get_currency_decimal_places($story_currency, true);
        $fiatCreditType = $fiatDecimalPlaceSetting["credit_type"];

        foreach($coin_total_listing as $wallet_type => $data){
            $total_coin = $data["total_coin"];
            $total_fiat = $data["total_fiat"];

            $decimalPlaceSetting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
            $creditType = $decimalPlaceSetting["credit_type"];
            $total_coin = $setting->setDecimal($total_coin, $creditType);
            
            $total_fiat = $setting->setDecimal($total_fiat, $fiatCreditType);

            $coin_data = array(
                "wallet_type" => $wallet_type,
                "total_coin" => $total_coin,
                "total_fiat" => $total_fiat,
                "currency_id" => $story_currency
            );
            
            $coin_listing[] = $coin_data;

        }

        $total_collected = bcmul((string)$total_collected, "1", 2);
        $total_collected = $setting->setDecimal($total_collected, $fiatCreditType);

        $return_data = [];
        $return_data["total"] = array(
            "amount" => $total_collected, 
            "unit" => strtoupper($story_currency)
        );
        $return_data["coin_listing"] = $coin_listing;
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Story fund details.", "data" => $return_data);
    }

    function get_donation_listing($story_id){
        $db = $this->db;

        $db->where("story_id", $story_id);
        $db->where("transaction_type", ["donation", "donation_refund"], "IN");
        $story_transaction_arr = $db->get("xun_story_transaction");

        $total_collected = 0;
        $coin_listing = [];
        for($i = 0; $i < count($story_transaction_arr); $i++){
            $data = $story_transaction_arr[$i];

            $wallet_type = $data["wallet_type"];
            $amount = $data["amount"];
            $value = $data["value"];

            $transaction_type = $data["transaction_type"];
            $status = $data["status"];

            $coin_data = $coin_listing[$wallet_type];
            $total_coin = isset($coin_data) ? $coin_data["total_coin"] : 0;
            $total_fiat = isset($coin_data) ? $coin_data["total_fiat"] : 0;

            if($transaction_type == "donation" && $status == "success"){
                $total_coin += $amount;
                $total_fiat += $value;
                $total_collected += $value;

                $coin_listing[$wallet_type] = array("total_coin" => $total_coin, "total_fiat" => $total_fiat);
            }else if($transaction_type == "donation_refund"){
                $total_coin -= $amount;
                $total_fiat -= $value;
                $total_collected -= $value;

                $coin_listing[$wallet_type] = array("total_coin" => $total_coin, "total_fiat" => $total_fiat);
            }
        }

        return array("total_collected" => $total_collected, "coin_listing" => $coin_listing);
    }

    public function get_story_backers_listing($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $page_limit = $setting->systemSetting["appsPageLimit"];

        $username = trim($params["username"]);
        $story_id = trim($params["story_id"]);
        $last_id = trim($params["last_id"]);
        $page_size = trim($params["page_size"]);

        $last_id = $last_id ? $last_id : 0;
        $page_size = $page_size ? $page_size : $page_limit;

        $order = strtoupper(trim($params["order"]));
        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $limit = array($last_id, $page_size);


        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required.", 'developer_msg' => "story id cannot be empty");
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);
        
        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];

        $db->where("id", $story_id);
        $xun_story = $db->getOne("xun_story");

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found.");
        }


        $db->where("a.story_id", $story_id);
        $db->where("a.transaction_type", "donation");
        $db->where("a.status", "success");
        $copyDb = $db->copy();
        $copyDb2 = $db->copy();
        $db->join('xun_user b', "a.user_id = b.id", "LEFT");
        $db->orderBy("a.id", "asc");

        $copyDb2->groupBy("user_id");
        $user_ids = $copyDb2->get("xun_story_transaction a", $limit, "user_id");
        if (!$user_ids)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "No story transaction found within limit.");
        foreach($user_ids as $user_id){
            $user_id_array[] = $user_id['user_id'];
        }

        // $db->map("id")->ArrayBuilder();
        $db->where("user_id", $user_id_array, "IN");
        $columns = "a.id, user_id, amount, wallet_type, value, currency_id, transaction_type, a.status, description, story_transaction_id, a.created_at, b.username, b.nickname, b.type";
        $story_transaction_arr = $db->get("xun_story_transaction a", null, $columns);

        $copyDb->groupBy("user_id");
        $distinct_records = $copyDb->getValue("xun_story_transaction a", "count(distinct(user_id))", null);
        $total_records = array_sum($distinct_records);

        $db->where("story_id", $story_id);
        $db->where("transaction_type", "donation_refund");
        $donation_refund_arr = $db->map("story_transaction_id")->ArrayBuilder()->get("xun_story_transaction", null, "id, amount, value, story_transaction_id");

        $story_transaction_result = [];
        $currency_id = $xun_story["currency_id"];

        $fiatDecimalPlaceSetting = $xunCurrency->get_currency_decimal_places($currency_id, true);
        $fiatCreditType = $fiatDecimalPlaceSetting["credit_type"];

        $user_id_arr = [];

        foreach($story_transaction_arr as $key => $transaction_data){
            $id = $transaction_data["id"];
            $status = $transaction_data["status"];
            $amount = $transaction_data["amount"];
            $value = $transaction_data["value"];
            $wallet_type = $transaction_data["wallet_type"];
            $created_at = $transaction_data["created_at"];
            $tx_currency_id = $transaction_data["currency_id"];
            $currency_name = $this->get_fiat_currency_name($tx_currency_id);
            $uc_currency_name = strtoupper($currency_name);

            $user_id = $transaction_data["user_id"];
            $username = $transaction_data["username"];
            $nickname = $transaction_data["nickname"];
            $user_type = $transaction_data["type"];

            $username = $user_type == "user" ? $username : $user_id;

            $donation_refund = $donation_refund_arr[$id];
            if($donation_refund){
                $refund_amount = $donation_refund["amount"];
                $refund_value = $donation_refund["value"];
                $donation_amount = $amount - $refund_amount;
                $donation_value = $value - $refund_value;
            }else{
                $donation_amount = $amount;
                $donation_value = $value;
            }

            $decimalPlaceSetting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
            $creditType = $decimalPlaceSetting["credit_type"];

            $donation_value = $setting->setDecimal($donation_value, $fiatCreditType);
            $donation_amount = $setting->setDecimal($donation_amount, $creditType);

            $user_transaction_result = $story_transaction_result[$user_id];
            if(isset($user_transaction_result)){
                $user_donation_value = $user_transaction_result["fiat_amount"];
                $total_user_donation_value = $user_donation_value + $donation_value;
                $total_user_donation_value = $setting->setDecimal($total_user_donation_value, $fiatCreditType);
                
                $user_transaction_result["fiat_amount"] = $total_user_donation_value;
                $story_transaction_result[$user_id] = $user_transaction_result;
            }else{
                $data = [];
                $data["user_id"] = $user_id;
                $data["username"] = (string)$username;
                $data["nickname"] = $nickname;
                $data["user_type"] = $user_type;
                $data["is_verified"] = false;
                $data["currency_id"] = $currency_id;
                $data["currency_name"] = $uc_currency_name;
                $data["fiat_amount"] = $donation_value;
                $story_transaction_result[$user_id] = $data;
            }

            $user_id_arr[] = $user_id;
        }

        if(!empty($user_id_arr)){
            $db->where("user_id", $user_id_arr, "IN");
            $db->where("status", "approved");
            $kyc_arr = $db->map("user_id")->ArrayBuilder()->get("xun_kyc", null, "id, user_id, status");

            $db->where("user_id", $user_id_arr, "IN");
            $xun_user_details = $db->map("user_id")->ArrayBuilder()->get("xun_user_details", null, "user_id, id, picture_url");
        }

        foreach($story_transaction_result as &$data){
            $user_id = $data["user_id"];
            if($kyc_arr[$user_id]){
                $data["is_verified"] = true;
            }

            $user_picture_url = $xun_user_details[$user_id]["picture_url"];
            $data["picture_url"] = $user_picture_url ? $user_picture_url : '';
            // unset($data["user_id"]);
        }

        $fiat_amount_arr = array_column($story_transaction_result, 'fiat_amount');

        array_multisort($fiat_amount_arr, SORT_DESC, $story_transaction_result);

        $return_data = [];
        $return_data["listing"] = (array)$story_transaction_result;
        $return_data["totalRecord"] = $total_records;
        $return_data["numRecord"]  = count($story_transaction_result);
        $return_data["totalPage"] = ceil($total_records / $page_size);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Backers listing.", "data" => $return_data);
    }

    public function get_story_transaction_history_listing($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $page_limit = $setting->systemSetting["appsPageLimit"];

        $username = trim($params["username"]);
        $user_id = trim($params["user_id"]);
        $story_id = trim($params["story_id"]);
        $last_id = trim($params["last_id"]);
        $page_size = trim($params["page_size"]);

        $last_id = $last_id ? $last_id : 0;
        $page_size = $page_size ? $page_size : $page_limit;

        $order = strtoupper(trim($params["order"]));
        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $limit = array($last_id, $page_size);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required.", 'developer_msg' => "story id cannot be empty");
        }

        if($user_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User ID is required.", 'developer_msg' => "user id cannot be empty");
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);
        
        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found.");
        }

        // $user_id = $xun_user["id"];

        $db->where("id", $story_id);
        $xun_story = $db->getOne("xun_story");

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found.");
        }

        $db->where("a.story_id", $story_id);
        $db->where("a.user_id", $user_id);
        $db->where("a.transaction_type", "donation");
        $db->where("a.status", "success");
        $copyDb = $db->copy();
        $db->join('xun_user b', "a.user_id = b.id", "LEFT");
        $db->orderBy("a.id", "asc");

        $columns = "a.id, user_id, amount, wallet_type, value, currency_id, transaction_type, a.status, description, story_transaction_id, a.created_at, b.username, b.nickname, b.type";
        $story_transaction_arr = $db->get("xun_story_transaction a", $limit, $columns);

        $total_record = $copyDb->getValue("xun_story_transaction a", "count(id)");

        $db->where("story_id", $story_id);
        $db->where("transaction_type", "donation_refund");
        $donation_refund_arr = $db->map("story_transaction_id")->ArrayBuilder()->get("xun_story_transaction", null, "id, amount, value, story_transaction_id");

        $story_transaction_result = [];
        $currency_id = $xun_story["currency_id"];

        $fiatDecimalPlaceSetting = $xunCurrency->get_currency_decimal_places($currency_id, true);
        $fiatCreditType = $fiatDecimalPlaceSetting["credit_type"];

        $story_currency = strtoupper($currency_id);

        $user_id_arr = array_column($story_transaction_arr, "user_id");

        if(!empty($user_id_arr)){
            $db->where("user_id", $user_id_arr, "IN");
            $xun_user_details = $db->map("user_id")->ArrayBuilder()->get("xun_user_details", null, "id, user_id, picture_url");
        }

        foreach($story_transaction_arr as $key => $transaction_data){
            $id = $transaction_data["id"];
            $status = $transaction_data["status"];
            $amount = $transaction_data["amount"];
            $value = $transaction_data["value"];
            $wallet_type = $transaction_data["wallet_type"];
            $created_at = $transaction_data["created_at"];
            $created_at = $general->formatDateTimeToIsoFormat($created_at);

            $user_id = $transaction_data["user_id"];
            $username = $transaction_data["username"];
            $nickname = $transaction_data["nickname"];
            $user_type = $transaction_data["type"];
            $picture_url = $xun_user_details[$user_id]["picture_url"];

            $username = $user_type == "user" ? $username : $user_id;

            $donation_refund = $donation_refund_arr[$id];
            if($donation_refund){
                $refund_amount = $donation_refund["amount"];
                $refund_value = $donation_refund["value"];
                $donation_amount = $amount - $refund_amount;
                $donation_value = $value - $refund_value;
            }else{
                $donation_amount = $amount;
                $donation_value = $value;
            }

            $decimalPlaceSetting = $xunCurrency->get_currency_decimal_places($wallet_type, true);
            $creditType = $decimalPlaceSetting["credit_type"];
            
            $donation_value = $setting->setDecimal($donation_value, $fiatCreditType);
            $donation_amount = $setting->setDecimal($donation_amount, $creditType);
            
            $currency_info = $xunCurrency->get_currency_info($wallet_type);
            $crypto_unit = strtoupper($currency_info["symbol"]);

            $fiat_info = $xunCurrency->get_currency_info($currency_id);
            $currency_name = strtoupper($fiat_info["symbol"]);
            
            $data = [];
            $data["username"] = (string) $username;
            $data["user_type"] = $user_type;
            $data["nickname"] = $nickname;
            $data["picture_url"] = $picture_url ? $picture_url : '';
            $data["donation_value"] = $donation_value;
            $data["currency_name"] = $currency_name;
            $data["created_at"] = $created_at;

            $story_transaction_result[] = $data;
        }

        $return_data = [];
        $return_data["listing"] = $story_transaction_result;
        $return_data["totalRecord"] = $total_record;
        $return_data["numRecord"] = count($story_transaction_result);
        $return_data["totalPage"] = ceil($total_record / $page_size);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Transaction history listing.", "data" => $return_data);
    }

    public function get_donated_story_listing($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;
        
        $now_second = time();
        $now = date("Y-m-d H:i:s");

        $page_limit = $setting->systemSetting["appsPageLimit"];

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $last_id = trim($params["last_id"]);
        $page_size = trim($params["page_size"]);

        $last_id = $last_id ? $last_id : 0;
        $page_size = $page_size ? $page_size : $page_limit;

        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }
        
        $xun_user = $db->getOne('xun_user');
        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }
        $user_id = $xun_user["id"];

        $db->where('b.user_id', $user_id);
        $db->join('xun_story_transaction b', "a.id=b.story_id", "LEFT");
        $copyDb = $db->copy();
        $db->orderBy("a.id", "desc");

        $limit = array($last_id, $page_size);

        $story_arr = $db->get('xun_story a', $limit, "distinct(a.id), a.*");
        $totalRecord = $copyDb->getValue('xun_story a', "count(distinct(a.id))");

        $xun_story_category = $db->map('id')->ArrayBuilder()->get('xun_story_category');
        
        $story_id_arr = [];
        $user_id_arr = [];
        
        $story_listing = [];
        for($i = 0; $i < count($story_arr); $i++){
            $story_data = $story_arr[$i];
            $expire_date = '';
            $diffSecond = '';
            $diffDays = '';
            $hours = '';

            $story_id = $story_data["id"];
            $story_user_id = $story_data["user_id"];
            $category_id = $story_data["category_id"];
            $category = $xun_story_category[$category_id]["category"]; 
            $recommended = $story_data["recommended"];
            $total_supporters = $story_data["total_supporters"];
            $currency_id = $story_data["currency_id"];

            $expire_date = strtotime($story_data["expires_at"]);
            
            if($expire_date){
                $diffSecond = $expire_date - $now_second;
                $diffDays = floor($diffSecond /86400);
                if($diffDays == 0){
                    $hours=floor(($diffSecond-$diffDays*60*60*24)/(60*60));
                }
                else{
                    $hours = 0;
                }
                
            }

            $fund_amount = $story_data["fund_amount"];
            $fund_collected = $story_data["fund_collected"];

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
            $creditType = $decimal_place_setting["credit_type"];
            $fund_amount = $setting->setDecimal($fund_amount, $creditType);
            $fund_collected = $setting->setDecimal($fund_collected, $creditType);

            if($fund_amount){
                $supportedPercentage = number_format((float)($fund_collected/$fund_amount) *100,2, '.', '');
            }

            $user_verified = 0;
            $story_saved = 0;

            $data = [];
            $data["id"] = $story_id;
            $data["user_id"] = $story_user_id;
            $data["category"] = $category;
            $data["fund_collected_pct"] = (string)$supportedPercentage;
            $data["days_left"] = $diffDays;
            $data["hours_left"] = $hours;
            // $data["expires_at"] = $general->formatDateTimeToIsoFormat($story_data["expires_at"]);
            $data["total_supporters"] = $total_supporters;
            $data["recommended"] = $recommended;

            $story_listing[] = $data;
            $story_id_arr[] = $story_id;
            $user_id_arr[] = $story_user_id;
        }

        if(!empty($story_listing)){
            $db->where("id", $user_id_arr, "IN");
            $xun_user_arr = $db->map("id")->ArrayBuilder()->get("xun_user", null, "id, username, type, nickname");

            $db->where("a.story_id", $story_id_arr, "IN");
            $db->where("a.story_type", "story");
            $db->join("xun_story_media b", "a.id=b.story_updates_id", "LEFT");
            $story_updates_result = $db->get("xun_story_updates a", null, "a.id, a.story_id, a.title, a.description, b.id as media_id, b.media_url, b.media_type");
            $story_updates_arr = [];
            for($i = 0; $i < count($story_updates_result); $i++){
                $story_updates_data = $story_updates_result[$i];
                $story_id = $story_updates_data["story_id"];
                if(isset($story_updates_arr[$story_id])) continue;

                $story_updates_arr[$story_id] = $story_updates_data;
            }

            $db->where("story_id", $story_id_arr, "IN");
            $db->where("user_id", $user_id);
            $saved_story_arr = $db->getValue("xun_story_favourite", "story_id", null);

            $db->where("user_id", $user_id_arr, "IN");
            $db->where("status", "approved");
            $kyc_arr = $db->map("user_id")->ArrayBuilder()->get("xun_kyc", null, "id, user_id, status");

            foreach($story_listing as &$data){
                $story_user_id = $data["user_id"];
                $story_id = $data["id"];
                
                $story_user_data = $xun_user_arr[$story_user_id];
                $story_username = $story_user_data["username"];
                $story_user_nickname = $story_user_data["nickname"];
                $story_user_type = $story_user_data["type"];

                $story_username = $story_user_type == "user" ? $story_username : $story_user_id;

                $kyc_is_verified = $kyc_arr[$story_user_id] ? 1 : 0;

                $story_updates_data = $story_updates_arr[$story_id];

                $saved_story = in_array($story_id, $saved_story_arr) ? 1 : 0;

                $data["username"] = $story_username;
                $data["nickname"] = $story_user_nickname;
                $data["user_type"] = $story_user_type;
                $data["user_verified"] = $kyc_is_verified;
                $data["title"] = $story_updates_data["title"];
                $data["description"] = $story_updates_data["description"];
                $data["media_url"] = $story_updates_data["media_url"];
                $data["media_type"] = $story_updates_data["media_type"];

                $data["story_saved"] = $saved_story;
                unset($data["user_id"]);
            }
        }
  
        $returnData["story_list"] = $story_listing;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = count($story_listing);
        $returnData["totalPage"] = ceil($totalRecord / $page_size);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Donated Story List.", "data"=>$returnData);
    }

    public function set_payment_method($params, $sourceName){
        global $xunCrypto;
        $db = $this->db;
        
        $username = trim($params["username"]);//for app validation
        $business_id = trim($params["business_id"]);
        $user_id = trim($params["user_id"]);//for web validation
        // $user_bank_id = trim($params['user_bank_id']);
        $country = trim($params['country']);
        $payment_name = trim($params['payment_name']);
        $payment_type_id = trim($params['payment_type_id']);
        // $payment_method_id = trim($params['payment_method_id']);
        $account = trim($params['account']);
        $account_holder = trim($params['account_holder']);
        $qr_code = trim($params['qr_code']);
        $wallet_type_list = $params["wallet_type"];
        $external_address = $params["external_address"];

        if ($username == '' && $sourceName == 'app') { 
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "app: username cannot be empty");
        }

        if($user_id == '' && $sourceName == 'web'){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "user_id is required.", 'developer_msg' => "web: user_id cannot be empty");
        }

        if($payment_type_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Payment type is required.", 'developer_msg' => "payment_type cannot be empty"); 
        }

        if($payment_type_id == "1"){
            $wallet_type = $wallet_type_list;
            if($sourceName == "app"){
                if(!is_array($wallet_type_list)){
                    return array("code" => 0, "message" => "FAILED", "message_d" => "Wallet type must be an array.");
                }
            }
            elseif($sourceName == "web"){
                if($wallet_type == ''){
                    return array("code" => 0, "message" => "FAILED", "message_d" => "Wallet type is needed.");
                }

                if($external_address == ''){
                    return array("code" => 0, "message" => "FAILED", "message_d" => "External address is required.");
                }

            }
        }else{
            if ($payment_type_id == '2'){
                if(!$country)
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Country is required.", 'developer_msg' => "Country cannot be empty");
                if(!$payment_name)
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Bank name is required.", 'developer_msg' => "Bank name cannot be empty");
            }            
            if(!$account)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Bank account is required.", 'developer_msg' => "Bank account cannot be empty");
            if(!$account_holder)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Bank account holder detail is required.", 'developer_msg' => "Bank account holder cannot be empty");
        }

        if($user_id){
            $xun_user = $this->validate_user_id($user_id);
        }
        elseif($username){
            $xun_user = $this->validate_username($username, $business_id);
        }
        
        if(isset($xun_user["code"]) && $xun_user["code"] == 0){
            return $xun_user;
        }

        $user_id = $xun_user["id"];
        $date = date("Y-m-d H:i:s");

        $db->where('id', $payment_type_id);
        $payment_method = $db->getOne('xun_payment_method_settings');
        $payment_type = $payment_method['name'];
        
        $db->where('payment_type', $payment_type, 'LIKE');
        $payment_method_id = $db->getValue('xun_marketplace_payment_method', 'id');

        if($payment_type_id == "1"){
            if($sourceName == "app"){
                try{

                    //  check if the payment method is removed

                    $user_crypto_payment_method = $this->get_user_crypto_payment_method_list($user_id, "wallet_type");

                    $current_payment_method_wallet_type = [];

                    foreach($user_crypto_payment_method as $data){
                        if($data["status"] == 1){
                            $current_payment_method_wallet_type[] = $data["wallet_type"];
                        }
                    }

                    $deleted_wallet_types = array_diff($current_payment_method_wallet_type, $wallet_type_list);
                    $new_wallet_types = array_diff($wallet_type_list, $current_payment_method_wallet_type);

                    foreach($new_wallet_types as $wallet_type){
                        //  get external address
                        $external_address = $this->handle_set_crypto_payment_method($user_id, $wallet_type);
        
                        //  only call this function for it's new wallet type or to activate the status
                        $this->upsert_crypto_payment_method($user_id, $wallet_type, $external_address);
                    }

                    foreach($deleted_wallet_types as $wallet_type){
                        $data = $user_crypto_payment_method[$wallet_type];
                        $crypto_payment_method_id = $data["id"];
                        $this->delete_user_crypto_payment_method($crypto_payment_method_id);
                    }
                }catch(Exception $ex){
                    $error_code = $ex->getCode();
                    $error_message = $ex->getMessage();

                    $return_data = array("code" => 0, "message" => "FAILED");

                    if($error_code == 999){
                        $return_data["message_d"] = "Something went wrong. Please try again.";
                        $return_data["error_message"] = $error_message;
                    }else{
                        $return_data["message_d"] = $error_message;
                    }

                    return $return_data;
                }
            }
            elseif($sourceName == "web"){
                $wallet_type = $wallet_type_list;
                $validate_address_result = $xunCrypto->crypto_validate_address($external_address, $wallet_type, "external");

                if($validate_address_result["code"] == 1){
                    return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid address.", "errorCode" => -100);
                }

                $db->where('user_id',$user_id);
                $db->where('wallet_type', $wallet_type);
                $story_user_crypto_pm = $db->map('external_address')->ArrayBuilder()->get('xun_story_user_crypto_payment_method');

                if($story_user_crypto_pm){
                    foreach($story_user_crypto_pm as $pm_key => $pm_value){
                        if($external_address != $pm_value["external_address"]){
                            if($pm_value["status"] == 1){
                                $update_data = array(
                                    "status"=> 0,
                                    "updated_at" => $date
                                );
                                $db->where('id', $pm_value["id"]);
                                $db->update('xun_story_user_crypto_payment_method', $update_data);
                            }
                        }
                        
                    }
                   
                }

                if(!$story_user_crypto_pm[$external_address]){
                    $insertCryptoPaymentMethod = array(
                        "user_id" => $user_id,
                        "wallet_type"=> $wallet_type,
                        "external_address" => $external_address,
                        "status" => '1',
                        "created_at" => $date,
                        "updated_at" => $date,
                        
                    );

                    $db->insert('xun_story_user_crypto_payment_method', $insertCryptoPaymentMethod);
                }
                else{
                    if($story_user_crypto_pm[$external_address]["status"] == 0){
                        $pm_id = $story_user_crypto_pm[$external_address]["id"];
                        $updatePaymentMethod = array(
                            "status" => 1,
                            "updated_at" => $date,
                        );
                        $db->where('id', $pm_id);
                        $db->update('xun_story_user_crypto_payment_method',$updatePaymentMethod );
                    }
                }
            }
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Set Payment Method Successful");
        }else{
            if ($payment_type_id == '2'){
                $insert_payment_method = array(
                    'name' => $payment_name,
                    'payment_type' => $payment_type,
                    'country' => $country,
                    'image' => 'https://s3-ap-southeast-1.amazonaws.com/com.thenux.image/assets/xchange/banks/default_bank%403x.png',
                    'record_type' => 'user',
                    'status' => '1',
                    'sort_order' => '2'
                );
    
                $payment_method_id = $db->insert('xun_marketplace_payment_method', $insert_payment_method);
                if (!$payment_method_id)
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Set Payment Method Failed", 'developer_msg' => $db->getLastError());
            }
    
            $input_data = array(
                'user_id' => $user_id,
                'payment_method_id' => $payment_method_id,
                'account' => $account,
                'qr_code' => $qr_code,
                'account_holder' => $account_holder,
                'updated_at' => date('Y-m-d H:i:s')
            );
            $input_data['created_at'] = date('Y-m-d H:i:s');
            $insert_result = $db->insert('xun_user_story_fiat_payment_method', $input_data);
            if ($insert_result)
                return array("code" => 1, "message" => "SUCCESS", "message_d" => "Set Payment Method Successful");
            else
                return array("code" => 0, "message" => "FAILED", "message_d" => "Set Payment Method Failed");
        }
    }

    public function get_payment_method_details($params){
        $db = $this->db;

        $payment_id = trim($params['payment_id']);
        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $payment_type_id = trim($params['payment_type_id']);

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }

        $xun_user = $db->getOne('xun_user');
        if (!$xun_user)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found.", 'developer_msg' => "User not found");

        $user_id = $xun_user["id"];

        if($payment_type_id == '1'){
            $crypto_payment_method = $this->get_user_crypto_payment_method_by_id($payment_id);

            $data['id'] = $crypto_payment_method['id'];
            $data['address'] = $crypto_payment_method['external_address'];
            $data['wallet_type'] = $crypto_payment_method['wallet_type'];
        }else{

            $db->where('status', '1');
            $db->where('id', $payment_id);
            $db->where('user_id', $user_id);
            $fiat_payment_method = $db->getOne('xun_user_story_fiat_payment_method');
            if (!$fiat_payment_method)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Fiat Payment Method not found.", 'developer_msg' => "Fiat Payment Method not found");
    
            $payment_method_id = $fiat_payment_method['payment_method_id'];
            $db->where('id', $payment_method_id);
            $payment_method = $db->getOne('xun_marketplace_payment_method');
            if (!$payment_method)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Payment Method not found.", 'developer_msg' => "Payment Method not found");
    
            $payment_name = $payment_method['name'];
            $payment_country = ucfirst($payment_method['country']);
            $payment_type = $payment_method['payment_type'];
    
            $db->where('name', $payment_type, 'LIKE');
            $payment_type_id = $db->getValue('xun_payment_method_settings', 'id');
    
            if (!$payment_type_id)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Payment type ID not found.", 'developer_msg' => "Payment type ID not found");
    
            $data['payment_id'] = empty($fiat_payment_method['id']) ? "" : $fiat_payment_method['id'];
            $data['payment_type_id'] = $payment_type_id;
            $data['payment_name'] = empty($payment_name) ? "" : $payment_name;
            $data['account'] = empty($fiat_payment_method['account']) ? "" : $fiat_payment_method['account'];
            $data['account_holder'] = empty($fiat_payment_method['account_holder']) ? "" : $fiat_payment_method['account_holder'];
            $data['payment_type'] = empty($payment_type) ? "" : $payment_type;
            $data['qr_code'] = empty($fiat_payment_method['qr_code']) ? "" : $fiat_payment_method['qr_code'];
            $data['country'] = empty($payment_country) ? "" : $payment_country;
        }


        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Get Payment Method Details Successful", "data" => $data);
    }

    public function process_withdrawal($xun_story)
    {
        global $xunCurrency, $log, $xunXmpp;
        $db = $this->db;
        $setting = $this->setting;

        /**
         * update reference number
         * add status
         * prompt admin
         * prompt user
         */

        $date = date("Y-m-d H:i:s");
        $reference_id = $this->generate_reference_number();

        $story_id = $xun_story["id"];

        $update_data = [];
        $update_data["withdrawal_reference_number"] = $reference_id;
        $update_data["updated_at"] = $date;
        
        $db->where("id", $story_id);
        $copyDb = $db->copy();
        $updated = $db->update("xun_story", $update_data);

        if(!$updated){
            $log->write("\n" . date("Y-m-d H:i:s") . ": Story ID = $story_id. Error:  " . $db->getLastError());
            return;
        }
        
        // $xun_story = $copyDb->getOne('xun_story', "user_id, fund_collected, currency_id");
        if(!$xun_story){
            $log->write("\n" . date("Y-m-d H:i:s") . " -  Invalid Story ID : $story_id");
            return;
        }

        $user_id = $xun_story["user_id"];
        $fund_collected = $xun_story["fund_collected"];
        $fund_amount = $xun_story["fund_amount"];
        $currency_id = strtolower($xun_story["currency_id"]);
        $status = $xun_story["status"];

        $name = $this->get_fiat_currency_name($currency_id);

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
        $creditType = $decimal_place_setting["credit_type"];

        $fund_collected = $setting->setDecimal($fund_collected, $creditType);

        $db->where('story_type', "story");
        $db->where('story_id', $story_id);
        $story_result = $db->getOne('xun_story_updates', "title");
        $story_title = $story_result["title"];

        if($status == "expired"){
            $story_status = "story_expired";

            $obj = new stdClass();
            $obj->value = $fund_collected;
            $obj->name = strtoupper($name);
            $obj->story_id = $story_id;
            $obj->story_title = $story_title;
            
            //$this->insert_story_user_activity($user_id, $story_id, $story_status, $obj);
            $this->insert_story_notification($story_id, 0, $user_id, $story_status, $obj);
        }

        if(bccomp((string)$fund_collected, "0", 8) <= 0 ){
            //  no fund collected
            //  no withdrawal
            $withdrawal_status = "completed";
            $story_status = "withdrawal_completed";
            $has_withdrawal = 0;
        }else{
            // withdrawal in process
            $has_withdrawal = 1;
            $withdrawal_status = "in_process";
            $story_status = "withdrawal_processing";
        }

        $insert_data = array(
            "story_id" => $story_id,
            "status" => $withdrawal_status,
            "created_at" => $date
        );

        $row_id = $db->insert("xun_story_withdrawal", $insert_data);

        if($row_id){
            if($has_withdrawal){
                $obj = new stdClass();
                $obj->value = $fund_collected;
                $obj->name = strtoupper($name);
                $obj->story_id = $story_id;
                $obj->story_title = $story_title;
                
                $this->insert_story_user_activity($user_id, $story_id, $story_status, $obj);
                $this->insert_story_notification($story_id, 0, $user_id, $story_status, $obj);
            }
        }else{
            $log->write("\n" . date("Y-m-d H:i:s") . " - Story ID : $story_id. DB error - ". $db->getLastError());
            return;
        }

        // prompt admin
        $xun_user_service = new XunUserService($db);
        $owner_user = $xun_user_service->getUserDetailsByID($user_id);

        $owner_nickname = $owner_user["nickname"];
        $owner_username = $owner_user["username"];

        $uc_currency_id = strtoupper($currency_id);
        $date = date("Y-m-d H:i:s");

        $msg = "Story ID: $story_id\n";
        $msg .= "Story Title: $story_title";
        $msg .= "Story Status: ". ucfirst($status) . "\n"; //   completed or expired
        $msg .= "Fund Amount: $fund_amount $uc_currency_id\n";
        $msg .= "Fund Collected: $fund_collected $uc_currency_id\n";
        $msg .= "Withdrawal Amount: $fund_collected $uc_currency_id\n";
        $msg .= "Story owner: $owner_username\n";
        $msg .= "Story owner name: $owner_username\n";
        $msg .= "Time: $date\n";

        $tag = "Story Ended";
        $this->send_admin_notification($tag, $msg);
    }

    public function send_admin_notification($tag, $msg){
        global $xunXmpp, $xun_numbers;

        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $msg;
        $erlang_params["mobile_list"] = $xun_numbers;
        $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params, "thenux_story");
        return $xmpp_result;
    }
    
    public function generate_reference_number(){
        $db = $this->db;
        $general = $this->general;

        $rand_number = $general->generateRandomNumber(16);

        while(true){
            $db->where("withdrawal_reference_number", $rand_number);
            $xun_story = $db->getOne("xun_story", "id");

            if(!$xun_story)
                break;
        }

        return $rand_number;
    }

    public function get_withdrawal_details($params){
        global $xunCurrency;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $date = date("Y-m-d H:i:s");

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $story_id = trim($params["story_id"]);

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username cannot be empty", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "story_id cannot be empty", 'developer_msg' => "story_id cannot be empty");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }

        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found.", 'developer_msg' => "User not found.");
        }
        $user_id = $xun_user["id"];

        $db->where('user_id', $user_id);
        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story', "fund_collected,  status, currency_id, withdrawal_reference_number, withdrawal_processing_fee, updated_at");

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found.", 'developer_msg' => "Story not found.");
        }

        $db->where('story_id', $story_id);
        $db->orderBy('id', "ASC");
        $story_withdrawal = $db->get('xun_story_withdrawal');

        $db->where('status', '1');
        $db->where('user_id', $user_id);
        $user_bank = $db->getOne('xun_user_story_fiat_payment_method');

        if(!$user_bank){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User did not set a bank account.", 'developer_msg' => "User did not set a bank account.");
        }

        $withdrawal_fund_amount = $xun_story["fund_collected"];
        $withdrawal_reference_number = $xun_story["withdrawal_reference_number"] ? $xun_story["withdrawal_reference_number"] : ''; 
        $withdrawal_processing_fee = $xun_story["withdrawal_processing_fee"];
        $status = $xun_story["status"];
        $currency_id = strtolower($xun_story["currency_id"]);     
        $bank_account_number = $user_bank["account"];
        $bank_name = $user_bank["bank_name"];

        if($status == "active"){
            $status_name = "Story (Ongoing)";
        }
        else{
            $status_name = "Story (End)";
        }

        $status_listing = [];

        $status_data = array(
            "status" => $status_name,
            "date" => $general->formatDateTimeToIsoFormat($xun_story["updated_at"]),
        );

        $status_listing[] = $status_data;

        //get decimal place and round up the value
        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
        $creditType = $decimal_place_setting["credit_type"];
        $withdrawal_fund_amount = $setting->setDecimal($withdrawal_fund_amount, $creditType);
        $withdrawal_processing_fee = $setting->setDecimal($withdrawal_processing_fee, $creditType);

        $currency_name = $this->get_fiat_currency_name($currency_id);
        $upper_currency_name = strtoupper($currency_name);

        $is_completed = false;

        foreach($story_withdrawal as $key => $value){
            $withdrawal_status = $value["status"];
            $withdrawal_status_date = $general->formatDateTimeToIsoFormat($value["created_at"]);

            if($withdrawal_status == "in_process"){
                $status_name = "Withdrawal in process";
            }
            elseif($withdrawal_status == "transferring"){
                $status_name = "Transferring";
            }
            elseif($withdrawal_status == "completed"){
                $status_name = "Completed";
            }

            $withdrawal_status_data = array(
                "status" => $status_name,
                "date" => $withdrawal_status_date,
            );

            $is_completed = $withdrawal_status == "completed" ? 1 : $is_completed;

            $status_listing[]  = $withdrawal_status_data;
        }
        
        if($is_completed && count($story_withdrawal) == 1){
            $in_process_data = array(
                "status" => "Withdrawal in process",
                "date" => null
            );
            $transferring_data = array(
                "status" => "Transferring",
                "date" => null
            );

            $completed_data = end($status_listing);
            array_pop($status_listing);
            $status_listing[] = $in_process_data;
            $status_listing[] = $transferring_data;
            $status_listing[] = $completed_data;
        }
        $story_status["details"] = $status_listing;

        // if(!$withdrawal_status_listing){
        //     $withdraw_status["details"] = [];
        // }
        // else{
        //     $withdraw_status["details"] = $story_status;
        // }
       
        $withdraw_details = array(
            "withdrawal_fund_amount" => $withdrawal_fund_amount,
            "currency_name" => $upper_currency_name,
            "withdrawal_reference_number" => $withdrawal_reference_number,
            "withdrawal_processing_fee" => $withdrawal_processing_fee,
            "bank_name" => $bank_name,
            "bank_account_number" => $bank_account_number,
            "story_status" => $story_status ? $story_status : [],

        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Get Story Withdrawal Details", "data" => $withdraw_details);
    }

    public function get_story_details_web($params){
        global $xunCurrency;
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $id = $params["id"];

        if($id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required." /*Story id cannot be empty.*/);
        }

        $db->where('id', $id);
        $xun_story = $db->getOne('xun_story');

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found." /*Story not found.*/);
        }

        $user_id = $xun_story["user_id"];
        $category_id = $xun_story["category_id"];
        $currency_id = strtolower($xun_story["currency_id"]);

        $user_arr = [];
        $story_updates_id_arr = [];
        $updates_arr = [];
        array_push($user_arr, $user_id);

        $db->where('story_id', $id);
        $story_updates = $db->get('xun_story_updates');   

        foreach($story_updates as $story_key => $story_value){
            $story_updates_id = $story_value['id'];
            
            if(!in_array($story_updates_id, $story_updates_id_arr)){
                array_push($story_updates_id_arr, $story_updates_id);
            }
        }

        $db->where('story_updates_id', $story_updates_id_arr, "IN");
        $story_media = $db->get('xun_story_media', null, "story_updates_id,media_url, media_type");

        $db->where('a.id', $user_arr, "IN");
        $db->join("xun_user_details b", "a.id=b.user_id", "LEFT");
        $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user a', null, 'a.*, b.picture_url');

        $db->where('id', $category_id);
        $story_category = $db->getOne('xun_story_category');

        $currency_name = $this->get_fiat_currency_name($currency_id);

        $db->where('story_id', $id);
        $total_followers = $db->getValue('xun_story_favourite', count('id'));

        $db->where('story_id', $id);
        $total_share =  $db->getOne('xun_story_share', 'sum(count) as sum');
        
        if($total_followers == null){
            $total_followers = 0;
        }
      
        $username = $xun_user[$user_id]["username"] ? $xun_user[$user_id]["username"] : $xun_user[$user_id]["id"];
        $nickname = $xun_user[$user_id]["nickname"];
        $user_type = $xun_user[$user_id]["type"];
        $user_image_url = $xun_user[$user_id]["picture_url"];
        $category_type = $story_category["category"];
        $fund_amount = $xun_story["fund_amount"];
        $fund_collected = $xun_story["fund_collected"];
        $created_at = $xun_story["created_at"];
        $total_supporters = $xun_story["total_supporters"];

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
        $creditType = $decimal_place_setting["credit_type"];
        $fund_amount = $setting->setDecimal($fund_amount, $creditType);
        $fund_collected = $setting->setDecimal($fund_collected, $creditType);
        if($fund_amount){
            $supportedPercentage = number_format((float)($fund_collected/$fund_amount) *100,2, '.', '');
        }
        
        foreach($story_updates as $story_key => $story_value){
            $story_updates_id = $story_value["id"];
            $media_list = [];
            foreach($story_media as $media_key => $media_value){
                if($media_value["story_updates_id"] == $story_updates_id){
                    $media = array(
                        "media_url" => $media_value["media_url"],
                        "media_type" => $media_value["media_type"],
                    );
                    $media_list[] = $media;
                }
            }

            $temp_array = array(
                "title" => $story_value["title"],
                "description" => $story_value["description"],
                "created_at" => $story_value["created_at"],
            );

            if($story_value["story_type"] == "story"){
                $temp_array["media"] = $media_list;
                $story_arr = $temp_array;
            }
            else{
                $temp_array["updates_media"] = $media_list;
                $updates_arr[] = $temp_array;
            }
            unset($temp_array);
        }

        if($updates_arr){
            $columns = array_column($updates_arr, 'created_at');
            //sort the updates by descending order
            array_multisort($columns, SORT_DESC, $updates_arr);
        }
        
        $title = $story_arr["title"];
        $description = $story_arr["description"];

        $story_details = array(
            "id" => $id,
            "title" => $title,
            "description" => $description,
            "username" => $username,
            "nickname" => $nickname,
            "user_type" => $user_type,
            "user_image_url" => "",
            "picture_url" => $user_image_url ? $user_image_url : '',
            "category_type" => $category_type,
            "fund_amount" => $fund_amount,
            "fund_collected" => $fund_collected,
            "fund_collected_pct" => (string) $supportedPercentage,
            "currency_name" => strtoupper($currency_name),
            "total_supporters" => $total_supporters,
            "total_followers" => $total_followers,
            "total_share" => $total_share["sum"] ? (int)$total_share["sum"] : 0,
            "created_at" => $created_at,
            "media" => $story_arr["media"],
            "updates" => $updates_arr,
            
        );

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => "Story details.", 'data' => $story_details);        

    }

    public function web_main_story_page($params){
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        // $top_story_page_number = trim($params["top_story_page_number"]) ? $params["top_story_page_number"] : 0;
        $top_story_offset = trim($params["top_story_offset"]) ? $params["top_story_offset"] : 0;
        $top_story_page_size = trim($params["top_story_page_size"]) ? $params["top_story_page_size"] : 3;

        // $category_filter_page_number = trim($params["category_filter_page_number"]);
        $category_filter_offset = trim($params["category_filter_offset"]);
        $category_filter_page_size = trim($params["category_filter_page_size"]);
        $category_id = trim($params['category_id']);

        // $category_filter['page_number'] = $category_filter_page_number;
        $category_filter['offset'] = $category_filter_offset;
        $category_filter['page_size'] = $category_filter_page_size;
        $category_filter['category_id'] = $category_id;

        // $top_story_filter['page_number'] = $top_story_page_number;
        $top_story_filter['offset'] = $top_story_offset;
        $top_story_filter['page_size'] = $top_story_page_size;

        $db->where('status', '1');
        $story_category = $db->get('xun_story_category', null, 'id, category');

        $top_stories_data = $this->get_top_stories($top_story_filter);
        $category_filter_data = $this->get_story_by_category_web($category_filter);

        $top_stories_listing = $top_stories_data['data']['top_stories'];
        // print_r($category_filter_data['data']);
        $story_data['story_category'] = $story_category;
        $story_data['top_stories'] = $top_stories_listing ? $top_stories_listing : [];
        $story_data['category_filter_story'] = $category_filter_data['data']['categorized_stories'];
        // print_r($story_data);

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => "Main Story Page.", 'data' => $story_data);        
    }

    private function get_top_stories ($params){
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        // $page_number = intval($params["page_number"]) ? $params["page_number"] : 1;
        $offset = intval($params["offset"]) ? $params["offset"] : 0;
        $page_size = intval($params["page_size"]) ? $params["page_size"] : 3;
        $totalRecord = 0;

        if($page_size < 3)
            $page_size = 3;

        // if ($page_number < 1) {
        //     $page_number = 1;
        // }
        if ($offset < 0) {
            $offset = 0;
        }
        // $start_limit = ($page_number - 1) * $page_size;
        // $limit = array($start_limit, $page_size);
        $limit = array($offset, $page_size);

        //get active story
        
        $db->where('recommended', '1');
        $db->where('status', 'active');
        $db->orderBy('id', 'DESC');
        $copyDb = $db->copy();
        $active_story = $db->map('id')->ArrayBuilder()->get('xun_story', $limit);
        if(!$active_story)
            return array('code' => 0, 'message' => 'FAILED', 'message_d' => "No story found", 'data' => '');        

        foreach($active_story as $story){
            $story_id_ary[] = $story['id'];
        }

        $totalRecord = $copyDb->getValue("xun_story", "count(id)");

        //get story updates
        $db->where('story_type', 'story');
        $db->where('story_id', $story_id_ary, 'IN');
        $story_updates = $db->map('id')->ArrayBuilder()->get('xun_story_updates', null, 'id, story_id, title, description');
        if(!$story_updates)
            return array('code' => 0, 'message' => 'FAILED', 'message_d' => "No updates found", 'data' => '');        


        foreach($story_updates as $updates){
            $story_updates_id_ary[] = $updates['id'];
        }

        $db->orderBy('created_at', 'DESC');
        $db->where('story_updates_id', $story_updates_id_ary, 'IN');
        $story_media = $db->map('story_updates_id')->ArrayBuilder()->get('xun_story_media', null, 'id, story_updates_id, media_url, media_type');
        if(!$story_media)
            return array('code' => 0, 'message' => 'FAILED', 'message_d' => "No media found", 'data' => '');        


        foreach($story_media as $media){
            $story_updates[$media['story_updates_id']]['media_url'] = $media['media_url'];
            $story_updates[$media['story_updates_id']]['media_type'] = $media['media_type'];
        }

        foreach($story_updates as $updates){
            $top_stories[$updates['story_id']]['title'] = $updates['title'];
            $top_stories[$updates['story_id']]['description'] = $updates['description'];
            $top_stories[$updates['story_id']]['media_url'] = $updates['media_url'];
            $top_stories[$updates['story_id']]['media_type'] = $updates['media_type'];
        }

        foreach($active_story as $story){
            $top_stories[$story['id']]['story_id'] = $story['id'];
            $top_stories[$story['id']]['fund_amount'] = $story['fund_amount'];
            $top_stories[$story['id']]['fund_collected'] = $story['fund_collected'];
            $top_stories[$story['id']]['currency_id'] = $story['currency_id'];
            $top_stories[$story['id']]['status'] = $story['status'];
        }

        $story_data['top_stories'] = array_values($top_stories);
        // $story_data['top_stories']["totalRecord"] = $totalRecord;
        // $story_data['top_stories']["numRecord"] = $page_size;
        // $story_data['top_stories']["totalPage"] = ceil($totalRecord/$page_size);
        // $story_data['top_stories']["pageNumber"] = $page_number;
        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => "Top Stories List.", 'data' => $story_data);        
    }

    private function get_story_by_category_web($params){
        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        $category_id = intval($params['category_id']);
        // $page_number = intval($params["page_number"]) ? $params["page_number"] : 1;
        $offset = intval($params["offset"]) ? $params["offset"] : 0;
        $page_size = intval($params["page_size"]) ? $params["page_size"] : 6;
        $totalRecord = 0;

        // if ($page_number < 1) {
        //     $page_number = 1;
        // }
        if ($offset < 0) {
            $offset = 0;
        }
        // $start_limit = ($page_number - 1) * $page_size;
        // $limit = array($start_limit, $page_size);
        $limit = array($offset, $page_size);

        if($page_size < 6)
            $page_size = 6;

        // print_r($category_id);
        if ($category_id){
            if($category_id < 0){
                return array('code' => 0, 'message' => 'FAILED', 'message_d' => "Invalid Category ID", 'data' => '');        
            }
            $db->where('category_id', $category_id);
        }
       
        $db->where('status', 'active');
        $db->orderBy('id', 'DESC');
        $copyDb = $db->copy();
        $active_story = $db->map('id')->ArrayBuilder()->get('xun_story', $limit);
        // if(!$active_story)
        //     return array('code' => 0, 'message' => 'FAILED', 'message_d' => "No story found", 'data' => '');        

        foreach($active_story as $story){
            $story_id_ary[] = $story['id'];
        }

        $totalRecord = $copyDb->getValue("xun_story", "count(id)");
        // print_r($totalRecord);

        //get story updates
        $db->where('story_type', 'story');
        $db->where('story_id', $story_id_ary, 'IN');
        $story_updates = $db->map('id')->ArrayBuilder()->get('xun_story_updates', null, 'id, story_id, title, description');
        if(!$story_updates)
            return array('code' => 0, 'message' => 'FAILED', 'message_d' => "No updates found", 'data' => '');        


        foreach($story_updates as $updates){
            $story_updates_id_ary[] = $updates['id'];
        }

        $db->orderBy('created_at', 'ASC');
        $db->where('story_updates_id', $story_updates_id_ary, 'IN');
        $story_media = $db->map('story_updates_id')->ArrayBuilder()->get('xun_story_media', null, 'id, story_updates_id, media_url, media_type');
        if(!$story_media)
            return array('code' => 0, 'message' => 'FAILED', 'message_d' => "No media found", 'data' => '');        

        foreach($active_story as $story){
            
                $categorized_stories[$story['id']]['story_id'] = $story['id'];
                $categorized_stories[$story['id']]['category_id'] = $story['category_id'];
                $categorized_stories[$story['id']]['fund_amount'] = $story['fund_amount'];
                $categorized_stories[$story['id']]['fund_collected'] = $story['fund_collected'];
                $categorized_stories[$story['id']]['currency_id'] = $story['currency_id'];
                $categorized_stories[$story['id']]['status'] = $story['status'];
                $categorized_stories[$story['id']]['last_donation'] = '';
        }

        foreach($story_media as $media){
            $story_updates[$media['story_updates_id']]['media_url'] = $media['media_url'];
            $story_updates[$media['story_updates_id']]['media_type'] = $media['media_type'];
        }

        foreach($story_updates as $updates){
            $categorized_stories[$updates['story_id']]['title'] = $updates['title'];
            $categorized_stories[$updates['story_id']]['description'] = $updates['description'];
            $categorized_stories[$updates['story_id']]['media_url'] = $updates['media_url'];
            $categorized_stories[$updates['story_id']]['media_type'] = $updates['media_type'];
        }

        $db->where('story_id', $story_id_ary, 'IN');
        $db->where('status', 'success');
        $db->where('transaction_type', 'donation');
        $db->orderBy('updated_at', 'ASC');
        $last_donation = $db->map('story_id')->ArrayBuilder()->get('xun_story_transaction');
        foreach($last_donation as $donation){
            $categorized_stories[$donation['story_id']]['last_donation'] = $donation['updated_at'];
        }


        $story_data['categorized_stories']['stories'] = array_values($categorized_stories);
        $story_data['categorized_stories']["totalRecord"] = $totalRecord;

        if($totalRecord < $page_size ){
            if($offset && $totalRecord > 0){
                $totalRecord = $totalRecord - $offset;
            }
            $story_data['categorized_stories']["numRecord"] = $totalRecord;
        }
        else{
            $story_data['categorized_stories']["numRecord"] = (int) $page_size;
        }
        $story_data['categorized_stories']["totalPage"] = ceil($totalRecord / $page_size);
        // $story_data['categorized_stories']["numRecord"] = $page_size;
        // $story_data['categorized_stories']["totalPage"] = ceil($totalRecord/$page_size);
        // $story_data['categorized_stories']["pageNumber"] = $page_number;
        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => "Category Story", 'data' => $story_data);
    }
    
    public function app_share_story($params){
        $db = $this->db;
        $setting = $this->setting;

        $username = trim($params["username"]);
        $story_id = trim($params["story_id"]);
        $business_id = trim($params["business_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username is required.", 'developer_msg' => "- Username is required.");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required.", 'developer_msg' => "Story ID is required.");
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];
        $user_type = $xun_user["register_site"] == 'nuxstory' ? 'NuxStory' : 'TheNux';

        if($business_id){
            //  check if user is business employee

            $xun_business_service = new XunBusinessService($db);
            
            $is_business_employee = $xun_business_service->isBusinessEmployee($business_id, $username);

            if($is_business_employee == false){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid business ID.");
            }

            $user_id = $business_id;
        }

        try{
            $token = $this->get_shared_story_token($user_id, $story_id);
        }catch(Exception $ex){
            $error_message = $ex->getMessage();
            return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $error_message);
        }

        try{
            $story_share_link = $this->get_story_share_link($token);
        }catch(Exception $ex){
            $error_message = $ex->getMessage();
            return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $error_message);
        }

        $return_data = [];
        $return_data["share_link"] = $story_share_link;

        $db->where('story_id', $story_id);
        $db->where('story_type', 'story');
        $story_updates = $db->getOne('xun_story_updates');
        $title = $story_updates["title"];
        $this->send_story_share_notification($nickname, $username, $user_type, 'App', $title);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "data" => $return_data);
    }

    public function web_share_story($params){
        $db = $this->db;
        $setting = $this->setting;

        $user_id = trim($params["user_id"]);
        $story_id = trim($params["story_id"]);
        // $business_id = trim($params["business_id"]);

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required", 'developer_msg' => "story id cannot be empty");
        }

        // if($username){
        //     $xun_user_service = new XunUserService($db);
    
        //     $xun_user = $xun_user_service->getUserByUsername($username);
    
        //     if(!$xun_user){
        //         return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        //     }
    
        //     $user_id = $xun_user["id"];
        // }else{
        //     $user_id = 0;
        // }

        if($user_id){
            $xun_user = $this->validate_user_id($user_id);

            if(isset($xun_user["code"]) && $xun_user["code"] == 0){
                return $xun_user;
            }

            $nickname = $xun_user["nickname"];
            $mobile = $xun_user["username"];
            $user_type = $xun_user["register_site"] == 'nuxstory' ? 'NuxStory' : 'TheNux';
        }  

        try{
            $token = $this->get_shared_story_token($user_id, $story_id);
        }catch(Exception $ex){
            $error_message = $ex->getMessage();
            return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $error_message);
        }

        try{
            $story_share_link = $this->get_story_share_link($token);
        }catch(Exception $ex){
            $error_message = $ex->getMessage();
            return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $error_message);
        }

        $return_data = [];
        $return_data["share_link"] = $story_share_link;

        $db->where('story_id', $story_id);
        $db->where('story_type', 'story');
        $story_updates = $db->getOne('xun_story_updates');
        $title = $story_updates["title"];
        $this->send_story_share_notification($nickname, $username, $user_type, 'Web', $title);


        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success", "data" => $return_data);
    }

    private function get_story_share_link($token){
        $setting = $this->setting;

        $story_url = $setting->systemSetting["storyShareLink"];

        if($story_url == ''){
            throw new Exception("Undefined storyShareLink");
        }
        return $story_url . "?sid=" . $token;

    }

    private function get_shared_story_token($user_id, $story_id){
        $db = $this->db;
        $general = $this->general;

        $db->where("user_id", $user_id);
        $db->where("story_id", $story_id);

        $share_story_data = $db->getOne("xun_story_share", "id, token");

        if($share_story_data){
            $update_data = [];
            $update_data["count"] = $db->inc(1);

            $db->where("id", $share_story_data["id"]);
            $db->update("xun_story_share", $update_data);

            $db->where('story_id', $story_id);
            $db->where('story_type', "story");
            $story_updates = $db->getOne('xun_story_updates', 'id, title');
            $story_title = $story_updates['title'];
            $info->story_title = $story_title;

            $this->insert_story_user_activity($user_id, $story_id, "share_story", $info);
            return $share_story_data["token"];
        }

        do {
            $token = $general->generateAlpaNumeric(8);

            $db->where("token", $token);
            $share_token = $db->getOne("xun_story_share", "id");

            if (!$share_token) {
                $token_valid = true;
            }
        } while (!$token_valid);

        $date = date("Y-m-d H:i:s");
        $insert_data = array(
            "user_id" => $user_id,
            "story_id" => $story_id,
            "token" => $token,
            "count" => 1,
            "created_at" => $date
        );

        $row_id = $db->insert("xun_story_share", $insert_data);

        if(!$row_id){
            throw new Exception($db->getLastError());
        }

        return $token;
    }

    private function get_total_story_share_count($story_id){
        $db = $this->db;

        $db->where("story_id", $story_id);
        $count = $db->getValue("xun_story_share", "sum(count)");
        return $count;
    }

    public function web_get_comment_list($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $story_id = $params["id"];
        $last_id = $params["last_id"] ? $params["last_id"] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 10;

        if ($story_id == '') {
            //$this->get_translation_message('E00006')
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  "ID is required.");
        }
        
        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story', "currency_id");

        $limit = array($last_id, $page_size);
        $db->where('story_id', $story_id);
        $db->orderBy('id', "DESC");
        $copyDb =$db->copy();
        $story_comment = $db->get('xun_story_comment', $limit);

        $totalRecord = $copyDb->getValue('xun_story_comment', "count(id)");
        $comment_list = [];
        if($story_comment){
            $user_id_arr = [];
            foreach($story_comment as $key => $value){
                $user_id = $value["user_id"];

                if(!in_array($user_id, $user_id_arr)){
                    array_push($user_id_arr, $user_id);
                }
            }

            $db->where('user_id', $user_id_arr, "IN");
            $donation_sum = $db->map('user_id')->ArrayBuilder()->get('xun_story_transaction', null, "user_id, sum(value)");

            $db->where('a.id', $user_id_arr, "IN");
            $db->join('xun_user_details b', "a.id=b.user_id", "LEFT");
            $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user a', null, "a.id,a.nickname,a.username, b.picture_url");

            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($xun_story["currency_id"], true);
            $creditType = $decimal_place_setting["credit_type"];

            $currency_name = $this->get_fiat_currency_name($xun_story["currency_id"]);
            
            foreach($story_comment as $key => $value){
                $comment_user_id = $value["user_id"];
                $created_at = $value["created_at"];
                $message = $value["comment"];
                
                //commenter nickname
                $user_nickname = $xun_user[$comment_user_id]["nickname"];
                $user_picture_url = $xun_user[$comment_user_id]["picture_url"];
                //the total donation amount of the person who comment
                $commenter_total_donation =  $donation_sum[$comment_user_id]? $setting->setDecimal($donation_sum[$comment_user_id],$creditType) : "0";

                $comment_array = array(
                    "nickname" => $user_nickname,
                    "message" => $message,
                    "picture_url" => $user_picture_url ? $user_picture_url : '',
                    "donation_value" => $commenter_total_donation,
                    "currency_name" => strtoupper($currency_name),
                    "created_at" => $created_at,
                );
                $comment_list[] = $comment_array;
            }
        }
        $numRecord = count($comment_list);
        $returnData["result"] = $comment_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $numRecord;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        $returnData["last_id"] = $last_id + $numRecord;
        
        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => "Comment List", 'data' => $returnData);

    }

    public function web_get_transaction_history($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $story_id = $params["id"];
        $last_id = $params["last_id"] ? $params["last_id"] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 5;

        if ($story_id == '') {
            //$this->get_translation_message('E00006')
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  "ID is required.");
        };

        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story', "currency_id");

        $limit = array($last_id, $page_size);
        $db->where('story_id', $story_id);
        $db->where('status', "success");
        $db->orderBy('id', "DESC");
        $copyDb= $db->copy();
        $story_transaction = $db->get('xun_story_transaction', $limit);

        $totalRecord = $copyDb->getValue('xun_story_transaction', "count(id)");
        
        $transaction_list = [];
        if($story_transaction){
            $user_id_arr = [];
            foreach($story_transaction as $key=>$value){
                $user_id = $value["user_id"];
    
                if(!in_array($user_id, $user_id_arr)){
                    array_push($user_id_arr, $user_id);
                }
            }  
            $db->where('a.id', $user_id_arr, "IN");
            $db->join("xun_user_details b", "a.id=b.user_id","LEFT");
            $xun_user = $db->map('id')->ArrayBuilder()->get('xun_user a', null, "a.id, a.nickname, a.type, b.picture_url");
    
            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($xun_story["currency_id"], true);
            $creditType = $decimal_place_setting["credit_type"];

            $currency_name = $this->get_fiat_currency_name($xun_story["currency_id"]);

            foreach($story_transaction as $transaction_key =>$transaction_value){
                $transaction_user_id = $transaction_value["user_id"];
                $transaction_nickname = $xun_user[$transaction_user_id]["nickname"];
                $user_picture_url = $xun_user[$transaction_user_id]["picture_url"];
                $donation_value = $setting->setDecimal($transaction_value["value"], $creditType);
                $transaction_arr = array(
                    "nickname" => $transaction_nickname,
                    "picture_url" => $user_picture_url  ? $user_picture_url : '',
                    "donation_value" => $donation_value,
                    "currency_name" => strtoupper($currency_name),
                    "created_at" => $transaction_value["created_at"],
                );
                $transaction_list[] = $transaction_arr;
            }
        }
        $numRecord = count($transaction_list);
        $returnData["result"] = $transaction_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $numRecord;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        $returnData["last_id"] = $last_id + $numRecord;

        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => "Transaction History", 'data' => $returnData);
    }

    public function get_fiat_currency_name($currency_id, $is_upper_case = null){
        $db = $this->db;
    
        $lower_currency_id = strtolower($currency_id);
        $db->where('fiat_currency_id', $lower_currency_id);
        $xun_fiat = $db->getOne('xun_fiat', "name");

        if($is_upper_case){
            $currency_name = strtoupper($xun_fiat["name"]);
        }
        else{
            $currency_name = $xun_fiat["name"];
        }

        return $currency_name;
    }
    
    private function get_story_details_from_token($token){
        $db = $this->db;

        $db->where("token", $token);
        $shared_story = $db->getOne("xun_story_share", "id, story_id, user_id");

        return $shared_story;
    }

    private function get_user_fiat_payment_method_list($params){

        $db = $this->db;
        $user_id = $params['user_id'];

        $db->where('status', '1');
        $db->where('user_id', $user_id);
        $fiat_payment_method = $db->get('xun_user_story_fiat_payment_method');

        if ($fiat_payment_method){
            foreach($fiat_payment_method as $fiat_info){
                $user_bank_ids[] = $fiat_info['id'];
                if ($fiat_info['payment_method_id'] != 0 ){
                    $payment_method_ids[] = $fiat_info['payment_method_id'];
                }
            }
            if(count($payment_method_ids) != 0){
                $db->where('id', $payment_method_ids, 'IN');
                $payment_method_info = $db->map('id')->ArrayBuilder()->get('xun_marketplace_payment_method', null, 'id, name, image, payment_type, country');
                if (empty($payment_method_info)){
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Payment method not found.", 'developer_msg' => "Payment method not found.");
                }
        
                $payment_method_type_info = $this->get_payment_method_type_info($params);
                $payment_method_type_data = $payment_method_type_info['data'];
                foreach($payment_method_type_data as $payment_method){
                    $payment_type_id[strtolower($payment_method['name'])] = $payment_method['id'];
                    $payment_method_qr[strtolower($payment_method['name'])] = $payment_method['qr_code'];
                }
        
                $i = 0;
                foreach($fiat_payment_method as $fiat_info){
                    // $data[] = $bank_info;
                    $data[$i]['id'] = $fiat_info['id'];
                    $data[$i]['payment_name'] = $payment_method_info[$fiat_info['payment_method_id']]['name'];
                    $data[$i]['account'] = $fiat_info['account'];
                    $data[$i]['account_holder'] = $fiat_info['account_holder'];
                    $data[$i]['country'] = ucfirst($payment_method_info[$fiat_info['payment_method_id']]['country']);
                    $data[$i]['image'] = $payment_method_info[$fiat_info['payment_method_id']]['image'];
                    $data[$i]['payment_type'] = $payment_method_info[$fiat_info['payment_method_id']]['payment_type'];
                    $payment_type = strtolower($data[$i]['payment_type']);
                    $data[$i]['payment_type_id'] = $payment_type_id[$payment_type];
                    $data[$i]['qr_code'] = $payment_method_qr[$payment_type];
                    $i++;
                }
            }
        }else{
            $data = [];
        }
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Get Bank Account List Successful", "data" => $data);
    }

    public function get_payment_method_listing($params){
        global $xunMarketplace;
        $db = $this->db;

        $username = trim($params['username']);

        if(!$username)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username is required.", 'developer_msg' => "username cannot be empty");

        return $xunMarketplace->get_marketplace_payment_method_listing_v2($params);
    }



    public function web_add_story_comment($params, $user_agent){
        $db = $this->db;

        $user_id = trim($params["user_id"]);
        $business_id = trim($params["business_id"]);
        $story_id = trim($params["story_id"]);
        $comment = trim($params["comment"]);

        if($user_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  "User ID is required.");
        }

        if($story_id == ''){
            //$this->get_translation_message('E00006')
            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  "Story ID is required.");
        }

        if($comment == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Comment is required.");
        }

        if(strlen($comment) > 8000){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Exceeded the limitation of comment.", 'developer_msg' => "Comment cannot be more than 8000 characters");
        }

        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story', 'id,user_id');

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story is required.", 'developer_msg' => "Story is required.");
        }

        $story_user_id = $xun_story['user_id'];

        if($user_id){
            $db->where('id', $user_id);
            $xun_user = $db->getOne('xun_user');

            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "User is required.");
            }

            $user_id = $xun_user["id"];
            $nickname = $xun_user["nickname"];
            $mobile = $xun_user["username"] ? $xun_user["username"] : '';
            $user_type = $xun_user["register_site"] == "nuxstory" ? 'NuxStory' : 'TheNux';
            
        }

        if($business_id){
            $db->where('id', $business_id);
            $db->where('type', "business");
            $xun_user = $db->getOne('xun_user', 'id');

            if(!$xun_user){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business not found.");
            }
            $user_id = $xun_user["id"];
        }

        $db->where('story_id', $story_id);
        $db->orderBy('created_at', "DESC");
        $xun_story_comment = $db->getOne('xun_story_comment');

        $insertComment = array(
            "user_id" => $user_id,
            "story_id" => $story_id,
            "comment" => $comment,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $comment_id = $db->insert('xun_story_comment', $insertComment);

        if(!$comment_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Failed to add comment.");
        }

        if($user_id != 0){
            $this->insert_story_user_activity($user_id, $comment_id, "comment");
        }

        $last_comment_user_id = $xun_story_comment["user_id"];

        $db->where('story_id', $story_id);
        $db->where('story_type', "story");
        $story_updates = $db->getOne('xun_story_updates');

        $db->where('id', $story_user_id);
        $story_user_result = $db->getOne('xun_user', 'id, username');
        $story_username = $story_user_result["username"] ? $story_user_result["username"] :  $story_user_result['id'];
        $story_title = $story_updates["title"];

        if($last_comment_user_id != 0){
        
            $info->story_title =  $story_title ;
            $info->story_username = $story_username;
    
            if( $user_id != $last_comment_user_id ){
                $this->insert_story_notification($story_id, $user_id, $last_comment_user_id, "comment", $info);
            }
        }

        $this->send_comment_notification($nickname, $mobile, $user_agent, $user_type, 'Web', $story_title, $comment);
        
        return array('code' => 1, 'message' => 'SUCCESS', 'message_d' => "Add Comment Successful");
    }

    public function app_get_story_payment_method_list($params){
        $db = $this->db;

        $username = trim($params["username"]);
        $business_id = trim($params["business_id"]);
        $story_id = trim($params['story_id']);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }

        $xun_user = $db->getOne('xun_user');
        if (!$xun_user)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found.", 'developer_msg' => "User not found");
        
        $newParams = $params;

        return $this->get_story_payment_method_list($newParams);
    }

    public function get_story_payment_method_list($params){
        $db = $this->db;

        $story_id = trim($params['story_id']);

        if(empty($story_id))
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required.", 'developer_msg' => "Story ID is required.");

        $db->where('story_id', $story_id);
        $story_payment_method = $db->get('xun_story_payment_method');
        // if (empty($story_payment_method)){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Payment method not found for this story.", 'developer_msg' => "Payment method not found for this story.");
        // }

        foreach($story_payment_method as $payment_method_info){
            // $wallet_type = strtolower($payment_method_info['wallet_type']);
            $payment_method_id = $payment_method_info['payment_method_id'];
            // if ($wallet_type != ''){
            //     $payment_method_wallet_types[] = $wallet_type ? $wallet_type : "";
            // }
            if ($payment_method_id != ''){
                $payment_method_ids[] = $payment_method_id;
            }
        }

        if (!empty($payment_method_ids)){
            $db->where('id', $payment_method_ids, 'IN');
            $fiat_payment_method_info = $db->map('id')->ArrayBuilder()->get('xun_marketplace_payment_method', null, 'id, name, image, country, payment_type');    
            if(empty($fiat_payment_method_info))
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Fiat Payment method not found.", 'developer_msg' => "Fiat Payment method not found.");
        }

        $payment_method_settings = $db->map("name")->ArrayBuilder()->get("xun_payment_method_settings", null, "id, name, qr_code");

        $i = 0;
        $fiat = [];
        foreach($story_payment_method as $story_payment){
                $story_fiat_data =  $fiat_payment_method_info[$story_payment['payment_method_id']];
                
                $fiat[$i]['id'] = $story_payment['id'];
                $fiat[$i]['bank_name'] = $fiat_payment_method_info[$story_payment['payment_method_id']]['name'];
                $fiat[$i]['account'] = $story_payment['bank_account'];
                $fiat[$i]['account_holder'] = $story_payment['bank_holder'];
                $fiat[$i]['qr_code'] = $story_payment['qr_code'] ? $story_payment['qr_code'] : "";
                $fiat[$i]['country'] = ucfirst($fiat_payment_method_info[$story_payment['payment_method_id']]['country']);
                $fiat[$i]['image'] = $fiat_payment_method_info[$story_payment['payment_method_id']]['image'];

                $story_payment_method_data = $payment_method_settings[$story_fiat_data["payment_type"]];
                $fiat[$i]['payment_type_id'] = $story_payment_method_data["id"];
                $fiat[$i]['payment_type'] = $story_payment_method_data["name"];
                $fiat[$i]['has_qr_code'] = $story_payment_method_data["qr_code"];
            $i++;
        }

        //  cryptocurrency get from xun_story_payment_gateway
        $crypto_pg_list = $this->get_story_payment_gateway_list($story_id);
        $cryptocurrency_list = [];

        if(!empty($crypto_pg_list)){
            $wallet_types = array_column($crypto_pg_list, "wallet_type");
            $db->where("currency_id", $wallet_types, "IN");
            $wallet_types_info = $db->map("currency_id")->ArrayBuilder()->get("xun_marketplace_currencies", null, "currency_id, name, image");

            foreach($crypto_pg_list as $crypto_pg){
                $pg_wallet_type = $crypto_pg["wallet_type"];
                $wallet_type_info = $wallet_types_info[$pg_wallet_type];

                $crypto_data = [];
                $crypto_data["id"] = $crypto_pg["id"];
                $crypto_data["wallet_type"] = $pg_wallet_type;
                $crypto_data["image"] = $wallet_type_info["image"];
                $crypto_data["address"] = $crypto_pg["crypto_address"];
    
                $cryptocurrency_list[] = $crypto_data;
            }
        }

        $data['fiat'] = array_values($fiat) ? array_values($fiat) : [];
        $data['cryptocurrency'] = $cryptocurrency_list;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Get Story Bank Account List Successful", "data" => $data);
    }

    public function app_story_setting_get_payment_method($params){
        $db = $this->db;
        global $xunMarketplace;

        $username = trim($params["username"]); //for app
        $business_id = trim($params["business_id"]);
        $user_id = trim($params["user_id"]);//for web
        
        if ($username == '' && $sourceName == 'app') { 
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "username cannot be empty");
        }
        elseif($user_id == '' && $sourceName == 'web'){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "user_id is required.", 'developer_msg' => "user_id cannot be empty");
        }

        if($user_id){
            $xun_user = $this->validate_user_id($user_id);
        }
        elseif($username){
            $xun_user = $this->validate_username($username, $business_id);
        }
        
        if(isset($xun_user["code"]) && $xun_user["code"] == 0){
            return $xun_user;
        }

        $user_id = $xun_user["id"];

        $payment_method_list = $this->get_payment_method_type_info($params);
        if (!$payment_method_list)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Payment method list retrive failed.", 'developer_msg' => "Payment method list retrive failed");

        $payment_method_data = $payment_method_list['data'];
        // print_r($payment_method_data);
        foreach($payment_method_data as $payment_method){
            $payment_info = array(
                "id" => $payment_method['id'] ? $payment_method['id'] : "",
                "name" => $payment_method['name'],
                "qr_code" => $payment_method['qr_code']
            );
            if ($payment_method['name'] == 'Cryptocurrency'){
                $crypto_payment_type_id = $payment_method['id'];
            }
            $payment_type[] = $payment_info;
            // $payment_type['name'] = $payment_method['name'];
            // $payment_type['qr_code'] = $payment_method['qr_code'];
        }
        // $payment_type = array_values(array_unique($payment_type));

        $fiatParams['user_id'] = $user_id;
        $user_fiat_list = $this->get_user_fiat_payment_method_list($fiatParams);
        if (!$user_fiat_list)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User bank list retrive failed.", 'developer_msg' => "User bank list retrive failed");
        $user_fiat_data = $user_fiat_list['data'];

        $user_crypto_list = $this->get_user_crypto_payment_method_list_display($user_id, "a.id, a.wallet_type, b.name, b.symbol, b.image");

        $user_crypto_list_return = [];
        foreach($user_crypto_list as $user_crypto){
            $user_crypto_data = [];
            $user_crypto_data["id"] = $user_crypto["id"];
            $user_crypto_data["wallet_type"] = $user_crypto["wallet_type"];
            $user_crypto_data["payment_name"] = $user_crypto["name"];
            $user_crypto_data["symbol"] = $user_crypto["symbol"];
            $user_crypto_data["image"] = $user_crypto["image"];
            $user_crypto_data['payment_type_id'] = $crypto_payment_type_id;
            $user_crypto_list_return[] = $user_crypto_data;
        }
        
        $cryptocurrency_list = $this->get_supported_cryptocurrency_details();

        $return_data['payment_type'] = $payment_type;
        $return_data['user_payment_list'] = $user_fiat_data ? $user_fiat_data : [];
        $return_data['user_crypto_payment_list'] = $user_crypto_list_return;
        $return_data['cryptocurrrency_list'] = $cryptocurrency_list;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Get Story Setting Payment Method List Successful", "data" => $return_data);
    }

    public function get_payment_method_type_info($params){
        $db = $this->db;

        $db->where('status', '1');
        $payment_method_settings = $db->get('xun_payment_method_settings');
        if($payment_method_settings)
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Get Payment Method Setting Successful", "data" => $payment_method_settings);
    }

    public function set_crypto_payment_method($params){
        $user_id = $params["user_id"];
        $wallet_type = $params["wallet_type"];

        try{
            $external_address = $this->handle_set_crypto_payment_method($user_id, $wallet_type);
            $this->upsert_crypto_payment_method($user_id, $wallet_type, $external_address);
        }catch(Exception $ex){
            $error_code = $ex->getCode();
            $error_message = $ex->getMessage();

            $return_data = array("code" => 0, "message" => "FAILED");

            if($error_code == 999){
                $return_data["message_d"] = "Something went wrong. Please try again.";
                $return_data["error_message"] = $error_message;
            }else{
                $return_data["message_d"] = $error_message;
            }

            return $return_data;
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");

    }

    public function delete_crypto_payment_method($params){
        $user_id = $params["user_id"];
        $payment_method_id = $params["payment_method_id"];

        $crypto_payment_method = $this->get_user_crypto_payment_method_by_id($payment_method_id);

        if(!$crypto_payment_method){
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid payment method ID.");
        }
        if($crypto_payment_method["user_id"] != $user_id){
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid payment method ID.");
        }
        if($crypto_payment_method["status"] == 0){
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
        }

        $this->delete_user_crypto_payment_method($payment_method_id);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");

    }

    private function handle_set_crypto_payment_method($user_id, $wallet_type){
        global $xunCoins, $xunCrypto;
        $db = $this->db;

        $coin_setting_type = "is_story";
        $is_story_coin = $xunCoins->checkCoinSetting($coin_setting_type, $wallet_type);

        if(!$is_story_coin){
            throw new Exception("Invalid cryptocurrency.");
        }

        /**
         * set user's wallet type, external address
         * check if user have external address created
         */

        $xun_user_service = new XunUserService($db);
        
        $user_address_data = $xun_user_service->getActiveInternalAddressByUserID($user_id);
        
        if(!$user_address_data){
            throw new Exception("Please create a wallet before continuing.");
        }

        $internal_address = $user_address_data["address"];

        $user_address_obj = new stdClass();
        $user_address_obj->internalAddress = $internal_address;
        $user_address_obj->walletType = $wallet_type;

        $external_address_data = $xun_user_service->getCryptoExternalAddressByInternalAddressAndWalletType($user_address_obj);

        if($external_address_data){
            $external_address = $external_address_data["external_address"];
        }
        else{
            //  generate new external address from bc
            $crypto_result = $xunCrypto->crypto_bc_create_multi_wallet($internal_address, $wallet_type);

            if($crypto_result["status"] == "ok"){
                $crypto_data = $crypto_result["data"];
                $external_address = $crypto_data["address"];

                if(!$external_address){
                    throw new Exception("Error creating external address", 999);
                }

                //  save external address as user's external address

                $user_address_obj->externalAddress = $external_address;

                $xun_user_service->insertCryptoExternalAddress($user_address_obj);
            }else {
                $status_message = $crypto_result["statusMsg"];

                throw new Exception($status_message);
            }
        }

        return $external_address;
    }

    private function get_user_crypto_payment_method_by_id($id){
        $db = $this->db;

        $db->where("id", $id);

        $crypto_payment_method = $db->getOne("xun_story_user_crypto_payment_method");
        return $crypto_payment_method;
    }

    private function get_user_crypto_payment_method($user_id, $wallet_type){
        $db = $this->db;

        $db->where("user_id", $user_id);
        $db->where("wallet_type", $wallet_type);

        $crypto_payment_method = $db->getOne("xun_story_user_crypto_payment_method");
        return $crypto_payment_method;
    }

    private function get_user_crypto_payment_method_list_display($user_id, $columns = null){
        $db = $this->db;

        $db->where("a.user_id", $user_id);
        $db->where("a.status", 1);
        $db->join("xun_marketplace_currencies b", "a.wallet_type=b.currency_id", "LEFT");
        $data = $db->get("xun_story_user_crypto_payment_method a", null, $columns);

        return $data;
    }

    private function get_user_crypto_payment_method_list($user_id, $map_column = null, $columns = null, $status = null){
        $db = $this->db;

        $db->where("user_id", $user_id);
        if(!is_null($status)){
            $db->where("status", $status);
        }
        if($map_column){
            $db->map($map_column)->ArrayBuilder();
        }

        $data = $db->get("xun_story_user_crypto_payment_method", null, $columns);
        return $data;
    }

    private function upsert_crypto_payment_method($user_id, $wallet_type, $external_address, $crypto_payment_method = null){
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        if(!$crypto_payment_method){
            $crypto_payment_method = $this->get_user_crypto_payment_method($user_id, $wallet_type);
        }

        if($crypto_payment_method && $crypto_payment_method["status"] == 0){
            $update_data = [];
            $update_data["status"] = 1;
            $update_data["updated_at"] = $date;

            $db->where("id", $crypto_payment_method["id"]);
            $db->update("xun_story_user_crypto_payment_method", $update_data);
        }else if(!$crypto_payment_method){
            $insert_data = array(
                "user_id" => $user_id,
                "wallet_type" => $wallet_type,
                "external_address" => $external_address,
                "status" => 1,
                "created_at" => $date,
                "updated_at" => $date
            );

            $row_id = $db->insert("xun_story_user_crypto_payment_method", $insert_data);

            if(!$row_id){
                throw new Exception($db->getLastError(), 999);
            }
        }
        //  delete = update status
        //  re activate = update status
    }

    private function delete_user_crypto_payment_method($payment_method_id){
        $db = $this->db;

        $date = date("Y-m-d H:i:s");
        $update_data = [];
        $update_data["status"] = 0;
        $update_data["updated_at"] = $date;

        $db->where("id", $payment_method_id);
        $ret_val = $db->update("xun_story_user_crypto_payment_method", $update_data);
        return $ret_val;

    }

    private function get_supported_cryptocurrency_details(){
        $db = $this->db;

        $db->where("a.is_story", 1);
        $db->join("xun_marketplace_currencies b", "a.currency_id=b.currency_id", "LEFT");
        $data = $db->get("xun_coins a", null, "b.name, b.symbol, b.currency_id as wallet_type");

        return $data;
    }

    private function delete_user_fiat_payment_method($params){
        $db = $this->db;

        $user_id = $params['user_id'];
        $payment_id = $params['payment_id'];

        $date = date("Y-m-d H:i:s");
        $update_data = [];
        $update_data["status"] = 0;
        $update_data["updated_at"] = $date;

        $db->where("user_id", $user_id);
        $db->where("id", $payment_id);
        $ret_val = $db->update("xun_user_story_fiat_payment_method", $update_data);
        return $ret_val;

    }
    public function delete_payment_method($params, $sourceName){
        $db = $this->db;
        
        $username = trim($params['username']);//for app validation
        $business_id = trim($params['business_id']);
        $user_id = trim($params["user_id"]);//for web validation
        $payment_type_id = trim($params['payment_type_id']);
        $payment_id = trim($params['payment_id']);

        if(!$username && $sourceName == 'app')
            return array("code" => 0, "message" => "FAILED", "message_d" => "Username is required.");
        if(!$user_id && $sourceName == 'web')
            return array("code" => 0, "message" => "FAILED", "message_d" => "User id is required.");
        if(!$payment_type_id)
            return array("code" => 0, "message" => "FAILED", "message_d" => "Payment Type ID is required.");
        if(!$payment_id)
            return array("code" => 0, "message" => "FAILED", "message_d" => "Payment ID is required.");

        if($user_id && $sourceName == 'web'){
            $xun_user = $this->validate_user_id($user_id);
        }
        elseif($username &&$sourceName == 'app'){
            $xun_user = $this->validate_username($username, $business_id);
        }
        else{
            return array("code" => 0, "message" => "FAILED", "message_d" => "Invalid data");
        }

        if(isset($xun_user["code"]) && $xun_user["code"] == 0){
            return $xun_user;
        }

        // if($business_id){
        //     $db->where('id', $business_id);
        // }
        // else{
        //     $db->where('username', $username);
        // }
    
        // $xun_user = $db->getOne('xun_user');
        // if (!$xun_user)
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found", 'developer_msg' => "User not found");
        
        $user_id = $xun_user['id'];

        if($payment_type_id == '1'){
            $crypto_params['user_id'] = $user_id;
            $crypto_params['payment_method_id'] = $payment_id;
            $crypto_result = $this->delete_crypto_payment_method($crypto_params);
            if (!$crypto_result)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Cryptocurrency payment method delete failed.", 'developer_msg' => $db->getLastError());
        }else{
            $fiat_params['user_id'] = $user_id;
            $fiat_params['payment_id'] = $payment_id;
            $fiat_result = $this->delete_user_fiat_payment_method($fiat_params);
            if (!$fiat_result)
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "This payment method delete failed.", 'developer_msg' => $db->getLastError());
        }
        if($crypto_result['code'] == 1 || $fiat_result)
            return array("code" => 1, "message" => "SUCCESS", "message_d" => "Deleted payment method successful.");
    }


    public function get_story_payment_gateway_address($story_id, $wallet_type){
        $db = $this->db;
        $setting = $this->setting;

        $story_business_id = $setting->systemSetting["storyBusinessID"];
        
        $story_pg_data = $this->get_story_payment_gateway_data($story_id, $wallet_type);

        if($story_pg_data){
            $address_id = $story_pg_data["address_id"];
            //  get address from xun_crypto_address

            $xun_payment_gateway_service = new XunPaymentGatewayService($db);
            $crypto_address_data = $xun_payment_gateway_service->getBusinessPaymentGatewayAddressByID($address_id, "crypto_address, status");

            if(!$crypto_address_data){
                throw new Exception("Donation address not found.");
            }
            else{
                return $crypto_address_data["crypto_address"];
            }
        }else{
            throw new Exception("This story does not accept the selected coin.");
        }
    }



    public function set_story_payment_gateway_address($story_id, $wallet_type, $destination_address){
        /**
         * get business story id
         * check if destination address is valid
         * create pg wallet if not created
         * generate new pg address
         * bind pg address to destination address
         * set address id and story
         */


        global $xunCrypto;

        $setting = $this->setting;

        $story_business_id = $setting->systemSetting["storyBusinessID"];

        try{
            $result = $xunCrypto->create_pg_fund_out_address($story_business_id, $wallet_type, $destination_address, true);
        }catch(Exception $e){
            $error_code = $e->getCode();
            $error_message = $e->getMessage();
            $return_data =  array("code" => 0, "message" => "FAILED", "message_d" => $error_message);

            if($error_code != 0){
                $return_data["errorCode"] = $error_code;
            }
            return $return_data;
        }

        // result = array("address_id" => "", "address" => "")
        $address_id = $result["address_id"];

        try{
            $this->insert_story_payment_gateway($story_id, $wallet_type, $address_id);
        }catch(Exception $e){
            $error_code = $e->getCode();
            $error_message = $e->getMessage();
            $return_data =  array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $error_message);

            if($error_code != 0){
                $return_data["errorCode"] = $error_code;
            }
            return $return_data;
        }
    }

    private function get_story_payment_gateway_data($story_id, $wallet_type){
        $db = $this->db;

        $db->where("story_id", $story_id);
        $db->where("wallet_type", $wallet_type);

        $story_pg = $db->getOne("xun_story_payment_gateway");

        return $story_pg;
    }

    private function get_story_payment_gateway_list($story_id){
        $db = $this->db;

        $db->where("a.story_id", $story_id);
        $db->join("xun_crypto_address b", "a.address_id=b.id", "LEFT");
        $db->orderBy("a.id", "ASC");
        $data = $db->get("xun_story_payment_gateway a", null, "a.*, b.crypto_address");

        return $data;
    }

    private function insert_story_payment_gateway($story_id, $wallet_type, $address_id){
        $db = $this->db;

        $date = date("Y-m-d H:i:s");
        $insert_data = array(
            "story_id" => $story_id,
            "wallet_type" => $wallet_type,
            "address_id" => $address_id,
            "created_at" => $date,
            "updated_at" => $date
        );

        $row_id = $db->insert("xun_story_payment_gateway", $insert_data);

        if(!$row_id){
            throw new Exception($db->getLastError());
        }
        return $row_id;
    }

    public function app_donation_fiat($params){
        $db = $this->db;

        $username = trim($params["username"]);
        $story_id = trim($params["story_id"]);
        $business_id = trim($params["business_id"]);
        $payment_method_id = trim($params["payment_method_id"]);
        $amount = trim($params["amount"]);
        $transaction_slip_url = trim($params["transaction_slip_url"]);
        $description = trim($params["description"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username is required.", 'developer_msg' => "username cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required.", 'developer_msg' => "story_id cannot be empty");
        }

        if($payment_method_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Payment method ID is required.", 'developer_msg' => "payment_method_id cannot be empty");
        }

        if($amount == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Amount is required.", 'developer_msg' => "amount cannot be empty");
        }

        if($transaction_slip_url == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Transaction slip URL is required.", 'developer_msg' => "transaction_slip_url cannot be empty");
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        }

        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];
        $email = $xun_user["email"];

        $user_setting = $this->get_user_ip_and_country($user_id);

        $ip = $user_setting["lastLoginIP"]["value"];
        $user_country = $user_setting["ipCountry"]["value"];

        $user_device_info = $this->get_user_device_info($username);
        if ($user_device_info) {
            $device_os = $user_device_info["os"];
            
            if($device_os == 1)
            {$device = "Android";}
            else if ($device_os == 2){$device = "iOS";}

        } else {
            $device = "";
        }

        $db->where('story_id', $story_id);
        $db->where('story_type', 'story');
        $story_updates = $db->getOne('xun_story_updates');
        $title = $story_updates["title"];

        $currency_id = $xun_story["currency_id"];
        $currency_name = $this->get_fiat_currency_name($currency_id);
        $uc_currency_name = strtoupper($currency_name);
        $amount_with_name = $amount . " " . $uc_currency_name;

        if($business_id){
            //  check if user is business employee

            $xun_business_service = new XunBusinessService($db);
            
            $is_business_employee = $xun_business_service->isBusinessEmployee($business_id, $username);

            if($is_business_employee == false){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid business ID.");
            }

            $user_id = $business_id;
        }

        if($amount <= 0 ){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid amount.");
        }
        
        $db->where("id", $payment_method_id);
        $story_payment_method = $db->getOne("xun_story_payment_method", "id, story_id");
        
        if(!$story_payment_method || $story_payment_method["story_id"] != $story_id){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid payment method ID for this story.");
        }
        $date = date("Y-m-d H:i:s");

        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story');

        if(!$xun_story || $xun_story["status"] != "active" || $xun_story["disabled"] == 1 || $xun_story["expires_at"] < $date){
            $error_message = "This story is no longer accepting donations.";
            $this->send_fiat_donation_notification($nickname, "TheNux", $username , $email, $ip, $user_country, $device, "FAILED", $title, $error_message,  $amount_with_name);
            return array("code" => 0, "message" => "FAILED", "message_d" => "This story is no longer accepting donations.");
        }

        $currency_id = $xun_story["currency_id"];

        $insert_story_donation_data = array(
            "user_id" => $user_id,
            "story_id" => $story_id,
            "amount" => $amount,
            "wallet_type" => $currency_id,
            "value" => $amount,
            "currency_id" => $currency_id,
            "currency_rate" => 1,
            "description" => $description,
            "transaction_type" => "donation",
            "status" => "pending",
            "created_at" => $date,
            "updated_at" => $date
        );

        $story_transaction_id = $db->insert("xun_story_transaction", $insert_story_donation_data);
        if(!$story_transaction_id){
            $error_message = "Something went wrong. Please try again.";
            $this->send_fiat_donation_notification($nickname, "TheNux", $username , $email, $ip, $user_country, $device, "FAILED", $title, $error_message,  $amount_with_name);
            return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "developer_msg" => $db->getLastError());
        }

        $insert_data = array(
            "story_transaction_id" => $story_transaction_id,
            "story_id" => $story_id,
            "user_id" => $user_id,
            // "first_name" => $first_name,
            // "last_name" => $last_name,
            // "email" => $email,
            // "country" => $country,
            // "phone_number" => $phone_number,
            // "hide_identity" => $hide_identity,
            "transaction_slip_url" => $transaction_slip_url,
            "amount" => $amount,
            "payment_method_id" => $payment_method_id,
            "payment_method_type" => "fiat",
            "platform" => "App",
            "created_at" => $date,
            "updated_at" => $date
        );
        
        $row_id = $db->insert("xun_story_donation", $insert_data);
        if(!$row_id){
            return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "developer_msg" => $db->getLastError());
        }

        $this->send_fiat_donation_notification($nickname, "TheNux", $username , $email, $ip, $user_country, $device, "SUCCESS", $title, "", $amount_with_name);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Thank you for your donation.");

    }

    public function web_donation($params, $ip, $user_agent){
        $db = $this->db;

        $user_id = trim($params["user_id"]);
        $story_id = trim($params["story_id"]);
        
        $first_name = trim($params["first_name"]);
        $last_name = trim($params["last_name"]);
        $email = trim($params["email"]);
        $country = trim($params["country"]);
        $phone_number = trim($params["phone_number"]);
        $amount = trim($params["amount"]);
        $description = trim($params["description"]);
        $hide_identity = trim($params["hide_identity"]);
        $payment_method_id = trim($params["payment_method_id"]);
        $payment_method_type = trim($params["payment_method_type"]); // fiat or cryptocurrency
        $transaction_slip_url = trim($params["transaction_slip_url"]);

        if($story_id == ''){
            $error_message = "Story ID is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }
        if($first_name == ''){
            $error_message = "First name is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }
        if($last_name == ''){
            $error_message = "Last name is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }
        if($email == ''){
            $error_message = "Email address is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }
        if($country == ''){
            $error_message = "Country is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }
        if($phone_number == ''){
            $error_message = "Phone number is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }
        if($amount == ''){
            $error_message = "Amount is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }
        if($payment_method_id == ''){
            $error_message = "Payment method is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }
        if($payment_method_type == ''){
            $error_message = "Payment method type is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }
        if ($payment_method_type){
            if (strtolower($payment_method_type) != "fiat" && strtolower($payment_method_type) != 'cryptocurrency'){
                $error_message = "Invalid payment method type.";
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }
            if($payment_method_type == "fiat"){
                if($transaction_slip_url == ''){
                    $error_message = "Transaction slip URL is required.";
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                }
                // $db->where("id", $payment_method_id);
                // $payment_method_id = $db->getValue("xun_story_payment_method", "payment_method_id");

                $db->where("id", $payment_method_id);
                $story_payment_method = $db->getOne("xun_story_payment_method", "id, story_id");
                
                if(!$story_payment_method || $story_payment_method["story_id"] != $story_id){
                    $error_message = "The payment method ID is invalid for this story";
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
                }
            }
        }


        if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
            $error_message = "Please enter a valid email address.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Please enter a valid email address.");
        }

        $country = strtolower($country);

        // if($username){
        //     $xun_user_service = new XunUserService($db);
    
        //     $xun_user = $xun_user_service->getUserByUsername($username);
    
        //     if(!$xun_user){
        //         return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.");
        //     }
    
        //     $user_id = $xun_user["id"];
        // }else{
        //     $user_id = 0;
        // }

        if($user_id){
            $xun_user = $this->validate_user_id($user_id);
            $nickname = $xun_user["nickname"];
            $email = $xun_user["email"];
            $username = $xun_user["username"];

            $register_site = $xun_user["register_site"];
            $user_type = $register_site ? 'NuxStory' : 'TheNux';
        }
        else{
            $user_type = 'Non-member';
        }

        $db->where('story_id', $story_id);
        $db->where('story_type', 'story');
        $story_updates = $db->getOne('xun_story_updates');
        $title = $story_updates["title"];

        $currency_id = $xun_story["currency_id"];
        $currency_name = $this->get_fiat_currency_name($currency_id);
        $uc_currency_name = strtoupper($currency_name);
        $amount_with_name = $amount . " " . $uc_currency_name;

        if($user_id && $ip){
            $xunIP = new XunIP($db);
            $ip_country = $xunIP->get_ip_country($ip);

            $db->where('user_id', $user_id);
            $db->where('name', array("ipCountry", "lastLoginIP", "device"), 'IN');
            $user_setting = $db->map('name')->ArrayBuilder()->get('xun_user_setting');

            $date = date("Y-m-d H;i:s");
            if($user_setting["lastLoginIP"]){
                $update_ip = array(
                    "value" => $ip,
                    "updated_at" => $date,
                );
                
                $db->where('user_id', $user_id);
                $db->where('name', "lastLoginIP");
                $updated_ip = $db->update('xun_user_setting', $update_ip);

                if(!$updated_ip){
                    $error_message = "Something went wrong. Please try again.";
                    $this->send_fiat_donation_notification($nickname, $user_type, $username ,$email, $ip, $ip_country, $user_agent, "FAILED", $title, $error_message,  $amount_with_name);
                    return array("code" => 0, "message" => "SUCCESS", "message_d" => $error_message, "developer_msg" => $db->getLastError());
                }
            }
            else{
                $insert_ip = array(
                    "user_id" => $user_id,
                    "name" => "lastLoginIP",
                    "value" => $ip,
                    "created_at" => $date,
                    "updated_at" => $date,
                );

                $inserted_ip = $db->insert('xun_user_setting', $insert_ip);

                if(!$inserted_ip){
                    $error_message = "Something went wrong. Please try again.";
                    $this->send_fiat_donation_notification($nickname, $user_type, $username ,$email, $ip, $ip_country, $user_agent, "FAILED", $title, $error_message,  $amount_with_name);
                    return array("code" => 0, "message" => "SUCCESS", "message_d" => $error_message, "developer_msg" => $db->getLastError());
                }
            }

            if($user_setting["ipCountry"]){
                $update_country = array(
                    "value" => $ip_country,
                    "updated_at" => $date,
                );

                $db->where('user_id', $user_id);
                $db->where('name', 'ipCountry');
                $updated_country = $db->update('xun_user_setting', $update_country);

                if(!$updated_country){
                    $error_message = "Something went wrong. Please try again.";
                    $this->send_fiat_donation_notification($nickname, $user_type, $username ,$email, $ip, $ip_country, $user_agent, "FAILED", $title, $error_message,  $amount_with_name);
                    return array("code" => 0, "message" => "SUCCESS", "message_d" => $error_message, "developer_msg" => $db->getLastError());
                }
            }
            else{
                $insert_country = array(
                    "user_id" => $user_id,
                    "name" => "ipCountry",
                    "value" => $ip_country,
                    "created_at" => $date,
                    "updated_at" => $date,

                );

                $inserted_country = $db->insert('xun_user_setting', $insert_country);

                if(!$inserted_country){
                    $error_message = "Something went wrong. Please try again.";
                    $this->send_fiat_donation_notification($nickname, $user_type, $username ,$email, $ip, $ip_country, $user_agent, "FAILED",$title, $error_message,  $amount_with_name);
                    return array("code" => 0, "message" => "SUCCESS", "message_d" => $error_message, "developer_msg" => $db->getLastError());
                }
            }

            if($user_setting["device"]){
                $update_device = array(
                    "value" => $user_agent,
                    "updated_at" => $date
                );

                $db->where('user_id', $user_id);
                $db->where('name', 'device');
                $updated_device = $db->update('xun_user_setting', $update_device);

                if(!$updated_device){
                    $error_message = "Something went wrong. Please try again.";
                    $this->send_fiat_donation_notification($nickname, $user_type, $username ,$email, $ip, $ip_country, $user_agent, "FAILED", $title, $error_message,  $amount_with_name);
                    return array("code" => 0, "message" => "SUCCESS", "message_d" => $error_message, "developer_msg" => $db->getLastError());
                }
            }
            else{
                $insert_device = array(
                    "user_id" => $user_id,
                    "name" => "device",
                    "value" => $user_agent,
                    "created_at" => $date,
                    "updated_at" => $date
                );

                $inserted_device = $db->insert('xun_user_setting', $insert_device);

                if(!$inserted_device){
                    $error_message = "Something went wrong. Please try again.";
                    $this->send_fiat_donation_notification($nickname, $user_type, $username ,$email, $ip, $ip_country, $user_agent, "FAILED", $title, $error_message,  $amount_with_name);
                    return array("code" => 0, "message" => "SUCCESS", "message_d" => $error_message, "developer_msg" => $db->getLastError());
                }
            }
        }

        if($hide_identity != 1){
            $hide_identity = 0;
        }

        if($amount <= 0 ){
            $error_message = "Invalid amount.";
            $this->send_fiat_donation_notification($nickname, $user_type, $username ,$email, $ip, $ip_country, $user_agent, "FAILED", $title, $error_message,  $amount_with_name);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        $db->where("id", $story_id);
        $xun_story = $db->getOne("xun_story");
        $date = date("Y-m-d H:i:s");

        if(!$xun_story || $xun_story["status"] != "active" || $xun_story["disabled"] == 1 || $xun_story["expires_at"] < $date){
            $error_message = "This story is no longer up for fundraising. ";
            $this->send_fiat_donation_notification($nickname, $user_type, $username ,$email, $ip, $ip_country, $user_agent, "FAILED", $title,  $error_message,  $amount_with_name);
            return array("code" => 0, "message" => "FAILED", "message_d" =>$error_message);
        }


        $insert_story_donation_data = array(
            "user_id" => $user_id ? $user_id : 0,
            "story_id" => $story_id,
            "amount" => $amount,
            "wallet_type" => "usd",
            "value" => $amount,
            "currency_id" => "usd",
            "currency_rate" => 1,
            "description" => $description,
            "transaction_type" => "donation",
            "status" => "pending",
            "created_at" => $date,
            "updated_at" => $date
        );


        $story_transaction_id = $db->insert("xun_story_transaction", $insert_story_donation_data);
        if(!$story_transaction_id){
            $error_message = "Something went wrong. Please try again.";
            $this->send_fiat_donation_notification($nickname, $user_type, $username ,$email, $ip, $ip_country, $user_agent, "FAILED", $title, $error_message,  $amount_with_name);
            return array("code" => 0, "message" => "SUCCESS", "message_d" => $error_message, "developer_msg" => $db->getLastError());
        }

        $insert_data = array(
            "story_transaction_id" => $story_transaction_id,
            "story_id" => $story_id,
            "user_id" => $user_id,
            "first_name" => $first_name,
            "last_name" => $last_name,
            "email" => $email,
            "country" => $country,
            "phone_number" => $phone_number,
            "hide_identity" => $hide_identity,
            "transaction_slip_url" => $transaction_slip_url,
            "amount" => $amount,
            "payment_method_id" => $payment_method_id,
            "payment_method_type" => $payment_method_type,
            "platform" => 'Web',
            "created_at" => $date,
            "updated_at" => $date
        );
        
        $row_id = $db->insert("xun_story_donation", $insert_data);
        if(!$row_id){
            $error_message = "Something went wrong. Please try again.";
            $this->send_fiat_donation_notification($nickname, $user_type, $username ,$email, $ip, $ip_country, $user_agent, "FAILED", $title, $error_message,  $amount_with_name);
            return array("code" => 0, "message" => "SUCCESS", "message_d" => $error_message, "developer_msg" => $db->getLastError());
        }

        $return_message = "Thank you for your donation.";
        $this->send_fiat_donation_notification($nickname, $user_type, $username ,$email, $ip, $ip_country, $user_agent, "SUCCESS",$title,  "",  $amount_with_name);

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $return_message);
    }


    public function web_get_donor_transaction_slip_url($params){
        $db = $this->db;

        $username = trim($params["username"]);
        $file_name = trim($params["file_name"]);
        $content_type = trim($params["content_type"]);
        $content_size = trim($params["content_size"]);

        if($file_name == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Filename is required.");
        }
        if($content_type == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Content type is required.");
        }
        if($content_size == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Content size is required.");
        }

        $result = $this->get_donor_transaction_slip_presign_url($file_name, $content_type, $content_size);

        if(isset($result["error"])){
            return array("code" => 1, "status" => "error", "statusMsg" => "Error generating AWS S3 presigned URL.", "errorMsg" => $result["error"]);
        }
        
        $return_message = "AWS presigned url.";
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $return_message, "data" => $result);
    }

    private function get_donor_transaction_slip_presign_url($file_name, $content_type, $content_size){
        global $xunAws;

        $setting = $this->setting;

        // $file_name = trim($params["file_name"]);
        // $content_type = trim($params["content_type"]);
        // $content_size = trim($params["content_size"]);

        $bucket = $setting->systemSetting["awsS3StoryBucket"];
        $s3_folder = 'transaction_slip';
        $timestamp = time();
        $presigned_url_key = $s3_folder . '/' . $timestamp . '/' . $file_name;
        $expiration = '+20 minutes';
        
        $newParams = array(
            "s3_bucket" => $bucket,
            "s3_file_key" => $presigned_url_key,
            "content_type" => $content_type,
            "content_size" => $content_size,
            "expiration" => $expiration
        );

        $result = $xunAws->generate_put_presign_url($newParams);
        
        return $result;
        // if(isset($result["error"])){
        //     return array("code" => 1, "status" => "error", "statusMsg" => "Error generating AWS S3 presigned URL.", "errorMsg" => $result["error"]);
        // }
        
        // $return_message = "AWS presigned url.";
        // return array("code" => 0, "status" => "ok", "statusMsg" => $return_message, "data" => $result);

    }

    public function web_get_create_story_details($params){
        $db = $this->db;
        
        // $user_id = trim($params["user_id"]);

        // if ($user_id == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "User ID cannot be empty", 'developer_msg' => "User ID cannot be empty");
        // }


        // $db->where('id', $user_id);
        // $xun_user = $db->getOne('xun_user');
        // if (!$xun_user)
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found", 'developer_msg' => "User not found");

        $story_category_list = $this->get_story_category_list(null);

        $country_list = $this->get_country_list(null);
        foreach($country_list as &$data){
            unset($data['iso_code2']);

        }

        $currency_list = $this->get_fiat_currency_list("symbol, currency_id, image, image_md5");

        $returnData["category_list"] = $story_category_list;
        $returnData["country_list"] = $country_list ? $country_list : [];
        $returnData["currency_list"] = $currency_list ? $currency_list : [];

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Create Story Details.", "data" => $returnData);
    }

    public function story_donation_listing($params, $sourceName){
        $db = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $page_limit = $setting->systemSetting["appsPageLimit"];

        $username = trim($params["username"]);//for app validation
        $business_id = trim($params["business_id"]);
        $user_id = trim($params["user_id"]);//for web validation
        $story_id = trim($params["story_id"]);
        $last_id = trim($params['last_id']);
        $page_size = trim($params["page_size"]);
        $status = trim($params['status']);

        $last_id = $last_id ? $last_id : 0;
        $page_size = $page_size ? $page_size : $page_limit;

        $order = strtoupper(trim($params["order"]));
        $order = ($order == 'DESCENDING' ? "DESC" : ($order == 'ASCENDING' ? "ASC" : "DESC"));

        $limit = array($last_id, $page_size);

        if ($username == '' && $sourceName == 'app') { 
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "app: username cannot be empty");
        }

        if($user_id == '' && $sourceName == 'web'){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "user_id is required.", 'developer_msg' => "aweb: user_id cannot be empty");
        }

        if(!$story_id)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story ID is required.", 'developer_msg' => "Story ID cannot be empty");

        if(!$status)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Status is required.", 'developer_msg' => "Status cannot be empty");

        if($user_id){
            $xun_user = $this->validate_user_id($user_id);
        }
        elseif($username){
            $xun_user = $this->validate_username($username, $business_id);
        }
            
        if(isset($xun_user["code"]) && $xun_user["code"] == 0){
            return $xun_user;
        }
    
        $user_id = $xun_user["id"];
    
        $db->where("id", $story_id);
        $db->where("user_id", $user_id);
        $xun_story = $db->getOne("xun_story");
        if(!$xun_story)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid story.", 'developer_msg' => "Invalid story");

        $status = strtolower($status);
        if ($status == 'accepted'){
            $query_status = array("success");
        }else if ($status == 'pending'){
            $query_status = array("pending");
        }else{
            $query_status = array("refunded", "pending_refund", "failed");
        }

        //get donation list from the story id
        $db->orderBy("a.created_at", $order);
        $db->where("b.status", $query_status, "IN");
        $db->where("a.payment_method_type", "fiat");
        // $db->where("a.payment_method_id", "1", "!=");
        $db->where("a.story_id", $story_id);
        $db->join("xun_story_transaction b", "a.story_transaction_id = b.id", "LEFT");
        $copyDb = $db->copy();
        $columns = "a.*, a.user_id, b.wallet_type, b.status, b.amount as final_amount, a.payment_method_id";
        $story_donation = $db->get("xun_story_donation a", $limit, $columns);
// print_r($db->getLastQuery());
        $totalRecord = $copyDb->getValue('xun_story_donation a', "count(distinct(a.id))");

        // $payment_method = $db->map('id')->ArrayBuilder()->get('xun_marketplace_payment_method', null);

        $payment_method_id_arr = [];
        $donation_listing = [];
        foreach($story_donation as $donation){
            if ($donation['payment_method_type'] == 'fiat'){
                // $donation["payment_name"] = $payment_method[$donation['payment_method_id']]['name'];
                $payment_method_id_arr[] = $donation["payment_method_id"];
            }
            $currency_id = $donation['wallet_type'];
            $currency_name = $this->get_fiat_currency_name($currency_id);
            $uc_currency_name = strtoupper($currency_name);
            $donation_info = array(
                "id" => $donation["id"],
                "user_id" => $donation["user_id"],
                "story_transaction_id" => $donation["story_transaction_id"],
                "first_name" => $donation["first_name"],
                "last_name" => $donation["last_name"],
                "email" => $donation["email"],
                "phone_number" => $donation["phone_number"],
                "country" => $donation["country"],
                // "hide_identity" => $donation["hide_identity"],
                // "transaction_slip_url" => $donation["transaction_slip_url"],
                // "payment_name" => $donation["payment_name"] ? $donation["payment_name"] : $donation["payment_method_type"],
                "payment_method_type" => $donation["payment_method_type"],
                "currency_id" => $donation['wallet_type'],
                "currency_name" => $uc_currency_name,
                "amount" => $donation["final_amount"],
                "created_at" => $donation["created_at"],
                "updated_at" => $donation["updated_at"],
                "status" => $status,
                "payment_method_id" => $donation["payment_method_id"]
            );
            if ($donation['user_id'] != 0){
                $user_ids[] = $donation['user_id'];
            }
            $donation_listing[] = $donation_info;
        }

        if (!empty($user_ids)){
            $db->where('id', $user_ids, "IN");
            $donor_info = $db->map("id")->ArrayBuilder()->get("xun_user", null, "id, username, nickname, type");

            $db->where('user_id', $user_ids, "IN");
            $user_details = $db->map("user_id")->ArrayBuilder()->get('xun_user_details', null, "id, user_id, picture_url");

        }
        if (!empty($payment_method_id_arr)){
            $db->where("a.id", $payment_method_id_arr, "IN");
            $db->join("xun_marketplace_payment_method b", "a.payment_method_id=b.id", "LEFT");
            $payment_method_arr = $db->map("id")->ArrayBuilder()->get("xun_story_payment_method a", null, "a.id, b.name, b.image");
        }

        foreach($donation_listing as &$donation){
            if ($donation['user_id'] != 0){
                $donation['username'] = $donor_info[$donation['user_id']]['username'];
                $donation['nickname'] = $donor_info[$donation['user_id']]['nickname'];
                $donation['user_type'] = $donor_info[$donation['user_id']]['type'];
                $donation['picture_url'] = $user_details[$donation['user_id']]['picture_url']? $user_details[$donation['user_id']]['picture_url'] : '';

            }else{
                $donation['username'] = $donation['phone_number'];
                $donation['nickname'] = $donation['first_name'] . " " . $donation['last_name'];
                $donation['user_type']= "user";
                $donation['picture_url'] = '';
            }

            if($donation["payment_method_type"] == "fiat"){
                $payment_method_id = $donation["payment_method_id"];
                $donation["payment_name"] = $payment_method_arr[$payment_method_id]['name'];
            }
        }
        $story_currency = $xun_story['story_currency_id'];
        foreach($donation_listing as $donate){
            $return_data = array(
                "id" => $donate['id'],
                "story_transaction_id" => $donate['story_transaction_id'],
                "username" => $donate['username'],
                "nickname" => $donate['nickname'],
                "user_type" => $donate['user_type'],
                "picture_url" => $donate['picture_url'],
                "email" => $donate['email'],
                "country" => $donate['country'],
                "payment_name" => $donate['payment_name'],
                "payment_method_type" => $donate['payment_method_type'],
                "currency_id" => $donate['currency_id'],
                "currency_name" => $donate['currency_name'],
                "story_currency_code" => $story_currency ? $story_currency : "",
                "amount" => $donate['amount'],
                "created_at" => $general->formatDateTimeToIsoFormat($donate['created_at']),
                "updated_at" => $general->formatDateTimeToIsoFormat($donate['updated_at']),
                "status" => $status
            );
            $return_list[] = $return_data;
        }

        $returnData["donation_listing"] = $return_list ? $return_list : [];
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = count($donation_listing);
        $returnData["totalPage"] = ceil($totalRecord / $page_size);

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Get Story Donation Listing Successful", 'data' => $returnData);
    }

    public function get_story_transaction_details($params, $sourceName){
        $db = $this->db;
        $general = $this->general;
        
        $username = trim($params["username"]);//for app validation
        $business_id = trim($params["business_id"]);
        $user_id = trim($params["user_id"]);//for web validation
        $story_transaction_id = trim($params["story_transaction_id"]);

        if ($username == '' && $sourceName == 'app') { 
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username is required.", 'developer_msg' => "app: username cannot be empty");
        }

        if($user_id == '' && $sourceName == 'web'){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "user_id is required.", 'developer_msg' => "web: user_id cannot be empty");
        }

        if(!$story_transaction_id)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story Transaction ID is required.", 'developer_msg' => "Story Transaction ID  cannot be empty");

       
        if($user_id){
            $xun_user = $this->validate_user_id($user_id);
        }
        elseif($username){
            $xun_user = $this->validate_username($username, $business_id);
        }
            
        if(isset($xun_user["code"]) && $xun_user["code"] == 0){
            return $xun_user;
        }

        $user_id = $xun_user["id"];

        $db->where("story_transaction_id", $story_transaction_id);
        $story_donation = $db->getOne("xun_story_donation");

        $db->where("a.id", $story_transaction_id);
        $db->join("xun_story b", "a.story_id=b.id", "LEFT");
        $story_tx = $db->getOne("xun_story_transaction a", "a.*, b.story_currency_id");

        $currency_id = $story_tx['wallet_type'];
        $currency_name = $this->get_fiat_currency_name($currency_id);
        $uc_currency_name = strtoupper($currency_name);

        $payment_method_id = $story_donation['payment_method_id'];

        $db->where("a.id", $payment_method_id);
        $db->join("xun_marketplace_payment_method b", "a.payment_method_id=b.id", "LEFT");
        $payment_name = $db->getValue("xun_story_payment_method a", "b.name");
        
        $status = strtolower($status);
        if ($story_tx['status'] == 'success'){
            $status = "Confirmed";
        }else if ($story_tx['status'] == 'pending'){
            $status = "Pending";
        }else{
            $status = "Rejected";
        }

        // $return_data["donation_id"] = $story_donation['id'];
        $return_data["story_transaction_id"] = $story_transaction_id;
        $return_data["story_currency"] = $story_tx["story_currency_id"];
        $return_data["transaction_slip_url"] = $story_donation['transaction_slip_url'];
        $return_data["payment_name"] = $payment_name;
        $return_data["payment_method_type"] = $story_donation['payment_method_type'];
        $return_data["currency_id"] = $story_tx['wallet_type'];
        $return_data["amount"] = $story_tx['amount'];
        $return_data["currency_name"] = $uc_currency_name;
        $return_data["created_at"] = $general->formatDateTimeToIsoFormat($story_donation['created_at']);
        $return_data["updated_at"] = $general->formatDateTimeToIsoFormat($story_donation['updated_at']);
        $return_data["status"] = $status;

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Get Story Transaction Details Successful", 'data' => $return_data);

    }

    public function owner_update_donation($params, $sourceName, $ip=null, $device=null){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $username = trim($params["username"]);//for app validation
        $business_id = trim($params["business_id"]);
        $user_id = trim($params["user_id"]); //for web validation
        $story_transaction_id = trim($params["story_transaction_id"]);
        $amount = trim($params['amount']);
        $action = trim($params['action']);

        if ($username == '' && $sourceName == 'app') { 
            //$error_message = $this->get_translation_message('E00027');
            $error_message = "Username is required.";
            
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => "app: username cannot be empty");
        }

        if($user_id == '' && $sourceName == 'web'){
            $error_message = "User id is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => "web: user_id cannot be empty");
        }

        if(!$story_transaction_id){
            $error_message = "Story Transaction ID is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        if(!$amount){
            $error_message = "Amount is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);      
        }
           

        if(!$action){
            $error_message = "Action is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }
           
        if($user_id){
            $xun_user = $this->validate_user_id($user_id);
        }
        elseif($username){
            $xun_user = $this->validate_username($username, $business_id);
        }
            
        if(isset($xun_user["code"]) && $xun_user["code"] == 0){
            $error_message = $xun_user["message_d"];

            return $xun_user;
        }

        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];
        $phone_number = $xun_user["username"] ? $xun_user["username"] : '';

        if($username){
            $user_setting = $this->get_user_ip_and_country($user_id);

            $ip = $user_setting["lastLoginIP"]["value"];
            $user_country = $user_setting["ipCountry"]["value"];

            $user_device_info = $this->get_user_device_info($username);
            if ($user_device_info) {
                $device_os = $user_device_info["os"];
                
                if($device_os == 1)
                {$device = "Android";}
                else if ($device_os == 2){$device = "iOS";}

            } else {
                $device = "";
            }

            if($xun_user["register_site"] == 'nuxstory'){
                $user_type = 'NuxStory';
            }
            else{
                $user_type = 'TheNux';
            }
            
        }
        elseif($user_id){
            if($xun_user["register_site"] == 'nuxstory' || $xun_user["email"]){
                $user_type = 'NuxStory';
            }
            else{
                $user_type = 'TheNux';
            }
        }
    
        $db->where("id", $story_transaction_id);
        $story_tx = $db->getOne("xun_story_transaction");
        if (!$story_tx || $story_tx["status"] != 'pending'){
            $error_message = "You're not allowed to update this transaction.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }         

        $story_tx_id = $story_tx["id"];
        $story_id = $story_tx["story_id"];
        $donator_user_id = $story_tx["user_id"];
        $ori_amount = $story_tx["value"];

        $db->where('story_transaction_id', $story_tx_id);
        $story_donation = $db->getOne('xun_story_donation');
        $email = $story_donation["email"];
        $user_pm_id = $story_donation["payment_method_id"];

        $db->where('id', $user_pm_id);
        $story_pm = $db->getOne('xun_story_payment_method');

        $pm_id = $story_pm["payment_method_id"];
        $db->where('id', $pm_id);
        $marketplace_pm = $db->getOne('xun_marketplace_payment_method');

        $pm_name = $marketplace_pm["name"];

        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story');
        $fund_collected = $xun_story["fund_collected"];
        $fund_amount = $xun_story["fund_amount"];
        $story_status = $xun_story["status"];
        $currency_id = $xun_story["currency_id"];

        $db->where('story_id', $story_id);
        $db->where('story_type', 'story');
        $story_updates = $db->getOne('xun_story_updates');
        $title = $story_updates["title"];

        // $status = strtolower($action) == 'approve' ? 'success' : 'pending_refund';
        if(strtolower($action) == 'approve'){
            $status = 'success';
         
            $db->where('user_id', $user_id);
            $db->where('story_id', $story_id);
            $db->where('id', $story_transaction_id, "!=");
            $check_story_tx = $db->get('xun_story_transaction');
            $update_fund_collected_arr = [];
            if(!$check_story_tx){
                $update_fund_collected_arr["total_supporters"] = $db->inc(1);
            }
        
            $updated_fund_collected = $fund_collected + $amount;
            
            //  update story status
            if($updated_fund_collected >= $fund_amount){
                $story_status = "completed";
            }

            // $update_fund_collected_arr = array(
            //     "fund_collected" => $updated_fund_collected,
            //     "updated_at" => date('Y-m-d H:i:s'),
            // );
            $update_fund_collected_arr["fund_collected"] = $updated_fund_collected;
            $update_fund_collected_arr["status"] = $story_status;
            $update_fund_collected_arr["updated_at"] = date('Y-m-d H:i:s');
            $db->where('id', $story_id);
            $update_story = $db->update('xun_story', $update_fund_collected_arr);

            if(!$update_story){
                $error_message = "Collected funds update failed.";
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $db->getLastError());
            }

            $currency_name = $this->get_fiat_currency_name($currency_id);
            $uc_currency_name = strtoupper($currency_name);
    
            $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
            $creditType = $decimal_place_setting["credit_type"];
    
            $new_ori_amount = $setting->setDecimal($ori_amount, $creditType);
            $new_ori_amount = $new_ori_amount. " ".$uc_currency_name;

            $new_amount = $amount. " ". $uc_currency_name;

            if($ori_amount != $amount){
                $new_action = "Verified with changes";
            }
            else{
                $new_action = "Verified";
            }
    
        }
        elseif(strtolower($action) == 'reject'){
            $status = 'failed';
            $new_action = "Reject";
        }
        $updateData = array(
            "amount" => $amount,
            "value" => $amount,
            "status" => $status,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where("id", $story_transaction_id);
        $update_result = $db->update("xun_story_transaction", $updateData);
        if(!$update_result){
            $error_message = "Update data failed.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $db->getLastError());
        }
           

        $db->where('user_id', $donator_user_id);
        $user_setting = $db->map('name')->ArrayBuilder()->get('xun_user_setting');
        
        $ip = $user_setting["lastLoginIP"]["value"] ? $user_setting["lastLoginIP"]["value"] : '-';
        $user_country = $user_setting["ipCountry"]["value"] ? $user_setting["ipCountry"]["value"]: '-';
    
        $message = "Username: ".$nickname."\n";
        $message .= "Phone number: ".$phone_number."\n";
        $message .= "Device: ".$device."\n";
        $message .= "Type of User: ".$user_type."\n";
        $message .= "Platform: ".$sourceName."\n\n";
        $message .= "Title: ".$title."\n";
        $message .= "Payment Method: ".$pm_name."\n";
        $message .= "Action: ".$new_action."\n";
        $message .= "Original Amount: ".$new_ori_amount."\n";
        $message .= "Amount: ".$new_amount."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Verified Transaction";

        $this->send_story_notification($tag, $message);

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Update Story Transaction Successful"); 

    }

    public function get_story_user_verify_code($params) {

	global $config;
	$db = $this->db;
	$post = $this->post;

	$mobile = $params["mobile"];

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Mobile number is required.");
        }

	$db->where("email", "", "!=");
	$db->where("web_password", "", "!=");
        $db->where("username", $mobile);
	$db->where("type", "user");
        $xun_user = $db->getOne('xun_user');

        if ($xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User already exist.");

        }else {

	    $req_data = array("mobile" => $mobile,
        	                "language" => 0);

	    $url = "https://".$config["server"].":5283/xun/register/verifycode/get";
	    $curl_return = $post->curl_get($url, $req_data, 0);

	    return json_decode($curl_return, true);

	}

    }

    public function validate_story_user_verify_code($params) {

	global $config;
	$db = $this->db;
	$post = $this->post;

	$mobile = $params["mobile"];
	$verifyCode = $params["verify_code"];
	$name = $params["name"];
	$email = $params["email"];
	$password = $params["password"];
	
	if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Mobile number is required.");
	}

	if ($verifyCode == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Fill up the verification code.");
	}

	if ($name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Name is required.");
	}

	if ($email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Email address is required.");
	}

	if ($password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Password is required.");
	}

	if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Please enter a valid email address.");
	}


        $db->where("email", "", "!=");
        $db->where("web_password", "", "!=");
        $db->where("username", $mobile);
        $db->where("type", "user");
        $xun_user = $db->getOne('xun_user');

        if ($xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User already exist.");

	}else {

	    $db->where("email", $email);
            $db->where("type", "user");
            $xun_user2 = $db->getOne('xun_user');

            if ($xun_user2){
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Email already exist.");
            }

   	    $db->where("is_valid", 1);
	    $db->where("expires_at", date("Y-m-d H:i:s"), ">=");
	    $db->where("mobile", $mobile);
	    $db->orderby("id", "desc");
            $xun_verify_code = $db->getOne('xun_user_verification');

	    if (!$xun_verify_code){
	        return array('code' => 0, 'error_code' => -102, 'message' => "FAILED", 'message_d' => "Please request for a new verification code.");

	    }else {

	        if($xun_verify_code["is_verified"] == 1) {
		    return array('code' => 0, 'error_code' => -102, 'message' => "FAILED", 'message_d' => "Please request for a new verification code.");

	        } else {

	    	    $verify_code = $xun_verify_code["verification_code"];

	    	    if($verify_code == $verifyCode) {

		        $msg = "Verification code verified.";

                        $insertData = array(
                    	    "mobile" => $xun_verify_code["mobile"],
                            "verification_code" => $xun_verify_code["verification_code"],
                    	    "expires_at" => $xun_verify_code["expires_at"],
                    	    "verify_at" => date("Y-m-d H:i:s"),
                    	    "is_verified" => 1,
                    	    "is_valid" => 1,
                    	    "status" => "success",
                    	    "country" => $xun_verify_code["country"],
                    	    "message" => $msg,
                    	    "sms_message_content" => "",
                    	    "device_os" => "",
                    	    "os_version" => "",
                    	    "phone_model" => "",
                    	    "user_type" => $xun_verify_code["user_type"],
                    	    "match" => $xun_verify_code["match"],
                    	    "created_at" => date("Y-m-d H:i:s")
                        );

		        $id = $db->insert('xun_user_verification', $insertData);
			

		        $new_params = [];
                $new_params["username"] = $mobile;
		        $curl_return = $post->curl_post("user/register", $new_params);
		        $curl_code = $curl_return["code"];

		        if ($curl_code == 1) {
			
			    $hash_password = password_hash($password, PASSWORD_BCRYPT);


        		    $db->where("username", $mobile);
        		    $db->where("type", "user");
        		    $xun_user3 = $db->getOne('xun_user');

			    if ($xun_user3){

                    $update_data = [];
                    $update_data["email"] = $email;
                    $update_data["web_password"] = $hash_password;
                    $update_data["updated_at"] = date("Y-m-d H:i:s");

                    $db->where("id", $xun_user3["id"]);
                    $ret_val = $db->update("xun_user", $update_data);

        		}else {

                    $insertUserData = array(
                        "username" => $mobile,
                        "server_host" => $config["server"],
                        "type" => "user",
                        "nickname" => $name,
                        "email" => $email,
                        "language" => 0,
                        "disabled" => 0,
                        "disable_type" => "",
                        "web_password" => $hash_password,
                        "created_at" => date("Y-m-d H:i:s"),
                        "updated_at" => date("Y-m-d H:i:s")
                    );

			    	$id = $db->insert('xun_user', $insertUserData);

			    }

			        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'User successfully registered');

		        } else {
			    return array('code' => 0, 'message' => "FAILED", 'message_d' => "Something went wrong, please try again.");

		        }

	    	    } else {
 
  		        $msg = "The code you entered is incorrect. Please try again.";

	 	        $insertData = array(
	            	    "mobile" => $xun_verify_code["mobile"],
        	    	    "verification_code" => $xun_verify_code["verification_code"],
	            	    "expires_at" => $xun_verify_code["expires_at"],
		    	    "verify_at" => date("Y-m-d H:i:s"),
		    	    "is_verified" => 0,
		    	    "is_valid" => 0,
	            	    "status" => "failed",
		    	    "country" => $xun_verify_code["country"],
		    	    "message" => $msg,
		    	    "sms_message_content" => "",
		    	    "device_os" => "",
		    	    "os_version" => "",
		    	    "phone_model" => "",
		    	    "user_type" => $xun_verify_code["user_type"],
		    	    "match" => $xun_verify_code["match"],
		    	    "created_at" => date("Y-m-d H:i:s")
        	        );

        	        $id = $db->insert('xun_user_verification', $insertData);

		        return array('code' => 0, 'error_code' => -100, 'message' => "FAILED", 'message_d' => $msg);
		    }
		}
	    }
	}

    }

    public function web_story_register($params, $ip, $device){
        global $config;
        $db = $this->db;
        $general = $this->general;

        $email = trim($params["email"]);
        $password = trim($params["password"]);
        $name = trim($params["name"]);
        
        if ($name == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Name is required.");
        }

        if ($email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Email is required.");
        }

        if ($password == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Password is required.");
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Please enter a valid email address.");
        }

        $xunIP = new XunIP($db);
        $ip_country = $xunIP->get_ip_country($ip);

        $xun_user_service = new XunUserService($db);

        //  check if email already exists
        $user_columns = "id, email";
        $xun_user = $xun_user_service->getUserByEmail($email, $user_columns);

        if($xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "An account already exists with this email. Please select another email address.");
        }
        $validate_password = $this->validate_web_password($password);

        if(isset($validate_password["code"]) && $validate_password["code"] == 0){
            $error_message = $validate_password['error_message'];

            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid password combination.", "developer_msg" => "business_password has an invalid character combination", "error_message" => $error_message);
        }

        $hash_password = password_hash($password, PASSWORD_BCRYPT);

        $date = date("Y-m-d H:i:s");

        $insert_user_data = array(
            "username" => "",
            "server_host" => "",
            "type" => "user",
            "nickname" => $name,
            "email" => $email,
            "language" => 0,
            "disabled" => 0,
            "disable_type" => "",
            "web_password" => $hash_password,
            "created_at" => $date,
            "updated_at" => $date
        );

        $verification_code = $general->generateAlpaNumeric(16);

        while(true){
            $db->where("verification_code", $verification_code);
            
            $verification_data = $db->getOne("xun_email_verification", "id");

            if(!$verification_data){
                break;
            }
        }

        try{
            $id = $db->insert('xun_user', $insert_user_data);
            if(!$id){
                throw new Exception($db->getLastError());
            }

            $insert_email_data = array(
                "email" => $email,
                "verification_code" => $verification_code,
                "type" => "nuxstory",
                "created_at" => $date
            );
            $email_id = $db->insert("xun_email_verification", $insert_email_data);
            if(!$email_id){
                throw new Exception($db->getLastError());
            }
        }catch(Exception $e){
            $error_message = $e->getMessage();
            $this->send_register_notification($name, "", $ip, $ip_country, $device, $email, "FAILED", "Something went wrong. Please try again.");
            return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $error_message);
        }

        //  send activation email
        $send_email_result = $this->send_activation_email($email, $name, $verification_code);

        $this->send_register_notification($name, "", $ip, $ip_country, $device, $email, "SUCCESS", "");

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "You've successfully registered.");
    }

    private function get_web_registration_verification_code(){
        $db = $this->db;
        $general = $this->general;

        $verification_code = $general->generateAlpaNumeric(16);

        while(true){
            $db->where("verification_code", $verification_code);
            
            $verification_data = $db->getOne("xun_email_verification", "id");

            if(!$verification_data){
                break;
            }
        }

        return $verification_code;
    }

    public function send_activation_email($email, $name, $verification_code)
    {
        global $setting, $xunEmail, $provider;
        $general = $this->general;
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $companyName = $setting->systemSetting["storyCompanyName"];
        
        $email_body = $xunEmail->getStoryActivationEmailHtml($name, $verification_code, $email, $companyName);
        
        // $translations_message = $translations['B00076'][$language]('B00076') /*Activate your email at %%companyName%% Business.*/;
        $translations_message = "Activate your email at %%companyName%%.";
        $subject = str_replace("%%companyName%%", $companyName, $translations_message);

        $provider_details = $provider->getProviderByName("nuxstory_email");

        $emailParams["subject"] = $subject;
        $emailParams["body"] = $email_body;
        $emailParams["recipients"] = array($email);
        $emailParams["emailFromName"] = $companyName;
        $emailParams["emailAddress"] = $provider_details["username"];
        $emailParams["emailPassword"] = $provider_details["password"];

        $result = $general->sendEmail($emailParams);
        return $result;
    }

    public function web_story_verify_email($params){
        $db = $this->db;

        $verify_code = trim($params["verify_code"]);

        if ($verify_code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Verify code is required.");
        };

        $date = date("Y-m-d H:i:s");

        $db->where("verification_code", $verify_code);
        $result = $db->getOne("xun_email_verification");

        if (!$result) {
            $error_message = "Invalid activation link.";
            $errorCode = -102;
            $title = "Error Activating Account.";

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "errorCode" => $errorCode, "title" => $title);
        }

        // check if is expired
        // code expires in 2 days
        
        $expired_at = date("Y-m-d H:i:s", strtotime('+2 days', strtotime($result["created_at"])));

        if ($expired_at < $date) {
            $error_message = "Your activation link has expired. Please request a new activation link.";
            $errorCode = -101;
            $title = "Activation Link Has Expired";

            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "errorCode" => $errorCode, "title" => $title);
        }
        
        $email = $result["email"];
        if($email != ''){
            $db->where("email", $email);
            $db->where("email_verified", 0);
    
            $update_user_data = [];
            $update_user_data["email_verified"] = 1;
            $update_user_data["updated_at"] = $date;
            $db->update("xun_user", $update_user_data);
        }

        $update_email_data = [];
        $update_email_data["verified_at"] = $date;
        $db->where("id", $result["id"]);
        $db->where("verified_at", NULL, "IS");
        $db->update("xun_email_verification", $update_email_data);

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Success", "title" => "Account activated successfully.");
    }

    public function web_story_resend_email($params){
        $db = $this->db;
        $general = $this->general;

        $email = trim($params["email"]);

        // Param validations
        if ($email == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business email is required.");
        };

        if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Please enter a valid email address.", "developer_msg" => "email is not a valid email.");
        }

        // get verification code from xun_business_verification
        $db->where("email", $email);
        $db->where("type", "user");
        $xun_user = $db->getOne("xun_user", "id, email, nickname, email_verified");

        if (!$xun_user) {
            // $error_message = $this->get_translation_message('E00056') /*This is not a registered user. Please register at our Sign Up page.*/;
            $error_message = "You’re not a registered user yet. Please register at our Sign Up page.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
        }

        if ($xun_user["email_verified"] === 1){
            $error_message = "This email has been verified.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'errorCode' => -100);
        }

        $db->where("email", $email);
        $db->where("type", "nuxstory");
        $db->orderBy("id", "DESC");
        $xun_email_verification = $db->getOne("xun_email_verification");

        $date = date("Y-m-d H:i:s");

        if (!$xun_email_verification) {
            // generate new verification code
            $verification_code = $this->get_web_registration_verification_code();

            try{
                $insert_email_data = array(
                    "email" => $email,
                    "verification_code" => $verification_code,
                    "type" => "nuxstory",
                    "created_at" => $date
                );
                $email_id = $db->insert("xun_email_verification", $insert_email_data);
                if(!$email_id){
                    throw new Exception($db->getLastError());
                }
            }catch(Exception $e){
                $error_message = $e->getMessage();
    
                return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $error_message);
            }
        } else {
            $code_expires_at = date("Y-m-d H:i:s", strtotime('+2 days', strtotime($xun_email_verification["created_at"])));

            // check if email is already verified
            if ($code_expires_at < $date) {
                // check expiry (2 days)
                // generate new code if expired
                $verification_code = $this->get_web_registration_verification_code();
                try{
                    $insert_email_data = array(
                        "email" => $email,
                        "verification_code" => $verification_code,
                        "type" => "nuxstory",
                        "created_at" => $date
                    );
                    $email_id = $db->insert("xun_email_verification", $insert_email_data);
                    if(!$email_id){
                        throw new Exception($db->getLastError());
                    }
                }catch(Exception $e){
                    $error_message = $e->getMessage();
        
                    return array("code" => 0, "message" => "FAILED", "message_d" => "Something went wrong. Please try again.", "error_message" => $error_message);
                }
            } else {
                $verification_code = $xun_email_verification["verification_code"];
            }
        }

        $name = $xun_user["nickname"];

        $send_email_result = $this->send_activation_email($email, $name, $verification_code);

        // return response
        // $translations_message = $this->get_translation_message('B00018') /*Activation email resent to %%email%%.*/;
        $translations_message = "Activation email resent to %%email%%.";
        $return_message = str_replace("%%email%%", $email, $translations_message);

        return array("code" => "1", "message" => "SUCCESS", "message_d" => $return_message);
        
    }

    public function story_login($params, $ip, $device) {

        $db = $this->db;
        $general = $this->general;

        $email = $params["email"];
        $password = $params["password"];

        if ($email == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Email address is required.");
            }

            if ($password == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Password is required.");
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Please enter a valid email address.");
        }

        $xunIP = new XunIP($db);
        $ip_country = $xunIP->get_ip_country($ip);

        $db->where("email", $email);
        $db->where("disabled", 0);
        
        $db->where("type", "user");
        $xun_user = $db->getOne('xun_user');

        if (!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Your email or password is incorrect. Please try again.");

        }else {
            $user_id = $xun_user['id'];
            if($xun_user["email_verified"] === 0){
                $error_message = "Your email is not activated. Please activate your email before signing in.";
                $this->send_login_notification($xun_user["nickname"], $mobile, $user_type, $ip, $ip_country, $device, "FAILED", $error_message);

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message);
            }
            $verify_password = $xun_user["web_password"];
            $user_id = $xun_user["id"];
            if($xun_user["register_site"] == 'nuxstory' || $xun_user["email"]){
                $user_type = "NuxStory";
            }
            else{
                $user_type = "TheNux";
            }

            if (!password_verify($password, $verify_password)) {
               $error_message = "Your email or password is incorrect. Please try again.";
               $this->send_login_notification($xun_user["nickname"], $mobile, $user_type, $ip, $ip_country, $device, "FAILED", $error_message, $email);

                return array('code' => 0, 'message' => "FAILED", 'message_d' => "Your email or password is incorrect. Please try again.");
            } else {

                $db->where("user_id", $user_id);
                $user_details = $db->getOne("xun_user_details");

                $access_token = $general->generateAlpaNumeric(32);

                $updateData["status"] = 0;
                $db->where("business_email", $email);
                $db->update("xun_access_token", $updateData);

                $access_token_expires_at = date("Y-m-d H:i:s", strtotime('+12 hours', strtotime(date("Y-m-d H:i:s"))));

                $fields = array("business_email", "business_id", "access_token", "expired_at");
                $values = array($email, $user_id, $access_token, $access_token_expires_at);

                $insertData = array_combine($fields, $values);

                $row_id = $db->insert("xun_access_token", $insertData);

                $user_data =  array(
                    "id" => $xun_user["id"],
                    "email" => $email, 
                    "name" => $xun_user["nickname"], 
                    "access_token" => $access_token,
                    "picture_url" => $user_details["picture_url"] ? $user_details["picture_url"] : ""
                );

                $this->send_login_notification($xun_user["nickname"], $mobile, $user_type, $ip, $ip_country, $device, "SUCCESS", "", $email);

                return array('code' => 1, 'message' => "Success", 'message_d' => "User credentials verified", 'user' => $user_data);
            }

        }

    }

    public function request_web_login_token($params) {

        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;

        while (1) {

            $access_token = $general->generateAlpaNumeric(32);

                $db->where('access_token', $access_token);
                $result = $db->get('xun_user_login_token');
                
                if (!$result) {
                    $memberTimeout = $setting->systemSetting['memberTimeout'];

                    $expired_at = date("Y-m-d H:i:s", strtotime('+'.$memberTimeout.' seconds', strtotime(date("Y-m-d H:i:s"))));
                    $insert_login_token = array(
                        "access_token" => $access_token,
                        "expired_at" => $expired_at,
                        "created_at" => date("Y-m-d H:i:s"),
                        "login" => 0,
                        "login_at" => '',
                        "login_user_id" => '',
                    );
                    $login_token = $db->insert('xun_user_login_token', $insert_login_token);
                    break;
                }
        }

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'Web login access token', 'access_token' => $access_token);
        
    }

    public function verify_web_login_token($params) {

    }

    public function web_dashboard($params){
        global $xunCurrency;
    
        $db = $this->db;
        $setting = $this->setting;

        $user_id = trim($params["user_id"]);

        if ($user_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User ID is required.", 'developer_msg' => "User ID cannot be empty");
        }

        $db->where('id', $user_id);
        $xun_user = $db->getOne('xun_user');
        if (!$xun_user)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found", 'developer_msg' => "User not found");

        $name = $xun_user["nickname"];

        $db->where('user_id', $user_id);
        $user_details = $db->getOne('xun_user_details');

        $picture_url = $user_details["picture_url"]? $user_details["picture_url"]:'';

        $db->where('user_id', $user_id);
        $db->orderBy('id', 'desc');
        $xun_story = $db->get('xun_story');
        $total_story = count($xun_story);

        $db->where('transaction_type', "donation");
        $db->where('status', "success");
        $db->orderBy('id', 'desc');
        $story_transaction = $db->get('xun_story_transaction');

        $db->where('user_id', $user_id);
        $db->orderBy('id', 'desc');
        $user_activity = $db->get('xun_story_user_activity', 5);

        $user_activity_list = $this->get_user_activity_list($user_activity, $user_id);

        $story_id_arr = [];
        $tx_user_arr = [];
        $story_list = [];
        $i = 0;
        foreach($xun_story as $key=>$value){
            $story_id = $value["id"];

            if(!in_array($story_id, $story_id_arr)){
                array_push($story_id_arr, $story_id);
            }

            $i++;
            if($i == $total_story){
               
                $db->where('story_id', $story_id_arr, "IN");
                $db->where('story_type', "story");
                $story_updates = $db->map('story_id')->ArrayBuilder()->get('xun_story_updates');
                $story_updates_arr = [];

                foreach($story_updates as $updates_key => $updates_value){
                    $story_updates_id = $updates_value["id"];

                    if(!in_array($story_updates_id, $story_updates_arr)){
                        array_push($story_updates_arr, $story_updates_id);
                    }
                }

                $db->where('story_updates_id', $story_updates_arr, "IN");
                $story_media = $db->get('xun_story_media', null, "story_updates_id, media_url, media_type");

                $db->where('story_id', $story_id_arr, "IN");
                $total_share = $db->get('xun_story_share', null, "sum(count) as total_share");

                $db->where('id', $story_id_arr, "IN");
                $total_supporters = $db->get('xun_story', null, "sum(total_supporters) as total_supporters");

            }

        }    

        foreach($story_transaction as $tx_key => $tx_value){
            $tx_user_id = $tx_value["user_id"];

            if(!in_array($tx_user_id, $tx_user_arr)){
                array_push($tx_user_arr, $tx_user_id);
            }
        }

        if(empty($tx_user_arr)){
            $donator_result = [];
        }else{
            $db->where('id', $tx_user_arr, "IN");
            $donator_result = $db->map('id')->ArrayBuilder()->get('xun_user', null, "id, nickname,type");
        }

        foreach($xun_story as $key=>$value){
            $story_id = $value["id"];
            //$total_supporters = $value["total_supporters"];

            $story_updates_id = $story_updates[$story_id]["id"];
            $story_title = $story_updates[$story_id]["title"];
            $story_description = $story_updates[$story_id]["description"];

            $media_list = [];
            $donation_list = [];

            foreach($story_media as $media_key => $media_value){
                if($media_value["story_updates_id"] == $story_updates_id){
                    $media_url = $media_value["media_url"];
                    $media_type = $media_value["media_type"];

                    $media_array = array(
                        "media_url" => $media_url,
                        "media_type" => $media_type,
                    );

                    $media_list[] = $media_array;
                }
            }

            foreach($story_transaction as $tx_key => $tx_value){
                if($tx_value["story_id"]== $story_id){
                    $value = $tx_value["value"];
                    $currency_id = $tx_value["currency_id"];
                    $donator_user_id = $tx_value["user_id"];
                    $created_at = $tx_value["created_at"];
                    if($donator_user_id == 0){
                        $nickname = 'Anonymous';
                    }
                    else{
                        $nickname = $donator_result[$donator_user_id]['nickname'];         
                    }
                    

                    $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
                    $creditType = $decimal_place_setting["credit_type"];

                    $currency_name = $this->get_fiat_currency_name($currency_id);
                    $upper_currency_name = strtoupper($currency_name);

                    $new_donation_value = $setting->setDecimal($value, $creditType);
                    $donation_arr = array(
                        "donor_nickname" => $nickname,
                        "donation_value" => $new_donation_value,
                        "currency_name" => $upper_currency_name,
                        "created_at" =>  $created_at,
                    );

                    $donation_list[] = $donation_arr;

                }
            }

            $story_arr = array(
                "story_id" => $story_id,
                "title" => $story_title,
                "description" => $story_description,
                "media"=> $media_list,
                "donation_list"=>$donation_list,
            );

            $story_list[] = $story_arr;
            
        }

        $dashboard_details = array(
            "name" => $name,
            "picture_url" => $picture_url,
            "total_story" => $total_story,
            "total_supporters" => $total_supporters ? (int)$total_supporters[0]["total_supporters"] : 0,
            "total_share" => $total_share ? (int)$total_share[0]["total_share"] : 0,
            "story_list" =>  $story_list ? $story_list : [],
            "owner_activity_list" => $user_activity_list,

        );

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => 'Web Dashboard', 'data' => $dashboard_details);
        
    }

    public function web_owner_get_profile($params){
        $db = $this->db;

        // $username = trim($params["username"]);
        $user_id = trim($params["user_id"]);

        if ($user_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User ID is required.", 'developer_msg' => "User ID cannot be empty");
        }

        // if($user_id){
            // $db->where('id', $user_id);
        // }
        // else{
        //     $db->where('username', $username);
        // }

        $db->where('id', $user_id);
        $db->where("disabled", "0");
        $xun_user = $db->getOne('xun_user');
        if (!$xun_user)
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found.", 'developer_msg' => "User not found");

        $user_id = $xun_user["id"];

        // $db->where("disabled", "0");
        $db->where('user_id', $user_id);
        $user_details = $db->getOne("xun_user_details");
        // if(!$user_details)
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found", 'developer_msg' => "User not found");

        $returnData["nickname"] = $xun_user["nickname"] ? $xun_user["nickname"] : "";
        $returnData["username"] = $xun_user["username"];
        $returnData["email"] = $xun_user["email"] ? $xun_user["email"] : "";
        $returnData["bio"] = $user_details["bio"] ? $user_details["bio"] : "";
        $returnData["picture_url"] = $user_details["picture_url"] ? $user_details["picture_url"] : "";
        $returnData["location"] = $user_details["location"] ? $user_details["location"] : "";

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Get Owner profile successfully", 'data' => $returnData);
    }

    public function web_owner_change_password($params, $ip, $user_agent){
        $db                  = $this->db;
        $setting             = $this->setting;
        $language            = $this->general->getCurrentLanguage();
        $translations        = $this->general->getTranslations();

        // $username            = trim($params['username']);
        // $business_id         = trim($params['business_id']);
        $currentPassword     = trim($params['current_password']);
        $newPassword         = trim($params['new_password']);
        $newPasswordConfirm  = trim($params['new_password_confirm']);
        $user_id = trim($params["user_id"]);

        if ($user_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User_id is required.", 'developer_msg' => "user_id cannot be empty");
        }

        $db->where("id", $user_id);

        // get password length
        $maxPass  = "12";
        $minPass  = "6";
        // $maxTPass = $setting->systemSetting['maxTransactionPasswordLength'];
        // $minTPass = $setting->systemSetting['minTransactionPasswordLength'];
        // Get password encryption type
        $passwordEncryption  = $setting->getMemberPasswordEncryption();
        $idName        = 'Password';
        $msgFieldB     = 'Password';
        $msgFieldS     = 'password';
        $maxLength     = $maxPass;
        $minLenght     = $minPass;

        // if ($username == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty", 'developer_msg' => "username cannot be empty");
        // }

        // if($business_id){
        //     $db->where('id', $business_id);
        // }
        // else{
        //     $db->where('username', $username);
        // }

        if (empty($newPasswordConfirm)) {
            $errorFieldArr[] = array(
                                        'id'  => "new".$idName."ConfirmError",
                                        'msg' => "Please re-type ".  $msgFieldS
                                    );
        } else {
            if ($newPasswordConfirm != $newPassword) 
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."ConfirmError",
                                            'msg' => "Re-type new" . " " . $msgFieldS . " no match."
                                        );
        }

        // Retrieve the encrypted password based on settings
        $newEncryptedPassword = $this->getEncryptedPassword($newPassword);
        // Retrieve the encrypted currentPassword based on settings
        $encryptedCurrentPassword = $this->getEncryptedPassword($currentPassword);

        $db->where('disabled', 0);
        $user = $db->getOne('xun_user', "id, nickname, username, email, web_password, register_site");
        $user_id = $user['id'];
        $nickname = $user['nickname'];
        $mobile = $user['username'];
        if($user['email'] || $user['register_site'] == 'nuxstory'){
            $user_type = 'NuxStory';
        }
        else{
            $user_type = 'TheNux';
        }

        $xunIP = new XunIP($db);
        $ip_country = $xunIP->get_ip_country($ip);

        if (empty($user)) 
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found.", 'developer_msg' => "User not found");

        if (empty($currentPassword)) {
            $errorFieldArr[] = array(
                                        'id'  => "current".$idName."Error",
                                        'msg' => "Please enter old password."
                                    );
        } else {
            // Check password encryption
            if ($passwordEncryption == "bcrypt") {
                // We need to verify hash password by using this function
                if(!password_verify($currentPassword, $user['web_password'])) {
                    $errorFieldArr[] = array(
                                                'id'  => "current".$idName."Error",
                                                'msg' => "Invalid" . " " . $msgFieldS
                                            );
                }
            } else {
                if ($encryptedCurrentPassword != $user['web_password']) {
                    $errorFieldArr[] = array(
                                                'id'  => "current".$idName."Error",
                                                'msg' => "Invalid" . " " . $msgFieldS
                                            );
                }
            }
        }
        if (empty($newPassword)) {
            $errorFieldArr[] = array(
                                        'id'  => "new".$idName."Error",
                                        'msg' => "Please enter new" . " " . $msgFieldS
                                    );
        } else {
            if (strlen($newPassword) < $minLenght || strlen($newPassword) > $maxLength) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' => $msgFieldB . "cannot be less than" . " " . $minLenght . " " . "or more than". " " . $maxLength
                                        );
            } else {
                //checking new password no match with current password
                if ($passwordEncryption == "bcrypt") {
                    // We need to verify hash password by using this function
                    if(password_verify($newPassword, $user['web_password'])) {
                        $errorFieldArr[] = array(
                                                    'id'  => "new".$idName."Error",
                                                    'msg' => "Please enter different" . " " . $msgFieldS
                                                );
                    }
                } else {
                    if ($newEncryptedPassword == $user['web_password']) {
                        $errorFieldArr[] = array(
                                                    'id'  => "new".$idName."Error",
                                                    'msg' => "Please enter different" . " " . $msgFieldS
                                                );
                    }  
                }
            }
        }
        $data['field'] = $errorFieldArr;
        if($errorFieldArr){
            $error_message = $errorFieldArr[0]['msg'];

            $this->send_change_password_notification($nickname, $mobile, $ip, $ip_country, $user_agent, $user_type, "FAILED", $error_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Data does not meet requirements.", 'data' => $data);
        }
        $updateData = array('web_password' => $newEncryptedPassword);
        $db->where('id', $user_id);
        $updateResult = $db->update('xun_user', $updateData);
        if(!$updateResult){
            $status = "FAILED";
            $error_message = "Change Password Failed.";
            $this->send_change_password_notification($nickname, $mobile, $ip, $ip_country, $user_agent, $user_type, "FAILED", $error_message);
            
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Password Change Failed.");
        }
         

        $this->send_change_password_notification($nickname, $mobile, $ip, $ip_country, $user_agent, $user_type, "SUCCESS", "");
            
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Password Change Success.");

    }
        
    public function getEncryptedPassword($password) {
        $db = $this->db;
        $setting = $this->setting;
        
        // Get the stored password type.
        $passwordEncryption = $setting->getMemberPasswordEncryption();
        if ($passwordEncryption == "bcrypt") {
            return password_hash($password, PASSWORD_BCRYPT);
        }
        else if ($passwordEncryption == "mysql") {
            return $db->encrypt($password);
        }
        else return $password;
    }

    public function owner_edit_profile($params, $user_agent){
        $db = $this->db;

        // $username = trim($params['username']);
        // $business_id = trim($params["business_id"]);
        $user_id = trim($params["user_id"]);
        $nickname = trim($params['nickname']);
        $bio = trim($params['bio']);
        $photo = trim($params['photo']);
        $location = trim($params['location']);
        $sourceName = 'web';

        // if ($username == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty", 'developer_msg' => "username cannot be empty");
        // }

        // if($business_id){
        //     $db->where('id', $business_id);
        // }
        // else{
        //     $db->where('username', $username);
        // }

        if ($user_id == '') {
            $error_message = "User ID is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => "User ID is required.");
        }

        $db->where("id", $user_id);
        $db->where("disabled", "0");
        $xun_user = $db->getOne('xun_user');
        if (!$xun_user){
            $error_message = "User not found.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => "User not found.");
        }
            

        $user_id = $xun_user["id"];
        if($xun_user["register_site"] == 'nuxstory' || $xun_user["email"]){
            $user_type = "NuxStory";
        }
        else{
            $user_type = "TheNux";
        }

        $action_arr = [];
        if ($nickname){
            $updateData['nickname'] = $nickname;
            $action_arr[] = "Edit Nickname";   
        }
        else{
            $nickname = $xun_user["nickname"];
        }

        if ($bio){
            $updateDetailsData['bio'] = $bio;
            $action_arr[] = "Edit Bio";
        }
        if ($photo){
            $xun_user_service = new XunUserService($db);
            $profile_picture_result = $xun_user_service->uploadProfilePictureBase64($user_id, $photo, $user_type);
    
            $picture_url = '';
            if($profile_picture_result){
                $picture_url = $profile_picture_result["object_url"];
            }
            $updateDetailsData['picture_url'] = $picture_url;
            $action_arr[] = "Edit Photo";
        }
        if ($location){
            $updateDetailsData['location'] = $location;
            $action_arr[] = "Edit Location";
        }
        $updateDetailsData['updated_at'] = date("Y-m-d H:i:s");

        if (!empty($updateDetailsData)){
            $db->where("user_id", $user_id);
            $user_details = $db->getOne("xun_user_details");
            if (!$user_details){
                $updateDetailsData['user_id'] = $user_id;
                $updateDetailsData['created_at'] = date("Y-m-d H:i:s");

                $details_insert = $db->insert("xun_user_details", $updateDetailsData);
                if(!$details_insert){
                    $error_message = "Profile Insert Failed.";
                    $this->owner_edit_profile_notification($nickname, "", $user_agent, $user_type, $sourceName, "FAILED", $action, $error_message);
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $db->getLastError()); 
                }
                   
            }else{
                $db->where("user_id", $user_id);
                $details_update = $db->update("xun_user_details", $updateDetailsData);
                if(!$details_update){
                    $error_message = "Profile Update Failed.";
                    $this->owner_edit_profile_notification($nickname, "", $user_agent, $user_type, $sourceName, "FAILED", $action, $error_message);
                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $db->getLastError()); 
                }
                    
            }
        }

        if (!empty($updateData)){
            $db->where("id", $user_id);
            $nickname_update = $db->update("xun_user", $updateData); 
            if(!$nickname_update){
                $error_message ="Nickname Update Failed.";
                $this->owner_edit_profile_notification($nickname, "", $user_agent, $user_type, $sourceName, "FAILED", $action, $error_message);
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $db->getLastError());
            }
        }

        if($action_arr){
            $action = implode(", ", $action_arr);
        }
        
        $this->owner_edit_profile_notification($nickname, "", $user_agent, $user_type, $sourceName, "SUCCESS", $action, "");

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Profile Updated Success");   
    }

    public function owner_delete_account($params, $user_agent){
        $db = $this->db;

        // $username = trim($params["username"]);
        // $business_id = trim($params["business_id"]);
        $user_id = trim($params["user_id"]);

        if ($user_id == '') {
            $error_message = "User_id is required.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $error_message);
        }

        $db->where("id", $user_id);
        // if ($username == '') {
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username cannot be empty", 'developer_msg' => "username cannot be empty");
        // }

        // if($business_id){
        //     $db->where('id', $business_id);
        // }
        // else{
        //     $db->where('username', $username);
        // }

        $db->where("disabled", "0");
        $xun_user = $db->getOne('xun_user');
        if (!$xun_user){
            $error_message = "User not found.";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User not found", 'developer_msg' => "User not found");
        }
            

        $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];
        $mobile = $xun_user["username"] ? $xun_user["username"] : '';

        if($xun_user["register_site"] == 'nuxstory'){
            $user_type = "NuxStory";
        }
        else{
            $user_type = "TheNux";
        }

        $updateData['disabled'] = 1;

        $db->where("id", $user_id);
        $delete_account = $db->update("xun_user", $updateData);
        if(!$delete_account){
            $error_message = "Account Failed To Delete.";
            $this->send_delete_account_notification($nickname, $mobile, $user_agent, $user_type, "FAILED", $error_message);
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, 'developer_msg' => $db->getLastError());
        }
            
        $return_message = "Account Deleted Successfully.";
        $this->send_delete_account_notification($nickname, $mobile, $user_agent,$user_type, "SUCCESS", $return_message);

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $return_message);
    }

    public function web_get_user_activity_list($params){
        $db = $this->db;

        $user_id = trim($params["user_id"]);
        $last_id = $params["last_id"] ? $params["last_id"] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 10;

        if($user_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User ID is required.", 'developer_msg' => "User ID cannot be empty");
        }

        $limit = array($last_id, $page_size);
        $db->where('user_id', $user_id);
        $db->orderBy('id', "DESC");
        $copyDb= $db->copy();
        $user_activity = $db->get('xun_story_user_activity', $limit);

        $totalRecord = $copyDb->getValue('xun_story_user_activity', "count(id)");

        $db->where('story_type', "story");
        $xun_story_updates = $db->map('story_id')->ArrayBuilder()->get('xun_story_updates', null, 'id, story_id, title');

        $db->where('user_id', $user_id);
        $story_comment = $db->map('id')->ArrayBuilder()->get('xun_story_comment');

        $activity_list = [];
        foreach($user_activity as $key=>$value){
            $activity_type = $value["activity_type"];
            $reference_id = $value["reference_id"];
            $created_at = $value["created_at"];
            $info = $value["info"];
            $info = json_decode($info);
            $value = '';
            $currency_name = '';
            $story_title = '';

            switch($activity_type){

                case "story":
                    $story_title = $xun_story_updates[$reference_id]["title"];
                    $message = "Your story <b>$story_title</b> was published. People can see your story now and start helping you!";

                    break;

                case "updates":
                    $story_title = $xun_story_updates[$reference_id]["title"];    
                    $message = "You posted a new update on <b>$story_title</b>.";

                    break;

                case "save_story":

                    $story_title = $xun_story_updates[$reference_id]["title"];
                    $message = "You liked <b>$story_title</b> story.";

                    break;

                case "comment":
                    $story_id = $story_comment[$reference_id]["story_id"];
                    $story_title = $info->story_title ? $info->story_title : $xun_story_updates[$story_id]["title"];
                    $message = "You leave a comment on <b>$story_title</b>.";

                    break;

                case "donation":
                    $story_title = $info->story_title;
                    $value = $info->value;
                    $currency_name = $info->name;

                    $message = "You supported $value$currency_name to <b>$story_title</b>.";

                    break;

                case "withdraw":
                    $story_id = $story_transaction[$reference_id]["story_id"];
                    $title = $xun_story_updates[$story_id]["title"];
                    
                    $value = $info->value;
                    $currency_name = $info->name;

                    $message = "You withdraw $value$currency_name from <b>$title</b>.";

                    break;
                
                case "share_story":
                    $story_title = $info->story_title;
                    $message = "You shared <b>$story_title</b>.";
                    
                    break;
                
                case "story_expired":
                    $story_title = $info->story_title;
                    $message = "Your story <b>$story_title</b> has expired!";
                    
                    break;

                default :
                    $message = '';
                    
                    break;
            }
            $activity_data = array(
                "activity_message" => $message, 
                "created_at" => $created_at,
            );

            $activity_list[] = $activity_data;
           
        }

        $numRecord = count($activity_list);
        $returnData["activity_list"] = $activity_list;
        $returnData["totalRecord"] = $totalRecord;
        $returnData["numRecord"] = $numRecord;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        $returnData["last_id"] = $last_id + $numRecord;
        
       return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Web Activity List", "data" => $returnData);
    }
    
    //standard function to get user activity list(story_user_activity)
    private function get_user_activity_list($user_activity, $user_id){
        $db = $this->db;
        $activity_list = [];

        $db->where('story_type', 'story');
        $xun_story_updates = $db->map('story_id')->ArrayBuilder()->get('xun_story_updates', null, 'id, story_id, title');

        $db->where('user_id', $user_id);
        $story_comment = $db->map('id')->ArrayBuilder()->get('xun_story_comment');

        foreach($user_activity as $key=>$value){
            $activity_type = $value["activity_type"];
            $reference_id = $value["reference_id"];
            $created_at = $value["created_at"];
            $info = $value["info"];
            $info = json_decode($info);
            $value = '';
            $currency_name = '';
            $story_title = '';

            switch($activity_type){

                case "story":
                    $story_title = $xun_story_updates[$reference_id]["title"];
                    $message = "Your story <b>$story_title</b> was published. People can see your story now and start helping you!";

                    break;

                case "updates":
                    $story_title = $xun_story_updates[$reference_id]["title"];    
                    $message = "You posted a new update on <b>$story_title</b>.";

                    break;

                case "save_story":

                    $story_title = $xun_story_updates[$reference_id]["title"];
                    $message = "You liked <b>$story_title</b> story.";

                    break;

                case "comment":
                    $story_id = $story_comment[$reference_id]["story_id"];
                    $story_title = $info->story_title ? $info->story_title : $xun_story_updates[$story_id]["title"];
                    $message = "You leave a comment on <b>$story_title</b>.";

                    break;

                case "donation":
                    $story_title = $info->story_title;
                    $value = $info->value;
                    $currency_name = $info->name;

                    $message = "You supported $value$currency_name to <b>$story_title</b>.";

                    break;

                case "withdraw":
                    $story_id = $story_transaction[$reference_id]["story_id"];
                    $title = $xun_story_updates[$story_id]["title"];
                    
                    $value = $info->value;
                    $currency_name = $info->name;

                    $message = "You withdraw $value$currency_name from <b>$title</b>.";

                    break;
                
                case "share_story":
                    $story_title = $info->story_title;
                    $message = "You shared <b>$story_title</b>.";
                    
                    break;
                
                case "story_expired":
                    $story_title = $info->story_title;
                    $message = "Your story <b>$story_title</b> has expired!";
                    
                    break;

                default :
                    $message = '';
                    
                    break;
            }
            $activity_data = array(
                "activity_message" => $message, 
                "created_at" => $created_at,
            );

            $activity_list[] = $activity_data;
           
        }

        return $activity_list;

        
    }

    private function validate_web_password($password)
    {
        // if (preg_match("/^.*(?=.{4,})(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z]).*$/", $password) === 0) {
        // $error_message = array("- Minimum 4 characters", "- At least 1 alphabet", "- At least 1 numeric", "- At least 1 capital letter");
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid password combination.", "developer_msg" => "business_password has an invalid character combination", "error_message" => $error_message);
        // }

        $length = strlen($password);
        $minimum_length = 6;

        if ($length < $minimum_length) {
            $error_message = array("- Minimum $minimum_length characters");
            return array('code' => 0, "error_message" => $error_message);
        }
    }

    private function get_fiat_currency_list($col = ''){
        $db = $this->db;

        $db->where('status', 1);
        $db->where('type', "currency");
        $currency_result = $db->map('symbol')->ObjectBuilder()->get("xun_marketplace_currencies",null, $col);

        $currency_list = [];
        foreach($currency_result as $key=>$value){
            $currency_id = strtoupper($value->currency_id);
            $name = strtoupper($value->symbol);
            $image = $value->image;
            $image_md5 = $value->image_md5;

            $currencyArr = array(
                "currency_id" => $currency_id,
                "currency_name" => $name,
                "image" => $image,
                "image_md5" => $image_md5,
            );

            $currency_list [] = $currencyArr;
        }

        return $currency_list;
    }
    //for web api
    public function validate_user_id($user_id){
        $db = $this->db;

        $db->where('id', $user_id);
        $db->where("disabled", 0);
        $xun_user = $db->getOne('xun_user');

        if ($user_id == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User_ID is required.", 'developer_msg' => "User_ID is required.");
        }

        if(!$xun_user){
             return array('code' => 0, 'message' => "FAILED", 'message_d' => "User doesn't exist.");
         }
        else{
            return $xun_user;
        }

    }
    
    //for app api
    public function validate_username($username, $business_id = ''){
        $db = $this->db;
        
        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username is required.", 'developer_msg' => "Username is required.");
        }

        if($business_id){
            $db->where('id', $business_id);
        }
        else{
            $db->where('username', $username);
        }

        $db->where("disabled", 0);
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User doesn't exist.");
        }
       else{
           return $xun_user;
       }
    }

    
    public function web_story_qr_login_callback($params){
        $post = $this->post;
        $db = $this->db;

        // decode the raw_url first
        $params['raw_url'] = urldecode($params['raw_url']);

        foreach (explode('&', explode('?', $params['raw_url'])[1]) as $value) {

            $value = explode('=', $value);
            $urlParams[$value[0]] = $value[1];
        }

        $phoneNumber =  $params['mobile'];
        $token       = $urlParams['token'];
        $source      = $urlParams['source'];

        $date = date('Y-m-d H:i:s');

        $db->where('username', $phoneNumber);
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.", 'developer_msg' => "User does not exist.$phoneNumber");
        }

        $user_id = $xun_user["id"];

        $db->where('access_token', $token);
        $db->where('login', 0);
        $login_token = $db->getOne('xun_user_login_token');

        if(!$login_token){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid token.", 'developer_msg' => "Token has been used or token not found");
        }

        // if($source == 'Web'){
            
    
        // }

        $update_login_token = array(
            "login_user_id" => $user_id,
        );

        $db->where('access_token', $token);
        $db->update('xun_user_login_token', $update_login_token);

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "SUCCESS");
          
    }

    public function web_get_user_login_status($params){
        $db = $this->db;

        $token = $params["token"];

        if($token == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Token details required.", 'developer_msg' => "Token details required.");
        }
        $date = date("Y-m-d H:i:s");

        $db->where('access_token', $token);
        $db->where('expired_at', $date, ">=");
        $db->where('login', 0);
        $login_token = $db->getOne('xun_user_login_token');
        
        $return_data = [];
        if(!$login_token){
            $return_data["status"] = "failed";
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Token.", 'developer_msg' => "Token not found/Token expired or Token is used", "data" => $return_data);
        }

        if($login_token["login_user_id"] == 0){
            $return_data["status"] = "pending";
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "New token", "data" => $return_data);
        }

        $user_id = $login_token["login_user_id"];
        $login_token_id = $login_token["id"];

        $update_login_token = array(
            "login" => 1,
            "login_at" => $date,
        );

        $db->where('id', $login_token_id);
        $update_login = $db->update('xun_user_login_token', $update_login_token);

        if(!$update_login){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Update login data failed.", 'developer_msg' => $db->getLastError());
        }

        $db->where('id', $user_id);
        $xun_user = $db->getOne('xun_user', "id, username, email, nickname");

        $username = $xun_user["username"];
        $nickname = $xun_user["nickname"];

        $db->where('user_id', $user_id);
        $user_detail = $db->getOne('xun_user_details', "picture_url");

        $picture_url = $user_detail["picture_url"] ? $user_detail["picture_url"] : '';

        $details_arr = array(
            "status" => "success",
            "user_id" => $user_id,
            "username" => $username,
            "nickname" => $nickname,
            "picture_url" => $picture_url,
        );

        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Login Status", 'data'=> $details_arr);
    }

    public function web_story_forgot_password($params, $ip, $user_agent){
        global $general;
        $db = $this->db;

        $email = $params["email"];

        if($email == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Email is required.", 'developer_msg' => "Email is required.");
        }
       
        $db->where('email', $email);
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User doesn't exist.", 'developer_msg' => "User doesn't exist");
        }
        $name = $xun_user["nickname"];
        $mobile = $xun_user["mobile"];
        $user_type = $xun_user["register_site"] == 'nuxstory' ? 'NuxStory' : 'TheNux';

        $xunIP = new XunIP($db);
        $ip_country = $xunIP->get_ip_country($ip);

        $new_password = $general->generateAlpaNumeric(16);
        $hash_password = password_hash($new_password, PASSWORD_BCRYPT);

        $update_password = array(
            "web_password" => $hash_password,
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $db->where('email', $email);
        $update_pass = $db->update('xun_user', $update_password);

        if(!$update_pass){
            $status = "FAILED";
            $error_message = "Something went wrong, Please try again.";
            return array('code' => 0, 'message' => $status, 'message_d' => $error_message, 'developer_msg' => $db->getLastError());
        }

         //  send forget password  email
         $send_email_result = $this->send_forgot_password_email($email, $new_password);


         return array("code" => 1, "message" => $status, "message_d" => "Forgot password successfully done.");

    }

    public function send_forgot_password_email($email, $password)
    {
        global $xunEmail, $provider, $setting;
        $general = $this->general;

        $companyName = $setting->systemSetting["storyCompanyName"];

        $email_body = $xunEmail->getStoryForgetPasswordEmailHtml($email,$password, $companyName);

        //$translations_message = $this->get_translation_message('B00077') /*Reset Your %%companyName%% Business Password*/;
        $translations_message = "Reset Your %%companyName%% Password";
        $subject = str_replace("%%companyName%%", $companyName, $translations_message);

        $provider_details = $provider->getProviderByName("nuxstory_email");

        $emailParams["subject"] = $subject;
        $emailParams["body"] = $email_body;
        $emailParams["recipients"] = array($email);
        $emailParams["emailFromName"] = $companyName;
        $emailParams["emailAddress"] = $provider_details["username"];
        $emailParams["emailPassword"] = $provider_details["password"];

        $result = $general->sendEmail($emailParams);
    }

    public function web_get_my_donation_list($params){
        global $xunCurrency;
        $db = $this->db;
        $setting = $this->setting;

        $user_id = $params["user_id"];
        $last_id = $params["last_id"] ? $params["last_id"] : 0;
        $page_size = $params["page_size"] ? $params["page_size"] : 10;

        if($user_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User id is required.", 'developer_msg' => "User id cannot be empty");
        }
        
        $limit = array($last_id, $page_size);

        $db->where('id', $user_id);
        $xun_user = $db->getOne('xun_user', "username, nickname, email");

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist.", 'developer_msg' => "User does not exist");
        }

        $db->where('user_id', $user_id);
        $xun_story = $db->get('xun_story', null, 'id');

        $story_id_arr = [];
        $story_updates_id_arr = [];
        $my_donation_list = [];
        foreach($xun_story as $key => $value){
            $story_id = $value["id"];

            if(!in_array($story_id, $story_id_arr)){
                array_push($story_id_arr, $story_id);
            }
        }

        if($story_id_arr){
            $db->where('story_id', $story_id_arr, "IN");
            $copyDb= $db->copy();
            $copyDb2 = $db->copy();
            $db->where('story_type', "story");
            $story_updates = $db->map('story_id')->ArrayBuilder()->get('xun_story_updates', null, 'id,story_id,title');
            $copyDb->orderBy('id','DESC');
            $story_tx= $copyDb->get('xun_story_transaction', $limit, 'id,user_id, story_id, value, currency_id');
    
            $totalRecord = $copyDb2->getValue('xun_story_transaction', 'count(id)');

            foreach($story_updates as $updates_key => $updates_value){
                $story_updates_id = $updates_value["id"];
                
                if(!in_array($story_updates_id, $story_updates_id_arr)){
                    array_push($story_updates_id_arr, $story_updates_id);
                }
           
            }

            $db->where('story_updates_id', $story_updates_id_arr, "IN");
            $story_media = $db->get('xun_story_media', null, "id,story_updates_id,media_url, media_type");

            $user_id_arr = [];
            foreach($story_tx as $tx_key => $tx_value){
                $tx_user_id = $tx_value["user_id"];
            
                if(!in_array($tx_user_id, $user_id_arr)){
                    array_push($user_id_arr, $tx_user_id );
                }
    
            }
    
           // print_r($story_tx);
            $db->where('id', $user_id_arr, "IN");
            $tx_user = $db->map('id')->ArrayBuilder()->get('xun_user', null, "id, username, nickname, email");
    
            $db->where('user_id', $user_id_arr, "IN");
            $tx_user_details = $db->map('user_id')->ArrayBuilder()->get('xun_user_details', null, 'user_id, picture_url');
        
            foreach($story_tx as $tx_key => $tx_value){
                $story_transaction_id = $tx_value["id"];
                $tx_user_id = $tx_value["user_id"];
                $story_id = $tx_value["story_id"];
                $donation_amount = $tx_value["value"];
                $currency_id = $tx_value["currency_id"];
 
                $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
                $creditType = $decimal_place_setting["credit_type"];
                $donation_amount = $setting->setDecimal($donation_amount, $creditType);
     
                $currency_name = $this->get_fiat_currency_name($currency_id);
                $uc_currency_name = strtoupper($currency_name);
                $donor_picture_url = $tx_user_details[$tx_user_id];
                $story_title = $story_updates[$story_id]["title"];
                $story_updates_id = $story_updates[$story_id]["id"];

                //print_r($story_media);
                $media_list = [];
                foreach($story_media as $media_key => $media_value){
                    $media_story_updates_id = $media_value["story_updates_id"];
                    $media_url = $media_value["media_url"];
                    $media_type = $media_value["media_type"];

                    if($story_updates_id == $media_story_updates_id){
                        $media_arr = array(
                            "media_url" => $media_url,
                            "media_type" => $media_type,
                        );

                        $media_list[] = $media_arr;

                    }
                }
                
    
                if($tx_user_id == 0){
                    $nickname = "Anonymous";
                }
                else{
                    $nickname = $tx_user[$tx_user_id]["nickname"];
                }
                
                $donation_arr = array(
                    "donor_nickname" => $nickname,
                    "donor_picture_url" => $donor_picture_url ? $donor_picture_url : '',
                    "story_transaction_id" => $story_transaction_id,
                    "donation_amount" => $donation_amount,
                    "currency_id" => $currency_id,
                    "currency_name" => $uc_currency_name,
                    "story_title" => $story_title,
                    "media" => $media_list,
                );
                $my_donation_list[] = $donation_arr;
                
            }
        }
        // print_r($story_id_arr);
        

        $numRecord = count($my_donation_list);
        $returnData["my_donation_list"] = $my_donation_list;
        $returnData["totalRecord"] = $totalRecord ? $totalRecord : 0;
        $returnData["numRecord"] = $numRecord;
        $returnData["totalPage"] = ceil($totalRecord / $page_size);
        $returnData["last_id"] = $last_id + $numRecord;
        
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "My donation list.", "data"=> $returnData);
   
    }

    public function web_get_donation_details($params){
        global $xunCurrency;
        $db= $this->db;
        $setting = $this->setting;

        $story_transaction_id = $params["story_transaction_id"];

        if($story_transaction_id ==''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story transaction ID is required.", 'developer_msg' => "Story transaction id cannot be empty");
        }

        $db->where('id', $story_transaction_id);
        $story_tx = $db->getOne('xun_story_transaction', 'story_id, user_id, wallet_type, value, currency_id, status, updated_at');

        if(!$story_tx){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Donation does not exist.", 'developer_msg' => "Donation does not exist");
        }

        $story_id = $story_tx["story_id"];
        $wallet_type = $story_tx["wallet_type"];
        $donation_amount = $story_tx["value"];
        $currency_id = $story_tx["currency_id"];
        $donator_user_id = $story_tx["user_id"];
        $donation_status = $story_tx["status"];
        $updated_at = $story_tx["updated_at"];

        $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
        $creditType = $decimal_place_setting["credit_type"];
        $donation_amount = $setting->setDecimal($donation_amount, $creditType);

        $currency_name = $this->get_fiat_currency_name($currency_id);
        $uc_currency_name = strtoupper($currency_name);

        $xun_coins = $db->map('currency_id')->ArrayBuilder()->get('xun_coins');

        if($xun_coins[$wallet_type]){
            $pm_name = "Cryptocurrency";
        }
        else{
            $db->where('story_transaction_id', $story_transaction_id);
            $story_donation = $db->getOne('xun_story_donation');
            $pm_id = $story_donation['payment_method_id'];
            $country = $story_donation['country'];
            $phone_number = $story_donation['phone_number'];
            $email_address = $story_donation['email'];
     
            $db->where('a.id', $pm_id);
            $db->join("xun_marketplace_payment_method b", "a.payment_method_id=b.id", "LEFT");
            $xun_marketplace_pm = $db->getOne('xun_story_payment_method a', 'b.name');
            $pm_name = $xun_marketplace_pm["name"];
    
        }

        $db->where('story_id', $story_id);
        $db->where('story_type', "story");
        $story_updates = $db->getOne('xun_story_updates', 'id, title, description');
        $story_title = $story_updates["title"];
        $story_description = $story_updates["description"];
        $story_updates_id = $story_updates["id"];

        $db->where('id', $story_id);
        $xun_story = $db->getOne('xun_story', 'user_id, category_id');

        $owner_user_id = $xun_story["user_id"];
        $category_id = $xun_story["category_id"];
        $db->where('id', $category_id);
        $story_category = $db->getOne('xun_story_category', "category");

        $category = $story_category['category'];

        $db->where('id', $donator_user_id);
        $xun_user = $db->getOne('xun_user', 'nickname');

        $nickname = $xun_user["nickname"];

        $db->where('story_updates_id', $story_updates_id);
        $story_media = $db->get('xun_story_media', null, "media_url, media_type");

        $donation_details = array(
            "nickname" => $nickname,
            "category" =>$category,
            "story_title" => $story_title,
            "description" => $story_description,
            "payment_method" => $pm_name,
            "status" => $donation_status,
            "country" => $country ? $country : '',
            "email_address" => $email_address ? $email_address : '',
            "phone_number" => $phone_number ? $phone_number : '',
            "updated_at" => $updated_at,
            "donation_amount" => $donation_amount,
            "currency_id" => $currency_id,
            "currency_name" => $uc_currency_name,
            "media" => $story_media,

        );

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "My donation details.", "data"=> $donation_details);
        
    }

    public function web_my_story_details($params){
        
        global $xunCurrency;

        $db = $this->db;
        $general = $this->general;
        $setting = $this->setting;
        
        $user_id = trim($params["user_id"]);
        $story_id = $params["story_id"];

        if ($user_id == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "user_id is required.", 'developer_msg' => "user_id cannot be empty");
        }

        if($story_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "story_id is required.", 'developer_msg' => "story_id cannot be empty");
        }

        $xun_user = $this->validate_user_id($user_id);
        if(isset($xun_user["code"]) && $xun_user["code"] == 0){
            return $xun_user;
        }

        // $db->where("disabled", 0);
        // $xun_user = $db->getOne('xun_user');
        // $user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];
        $user_type = $xun_user["type"];
        //$username = $xun_user["username"];

        $db->where('id', $story_id);
        $db->where('user_id', $user_id);
        $xun_story = $db->getOne('xun_story');

        if(!$xun_story){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Story not found!", 'developer_msg' => "Story not found!");
        }

        $db->where('story_id',$story_id);
        $db->orderBy('created_at', "DESC");
        $xun_story_updates = $db->get('xun_story_updates');
        
        $db->where('id', $xun_story["category_id"]);
        $xun_story_category = $db->getOne('xun_story_category','category');

        $user_id = $xun_story["user_id"];
        $total_supporters = $xun_story["total_supporters"];
        $story_currency_id = $xun_story["story_currency_id"];

        foreach($xun_story_updates as $key=>$value){
            $story_updates_id = $value["id"];
            $title = $value["title"];
            $description = $value["description"];
            $story_id = $value["story_id"];

            $db->where('story_updates_id', $story_updates_id);
            $db->orderBy('id', "ASC");
            $media_data = $db->get('xun_story_media', null, "media_url, media_type");

            if($value["story_type"] == "story"){
                $recommended = $xun_story["recommended"];
                $fund_amount = $xun_story["fund_amount"];
                $fund_collected = $xun_story["fund_collected"];
                $currency_id = strtolower($xun_story["currency_id"]);

                $currency_name = $this->get_fiat_currency_name($currency_id);
        
                $decimal_place_setting = $xunCurrency->get_currency_decimal_places($currency_id, true);
                $creditType = $decimal_place_setting["credit_type"];
                $fund_amount = $setting->setDecimal($fund_amount, $creditType);
                $fund_collected = $setting->setDecimal($fund_collected, $creditType);
                if($fund_amount){
                    $supportedPercentage = number_format((float)($fund_collected/$fund_amount) *100,2, '.', '');
                }

                $user_verified = 0;
                $story_saved = 0;
                $db->where("user_id", $user_id);
                $db->where("status", "approved");
                $db->orderby("id", "desc");	
                $kyc_record = $db->getOne("xun_kyc");

                if($kyc_record){
                    $user_verified = 1;
                }

                $db->where("story_id", $story_id);
                $db->where("user_id", $user_id);
                $saved_story = $db->getOne("xun_story_favourite");

                if($saved_story){
                    $story_saved = 1;
                }

                $db->where('story_id',$story_id);
                $totalComment = $db->getValue('xun_story_comment','count(id)');

                $story_data = array(
                    "id" => $story_updates_id,
                    "story_id" => $story_id,
                    "title" => $title,
                    "description" => $description,
                    "recommended" => $recommended,
                    "total_comment" =>  $totalComment,
                    "category" => $xun_story_category["category"],
                    "fund_amount" =>  $fund_amount,
                    "fund_collected" => $fund_collected,
                    "fund_collected_pct" => (string)$supportedPercentage,
                    "total_supporters" => $total_supporters,
                    "currency_name" =>  strtoupper($currency_name),
                    "nickname" => $nickname,
                    //"user_type" => $user_type,
                    //"username" => $username,
                    //"user_verified" => $user_verified,
                    "story_saved" => $story_saved,
                    "media" => $media_data,
                    //"story_currency" => $story_currency_id,
                    "currency_id" => $currency_id,
                    "updated_at" => $xun_story["updated_at"],

                );
                
                $main_story[] = $story_data;
            }
            else{
                $updates_data = array(   
                    "id" => $story_updates_id,
                    "title" => $title,
                    "description" =>$description,
                    "media" => $media_data,
                    "updated_at" =>$value["created_at"],  
                );
                
                $updates_data_arr[]= $updates_data;
            }
            
            if(!$updates_data_arr){
                $updates["details"] = [];
                
            }
            else{
                $updates["details"] = $updates_data_arr;
            }
        }

        $returnData["story"] = $story_data;
        $returnData["updates"] = $updates;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "My Story Details.", "data" => $returnData);    
        
    }

    public function web_get_country_list($params){

        $country_list = $this->get_country_list(null);
        foreach($country_list as &$data){
            unset($data['currency_id']);

        }
        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Country list.", "data" => $country_list);    
    }

    public function create_story_notification($nickname, $phone_number, $device,  $user_type, $platform, $status, $error_message, $title, $country, $url = null){

        $message = "Username: ".$nickname."\n";
        $message .="Phone number: ".$phone_number."\n";
        $message .="Device: ".$device. "\n";
        $message .="Type of User: ".$user_type."\n";
        $message .="Platform: ".$platform."\n\n";

        $message .="Status: ".$status."\n";
        $message .="Error message: ".$error_message."\n";
        $message .="Title: ".$title."\n";
        $message .="Country: ".$country."\n";
        $message .="URL: ".$url."\n";
        $message .="Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Success Create Story";

        $this->send_story_notification($tag, $message);

    }

    public function owner_edit_profile_notification($nickname, $phone_number, $device, $user_type, $platform, $status, $action, $error_message){
        $message = "Username: " .$nickname."\n";
        $message .= "Phone number: " .$phone_number."\n";
        $message .= "Device: ".$device."\n";
        $message .= "Type of user: ".$user_type."\n";
        $message .= "Platform: ".$platform."\n\n";
        $message .= "Status: ".$status."\n";
        $message .= "Action: ".$action."\n";
        $message .= "Error message: ".$error_message."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Story Owner Activity";

        $this->send_story_notification($tag, $message);
    }

    public function send_story_notification($tag, $message){
        global $xunXmpp;
        $params["tag"] = $tag;
        $params["message"] = $message;
        
        $return = $xunXmpp->send_xmpp_notification($params, "thenux_story");

        return $return;

    }

    //app user get ip and country
    public function get_user_ip_and_country($user_id){
        $db = $this->db;

        $db->where('user_id', $user_id);
        $db->where('name', array("lastLoginIP", "ipCountry"), "IN");
        $user_setting = $db->map('name')->ArrayBuilder()->get('xun_user_setting');

        return $user_setting;
    }

    public function get_user_device_info($mobile){

        $db= $this->db;
        $db->where('mobile_number', $mobile);
        $device_info = $db->getOne('xun_user_device');

        return $device_info;
    }

    public function send_fiat_donation_notification($username, $user_type, $phone_number = null, $email = null,  $ip = null, $country= null, $device = null, $status, $title, $error_message, $amount){
        $message = "Username: ".$username."\n";
        $message .= "Type of User: ".$user_type."\n";
        $message .= "Phone number: ".$phone_number."\n";
        $message .= "Email Address: ".$email."\n";
        $message .= "IP: ".$ip."\n";
        $message .= "Country: ".$country."\n";
        $message .= "Device: ".$device."\n\n";

        $message .= "Status: ".$status."\n";
        $message .= "Title: " .$title."\n";
        $message .= "Error Message: ".$error_message."\n";
        $message .= "Amount: ".$amount."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Donate with Fiat";

        $return = $this->send_story_notification($tag, $message);

    }

    public function send_create_updates_notification($nickname, $phone_number = null, $device, $user_type, $platform, $status, $title, $error_message){
        $message .= "Username: ".$nickname."\n";
        $message .= "Phone number: ".$phone_number."\n";
        $message .= "Device: ".$device."\n";
        $message .= "Type of User: ".$user_type."\n";
        $message .= "Platform: ".$platform."\n\n";

        $message .= "Status: ".$status."\n";
        $message .= "Title: " .$title."\n";
        $message .= "Error message: ".$error_message."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Update Story";

        $this->send_story_notification($tag, $message);
        
    }

    public function send_comment_notification($username, $phone_number, $device, $user_type, $platform, $title, $comment){
        $message = "Username: ".$username."\n";
        $message .= "Phone number: ".$phone_number."\n";
        $message .= "Device: ".$device."\n";
        $message .= "Type of User: ".$user_type."\n";
        $message .= "Platform: ".$platform."\n\n";
        
        $message .= "Title: ".$title."\n";
        $message .= "Message: ".$comment."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Story Comment";

        $return = $this->send_story_notification($tag, $message);

        return $return;
        
    }

    public function send_story_share_notification($username, $phone_number, $user_type, $platform, $title){
        $message = "Username: ".$username."\n";
        $message .= "Phone number: ".$phone_number."\n";
        $message .= "Type of User: ".$user_type."\n";
        $message .= "Platform: ".$platform."\n\n";
        $message .= "Title: ".$title."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Story Shared";

        $this->send_story_notification($tag, $message);


    }

    public function send_delete_account_notification($nickname, $phone_number, $device, $user_type, $status, $error_message){
        $message = "Username: ".$nickname."\n";
        $message .= "Phone number: ".$phone_number. "\n";
        $message .= "Device: ".$device."\n";
        $message .= "Type of User: ".$user_type."\n\n";
        $message .= "Status: ".$status."\n";
        $message .= "Message: ".$error_message."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Delete Account";

        $this->send_story_notification($tag, $message);
    }

    public function send_crypto_donation_notification($nickname = null, $phone_number = null, $email = null, $country = null, $device = null, $user_type = null, $status , $title, $from_address, $to_address, $coin, $amount){
        $message = "Username: ".$nickname."\n";
        $message .= "Phone number: ".$phone_number."\n";
        $message .= "Email Address: ".$email."\n";
        $message .= "Country: ".$country."\n";
        $message .= "Device: ".$device."\n";
        $message .= "Type of User: ".$user_type."\n\n";

        $message .= "Status: ".$status."\n";
        $message .= "Title: ".$title."\n";
        $message .= "From Address: ".$from_address."\n";
        $message .= "To Address: ".$to_address."\n";
        $message .= "Coin: ".$coin."\n";
        $message .= "Amount: ".$amount."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Donate with Cryptocurrency";

        $this->send_story_notification($tag, $message); 

    }

    public function send_login_notification($nickname, $mobile, $user_type, $ip, $country, $device, $status, $error_message, $email= null){
        $message = "Username: ".$nickname."\n";
        $message .= "Phone number: ".$mobile."\n";
        $message .= "Type of User: ".$user_type."\n";
        $message .= "IP: ".$ip."\n";
        $message .= "Country: ".$country."\n";
        $message .= "Device: ".$device."\n\n";

        $message .= "Email Address: " .$email."\n";
        $message .= "Status: ".$status."\n";
        $message .= "Error message: ".$error_message."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Login";
        $this->send_story_notification($tag, $message);

    }

    public function send_register_notification($nickname, $mobile, $ip, $country, $device, $email, $status, $error_message){
        $message = "Username: ".$nickname."\n";
        $message .= "Phone number: ".$mobile."\n";
        $message .= "IP: ".$ip."\n";
        $message .= "Country: ".$country."\n";
        $message .= "Device: ".$device."\n\n";

        $message .= "Email Address: ".$email."\n";
        $message .= "Status: ".$status."\n";
        $message .= "Error message: ".$error_message."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "New Sign Up";

        $this->send_story_notification($tag, $message);
    }

    public function send_request_verification_notification($mobile, $ip, $country, $device, $verify_code){
        $message = "Phone number: ".$mobile."\n";
        $message = "IP: ".$ip."\n";
        $message .= "Country: ".$country."\n";
        $message .= "Device: ".$device."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Request Verification Code";

        $this->send_story_notification($tag, $message);
    }

    public function send_change_password_notification($nickname, $mobile, $ip, $country, $user_agent, $user_type, $status, $error_message){
        $message  = "Username: ".$nickname."\n";
        $message .= "Phone number: ".$mobile."\n";
        $message .= "IP: ".$ip."\n";
        $message .= "Country: ".$country."\n";
        $message .= "Device: ".$user_agent."\n";
        $message .= "Type of User: ".$user_type."\n\n";

        $message .= "Status: ".$status."\n";
        $message .= "Error message: ".$error_message."\n";
        $message .= "Time: ".date("Y-m-d H:i:s")."\n";

        $tag = "Reset Password";
        $this->send_story_notification($tag, $message);
    }

}

