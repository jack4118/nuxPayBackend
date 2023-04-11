<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file contains functions for SMS gateway
 * Date  29/06/2017.
 **/
class XunSms
{

    public function __construct($db, $post)
    {
        $this->db = $db;
        $this->post = $post;
    }

    public function send_sms($params){
        global $setting;

        $sms_gateway = $setting->systemSetting["smsGateway"];
        $sendType = $params["sendType"];
        if($sms_gateway == "sms123")
        {
            if($sendType == "2way")
            {
                $response = $this->send_sms2way($params);
            }elseif(!$sendType){
                $response = $this->send_to_sms_123($params);
            }

        }else {
            $response =  $this->send_to_smss360($params);
        }
        $this->send_notification($response, $params);
        return $response;
    }

    public function send_to_sms_123($params)
    {
        $post = $this->post;

        $recipients = $params["recipients"];
        $message = $params["message"];

        $sms_data = $this->get_sms_xml($recipients, $message);
        $url = $sms_data["url"];
        $xml_data = $sms_data["xml"];
        $response = $post->curl_post_xml($url, $xml_data, 0);

        try{
            $xml = new SimpleXMLElement($response);
            $is_xml = true;
        }catch(exception $e){
            $is_xml = false;
        }
        
        $status = 0;
        if(!$is_xml){
            return array("msgCode" => null, "msg" => "Error getting response from SMS gateway", "status" => $status, "sms_gateway" => $url);
        }

        $xmlMsgCode = (string)$xml->msgCode;
        $xmlMsg = (string)$xml->msg;
        $xmlBalance = (string)$xml->balance;

        if($xmlMsgCode == "1609"){
            $status = 1;
        }

        return array("msgCode" => $xmlMsgCode, "msg" => $xmlMsg, "status" => $status, "sms_gateway" => $url);
    }

    private function get_sms_xml($recipients, $message)
    {
        $sms_credentials = $this->sms_credentials();

        $xml = "<root>";
        $xml .= "<command>" . $sms_credentials["sms_command"] . "</command>";
        $xml .= "<sendType>" . $sms_credentials["sms_send_type"] . "</sendType>";
        $xml .= "<email>" . $sms_credentials["sms_email"] . "</email>";
        $xml .= "<password>" . $sms_credentials["sms_password"] . "</password>";
        $xml .= "<params>";
        $xml .= "<items>";
        $xml .= "<recipient>" . $recipients . "</recipient>";
        $xml .= "<textMessage>";
        $xml .= htmlspecialchars($message);
        $xml .= "</textMessage>";
        $xml .= "</items>";
        $xml .= "</params>";
        $xml .= "</root>";

        return array("url" => $sms_credentials["sms_gateway"], "xml" => $xml);
    }

    private function sms_credentials()
    {
        global $setting;

        $sms_gateway = $setting->systemSetting["sms123URL"];
        $sms_email = $setting->systemSetting["sms123Email"];
        $sms_password = $setting->systemSetting["sms123Password"];
        $sms_command = "sendPrivateSMS";
        $sms_send_type = "shortCode";

        return array("sms_gateway" => $sms_gateway, "sms_email" => $sms_email, "sms_password" => $sms_password, "sms_command" => $sms_command, "sms_send_type" => $sms_send_type);
    }

