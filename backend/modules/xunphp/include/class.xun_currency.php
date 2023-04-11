<?php

class XunCurrency
{

    public function __construct($db)
    {
        $this->db = $db;

        $db->join("xun_coins b", "a.currency_id=b.currency_id", "LEFT");
        $results = $db->get('xun_marketplace_currencies a', null, "a.name, a.type, LOWER(a.currency_id) as currency_id, a.fiat_currency_id, a.fiat_currency_reference_price, a.image, a.image_md5, a.symbol,a.display_symbol, a.is_show_new_coin, a.unit_conversion, a.bg_image_url, a.bg_image_md5, a.font_color, b.type as coin_type");

        foreach ($results as $row) {
            $this->marketplaceCurrencies[$row['currency_id']] = $row;

            if (!empty($row["fiat_currency_id"])) {
                $this->stableCoinArr[] = $row["currency_id"];
            }

        }
    }

    public function get_cryptocurrency_rate($cryptocurrencyIDArr)
    {
        $db = $this->db;

        $newCryptocurrencyArr = [];
        $fetchedCryptocurrencyArr = [];

        if (isset($this->cryptocurrencyArr)) {
            $cryptocurrencyArr = $this->cryptocurrencyArr;

            foreach ($cryptocurrencyIDArr as $cryptocurrencyID) {
                if (isset($this->cryptocurrencyArr[$cryptocurrencyID])) {
                    $fetchedCryptocurrencyArr[$cryptocurrencyID] = $this->cryptocurrencyArr[$cryptocurrencyID];
                } else {
                    $newCryptocurrencyArr[] = $cryptocurrencyID;
                }
            }
        } else {
            $cryptocurrencyArr = [];
            $newCryptocurrencyArr = $cryptocurrencyIDArr;
        }

        if (sizeof($newCryptocurrencyArr) > 0) {
            $has_bitcoincash = false;
            // if (in_array("bitcoincash", $newCryptocurrencyArr)) {
            //     $newCryptocurrencyArr[] = "bitcoin-cash";
            //     $has_bitcoincash = true;
            // }
            $db->where("cryptocurrency_id", $newCryptocurrencyArr, "in");
            $cryptocurrencyRateArr = $db->map('cryptocurrency_id')->ObjectBuilder()->get("xun_cryptocurrency_rate", null, 'LOWER(cryptocurrency_id) as cryptocurrency_id, value');

            if ($has_bitcoincash) {
                $cryptocurrencyRateArr["bitcoincash"] = $cryptocurrencyRateArr["bitcoin-cash"];
                unset($cryptocurrencyRateArr["bitcoin-cash"]);
            }
            $this->cryptocurrencyArr = array_merge($cryptocurrencyArr, $cryptocurrencyRateArr);

            $fetchedCryptocurrencyArr = array_merge($fetchedCryptocurrencyArr, $cryptocurrencyRateArr);
        }

        return $fetchedCryptocurrencyArr;
    }

    public function get_cryptocurrency_rate_with_stable_coin($cryptocurrencyIDArr)
    {
        $db = $this->db;

        $newCryptocurrencyArr = [];
        $fetchedCryptocurrencyArr = [];

        $newStableCoinArr = [];

        if (isset($this->cryptocurrencyArr)) {
            $cryptocurrencyArr = $this->cryptocurrencyArr;

            foreach ($cryptocurrencyIDArr as $cryptocurrencyData) {
                // array("fiat_currency_id" => "usd", "currency_id" => "usd2")
                $cryptocurrencyID = $cryptocurrencyData["currency_id"];
                if (isset($this->cryptocurrencyArr[$cryptocurrencyID])) {
                    $fetchedCryptocurrencyArr[$cryptocurrencyID] = $this->cryptocurrencyArr[$cryptocurrencyID];
                } else {
                    if (!empty($cryptocurrencyData["fiat_currency_id"])) {
                        $newStableCoinArr[] = $cryptocurrencyData;
                    } else {
                        $newCryptocurrencyArr[] = $cryptocurrencyID;
                    }
                }
            }
        } else {
            $cryptocurrencyArr = [];

            foreach ($cryptocurrencyIDArr as $cryptocurrencyData) {
                // array("fiat_currency_id" => "usd", "currency_id" => "usd2")
                $cryptocurrencyID = $cryptocurrencyData["currency_id"];

                if (!empty($cryptocurrencyData["fiat_currency_id"])) {
                    $newStableCoinArr[] = $cryptocurrencyData;
                } else {
                    $newCryptocurrencyArr[] = $cryptocurrencyID;
                }
            }
        }

        if (sizeof($newCryptocurrencyArr) > 0) {
            $has_bitcoincash = false;
            // if (in_array("bitcoincash", $newCryptocurrencyArr)) {
            //     $newCryptocurrencyArr[] = "bitcoin-cash";
            //     $has_bitcoincash = true;
            // }
            $db->where("cryptocurrency_id", $newCryptocurrencyArr, "in");
            $cryptocurrencyRateArr = $db->map('cryptocurrency_id')->ObjectBuilder()->get("xun_cryptocurrency_rate", null, 'LOWER(cryptocurrency_id) as cryptocurrency_id, value');

            if ($has_bitcoincash) {
                $cryptocurrencyRateArr["bitcoincash"] = $cryptocurrencyRateArr["bitcoin-cash"];
                unset($cryptocurrencyRateArr["bitcoin-cash"]);
            }

            $this->cryptocurrencyArr = array_merge($cryptocurrencyArr, $cryptocurrencyRateArr);

            $fetchedCryptocurrencyArr = array_merge($fetchedCryptocurrencyArr, $cryptocurrencyRateArr);
        }

        if (sizeof($newStableCoinArr) > 0) {
            // get currency value

            $stableCoinArr = $this->get_stable_coin_rate($newStableCoinArr);
            $fetchedCryptocurrencyArr = array_merge($fetchedCryptocurrencyArr, $stableCoinArr);
        }
        return $fetchedCryptocurrencyArr;
    }

