<?php

class XunUserModel
{
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getUserByID($userID, $columns = null)
    {
        $db = $this->db;

        $db->where("id", $userID);
        $userData = $db->getOne("xun_user", $columns);

        return $userData;
    }

    public function getUserByUsername($username, $columns = null, $type = "user", $mapColumn = null)
    {
        $db = $this->db;

        if(is_array($username)){
            $db->where("username", $username, "in");
            $db->where("type", $type);
            if($mapColumn){
                $userData = $db->map($mapColumn)->ArrayBuilder();
            }
            $userData = $db->get("xun_user", null, $columns);
        }else{
            $db->where("username", $username);
            $db->where("type", $type);
            $userData = $db->getOne("xun_user", $columns);
        }

        return $userData;
    }

    public function getUserByEmail($email, $columns = null){
        $db = $this->db;

        $db->where("email", $email);
        $db->where("type", "user");
        $data = $db->getOne("xun_user", $columns);

        return $data;
    }

    public function getUserDetailsByID($userID, $columns = null){
        $db = $this->db;

        if(is_array($userID)){
            $db->where("a.id", $userID, "IN");
            $db->where("(a.type = ? or a.type = ?)", ["business", "user"]);
            $db->join("xun_user_details b", "a.id=b.user_id", "LEFT");
            $data = $db->map("id")->ArrayBuilder()->get("xun_user a", null, $columns);
        }else{
            $db->where("id", $userID);
            $db->where("(type = ? or type = ?)", ["business", "user"]);
            $data = $db->getOne("xun_user");
            
            if($data && $data["type"] == "business"){
                $data["username"] = $data["id"];
            }
        }


        return $data;
    }

    public function getDeviceInfo($obj, $columns = null)
    {
        $db = $this->db;
        
        $username = $obj->username;
        if($username){
            $db->where("mobile_number", $username);
        }
        $data = $db->getOne("xun_user_device", $columns);

        return $data;
    }

    public function createUser($serverHost, $username)
    {

    }

    public function createBusinessUser($serverHost, $businessName)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $insertData = array(
            "server_host" => $serverHost,
            "type" => "business",
            "nickname" => $businessName,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $userID = $db->insert("xun_user", $insertData);

        // if (!$userID) {
        //     // print_r($db);
        //     throw new Exception("Error creating busines user");
        // }

        return $userID;
    }

    public function getActiveInternalAddressByUserID($userID, $columns = null)
    {
        $db = $this->$db;

        $addressType = "personal";
        return $this->getActiveAddressByUserIDandType($userID, $addressType, $columns);
    }

    public function getActiveAddressByUserIDandType($userID, $addressType, $columns = null)
    {
        $db = $this->db;
        $db->where("user_id", $userID);
        $db->where("active", 1);
        $db->where("address_type", $addressType);

        $userAddressData = $db->getOne("xun_crypto_user_address", $columns);

        return $userAddressData;
    }

    public function getAddressByExternalAddressAndAddressType($addressObj, $columns = null)
    {
        $db = $this->db;
        $externalAddress = $addressObj->externalAddress;
        $addressType = $addressObj->addressType;
        $db->where("external_address", $externalAddress);
        $db->where("address_type", $addressType);

        $userAddressData = $db->getOne("xun_crypto_user_address", $columns);
        return $userAddressData;
    }

    public function getAddressDetailsByAddress($address, $columns = null)
    {
        $db = $this->db;
        $db->where("address", $address);
        $userAddressData = $db->getOne("xun_crypto_user_address", $columns);
        return $userAddressData;
    }

    public function getAddressDetailsByAddressList($address_arr, $columns = null)
    {
        $db = $this->db;
        $db->where("address", $address_arr, "in");
        $data = $db->map("address")->ArrayBuilder()->get("xun_crypto_user_address", null, $columns);
        return $data;
    }

    public function getActiveAddressDetailsByUserID($userId, $columns = null)
    {
        $db = $this->db;
        if(is_array($userId) && !empty($userId)){
            $db->where("user_id", $userId, "IN");
            $db->where("address_type", "personal");
            $db->where("active", 1);
            $db->where("deleted", 0);
            $data = $db->map("user_id")->ArrayBuilder()->get("xun_crypto_user_address", null, $columns);
        }else{
            $db->where("user_id", $userId);
            $db->where("address_type", "personal");
            $db->where("active", 1);
            $db->where("deleted", 0);
            $data = $db->getOne("xun_crypto_user_address", $columns);
        }
        return $data;
    }

    public function getAddressAndUserDetailsByAddressList($address_arr, $columns = null)
    {
        $db = $this->db;

        $columns = $columns ? $columns : "a.*, b.username, b.server_host, b.type, b.nickname, b.disabled, b.service_charge_rate, b.wallet_callback_url";

        $db->where("a.address", $address_arr, "in");
        $db->join("xun_user b", "a.user_id=b.id", "LEFT");
        $result = $db->map("address")->ArrayBuilder()->get("xun_crypto_user_address a", null, $columns);

        return $result;
    }