    public function send_to_smss360($params){
        global $setting;

        $post = $this->post;

        $recipients = $params["recipients"];
        $message = $params["message"];

        $sms_gateway = $setting->systemSetting["smss360URL"];

        $sms_email = $setting->systemSetting["smss360Email"];
        $sms_key = $setting->systemSetting["smss360Key"];

        $req_data = array(
            "email" => $sms_email,
            "key" => $sms_key,
            "message" => $message,
            "recipient" => $recipients,
        );
        $result = $post->curl_get($sms_gateway, $req_data, 0);
        $status = 0;
        try{
            $xml = new SimpleXMLElement($result);
            $is_xml = true;
        }catch(exception $e){
            $is_xml = false;
        }
        
        if(!$is_xml){
            return array("msgCode" => null, "msg" => "Error getting response from SMS gateway", "status" => $status, "sms_gateway" => $sms_gateway);
        }
        
        $xmlStatusCode = (string)$xml->statusCode;
        $xmlMsg = (string)$xml->statusMsg;
        $xmlBalance = (string)$xml->balance;
        
        if($xmlStatusCode == "1606"){
            $status = 1;
        }

        return array("msgCode" => $xmlStatusCode, "msg" => $xmlMsg, "balance" => $xmlBalance, "status" => $status, "sms_gateway" => $sms_gateway);
    }

    public function send_sms2way($params){
        global $setting;

        $post = $this->post;

        $recipients = $params["recipients"];
        $message = $params["message"];

        $sms_gateway = $setting->systemSetting["sms123URL2way"];

        $sms_email = $setting->systemSetting["sms123Email2way"];
        $sms_key = $setting->systemSetting["sms1232wayApiKey"];
        
        $req_data = array(
            "apiKey" => $sms_key,
            "recipients" => $recipients,
            "messageContent" => $message,
            
        );
        
        $result = $post->curl_get($sms_gateway, $req_data, 0);
        $status = 0;
        try{
            $xml = new SimpleXMLElement($result);
            $is_xml = true;
        }catch(exception $e){
            $is_xml = false;
        }
        
        if(!$is_xml){
            return array("msgCode" => null, "msg" => "Error getting response from SMS gateway", "status" => $status, "sms_gateway" => $sms_gateway);
        }
        
        $xmlStatusCode = (string)$xml->statusCode;
        $xmlMsg = (string)$xml->statusMsg;
        $xmlBalance = (string)$xml->balance;
        
        if($xmlStatusCode == "1606"){
            $status = 1;
        }

        return array("msgCode" => $xmlStatusCode, "msg" => $xmlMsg, "balance" => $xmlBalance, "status" => $status, "sms_gateway" => $sms_gateway);
    }

    function send_notification($response, $newParams){
        global $general;

        $db= $this->db;

        $sms_gateway = $response["sms_gateway"];
        $status = $response["status"] == 1 || $response["status"] == 'ok'  ? "Success" : "Failed";
        $recipients = $newParams["recipients"];
        $ip = $newParams["ip"];
        $country = $newParams["country"];
        $device_os = $newParams["device"];
        $type = $newParams["type"];
        $sending_count = $newParams["sending_count"];
        $grouping = $newParams["companyName"] == 'NuxPay' ? 'thenux_pay' : '';

        if($newParams["companyName"] == 'NuxPay'){
            $type = 'nuxpay';
            $phone_number = "+".str_replace("+","",$recipients);
            $db->where('username', $phone_number);
            $db->where('register_site', $type);
            $xun_user = $db->getOne('xun_user');

            $nickname = $xun_user["nickname"];
            $content .= "Username: ".$nickname . "\n";
            $email = $xun_user["email"];
            $content .= "Email: ".$email . "\n";
        }

        $content .= "Phone number: " . $recipients . "\n";
        $content .= "IP: " . $ip . "\n";
        $content .= "Country: " . $country . "\n";
        $content .= "Device: " . $device_os . "\n";
        $content .= "Type Of User: " . $type . "\n";
        $content .= "Sending Count: " . $sending_count . "\n";
        $content .= "Status: " . $status . "\n";
        $content .= "SMS URL: " . $sms_gateway . "\n";
        $content .= "Time: " . date("Y-m-d H:i:s");

        $tag = "Post To SMS";
        $thenux_params["tag"] = $tag;
        $thenux_params["message"] = $content;
        $thenux_params["mobile_list"] = array();
        $thenux_result = $general->send_thenux_notification($thenux_params, $grouping);
        
    }
}
