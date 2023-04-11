<?php

abstract class AbstractXunUser
{
    public function __construct($db)
    {
        $this->db = $db;

        $this->userModel = new XunUserModel($db);
        $this->liveGroupChatModel = new XunLiveChatModel($db);
    }

    abstract protected function createUser($user);

    public function getUserByID($userID, $columns = null)
    {
        $userModel = $this->userModel;
        $userData = $userModel->getUserByID($userID, $columns);

        return $userData;
    }

    public function getUserDetailsByID($userID, $columns = null)
    {
        $userModel = $this->userModel;
        $userData = $userModel->getUserDetailsByID($userID, $columns);

        return $userData;
    }

    public function setActiveWalletAddress($userObj)
    {
        $userModel = $this->userModel;

        $data = $userModel->setActiveWalletAddress($userObj);

        return $data;
    }

    public function getActiveInternalAddressByUserID($userID, $columns = null)
    {
        $userModel = $this->userModel;

        $data = $userModel->getActiveInternalAddressByUserID($userID, $columns);

        return $data;
    }

    public function getActiveAddressByUserIDandType($userID, $addressType, $columns = null)
    {
        $userModel = $this->userModel;

        $data = $userModel->getActiveAddressByUserIDandType($userID, $addressType, $columns);

        return $data;
    }

    public function getAddressDetailsByAddress($address, $columns = null)
    {
        $userModel = $this->userModel;

        $data = $userModel->getAddressDetailsByAddress($address, $columns);

        return $data;
    }

    public function getActiveAddressDetailsByUserID($userId, $columns = null)
    {
        $userModel = $this->userModel;

        $data = $userModel->getActiveAddressDetailsByUserID($userId, $columns);

        return $data;
    }

    public function getAddressAndUserDetailsByAddressList($addressArr, $columns = null)
    {
        $userModel = $this->userModel;

        $data = $userModel->getAddressAndUserDetailsByAddressList($addressArr, $columns);

        return $data;
    }

    public function getAddressDetailsByAddressList($addressArr, $columns = null)
    {
        $userModel = $this->userModel;

        $data = $userModel->getAddressDetailsByAddressList($addressArr, $columns);
        return $data;
    }

    public function insertCryptoTransactionToken($txObj)
    {
        $userModel = $this->userModel;

        $data = $userModel->insertCryptoTransactionToken($txObj);

        return $data;
    }

    public function insertMultiCryptoTransactionToken($txObj){
        $userModel = $this->userModel;

        $data = $userModel->insertMultiCryptoTransactionToken($txObj);

        return $data;
    }

    public function insertCryptoExternalAddress($obj)
    {
        $userModel = $this->userModel;

        $data = $userModel->insertCryptoExternalAddress($obj);

        return $data;
    }

    public function getCryptoExternalAddressByInternalAddressAndWalletType($obj, $columns = null)
    {
        $userModel = $this->userModel;

        $data = $userModel->getCryptoExternalAddressByInternalAddressAndWalletType($obj, $columns);

        return $data;
    }

    public function getExternalAddressByUserIDandWalletType($obj, $columns = null)
    {
        $userModel = $this->userModel;

        $data = $userModel->getExternalAddressByUserIDandWalletType($obj, $columns);

        return $data;
    }

    public function insertUserAddressAndExternalAddress($obj)
    {
        $userModel = $this->userModel;

        $res = $this->setActiveWalletAddress($obj);
        if ($res) {
            $res2 = $this->insertCryptoExternalAddress($obj);
        }

        return $res2;
    }

    public function getUserAccecptedCurrencyListAndPrimaryCurrency($userID, $columns = null)
    {
        $userModel = $this->userModel;

        $nameArr = ["acceptedCurrency", "primaryCurrency", "acceptedCurrencyFloatingRatio"];
        $data = $userModel->getUserSettingByUserID($userID, $nameArr, $columns);

        if (!empty($data["acceptedCurrency"])) {
            $acceptedCurrencyData = $data["acceptedCurrency"];
            $acceptedCurrencyValue = $acceptedCurrencyData["value"];
            $acceptedCurrencyArr = empty($acceptedCurrencyValue) ? null : json_decode($acceptedCurrencyValue);

            $primaryCurrencyData = $data["primaryCurrency"];
            if ($primaryCurrencyData) {
                $primaryCurrency = $primaryCurrencyData["value"];
            }

            $acceptedCurrencyFloatingRatioData = $data["acceptedCurrencyFloatingRatio"];
            if ($acceptedCurrencyFloatingRatioData) {
                $acceptedCurrencyFloatingRatioValue = $acceptedCurrencyFloatingRatioData["value"];
                $acceptedCurrencyFloatingRatio = empty($acceptedCurrencyFloatingRatioValue) ? new stdClass() : json_decode($acceptedCurrencyFloatingRatioValue);
            }
        }

        $returnData = array(
            "acceptedCurrency" => $acceptedCurrencyArr ? $acceptedCurrencyArr : [],
            "primaryCurrency" => $primaryCurrency ? $primaryCurrency : null,
            "acceptedCurrencyFloatingRatio" => $acceptedCurrencyFloatingRatio ? $acceptedCurrencyFloatingRatio : new stdClass()
        );
        return $returnData;
    }

