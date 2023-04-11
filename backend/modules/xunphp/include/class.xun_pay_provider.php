<?php
class XunPayProvider
{
    public function __construct($db, $setting, $post)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->post = $post;
    }

    public function sendCommandMeReload($command, $ref_id)
    {
        $setting = $this->setting;
        $post = $this->post;
        $resellerAccount = $setting->systemSetting["meReloadResellerAccount"];
        /**
         * R_<ReloadNumber>_<ReloadAmount>_<Product>
         */

        $url = 'http://uone.webhop.biz:2017/ereloadws/service.asmx?op=SendCommand';

        ############################# SOAP v1.2################################
        $xml_post_string = '<?xml version="1.0" encoding="utf-8"?>';
        $xml_post_string .= '    <soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">';
        $xml_post_string .= '    <soap12:Body>';
        $xml_post_string .= '        <SendCommand xmlns="http://tempuri.org/">';
        $xml_post_string .= '            <ResellerAccount>' . $resellerAccount . '</ResellerAccount>';
        $xml_post_string .= '            <RefNum>' . $ref_id . '</RefNum>';
        $xml_post_string .= '            <Message>' . $command . '</Message>';
        $xml_post_string .= '        </SendCommand>';
        $xml_post_string .= '    </soap12:Body>';
        $xml_post_string .= '    </soap12:Envelope>';

        $headers = array(
            "POST  /ereloadws/service.asmx HTTP/1.1",
            "Host: uone.webhop.biz",
            "Content-Type: application/soap+xml; charset=utf-8",
            "Content-Length: " . strlen($xml_post_string),
            "SOAPAction: http://tempuri.org/SendCommand",
        );
        #########################################################################

        $post_response = $post->curl_post_xml($url, $xml_post_string, 0, $headers);

        if (isset($post_response["status"]) && $post_response["status"] == "error") {
            //  send notification
            $return_data = array("code" => 0, "error_message" => $post_response["curl_error"]);
        } else {
            $response1 = str_replace("<soap:Body>", "", $post_response);
            $response2 = str_replace("</soap:Body>", "", $response1);

            $parser = simplexml_load_string($response2);
            $sendCommandResult = $parser->SendCommandResponse->SendCommandResult;

            if ($sendCommandResult == 1) {
                $return_data = array("code" => 1);
            } else {
                $return_data = array("code" => 0, "error_message" => "SendCommandResult: " . $sendCommandResult);
            }
        }

        return $return_data;
    }

    public function reloadlyTopup($params)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $reloadly = new reloadly($db, $setting, $post);
        $reloadlyResponce = $reloadly->topup($params);

        if (isset($reloadlyResponce["errorCode"])) {
            $return_data = array("code" => 0, "error_message" => $reloadlyResponce["message"]);
        } else {
            $transactionId = $reloadlyResponce["transactionId"];
            $return_data = array("code" => 1, "transaction_id" => $transactionId);
        }

        return $return_data;
    }

    public function giftnpayGiftcard($payTransactionRec, $productData, $productProviderData, $productOptionData)
    {
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $giftnpay = new GiftnPay($db, $setting, $post);

        $productId = $productOptionData["pid"];

        $sell_price = $payTransactionRec["sell_price"];
        $sell_price = $setting->setDecimal($sell_price, "fiatCurrency");
        $providerParams = array(
            "amount" => $sell_price, // not local_currency
            "quantity" => $payTransactionRec["quantity"],
            "productId" => $productOptionData["pid"],
            "quantity" => $payTransactionRec["quantity"],
            "phoneNumber" => $payTransactionRec["phone_number"],
            "accountNumber" => $payTransactionRec["account_no"]
        );
        
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
        $giftnpayResponse = $giftnpay->buyProductVerification($providerParams);
        
        if (isset($giftnpayResponse["code"]) && $giftnpayResponse["code"] === 0) {
            $verifyData = $giftnpayResponse["data"];
            $transactionAmountCurrency = $payTransactionRec["amount_currency"];
            $totalPayAmount = $verifyData["totalPayAmount"];

            //  refund if provider charges higher
            if(bccomp((string)$transactionAmountCurrency, (string)$totalPayAmount, 2) < 0){
                $return_data = array("code" => 0, "error_message" => "Amount charged by provider: $totalPayAmount");
                return $return_data;
            }
            
            $giftnpayConfirmationResponse = $giftnpay->buyProductPaymentConfirmation($providerParams);
            // $giftnpayConfirmationResponse = array(
            //         "status"=> "error",
            //         "code"=> 1,
            //         "statusMsg"=> null,
            //         "data"=> array(
            //             "field"=> [
            //                 array(
            //                     "id"=> "paymentError",
            //                     "msg"=> "Insufficient Balance"
            //                 )
            //             ]
            //         )
            //     );

            if (isset($giftnpayConfirmationResponse["code"]) && $giftnpayConfirmationResponse["code"] === 0) {
                $giftnpayConfirmationResponseData = $giftnpayConfirmationResponse["data"];
                $transactionId = $giftnpayConfirmationResponseData["paymentNo"];
                $orderIdArr = $giftnpayConfirmationResponseData["orderID"];
                $return_data = array("code" => 1, "transaction_id" => $transactionId, "order_id" => $orderIdArr);
            }else{
                $responseData = $giftnpayConfirmationResponse["data"];
                $responseDataField = $responseData["field"];
                if(!empty($responseDataField)){
                    $errorData = $responseDataField[0];
                    $errorId = $errorData["id"];
                    $responseMsg = $errorData["msg"];
                    $errorMessage = $errorId . ": " . $responseMsg;

                }
                $return_data = array("code" => 0, "error_message" => $errorMessage);
            }
        } else {
            $responseData = $giftnpayResponse["data"];
            $responseDataField = $responseData["field"];
            if(!empty($responseDataField)){
                $errorData = $responseDataField[0];
                $errorId = $errorData["id"];
                $responseMsg = $errorData["msg"];
                $errorMessage = $errorId . ": " . $responseMsg;

            }
            $return_data = array("code" => 0, "error_message" => $errorMessage);
        }

        return $return_data;
    }
}