    public function get_stable_coin_rate($stableCoinArr)
    {
        global $setting;
        $db = $this->db;

        $fetchedCurrencyArr = [];
        $newCurrencyArr = [];
        $newStableCoinArr = [];
        foreach ($stableCoinArr as $data) {
            $stableCoinCurrency = $data["fiat_currency_id"];
            $stableCoinID = $data["currency_id"];

            if (isset($this->currencyArr[$stableCoinCurrency])) {
                $stableCoinValue = $this->get_stable_coin_value($this->currencyArr[$stableCoinCurrency]);
                $fetchedCurrencyArr[$stableCoinID] = $stableCoinValue;
                $this->cryptocurrencyArr[$stableCoinID] = $stableCoinValue;
            } else {
                $newCurrencyArr[] = $stableCoinCurrency;
                $newStableCoinArr[] = $data;
            }
        }

        if (sizeof($newCurrencyArr) > 0) {
            $db->where("currency", $newCurrencyArr, "in");
            $currencyRateArr = $db->map('currency')->ObjectBuilder()->get("xun_currency_rate", null, 'currency, exchange_rate');

            $currencyArr = $this->currencyArr;
            $cryptocurrencyArr = $this->cryptocurrencyArr;

            $newStableCoinArrLen = count($newStableCoinArr);
            for ($i = 0; $i < $newStableCoinArrLen; $i++) {
                $stableCoinData = $newStableCoinArr[$i];
                $stableCoinID = $stableCoinData["currency_id"];
                $stableCoinValue = $this->get_stable_coin_value($currencyRateArr[$stableCoinData["fiat_currency_id"]]);
                $cryptocurrencyArr[$stableCoinID] = $stableCoinValue;
                $fetchedCurrencyArr[$stableCoinID] = $stableCoinValue;
            }

            $this->currencyArr = array_merge($currencyArr, $currencyRateArr);

            $this->cryptocurrencyArr = $cryptocurrencyArr;

            $fetchedCurrencyArr = array_merge($fetchedCurrencyArr, $currencyRateArr);
        }

        return $fetchedCurrencyArr;
    }

