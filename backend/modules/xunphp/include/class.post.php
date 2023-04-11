<?php

class post{
    
    function curl_post($url_string, $params = array(), $isXun = 1, $is_json = 1, $headers = array(), $isLogged = 1, $isCallBC = null) {
        global $db;
        global $config;
        global $xunWebservice;
        if(!$config){
            include_once(dirname(__FILE__)."/../include/config.php");
        }
        if(!$xunWebservice){
            include_once(dirname(__FILE__)."/../include/class.xun_webservice.php");
            $xunWebservice = new XunWebservice($db);
        }

        if($isXun == 1){
            $webserviceURL = $config['erlangUrl'].$url_string;
        }else {
            $webserviceURL = $url_string;
        }

        $_SERVER['REMOTE_ADDR'] = isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER["REMOTE_ADDR"];

        if($is_json){
            $params = json_encode($params);
            $jsonParams = $params;
        }else{
            $jsonParams = json_encode($params);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webserviceURL);
        curl_setopt($ch, CURLOPT_POST, true);
        
        if($isXun == 1){
            $headers[] = 'X-Xun-Token: '.$config["xunToken"];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        if($isCallBC ==1){
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        }
        else{
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        }
        
        $starttime = time();
        $createTime = date("Y-m-d H:i:s");
        $tblDate = date("Ymd");

        if($isLogged){
            $webservice_id = $xunWebservice->insertXunWebserviceData($jsonParams, $tblDate, $createTime, $url_string);
        }

        $jsonResult = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	$curlInfo = curl_getinfo($ch);

        curl_close($ch);
        
        if($is_json){
            $result = json_decode($jsonResult, true);
            $status = $result["code"];
        }else{
            $result = $jsonResult;
            $status = $result["status"];
        }
        
        $completeTime = date("Y-m-d H:i:s");
        $processedTime = time() - $starttime;
        
        if($isLogged){
            $xunWebservice->updateXunWebserviceData($webservice_id, $jsonResult, $status, $completeTime, $processedTime, $tblDate, $httpCode);
        }

	if ($params['command'] == "buySellCryptoCallback") {
		if (strlen($jsonResult) == 0) $error = "1";
		else $error = "0";
		$result['debug'] = array('error' => $error, 'rawResult' => $jsonResult, 'curlInfo' => $curlInfo);
        
	}

	return $result;
    }

    function curl_post_http2($url_string, $params = array(), $isXun = 1, $is_json = 1, $headers = array()) {
        global $db;
        global $config;
        global $xunWebservice;

        if(!$config){
            include_once(dirname(__FILE__)."/../include/config.php");
        }
        if(!$xunWebservice){
            include_once(dirname(__FILE__)."/../include/class.xun_webservice.php");
            $xunWebservice = new XunWebservice($db);
        }

        if($isXun == 1){
            $webserviceURL = $config['erlangUrl'].$url_string;
        }else {
            $webserviceURL = $url_string;
        }

        $_SERVER['REMOTE_ADDR'] = isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER["REMOTE_ADDR"];

        if($is_json){
            $params = json_encode($params);
            $jsonParams = $params;
        }else{
            $jsonParams = json_encode($params);
        }

       
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webserviceURL);
        curl_setopt($ch, CURLOPT_POST, true);
        
        if($isXun == 1){
            $headers[] = 'X-Xun-Token: '.$config["xunToken"];
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $starttime = time();
        $createTime = date("Y-m-d H:i:s");
        $tblDate = date("Ymd");
        $webservice_id = $xunWebservice->insertXunWebserviceData($jsonParams, $tblDate, $createTime, $url_string);

        $jsonResult = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        
        if($is_json){
            $result = json_decode($jsonResult, true);
            $status = $httpcode;
        }else{
            $result = $jsonResult;
            $status = $httpcode;
        }
        
        $completeTime = date("Y-m-d H:i:s");
        $processedTime = time() - $starttime;
        
        $xunWebservice->updateXunWebserviceData($webservice_id, $jsonResult, $status, $completeTime, $processedTime, $tblDate, $httpcode);

        return $result;
    }


    function curl_post_xml($url_string, $params = array(), $isXun = 1, $headers = array()) {
        global $db;
        global $config;
        global $xunWebservice;

        if(!$config){
            include_once(dirname(__FILE__)."/../include/config.php");
        }
        if(!$xunWebservice){
            include_once(dirname(__FILE__)."/../include/class.xun_webservice.php");
            $xunWebservice = new XunWebservice($db);
        }

        if($isXun == 1){
            $webserviceURL = $config['erlangUrl'].$url_string;
        }else {
            $webserviceURL = $url_string;
        }

        $_SERVER['REMOTE_ADDR'] = isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER["REMOTE_ADDR"];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webserviceURL);
        curl_setopt($ch, CURLOPT_POST, true);
        
        if($isXun == 1){
            $headers[] = 'X-Xun-Token: '.$config["xunToken"];
        }

        if(!empty($headers)){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $starttime = time();
        $createTime = date("Y-m-d H:i:s");
        $webservice_id = $xunWebservice->insertXunWebserviceData($params, $tblDate, $createTime, $url_string);
        
        $xmlResult = curl_exec($ch);
        $curlErrorNo = curl_errno($ch);
        $curlErrorDesc = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $status = 1;
        
        $completeTime = date("Y-m-d H:i:s");
        $processedTime = time() - $starttime;
            
        $xunWebservice->updateXunWebserviceData($webservice_id, $xmlResult, $status, $completeTime, $processedTime, $tblDate, $httpCode);

        if($curlErrorNo){
            return array("status" => "error", "curl_errno" => $curlErrorNo, "curl_error" => $curlErrorDesc);
        }

        return $xmlResult;
    }

    function curl_post_multipart($url_string, $params = array()) {
        include(dirname(__FILE__)."/../include/config.php");

        $webserviceURL = $config['webserviceURL'].$url_string;
        if(!in_array($command, $filteredCommand)) $params["business_email"] = $_SESSION["business_email"];

        $_SERVER['REMOTE_ADDR'] = isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER["REMOTE_ADDR"];

        // $boundary = 'TITO-' . md5(time());
        // $params = json_encode($params);

        // return $this->getBody($boundary, $params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webserviceURL);
        curl_setopt($ch, CURLOPT_POST, true);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Xun-Token: '.$_SESSION["access_token"], 'Content-Type: multipart/form-data; boundary=' . $boundary));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Xun-Token: '.$config["xunToken"]));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $result = curl_exec($ch);

