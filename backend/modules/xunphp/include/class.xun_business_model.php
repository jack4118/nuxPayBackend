<?php

class XunBusinessModel
{
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getBusinessAccountByEmail($email)
    {
        $db = $this->db;

        $db->where("email", $email);
        $businessAccount = $db->getOne("xun_business_account");

        return $businessAccount;
    }

    public function createBusinessAccount($user_id, $email, $password, $referral_code, $main_mobile)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $main_mobile_verified = $main_mobile ? 1 : 0;

        $insertData = array(
            "user_id" => $user_id,
            "email" => $email,
            "password" => $password,
            "referral_code" => $referral_code,
            "main_mobile" => $main_mobile,
            "main_mobile_verified" => $main_mobile_verified,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $rowID = $db->insert("xun_business_account", $insertData);
        return $rowID;
    }

    public function createBusinessDetails($userID, $email, $name)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "user_id" => $userID,
            "email" => $email,
            "name" => $name,
            // "profile_picture" => $profile_picture,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $rowID = $db->insert("xun_business", $insertData);

        if (!$rowID) {
            throw new Exception("Error creating business details");
        }

        return $rowID;
    }

    public function updateBusinessDetails($businessObj)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");
        /**
         * data in:
         * website
         * phone number
         * company size
         * business email
         * contact us url
         * address
         * city
         * state
         * postal_code
         * country
         * business_info
         *
         */

        $updateData = [];
        $updateData["website"] = $businessObj->website;
        $updateData["phone_number"] = $businessObj->phone_number;
        $updateData["address1"] = $businessObj->address1;
        $updateData["address2"] = $businessObj->address2;
        $updateData["city"] = $businessObj->city;
        $updateData["state"] = $businessObj->state;
        $updateData["postal"] = $businessObj->postal;
        $updateData["country"] = $businessObj->country;
        $updateData["info"] = $businessObj->info;
        $updateData["company_size"] = $businessObj->company_size;
        $updateData["display_email"] = $businessObj->display_email;
        // $updateData["contact_us_url"] = $businessObj->contact_us_url;
        $updateData["updated_at"] = $date;

        $userID = $businessObj->userID;
        $db->where("user_id", $userID);
        $updateRes = $db->update("xun_business", $updateData);
        return $updateRes;
    }

    public function updateBusinessImage($businessUserID, $imageBase64, $imageURL)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $updateData = [];
        $updateData["profile_picture"] = $imageBase64 ? $imageBase64 : "";
        $updateData["profile_picture_url"] = $imageURL ? $imageURL : "";
        $updateData["updated_at"] = $date;

        $db->where("user_id", $businessUserID);
        $result = $db->update("xun_business", $updateData);

        return $result;
    }

    public function getBusinessDetailsByBusinessID($userID)
    {
        $db = $this->db;

        $db->where("a.user_id", $userID);
        $db->join("xun_business_account b", "a.user_id=b.user_id", "LEFT");
        $business = $db->getOne("xun_business a", "a.id, a.user_id, a.name, a.email, b.main_mobile");
        return $business;
    }

    public function getBusinessByBusinessID($userID, $columns = null)
    {
        $db = $this->db;

        $db->where("user_id", $userID);
        $business = $db->getOne("xun_business", $columns);
        return $business;
    }

    public function getBusinessByBusinessName($name, $columns = null)
    {
        $db = $this->db;

        $db->where("name", $name);
        $business = $db->getOne("xun_business", $columns);
        return $business;
    }

    public function createBusinessEmployee($params)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "business_id" => $params["business_id"],
            "name" => $params["name"],
            "mobile" => $params["mobile"],
            "employment_status" => $params["employment_status"],
            "role" => $params["role"],
            "old_id" => $params["old_id"],
            "created_at" => $date,
            "updated_at" => $date,
        );

        $employeeID = $db->insert("xun_employee", $insertData);

        if (!$employeeID) {
            // print_r($db);
        }

        return $employeeID;
    }

    public function createBusinessTag($params)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "business_id" => $params["business_id"],
            "tag" => $params["tag"],
            "status" => 1,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $tagID = $db->insert("xun_business_tag", $insertData);

        // if(!$tagID) print_r($tagID);

        return $tagID;
    }

    public function createBusinessTagEmployee($params)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "employee_id" => $params["employee_id"],
            "username" => $params["username"],
            "business_id" => $params["business_id"],
            "tag" => $params["tag"],
            "status" => 1,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $employeeTagID = $db->insert("xun_business_tag_employee", $insertData);

        // if(!$employeeTagID) print_r($db);

        return $employeeTagID;
    }

    public function createBusinessFollow($params)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "business_id" => $params["business_id"],
            "username" => $params["username"],
            "server_host" => $params["server_host"],
            "created_at" => $date,
            "updated_at" => $date,
        );

        $followBusinessID = $db->insert("xun_business_follow", $insertData);

        // if(!$followBusinessID) print_r($db);

        return $followBusinessID;
    }

    public function createBusinessPasswd($params)
    {
        $db = $this->db;

        $insertData = array(
            "username" => $params["username"],
            "server_host" => $params["server_host"],
            "password" => $params["password"],
        );

        $rowID = $db->insert("xun_passwd", $insertData);

        // if(!$rowID) print_r($db);

        return $rowID;
    }

    public function createBusinessSubscription($params)
    {
        $db = $this->db;
        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "business_id" => $params["business_id"],
            "package_code" => $params["package_code"],
            "billing_id" => $params["billing_id"],
            "message_limit" => $params["message_limit"],
            "status" => $params["status"],
            "startdate" => $params["startdate"],
            "enddate" => $params["enddate"],
            "created_at" => $date,
            "updated_at" => $date,
        );

        $rowID = $db->insert("xun_business_package_subscription", $insertData);

        // if(!$rowID) print_r($db);

        return $rowID;
    }

    public function getBusinessPackageByCode($code, $columns = null)
    {
        $db = $this->db;

        $db->where("code", $code);
        $businessPackage = $db->getOne("xun_business_package");

        return $businessPackage;
    }

    //  EMPLOYEE
    public function getBusinessActiveEmployee($businessID, $columns = null)
    {
        $db = $this->db;

        $db->where("business_id", $businessID);
        $db->where("employment_status", "confirmed");
        $db->where("status", '1');

        $result = $db->get("xun_employee", null, $columns);
        return $result;
    }

    //  LIVECHAT SETTING

    public function getBusinessLivechatSetting($obj)
    {
        $db = $this->db;

        $businessID = $obj->business_id;
        $db->where("business_id", $businessID);
        $data = $db->getOne("xun_business_livechat_setting");
        return $data;
    }

    public function insertBusinessLivechatSetting($obj)
    {
        $db = $this->db;

        $business_id = $obj->business_id;
        $contact_us_url = $obj->contact_us_url;
        $website_url = $obj->website_url;
        $live_chat_no_agent_msg = $obj->live_chat_no_agent_msg;
        $live_chat_after_working_hrs_msg = $obj->live_chat_after_working_hrs_msg;
        $live_chat_first_msg = $obj->live_chat_first_msg;
        $live_chat_prompt = $obj->live_chat_prompt;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "business_id" => $business_id,
            "contact_us_url" => $contact_us_url,
            "website_url" => $website_url ? $website_url : '',
            "live_chat_no_agent_msg" => $live_chat_no_agent_msg ? $live_chat_no_agent_msg : '',
            "live_chat_after_working_hrs_msg" => $live_chat_after_working_hrs_msg ? $live_chat_after_working_hrs_msg : '',
            "live_chat_first_msg" => $live_chat_first_msg ? $live_chat_first_msg : '',
            "live_chat_prompt" => $live_chat_prompt ? $live_chat_prompt : '',
            "created_at" => $date,
            "updated_at" => $date,
        );

        $row_id = $db->insert("xun_business_livechat_setting", $insertData);

        // if(!$row_id){
        //     print_r($db);
        //     print_r($insertData);
        // }

        return $row_id;
    }

    public function updateBusinessContactUsURL($obj)
    {
        $db = $this->db;

        $recordID = $obj->record_id;
        $businessID = $obj->business_id;
        $contact_us_url = $obj->contact_us_url;
        $date = date("Y-m-d H:i:s");

        $updateData = [];
        $updateData["contact_us_url"] = $contact_us_url ? $contact_us_url : '';
        $updateData["updated_at"] = $date;

        $db->where("id", $recordID);
        $db->update("xun_business_livechat_setting", $updateData);
    }

    public function getBusinessOwner($business_id)
    {
        $db = $this->db;

        $db->where("business_id", $business_id);
        $db->where("role", "owner");
        $db->where("status", 1);

        $owner = $db->getOne("xun_employee");
        return $owner;
    }

    public function getBusinessAPIKey($businessID, $apiKey)
    {
        $db = $this->db;

        $db->where("business_id", $businessID);
        $db->where("apikey", $apiKey);
        $db->where("is_enabled", 1);
        $db->where("status", "active");
        $db->where("apikey_expire_datetime", date("Y-m-d H:i:s"), ">");

        $data = $db->getOne("xun_business_api_key");

        return $data;
    }

    public function getBusinessRequestMoneyByID($id)
    {
        $db = $this->db;

        $db->where("id", $id);

        $data = $db->getOne("xun_business_request_money");

        return $data;
    }

    public function insertBusinessRequestMoney($obj)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "business_id" => $obj->businessID,
            "username" => $obj->username,
            "amount" => $obj->amount,
            "wallet_type" => $obj->walletType,
            "destination_address" => $obj->destinationAddress,
            "reference_id" => $obj->referenceID,
            "transaction_hash" => '',
            "created_at" => $date,
            "updated_at" => $date,
        );

        $rowID = $db->insert("xun_business_request_money", $insertData);

        // if (!$rowID) {
        //     print_r($insertData);
        //     print_r($db);
        // }

        return $rowID;
    }

    public function updateBusinessRequestMoneyTxHashByID($obj)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");
        $id = $obj->id;

        if($id){
            $updateData = [];
            $updateData["transaction_hash"] = $obj->transactionHash;
            $updateData["updated_at"] = $date;

            $db->where("id", $id);
            $retVal = $db->update("xun_business_request_money", $updateData);
        }
        // else{
        //     print_r($obj);
        // }

        return $retVal;
    }

    public function createBusinessCoin($obj){
        $db = $this->db;

        if($obj instanceof XunBusinessCoinModel){
            $symbol = $obj->symbol;
            $imageUrl = $obj->cardImageUrl;
        }else{
            $symbol = $obj->rewardSymbol;
            $imageUrl  = $obj->cardBackgroundUrl;
        }
        $date = date("Y-m-d H:i:s");
        $insert_business_coin = array(
            "business_id" => $obj->businessID,
            "business_name" => $obj->businessName,
            "wallet_type" => '',
            "symbol" => $symbol,
            "fiat_currency_id" => $obj->fiatCurrencyID,
            "total_supply" => $obj->totalSupply,
            "reference_price" => $obj->referencePrice,
            "unit_conversion" => '',
            "card_image_url" => $imageUrl,
            "default_show" => 1,
            "status" => 'pending',
            "type" => $obj->type,
            "font_color" => $obj->fontColor,
            "created_at" => $date,
            "updated_at" => $date
            
        );

        $rowID = $db->insert('xun_business_coin', $insert_business_coin);

        // if(!$rowID) print_r($db->getLastError());
        return $rowID;
        
    }

    public function getBusinessCoin($dataArr, $columns = null){
        $db = $this->db;

        foreach($dataArr as $col => $value){
            if(is_null($value)) continue;
            switch($col){
                case "business_id":
                    $db->where($col, $value);
                    break;
                
                case "wallet_type":
                    $db->where($col, $value);
                    break;

                case "type":
                    $db->where($col, $value);
                    break;
            }
        }

        $data = $db->getOne("xun_business_coin", $columns);
        return $data;
    }

    public function getBusinessCoinArr($dataArr, $columns = null, $orderBy = null, $limit = null){
        $db = $this->db;

        foreach($dataArr as $col => $value){
            if(is_null($value)) continue;
            switch($col){
                case "business_id":
                    $db->where($col, $value);
                    break;
                
                case "wallet_type":
                    $db->where($col, $value);
                    break;

                case "type":
                    $db->where($col, $value);
                    break;
            }
        }

        if($orderBy){
            $db->orderBy($orderBy["field"], $orderBy["direction"]);
        }

        $data = $db->get("xun_business_coin", $limit, $columns);
        return $data;
    }

    public function validateBusinessName($businessName){
        $db = $this->db;

        $db->where('name', $businessName);
        $xun_business = $db->get('xun_business');

        return $xun_business;
    }
}