    public function get_all_currency_rate($currencyIDArr = [])
    {
        global $setting;
        $db = $this->db;

        $currencyIDArr = $this->marketplaceCurrencies;

        $newCryptocurrencyArr = [];
        $newCurrencyArr = [];
        $newStableCoinArr = [];

        $fetchedCurrencyArr = [];

        if (isset($this->fullCurrencyArr)) {
            $fullCurrencyArr = $this->fullCurrencyArr;

            foreach ($currencyIDArr as $currencyData) {
                // array("fiat_currency_id" => "usd", "currency_id" => "usd2", "type" => "cryptocurrency)
                // array("fiat_currency_id" => "", "currency_id" => "usd")
                // array("fiat_currency_id" => "", "currency_id" => "bitcoin")
                $currencyData = (array) $currencyData;
                $currencyID = $currencyData["currency_id"];
                if (isset($this->fullCurrencyArr[$currencyID])) {
                    $fetchedCurrencyArr[$currencyID] = $this->fullCurrencyArr[$currencyID];
                } else {
                    $currencyType = $currencyData["type"];
                    if ($currencyType == "currency") {
                        $newCurrencyArr[] = $currencyID;
                    } elseif (!empty($currencyData["fiat_currency_id"])) {
                        $fiat_currency_id = $currencyArr["fiat_currency_id"];
                        if (isset($this->fullCurrencyArr[$fiat_currency_id])) {
                            $fetchedCurrencyArr[$currencyID] = $this->fullCurrencyArr[$fiat_currency_id];
                        } else {
                            $newStableCoinArr[] = $currencyData;
                        }
                    } else {
                        $newCryptocurrencyArr[] = $currencyID;
                    }
                }
            }
        } else {
            $fullCurrencyArr = array();
            foreach ($currencyIDArr as $currencyData) {
                // array("fiat_currency_id" => "usd", "currency_id" => "usd2")
                $currencyData = (array) $currencyData;
                $currencyID = $currencyData["currency_id"];

                $currencyType = $currencyData["type"];
                if ($currencyType == "currency") {
                    $newCurrencyArr[] = $currencyID;
                } elseif (!empty($currencyData["fiat_currency_id"])) {
                    $newStableCoinArr[] = $currencyData;
                } else {
                    $newCryptocurrencyArr[] = $currencyID;
                }
            }
        }

        // get cryptocurrency rate
        if (count($newCryptocurrencyArr) > 0) {
            $cryptocurrencyRateArr = $this->get_cryptocurrency_usd_rate($newCryptocurrencyArr);

            $fullCurrencyArr = array_merge($fullCurrencyArr, $cryptocurrencyRateArr);

            $fetchedCurrencyArr = array_merge($fetchedCurrencyArr, $cryptocurrencyRateArr);
        }

        // get currency rate
        if (count($newCurrencyArr) > 0) {
            $currencyRateArr = $this->get_currency_usd_rate($newCurrencyArr);
            $fullCurrencyArr = array_merge($fullCurrencyArr, $currencyRateArr);

            $fetchedCurrencyArr = array_merge($fetchedCurrencyArr, $currencyRateArr);
        }

        $this->fullCurrencyArr = $fullCurrencyArr;
        if (sizeof($newStableCoinArr) > 0) {
            // get currency value

            $stableCoinArr = $this->get_stable_coin_rate_all($newStableCoinArr);
            $fetchedCurrencyArr = array_merge($fetchedCurrencyArr, $stableCoinArr);
        }

        // print_r($this->fullCurrencyArr)
        return $fetchedCurrencyArr;
    }

    private function get_cryptocurrency_usd_rate($cryptocurrencyArr)
    {
        $db = $this->db;

        $has_bitcoincash = false;
        // if (in_array("bitcoincash", $cryptocurrencyArr)) {
        //     $cryptocurrencyArr[] = "bitcoin-cash";
        //     $has_bitcoincash = true;
        // }
        $db->where("cryptocurrency_id", $cryptocurrencyArr, "in");
        $cryptocurrencyRateArr = $db->map('cryptocurrency_id')->ObjectBuilder()->get("xun_cryptocurrency_rate", null, 'LOWER(cryptocurrency_id) as cryptocurrency_id, value');

        if ($has_bitcoincash) {
            $cryptocurrencyRateArr["bitcoincash"] = $cryptocurrencyRateArr["bitcoin-cash"];
            unset($cryptocurrencyRateArr["bitcoin-cash"]);
        }

        return $cryptocurrencyRateArr;
    }

    private function get_currency_usd_rate($currencyArr)
    {
        $db = $this->db;
        global $setting;

        $db->where("currency", $currencyArr, "in");
        $currencyRateArr = $db->map('currency')->ObjectBuilder()->get("xun_currency_rate", null, 'currency, exchange_rate');

        $decimalPlaces = (int) $setting->systemSetting["cryptocurrencyDecimalPlaces"];

        foreach ($currencyRateArr as $key => $value) {
            // USD/MYR = 4.1385067
            $usd_quote_value = bcdiv("1", (string) $value, $decimalPlaces);
            $currencyRateArr[$key] = $usd_quote_value;
        }
        return $currencyRateArr;
    }

