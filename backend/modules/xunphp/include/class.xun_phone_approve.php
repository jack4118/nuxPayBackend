<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  16/01/2020.
 **/

class XunPhoneApprove
{

    public function __construct($db, $setting, $general, $post, $account)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
        $this->post = $post;
        $this->account = $account;
    }

    // */business/wallet/transaction/phone_approval
    public function request_business_wallet_transaction($params)
    {
        global $xunCrypto, $xunCoins, $xunCurrency, $xunReward;
        $db = $this->db;
        $post = $this->post;
        $general = $this->general;

        $business_id = trim($params["business_id"]);
        $api_key = trim($params["api_key"]);
        $wallet_type = trim($params["currency"]);
        $request_data = $params["data"];
        $reference_id = trim($params["reference_id"]);
        $transaction_method = trim($params["transaction_method"]);
        $date = date("Y-m-d H:i:s");

        if ($business_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Business ID is required");
        }

        if ($api_key == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "api_key is required");
        }

        if ($reference_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "reference_id is required");
        }

        $validate_api_key = $xunCrypto->validate_crypto_api_key($api_key, $business_id);

        if ($validate_api_key !== true) {
            return $validate_api_key;
        }

        if (empty($request_data)) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => "Data is required.",
            );
        }

        $data_limit = 100;
        if (count($request_data) > $data_limit) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => "Maximum $data_limit transaction is allowed.",
            );
        }

        if (!in_array($transaction_method, ["company_pool", "phone_approval"])) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => "Invalid transaction_method.",
            );
        }

        $wallet_type = strtolower($wallet_type);

        // validate wallet_type
        $coinObj->currencyID = $wallet_type;

        $coin_settings = $xunCoins->getCoin($coinObj);
        if (!$coin_settings) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => "Invalid currency.",
            );
        }

        $currency_info = $xunCurrency->get_currency_info($wallet_type);
        $unit_conversion = $currency_info["unit_conversion"];

        $mobile_list = [];
        $address_list = [];
        //  phone_approval allow external address, extra param: address

        //  validate data array
        $accept_external_address = in_array($transaction_method, ["phone_approval"]) ? 1 : 0;

        if ($accept_external_address === 1) {
            foreach ($request_data as &$data) {
                $mobile = trim($data["mobile"]);
                $address = trim($data["address"]);

                $data["mobile"] = $mobile;
                $data["amount"] = trim($data["amount"]);
                $data["address"] = $address;
                $mobile_list[] = $mobile;
                $address_list[] = $address;
            }
        } else {
            foreach ($request_data as &$data) {
                $mobile = trim($data["mobile"]);

                $data["mobile"] = $mobile;
                $data["amount"] = trim($data["amount"]);
                $mobile_list[] = $mobile;
            }
        }

        $xun_user_service = new XunUserService($db);
        $xun_user_arr = [];
        if (!empty($mobile_list)) {

            $xun_user_list = $xun_user_service->getUserByUsername($mobile_list, "id, username, disabled", "user");

            $xun_user_arr = [];
            foreach ($xun_user_list as $v) {
                $xun_user_arr[$v["username"]] = $v;
            }
        }

        $external_address_arr = [];
        if (!empty($address_list)) {
            $db->where("a.external_address", $address_list, "IN");
            $db->join("xun_crypto_user_address b", "a.internal_address=b.address", "INNER");
            $db->join("xun_user c", "c.id=b.user_id", "INNER");
            $db->groupBy("a.external_address");
            $external_address_arr = $db->map("external_address")->ArrayBuilder()->get("xun_crypto_external_address a", null, "a.id, a.external_address, a.internal_address, b.user_id, c.username, c.type, c.nickname");
        }

        $batch_id = $db->getNewID();

        $unregistered_user_list = [];
        $successful_request_list = [];
        // $successful_address_list = [];
        $failed_request_list = [];

        $insert_details_data_arr = [];

        $total_amount = 0;

        foreach ($request_data as $req_data) {
            $amount = $req_data["amount"];
            $mobile = $req_data["mobile"];
            $address = $req_data["address"];
            unset($error_message);

            $user_data = $xun_user_arr[$mobile];

            if ($accept_external_address) {
                //  check if there's only mobile or address, reject if there's two or none
                if ($address != '' && $mobile != '') {
                    $failed_request_list[] = array(
                        "mobile" => $mobile,
                        "address" => $address,
                        "error" => "Either mobile or address is allowed.",
                    );
                    continue;
                } else if ($address == '' && $mobile == '') {
                    $failed_request_list[] = array(
                        "mobile" => $mobile,
                        "address" => $address,
                        "error" => "Invalid mobile or address",
                    );
                    continue;
                } 
            } else {
                if ($mobile == '') {
                    $unregistered_user_list[] = $mobile;
                    continue;
                }
            }

            if (!empty($mobile) && (!$user_data || $user_data["disabled"] === 1)) {
                $unregistered_user_list[] = $mobile;
                continue;
            } else {
                if ($amount != '' && !is_numeric($amount) || $amount <= 0) {
                    $error_message = "Invalid amount.";

                    $failed_request = array(
                        "error" => $error_message,
                    );

                    if (!empty($mobile)) {
                        $failed_request["mobile"] = $mobile;
                    } else {
                        $failed_request["address"] = $address;
                    }
                    $failed_request_list[] = $failed_request;
                    continue;
                } else if (!$general->checkDecimalPlaces($amount, $unit_conversion)) {
                    $error_message = "Too many decimal places.";

                    $failed_request = array(
                        "error" => $error_message,
                    );

                    if (!empty($mobile)) {
                        $failed_request["mobile"] = $mobile;
                    } else {
                        $failed_request["address"] = $address;
                    }
                    $failed_request_list[] = $failed_request;
                    continue;
                } else {
                    if (!empty($mobile) && in_array($mobile, $successful_request_list)) {
                        $error_message = "Mobile number cannot be duplicated.";

                        $failed_request_list[] = array(
                            "mobile" => $mobile,
                            "error" => $error_message,
                        );

                        continue;
                    } else if (!empty($address) && in_array($address, $successful_request_list)) {
                        $error_message = "Address cannot be duplicated.";

                        $failed_request_list[] = array(
                            "address" => $address,
                            "error" => $error_message,
                        );
                        continue;
                    }

                    if (!empty($mobile)) {
                        $db->where("user_id", $user_data["id"]);
                        $db->where("address_type", "personal");
                        $db->where("active", 1);
                        $user_address = $db->getOne("xun_crypto_user_address");
                        if (!$user_address) {
                            $error_message = "User does not have a wallet.";

                            $failed_request_list[] = array(
                                "mobile" => $mobile,
                                "error" => $error_message,
                            );

                            continue;
                        }

                        $successful_request_list[] = $mobile;
                        unset($insert_data);
                        $insert_data = array(
                            // "request_id" => $request_id,
                            "user_id" => $user_data['id'],
                            "username" => $mobile,
                            "amount" => $amount,
                            "address" => $user_address["address"],
                            "crypto_user_address_id" => $user_address["id"],
                        );
                    } else if (!empty($address)) {
                        $user_external_address_data = $external_address_arr[$address];

                        $successful_request_list[] = $address;
                        unset($insert_data);
                        $insert_data = array(
                            "user_id" => $user_external_address_data ? $user_external_address_data["user_id"] : "",
                            "username" => "",
                            "amount" => $amount,
                            "external_address" => $address,
                        );

                    } else {
                        continue;

                    }

                    $insert_details_data_arr[] = $insert_data;
                    $total_amount = bcadd((string) $total_amount, (string) $amount, 18);
                }
            }
        }

        if ($transaction_method == "phone_approval") {

            $insert_request_data = array(
                "business_id" => $business_id,
                "business_reference_id" => $reference_id,
                "batch_id" => $batch_id,
                "wallet_type" => $wallet_type,
                "status" => "pending",
                "created_at" => $date,
                "updated_at" => $date,
            );

            $request_id = $db->insert("xun_business_phone_approve_request", $insert_request_data);

            if (!empty($insert_details_data_arr)) {
                try {
                    foreach ($insert_details_data_arr as &$value) {
                        $value["request_id"] = $request_id;
                        $value["status"] = "pending";
                        $value["created_at"] = $date;
                        $value["updated_at"] = $date;
                        $value["address"] = $value["external_address"] ?: "";
                        // unset($value["address"]);
                        unset($value["external_address"]);
                        unset($value["crypto_user_address_id"]);
                    }
                    $row_ids = $db->insertMulti("xun_business_phone_approve_request_detail", $insert_details_data_arr);

                    if (!$row_ids) {
                        throw new Exception($db->getLastError());
                    }

                    //  call erlang API

                    $xun_business_service = new XunBusinessService($db);
                    $business_data = $xun_business_service->getBusinessDetails($business_id);

                    $business_name = $business_data["name"];
                    $business_owner_mobile = $business_data["main_mobile"];

                    $erlang_params = array(
                        "business_id" => $business_id,
                        "business_name" => $business_name,
                        "username" => $business_owner_mobile,
                        "batch_id" => (string) $batch_id,
                    );

                    $url_string = "business/phone_approval/request";
                    $erlang_return = $post->curl_post($url_string, $erlang_params);
                } catch (Exception $e) {
                    $update_data = [];
                    $update_data["status"] = "failed";

                    $db->where("id", $request_id);
                    $db->update("xun_business_phone_approve_request", $update_data);
                    return array(
                        "code" => 0,
                        "message" => "FAILED",
                        "message_d" => "Something went wrong. Please try again.",
                        "error_message" => $e->getMessage(),
                    );
                }
            } else {
                $update_data = [];
                $update_data["status"] = "failed";

                $db->where("id", $request_id);
                $db->update("xun_business_phone_approve_request", $update_data);
            }
        } else if ($transaction_method == "company_pool") {
            $reward_params = array(
                "business_id" => $business_id,
                "wallet_type" => $wallet_type,
                "request_arr" => $insert_details_data_arr,
                "batch_id" => $batch_id,
                "reference_id" => $reference_id,
                "total_amount" => $total_amount,
            );

            $reward_res = $xunReward->send_reward_external_api($reward_params);
            if (isset($reward_res["code"]) && $reward_res["code"] == 0) {
                return $reward_res;
            }

            $reward_res_data = $reward_res["data"];
            $successful_request_list = $reward_res_data["successful_request_list"];
            $failed_reward_list = $reward_res_data["failed_request_list"];
            $failed_request_list = array_merge($failed_request_list, $failed_reward_list);
        }

        $return_data = [];
        $return_data["batch_id"] = $batch_id;
        $return_data["reference_id"] = $reference_id;
        $return_data["successful_request"] = $successful_request_list;
        $return_data["failed_request"] = $failed_request_list;
        $return_data["unregistered_users"] = $unregistered_user_list;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success",
            "data" => $return_data,
        );
    }

    public function app_get_request_details($params)
    {
        $db = $this->db;
        $general = $this->general;

        $username = trim($params["username"]);
        $batch_id = trim($params["batch_id"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username is required.");
        }

        if ($batch_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Batch ID is required.");
        }

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist");
        }

        $xun_phone_approve_service = new XunPhoneApproveService($db);

        $obj = new stdClass();
        $obj->batchId = $batch_id;
        $request_data = $xun_phone_approve_service->getRequest($obj);

        if (!$request_data) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => "Invalid batch ID",
            );
        }

        $wallet_type = $request_data["wallet_type"];
        $business_id = $request_data["business_id"];
        $request_at = $request_data["created_at"];

        $xun_business_service = new XunBusinessService($db);
        $business_data = $xun_business_service->getBusinessDetails($business_id);
        $request_id = $request_data["id"];

        $obj2 = new stdClass();
        $obj2->requestId = $request_id;

        $request_details = $xun_phone_approve_service->getRequestDetails($obj2);

        if (!empty($request_details)) {
            $user_id_arr = array_column($request_details, "user_id");

            $columns = "a.id, a.username, a.nickname, b.picture_url";
            $xun_user_arr = $xun_user_service->getUserDetailsByID($user_id_arr, $columns);

            $xun_crypto_user_address_arr = $xun_user_service->getActiveAddressDetailsByUserID($user_id_arr);

            $total_amount = 0;
            foreach ($request_details as $request_data) {
                $amount = $request_data["amount"];

                $user_id = $request_data["user_id"];
                $external_address = $request_data["address"];
                $user_data = $xun_user_arr[$user_id];
                $nickname = $user_data["nickname"];
                $picture_url = $user_data["picture_url"];
                $username = $user_data["username"];

                $user_address = $xun_crypto_user_address_arr[$user_id];
                $internal_address = $user_address["address"];

                $total_amount = bcadd((string) $total_amount, (string) $amount, 18);

                $transaction_type = $internal_address ? "internal_transfer" : "external_transfer";
                $data = array(
                    "amount" => $amount,
                    "username" => $username ?: "",
                    "nickname" => $nickname ?: "",
                    "picture_url" => $picture_url ? $picture_url : "",
                    "internal_address" => $internal_address ?: "",
                    "external_address" => $external_address,
                    "transaction_type" => $transaction_type
                );

                $transaction_data_list[] = $data;
            }
        }

        $return_data = [];
        $return_data["business_id"] = $business_id;
        $return_data["business_name"] = $business_data["name"];
        $return_data["wallet_type"] = $wallet_type;
        $return_data["total_amount"] = $total_amount;
        $return_data["request_at"] = $general->formatDateTimeToIsoFormat($request_at);
        $return_data["transaction_data"] = $transaction_data_list ? $transaction_data_list : [];

        return array(
            "code" => 1,
            "message" => "Success",
            "message_d" => "Transaction Details",
            "data" => $return_data,
        );

    }

    public function app_update_request_status($params)
    {
        $db = $this->db;

        $username = trim($params["username"]);
        $batch_id = trim($params["batch_id"]);
        $action = trim($params["action"]);
        $message = trim($params["message"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Username is required.");
        }

        if ($batch_id == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Batch ID is required.");
        }

        if ($action == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "Action is required.");
        }

        $action = strtolower($action);

        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "User does not exist");
        }

        if (!in_array($action, ["approve", "reject", "failed"])) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => "Invalid action",
            );
        }

        /**
         * TODO:    check user permission
         */

        $xun_phone_approve_service = new XunPhoneApproveService($db);

        $obj = new stdClass();
        $obj->batchId = $batch_id;
        $request_data = $xun_phone_approve_service->getRequest($obj);

        if (!$request_data) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => "Invalid batch ID",
            );
        }

        $business_id = $request_data["business_id"];
        $request_status = $request_data["status"];

        if ($request_status != "pending") {
            $return_data = [];
            $return_data["status"] = $request_status;
            return array(
                "code" => 1,
                "message" => "SUCCESS",
                "message_d" => "Transaction " . ucfirst($request_status),
                "data" => $return_data,
            );
        }

        $request_obj = new stdClass();
        $request_obj->id = $request_data["id"];
        $request_obj->status = $action;
        $request_obj->message = $message;

        try {
            $update_val = $xun_phone_approve_service->updateRequestStatus($request_obj);
        } catch (Exception $e) {
            return array(
                "code" => 0,
                "message" => "FAILED",
                "message_d" => "Something went wrong. Please try again.",
                "error_message" => $e->getMessage(),
            );
        }

        //  callback to business
        $callback_params = array(
            "batch_id" => $batch_id,
            "status" => $action,
            "message" => $message,
        );

        $this->request_response_callback($business_id, $callback_params);

        $return_data = [];
        $return_data["status"] = $action;

        return array(
            "code" => 1,
            "message" => "SUCCESS",
            "message_d" => "Transaction " . ucfirst($action),
            "data" => $return_data,
        );
    }

    private function request_response_callback($business_id, $callback_params)
    {
        $db = $this->db;
        $post = $this->post;

        $xun_business_service = new XunBusinessService($db);
        $columns = "id, wallet_callback_url";
        $xun_user = $xun_business_service->getUserByID($business_id);

        $wallet_callback_url = $xun_user["wallet_callback_url"];
        if ($xun_user && !empty($wallet_callback_url)) {
            //  post to business' wallet_callback_url
            $callback_command = "businessWalletTransactionResponseCallback";
            // $post_result = $post->curl_crypto($callback_command, $callback_params, 0, $wallet_callback_url);

            $curl_params = array(
                "command" => $callback_command,
                "params" => $callback_params,
            );
            $post_result = $post->curl_post($wallet_callback_url, $curl_params, 0);
        }

        return;
    }

    public function transaction_signing_update($wallet_transaction_params)
    {
        $db = $this->db;

        $batch_id = $wallet_transaction_params["batch_id"];
        $status = $wallet_transaction_params["status"];
        $receiver_username = $wallet_transaction_params["receiver_username"];
        $receiver_user_id = $wallet_transaction_params["receiver_user_id"];
        $message = $wallet_transaction_params["message"];
        $wallet_transaction_id = $wallet_transaction_params["wallet_transaction_id"];
        $transaction_hash = $wallet_transaction_params["transaction_hash"];
        $external_address = $wallet_transaction_params["external_address"];

        $date = date("Y-m-d H:i:s");

        //  update request table
        $xun_phone_approve_service = new XunPhoneApproveService($db);

        $obj = new stdClass();
        $obj->batchId = $batch_id;
        $obj->username = $receiver_username;
        $obj->userId = $receiver_user_id;
        $obj->walletTransactionId = $wallet_transaction_id;
        $obj->externalAddress = $external_address;

        $columns = "a.batch_id, a.business_id, b.*";
        $request_details = $xun_phone_approve_service->getUserRequestDetails($obj, $columns);

        if (!$request_details) {
            throw new Exception("Invalid batch ID or receiver username.");
        }
        if ($request_details["status"] != "pending") {
            return;
        }

        //  update status
        $update_data = [];
        $update_data["status"] = $status;
        $update_data["message"] = $message;
        $update_data["updated_at"] = $date;

        if ($wallet_transaction_id) {
            $update_data["wallet_transaction_id"] = $wallet_transaction_id;
        }

        $db->where("id", $request_details["id"]);
        $db->update("xun_business_phone_approve_request_detail", $update_data);

        $transaction_receiver_username = $request_details["username"];
        $transaction_address = $request_details["address"];
        $business_id = $request_details["business_id"];
        $callback_params = array(
            "batch_id" => $batch_id,
            "mobile" => $transaction_receiver_username,
            "address" => $transaction_address,
            "status" => $status,
            "message" => $message,
            "transaction_hash" => $transaction_hash,
        );

        $this->request_transaction_callback($business_id, $callback_params);
    }

    public function request_transaction_callback($business_id, $callback_params)
    {
        $db = $this->db;
        $post = $this->post;

        $xun_business_service = new XunBusinessService($db);
        $columns = "id, wallet_callback_url";
        $xun_user = $xun_business_service->getUserByID($business_id);

        $wallet_callback_url = $xun_user["wallet_callback_url"];
        if ($xun_user && !empty($wallet_callback_url)) {
            //  post to business' wallet_callback_url
            $callback_command = "businessWalletTransactionSigningCallback";
            // $post_result = $post->curl_crypto($callback_command, $callback_params, 0, $wallet_callback_url);

            $curl_params = array(
                "command" => $callback_command,
                "params" => $callback_params,
            );
            $post_result = $post->curl_post($wallet_callback_url, $curl_params, 0);
        }

        return;
    }
}
