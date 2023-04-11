<?php

    /**        
     * API Provided by Poloniex, I only convert the code from shell to php.
     * API Documentation: 
     * https://docs.poloniex.com/#sign-up
     * If you have any problem, Please refer to Poloniex API documentation.
     **/
class Poloniex {

    function __construct($apiKey, $apiSecret, $url) {
        $this->url = $url; // https://poloniex.com
        $this->apiKey = $apiKey; //ULEV0HAF-26ZM52Y5-ELF1DD9D-O5DJF5M8
        $this->apiSecret = $apiSecret; //3ecee0b251e175e5c178628b2c40b4a3c382d746961e147edf1420aa9c0b86c112a8e3e9cd1cea5f89700f6316f50c1f47dbe26543f6251e463423e5625bf2e2   
        $this->currentPath = __DIR__;

    }

    public function newReturnBalances($currency) {
        $path = $this->currentPath;

        $cmd = "/usr/bin/node $path/poloniex.js returnBalance ";
        $output = shell_exec($cmd);
        $data = json_decode($output, true)['data'][0];
        $balances = $data['balances'];  
        foreach($balances as $res){
            if($currency == "tron"){
            if($res['currency']=="TRX"){
                return $res['available'];
            }
            }else if($currency == "ethereum"){
                if($res['currency']=="ETH"){
                    return $res['available'];
                }
            }
        }
    }

    public function newPlaceOrder($symbol,$amount) {
        $path = $this->currentPath;
        if($symbol == "USDT_ETH"){
            $symbol = "ETH_USDT";
        }else if($symbol == "USDT_TRX"){
            $symbol = "TRX_USDT";
        }

        $orderData = array(
            'symbol' => $symbol,
            'accountType' => 'spot',
            'side' => 'buy',
            'amount' => $amount,
        );
        $cmd = "/usr/bin/node $path/poloniex.js placeOrder '".json_encode($orderData)."'";
        $output = shell_exec($cmd);

        return $output;
    
    }    

    public function newWithdraw($currency,$amount,$address) {
        $path = $this->currentPath;

        $withdraw = array(
            'currency'=>$currency,
            'amount' => $amount,
            'address' => $address,

        );

        $cmd = "/usr/bin/node $path/poloniex.js Withdraw '".json_encode($withdraw)."'";
        $output = shell_exec($cmd);

        return $output;
    
    } 

    public function newOrderStatus($orderId) {
        $path = $this->currentPath;

       
        $cmd = "/usr/bin/node $path/poloniex.js orderStatus '".$orderId."'";
        $output = shell_exec($cmd);

        return $output;
    
    }   

    // Public HTTP API Methods Start
    public function returnTicker() {
        $url = $this->url;
        $path= "/public?command=returnTicker";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }

    public function return24hVolume() {
        $url = $this->url;
        $path= "/public?command=return24hVolume";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }

    public function returnOrderBook($currencyPair="",$depth="") {
        $url = $this->url;
        if(empty($currencyPair)){
            $currencyPair="all"; // default value
        }
        if(empty($depth)){
            $depth=50;// default value
        }
        $path= "/public?command=returnOrderBook&currencyPair=$currencyPair&depth=$depth";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }

    public function returnTradeHistoryPublic($currencyPair,$startTimeStamp="",$endTimeStamp="") {
        $url = $this->url;
        $path= "/public?command=returnTradeHistory&currencyPair=$currencyPair";
        if(($startTimeStamp) & ($endTimeStamp)){   
            $path= "/public?command=returnTradeHistory&currencyPair=$currencyPair&start=$startTimeStamp&end=$endTimeStamp";
        }
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }

    // $period valid value = (candlestick period in seconds; valid values are 300, 900, 1800, 7200, 14400, and 86400)
    // $start and $end valid value ="Start" and "end" are given in UNIX timestamp format and used to specify the date range for the data returned. 
    public function returnChartData($currencyPair,$period,$start,$end) {
        $url = $this->url;
        $path= "/public?command=returnChartData&currencyPair=$currencyPair&period=$period&start=$start&end=$end";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }

    public function returnCurrencies() {
        $url = $this->url;
        $path= "/public?command=returnCurrencies";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }

    // Public HTTP API Methods End

