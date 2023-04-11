<?php
class GiftnPay
{
    private $devURL = "http://testgiftnpaymember.speed101.pw/api/webservices.php";
    private $prodURL = "https://www.giftnpay.com/api/webservices.php";
    private $baseURL;

    public function __construct($db, $setting, $post)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->post = $post;
        $this->init();
    }

    public function init()
    {
        global $config;
        $setting = $this->setting;

        $giftnpayUrl = $setting->systemSetting["giftnpayUrl"];
        $this->baseURL = $giftnpayUrl;
    }

    public function getCategoryList()
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $command = "apiGetCategoryList";
        $memberID = $setting->systemSetting["giftnpayMemberID"];
        $apiKey = $setting->systemSetting["giftnpayApiKey"];

        $url = $this->baseURL;

        $page = 1;
        $fetchData = true;
        $insertDataArr = [];

        $db->where("type_id", 0, "!=");
        $categoryList = $db->map("type_id")->ArrayBuilder()->get("xun_pay_product_type");

        while ($fetchData) {
            // echo "\n fetching data.. ";
            $curlParams = array(
                "command" => $command,
                "memberID" => $memberID,
                "apiKey" => $apiKey,
                "pageNumber" => $page,
            );
            $curlResponse = $post->curl_post($url, $curlParams, 0);

            if ($curlResponse["code"] === 0) {
                $data = $curlResponse["data"];

                $listing = $data["listing"];

                for ($i = 0; $i < count($listing); $i++) {
                    $listingData = $listing[$i];

                    $id = $listingData["id"];
                    $categoryData = $categoryList[$id];

                    if (isset($categoryData)) {
                        unset($categoryList[$id]);
                        continue;
                    }

                    $name = $listingData["name"];
                    $isPopular = $listingData["is_popular"];
                    $popularPriority = $listingData["popular_priority"];

                    $insertData = array(
                        "type" => "",
                        "type_id" => $id,
                        "name" => $name,
                        "is_popular" => $isPopular,
                        "popular_priority" => $popularPriority,
                        "status" => 0,
                    );

                    $insertDataArr[] = $insertData;
                }

                $totalPage = $data["totalPage"];

                if ($page >= $totalPage) {
                    $fetchData = false;
                } else {
                    $page += 1;
                }
            } else {
                // echo "\nCurl Response Error, statusMsg: " . $curlResponse["statusMsg"];

                break;
            }
        }

        if (!empty($insertDataArr)) {
            $db->insertMulti("xun_pay_product_type", $insertDataArr);
        }

        if(!empty($categoryList)){
            foreach($categoryList as $data){
                $updateData = [];
                $updateData["status"] = 0;
                $db->where("id", $data["id"]);
                $db->update("xun_pay_product_type", $updateData);
            }
        }
    }

    public function getProductList($searchData = null)
    {
        global $log;
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $command = "apiGetProductList";
        $memberID = $setting->systemSetting["giftnpayMemberID"];
        $apiKey = $setting->systemSetting["giftnpayApiKey"];
        $url = $this->baseURL;

        $page = 1;
        $fetchData = true;
        $insertDataArr = [];

        $date = date("Y-m-d H:i:s");

        $db->where("provider_id", 3);
        $productList = $db->map("product_code")->ArrayBuilder()->get("xun_pay_product");
        $productTypeMainList = $db->get("xun_pay_product_type");
        foreach ($productTypeMainList as $productType) {
            $typeId = $productType["type_id"];
            $productTypeArr[$typeId] = $productType;

            $type = $productType["type"];
            if ($type != '') {
                $productTypeTagArr[$type] = $productType;
            }
        }

        while ($fetchData) {
            // echo "\n fetching data.. ";
            $curlParams = array(
                "command" => $command,
                "memberID" => $memberID,
                "apiKey" => $apiKey,
                "pageNumber" => $page,
                "searchData" => $searchData,
            );
            $curlResponse = $post->curl_post($url, $curlParams, 0);

            if ($curlResponse["code"] === 0) {
                $data = $curlResponse["data"];

                $listing = $data["listing"];
                if($searchData && $listing){
                    foreach($searchData as $key => $value){
                        $dataName = $value['dataName'];

                        if($dataName == 'bid'){
                            $bid_value = $value['dataValue'];
                        }
                        elseif($dataName == 'brandNo'){
                            $brandno_value = $value['dataValue'];
                        }
                    }
                    
                    $updateArr = array(
                        "active" => 0,
                        "updated_at" => date("Y-m-d H:i:s"),
                    );
                    $db->where('product_code', $bid_value);
                    $db->update('xun_pay_product', $updateArr);
                }

                for ($i = 0; $i < count($listing); $i++) {
                    $listingData = $listing[$i];
                    $bid = $listingData["bid"];

                    // $productData = $productList[$bid];

                    $categoryID = $listingData["category_id"];
                    $countryCode = strtolower($listingData["country_code"]);
                    $currencyCode = strtolower($listingData["currency_code"]);
                    $description = $listingData["description"];
                    $imageUrl = $listingData["image_url"];
                    $isHotSell = $listingData["is_hot_sell"];
                    $name = $listingData["name"];
                    $details = $listingData["details"];
                    $discountPercent = $listingData["discount_percent"];
                    $tag = $listingData["tag"];
                    $inputType = $listingData["input_type"];

                    $categoryID = $categoryID ? $categoryID : [];
                    //  backward compatible
                    $tag = str_replace(" ", '', $tag);
                    $tag = $tag ? $tag : "giftcard";
                    if ($tag != '') {
                        $tag = strtolower($tag);
                        $tagType = $productTypeTagArr[$tag];
                        if ($tagType) {
                            $tagTypeId = $tagType["id"];
                        }
                    }

                    $tagTypeId = $tagTypeId ? $tagTypeId : 0;

                    //  check if input type is null then fallback to old one
                    //  backward compatible
                    if ($tagTypeId == 3) {
                        $inputType["email"] = "input";
                    }
                    $inputTypeJson = $inputType ? json_encode($inputType) : "";
                    $amountType = $inputType["amount"];
                    $amountType = $amountType ? $amountType : "dropdown";

                    $typeIds = [];
                    $productOptionList = [];

                    if (!$details) {
                        continue;
                    }

                    switch ($tagTypeId) {
                        case 1:
                            $accountType = "Phone Number";
                            break;
                        case 2:
                            $accountType = "Account Number";
                            break;
                        case 3:
                            $accountType = "email";
                            break;
                        default:
                            $accountType = "Phone Number";
                    }

                    $updateProductData = array(
                        "name" => $name,
                        "description" => $description,
                        "type" => $tagTypeId,
                        "image_url" => $imageUrl,
                        "account_type" => $accountType,
                        "country_iso_code2" => $countryCode,
                        "currency_code" => $currencyCode,
                        "active" => 1,
                        "is_hot_sell" => $isHotSell,
                        "discount_percent" => $discountPercent,
                        "input_type" => $inputTypeJson,
                        "updated_at" => $date,
                    );

                    $insertProductData = $updateProductData;
                    $insertProductData["provider_id"] = 3;
                    $insertProductData["product_code"] = $bid;
                    $insertProductData["command"] = "";
                    $insertProductData["popularity"] = 0;
                    $insertProductData["created_at"] = $date;

                    $db->onDuplicate($updateProductData);
                    $productId = $db->insert("xun_pay_product", $insertProductData);

                    if (!$productId || gettype($productId) == 'boolean') {
                        $log->write("\n" . date("Y-m-d H:i:s") . ": " . $db->getLastError());
                        continue;
                    }

                    $db->where("product_id", $productId);
                    $typeIds = $db->getValue("xun_pay_product_product_type_map", "type_id", null);
                    $typeIds = $typeIds ? $typeIds : [];

                    $db->where("product_id", $productId);
                    $productOptionList = $db->map("pid")->get("xun_pay_product_option");

                    unset($productList[$bid]);

                    sort($typeIds);
                    $filteredCategoryID = array_filter($categoryID);
                    $filteredCategoryID[] = "0";

                    if (!is_array($details)) {
                        continue;
                    }

                    $insertProductOptionDataArr = [];

                    // print_r($productOptionList);

                    if (empty($productOptionList)) {
                        /**
                         *  add unique on pid column after clean up table
                         * */

                        for ($j = 0; $j < count($details); $j++) {
                            $productDetailsData = $details[$j];

                            $productDiscountPercent = $productDetailsData["discount_percent"];
                            $pid = $productDetailsData["pid"];
                            $productPrice = $productDetailsData["price"];
                            $productProviderId = $productDetailsData["provider_id"];
                            $productSellPrice = $productDetailsData["sell_price"];
                            $productUtid = $productDetailsData["utid"];
                            $maxPrice = $productDetailsData["max_price"];
                            $minPrice = $productDetailsData["min_price"];

                            $insertProductOptionData = array(
                                "product_id" => $productId,
                                "pid" => $pid,
                                "provider_id" => $productProviderId,
                                "amount_type" => $amountType,
                                "amount" => $productPrice,
                                "sell_price" => $productSellPrice,
                                "discount_percent" => $productDiscountPercent,
                                "utid" => $productUtid,
                                "max_price" => $maxPrice,
                                "min_price" => $minPrice,
                                "status" => 1,
                            );

                            $insertProductOptionDataArr[] = $insertProductOptionData;
                        }
                    } else {
                        $currentProductOptionPidList = array_keys($productOptionList);
                        $productOptionPidList = array_column($details, "pid");

                        // get removed pids
                        $removedPidList = array_diff($currentProductOptionPidList, $productOptionPidList);

                        for ($j = 0; $j < count($details); $j++) {
                            $productDetailsData = $details[$j];

                            $productDiscountPercent = $productDetailsData["discount_percent"];
                            $pid = $productDetailsData["pid"];
                            $productPrice = $productDetailsData["price"];
                            $productProviderId = $productDetailsData["provider_id"];
                            $productSellPrice = $productDetailsData["sell_price"];
                            $productUtid = $productDetailsData["utid"];
                            $maxPrice = $productDetailsData["max_price"];
                            $minPrice = $productDetailsData["min_price"];

                            $productOptionData = $productOptionList[$pid];
                            if ($productOptionData) {
                                // update
                                $productOptionDataId = $productOptionData["id"];
                                $updateProductOptionData = [];
                                $updateProductOptionData = array(
                                    "provider_id" => $productProviderId,
                                    "amount" => $productPrice,
                                    "amount_type" => $amountType,
                                    "sell_price" => $productSellPrice,
                                    "max_price" => $maxPrice,
                                    "min_price" => $minPrice,
                                    "discount_percent" => $productDiscountPercent,
                                    "utid" => $productUtid,
                                    "status" => 1,
                                );

                                $db->where("id", $productOptionDataId);
                                $db->update("xun_pay_product_option", $updateProductOptionData);
                            } else {
                                $insertProductOptionData = array(
                                    "product_id" => $productId,
                                    "pid" => $pid,
                                    "provider_id" => $productProviderId,
                                    "amount_type" => $amountType,
                                    "amount" => $productPrice,
                                    "sell_price" => $productSellPrice,
                                    "max_price" => $maxPrice,
                                    "min_price" => $minPrice,
                                    "discount_percent" => $productDiscountPercent,
                                    "utid" => $productUtid,
                                    "status" => 1,
                                );
                                $insertProductOptionDataArr[] = $insertProductOptionData;
                            }
                        }

                        if (!empty($removedPidList)) {
                            for ($j = 0; $j < count($removedPidList); $j++) {
                                $removedPid = $removedPidList[$j];

                                $productOptionData = $productOptionList[$removedPid];

                                if ($productOptionData) {
                                    //  inactivate product option
                                    $productOptionDataId = $productOptionData["id"];

                                    $updateProductOptionData = [];
                                    $updateProductOptionData["status"] = 0;
                                    $db->where("id", $productOptionDataId);
                                    $db->update("xun_pay_product_option", $updateProductOptionData);
                                }
                            }
                        }
                    }

                    if (!empty($insertProductOptionDataArr)) {
                        $rowIds = $db->insertMulti("xun_pay_product_option", $insertProductOptionDataArr);
                        if (!$rowIds) {
                            $log->write("\n" . date("Y-m-d H:i:s") . ": " . $db->getLastError());
                            // print_r($db);
                        }
                    }

                    $insertCategoryDataArr = [];
                    $tempCat = [];
                    $newCat = [];
                    for ($j = 0; $j < count($filteredCategoryID); $j++) {
                        unset($typeId);
                        $productCategoryId = (int) $filteredCategoryID[$j];
                        // echo "\nproductCategoryId $productCategoryId";
                        if ($productCategoryId === 0) {
                            $typeId = $tagTypeId;
                        } else {
                            $productType = $productTypeArr[$productCategoryId];
                            if (isset($productType)) {
                                $typeId = $productType["id"];
                            }
                        }
                        if (!isset($typeId)) {
                            continue;
                        }

                        if (in_array($typeId, $typeIds)) {
                            $index = array_search($typeId, $typeIds);
                            array_splice($typeIds, $index, 1);
                            continue;
                        }

                        // echo "\n ProductId: $bid CategoryId: $productCategoryId, type id $typeId";

                        $insertCategoryData = array(
                            "product_id" => $productId,
                            "type_id" => $typeId,
                        );

                        $insertCategoryDataArr[] = $insertCategoryData;
                    }

                    if (count($insertCategoryDataArr) > 0) {
                        $rowIds = $db->insertMulti("xun_pay_product_product_type_map", $insertCategoryDataArr);
                        if (!$rowIds) {
                            $log->write("\n" . date("Y-m-d H:i:s") . ": " . $db->getLastError());
                            //    print_r($db);
                        }
                    }

                    if (!empty($typeIds)) {
                        for ($j = 0; $j < count($typeIds); $j++) {
                            $db->where("product_id", $productId);
                            $db->where("type_id", $typeIds[$j]);
                            $db->delete("xun_pay_product_product_type_map");
                        }
                    }
                }

                $totalPage = $data["totalPage"];

                if ($page >= $totalPage) {
                    $fetchData = false;
                } else {
                    $page += 1;
                }
            } else {
                // echo "\nCurl Response Error, statusMsg: " . $curlResponse["statusMsg"];
                $log->write("\n" . date("Y-m-d H:i:s") . ": " . "Curl Response Error, statusMsg: " . $curlResponse["statusMsg"]);
                break;
            }
        }

        //  inactivate remaining products in $productList
        foreach ($productList as $productData) {
            $productId = $productData["id"];

            if ($productData["active"] == 1) {
                $updateData = [];
                $updateData["active"] = 0;
                $updateData["updated_at"] = $date;
                $db->where("id", $productId);
                $db->update("xun_pay_product", $updateData);
            }

        }
    }

    public function patchProductOptionUtid()
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $command = "apiGetProductList";
        $memberID = $setting->systemSetting["giftnpayMemberID"];
        $apiKey = $setting->systemSetting["giftnpayApiKey"];
        $url = $this->baseURL;

        $page = 1;
        $fetchData = true;
        $insertDataArr = [];

        $date = date("Y-m-d H:i:s");

        $productTypeArr = $db->map("type_id")->ArrayBuilder()->get("xun_pay_product_type");

        $db->where("provider_id", 3);
        $productDataArr = $db->map("product_code")->ArrayBuilder()->get("xun_pay_product");

        while ($fetchData) {
            $curlParams = array(
                "command" => $command,
                "memberID" => $memberID,
                "apiKey" => $apiKey,
                "pageNumber" => $page,
            );
            $curlResponse = $post->curl_post($url, $curlParams, 0);

            if ($curlResponse["code"] === 0) {
                $data = $curlResponse["data"];

                $listing = $data["listing"];

                for ($i = 0; $i < count($listing); $i++) {
                    $productData = $listing[$i];
                    $details = $productData["details"];
                    $bid = $productData["bid"];

                    $product = $productDataArr[$bid];
                    if (!$product) {
                        continue;
                    }

                    $productId = $product["id"];
                    $pname = $product['name'];
                    $db->where("product_id", $productId);
                    $productOptionArr = $db->map("pid")->ArrayBuilder()->get("xun_pay_product_option");
                    if (!empty($details)) {
                        $head = $details[0];
                        $utid = $head["utid"];
                        echo "\n product_id : $productId NAme: $pname utid $utid\n";
                        $updateData = [];
                        $updateData["utid"] = $utid;
                        $db->where("product_id", $productId);
                        $db->update("xun_pay_product_option", $updateData);
                    }
                }

                $totalPage = $data["totalPage"];

                if ($page >= $totalPage) {
                    $fetchData = false;
                } else {
                    $page += 1;
                }
            } else {
                echo "\nCurl Response Error, statusMsg: " . $curlResponse["statusMsg"];

                break;
            }
        }
    }

    public function buyProductVerification($params)
    {
        // {
        //     "command": "apiBuyProductVerification",
        //     "memberID": "25757195",
        //     "apiKey": "$2y$10$Snd3.Dg4xQpAxXS765OZ8eyLoNB24AwCnVr6tGQE1Ej.T.xlxABSm",
        //     "amount" : "5.00",
        //     "quantity" : "1",
        //     "utid" : "27222",
        //     "rewardName" : "Amazon.com",
        //     "providerID" : "10"
        // }

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $command = "apiBuyProductVerification";
        $memberID = $setting->systemSetting["giftnpayMemberID"];
        $apiKey = $setting->systemSetting["giftnpayApiKey"];

        $url = $this->baseURL;

        $amount = $params["amount"];
        $quantity = $params["quantity"];
        $productId = $params["productId"];
        $phoneNumber = $params["phoneNumber"];
        $accountNumber = $params["accountNumber"];

        $curlParams = array(
            "command" => $command,
            "memberID" => $memberID,
            "apiKey" => $apiKey,
            "amount" => $amount,
            "quantity" => $quantity,
            "productID" => $productId,
        );

        if ($phoneNumber) {
            $curlParams["phoneNum"] = $phoneNumber;
        }
        if ($accountNumber) {
            $curlParams["accNum"] = $accountNumber;
        }
        /**
         * {
        "command": "apiBuyProductVerification",
        "memberID": "25757195",
        "apiKey": "$2y$10$Snd3.Dg4xQpAxXS765OZ8eyLoNB24AwCnVr6tGQE1Ej.T.xlxABSm",
        "productID" : "18",
        "amount" : "10",
        "quantity" : "1",
        "phoneNum" : "0102208361",
        "accNum" : ""
        }
         */
        $curlResponse = $post->curl_post($url, $curlParams, 0);
        return $curlResponse;
        // if ($curlResponse["code"] === 0) {
        //     $data = $curlResponse["data"];

        //     // $totalPayAmount = $data["totalPayAmount"];
        // }
    }

    public function buyProductPaymentConfirmation($params)
    {
        // "command": "apiBuyProductPaymentConfirmation",
        // "memberID": "25757195",
        // "apiKey": "$2y$10$Snd3.Dg4xQpAxXS765OZ8eyLoNB24AwCnVr6tGQE1Ej.T.xlxABSm",
        // "amount" : "5.00",
        // "quantity" : "1",
        // "utid" : "27222",
        // "rewardName" : "Amazon.com",
        // "providerID" : "10",
        // "payCurrencyCode" : "USD"

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $command = "apiBuyProductPaymentConfirmation";
        $memberID = $setting->systemSetting["giftnpayMemberID"];
        $apiKey = $setting->systemSetting["giftnpayApiKey"];

        $url = $this->baseURL;

        $amount = $params["amount"];
        $quantity = $params["quantity"];
        $productId = $params["productId"];
        $phoneNumber = $params["phoneNumber"];
        $accountNumber = $params["accountNumber"];

        $curlParams = array(
            "command" => $command,
            "memberID" => $memberID,
            "apiKey" => $apiKey,
            "amount" => $amount,
            "quantity" => $quantity,
            "productID" => $productId,
        );

        if ($phoneNumber) {
            $curlParams["phoneNum"] = $phoneNumber;
        }
        if ($accountNumber) {
            $curlParams["accNum"] = $accountNumber;
        }
        // {
        //     "command": "apiBuyProductPaymentConfirmation",
        //     "memberID": "25757195",
        //     "apiKey": "$2y$10$Snd3.Dg4xQpAxXS765OZ8eyLoNB24AwCnVr6tGQE1Ej.T.xlxABSm",
        //     "productID" : "18",
        //     "amount" : "10",
        //     "quantity" : "1",
        //     "phoneNum" : "0102208361",
        //     "accNum" : ""
        // }

        $curlResponse = $post->curl_post($url, $curlParams, 0);

        return $curlResponse;
        // if ($curlResponse["code"] === 0) {
        //     $data = $curlResponse["data"];

        //     // $totalPayAmount = $data["totalPayAmount"];
        // }
    }

    public function giftnpayCallback($params)
    {
        $command = trim($params["command"]);

        switch ($command) {
            case "redeemCode":
                $res = $this->redeemCodeCallback($params);
                break;

            case "updateProductList":
                $this->updateProductListCallback($params);
                break;

            default:
                break;
        }

        return array("received" => "1");
    }

    private function redeemCodeCallback($params)
    {
        /**
         * {
        "command": "redeemCode",
        "paymentID": 3456445243,
        orderID: "",
        "status": success/fail,
        "message": error message,
        "action": charge/refund,
        "redeemCode": 1000337,
        "expiredDate": 2020-10-03,
        "lastUpdate": 2019-10-03 12:33:21,
        }

        command : redeemCode
        paymentID : 7813970469573285
        status : success
        message :
        action : charge
        redeemCode : DEMO CODE 0
        expiredDate : 1970-01-01
        lastUpdate : 2019-11-25 10:39:35
         */
        global $country, $log, $xunBusinessPartner, $account;
        global $xun_numbers;
        $recipientList = $xun_numbers;

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $paymentID = trim($params["paymentID"]);
        $orderID = trim($params["orderID"]);
        $details = $params["details"];
        $status = trim($params["status"]);
        $message = trim($params["message"]);
        $action = trim($params["action"]);
        $redeemCode = trim($params["redeemCode"]);
        $expiredDate = trim($params["expiredDate"]);
        $lastUpdate = trim($params["lastUpdate"]);

        // send email
        $payObj = new stdClass();
        $payObj->providerTransactionId = $paymentID;
        $payObj->orderId = $orderID;

        $xunPayService = new XunPayService($db);
        $payTransaction = $xunPayService->getProductTransaction($payObj);

        if (!$payTransaction) {
            $log->write(date("Y-m-d H:i:s") . ": GiftnPay RedeemCode Callback - Error: PaymentID $paymentID not found");
            return array("errorMsg" => "Pay transaction is empty");
        }

        if ($status == "fail") {
            $payStatus = "failed";
        } else if ($status == "success") {
            $payStatus = "success";
        } else {
            $log->write(date("Y-m-d H:i:s") . ": GiftnPay RedeemCode Callback - Error: PaymentID: $paymentID -  invalid status ($status)");
            return array("errorMsg" => "Invalid status - $status");
        }

        $payTransactionId = $payTransaction["id"];
        $purchasedQuantity = $payTransaction["quantity"];
        $sellPrice = $payTransaction["sell_price"];
        $payAmount = $payTransaction["amount"];
        $walletType = $payTransaction["wallet_type"];

        $payObj->payTransactionId = $payTransactionId;
        $payTransactionItem = $xunPayService->getPayTransactionItem($payObj);

        if (!$payTransactionItem) {
            // send notification
            $log->write(date("Y-m-d H:i:s") . ": GiftnPay RedeemCode Callback - Error: PaymentID: $paymentID - Invalid orderId - $orderID");
            return array("errorMsg" => "Invalid orderId - $orderID");
        }
        if($payTransactionItem["status"] != "submitted"){
            return array("errorMsg" => "Status is not submitted");
        }

        $payTransactionItemId = $payTransactionItem["id"];

        $payTransactionItemObj = new stdClass();
        $payTransactionItemObj->id = $payTransactionItemId;
        $payTransactionItemObj->payTransactionId = $payTransactionId;
        $payTransactionItemObj->status = $payStatus;
        $payTransactionItemObj->message = $message;
        $payTransactionItemObj->action = $action;
        $payTransactionItemObj->code = $code;
        $payTransactionItemObj->expiredDate = $expiredDate;

        $xunPayService = new XunPayService($db);
        $updateResult = $xunPayService->updatePayTransactionItem($payTransactionItemObj);

        if(!$updateResult){
            $log->write(date("Y-m-d H:i:s") . ": GiftnPay RedeemCode Callback - Error: PaymentID: $paymentID - Invalid orderId - $orderID. Error updating xun_pay_transaction_item.");
            return array("errorMsg" => "");
        }

        $payTransactionItemList = $xunPayService->getPayTransactionItemList($payTransactionItem, "id");

        if ($purchasedQuantity == count($payTransactionItemList)) {
            $updateData = [];
            $updateData["status"] = "completed";
            $updateData["updated_at"] = date("Y-m-d H:i:s");
            $db->where("id", $payTransactionId);
            $db->where("status", "completed", "!=");
            $db->update("xun_pay_transaction", $updateData);
        }

        if ($payStatus == "failed") {
            //  refund failed order
            $unitPrice = bcdiv((string) $payAmount, (string) $purchasedQuantity, 8);

            $xunCompanyWallet = new XunCompanyWallet($db, $setting, $post);

            $payTransaction["unit_price"] = $unitPrice;
            $xunCompanyWallet->payTransactionItemRefund($payTransaction, $payTransactionItemId);

            return;

        } else if ($payStatus == "success") {
            //  send email

            $emailAddress = $payTransaction["email"];
            $productId = $payTransaction["product_id"];
            $pid = $payTransaction["pid"];

            $productObj = new stdClass();
            $productObj->id = $productId;

            $productData = $xunPayService->getProduct($productObj);

            if (!$productData) {
                return array("errorMsg" => "Product data is empty");
            }

            $productName = $productData["name"];
            $productDescription = $productData["description"];
            $productImage = $productData["image_url"];
            $productCategoryId = $productData["type"];

            $productOptionObj = new stdClass();
            $productOptionObj->pid = $pid;

            $productOptionData = $xunPayService->getProductOption($productOptionObj);
            // $sellPrice = $productOptionData["sell_price"];
            $sellPrice = $setting->setDecimal($sellPrice, "fiatCurrency");

            $country_iso_code2 = $productData["country_iso_code2"];

            $country_params = array("iso_code2_arr" => [$country_iso_code2]);
            $country_data_arr = $country->getCountryDataByIsoCode2($country_params);

            if (!empty($country_data_arr)) {
                $country_data = $country_data_arr[strtoupper($country_iso_code2)];
                $currencyCode = strtoupper($country_data["currency_code"]);
                $country = $country_data["name"];
            }

            $xunUserService = new XunUserService($db);

            $userId = $payTransaction["user_id"];
            $xunUser = $xunUserService->getUserByID($userId);
            $nickname = $xunUser["nickname"];
            $username = $xunUser["username"];

            if ($redeemCode == '') {
                return array("errorMsg" => "Claim Code is empty");
            }

            $emailCodeArray = array(
                "code" => $redeemCode,
                // "pin" => $pin,
                "productName" => $productName,
                "currency" => $currencyCode,
                "productSellPrice" => $sellPrice,
                "productCountry" => $country,
                "productImage" => $productImage,
                "productDescription" => $productDescription,
                "nickname" => $nickname,
            );

            $emailParams = $this->getEmailContent($emailCodeArray);
            $emailRes = $this->sendCodeEmailSes($emailAddress, $emailParams);

            if ($emailRes["code"] == 1) {
                $status = "Success";

            } else {
                $status = "Failed";
                $message = "Error sending email";
            }

            // send notification
            $notification_message = "PaymentID: " . $paymentID;
            $notification_message .= "\nProduct Name: " . $productName;
            $notification_message .= "\nProduct country: " . $country;
            $notification_message .= "\nAmount: " . $sellPrice . ' ' . $currencyCode;
            $notification_message .= "\nNickname: " . $nickname;
            $notification_message .= "\nStatus: " . $status;
            $notification_message .= "\nMessage: " . $message;
            $notification_message .= "\nTime: " . date("Y-m-d H:i:s");

            $json_params = array(
                "business_id" => "1",
                "tag" => "Pay:Gift Card Email Status",
                "message" => $notification_message,
                "mobile_list" => $recipientList,
            );

            $insert_data = array(
                "data" => json_encode($json_params),
                "message_type" => "business",
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            );

            $ids = $db->insert('xun_business_sending_queue', $insert_data);

            $coinUser = $xunUserService->getUserByUsername($walletType, null, "coin");

            if($coinUser){

                $db->where("type_id", $productCategoryId);
                $productCategory = $db->getValue("xun_pay_product_type", "name");
                $creditType = "coinCredit";
                $coinUserId = $coinUser["id"];

                $coinBalance = $account->getClientCacheBalance($coinUserId, $creditType);
                $callback_params = array(
                    "amount" => $payAmount,
                    "wallet_type" => $walletType,
                    "mobile" => $username,
                    "product_name" => $productName,
                    "product_category" => $productCategory,
                    "product_price" => $sellPrice,
                    "product_currency" => $currencyCode,
                    "balance" => $coinBalance
                );

                $business_partner_callback_params = array(
                    "wallet_type" => $walletType,
                    "command" => "shopPurchase",
                    "callback_params" => $callback_params
                );

                $xunBusinessPartner->callback_business_coin($business_partner_callback_params);
            }
        }
    }

    private function sendCodeEmailSes($emailAddress, $emailParams)
    {
        global $xunAws, $xunEmail, $setting;

        $emailParams["recipient_emails"] = array($emailAddress);
        $emailParams["html_body"] = $emailParams["body"];
        unset($emailParams["body"]);

        $result = $xunAws->send_ses_email($emailParams);
        return $result;
    }

    private function getEmailContent($params)
    {
        global $xunEmail, $setting;

        $giftcardName = $params["productName"];
        $code = $params["code"];
        $pin = $params["pin"];
        $sellPrice = $params["productSellPrice"];
        $currency = $params["currency"];
        $productCountry = $params["productCountry"];
        $productImage = $params["productImage"];
        $productDescription = $params["productDescription"];
        $nickname = $params["nickname"];

        $date = date("Y-m-d H:i:s");
        $newDateTime = date('d/m/Y h:i A', strtotime($date));

        $companyName = $setting->systemSetting["companyName"];
        $companySupportEmail = $setting->systemSetting["systemEmailAddress"];

        $emailHeader = "Gift Card";
        $emailContent =
            "<p style=\"font-size: 22px; letter-spacing: .5px; margin-bottom: 10px;text-transform: uppercase;\">" . $emailHeader . "</p>
            </div>

            <div class=\"content_details\">
                <div style=\"margin: 25px; margin-bottom: 35px;\">
                    <div align=\"center\">
                        <div style=\"margin-top: 35px; font-weight: 700;\">Dear " . $nickname . ",</div><br>
                        <div>Your " . $giftcardName . " Gift Card is Here!</div>
                    </div>

                    <div style=\"margin: 30px 0;color: #DE2D64;font-size: 24px;font-weight: 600;\" align=\"center\">" . $currency . " " . $sellPrice . "</div>

                    <div align=\"center\">
                    <div><img src=\"" . $productImage . "\" style=\"width: 250px;\"></div>
                    <div style=\"padding-top:5px;font-size: 12px;color: #DE2D64\">*Valid for use in the " . $productCountry . " only</div>
                    </div>
                    <div align=\"center\"> ";
        if ($pin != "") {
            $emailContent .= "<div style=\"margin:20px 10px 5px 10px;font-weight: 700;\"><span style=\"color: #DE2D64\">Pin & Serial Number :</span> " . $pin . "</div>";
        }
        if ($code != "") {
            $emailContent .= "<div style=\"font-weight: 700;\"><span style=\"color: #DE2D64\">Claim Code :</span> " . $code . "</div>";
        }
        $emailContent .= "</div>

                    <div style=\"padding:20px;margin-top: 20px;background: #f4f4f4;\" align=\"left\">
                        <div><span style=\"font-weight: 700;\">Description : </span>" . $productDescription . "</div>
                    </div>

                    <div style=\"margin-top: 5px;font-size: 12px; padding-bottom: 20px;text-align: center;\">If you have any problems or questions please contact us at <a href=\"mailto:" . $companySupportEmail . "\" style=\"cursor: pointer;color: blue; text-decoration: underline;\">" . $companySupportEmail . "</a>.</div>
                </div>
            </div>
        </div>";

        $emailBody = $xunEmail->getEmailHtml($emailContent, true, false);

        $translationsMessage = "%%companyName%% - " . $emailHeader;
        $subject = str_replace("%%companyName%%", $companyName, $translationsMessage);

        $emailParams["subject"] = $subject;
        $emailParams["body"] = $emailBody;

        return $emailParams;
    }

    private function updateProductListCallback($params)
    {
        // {
        //     "command": "updateProductList",
        //     "lastChangesAt": "2019-11-03 12:14:16"
        // }
        $lastChangesAt = trim($params["lastChangesAt"]);
        $bid = trim($params['bid']);
        $brandNo = trim($params['brandNo']);

        if($bid && $brandNo){
            $searchArr = array(
                "dataName" => 'bid',
                "dataValue" => $bid,
            );
            $searchData[] = $searchArr;
            unset($searchArr);
            $searchArr = array(
                "dataName" => 'brandNo',
                "dataValue" => $brandNo,
            );
            $searchData[] = $searchArr;
        }
      
        $this->getCategoryList();
        $this->getProductList($searchData);
    }
}