    public function get_stable_coin_rate_all($stableCoinArr)
    {
        global $setting;
        $db = $this->db;

        $fetchedCurrencyArr = [];
        $newCurrencyArr = [];
        $newStableCoinArr = [];
        $fullCurrencyArr = isset($this->fullCurrencyArr) ? $this->fullCurrencyArr : array();
        foreach ($stableCoinArr as $data) {
            $stableCoinCurrency = strtolower($data["fiat_currency_id"]);
            $stableCoinID = $data["currency_id"];
            if (isset($fullCurrencyArr[$stableCoinCurrency])) {
                $fiatRefencePrice = $data["fiat_currency_reference_price"];
                $stableCoinValue = $this->get_stable_coin_value($fullCurrencyArr[$stableCoinCurrency]);
                $referencePrice = bcmul($stableCoinValue, $fiatRefencePrice, 8);

                $fetchedCurrencyArr[$stableCoinID] = $referencePrice;
                $fullCurrencyArr[$stableCoinID] = $referencePrice;
            } else {
                $newCurrencyArr[] = $stableCoinCurrency;
                $newStableCoinArr[] = $data;
            }
        }

        if (sizeof($newCurrencyArr) > 0) {
            $db->where("currency", $newCurrencyArr, "in");
            $currencyRateArr = $db->map('currency')->ObjectBuilder()->get("xun_currency_rate", null, 'currency, exchange_rate');

            foreach ($currencyRateArr as $key => $value) {
                $value = $setting->setDecimal($currencyRateArr[$key], "marketplacePrice");
                $currencyRateArr[$key] = $value;
            }

            $newStableCoinArrLen = count($newStableCoinArr);
            for ($i = 0; $i < $newStableCoinArrLen; $i++) {
                $stableCoinData = $newStableCoinArr[$i];
                $stableCoinID = $stableCoinData["currency_id"];
                $fiatRefencePrice = $stableCoinData["fiat_currency_reference_price"];
                $stableCoinValue = $this->get_stable_coin_value($currencyRateArr[$stableCoinData["fiat_currency_id"]]);

                $referencePrice = bcmul($stableCoinValue, bcdiv("1",$fiatRefencePrice, 2), 8);
                
                $fullCurrencyArr[$stableCoinID] = $referencePrice;
                $fetchedCurrencyArr[$stableCoinID] = $referencePrice;
            }

            $fullCurrencyArr = array_merge($fullCurrencyArr, $currencyRateArr);
            $fetchedCurrencyArr = array_merge($fetchedCurrencyArr, $currencyRateArr);
        }

        $this->fullCurrencyArr = $fullCurrencyArr;
        return $fetchedCurrencyArr;
    }

    public function get_stable_coin_value($fiatCurrencyRate)
    {
        // return bcdiv("1", (string) $fiatCurrencyRate, 2);
        return $fiatCurrencyRate;
    }

    public function get_currency_rate($currencyIDArr)
    {
        global $setting;
        $db = $this->db;

        $newCurrencyArr = [];
        $fetchedCurrencyArr = [];

        if (isset($this->currencyArr)) {
            $currencyArr = $this->currencyArr;

            foreach ($currencyIDArr as $currencyID) {
                if (isset($this->currencyArr[$currencyID])) {
                    $fetchedCurrencyArr[$currencyID] = $this->currencyArr[$currencyID];
                } else {
                    $newCurrencyArr[] = $currencyID;
                }
            }
        } else {
            $currencyArr = [];
            $newCurrencyArr = $currencyIDArr;
        }

        if (sizeof($newCurrencyArr) > 0) {
            $db->where("currency", $newCurrencyArr, "in");
            $currencyRateArr = $db->map('currency')->ObjectBuilder()->get("xun_currency_rate", null, 'currency, exchange_rate');

            foreach ($currencyRateArr as $key => $value) {
                $truncValue = $setting->setDecimal($value, "marketplacePrice");
                $currencyRateArr[$key] = $truncValue;
            }
            $this->currencyArr = array_merge($currencyArr, $currencyRateArr);

            $fetchedCurrencyArr = array_merge($fetchedCurrencyArr, $currencyRateArr);
        }

        return $fetchedCurrencyArr;
    }

    public function get_marketplace_currency_details($currencyArr)
    {
        $db = $this->db;

        $db->where("currency_id", $currencyArr, "in");

        $result = $db->map('currency_id')->ObjectBuilder()->get("xun_marketplace_currencies", null, "name, type, symbol, currency_id, image");

        return $result;
    }

    public function get_rate($baseCurrency, $quoteCurrency)
    {
        $db = $this->db;

        if (!isset($this->fullCurrencyArr)) {
            $this->get_all_currency_rate();
        }

        $fullCurrencyArr = $this->fullCurrencyArr;
        $marketplaceCurrencies = $this->marketplaceCurrencies;

        $baseCurrencyInfo = (array) $marketplaceCurrencies[$baseCurrency];
        $quoteCurrencyInfo = (array) $marketplaceCurrencies[$quoteCurrency];

        if(!$baseCurrencyInfo){
            $newCurrencyArr = $this->get_currency_usd_rate([$baseCurrency]);
            $fullCurrencyArr = array_merge($fullCurrencyArr, $newCurrencyArr);
            $this->fullCurrencyArr = $fullCurrencyArr;
        }
        if(!$quoteCurrencyInfo){
            $newCurrencyArr = $this->get_currency_usd_rate([$quoteCurrency]);
            $fullCurrencyArr = array_merge($fullCurrencyArr, $newCurrencyArr);
            $this->fullCurrencyArr = $fullCurrencyArr;
        }

        $baseCurrencyValue = $fullCurrencyArr[$baseCurrency];
        $quoteCurrencyValue = $fullCurrencyArr[$quoteCurrency];
        $currencyRate = $this->calculate_currency_rate($baseCurrencyInfo, $quoteCurrencyInfo, $baseCurrencyValue, $quoteCurrencyValue);

        return $currencyRate;
    }

