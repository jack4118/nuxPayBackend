<?php
class reloadly
{
    private $ouathTokenURL = "https://auth.reloadly.com/oauth/token";
    private $devURL = "https://topups-sandbox.reloadly.com";
    private $prodURL = "https://topups.reloadly.com";
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

        $env = $config["environment"];
        $this->baseURL = ($env == "prod") ? $this->prodURL : $this->devURL;
    }

    public function getAccessToken()
    {
        $setting = $this->setting;
        $db = $this->db;
        $post = $this->post;

        $clientID = $setting->systemSetting["reloadlyClientID"];
        $clientSecret = $setting->systemSetting["reloadlyClientSecret"];

        $baseURL = $this->baseURL;
        $url = $this->ouathTokenURL;

        $postParams = [];
        $postParams["client_id"] = $clientID;
        $postParams["client_secret"] = $clientSecret;
        $postParams["grant_type"] = "client_credentials";
        $postParams["audience"] = $baseURL;

        $postHeader = [];
        $postHeader[] = "Content-Type: application/json";
        $postResponse = $post->curl_post($url, $postParams, 0, 1, $postHeader);

        if ($postResponse && isset($postResponse["access_token"])) {
            $accessToken = $postResponse["access_token"];
            $tokenType = $postResponse["token_type"];
            $expiresIn = $postResponse["expires_in"];
            $returnData = [];
            $returnData["accessToken"] = $accessToken;
            $returnData["tokenType"] = $tokenType;
            $returnData["expiresIn"] = $expiresIn;

        } else {
            // $returnData = [];
            // $returnData["code"] = 0;
            $returnData = null;
        }
        return $returnData;
    }

    public function getAccountBalance()
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $accessTokenData = $this->getAccessToken();
        if (!$accessTokenData) {
            return;
        }
        $accessToken = $accessTokenData["accessToken"];
        $url = $this->baseURL . "/accounts/balance";
        $postHeader = [];
        $postHeader[] = "Accept: application/com.reloadly.topups-v1+json";
        $postHeader[] = "Authorization: Bearer " . $accessToken;

        $postResponse = $post->curl_get($url, null, 0, $postHeader);

        return $postResponse;
    }

    public function getCountries()
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $accessTokenData = $this->getAccessToken();
        if (!$accessTokenData) {
            return;
        }
        $accessToken = $accessTokenData["accessToken"];
        $url = $this->baseURL . "/countries";
        $curlHeader = [];
        $curlHeader[] = "Accept: application/com.reloadly.topups-v1+json";
        $curlHeader[] = "Authorization: Bearer " . $accessToken;

        $curlResponse = $post->curl_get($url, null, 0, $curlHeader);

        $response = json_decode($curlResponse, true);

        foreach($response as $data){
            $imageUrl = $data["flag"];
            $isoName = $data["isoName"];

            $updateData = [];
            $updateData["image_url"] = $imageUrl;

            $db->where("iso_code2", $isoName);
            $db->where("image_url", "");
            $db->update("country", $updateData);
        }
        return $curlResponse;
    }

    public function getOperatorListing()
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $accessTokenData = $this->getAccessToken();
        if (!$accessTokenData) {
            return;
        }
        $accessToken = $accessTokenData["accessToken"];
        $url = $this->baseURL . "/operators";

        $curlHeader = [];
        $curlHeader[] = "Accept: application/com.reloadly.topups-v1+json";
        $curlHeader[] = "Authorization: Bearer " . $accessToken;

        $page = 1;
        $size = 100;
        // $curlParams["suggestedAmounts"] = true;
        // $curlParams["suggestedAmountsMap"] = true;
        $fetchData = true;
        $insertDataArr = [];
        while ($fetchData) {
            $curlParams = [];
            $curlParams["page"] = $page;
            $curlParams["size"] = $size;
            $curlResponse = $post->curl_get($url, $curlParams, 0, $curlHeader);
            $responseArr = json_decode($curlResponse, true);

            $content = $responseArr["content"];
            if (isset($content)) {
                for ($i = 0; $i < count($content); $i++) {
                    $data = $content[$i];
                    $operatorId = $data["operatorId"];
                    $name = $data["name"];
                    $denominationType = strtolower($data["denominationType"]); // FIXED/RANGE
                    $currencyCode = $data["senderCurrencyCode"];
                    $currencySymbol = $data["senderCurrencySymbol"];
                    $destinationCurrencyCode = $data["destinationCurrencyCode"];
                    $destinationCurrencySymbol = $data["destinationCurrencySymbol"];
                    $country = $data["country"];
                    $countryIsoName = strtolower($country["isoName"]);
                    $countryName = $country["name"];
                    $logoUrls = $data["logoUrls"];
                    if (!empty($logoUrls)) {
                        $imageUrl = end($logoUrls);
                    }
                    $minAmount = $data["minAmount"];
                    $maxAmount = $data["maxAmount"];
                    $localMinAmount = $data["localMinAmount"];
                    $localMaxAmount = $data["localMaxAmount"];
                    $fixedAmounts = $data["fixedAmounts"];
                    $localFixedAmounts = $data["localFixedAmounts"];

                    $insertData = array(
                        "provider_id" => 2,
                        "name" => $name,
                        "type" => 1,
                        "product_code" => $operatorId,
                        "image_url" => $imageUrl ? $imageUrl : '',
                        "image_md5" => "",
                        "account_type" => "Phone Number",
                        "command" => "Prepaid Command",
                        "country_iso_code2" => $countryIsoName,
                        "active" => 1,
                        "created_at" => date("Y-m-d H:i:s"),
                        "updated_at" => date("Y-m-d H:i:s"),
                    );

                    $rowID = $db->insert("xun_pay_product", $insertData);
                    if (!$rowID) {
                        print_r($db);
                        print_r($insertData);
                        break;
                    } else {
                        echo "\n name $name";
                        $insert_product_option_data = [];
                        $insertOptionDataArr = [];
                        if ($denominationType == "fixed") {
                            print_r($fixedAmounts);
                            for ($j = 0; $j < count($fixedAmounts); $j++) {
                                $insertProductOptionData = array(
                                    "product_id" => $rowID,
                                    "amount_type" => "dropdown",
                                    "amount" => $fixedAmounts[$j],
                                );
                                $insertOptionDataArr[] = $insertProductOptionData;
                            }
                        } else if ($denominationType == "range") {
                            $insertProductOptionMinData = array(
                                "product_id" => $rowID,
                                "amount_type" => "min",
                                "amount" => $minAmount,
                            );
                            $insertProductOptionMaxData = array(
                                "product_id" => $rowID,
                                "amount_type" => "max",
                                "amount" => $maxAmount,
                            );

                            $insertOptionDataArr[] = $insertProductOptionMinData;
                            $insertOptionDataArr[] = $insertProductOptionMaxData;
                        }

                        $db->insertMulti("xun_pay_product_option", $insertOptionDataArr);
                    }
                }

                if (count($content) < $size) {
                    $fetchData = false;
                } else {
                    $page += 1;
                }
            }
        }
        return;
    }

    public function detectPhoneNumber($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $phoneNumber = trim($params["phone_number"]);
        $countryCode = strtoupper(trim($params["country_code"]));

        $accessTokenData = $this->getAccessToken();
        if (!$accessTokenData) {
            return array("code" => 0, "message_d" => "Missing Reloadly access token.");
        }
        $accessToken = $accessTokenData["accessToken"];

        $url = $this->baseURL . "/operators/auto-detect/phone/" . $phoneNumber . "/country-code/" . $countryCode . "";

        $curlParams = [];
        $curlParams["includeBundles"] = "TRUE";

        $curlHeader = [];
        $curlHeader[] = "Accept: application/com.reloadly.topups-v1+json";
        $curlHeader[] = "Authorization: Bearer " . $accessToken;

        $curlResponse = $post->curl_get($url, null, 0, $curlHeader);
        $responseArr = json_decode($curlResponse, true);

        return $responseArr;
    }

    public function topup($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $accessTokenData = $this->getAccessToken();
        if (!$accessTokenData) {
            return array("code" => 0, "message_d" => "Missing Reloadly access token.");
        }
        $accessToken = $accessTokenData["accessToken"];

        $url = $this->baseURL . "/topups";

        $amount = $params["amount"];
        $operatorId = $params["operatorId"];
        $referenceId = $params["referenceId"];
        $senderPhone = $params["senderPhone"];
        $recipientPhone = $params["recipientPhone"];

        $curlParams = [];
        $curlParams["recipientPhone"] = array(
            "countryCode" => $recipientPhone["countryCode"],
            "number" => $recipientPhone["number"],
        );
        $curlParams["senderPhone"] = array(
            "countryCode" => $senderPhone["countryCode"],
            "number" => $senderPhone["number"],
        );
        $curlParams["operatorId"] = $operatorId;
        $curlParams["amount"] = $amount;
        $curlParams["customIdentifier"] = $referenceId;

        $curlHeader = [];
        $curlHeader[] = "Accept: application/com.reloadly.topups-v1+json";
        $curlHeader[] = "Authorization: Bearer " . $accessToken;
        $curlHeader[] = "Content-Type: application/json";
        $curlResponse = $post->curl_post($url, $curlParams, 0, 1, $curlHeader);

        return $curlResponse;
    }

    public function getFxRate($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $accessTokenData = $this->getAccessToken();
        if (!$accessTokenData) {
            return array("code" => 0, "message_d" => "Missing Reloadly access token.");
        }
        $accessToken = $accessTokenData["accessToken"];

        $url = $this->baseURL . "/operators/fx-rate";

        /**
         * $data = array(
        "amount" => "5",
        "currencyCode" => "USD",
        "operatorId" => "173",
         */
        $amount = $params["amount"];
        $operatorId = $params["operatorId"];
        $currencyCode = $params["currencyCode"];

        $curlParams = [];
        $curlParams["operatorId"] = $operatorId;
        $curlParams["amount"] = $amount;
        $curlParams["currencyCode"] = $currencyCode;

        $curlHeader = [];
        $curlHeader[] = "Accept: application/com.reloadly.topups-v1+json";
        $curlHeader[] = "Authorization: Bearer " . $accessToken;
        $curlHeader[] = "Content-Type: application/json";
        $curlResponse = $post->curl_post($url, $curlParams, 0, 1, $curlHeader);

        return $curlResponse;
    }

    public function addDefaultProductOption()
    {
        $db = $this->db;

        /**
         * if min < 5 , starts with 5, 10, 30 (if max more than 30)
         *
         *
         */
        $db->where("provider_id", 2);
        $product_ids_arr = $db->getValue("xun_pay_product", "id", null);
        // print_r($db);
        print_r($product_ids_arr);
        foreach ($product_ids_arr as $productId) {
            $db->where("product_id", $productId);
            $db->where("amount_type", ["min", "max"], "in");
            $product_option = $db->map("amount_type")->ArrayBuilder()->get("xun_pay_product_option");
            // print_r($product_option);
            
            if (isset($product_option["min"]) && isset($product_option["max"])) {
                echo "\n product id $productId";
                $min = $product_option["min"];
                $max = $product_option["max"];

                $minAmount = $min["amount"];
                $maxAmount = $max["amount"];
                echo "\n min $minAmount max $maxAmount";

                $package_arr = [];

                if($min < 4){
                    $packageMin = 5;
                }else{
                    echo "\n " . floor($minAmount / 10);
                    $packageMin = (floor($minAmount / 10) + 1) * 10;
                    echo "\n package min $packageMin";
                }
                $package_arr[] = $packageMin;
                $packageMax = $packageMin;
                for ($j = 0; $j < 3; $j++){
                    $packageAmount = (floor($packageMax / 10) + 1) * 10;
                    if($packageAmount < $maxAmount){
                        $packageMax = $packageAmount;
                        $package_arr[] = $packageAmount;
                    }
                }

                $updateData = [];
                $updateData["status"] = 0;
                $db->where("product_id", $productId);
                $db->where("amount_type", ["min", "max"], "in");
                $db->update("xun_pay_product_option", $updateData);

                $insertDataArr = [];
                for($k = 0; $k < count($package_arr); $k++){
                    $insertData = array(
                        "product_id" => $productId,
                        "amount_type" => "dropdown",
                        "amount" => $package_arr[$k],
                        "status" => 1
                    );

                    $insertDataArr[] = $insertData;
                }

                $db->insertMulti("xun_pay_product_option", $insertDataArr);
            }
        }
    }
}