    public function setActiveWalletAddress($userObj)
    {
        global $general, $xunReward;
        $db = $this->db;
        
        $language = $general->getCurrentLanguage();
        $translations = $general->getTranslations();

        $date = date("Y-m-d H:i:s");

        $userID = $userObj->userID;
        $addressType = $userObj->addressType;
        $internalAddress = $userObj->internalAddress;
        $externalAddress = $userObj->externalAddress;
        $walletType = $userObj->walletType;

        $db->where("address", $internalAddress);
        $db->where("address_type", $addressType);
        $db->where("deleted", 0);

        $xun_crypto_user_address = $db->getOne("xun_crypto_user_address");

        // if key exists and user_id !== user_id -> reject update
        if ($xun_crypto_user_address) {
            if ($xun_crypto_user_address["user_id"] != $userID) {
                $message = $translations['E00262'][$language];
                return array('code' => 0, 'errorCode' => -100, 'message_d' => $message);
            }

            // activate key if it's inactive
            if ($xun_crypto_user_address["active"] === 0) {
                // inactivate the current active key
                $updateData;
                $updateData["active"] = 0;
                $updateData["updated_at"] = $date;

                // if($externalAddress){
                //     $updateData["external_address"] = $externalAddress;
                // }
                $db->where("user_id", $userID);
                $db->where("active", 1);
                $db->where("address_type", $addressType);
                $db->update("xun_crypto_user_address", $updateData);

                $updateData = [];
                $updateData["active"] = 1;
                $updateData["updated_at"] = $date;

                $db->where("id", $xun_crypto_user_address["id"]);
                $row_id = $db->update("xun_crypto_user_address", $updateData);
            } else {
                $row_id = $xun_crypto_user_address["id"];
            }
        } else {
            // inactivate the current active key
            $updateData;
            $updateData["active"] = 0;
            $updateData["updated_at"] = $date;
            $db->where("user_id", $userID);
            $db->where("active", 1);
            $db->where("address_type", $addressType);
            $db->update("xun_crypto_user_address", $updateData);

            $insertData = array(
                "user_id" => $userID,
                "address" => $internalAddress,
                "address_type" => $addressType,
                "active" => '1',
                "deleted" => 0,
                "created_at" => $date,
                "updated_at" => $date,
            );
            if($externalAddress){
                $insertData["external_address"] = $externalAddress;
            }
            $row_id = $db->insert("xun_crypto_user_address", $insertData);
        }

        // if (!$row_id) {
        //     print_r($db);
        //     print_r($userObj);
        // }
        

        return array("code" => 1, "id" => $row_id);
    }

    public function insertCryptoExternalAddress($obj)
    {
        $db = $this->db;

        $date = date("Y-m-d H:i:s");
        $internalAddress = $obj->internalAddress;
        $externalAddress = $obj->externalAddress;
        $walletType = $obj->walletType;

        $insertData = array(
            "internal_address" => $internalAddress,
            "external_address" => $externalAddress,
            "wallet_type" => $walletType,
            "created_at" => $date,
            "updated_at" => $date,
        );

        $rowID = $db->insert("xun_crypto_external_address", $insertData);

        // if (!$rowID) {
        //     print_r($db);
        //     print_r($obj);
        // }
        return $rowID;
    }

    public function getCryptoExternalAddressByInternalAddressAndWalletType($obj, $columns = null)
    {
        $db = $this->db;

        $internalAddress = $obj->internalAddress;
        $walletType = $obj->walletType;

        $db->where("internal_address", $internalAddress);
        $db->where("wallet_type", $walletType);

        $addressData = $db->getOne("xun_crypto_external_address", $columns);

        return $addressData;
    }

    public function getExternalAddressByUserIDandWalletType($obj, $columns = null){
        $db = $this->db;

        $userID = $obj->userID;
        $walletType = $obj->walletType;

        $db->where("a.user_id", $userID);
        $db->where("a.address_type", "personal");
        $db->where("b.wallet_type", $walletType);
        $db->join("xun_crypto_user_address a", "a.address=b.internal_address", "LEFT");
        $data = $db->getOne("xun_crypto_external_address", $columns);

        return $data;
    }

    public function insertCryptoTransactionToken($txObj)
    {
        global $general;
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $expiresAt = date("Y-m-d H:i:s", strtotime('+1 year', strtotime($date)));

        $userID = $txObj->userID;
        $address = $txObj->address;
        $referenceID = $txObj->referenceID;

        $tokenValid = false;

        do {
            $transactionToken = $general->generateApiKey($userID);

            $db->where("transaction_token", $transactionToken);
            $txTokenRecord = $db->getOne("xun_crypto_user_transaction_verification", "id");

            if (!$txTokenRecord) {
                $tokenValid = true;
            }
        } while (!$tokenValid);

        $insertData = array(
            "user_id" => $userID,
            "address" => $address,
            "transaction_token" => $transactionToken,
            "reference_id" => $referenceID ? $referenceID : '',
            "expires_at" => $expiresAt,
            "verified" => '0',
            "created_at" => $date,
            "updated_at" => $date,
        );

        $row_id = $db->insert("xun_crypto_user_transaction_verification", $insertData);
        // if (!$row_id) {
            // print_r($insertData);
            // print_r($db);
        // }

        return $transactionToken;
    }