    // Private HTTP API Methods Start
    public function returnBalances() {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnBalances";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnCompleteBalances() {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnCompleteBalances";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnDepositAddresses() {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnDepositAddresses";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function generateNewAddress($currency) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=generateNewAddress&currency=$currency";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnDepositsWithdrawals($startTimestamp,$endTimestamp) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnDepositsWithdrawals&start=$startTimestamp&end=$endTimestamp";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnOpenOrders($currencyPair) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnOpenOrders&currencyPair=$currencyPair";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnTradeHistoryPrivate($currencyPair) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnTradeHistory&currencyPair=$currencyPair";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnOrderTrades($orderNumber) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnOrderTrades&orderNumber=$orderNumber";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnOrderStatus($orderNumber) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnOrderStatus&orderNumber=$orderNumber";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function buy($currencyPair,$rate,$amount) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=buy&currencyPair=$currencyPair&rate=$rate&amount=$amount";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function sell($currencyPair,$rate,$amount) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=sell&currencyPair=$currencyPair&rate=$rate&amount=$amount";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function cancelOrder($orderNumber,$clientOrderId="") {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=cancelOrder&orderNumber=$orderNumber";
        if($clientOrderId){
            $data.="&clientOrderId=$clientOrderId";
        }
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function cancelAllOrders($currencyPair="") {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=cancelAllOrders";
        if($currencyPair){
            $data.="&currencyPair=$currencyPair";
        }
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function cancelReplace($orderNumber,$rate,$clientOrderId='',$amount='') {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=cancelReplace&orderNumber=$orderNumber&rate=$rate";
        if($clientOrderId){
            $data.="&clientOrderId=$clientOrderId";
        }
        if($amount){
            $data.="&amount=$amount";
        }
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function moveOrder($orderNumber,$rate,$clientOrderId,$amount) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=moveOrder&orderNumber=$orderNumber&rate=$rate";
        if($clientOrderId){
            $data.="&clientOrderId=$clientOrderId";
        }
        if($amount){
            $data.="&amount=$amount";
        }
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function withdraw($currency,$amount,$address,$paymentId='') {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=withdraw&currency=$currency&amount=$amount&address=$address";
        if($paymentId){
            $data.="&paymentId=$paymentId";
        }
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnFeeInfo() {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnOrderTrades";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnAvailableAccountBalances($orderNumber) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnAvailableAccountBalances";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnTradableBalances($orderNumber) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnTradableBalances";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function transferBalance($currency,$amount,$fromAccount,$toAccount) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=transferBalance&currency=$currency&amount=$amount&fromAccount=$fromAccount&toAccount=$toAccount";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnMarginAccountSummary() {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnMarginAccountSummary";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function marginBuy($currency,$rate,$lendingRate,$amount,$clientOrderId='') {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=marginBuy&currency=$currency&rate=$rate&lendingRate=$lendingRate&amount=$amount";
        if($clientOrderId){
            $data.="&clientOrderId=$clientOrderId";
        }
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function marginSell($currency,$rate,$lendingRate,$amount,$clientOrderId='') {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=marginSell&currency=$currency&rate=$rate&lendingRate=$lendingRate&amount=$amount";
        if($clientOrderId){
            $data.="&clientOrderId=$clientOrderId";
        }
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getMarginPosition($currencyPair) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=getMarginPosition&currencyPair=$currencyPair";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function closeMarginPosition($currencyPair) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=transferBalance&currencyPair=$currencyPair";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function createLoanOffer($currency,$amount,$duration,$autoRenew) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=createLoanOffer&currency=$currency&amount=$amount&duration=$duration&autoRenew=$autoRenew";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function cancelLoanOffer($orderNumber) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=cancelLoanOffer&orderNumber=$orderNumber";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnOpenLoanOffers() {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnOpenLoanOffers";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnActiveLoans() {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnActiveLoans";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function returnLendingHistory($startTimeStamp,$endTimeStamp,$limit) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=returnLendingHistory&startTimeStamp=$startTimeStamp&endTimeStamp=$endTimeStamp&limit=$limit";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }   
    public function toggleAutoRenew($orderNumber) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=toggleAutoRenew&orderNumber=$orderNumber";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }  
    public function swapCurrencies($fromCurrency,$toCurrency,$amount) {
        $url = $this->url;
        $path= "/tradingApi";
        $data="command=swapCurrencies&fromCurrency=$fromCurrency&toCurrency=$toCurrency&amount=$amount";
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    // Private HTTP API Methods End

    // httpRequest able to (cURL) GET,POST, and DELETE
    public function httpRequest($url, $path, $data,$verb) {
        $url=$url.$path;
        $date=date_create();
        $nonce=$date->getTimestamp();
        $data=$data."&nonce=".strval($nonce)."00000";
        $sig = hash_hmac('sha512', $data, $this->apiSecret);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // If $verb = "GET", no need to run if/else condition below
        // POST Method
        if($verb=="POST"){
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        // DELETE Method
        if($verb=="DELETE"){
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $verb);
        }
        $headers = array(
            'Key:'.$this->apiKey,
            'Sign:'.$sig,
        );
        
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        //for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
        $resp = curl_exec($curl);
        curl_close($curl);
        // var_dump($resp);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);     
        return $resp;               
    }

}

?>