<?php
class XunBusinessService extends AbstractXunUser
{
    public function __construct($db)
    {
        parent::__construct($db);

        $this->db = $db;
        $this->businessModel = new XunBusinessModel($db);
    }

    public function getBusinessDetails($businessID)
    {
        $businessModel = $this->businessModel;
        $business = $businessModel->getBusinessDetailsByBusinessID($businessID);

        return $business;
    }

    public function getBusinessByBusinessID($businessID, $columns = null)
    {
        $businessModel = $this->businessModel;
        $business = $businessModel->getBusinessByBusinessID($businessID, $columns);

        return $business;
    }

    public function createBusinessApp($mobile, $businessEmail, $businessName, $password, $businessImage)
    {
        global $config;
        $businessModel = $this->businessModel;

        $businessAccount = $businessModel->getBusinessAccountByEmail($businessEmail);

        if ($businessAccount) {
            return array("code" => 0, "message_code" => 'E00043');
        }

        $xunBusiness = $businessModel->getBusinessByBusinessName($businessName);
        if ($xunBusiness) {
            return array("code" => 0, "message_code" => 'E00468' /*Business of this name already exists. Please try again*/);
        }

        $date = date("Y-m-d H:i:s");

        $erlangServer = $config["erlang_server"];
        $businessUserID = $this->createUser(array("businessName" => $businessName, "server_host" => $erlangServer));

        $referral_code = "";
        $businessAccountID = $businessModel->createBusinessAccount($businessUserID, $businessEmail, $password, $referral_code, $mobile);

        $businessDetailsID = $businessModel->createBusinessDetails($businessUserID, $businessEmail, $businessName, $businessImage);

        $initBusinessRet = $this->initialiseNewBusiness($businessUserID, $businessEmail, $businessName, $mobile, $erlangServer);

        $uploadImageRet = $this->updateBusinessProfilePicture($businessUserID, $businessImage);

        $returnArr = array(
            "code" => 1,
            "business_id" => $businessUserID,
            "business_follow_id" => $initBusinessRet["businessFollowID"],
            "employee_id" => $initBusinessRet["employeeID"],
        );
        return $returnArr;
    }

    protected function createUser($businessUser)
    {
        // server host, type, nickname = business name
        $userModel = $this->userModel;

        $businessName = $businessUser["businessName"];
        $server_host = $businessUser["server_host"];

        $userID = $userModel->createBusinessUser($server_host, $businessName);

        return $userID;
    }

    public function initialiseNewBusiness($businessID, $businessEmail, $businessName, $ownerMobile, $erlangServer)
    {
        $businessModel = $this->businessModel;

        $employeeOldID = $this->getEmployeeOldId($businessID, $ownerMobile);
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
        $employeeParams = array(
            "business_id" => $businessID,
            "name" => $businessName,
            "mobile" => $ownerMobile,
            "employment_status" => "confirmed",
            "role" => "owner",
            "old_id" => $employeeOldID,
        );

        $employeeID = $businessModel->createBusinessEmployee($employeeParams);

        $defaultBusinessTag = "General";

        $businessTagParams = array(
            "business_id" => $businessID,
            "tag" => $defaultBusinessTag,
        );

        $businessTagID = $businessModel->createBusinessTag($businessTagParams);

        $businessTagEmployeeParams = array(
            "employee_id" => $employeeOldID,
            "username" => $ownerMobile,
            "tag" => $defaultBusinessTag,
            "business_id" => $businessID,
        );

        $businessModel->createBusinessTagEmployee($businessTagEmployeeParams);

        $businessFollowParams = array(
            "business_id" => $businessID,
            "username" => $ownerMobile,
            "server_host" => $erlangServer,
        );

        $businessFollowID = $businessModel->createBusinessFollow($businessFollowParams);

        $this->createDefaultBusinessSubscription($businessID);
        $returnArr = array(
            "businessFollowID" => $businessFollowID,
            "employeeID" => $employeeOldID,
        );
        return $returnArr;
    }

    public function createDefaultBusinessSubscription($businessID)
    {
        $businessModel = $this->businessModel;

        $defaultPackageCode = "S0001";

        $date = date("Y-m-d H:i:s");
        $endDate = date("Y-m-d H:i:s", strtotime('+30 days', strtotime($date)));

        $businessPackage = $businessModel->getBusinessPackageByCode($defaultPackageCode);

        $packageMessageLimit = $businessPackage ? $businessPackage["message_limit"] : '';

        $subscriptionParams = array(
            "business_id" => $businessID,
            "package_code" => $defaultPackageCode,
            "billing_id" => "",
            "message_limit" => $packageMessageLimit,
            "status" => 1,
            "startdate" => $date,
            "enddate" => $endDate,
        );

        $subscriptionID = $businessModel->createBusinessSubscription($subscriptionParams);

        return $subscriptionID;
    }

