<?php
    /** 
     * ETHUSDT  symbol        
     * https://github.com/binance-exchange/binance-official-api-docs
     **/

    class Binance {
        function __construct($apiKey, $apiSecret, $url, $wapiUrl) {
            //$this->url = "https://testnet.binance.vision/api/v3/";
            $this->wapiUrl = $wapiUrl;
            $this->url = $url;
            $this->apiKey = $apiKey;
            $this->apiSecret = $apiSecret;            
        }

        /**
         * Get curret time
         * 
         *  PARAM       TYPE    Mandatory   DESCRIPTION    
         *  ------      -----   ---------   -----------   
         *           
         */
        public function getServerTime() {
            $cmd = "time";
            $json = $this->httpRequest($cmd, "GET", [], false);
            return $json;
        }

        /**
         * Current exchange trading rules and symbol information
         * 
         *  PARAM       TYPE    Mandatory   DESCRIPTION    
         *  ------      -----   ---------   -----------   
         *           
         */
        public function exchangeInfo(){            
            $cmd = "exchangeInfo";
            $json = $this->httpRequest($cmd, "GET", [], false);
            return $json;
        }


        // Market

        /**
         * Get latest price of one specific symbol or all symbol         
         * Symbol Ticker Price
         * 
         *  PARAM       TYPE    Mandatory   DESCRIPTION    
         *  ------      -----   ---------   -----------  
         *  symbol      String  No
         */
        public function getPrice($symbol="") {
            $cmd = "ticker/price";      // command name from API
            $json = empty($symbol) ? $this->httpRequest($cmd, "GET", []) : $this->httpRequest($cmd, "GET", ["symbol" => $symbol]);            
            return $json;
        }    



        /**
         * Get 24hr ticker price change statistic of one specific symbol
         * Symbol Ticker Price
         * 
         *  PARAM       TYPE    Mandatory   DESCRIPTION    
         *  ------      -----   ---------   -----------  
         *  symbol      String  No
         */
        public function getPrice24hr($symbol="") {
            $cmd = "ticker/24hr";      // command name from API
            $json = empty($symbol) ? $this->httpRequest($cmd, "GET", []) : $this->httpRequest($cmd, "GET", ["symbol" => $symbol]);            
            return $json;
        }

        /**
         * Order book         
         * 
         *  PARAM       TYPE    Mandatory   DESCRIPTION    
         *  ------      -----   ---------   -----------  
         *  symbol      String  Yes
         */
        public function getOrderBook($symbol,$limit="100") {
            $cmd = "depth";
            $opt = [
                "symbol"    => $symbol,                
                "limit"     => $limit,                
            ];

            return $this->httpRequest($cmd, "GET", $opt, false);
        }

        // Trade

        /**
         * Get all account orders; active, cancelled or filled.
         * 
         *  PARAM       TYPE    Mandatory   DESCRIPTION    
         *  ------      -----   ---------   -----------  
         *  symbol      String  Yes
         *  orderID     String  No
         *  startTime   String  No
         *  endTime     String  No
         *  limit       Int     No
         *  recvWindow  Long    No
         *  timestamp   String  Yes
         * 
         */
        public function getAllOrders($symbol,$orderID="",$startTime="",$endTime="",$limit=""){
            $cmd = "allOrders";
            $opt = [
                "symbol"    => $symbol,
                "orderID"   => $orderID,
                "startTime" => $startTime,
                "endTime"   => $endTime,
                "limit"     => $limit,                
            ];

            // Remove array key if value is empty/""/null
            $opt = array_filter($opt, 'strlen');
            
            return $this->httpRequest($cmd, "GET", $opt, true);
        }

        /**
         * Get all account orders; active, cancelled or filled.
         * 
         *  PARAM       TYPE    Mandatory   DESCRIPTION    
         *  ------      -----   ---------   -----------  
         *  symbol      String  Yes
         *  orderID     String  No
         *  startTime   String  No
         *  endTime     String  No
         *  limit       Int     No
         *  recvWindow  Long    No
         *  timestamp   String  Yes
         * 
         */
        public function queryOrder($symbol,$orderID="",$origClientOrderId=""){
            $cmd = "order";
            $opt = [
                "symbol"    => $symbol,
                "orderId"   => $orderID,
                "origClientOrderId" => $origClientOrderId,                
            ];

            // Remove array key if value is empty/""/null
            $opt = array_filter($opt, 'strlen');
            
            return $this->httpRequest($cmd, "GET", $opt, true);
        }

        /**
         * Get current account information.
         * 
         *  PARAM       TYPE    Mandatory   DESCRIPTION    
         *  ------      -----   ---------   -----------   
         *  timestamp   LONG    YES
         *  recvWindow  LONG    NO          The value cannot be greater than 60000
         */
        public function getAccountInfo() {
            $cmd = "account";           // Command name from API
            $json = $this->httpRequest($cmd, "GET", [], true);
            return $json;
        }        
        
        /**
         * Get all account orders; active, cancelled or filled.
         * 
         *  PARAM       TYPE    Mandatory   DESCRIPTION    
         *  ------      -----   ---------   -----------  
         *  symbol      String  Yes         
         * 
         */
        public function getCurrentOpenOrders($symbol) {
            $cmd = "openOrders";
            $opt = [
                "symbol"    => $symbol,                               
            ];
            return $this->httpRequest($cmd, "GET", $opt, true);
        }
        

        /**
         * Cancel all open orders for a symbol
         * 
         *  PARAM       TYPE    Mandatory   DESCRIPTION    
         *  ------      -----   ---------   -----------  
         *  symbol      String  Yes         
         * 
         */
        public function cancelAllOpenOrders($symbol) {
            $cmd = "openOrders";
            $opt = [
                "symbol"    => $symbol,
            ];
            return $this->httpRequest($cmd, "DELETE", $opt, true);
        }

        /**
         * New Order (TRADE), Send in a new order
         * https://github.com/binance-exchange/binance-official-api-docs/blob/master/rest-api.md#account-endpoints
         * 
         *  PARAM       TYPE    Mandatory   DESCRIPTION    
         *  ------      -----   ---------   -----------  
         *  side        Enum    Yes
         *  symbol      String  Yes         
         *  quantity    Decimal No 
         *  price       Decimal No
         *  type        Enum    Yes
         *  flags               No         Additionals such as newOrderRespType, icebertQty etc
         * 
         */
        public function order(
            $side, $symbol, $quantity, $price, $type="LIMIT", $flags=[], $test=false
        ) {
            $cmd = "order";

            $opt = [
                "symbol"    => $symbol,
                "side"      => $side,
                "type"      => $type,
                "quantity"  => $quantity,
                "recvWindow"=> 60000,
            ];

            if (gettype($price) !== "string") {
                // for every other type, lets format it appropriately
                $price = number_format($price, 8, '.', '');
            }
    
            if (is_numeric($quantity) === false) {
                // WPCS: XSS OK.
                echo "warning: quantity expected numeric got " . gettype($quantity) . PHP_EOL;
            }
    
            if (is_string($price) === false) {
                // WPCS: XSS OK.
                echo "warning: price expected string got " . gettype($price) . PHP_EOL;
            }

            if ($type === "LIMIT" || $type === "STOP_LOSS_LIMIT" || $type === "TAKE_PROFIT_LIMIT") {
                $opt["price"] = $price;
                $opt["timeInForce"] = "GTC";
            }
    
            if (isset($flags['stopPrice'])) {
                $opt['stopPrice'] = $flags['stopPrice'];
            }
    
            if (isset($flags['icebergQty'])) {
                $opt['icebergQty'] = $flags['icebergQty'];
            }
    
            if (isset($flags['newOrderRespType'])) {
                $opt['newOrderRespType'] = $flags['newOrderRespType'];
            }

            $qstring = ($test === false) ? "order" : "order/test";
            return $this->httpRequest($qstring, "POST", $opt, true);

        }

        /**
         * withdraw requests a asset be withdrawn from binance to another wallet
         *
         * $asset = "BTC";
         * $address = "1C5gqLRs96Xq4V2ZZAR1347yUCpHie7sa";
         * $amount = 0.2;
         * $response = $binance->withdraw($asset, $address, $amount);
         *
         * $address = "44tLjmXrQNrWJ5NBsEj2R77ZBEgDa3fEe9GLpSf2FRmhexPvfYDUAB7EXX1Hdb3aMQ9FLqdJ56yaAhiXoRsceGJCRS3Jxkn";
         * $addressTag = "0e5e38a01058dbf64e53a4333a5acf98e0d5feb8e523d32e3186c664a9c762c1";
         * $amount = 0.1;
         * $response = $binance->withdraw($asset, $address, $amount, $addressTag);
         * 
         * 
         *
         * @param $asset string the currency such as BTC
         * @param $address string the addressed to whihc the asset should be deposited
         * @param $amount double the amount of the asset to transfer
         * @param $addressTag string adtional transactionid required by some assets
         * @return array with error message or array transaction
         * @throws \Exception
         */

        public function withdraw(string $asset, string $address, $amount, $addressTag = null, $addressName = "", bool $transactionFeeFlag = false,$network = null)
        {
            $options = [
                "asset" => $asset,
                "address" => $address,
                "amount" => $amount,
                "transactionFeeFlag" => $transactionFeeFlag,
                "wapi" => true,
            ];
            if (is_null($addressName) === false && empty($addressName) === false) {
                $options['name'] = str_replace(' ', '%20', $addressName);
            }
            if (is_null($addressTag) === false && empty($addressTag) === false) {
                $options['addressTag'] = $addressTag;
            }
            if (is_null($network) === false && empty($network) === false) {
                $options['network'] = $network;
            }
            return $this->httpRequest("withdraw.html", "POST", $options, true);        
        }

        /**
         * Perform http request
         * params: 
         *  url = string
         *  method = string
         *  params = []
         *  signed = bool
         */
        public function httpRequest($url, string $method = "GET", array $params = [], bool $signed = false) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_VERBOSE, $this->httpDebug);
            $query = http_build_query($params, '', '&');
            

            if ($signed === true) {
                
                if (empty($this->apiKey)) {
                    throw new \Exception("signedRequest error: API Key not set!");
                }
    
                if (empty($this->apiSecret)) {
                    throw new \Exception("signedRequest error: API Secret not set!");
                }

                if (isset($params['wapi'])) {
                    unset($params['wapi']);
                    $this->url = $this->wapiUrl;
                }


                $ts = (microtime(true) * 1000) + $this->info['timeOffset'];
                $params['timestamp'] = number_format($ts, 0, '.', '');
                $query = http_build_query($params, '', '&');
                $signature = hash_hmac('sha256', $query, $this->apiSecret);
                
                if ($method === "POST") {
                    $endpoint = $this->url . $url;
                    $params['signature'] = $signature; // signature needs to be inside BODY
                    $query = http_build_query($params, '', '&'); // rebuilding query
                    //var_dump($query);
                } else {
                    $endpoint = $this->url . $url . '?' . $query . '&signature=' . $signature;                    
                }

                curl_setopt($curl, CURLOPT_URL, $endpoint);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'X-MBX-APIKEY: ' . $this->apiKey,
                ));

            } else if (count($params) > 0) {
                curl_setopt($curl, CURLOPT_URL, $this->url . $url . '?' . $query);
            }
            else {             
                // no params so just use base url
                curl_setopt($curl, CURLOPT_URL, $this->url . $url);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'X-MBX-APIKEY: ' . $this->apiKey,
                ));
            }

            if ($method === "POST") {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
            }

            // Delete Method
            if ($method === "DELETE") {
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            }
            
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);

            // set user defined curl opts last for overriding
            foreach ($this->curlOpts as $key => $value) {
                curl_setopt($curl, constant($key), $value);
            }
                    
            //var_dump($this->url . $url);
            
            $output = curl_exec($curl);             
            
            if (curl_errno($curl) > 0) {
                // should always output error, not only on httpdebug
                // not outputing errors, hides it from users and ends up with tickets on github
                echo 'Curl error: ' . curl_error($curl) . "\n";
                return [];
            }

            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $header = substr($output, 0, $header_size);
            $output = substr($output, $header_size);

            curl_close($curl);

            $json = json_decode($output, true);            

            $this->lastRequest = [
                'url' => $url,
                'method' => $method,
                'params' => $params,
                'header' => $header,
                'json' => $json
            ];

            if(isset($json['msg'])){
                // should always output error, not only on httpdebug
                // not outputing errors, hides it from users and ends up with tickets on github
                
                // comment
                // echo "signedRequest error: {$output}" . PHP_EOL;
            }
            $this->transfered += strlen($output);
            $this->requestCount++;
            return $json;                        
        }
    }

?>