    public function getUserSettingByUserID($userID, $nameArr, $columns = null)
    {
        $userModel = $this->userModel;

        $data = $userModel->getUserSettingByUserID($userID, $nameArr, $columns);

        return $data;
    }
    
    public function getUserSettingByName($userID, $nameArr, $columns = null, $mapColumn = null)
    {
        $userModel = $this->userModel;

        $data = $userModel->getUserSettingByName($userID, $nameArr, $columns, $mapColumn);

        return $data;
    }

    public function getAddressByExternalAddressAndAddressType($addressObj, $columns = null)
    {
        $userModel = $this->userModel;

        $data = $userModel->getAddressByExternalAddressAndAddressType($addressObj, $columns);
        return $data;
    }

    public function updateUserAcceptedCurrency($userObj)
    {
        $userModel = $this->userModel;

        $userID = $userObj->userID;

        if (empty($userObj->acceptedCurrencyArr)) {
            $acceptedCurrencyArr = '';
            $primaryCurrency = '';
        } else {
            $acceptedCurrencyArr = json_encode($userObj->acceptedCurrencyArr);
            $primaryCurrency = $userObj->primaryCurrency;
        }

        $data1 = $userModel->updateUserSetting($userID, "acceptedCurrency", $acceptedCurrencyArr);
        $data2 = $userModel->updateUserSetting($userID, "primaryCurrency", $primaryCurrency);

        return $data;
    }

    public function updateUserSetting($userObj)
    {
        $userModel = $this->userModel;

        $userID = $userObj->userID;
        $name = $userObj->name;
        $value = $userObj->value;

        $data = $userModel->updateUserSetting($userID, $name, $value);
        return $data;
    }
    public function createLiveGroupChatRoom($chatroomObj)
    {
        $liveGroupChatModel = $this->liveGroupChatModel;

        $chatroomData = $liveGroupChatModel->createLiveGroupChatRoom($chatroomObj);

        return $chatroomData;
    }

    public function getLiveGroupChatRoomDetailsByBusinessIDUserID($chatroomObj, $columns = null)
    {
        $liveGroupChatModel = $this->liveGroupChatModel;

        $chatroomData = $liveGroupChatModel->getLiveGroupChatRoomDetailsByBusinessIDUserID($chatroomObj, $columns);

        return $chatroomData;
    }

    public function getLiveGroupChatRoomDetailsByChatroomID($chatroomObj, $columns = null)
    {
        $liveGroupChatModel = $this->liveGroupChatModel;

        $chatroomData = $liveGroupChatModel->getLiveGroupChatRoomDetailsByChatroomID($chatroomObj, $columns);

        return $chatroomData;
    }

    public function getLiveGroupChatRoomDetailsForBusinessToBusiness($userID1, $userID2, $columns = null)
    {
        $liveGroupChatModel = $this->liveGroupChatModel;

        $chatroomData = $liveGroupChatModel->getLiveGroupChatRoomDetailsForBusinessToBusiness($userID1, $userID2, $columns);

        return $chatroomData;
    }

    public function getWalletTransactionByTxHash($transactionHash)
    {
        $db = $this->db;
        $xunWalletTransactionModel = new XunWalletTransactionModel($db);

        $data = $xunWalletTransactionModel->getWalletTransactionByTxHash($transactionHash);
        return $data;
    }

    public function uploadProfilePictureBinval($userID, $imageBinval, $imageType, $userType)
    {
        if (!empty($imageBinval) && !empty($imageType)) {
            $uploadImageRet = $this->uploadImageToAws($userID, $userType, $imageBinval, $imageType);
        }

        return $uploadImageRet;
    }