    public function getUserAccecptedCurrencyListAndPrimaryCurrency($userID, $columns = null)
    {
        $db = $this->db;

        $db->where("user_id", $userID);
        $db->where("name", ["acceptedCurrency", "primaryCurrency"], "in");

        $record = $db->map("name")->ObjectBuilder()->get("xun_user_setting", null, $columns);
        return $record;
    }

    public function getUserSettingByUserID($userID, $nameArr, $columns = null)
    {
        $db = $this->db;

        $db->where("user_id", $userID);
        $db->where("name", $nameArr, "in");

        $record = $db->map("name")->ArrayBuilder()->get("xun_user_setting", null, $columns);
        return $record;
    }

    public function getUserSettingByName($userID, $name, $columns = null, $mapColumn = null){
        $db = $this->db;

        if(is_array($userID)){
            $db->where("user_id", $userID, "IN");
        }else{
            $db->where("user_id", $userID);
        }

        $db->where("name", $name);

        if($mapColumn){
            $db->map($mapColumn)->ArrayBuilder();
        }

        $data = $db->get("xun_user_setting", null, $columns);

        return $data;
    }

    public function updateUserSetting($userID, $name, $value)
    {
        $db = $this->db;

        $db->where("user_id", $userID);
        $db->where("name", $name);

        $record = $db->getOne("xun_user_setting");
        if ($record) {
            $updateData["value"] = $value;
            $db->where("id", $record["id"]);
            $db->update("xun_user_setting", $updateData);
            $rowID = $record["id"];
        } else {
            $insertData = array(
                "user_id" => $userID,
                "name" => $name,
                "value" => $value,
            );

            $rowID = $db->insert("xun_user_setting", $insertData);
            // if(!$rowID){
            //     print_r($insertData);
            //     print_r($db);
            // }
        }

        return $rowID;
    }

    public function insertCustomCoinTransactionToken($txObj)
    {
        global $general;
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $expiresAt = date("Y-m-d H:i:s", strtotime('+1 year', strtotime($date)));

        $userID = $txObj->userID;
        $walletType = $txObj->walletType;
        $amount = $txObj->amount;

        $tokenValid = false;

        do {
            $transactionToken = $general->generateApiKey($userID);

            $db->where("transaction_token", $transactionToken);
            $txTokenRecord = $db->getOne("xun_custom_coin_supply_transaction", "id");

            if (!$txTokenRecord) {
                $tokenValid = true;
            }
        } while (!$tokenValid);

        $insertData = array(
            "business_id" => $userID,
            "amount" => $amount,
            "wallet_type" => $walletType,
            "transaction_token" => $transactionToken,
            "is_verified" => '0',
            "created_at" => $date,
            "updated_at" => $date,
        );

        $row_id = $db->insert("xun_custom_coin_supply_transaction", $insertData);
        // if (!$row_id) {
            // print_r($insertData);
            // print_r($db);
        // }

        return $transactionToken;
    }

    public function insertMultiCryptoTransactionToken($txObj)
    {
        global $general;
        $db = $this->db;

        $date = date("Y-m-d H:i:s");

        $expiresAt = date("Y-m-d H:i:s", strtotime('+1 year', strtotime($date)));

        $userID = $txObj->userID;
        $address = $txObj->address;
        $referenceID = $txObj->referenceID;
        $totalToken = $txObj->totalToken;

        for($i = 0; $i < $totalToken; $i++){
            $tokenValid = false;

            // do {
            //     $transactionToken = $general->generateTransactionToken("et");
    
            //     $db->where("transaction_token", $transactionToken);
            //     $txTokenRecord = $db->getOne("xun_crypto_user_transaction_verification", "id");
    
            //     if (!$txTokenRecord) {
            //         $tokenValid = true;
            //     }
            // } while (!$tokenValid);

            $transactionToken = $general->generateTransactionToken("et");
    
            $insertData = array(
                "user_id" => $userID,
                "address" => $address,
                "transaction_token" => $transactionToken,
                "reference_id" => $referenceID ? $referenceID : '',
                "expires_at" => $expiresAt,
                "verified" => '0',
                "created_at" => $date,
                "updated_at" => $date,
            );
            
            $insertMultiData[] = $insertData;
           
            $transactionTokenArr[] = $transactionToken;
        }

        $row_id = $db->insertMulti("xun_crypto_user_transaction_verification", $insertMultiData);
        // if (!$row_id) {
            // print_r($insertData);
            // print_r($db);
        // }

       

        return $transactionTokenArr;
    }
}
