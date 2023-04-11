<?php
    /**        
     * API Provided by AAX, I only convert the code from shell to php.
     * API Documentation: 
     * https://www.aax.com/apidoc/index.html?shell#introduction
     * If you have any problem, Please refer to AXX API documentation.
     **/
class AAX {
    function __construct($apiKey, $apiSecret, $url) {
        $this->url = $url;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;            
    }

    // Market data Start
    public function getMaintenance() {
        $url = $this->url;
        $path= "/v2/announcement/maintenance";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getServerTime() {
        $url = $this->url;
        $path= "/v2/time";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getInstruments() {
        $url = $this->url;
        $path= "/v2/instruments";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getCurrency() {
        $url = $this->url;
        $path= "/v2/currencies";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getOrderbook($symbol,$level) {
        $url = $this->url;
        $path= "/v2/market/orderbook?symbol=".$symbol."&level=".$level;
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getOpenInterest($symbol) {
        $url = $this->url;
        $path= "/v2/futures/position/openInterest?symbol=".$symbol;
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getTheLast24hMarketSummary() {
        $url = $this->url;
        $path= "/v2/market/tickers";
        $data="";
        $verb="GET";
                $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getCurrentCandlestick($symbol,$timeFrame) {
        $url = $this->url;
        $path= "/v2/market/candles/symbol=".$symbol."&timeFrame=".$timeFrame;
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getRecentTrades($symbol,$limit) {
        $url = $this->url;
        $path= "/v2/market/trades?symbol=$symbol&limit=$limit";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getCurrentMarkPrice($symbol) {
        $url = $this->url;
        $path= "/v2/market/markPrice?symbol=$symbol";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getPredictedFundingRate($symbol) {
        $url = $this->url;
        $path= "/v2/futures/funding/predictedFunding/$symbol";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getLastFundingRate($symbol) {
        $url = $this->url;
        $path= "/v2/futures/funding/prevFundingRate/$symbol";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getFundingRateHistory($symbol,$limit) {
        $url = $this->url;
        $path= "/v2/futures/funding/fundingRate?symbol=$symbol&limit=$limit";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getCurrentIndexCandlestick($symbol,$timeFrame) {
        $url = $this->url;
        $path= "/v2/market/index/candles?symbol=$symbol&timeFrame=$timeFrame";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    // Market data End

    // User Info Start
    public function getUserInfo() {
        $url = $this->url;
        $path= "/v2/user/info";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getAccountBalances() {
        $url = $this->url;
        $path= "/v2/account/balances";
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getDepositAddress($currency,$network) {
        $url = $this->url;
        $path= "/v2/account/deposit/address?currency=$currency";
        if($network!=null){
            $path= $path."&network=$network";
        }
        $data="";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getDepositHistory() {
        $url = $this->url;
        $path = "/v2/account/deposits";
        $data = "";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getWithdrawHistory() {
        $url = $this->url;
        $path= "/v2/account/withdraws";
        $data= "";
        $verb="GET";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getAssetTransfer($fromPurse,$toPurse,$currency,$quantity) {
        $url = $this->url;
        $path= "/v2/account/transfer";
        $data= array(
            "fromPurse"=> $fromPurse,
            "toPurse"=> $toPurse,
            "currency"=> $currency,
            "quantity"=> $quantity
        );
        $data=json_encode($data,true);
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    // User Info End 

    // Sport Trading Start
    public function createANewSpotOrder($orderType,$symbol,$price,$orderQty,$side,$cl0rdID) {
        $url = $this->url;
        $path= "/v2/spot/orders";
        $data= array(
            "orderType"=> $orderType,
            "symbol"=> $symbol,
            "price"=> $price,
            "orderQty"=> $orderQty,
            "side"=> $side,
            "clOrdID"=> $cl0rdID,
        );
        $data=json_encode($data,true);
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function cancelASpotOrder($orderID) {
        $url = $this->url;
        $path= "/v2/spot/orders/cancel/$orderID";
        $data= "";
        $verb='DELETE';
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function batchCancelSpotOrder($symbol,$orderID,$cl0rdID) {
        $url = $this->url;
        $path= "/v2/spot/orders/cancel/all";
        $data= array(
            "symbol"=> $symbol,
            "orderID"=>$orderID?$orderID:"",
            "clOrdID"=>$cl0rdID?$cl0rdID:""
        );
        $data=json_encode($data,true);
        $verb='DELETE';
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function cancelAllOnTimeout($timeout) {
        $url = $this->url;
        $path= "/v2/spot/orders/cancelAllOnTimeout";
        $data= array(
            "timeout"=> $timeout,
        );
        $verb='POST';
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    //
    public function retrieveSpotTrades() {
        $url = $this->url;
        $path= "/v2/spot/trades";
        $data= "";
        $verb="GET";
            $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function retrieveSpotOpenOrders($orderID) {
        $url = $this->url;
        $path= "/v2/spot/openOrders?orderID=$orderID";
        $data= "";
        $verb="GET";
         $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    //
    public function retrieveSpotHistoricalOrders($orderID) {
        $url = $this->url;
        $path= "/v2/spot/orders?orderID=$orderID";
        $data= "";
        $verb="GET";
         $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    // Sport Trading End

    // Futures Trading Start
    public function placeFuturesOrder($orderType,$symbol,$price,$orderQty,$side,$cl0rdID,$stopPrice,$execInst) {
        $url = $this->url;
        $path= "/v2/futures/orders";
        $data= array(
            "orderType"=> $orderType,
            "symbol"=> $symbol,
            "price"=> $price,
            "orderQty"=> $orderQty,
            "side"=> $side,
            "clOrdID"=> $cl0rdID,
            "stopPrice"=>$stopPrice?$stopPrice:"",
            "execInst"=>$execInst?$execInst:"",
        );
        $data=json_encode($data,true);
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function cancelAFuturesOrder($orderID) {
        $url = $this->url;
        $path= "/v2/futures/orders/cancel/$orderID";
        $data= "";
        $verb='DELETE';
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function batchCancelFutureOrder($symbol,$orderID,$cl0rdID) {
        $url = $this->url;
        $path= "/v2/spot/futures/cancel/all";
        $data= array(
            "symbol"=> $symbol,
            "orderID"=>$orderID?$orderID:"",
            "clOrdID"=>$cl0rdID?$cl0rdID:""
        );
        $data=json_encode($data,true);
        $verb='DELETE';
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function cancelAllFuturesOrdersOnTimeout($timeout) {
        $url = $this->url;
        $path= "/v2/futures/orders/cancelAllOnTimeout";
        $data= array(
            "timeout"=> $timeout,
        );
        $verb='POST';
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function retrieveFuturesPosition($symbol) {
        $url = $this->url;
        $path= $symbol?"/v2/futures/position?symbol=$symbol":"/v2/futures/position";
        $data= "";
        $verb="GET";
         $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    //
    public function setPositionTakeProfitAndStopLoss($symbol,$side,$stopLossPrice,$stopLossStatus,$takeProfitPrice,$takeProfitStatus) {
        $url = $this->url;
        $path= "/v2/futures/position/sltp";
        $data= array(
            "symbol"=> $symbol,
            "side"=> $side,
            "stopLossPrice"=> $stopLossPrice?$stopLossPrice:"",
            "stopLossStatus"=> $stopLossStatus?$stopLossStatus:"",
            "takeProfitPrice"=>$takeProfitPrice?$takeProfitPrice:"",
            "takeProfitStatus"=>$takeProfitStatus?$takeProfitStatus:"",
        );
        $data=json_encode($data,true);
        $verb="POST";
         $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function closeFuturesPosition($symbol,$price) {
        $url = $this->url;
        $path= "/v2/futures/position/close";
        $data= array(
            "symbol"=> $symbol,
            "price"=> $price?$price:"",
        );
        $data=json_encode($data,true);
        $verb="POST";
         $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    //
    public function retrieveFuturesClosedPosition() {
        $url = $this->url;
        $path= "/v2/futures/position/closed";
        $data="";
        $verb="GET";
         $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function futuresUpdateLeverage($symbol,$leverage) {
        $url = $this->url;
        $path= "/v2/futures/position/leverage";
        $data= array(
            "symbol"=> $symbol,
            "leverage"=> $leverage,
        );
        $data=json_encode($data,true);
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function modifyIsolatedPositionMargin($symbol,$margin) {
        $url = $this->url;
        $path= "/v2/futures/position/margin";
        $data= array(
            "symbol"=> $symbol,
            "margin"=> $margin,
        );
        $data=json_encode($data,true);
        $verb="POST";
        $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function retrieveFuturesTrades() {
        $url = $this->url;
        $path= "/v2/futures/trades";
        $data= "";
        $verb="GET";
         $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function retrieveFuturesOpenOrders() {
        $url = $this->url;
        $path= "/v2/futures/openOrders";
        $data= "";
        $verb="GET";
         $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function retrieveFuturesHistoricalOrders() {
        $url = $this->url;
        $path= "/v2/futures/orders";
        $data= "";
        $verb="GET";
         $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getPredictedFundingFee($symbol) {
        $url = $this->url;
        $path= "/v2/futures/funding/predictedFundingFee/$symbol";
        $data= "";
        $verb="GET";
         $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    public function getHistoricalFundingFee($symbol) {
        $url = $this->url;
        $path= "/v2/futures/funding/fundingFee?symbol=$symbol";
        $data= "";
        $verb="GET";
         $json = $this->httpRequest($url,$path,$data,$verb);
        return $json;
    }
    // Futures Trading End

    // httpRequest able to (cURL) GET,POST, and DELETE
    public function httpRequest($url, $path, $data,$verb) {

        $url = $url.$path;
        $date=date_create();
        $nonce=$date->getTimestamp().'000';
        $string=strval($nonce) . ':' . $verb . $path .$data;
        $sig = hash_hmac('sha256', $string, $this->apiSecret);

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
            'X-ACCESS-NONCE:'.$nonce,
            'X-ACCESS-KEY:'.$this->apiKey,
            'X-ACCESS-SIGN:'.$sig,
            'Content-Type: application/json'
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