    public function get_conversion_amount($baseCurrency, $quoteCurrency, $amount, $ceil = false, $rate = null)
    {
        if($rate == null){
            $rate = $this->get_rate($baseCurrency, $quoteCurrency);
        }
        
        $baseDecimalPlaces = $this->get_currency_decimal_places($baseCurrency);
        $currencyInfo = $this->get_currency_info($baseCurrency);
        
        if($ceil == true){
            $currencyType = $currencyInfo["type"];
            if($currencyType == "currency"){
                $unit_conversion = 100;
            }else{
                $unit_conversion = $currencyInfo["unit_conversion"];
            }

            $convertedAmountRaw = bcdiv((string) $amount, (string) $rate, 20);
            $p = ceil(bcmul((string)$convertedAmountRaw, (string)$unit_conversion, 8));
            $convertedAmount = bcdiv((string)$p, (string)$unit_conversion, 8);
        }else{
            $convertedAmount = bcdiv((string) $amount, (string) $rate, (int) $baseDecimalPlaces);
        }

        return $convertedAmount ? $convertedAmount : "0";
    }

    public function is_fiat_currency_equivalent($baseCurrencyInfo, $quoteCurrencyInfo)
    {
        $quoteCurrency = $quoteCurrencyInfo["currency_id"];

        return $baseCurrencyInfo["fiat_currency_id"] == $quoteCurrency ? true : false;
    }

    public function calculate_currency_rate($baseCurrencyInfo, $quoteCurrencyInfo, $baseCurrencyValue, $quoteCurrencyValue)
    {
        // $decimalPlaces = $this->get_rate_decimal_places($baseCurrencyInfo, $quoteCurrencyInfo);
        $decimalPlaces = 8;

        $currencyRate = bcdiv((string) $baseCurrencyValue, (string) $quoteCurrencyValue, (int) $decimalPlaces);

        return $currencyRate;
    }

    // public function get_currency_decimal_places($currency){
    //     $marketplaceCurrencies = $this->marketplaceCurrencies;
    //     $quoteCurrencyInfo = $marketplaceCurrencies["currency"];
    //     print_r($quoteCurrencyInfo);
    //     return $this->get_rate_decimal_places(null, $quoteCurrencyInfo);
    // }

    public function get_rate_decimal_places($baseCurrencyInfo, $quoteCurrencyInfo)
    {
        global $setting;
        $quoteCurrencyType = $quoteCurrencyInfo["type"];

        $quoteCurrencyID = $quoteCurrencyInfo["currency_id"];

        $decimalPlaces = isset($this->decimalPlaces) ? $this->decimalPlaces : [];
        if ($decimalPlaces[$quoteCurrencyID]) {
            return $decimalPlaces[$quoteCurrencyID];
        }

        $fiatCurrencyDecimalPlaces = $setting->systemSetting["fiatCurrencyDecimalPlaces"];
        $cryptocurrencyDecimalPlaces = $setting->systemSetting["cryptocurrencyDecimalPlaces"];

        if ($quoteCurrencyType == "currency") {
            $cryptocurrencyDecimalPlaces = $fiatCurrencyDecimalPlaces;
        } else {
            $isQuoteCurrencyCoins2 = !empty($quoteCurrencyInfo["fiat_currency_id"]) ? true : false;

            if ($isQuoteCurrencyCoins2) {
                $cryptocurrencyDecimalPlaces = $fiatCurrencyDecimalPlaces;
            }
        }

        $decimalPlaces[$quoteCurrencyID] = $cryptocurrencyDecimalPlaces;
        $this->decimalPlaces = $decimalPlaces;

        return $cryptocurrencyDecimalPlaces;
    }

    public function isStableCoin($currency)
    {
        $marketplaceCurrencies = $this->marketplaceCurrencies;

        $currencyDetails = $marketplaceCurrencies[$currency];

        return $currencyDetails["fiat_currency_id"] != '' ? true : false;
    }

    public function get_decimal_places()
    {
        global $setting;
        $fiatCurrencyCreditType = "fiatCurrency";
        $cryptocurrencyCreditType = "cryptocurrency";

        $fiatDecimalPlaces = (int) $setting->systemSetting[$fiatCurrencyCreditType . "DecimalPlaces"];
        $cryptocurrencyDecimalPlaces = (int) $setting->systemSetting[$cryptocurrencyCreditType . "DecimalPlaces"];

        return array(
            "fiat_decimal_places" => $fiatDecimalPlaces,
            "fiat_credit_type" => $fiatCurrencyCreditType,
            "cryptocurrency_decimal_places" => $cryptocurrencyDecimalPlaces,
            "cryptocurrency_credit_type" => $cryptocurrencyCreditType,
        );
    }

