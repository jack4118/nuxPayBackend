<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/

class XunAnnouncement
{

    public function __construct($db, $setting, $general)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
    }
    
    public function announcement_create($params)
    {
        $db = $this->db;

        $scheduled_date = date("Y-m-d H:i:s", $params["scheduled_date"]);
        $time_zone = $params["time_zone"];
        $s3_link = trim($params["s3_link"]);
        $title = trim($params["title"]);
        $description = trim($params["description"]);
        $audience_number = $params["audience_number"];
        $audience_id = $params["audience_id"];
        $valid_days = $params["valid_days"];
        $button_type = $params["button_type"];
        $button_name = $params["button_name"];
        $button_link = $params["button_link"];
        
        $date = date("Y-m-d H:i:s");
        
        if ($scheduled_date == '') {
            //$error_message = $this->get_translation_message('E00026');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Scheduled date cannot be empty");
		}
        
        if ($time_zone == '') {
            //$error_message = $this->get_translation_message('E00021');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "time zone cannot be empty");
		}
        
        if ($s3_link == '') {
            //$error_message = $this->get_translation_message('E00022');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "S3 Link cannot be empty");
		}
        
        if ($title == '') {
            //$error_message = $this->get_translation_message('E00022');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Title cannot be empty");
		} 
        
        if ($description == '') {
            //$error_message = $this->get_translation_message('E00022');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Description cannot be empty");
		} 
    
        if ($audience_number && !is_array($audience_number)) {
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Audience number must be in array format");
        }
        
        if ($audience_id && !is_array($audience_id)) {
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Audience ID must be in array format");
        }
        
//        if ($audience_id == '') {
//            //$error_message = $this->get_translation_message('E00022');
//            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Audience ID cannot be empty");
//		} 
        
        if ($valid_days == '') {
            //$error_message = $this->get_translation_message('E00022');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Valid days cannot be empty");
		} 
        
        $end_date = date("Y-m-d 23:59:59", strtotime("+$valid_days days", strtotime($scheduled_date)));
        
        $insertData = array("start_date" => $scheduled_date, 
                            "end_date" => $end_date,
                            "timezone" => $time_zone,
                            "image_url" => $s3_link,
                            "title" => $title,
                            "days_active" => $valid_days,
                            "description" => $description,
                            "button_type" => $button_type,
                            "button_name" => $button_name,
                            "button_link" => $button_link,
                            "created_at" => $date,
                            "updated_at" => $date);
    
        $announcement_id = $db->insert("xun_announcement", $insertData);
        
        if($audience_number){
            foreach($audience_number as $number){
                $db->where("username", $number);
                $userData = $db->getOne("xun_user");
                
                $insertData = array("announcement_id" => $announcement_id,
                                    "user_id" => $userData["id"],
                                    "audience_id" => "0");
                
                $db->insert("xun_announcement_recipient", $insertData);
            }
        }
        
        if($audience_id){
            foreach($audience_id as $a_id){
                $insertData = array("announcement_id" => $announcement_id,
                                    "user_id" => "0",
                                    "audience_id" => $a_id);
                
                $db->insert("xun_announcement_recipient", $insertData);
            }
        }
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Announcement saved.");
        
    }
    
    public function announcement_edit($params)
    {
        $db = $this->db;

        $announcement_id = $params["announcement_id"];
        $scheduled_date = date("Y-m-d H:i:s", $params["scheduled_date"]);
        $time_zone = $params["time_zone"];
        $s3_link = trim($params["s3_link"]);
        $title = trim($params["title"]);
        $description = trim($params["description"]);
        $audience_number = $params["audience_number"];
        $audience_id = $params["audience_id"];
        $valid_days = $params["valid_days"];
        $button_type = $params["button_type"];
        $button_name = $params["button_name"];
        $button_link = $params["button_link"];
        
        $date = date("Y-m-d H:i:s");
        
        if($announcement_id == ''){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Announcement ID cannot be empty");
        }
        
        if ($scheduled_date == '') {
            //$error_message = $this->get_translation_message('E00026');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Scheduled date cannot be empty");
		}
        
        if ($time_zone == '') {
            //$error_message = $this->get_translation_message('E00021');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "time zone cannot be empty");
		}
        
        if ($s3_link == '') {
            //$error_message = $this->get_translation_message('E00022');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "S3 Link cannot be empty");
		}
        
        if ($title == '') {
            //$error_message = $this->get_translation_message('E00022');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Title cannot be empty");
		} 
        
        if ($description == '') {
            //$error_message = $this->get_translation_message('E00022');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Description cannot be empty");
		} 
    
        if ($audience_number && !is_array($audience_number)) {
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Audience number must be in array format");
        }
        
        if ($audience_id && !is_array($audience_id)) {
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Audience ID must be in array format");
        }
        
        if ($valid_days == '') {
            //$error_message = $this->get_translation_message('E00022');
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Valid days cannot be empty");
		} 
        
        $db->where("id", $announcement_id);
        $announcement_data = $db->getOne("xun_announcement");
        
        if(!$announcement_data){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Invalid announcement ID");
        }
        
        $end_date = date("Y-m-d 23:59:59", strtotime("+$valid_days days", strtotime($scheduled_date)));
        
        $updateData = array("start_date" => $scheduled_date, 
                            "end_date" => $end_date,
                            "timezone" => $time_zone,
                            "image_url" => $s3_link,
                            "title" => $title,
                            "days_active" => $valid_days,
                            "description" => $description,
                            "button_type" => $button_type,
                            "button_name" => $button_name,
                            "button_link" => $button_link,
                            "updated_at" => $date);
    
        $db->where("id", $announcement_id);
        $db->update("xun_announcement", $updateData);
        
        //clear out recipients
        $db->where("announcement_id", $announcement_id);
        $db->delete("xun_announcement_recipient");
        
        if($audience_number){
            foreach($audience_number as $number){
                $db->where("username", $number);
                $userData = $db->getOne("xun_user");
                
                $insertData = array("announcement_id" => $announcement_id,
                                    "user_id" => $userData["id"],
                                    "audience_id" => "0");
                
                $db->insert("xun_announcement_recipient", $insertData);
            }
        }
        
        if($audience_id){
            foreach($audience_id as $a_id){
                $insertData = array("announcement_id" => $announcement_id,
                                    "user_id" => "0",
                                    "audience_id" => $a_id);
                
                $db->insert("xun_announcement_recipient", $insertData);
            }
        }
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Announcement saved.");
        
    }
    
    public function announcement_list($params){
        $db      = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $pageNumber = $params['page'] ? $params['page'] : 1;
        $limit = $general->getXunLimit($pageNumber);
        
        $type = $params["type"];

        foreach ($params as $key => $value) {
            switch ($key) {
                case 'date_from_created':
                    if ($value != '') {
                        $db->where("created_at", date('Y-m-d H:i:s', $value), '>=');
                    }
                    break;
                case 'date_to_created':
                    if ($value != '') {
                        $db->where("created_at", date('Y-m-d H:i:s', $value), '<=');
                    }
                    break;
                case 'date_from_scheduled':
                    if ($value != '') {
                        $db->where("start_date", date('Y-m-d H:i:s', $value), '>=');
                    }
                    break;
                case 'date_to_scheduled':
                    if ($value != '') {
                        $db->where("start_date", date('Y-m-d H:i:s', $value), '<=');
                    }
                    break;
                case 'title':
                    if ($value != '') {
                        $db->where('title', $value . "%", 'LIKE');
                    }
                    break;

                case 'button_type':
                    if ($value != '') {
                        $db->where('button_type', $value, '=');
                    }
                    break;
            }
        }

        $db->orderBy("created_at", "DESC");
        
        //get only active announcements
        if(strtolower($type) == "scheduled"){
            $db->where("start_date", date('Y-m-d H:i:s'), ">");
        }else if(strtolower($type) == "sent"){
            $db->where("start_date", date("Y-m-d H:i:s"), "<=");
        }
        
        $copyDb = $db->copy();

        if (strtolower($params['pagination']) == "no") {
            $result = $db->get("xun_announcement");
        } else {
            $result = $db->get("xun_announcement", $limit);
        }
        
        if (!empty($result)) { 
            $announcement_audience = $db->get("xun_announcement_audience");
            foreach($announcement_audience as $audience){
                $audience_filter[$audience["id"]] = $audience;
            }
            
            foreach($result as $announcement_data){
                $announcement_id = $announcement_data["id"];
                
                $grouping_string = "";
                
                $db->where("announcement_id", $announcement_id);
                $db->where("audience_id", "0", ">");
                $db->orderBy("audience_id", "ASC");
                
                $audience_recipient = $db->get("xun_announcement_recipient");
                
                foreach($audience_recipient as $audience_data){
                    $audience = $audience_filter[$audience_data["audience_id"]];
                    
                    if($grouping_string){
                        $grouping_string .= ", ".$audience["name"];
                    }else{
                        $grouping_string .= $audience["name"];
                    }
                }
                
                foreach($announcement_data as $key => $value){
                    $new_announcement[$key] = $value;
                }
                
                $new_announcement["audience"] = $grouping_string;
                
                $return_data[] = $new_announcement;
                
            }
            
            $totalRecords = $copyDb->getValue("xun_announcement", "count(id)");
            
            $data['announcements'] = $return_data;
            $data['totalPage']     = ceil($totalRecords / $limit[1]);
            $data['pageNumber']    = $pageNumber;
            $data['totalRecord']   = $totalRecords;
            $data['numRecord']     = $limit[1];

            return array('status' => "ok", 'code' => 1, 'statusMsg' => '', 'data' => $data);
        } else {
            return array('status' => "ok", 'code' => 1, 'statusMsg' => "No Results Found", 'data' => "");
        }
    }
    
    public function announcement_recipient_list($params){
        $db      = $this->db;
        $setting = $this->setting;
        $general = $this->general;

        $announcement_id = $params["announcement_id"];
        $pageNumber = $params['page'] ? $params['page'] : 1;
        $limit = $general->getXunLimit($pageNumber);
        
        if($announcement_id == ''){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Announcement ID cannot be empty");
        }

        foreach ($params as $key => $value) {
            switch ($key) {
                case 'date_from':
                    if ($value != '') {
                        $db->where("created_at", date('Y-m-d H:i:s', $value), '>=');
                    }
                    break;
                case 'date_to':
                    if ($value != '') {
                        $db->where("created_at", date('Y-m-d H:i:s', $value), '<=');
                    }
                    break;
            }
        }

        $db->where("announcement_id", $announcement_id);
        $db->orderBy("created_at", "DESC");
        
        $copyDb = $db->copy();

        if (strtolower($params['pagination']) == "no") {
            $result = $db->get("xun_announcement_viewing_history");
        } else {
            $result = $db->get("xun_announcement_viewing_history", $limit);
        }
        
        if (!empty($result)) {
            $announcement_audience = $db->get("xun_announcement_audience");
            foreach($announcement_audience as $audience){
                $audience_filter[$audience["id"]] = $audience;
            }
            
            foreach($result as $viewing_history){
                foreach($viewing_history as $key => $value){
                    if($key == "audience_id"){
                        $key = "audience";
                        $value = $audience_filter[$value]["name"];
                    } 
                    
                    $new_viewing_history[$key] = $value;
                }
                
                $return_data[] = $new_viewing_history;
            }
            
            $totalRecords = $copyDb->getValue("xun_announcement_viewing_history", "count(id)");
            
            $data['recipients']     = $return_data;
            $data['totalPage']      = ceil($totalRecords / $limit[1]);
            $data['pageNumber']     = $pageNumber;
            $data['totalRecord']    = $totalRecords;
            $data['numRecord']      = $limit[1];

            return array('status' => "ok", 'code' => 1, 'statusMsg' => '', 'data' => $data);
        } else {
            return array('status' => "ok", 'code' => 1, 'statusMsg' => "No Results Found", 'data' => "");
        }
    }
    
    public function audience_get($params)
    {
        $db = $this->db;
        
        $audience = $db->get("xun_announcement_audience");
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Audience List.", "data" => $audience);
        
    }
    
    public function get_announcement_for_user($params){
        
        $db = $this->db;
        
        $date = date("Y-m-d H:i:s");
        
        $username = trim($params["username"]);
        
        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username cannot be empty", 'developer_msg' => "username cannot be empty");
        }
        
        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user");

        if (count($xun_user) == 0) {
            //$error_message = $this->get_translation_message('E00457');  //User doesn't exist
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User doesn't exist");
        }
        
        $xun_user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];
        $date = date("Y-m-d H:i:s");
        
        $db->where("user_id", $xun_user_id);
        $db->where("name", "lastAnnouncementID");
        $user_last_announcement_id = $db->getOne("xun_user_setting");
        $last_announcement_id = $user_last_announcement_id["value"];
        
        if(!$last_announcement_id) $last_announcement_id = 0;
        
        //get all active announcements
        $db->where("end_date", $date, ">=");
        $db->where("id", $last_announcement_id, ">");
        $db->orderBy("created_at", "ASC");
        $active_announcements = $db->get("xun_announcement");
        if(count($active_announcements) == 0){
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "no active announcements");
        }

        $announcement_audience = $db->get("xun_announcement_audience");
        foreach($announcement_audience as $audience){
            $audience_filter[$audience["id"]] = $audience;
        }
        
        foreach($active_announcements as $announcement){
            
            $db->where("announcement_id", $announcement["id"]);
            $recipients = $db->get("xun_announcement_recipient");
            
            if(count($recipients) > 0){
                
                foreach($recipients as $recipient){
                    
                    $user_id = $recipient["user_id"];
                    $audience_id = $recipient["audience_id"];
	
                    $announcement["audience_id"] = $audience_id;
		
                    if($user_id > 0){
                        if($user_id == $xun_user_id){
                            $eligible_user_announcements[$announcement["id"]] = $announcement;
                        }
                    }

                    if($audience_id > 0){
                        $filter = $audience_filter[$audience_id];

                        $all = $filter["all"];
                        $wallet = $filter["wallet"];
                        $freecoin = $filter["free_coin"];
                        $xchange = $filter["xchange"];
                        $upline = $filter["upline"];

                        //group A - all
                        if($all){
                            $eligible_user_announcements[$announcement["id"]] = $announcement;
                        }else{
                            //get all criterias
                            //check if user have an active wallet
                            $db->where("user_id", $xun_user_id);
                            $db->where("active", "1");
                            $user_wallet = $db->get("xun_crypto_user_address");

                            //check if user has payout free coin
                            $db->where("user_id", $xun_user_id);
                            $user_freecoin = $db->get("xun_freecoin_payout_transaction");

                            //check if user has used xchange
                            $db->where("user_id", $xun_user_id);
                            $xchange_order = $db->get("xun_marketplace_advertisement_order_cache");

                            $db->where("user_id", $xun_user_id);
                            $xchange_advertisement = $db->get("xun_marketplace_advertisement");

                            //check if user has upline
                            $db->where("user_id", $xun_user_id);
                            $user_upline = $db->get("xun_tree_referral");

                            //group B - no wallet
                            if(!$wallet){
                                if(count($user_wallet) == 0){
                                    $eligible_user_announcements[$announcement["id"]] = $announcement;
                                }
                            }

                            //group C - have wallet, have upline, no free coin
                            else if($wallet && $upline && !$freecoin){
                                if(count($user_wallet) > 0 && count($user_upline) > 0 && count($user_freecoin) == 0){
                                    $eligible_user_announcements[$announcement["id"]] = $announcement;
                                }
                            }

                            //group D - have wallet/upline/freecoin, no xchange
                            else if($wallet && $upline && $freecoin && !$xchange){
                                if(count($user_wallet) > 0 && count($user_upline) > 0 && count($user_freecoin) > 0 && (count($xchange_order) == 0 && count($xchange_advertisement) == 0)){
                                    $eligible_user_announcements[$announcement["id"]] = $announcement;
                                }
                            }

                            //group E - have wallet no upline
                            else if($wallet && !$upline){
                                if(count($user_wallet) > 0 && count($user_upline) == 0){
                                    $eligible_user_announcements[$announcement["id"]] = $announcement;
                                }
                            }

                        }

                    }
                    
                }
                
            }
            
        }
        
        if(!$eligible_user_announcements){
            return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "No Eligible Announcements.", "data" => "");
        }
        
        $return["announcements"] = $eligible_user_announcements;
        
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "User Eligible Announcements.", "data" => $return);
        
    }
    
    public function announcement_view($params){
        $db = $this->db;
        
        $announcement_id = $params["announcement_id"];
        $audience_id = $params["audience_id"];
        $action = $params["action"];
        $username = $params["username"];
        $device = $params["device"];
        $app_version = $params["app_version"];
        
        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username cannot be empty", 'developer_msg' => "username cannot be empty");
        }
        
        if($announcement_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Announcement ID cannot be empty", 'developer_msg' => "Announcement ID cannot be empty");
        }
        
        $db->where("username", $username);
        $db->where("disabled", 0);
        $xun_user = $db->getOne("xun_user");

        if (count($xun_user) == 0) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User doesn't exist");
        }
        
        $db->where("id", $announcement_id);
        $announcement = $db->getOne("xun_announcement");
        
        if(count($announcement) == 0){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Announcement doesn't exist");
        }
        
        $xun_user_id = $xun_user["id"];
        $nickname = $xun_user["nickname"];
        
        $db->where("user_id", $xun_user_id);
        $db->where("name", "ipCountry");
        $user_country = $db->getOne("xun_user_setting");
        
        $db->where("user_id", $xun_user_id);
        $db->where("active", "1");
        $user_wallet = $db->getOne("xun_crypto_user_address");
        
        $db->where("user_id", $xun_user_id);
        $user_tree = $db->getOne("xun_tree_referral");
        
        $db->where("id", $user_tree["upline_id"]);
        $upline = $db->getOne("xun_user");
        
        $insertData["user_id"] = $xun_user_id;
        $insertData["announcement_id"] = $announcement_id;
        $insertData["audience_id"] = $audience_id ? $audience_id : "0";
        $insertData["action"] = $action ? $action : "";
        $insertData["account_date"] = $xun_user["created_at"];
        $insertData["wallet_date"] = $user_wallet["created_at"] ? $user_wallet["created_at"]:"00-00-00";
        $insertData["device"] = $device ? $device : "";
        $insertData["app_version"] = $app_version ? $app_version : "";
        $insertData["nickname"] = $xun_user["nickname"] ? $xun_user["nickname"] : "";
        $insertData["username"] = $username ? $username : "";
        $insertData["upline"] = $upline["nickname"] ? $upline["nickname"] : "";
        $insertData["country"] = $user_country["value"] ? $user_country["value"] : "";
	    $insertData["created_at"] = date("Y-m-d H:i:s");

        $db->insert("xun_announcement_viewing_history", $insertData);

        $db->where("user_id", $xun_user_id);
        $db->where("name", "lastAnnouncementID");
        $user_setting = $db->getOne("xun_user_setting");
        
        if($user_setting){
            
            //only updates when viewed announcement id is bigger than current saved id
            if($user_setting["value"] < $announcement_id){
                $updateData["value"] = $announcement_id;
                $updateData["updated_at"] = date("Y-m-d H:i:s");

                $db->where("user_id", $xun_user_id);
                $db->where("name", "lastAnnouncementID");
                $db->update("xun_user_setting", $updateData);
            }
            
        }else{
            $userSettingInsertData["user_id"] = $xun_user_id;
            $userSettingInsertData["name"] = "lastAnnouncementID";
            $userSettingInsertData["value"] = $announcement_id;
            $userSettingInsertData["created_at"] = date("Y-m-d H:i:s");
            $userSettingInsertData["updated_at"] = date("Y-m-d H:i:s");
            
            $db->insert("xun_user_setting", $userSettingInsertData);
        }
        
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => "Announcement Viewed.", "data" => $return);
    
    }
    
    public function get_announcement_image_presign_url($params){
        global $xunAws;

        $setting = $this->setting;

        $file_name = trim($params["file_name"]);
        $content_type = trim($params["content_type"]);
        $content_size = trim($params["content_size"]);

        $bucket = $setting->systemSetting["awsS3AnnouncementBucket"];
        $s3_folder = 'announcement';
        $time = time();
        
        $presigned_url_key = $s3_folder . '/' . $time . '/' . $file_name;
        $expiration = '+20 minutes';
        
        $newParams = array(
            "s3_bucket" => $bucket,
            "s3_file_key" => $presigned_url_key,
            "content_type" => $content_type,
            "content_size" => $content_size,
            "expiration" => $expiration
        );

        $result = $xunAws->generate_put_presign_url($newParams);
        
        if(isset($result["error"])){
            return array("code" => 1, "status" => "error", "statusMsg" => "Error generating AWS S3 presigned URL.", "errorMsg" => $result["error"]);
        }
        
        $return_message = "AWS presigned url.";
        return array("code" => 0, "status" => "ok", "statusMsg" => $return_message, "data" => $result);

    }
    
    public function announcement_details($params)
    {
        $db = $this->db;

        $announcement_id = $params["announcement_id"];
        
        if($announcement_id == ''){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Announcement ID cannot be empty");
        }
        
        $db->where("id", $announcement_id);
        $announcement_data = $db->getOne("xun_announcement");
        
        if(!$announcement_data){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Invalid announcement ID");
        }
        
        $announcement_audience = $db->get("xun_announcement_audience");
        foreach($announcement_audience as $audience){
            $audience_filter[$audience["id"]] = $audience;
        }
        
        $db->where("announcement_id", $announcement_id);
        $recipient_list = $db->get("xun_announcement_recipient");
        
        if(count($recipient_list) > 0){
            foreach($recipient_list as $recipient){
                $user_id = $recipient["user_id"];
                $audience_id = $recipient["audience_id"];
                
                if($user_id > 0){
                    $db->where("id", $user_id);
                    $user_data = $db->getOne("xun_user");
                    
                    $announcement_data["phone_number"][] = $user_data["username"];
                }
                
                if($audience_id){
                    $announcement_data["audience_group"][] = $audience_filter[$audience_id];
                }
            }
        }
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Announcement Details.", "data" => $announcement_data);
        
    }
    
    public function announcement_delete($params)
    {
        $db = $this->db;

        $announcement_id = $params["announcement_id"];
        
        if($announcement_id == ''){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Announcement ID cannot be empty");
        }
        
        $db->where("id", $announcement_id);
        $announcement_data = $db->getOne("xun_announcement");
        
        if(!$announcement_data){
            return array('code' => 1, 'message' => "FAILED", 'message_d' => "Invalid announcement ID");
        }
        
        //delete recipients
        $db->where("announcement_id", $announcement_id);
        $db->delete("xun_announcement_recipient");
        
        //delete annnouncement
        $db->where("id", $announcement_id);
        $db->delete("xun_announcement");
        
        return array('status' => 'ok', 'code' => 0, 'statusMsg' => "Announcement Deleted.", "data" => "");
        
    }

}