    public function uploadPictureBase64($userID, $imageBase64, $userType, $title)
    {
        global $xunAws, $setting, $config;

        if (!empty($imageBase64)) {
            //  separate base64 image string to binval and image type
            $imageParts = explode(";base64,", $imageBase64);
            $contentType = str_replace("data:", "", $imageParts[0]);
            $imageBinval = $imageParts[1];
        }

        $imageTypeAux = explode("/", $contentType);
        $imageType = $imageTypeAux[1];
        $imageDecoded = base64_decode($imageBinval);

        $bucket = $setting->systemSetting["awsS3ImageBucket"];
        $env = $config["environment"];
        if ($env != "prod") {
            $key = "dev/";
        }

        if (!is_null($title)){
            $key .= $userID . '/' . $title . '/' . $userID . '.' . $imageType;
        }else{
            $key .= $userID . '/' . $userID . '.' . $imageType;
        }

        // $key = $env . '/' . $userType . '/' . $userID . '/' . $userID . '.' . $imageType;
        // $contentType = 'image/' . $imageType;

        $s3Params = [];
        $s3Params["s3_bucket"] = $bucket;
        $s3Params["s3_file_key"] = $key;
        $s3Params["file_body"] = $imageDecoded;
        $s3Params["content_type"] = $contentType;
        $retVal = $xunAws->s3_put_object($s3Params);

        return $retVal;
    }

    public function uploadProfilePictureBase64($userID, $imageBase64, $userType, $title)
    {
        if (!empty($imageBase64)) {
            //  separate base64 image string to binval and image type
            $imageParts = explode(";base64,", $imageBase64);
            $contentType = str_replace("data:", "", $imageParts[0]);
            $imageBinval = $imageParts[1];

            $uploadImageRet = $this->uploadImageToAws($userID, $userType, $imageBinval, $contentType, $title);
        }

        return $uploadImageRet;
    }

    public function uploadImageToAws($userID, $userType, $imageBinval, $contentType, $title = null)
    {
        global $xunAws, $setting, $config;

        $imageTypeAux = explode("/", $contentType);
        $imageType = $imageTypeAux[1];
        $imageDecoded = base64_decode($imageBinval);

        $bucket = $setting->systemSetting["awsS3ImageBucket"];
        $env = $config["environment"];
        if ($env == "prod") {
            $key = "profile/";
        } else {
            $key = "dev_profile/";
        }

        if (!is_null($title)){
            $key .= $userID . '/' . $title . '/' . $userID . '.' . $imageType;
        }else{
            $key .= $userID . '/' . $userID . '.' . $imageType;
        }

        // $key = $env . '/' . $userType . '/' . $userID . '/' . $userID . '.' . $imageType;
        // $contentType = 'image/' . $imageType;

        $s3Params = [];
        $s3Params["s3_bucket"] = $bucket;
        $s3Params["s3_file_key"] = $key;
        $s3Params["file_body"] = $imageDecoded;
        $s3Params["content_type"] = $contentType;
        $retVal = $xunAws->s3_put_object($s3Params);

        return $retVal;
    }

    public function uploadProfilePictureToAWS($userID, $image, $userType)
    {
        global $xunAws, $setting, $config;

        $imageParts = explode(";base64,", $image);
        $contentType = str_replace("data:", "", $imageParts[0]);
        $imageTypeAux = explode("/", $contentType);
        $imageType = $imageTypeAux[1];
        $imageBase64 = $imageParts[1];
        $imageDecoded = base64_decode($imageBase64);

        $bucket = $setting->systemSetting["awsS3ImageBucket"];
        $env = $config["environment"];
        if ($env == "prod") {
            $key = "profile/";
        } else {
            $key = "dev_profile/";
        }

        $key .= $userID . '/' . $userID . '.' . $imageType;

        $s3Params = [];
        $s3Params["s3_bucket"] = $bucket;
        $s3Params["s3_file_key"] = $key;
        $s3Params["file_body"] = $imageDecoded;
        $s3Params["content_type"] = $contentType;

        $retVal = $xunAws->s3_put_object($s3Params);
        return $retVal;
    }

    public function insertCustomCoinTransactionToken($txObj)
    {
        $userModel = $this->userModel;

        $data = $userModel->insertCustomCoinTransactionToken($txObj);

        return $data;
    }


}