    public function get_currency_decimal_places($currency, $returnCreditType = false)
    {
        global $setting;

        $currency = strtolower($currency);
        $marketplaceCurrencies = $this->marketplaceCurrencies;
        $fiatCurrencyCreditType = "fiatCurrency";
        $cryptocurrencyCreditType = "cryptocurrency";
        $tetherUsdCreditType = "tetherUsd";

        $currencyDetails = $marketplaceCurrencies[$currency];
        $unitConversion = $currencyDetails["unit_conversion"];

        $decimalPlaces = log($unitConversion, 10);

        if ($currencyDetails["type"] == "currency") {
            $creditType = $fiatCurrencyCreditType;
            $decimalPlaces = 2;
        } else if($decimalPlaces == 2){
            $creditType = $fiatCurrencyCreditType;
        } else if($decimalPlaces == 8){
            $creditType = $cryptocurrencyCreditType;
        } else if($decimalPlaces == 6){
            $creditType = $tetherUsdCreditType;
        } else if($decimalPlaces == 18){
            $decimalPlaces = 8;
            $creditType = $cryptocurrencyCreditType;
        }

        if ($returnCreditType === true) {
            return array("decimal_places" => $decimalPlaces, "credit_type" => $creditType);
        }
        return $decimalPlaces;
    }

    public function get_live_price_by_currency_list($currencyArr)
    {
        global $setting;
        $db = $this->db;
        $stableCoinArr = $this->stableCoinArr;

        if (empty($currencyArr)) {
            return [];
        }

        $cryptoCoinsArr = $currencyArr;

        $cryptocurrencyRateArr = [];

        $price_change_duration = "24 hours";
        $marketplaceCurrencies = $this->marketplaceCurrencies;

        if (count($cryptoCoinsArr) > 0) {
            $db->where("cryptocurrency_id", $cryptoCoinsArr, "in");
            $cryptocurrencyRateDataArr = $db->map("cryptocurrency_id")->ObjectBuilder()->get("xun_cryptocurrency_rate", null, "LOWER(cryptocurrency_id) as cryptocurrency_id, value, price_change_percentage_24h");

            $cryptocurrencyRateArrLen = count($cryptocurrencyRateDataArr);
            foreach ($cryptocurrencyRateDataArr as $key => $data) {
                $data = (array) $data;
                $cryptocurrency_id = $data["cryptocurrency_id"];

                $value = $data["value"];
                $value = $setting->setDecimal($value, "cryptocurrency");
                $price_change = $data["price_change_percentage_24h"];
                $price_change = $setting->setDecimal($price_change, "fiatCurrency");

                $marketplaceCurrenciesData = $marketplaceCurrencies[$cryptocurrency_id];
                $fiat_currency_id = $marketplaceCurrencies[$marketplaceCurrenciesData["fiat_currency_id"]]['symbol'];
                $uc_fiat_currency_id = $fiat_currency_id ? strtoupper($fiat_currency_id) : '';
                $name = $marketplaceCurrenciesData["name"];
                $image_url = $marketplaceCurrenciesData["image"];
                $image_md5 = $marketplaceCurrenciesData["image_md5"];
				$is_show_new_coin = $marketplaceCurrenciesData["is_show_new_coin"];
				$bg_image_url = $marketplaceCurrenciesData["bg_image_url"];
                $bg_image_md5 = $marketplaceCurrenciesData["bg_image_md5"];
                $font_color = $marketplaceCurrenciesData["font_color"];
                $coin_type = $marketplaceCurrenciesData["coin_type"];
                
                $bg_image_url = $bg_image_url ? $bg_image_url : "https://s3-ap-southeast-1.amazonaws.com/com.thenux.image/assets/wallet/cards/coins2_bg.png";
                $bg_image_md5 = $bg_image_md5 ? $bg_image_md5 : "c72b67edf4f4586eb0b62dbdc7fa3ce5";

                $data = array(
                    "value" => $value,
                    "fiat_currency_id" => $uc_fiat_currency_id,
                    "price_change_percentage" => $price_change,
                    "price_change_duration" => $price_change_duration,
                    "image" => $image_url,
                    "image_md5" => $image_md5,
                    "is_show_new_coin" => $is_show_new_coin,
                    "bg_image_url" => $bg_image_url,
                    "bg_image_md5" => $bg_image_md5,
                    "font_color" => $font_color,
                    "coin_type" => $coin_type,
                    "name" => $name,
                    "wallet_type" => $cryptocurrency_id
                );

                $cryptocurrencyRateArr[$cryptocurrency_id] = $data;
            }

            unset($cryptocurrencyRateArr["bitcoin-cash"]);
        }

        $coins2ArrLen = count($coins2Arr);
        $coins2RateArr = [];
        if ($coins2ArrLen > 0) {
            $coins2InfoArr = [];
            $coins2Arr = array_values($coins2Arr);

            for ($i = 0; $i < $coins2ArrLen; $i++) {
                $coins2ID = $coins2Arr[$i];
                $coins2InfoArr[$coins2ID] = $marketplaceCurrencies[$coins2ID];
            }

            $baseUSDRate = $this->get_stable_coin_rate_all($coins2InfoArr);
            $coins2_price_change_arr = $this->get_stable_coin_price_change($coins2InfoArr, $baseUSDRate);

            for ($i = 0; $i < $coins2ArrLen; $i++) {
                $coins2ID = $coins2Arr[$i];
                $baseCoinRate = bcdiv("1", (string) $baseUSDRate[$coins2ID], 8);
                $price_change = $coins2_price_change_arr[$coins2ID];

                $marketplaceCurrenciesData = $marketplaceCurrencies[$coins2ID];
                $image_url = $marketplaceCurrenciesData["image"];
                $image_md5 = $marketplaceCurrenciesData["image_md5"];
				$is_show_new_coin = $marketplaceCurrenciesData["is_show_new_coin"];

                $data = array(
                    "value" => $baseCoinRate,
                    "price_change_percentage" => $price_change,
                    "price_change_duration" => $price_change_duration,
                    "image" => $image_url,
                    "image_md5" => $image_md5,
					"is_show_new_coin" => $is_show_new_coin,
                );
                $coins2RateArr[$coins2ID] = $data;
            }
        }

        $finalRateArr = array_merge($cryptocurrencyRateArr, $coins2RateArr);
        return $finalRateArr;
    }

