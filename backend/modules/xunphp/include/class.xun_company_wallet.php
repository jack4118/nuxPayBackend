<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file contains the functions for wallet fund out
 * Date  13/07/2019.
 **/
class XunCompanyWallet
{

    public function __construct($db, $setting, $post)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->post = $post;
    }

    public function fundOut($walletServer, $postParams)
    {
        global $config, $xunCrypto;

        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        if (empty($walletServer)) {
            return;
        }

        $amount = $postParams["amount"];
        $walletType = $postParams["walletType"];
        $wallet_transaction_id = $postParams["walletTransactionID"];

        $satoshiAmount = $xunCrypto->get_satoshi_amount($walletType, $amount);

        $postParams["amount"] = $satoshiAmount;

        $walletAddress = $setting->systemSetting[$walletServer . "WalletAddress"];
        // if ($walletServer == "escrow") {
        //     $url_string = $config["escrowURL"];
        // } else 
        if ($walletServer == "trading_fee") {
            if ($wallet_transaction_id) {
                $url_string = $config["tradingFeeURL_walletTransaction"];
            } else {
                $url_string = $config["tradingFeeURL"];
            }
        } else if ($walletServer == "company_pool") {
            if ($wallet_transaction_id) {
                $url_string = $config["companyPoolURL_walletTransaction"];
            } else {
                $url_string = $config["companyPoolURL"];
            }
        } 
        // else if ($walletServer == "freecoin") {
        //     $url_string = $config["freecoinURL"];
        // }

        $postResponse = $post->curl_post($url_string, $postParams, 0, 0, array(), 1, 1);

        $res = $this->logFundOutResponse($postResponse, $walletServer, $postParams);

        return $res;
    }

    private function logFundOutResponse($postResponse, $walletServer, $inputParams)
    {
        global $config, $setting, $general;
        $db = $this->db;

        $postReturnObj = json_decode($postResponse);
        if ($postReturnObj->code == 0) {
            // send notification
            $result = $postReturnObj->result;
            if (gettype($result) == "object") {
                $error_message = $result->statusMsg;
            } else {
                $error_message = $result;
            }

            $tag = "Fund Out Error";
            $content = "Error: " . $error_message;
            $content .= "\n\nWallet Server: " . $walletServer;
            $content .= "\nInput: " . json_encode($inputParams);

            $thenux_params["tag"] = $tag;
            $thenux_params["message"] = $content;
            $thenux_params["mobile_list"] = array();
            $general_result = $general->send_thenux_notification($thenux_params);

        }
        return (array) $postReturnObj;
    }

    public function payTransactionRefund($payTransactionData)
    {
        global $xunCrypto, $config, $general;
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $payTopupWalletAddress = $setting->systemSetting["payWalletAddress"];

        /**
         * if error, call refund,
         * pay transaction table, status = pending_refund
         * add record to wallet transaction
         *
         */

        $amount = $payTransactionData["amount"];
        $walletType = $payTransactionData["wallet_type"];
        $userID = $payTransactionData["user_id"];
        $payTransactionID = $payTransactionData["id"];

        $xunUserService = new XunUserService($db);
        $userCryptoAddress = $xunUserService->getActiveInternalAddressByUserID($userID);

        if (!$userCryptoAddress) {
            return;
        }
        $satoshiAmount = $xunCrypto->get_satoshi_amount($walletType, $amount);
        $receiverAddress = $userCryptoAddress["address"];

        $txObj = new stdClass();
        $txObj->userID = $userID;
        $txObj->address = $payTopupWalletAddress;
        $txObj->referenceID = '';

        $transactionToken = $xunUserService->insertCryptoTransactionToken($txObj);

        $transactionStatus = "pending";
        $addressType = "pay";

        $date = date("Y-m-d H:i:s");

        $transactionObj = new stdClass();
        $transactionObj->status = $transactionStatus;
        $transactionObj->transactionHash = "";
        $transactionObj->transactionToken = $transactionToken;
        $transactionObj->senderAddress = $payTopupWalletAddress;
        $transactionObj->recipientAddress = $receiverAddress;
        $transactionObj->userID = $userID;
        $transactionObj->walletType = $walletType;
        $transactionObj->amount = $amount;
        $transactionObj->addressType = $addressType;
        $transactionObj->transactionType = "send";
        $transactionObj->escrow = 0;
        $transactionObj->referenceID = $payTransactionID;
        $transactionObj->escrowContractAddress = '';
        $transactionObj->createdAt = $date;
        $transactionObj->updatedAt = $date;
        $transactionObj->expiresAt = '';

        $xunWallet = new XunWallet($db);

        $walletTransactionID = $xunWallet->insertUserWalletTransaction($transactionObj);

        if ($walletTransactionID) {
            $updatePayTransactionData = [];
            $updatePayTransactionData["status"] = "pending_refund";
            $updatePayTransactionData["updated_at"] = $date;

            $db->where("id", $payTransactionData["id"]);
            $db->update("xun_pay_transaction", $updatePayTransactionData);
            $urlString = $config["giftCodeUrl"];

            $curlParams = array(
                "command" => "fundOutCompanyWallet",
                "params" => array(
                    "senderAddress" => $payTopupWalletAddress,
                    "receiverAddress" => $receiverAddress,
                    "amount" => $amount,
                    "satoshiAmount" => $satoshiAmount,
                    "walletType" => $walletType,
                    "id" => $walletTransactionID,
                    "transactionToken" => $transactionToken,
                    "addressType" => "Top Up",
                ),
            );

            $curlResponse = $post->curl_post($urlString, $curlParams, 0, 1);
            if ($curlResponse["code"] == 0) {
                // send notification
                // $error_message = $curlResponse_obj->result;
                $error_message = $curlResponse["message_d"];

                $tag = "Pay Wallet Transfer Error";
                $content = "Error: " . $error_message;
                $content .= "\n\nID: " . $walletTransactionID;
                // $content .= "\nGift code: " . $gift_code;
                $content .= "\nAmount: " . $amount;
                $content .= "\nWallet Type: " . $walletType;
                $content .= "\nReceiver Address: " . $receiverAddress;

                $thenux_params = [];
                $thenux_params["tag"] = $tag;
                $thenux_params["message"] = $content;
                $thenux_params["mobile_list"] = array();
                $thenux_result = $general->send_thenux_notification($thenux_params);

                $insert_data = [];
                $insert_data["username"] = $username ? $username : '';
                $insert_data["data"] = json_encode($new_params);
                $insert_data["processed"] = 0;
                $insert_data["created_at"] = date("Y-m-d H:i:s");
                $insert_data["updated_at"] = date("Y-m-d H:i:s");

                $db->insert("xun_marketplace_escrow_error", $insert_data);
            }
            return $curlResponse;
        }
    }

    public function payTransactionItemRefund($payTransactionData, $payTransactionItemId)
    {
        global $xunCrypto, $config, $general;
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $payTopupWalletAddress = $setting->systemSetting["payWalletAddress"];

        $refundAmount = $payTransactionData["unit_price"];
        $walletType = $payTransactionData["wallet_type"];
        $userID = $payTransactionData["user_id"];
        $payTransactionID = $payTransactionData["id"];

        $xunUserService = new XunUserService($db);
        $userCryptoAddress = $xunUserService->getActiveInternalAddressByUserID($userID);

        if (!$userCryptoAddress) {
            return;
        }
        $satoshiAmount = $xunCrypto->get_satoshi_amount($walletType, $refundAmount);
        $receiverAddress = $userCryptoAddress["address"];

        $txObj = new stdClass();
        $txObj->userID = $userID;
        $txObj->address = $payTopupWalletAddress;
        $txObj->referenceID = '';

        $transactionToken = $xunUserService->insertCryptoTransactionToken($txObj);

        $transactionStatus = "pending";
        $addressType = "payCallbackRefund";

        $date = date("Y-m-d H:i:s");

        $transactionObj = new stdClass();
        $transactionObj->status = $transactionStatus;
        $transactionObj->transactionHash = "";
        $transactionObj->transactionToken = $transactionToken;
        $transactionObj->senderAddress = $payTopupWalletAddress;
        $transactionObj->recipientAddress = $receiverAddress;
        $transactionObj->userID = $userID;
        $transactionObj->walletType = $walletType;
        $transactionObj->amount = $refundAmount;
        $transactionObj->addressType = $addressType;
        $transactionObj->transactionType = "send";
        $transactionObj->escrow = 0;
        $transactionObj->referenceID = $payTransactionItemId;
        $transactionObj->escrowContractAddress = '';
        $transactionObj->createdAt = $date;
        $transactionObj->updatedAt = $date;
        $transactionObj->expiresAt = '';

        $xunWallet = new XunWallet($db);

        $walletTransactionID = $xunWallet->insertUserWalletTransaction($transactionObj);

        if ($walletTransactionID) {
            $updatePayTransactionData = [];
            $updatePayTransactionData["status"] = "pending_refund";
            $updatePayTransactionData["updated_at"] = $date;

            $db->where("id", $payTransactionItemId);
            $db->update("xun_pay_transaction_item", $updatePayTransactionData);
            $urlString = $config["giftCodeUrl"];

            $curlParams = array(
                "command" => "fundOutCompanyWallet",
                "params" => array(
                    "senderAddress" => $payTopupWalletAddress,
                    "receiverAddress" => $receiverAddress,
                    "amount" => $refundAmount,
                    "satoshiAmount" => $satoshiAmount,
                    "walletType" => $walletType,
                    "id" => $walletTransactionID,
                    "transactionToken" => $transactionToken,
                    "addressType" => "Top Up",
                ),
            );

            $curlResponse = $post->curl_post($urlString, $curlParams, 0, 1);
            if ($curlResponse["code"] == 0) {
                // send notification
                // $error_message = $curlResponse_obj->result;
                $error_message = $curlResponse["message_d"];

                $tag = "Pay Wallet Transfer Error";
                $content = "Error: " . $error_message;
                $content .= "\n\nID: " . $walletTransactionID;
                // $content .= "\nGift code: " . $gift_code;
                $content .= "\nAmount: " . $amount;
                $content .= "\nWallet Type: " . $walletType;
                $content .= "\nReceiver Address: " . $receiverAddress;

                $thenux_params = [];
                $thenux_params["tag"] = $tag;
                $thenux_params["message"] = $content;
                $thenux_params["mobile_list"] = array();
                $thenux_result = $general->send_thenux_notification($thenux_params);

                $insert_data = [];
                $insert_data["username"] = $username ? $username : '';
                $insert_data["data"] = json_encode($new_params);
                $insert_data["processed"] = 0;
                $insert_data["created_at"] = date("Y-m-d H:i:s");
                $insert_data["updated_at"] = date("Y-m-d H:i:s");

                $db->insert("xun_marketplace_escrow_error", $insert_data);
            }
            return $curlResponse;
        }

    }

    public function escrowDecision($escrowData)
    {
        global $xunCrypto, $config, $general;
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;
        
        $escrowAgentAddress = $setting->systemSetting["walletEscrowAgentAddress"];
        
        $amount = $escrowData["amount"];
        $walletType = $escrowData["walletType"];
        
        $senderAddress = $escrowData["senderAddress"];
        $receiverAddress = $escrowData["receiverAddress"];
        $escrowContractAddress = $escrowData["escrowContractAddress"];
        $referenceID = $escrowData["referenceID"];
        $walletTransactionID = $escrowData["walletTransactionID"];
        $refundTo = $escrowData["refundTo"];
        
        $satoshiAmount = $xunCrypto->get_satoshi_amount($walletType, $amount);

        $date = date("Y-m-d H:i:s");

        $updateWalletTransaction = [];
        $updateWalletTransaction["status"] = "pending_refund";
        $updateWalletTransaction["updated_at"] = $date;

        $db->where("id", $walletTransactionID);
        $db->update("xun_wallet_transaction", $updateWalletTransaction);
        $urlString = $config["giftCodeUrl"];

        $escrowData["amount"] = (int)$satoshiAmount;

        $curlParams = array(
            "command" => "escrowRefund",
            "params" => array(
                "senderAddress" => $senderAddress,
                "receiverAddress" => $receiverAddress,
                "escrowAgentAddress" => $escrowAgentAddress,
                "escrowContractAddress" => $escrowContractAddress,
                "referenceID" => $referenceID,
                "amount" => $satoshiAmount,
                "walletType" => $walletType,
                "id" => $walletTransactionID,
                "refundTo" => $refundTo,
            ),
        );

        $curlResponse = $post->curl_post($urlString, $curlParams, 0, 1);
        if ($curlResponse["code"] == 0) {
            // send notification
            // $error_message = $curlResponse_obj->result;
            $error_message = $curlResponse["message_d"];

            $tag = "Escrow Refund Transfer Error";
            $content = "Error: " . $error_message;
            $content .= "\n\nID: " . $walletTransactionID;
            // $content .= "\nGift code: " . $gift_code;
            $content .= "\nAmount: " . $amount;
            $content .= "\nWallet Type: " . $walletType;
            $content .= "\nReceiver Address: " . $receiverAddress;

            $erlang_params = [];
            $erlang_params["tag"] = $tag;
            $erlang_params["message"] = $content;
            $erlang_params["mobile_list"] = array();
            $general_result = $general->send_general_notification($erlang_params);

            $insert_data = [];
            $insert_data["username"] = $username ? $username : '';
            $insert_data["data"] = json_encode($new_params);
            $insert_data["processed"] = 0;
            $insert_data["created_at"] = date("Y-m-d H:i:s");
            $insert_data["updated_at"] = date("Y-m-d H:i:s");

            $db->insert("xun_marketplace_escrow_error", $insert_data);
        }
        return $curlResponse;

    }

    public function storyTransactionRefund($transactionData)
    {
        global $xunCrypto, $config, $general;
        $db = $this->db;
        $setting = $this->setting;
        $post = $this->post;

        $walletAddress = $setting->systemSetting["storyWalletAddress"];

        $amount = $transactionData["amount"];
        $walletType = $transactionData["wallet_type"];
        $userID = $transactionData["user_id"];
        $transactionID = $transactionData["id"];

        $xunUserService = new XunUserService($db);
        $userCryptoAddress = $xunUserService->getActiveInternalAddressByUserID($userID);

        if (!$userCryptoAddress) {
            return;
        }
        $satoshiAmount = $xunCrypto->get_satoshi_amount($walletType, $amount);
        $receiverAddress = $userCryptoAddress["address"];

        $txObj = new stdClass();
        $txObj->userID = $userID;
        $txObj->address = $walletAddress;
        $txObj->referenceID = '';

        $transactionToken = $xunUserService->insertCryptoTransactionToken($txObj);

        $transactionStatus = "pending";
        $addressType = "story";

        $date = date("Y-m-d H:i:s");

        $transactionObj = new stdClass();
        $transactionObj->status = $transactionStatus;
        $transactionObj->transactionHash = "";
        $transactionObj->transactionToken = $transactionToken;
        $transactionObj->senderAddress = $walletAddress;
        $transactionObj->recipientAddress = $receiverAddress;
        $transactionObj->userID = $userID;
        $transactionObj->walletType = $walletType;
        $transactionObj->amount = $amount;
        $transactionObj->addressType = $addressType;
        $transactionObj->transactionType = "send";
        $transactionObj->escrow = 0;
        $transactionObj->referenceID = $transactionID;
        $transactionObj->escrowContractAddress = '';
        $transactionObj->createdAt = $date;
        $transactionObj->updatedAt = $date;
        $transactionObj->expiresAt = '';

        $xunWallet = new XunWallet($db);

        $walletTransactionID = $xunWallet->insertUserWalletTransaction($transactionObj);

        if ($walletTransactionID) {
            $updateStoryTransactionData = [];
            $updateStoryTransactionData["status"] = "pending_refund";
            $updateStoryTransactionData["updated_at"] = $date;

            $db->where("id", $transactionData["id"]);
            $db->update("xun_story_transaction", $updateStoryTransactionData);
            $urlString = $config["giftCodeUrl"];

            $curlParams = array(
                "command" => "fundOutCompanyWallet",
                "params" => array(
                    "senderAddress" => $walletAddress,
                    "receiverAddress" => $receiverAddress,
                    "amount" => $amount,
                    "satoshiAmount" => $satoshiAmount,
                    "walletType" => $walletType,
                    "id" => $walletTransactionID,
                    "transactionToken" => $transactionToken,
                    "addressType" => "Story",
                ),
            );

            $curlResponse = $post->curl_post($urlString, $curlParams, 0, 1);
            if ($curlResponse["code"] == 0) {
                // send notification
                // $error_message = $curlResponse_obj->result;
                $error_message = $curlResponse["message_d"];

                $tag = "Story Wallet Transfer Error";
                $content = "Error: " . $error_message;
                $content .= "\n\nID: " . $walletTransactionID;
                // $content .= "\nGift code: " . $gift_code;
                $content .= "\nAmount: " . $amount;
                $content .= "\nWallet Type: " . $walletType;
                $content .= "\nReceiver Address: " . $receiverAddress;

                $thenux_params = [];
                $thenux_params["tag"] = $tag;
                $thenux_params["message"] = $content;
                $thenux_params["mobile_list"] = array();
                $thenux_result = $general->send_thenux_notification($thenux_params);

                $insert_data = [];
                $insert_data["username"] = $username ? $username : '';
                $insert_data["data"] = json_encode($new_params);
                $insert_data["processed"] = 0;
                $insert_data["created_at"] = date("Y-m-d H:i:s");
                $insert_data["updated_at"] = date("Y-m-d H:i:s");

                $db->insert("xun_marketplace_escrow_error", $insert_data);
            }
            return $curlResponse;
        }
    }

    public function createUserServerWallet($userID, $addressType, $walletType){
        /**
         * {
            "code": 1,
            "message": "SUCCESS",
            "message_d": "Success",
            "data": {
                "address": "0xfae8eb4461ea921c7dafdcc40ecfdc9ded674484",
                "externalAddress": null,
                "walletType": "sms123rewards",
                "addressType": "reward",
                "userID": "15140"
            }
        }
         */
        return $this->createPrepaidWalletPost($userID, $addressType, $walletType);
    }

    private function createPrepaidWalletPost($userID, $addressType, $walletType)
    {
        global $config;

        $post = $this->post;

        $command = "createWallet";

        $url_string = $config["giftCodeUrl"];
        $params = array(
            "command" => $command,
            "params" => array(
                "userID" => $userID,
                "walletType" => $walletType,
                "addressType" => $addressType
            ),
        );

        $postReturn = $post->curl_post($url_string, $params, 0, 1);

        return $postReturn;
    }



}