    public function updateBusinessDetails($businessObj)
    {
        $businessModel = $this->businessModel;

        $retVal = $businessModel->updateBusinessDetails($businessObj);

        $this->updateAppBusinessLivechatSetting($businessObj);

        return $retVal;
    }

    public function updateAppBusinessLivechatSetting($obj)
    {
        $businessModel = $this->businessModel;

        $livechatSettingObj = new stdClass();
        $livechatSettingObj->business_id = $obj->userID;
        $livechatSettingObj->contact_us_url = $obj->contact_us_url;

        $livechatSetting = $businessModel->getBusinessLivechatSetting($livechatSettingObj);

        if ($livechatSetting) {
            $livechatSettingObj->record_id = $livechatSetting["id"];
            $businessModel->updateBusinessContactUsURL($livechatSettingObj);
        } else {
            $businessModel->insertBusinessLivechatSetting($livechatSettingObj);
        }
    }

    public function getEmployeeOldId($business_id, $mobile)
    {
        $new_mobile = str_replace("+", "", $mobile);

        $employee_id = $business_id . "_" . $new_mobile;
        return $employee_id;
    }

    public function updateBusinessProfilePicture($businessID, $imageBase64, $source = "")
    {
        $businessModel = $this->businessModel;

        if (!empty($imageBase64)) {
            $uploadImageRet = $this->uploadProfilePicture($businessID, $imageBase64, $source);
        }

        $imageURL = isset($uploadImageRet["object_url"]) ? $uploadImageRet["object_url"] : "";

        $businessModel->updateBusinessImage($businessID, $imageBase64, $imageURL);

        return $uploadImageRet;
    }

    public function uploadProfilePicture($businessID, $image, $source = "")
    {
        global $xunAws, $setting, $config;

        $image_parts = explode(";base64,", $image);
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[1];
        $image_base64 = $image_parts[1];
        $image_decoded = base64_decode($image_base64);

        $bucket = $setting->systemSetting["awsS3ImageBucket"];
        $env = $config["environment"];

        if($source != "") {
            $key = $env . '/business/' . $source . "/" . $businessID . '/' . $businessID . '.' . $image_type;
        } else {
            $key = $env . '/business/' . $businessID . '/' . $businessID . '.' . $image_type;
        }
        
        $content_type = 'image/' . $image_type;

        $s3_params = [];
        $s3_params["s3_bucket"] = $bucket;
        $s3_params["s3_file_key"] = $key;
        $s3_params["file_body"] = $image_decoded;
        $s3_params["content_type"] = $content_type;
        $retVal = $xunAws->s3_put_object($s3_params);
        return $retVal;
    }

    public function getBusinessActiveEmployee($businessID, $columns = null)
    {
        $businessModel = $this->businessModel;
        $result = $businessModel->getBusinessActiveEmployee($businessID, $columns);

        return $result;
    }

    public function getBusinessOwner($businessID)
    {
        $businessModel = $this->businessModel;

        $data = $businessModel->getBusinessOwner($businessID);

        return $data;
    }

    public function isBusinessEmployee($businessID, $username)
    {
        $businessEmployeeArr = $this->getBusinessActiveEmployee($businessID, "id, mobile");

        $isEmployee = false;
        for ($i = 0; $i < count($businessEmployeeArr); $i++) {
            if ($businessEmployeeArr[$i]["mobile"] == $username) {
                $isEmployee = true;
                break;
            }
        }

        return $isEmployee;
    }

    public function validateApiKey($businessID, $apiKey)
    {
        $businessModel = $this->businessModel;

        $apiKeyData = $businessModel->getBusinessApiKey($businessID, $apiKey);

        if (!$apiKeyData) {
            return false;
        }

        return true;
    }

    public function getBusinessRequestMoneyByID($obj)
    {
        $businessModel = $this->businessModel;

        $data = $businessModel->getBusinessRequestMoneyByID($obj);

        return $data;
    }
    public function insertBusinessRequestMoney($obj)
    {
        $businessModel = $this->businessModel;

        $rowID = $businessModel->insertBusinessRequestMoney($obj);

        return $rowID;
    }

    public function updateBusinessRequestMoneyTxHashByID($obj)
    {
        $businessModel = $this->businessModel;

        $retVal = $businessModel->updateBusinessRequestMoneyTxHashByID($obj);

        return $retVal;
    }

    public function createBusinessCoin($obj)
    {
        $businessModel = $this->businessModel;

        $retVal = $businessModel->createBusinessCoin($obj);

        return $retVal;

    }