    public function get_live_price_by_currency_list_v1($currencyArr)
    {
        
        global $setting;
        $db = $this->db;
        $stableCoinArr = $this->stableCoinArr;

        $finalRateArr = $this->get_live_price_by_currency_list($currencyArr);
        $exchangeRateArr = $this->calculate_crypto_fiat_rate($finalRateArr);
        $currency_list["crypto_usd_rate"] = $finalRateArr;
        $currency_list["crypto_exchange_rate"] = $exchangeRateArr;
        return $currency_list;
    }

    public function calculate_crypto_fiat_rate($finalRateArr){
        $db = $this->db;

        $db->join('xun_fiat f', "f.fiat_currency_id = c.currency", "INNER");
        $db->where('f.is_wallet', 1);
        $fiat_currency = $db->get('xun_currency_rate c');

        $exchangeRateArr = [];
        foreach($finalRateArr as $key => $data){
            $cryptocurrency_id = $key; 
            $crypto_value = $data["value"];

            foreach($fiat_currency as $fiat_data){
                $fiat_currency_value = $fiat_data["exchange_rate"];
                $fiat_currency_id = $fiat_data["currency"];
                
                $conversion_rate = bcdiv((string)$crypto_value, (string)(bcdiv("1", (string)$fiat_currency_value, 8)), 8);
                // $conversion_rate = bcmul((string)$crypto_value, (string)$fiat_currency_value, 8);   
                $fiat_arr[$fiat_currency_id] = $conversion_rate;
            }
            $exchangeRateArr[$cryptocurrency_id] = $fiat_arr;
          
        }
        return $exchangeRateArr;
    }


    public function get_stable_coin_price_change($coins2InfoArr, $baseUSDRate)
    {
        $fiat_currency_arr = array_unique(array_column($coins2InfoArr, "fiat_currency_id"));

        $fiat_price_change_arr = $this->get_fiat_currency_price($fiat_currency_arr);

        $price_change_arr = [];
        foreach ($coins2InfoArr as $key => $data) {
            $fiat_currency_id = $data["fiat_currency_id"];
            $passed_value = $fiat_price_change_arr[$fiat_currency_id];

            $current_value = $baseUSDRate[$fiat_currency_id];

            $price_change_percentage = bcdiv((string) ($current_value - $passed_value), (string) $current_value, 2);
            $price_change_arr[$key] = $price_change_percentage;
        }

        return $price_change_arr;
    }

    public function get_fiat_currency_price($currencyArr)
    {
        return $this->get_fiat_currency_price_24h($currencyArr);
    }