        curl_close($ch);

        return $result;
    }

    function curl_get($url_string, $params = array(), $isXun = 1, $headers = array()) {
        global $db;
        global $config;
        global $xunWebservice;
        include(dirname(__FILE__)."/../include/config.php");

        if(!$config){
            include_once(dirname(__FILE__)."/../include/config.php");
        }
        if(!$xunWebservice){
            include_once(dirname(__FILE__)."/../include/class.xun_webservice.php");
            $xunWebservice = new XunWebservice($db);
        }

        if($isXun){
            $webserviceURL = $config['erlangUrl'].$url_string;
        }else{
            $webserviceURL = $url_string;
        }
        
        
        // if(!in_array($command, $filteredCommand)) $params["business_email"] = $_SESSION["business_email"];
        $remote_addr = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '127.0.0.1';

        $_SERVER['REMOTE_ADDR'] = isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $remote_addr;

        if(!empty($params)){
            $webserviceURL .= "?".http_build_query($params);
        }

        if (curl_error($ch))
        return array("curl_error" => curl_error($ch));
        
        $starttime = time();
        $createTime = date("Y-m-d H:i:s");

        $paramsJson = json_encode($params);
        $tblDate = date("Ymd");
        $webservice_id = $xunWebservice->insertXunWebserviceData($paramsJson, $tblDate, $createTime, $url_string);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webserviceURL);
        if($isXun == 1){
            $headers[] = 'X-Xun-Token: '.$config["xunToken"];
        }
        if(!empty($headers)){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, 'POST');
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorNo = curl_errno($ch);
        $curlErrorDesc = curl_error($ch);  

        curl_close($ch);
        
        $completeTime = date("Y-m-d H:i:s");
        $processedTime = time() - $starttime;
        
        $xunWebservice->updateXunWebserviceData($webservice_id, $result, $status, $completeTime, $processedTime, $tblDate, $httpCode);

        if($curlErrorNo){
            return array("status" => "error", "curl_errno" => $curlErrorNo, "curl_error" => $curlErrorDesc);
        }

        return $xmlResult;  
    }
    
    function curl_crypto($command, $params = array(), $cryptoType = 1, $callbackUrl = 0)
    {

        global $db;
        global $config;
        global $xunWebservice;
        global $general;
        global $xun_numbers;

        if(!$config){
            include_once(dirname(__FILE__)."/../include/config.php");
        }

        if(!$xunWebservice){
            include_once(dirname(__FILE__)."/../include/class.xun_webservice.php");
            $xunWebservice = new XunWebservice($db);
        }

        if($cryptoType == 0){
            $webserviceURL = $callbackUrl;
        }else if($cryptoType == 1){
            //  payment gateway
           $webserviceURL = $config["cryptoUrl"]; 
            
            $curlData["name"] = $config["cryptoPartnerName"];
            $curlData["site"] = $config["cryptoSite"];
            $curlData["apiKey"] = $config["cryptoApiKey"];
        }else if($cryptoType == 2){
            //  blockchain/wallet
            $webserviceURL = $config["cryptoWalletUrl"]; 
            
            $curlData["partnerSite"] = $config["cryptoBCPartnerSite"];
        }

        $curlData["command"] = $command;
        $curlData["params"] = $params;

        $curlData = json_encode($curlData);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webserviceURL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

        $starttime = time();
        $createTime = date("Y-m-d H:i:s");
        $webservice_id = $xunWebservice->insertXunWebserviceData($curlData, $tblDate, $createTime, $command);

        $jsonResult = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($jsonResult, true);
        $status = $result["code"];

        if($status != '1' && $status != '0'){
            $message = "Command: ".$command."\n";
            $message .= "Data In: ".$curlData."\n";
            $message .= "Data Out: ".$jsonResult."\n";
            $message .= "Created At: ".date("Y-m-d H:i:s")."\n";
    
            $thenux_params["tag"] = 'BC API Error';
            $thenux_params["message"] = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");
        }
        

        $completeTime = date("Y-m-d H:i:s");
        $processedTime = time() - $starttime;

        $xunWebservice->updateXunWebserviceData($webservice_id, $jsonResult, $status, $completeTime, $processedTime, $tblDate, $httpCode);

        return $result;

    }

    public function curl_xanpool($url, $params, $method = 'POST'){
        global $db;
        global $config;
        global $xunWebservice;
        global $general;
        if(!$config){
            include_once(dirname(__FILE__)."/../include/config.php");
        }
        if(!$xunWebservice){
            include_once(dirname(__FILE__)."/../include/class.xun_webservice.php");
            $xunWebservice = new XunWebservice($db);
        }

        $params = json_encode($params, true);

        $starttime = time();
        $createTime = date("Y-m-d H:i:s");
        $tblDate = date("Ymd");

        $webservice_id = $xunWebservice->insertXunWebserviceData($params, $tblDate, $createTime, $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_USERPWD, $config['xanpool_api_key'].":".$config['xanpool_secret_key']);
        if($method == 'GET'){
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
        }
        else if($method == "DELETE"){
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        else{
            curl_setopt($ch, CURLOPT_POST, true);
        }


        $jsonResult = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorNo = curl_errno($ch);
        $curlErrorDesc = curl_error($ch);  

        $result = json_decode($jsonResult, true);

        if($result['error']){
            $message = "URL: ".$url."\n";
            $message .= "Data In: ".json_encode($params)."\n";
            $message .= "Data Out: ".$jsonResult."\n";
            $message .= "Created At: ".date("Y-m-d H:i:s")."\n";
    
            $thenux_params["tag"] = 'Xanpool API Error';
            $thenux_params["message"] = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");

        }
        $completeTime = date("Y-m-d H:i:s");
        $processedTime = time() - $starttime;
        
        $xunWebservice->updateXunWebserviceData($webservice_id, $jsonResult, $status, $completeTime, $processedTime, $tblDate, $httpCode);


        return $result;
    }

    public function curl_simplex($url, $params, $method = 'POST'){
        global $db;
        global $config;
        global $xunWebservice;
        global $general;
        if(!$config){
            include_once(dirname(__FILE__)."/../include/config.php");
        }
        if(!$xunWebservice){
            include_once(dirname(__FILE__)."/../include/class.xun_webservice.php");
            $xunWebservice = new XunWebservice($db);
        }

        $params = json_encode($params);

        $starttime = time();
        $createTime = date("Y-m-d H:i:s");
        $tblDate = date("Ymd");
        $webservice_id = $xunWebservice->insertXunWebserviceData($params, $tblDate, $createTime, $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        if($method == 'GET'){
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
        }
        else if($method == "DELETE"){
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        else{
            curl_setopt($ch, CURLOPT_POST, true);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Authorization: ApiKey '.$config['simplex_api_key']
            )
        );
        // curl_setopt($ch, CURLOPT_VERBOSE, true);

        $jsonResult = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorNo = curl_errno($ch);
        $curlErrorDesc = curl_error($ch);  
        
        curl_close($ch);

        $result = json_decode($jsonResult, true);
        $status = $result["code"];
        
     
        if ($result['error']) {
            $message = "URL: ".$url."\n";
            $message .= "Data In: ".json_encode($params)."\n";
            $message .= "Data Out: ".$jsonResult."\n";
            $message .= "Created At: ".date("Y-m-d H:i:s")."\n";
    
            $thenux_params["tag"] = 'Simplex API Error';
            $thenux_params["message"] = $message;
            $thenux_params["mobile_list"] = $xun_numbers;
            $thenux_result = $general->send_thenux_notification($thenux_params, "thenux_issues");
        }
        $completeTime = date("Y-m-d H:i:s");
        $processedTime = time() - $starttime;
        
        $xunWebservice->updateXunWebserviceData($webservice_id, $jsonResult, $status, $completeTime, $processedTime, $tblDate, $httpCode);
    
        return $result;
    }

}

?>