    public function getBusinessCoin($obj, $columns = null)
    {
        $businessModel = $this->businessModel;

        $dataArr = $this->mapBusinessCoinToArray($obj);
        $data = $businessModel->getBusinessCoin($dataArr, $columns);

        if ($data) {
            return $this->mapBusinessCoinToObj($data);
        }
        return $data;
    }

    public function getBusinessCoinArr($obj, $columns = null, $orderBy = null, $limit = null)
    {
        $businessModel = $this->businessModel;

        $dataArr = $this->mapBusinessCoinToArray($obj);
        $data = $businessModel->getBusinessCoinArr($dataArr, $columns, $orderBy, $limit);

        if ($data) {
            $objArr = [];
            foreach($data as $v){
                $objArr[] = $this->mapBusinessCoinToObj($v);
            }

            return $objArr;
        }
        return $data;
    }

    public function validateBusinessName($businessName)
    {
        $businessModel = $this->businessModel;

        $retVal = $businessModel->validateBusinessName($businessName);

        return $retVal;
    }

    private function mapBusinessCoinToObj($dataArr)
    {
        $db = $this->db;
        $businessCoin = new XunBusinessCoinModel($db);
        foreach ($dataArr as $col => $value) {
            switch ($col) {
                case "id":
                    $businessCoin->setId($value);
                    break;
                case "business_id":
                    $businessCoin->setBusinessID($value);
                    break;
                case "wallet_type":
                    $businessCoin->setWalletType($value);
                    break;
                case "symbol":
                    $businessCoin->setSymbol($value);
                    break;
                case "fiat_currency_id":
                    $businessCoin->setFiatCurrencyID($value);
                    break;
                case "total_supply":
                    $businessCoin->setTotalSupply($value);
                    break;
                case "reference_price":
                    $businessCoin->setReferencePrice($value);
                    break;
                case "unit_conversion":
                    $businessCoin->setUnitConversion($value);
                    break;
                case "card_image_url":
                    $businessCoin->setCardImageUrl($value);
                    break;
                case "font_color":
                    $businessCoin->setFontColor($value);
                    break;
                case "status":
                    $businessCoin->setStatus($value);
                    break;
                case "type":
                    $businessCoin->setType($value);
                    break;
                case "default_show":
                    $businessCoin->setDefaultShow($value);
                    break;
                case "created_at":
                    $businessCoin->setCreatedAt($value);
                    break;
                case "updated_at":
                    $businessCoin->setUpdatedAt($value);
                    break;
            }
        }
        return $businessCoin;
    }

    public function mapBusinessCoinToArray($businessCoin, $columns = null)
    {
        if (!$businessCoin instanceof XunBusinessCoinModel) {
            return false;
        }

        $tableColumns = [
            "id", "business_id", "wallet_type", "business_name", "symbol", "fiat_currency_id",
            "total_supply", "reference_price", "unit_conversion", "card_image_url",
            "font_color", "status", "type", "default_show", "created_at", "updated_at",
        ];

        if (!empty($columns)) {
            $selectedColumns = explode(",", $columns);
        } else {
            $selectedColumns = $tableColumns;
        }

        $dataArr = [];

        foreach ($selectedColumns as $col) {
            $col = trim($col);

            switch ($col) {
                case "id":
                    $dataArr[$col] = $businessCoin->getId();
                    break;
                case "business_id":
                    $dataArr[$col] = $businessCoin->getBusinessID();
                    break;
                case "wallet_type":
                    $dataArr[$col] = $businessCoin->getWalletType();
                    break;
                case "symbol":
                    $dataArr[$col] = $businessCoin->getSymbol();
                    break;
                case "fiat_currency_id":
                    $dataArr[$col] = $businessCoin->getFiatCurrencyID();
                    break;
                case "total_supply":
                    $dataArr[$col] = $businessCoin->getTotalSupply();
                    break;
                case "reference_price":
                    $dataArr[$col] = $businessCoin->getReferencePrice();
                    break;
                case "unit_conversion":
                    $dataArr[$col] = $businessCoin->getUnitConversion();
                    break;
                case "card_image_url":
                    $dataArr[$col] = $businessCoin->getCardImageUrl();
                    break;
                case "font_color":
                    $dataArr[$col] = $businessCoin->getFontColor();
                    break;
                case "status":
                    $dataArr[$col] = $businessCoin->getStatus();
                    break;
                case "type":
                    $dataArr[$col] = $businessCoin->getType();
                    break;
                case "default_show":
                    $dataArr[$col] = $businessCoin->getDefaultShow();
                    break;
                case "created_at":
                    $dataArr[$col] = $businessCoin->getCreatedAt();
                    break;
                case "updated_at":
                    $dataArr[$col] = $businessCoin->getUpdatedAt();
                    break;
            }
        }
        return $dataArr;
    }
}