    public function get_fiat_currency_price_24h($currencyArr)
    {
        global $setting;
        $db = $this->db;

        $date = date("Y-m-d H:i:s");
        $price_change_duration = '1 day';

        $price_change_date = date("Y-m-d H:i:s", strtotime("-$price_change_duration", strtotime($date)));

        $price_change_date_start = date("Y-m-d H:i:s", strtotime("-10 minutes", strtotime($price_change_date)));

        $tblDate = date("Ymd", strtotime($price_change_date));
        $table_name = "xun_currency_" . $db->escape($tblDate);

        if ($db->tableExists($table_name)) {
            $db->where("currency", $currencyArr, "in");
            $db->where("created_at", $price_change_date_start, ">=");
            $db->where("created_at", $price_change_date, "<=");
            $db->orderBy("batch_id", "DESC");
            $result = $db->get($table_name, null, "currency, exchange_rate, created_at, batch_id");

            $resultLen = count($result);

            $currency_price_change_arr = [];

            for ($i = 0; $i < $resultLen; $i++) {
                $data = $result[$i];
                $currency_id = $data["currency"];

                if (isset($currency_price_change_arr[$currency_id])) {
                    continue;
                }

                $value = $data["exchange_rate"];
                $value = $setting->setDecimal($value, "fiatCurrency");

                $currency_price_change_arr[$currency_id] = $value;
            }

            return $currency_price_change_arr;
        }
    }

    public function get_marketplace_currencies()
    {
        $marketplaceCurrencies = $this->marketplaceCurrencies;

        return $marketplaceCurrencies;
    }

    public function get_currency_info($currencyID)
    {
        $marketplaceCurrencies = $this->marketplaceCurrencies;

        $currencyInfo = $marketplaceCurrencies[$currencyID];
        return $currencyInfo;
    }

    public function is_supported_currency($currencyID)
    {
        $currencyInfo = $this->get_currency_info($currencyID);

        return $currencyInfo ? true : false;
    }

    public function get_supported_fiat_currency_rate($columns = null)
    {
        $db = $this->db;
        // SELECT * FROM `xun_currency_rate` a join country b on a.currency = b.currency_code
        $db->join("country b", "a.currency=b.currency_code");
        $db->orderBy("b.name", "ASC");
        $data = $db->get("xun_currency_rate a", null, $columns);

        return $data;
    }

    public function get_latest_fiat_price($fiat_arr)
    {
        $db= $this->db;

        $db->where('currency', $fiat_arr, 'IN');
        $currency_rate = $db->map('currency')->ArrayBuilder()->get('xun_currency_rate');

        return $currency_rate;
    }

    public function verify_fiat_currency($fiat_currency_id){
        $db = $this->db;

        $db->where('currency', $fiat_currency_id);
        $currency_result = $db->getOne('xun_currency_rate');

        return $currency_result;
    }

    public function get_cryptocurrency_list($col = null){
        $db= $this->db;

        $db->where('b.is_payment_gateway', 1);
        $db->where('a.type', 'cryptocurrency');
        $db->join('xun_coins b', 'b.currency_id = a.currency_id', 'LEFT');
        $cryptocurrency_list = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies a', null, $col);
        return $cryptocurrency_list;
    }

    public function calculate_cryptocurrency_rate_by_wallet_type($wallet_type, $crypto_amount){
        $db= $this->db;

        $db->where('cryptocurrency_id', $wallet_type);
        $cryptocurrency_rate = $db->getOne('xun_cryptocurrency_rate');

        $crypto_usd_value = $cryptocurrency_rate['value'];
        
        $converted_usd_amount = bcmul($crypto_amount, $crypto_usd_value, 8);

        return $converted_usd_amount;


    }

    public function round_miner_fee($wallet_type, $amount){

        $amount = (string) $amount;
        $ceil_amount = ceil(bcmul((string)$amount, "100000000", 8));
        $convertedAmount = bcdiv((string)$ceil_amount, "100000000", 8);

        return $convertedAmount;
        
    }

    public function get_all_cryptocurrency_list($col = null){
        $db= $this->db;

        $db->where('type', 'cryptocurrency');
        $cryptocurrency_list = $db->map('currency_id')->ArrayBuilder()->get('xun_marketplace_currencies', null, $col);
        return $cryptocurrency_list;
    }

    public function get_usd_to_crypto_rate($wallet_type, $usd_amount){
        $db= $this->db;

        $db->where('cryptocurrency_id', $wallet_type);
        $crypto_rate = $db->getOne('xun_cryptocurrency_rate', 'cryptocurrency_id, value');

        $decimal_place_setting = $this->get_currency_decimal_places($wallet_type, true);
        $decimal_places = $decimal_place_setting['decimal_places'];

        $crypto_amount = bcdiv($usd_amount, $crypto_rate['value'], $decimal_places);

        return $crypto_amount;

    }

    public function get_crypto_conversion_rate($wallet_type){
        $db = $this->db;

        $db->where('currency_id', $wallet_type);
        $conversionRate = $db->getValue('xun_marketplace_currencies', 'unit_conversion');

        return $conversionRate;
    }
}
