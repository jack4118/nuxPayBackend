<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the email templates.
 * Date  29/06/2017.
 **/
class XunUser
{

    public function __construct($db, $post, $general, $whoisserver)
    {
        $this->db = $db;
        $this->post = $post;
        $this->general = $general;
        $this->whoisserver = $whoisserver;
    }

    public function register_verifycode_get($params)
    {
        $db = $this->db;
        $general = $this->general;
        global $config;
        global $xunSms;
        global $setting;
        global $xunEmail;

        $req_type = trim($params["req_type"]);
        $email = trim($params["email"]);
        $mobile = trim($params["mobile"]);
        $language = trim($params["language"]);
        $device = trim($params["device"]);
        $ip = trim($params["ip"]);
        $company_name = trim($params["company_name"]);
        $request_type = trim($params['request_type']);
        $nuxpay_user_type = trim($params['user_type']) ? trim($params['user_type']) : 'business';
        
        $test_account = $config["test_account"];
        $test_account_code = $config["test_account_code"];

        $user_country_info_arr = $this->get_user_country_info([$mobile]);

        $user_country_info = $user_country_info_arr[$mobile];
        $user_country = $user_country_info["name"];

        if($req_type=="email") {

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $tag = "Sign Up with Invalid Email Address";

                $msg = "IP: " . $ip . "\n";
                //$msg .= "Country: " . $user_country . "\n";
                $msg .= "Device: " . $device . "\n";

                $msg .= "\nEmail address entered: " . $email . "\n";
                $msg .= "Message: Please enter a valid email address.\n";
                $msg .= "Time: " . date("Y-m-d H:i:s") . "\n";

                $thenux_params["tag"]         = $tag;
                $thenux_params["message"]     = $msg;
                $thenux_params["mobile_list"] = array();

                //PENDING0818 - ok
                $thenux_result                  = $general->send_thenux_notification($thenux_params);

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00543') /*Please enter a valid email address.*/, "errorCode" => -102);
            }

            $is_test_account = false;
            $user_info = $this->get_user_device_info($params, $email, array());

        } else {

            // validate mobile
            $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
            if ($mobileNumberInfo["isValid"] == 0 || $mobile == '') {
                $tag = "Sign Up with Invalid Phone Number";

                $msg = "IP: " . $ip . "\n";
                //$msg .= "Country: " . $user_country . "\n";
                $msg .= "Device: " . $device . "\n";

                $msg .= "\nPhone number entered: " . $mobile . "\n";
                $msg .= "Message: Please enter a valid mobile number.\n";
                $msg .= "Time: " . date("Y-m-d H:i:s") . "\n";

                $thenux_params["tag"]         = $tag;
                $thenux_params["message"]     = $msg;
                $thenux_params["mobile_list"] = array();

                //PENDING0818 - ok
                $thenux_result                  = $general->send_thenux_notification($thenux_params);

                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/, "errorCode" => -102);
            }

            $mobile_region_code = strtolower($mobileNumberInfo["regionCode"]);
            $mobile = str_replace("-", "", $mobileNumberInfo["phone"]);
            $is_test_account = in_array($mobile, $test_account) ? true : false;

            $user_info = $this->get_user_device_info($params, $mobile, $mobileNumberInfo);
        }
        

        $now = date("Y-m-d H:i:s");

        if($req_type=="email") {
            $db->where("email", $email);
        } else {
            $db->where("mobile", $mobile);
        }

        $db->where('type', $nuxpay_user_type);//kpong
        $db->where('source', $company_name);//kpong
        $db->where("is_valid", 1);
        $db->where("request_at", '0', '>');
        $db->orderBy("request_at", "DESC");

        $latest_xun_user_verification = $db->getOne("xun_user_verification");

        // check if code has been verified
        $new_code = true;
        if ($latest_xun_user_verification) {
            $latest_verification_code = $latest_xun_user_verification["verification_code"];
            
            if($req_type=="email") {
                $db->where("email", $email);
            } else {
                $db->where("mobile", $mobile);
            }

            $db->where("verification_code", $latest_verification_code);
            $db->where("is_valid", 1);
            $db->where("is_verified", 1);
            $db->where("verify_at", 0, '>');
            $db->where('type', $nuxpay_user_type);
            $db->where('source', $company_name);//kpong

            $code_verification = $db->getOne("xun_user_verification");

            //  if latest code has been verified, generate new code
            $new_code = $code_verification ? true : false;
        }

        $db->where("is_verified", 1);
        $db->where("is_valid", 1);
        $db->where('type', $nuxpay_user_type);
        $db->where('source', $company_name);//kpong

        if($req_type=="email") {
            $db->where("email", $email);
        } else {
            $db->where("mobile", $mobile);
        }
        $max_id = $db->getValue("xun_user_verification", "MAX(id)");

        if($max_id != NULL){
            $db->where("id", $max_id, ">");
        }
        $db->where("request_at", "NULL", "!=");
        $db->where('type', $nuxpay_user_type);
        $db->where('source', $company_name);//kpong

        if($req_type=="email") {
            $db->where("email", $email);
        } else {
            $db->where("mobile", $mobile);
        }
        $request_count = $db->get("xun_user_verification", null, "request_at");
        $sending_count = count($request_count) + 1; 


        $db->where('ip', "%$ip%", 'LIKE');
        $db->where('ABS(TIMESTAMPDIFF(MINUTE, created_at, NOW()))','10','<=');
        $Recordverification = $db->get("xun_user_verification");
        $Count_Record = count($Recordverification);
        if ($Count_Record>20)
        {
            return array("code" => 0, "message" => "FAILED", "message_d" => "Operation too frequent, pls try again later");
        }

        if ($new_code) {
            // generate new code
            // number of attempts =  sizeof(array) % 7
            if($req_type=="email") {
                $verification_code_result = $this->generate_mobile_verification_code($email, null, null, $req_type, $company_name, $nuxpay_user_type, $ip);
            } else {
                $verification_code_result = $this->generate_mobile_verification_code($mobile, null, null, $req_type, $company_name, $nuxpay_user_type, $ip);
            }
            
            $number_of_attempts = 1;
            $timeout = $this->verification_get_timeout($number_of_attempts);

        } else {
            // if record is % 7 = 5, return errorCode -100

            if($req_type=="email") {
                $db->where("email", $email);
            } else {
                $db->where("mobile", $mobile);
            }
            $db->where("verification_code", $latest_verification_code);
            $db->where("is_valid", 1);
            $db->where("request_at", '0', '>');
            $db->where('type', $nuxpay_user_type);
            $db->where('source', $company_name);//kpong
            $db->orderBy("request_at", "DESC");

            $xun_user_verification = $db->get("xun_user_verification");

            if($req_type=="email") {
                $db->where("email", $email);
            } else {
                $db->where("mobile", $mobile);
            }
            $db->where("is_valid", 1);
            $db->where("expires_at", $now, ">=");
            $db->where("request_at", '0', '>');
            $db->where('type', $nuxpay_user_type);
            $db->where('source', $company_name);//kpong
            $db->orderBy("request_at", "DESC");
            $xun_user_verification = $db->get("xun_user_verification");
            $record_size = sizeof($xun_user_verification);


            $max_attempts = $this->maximum_request_verification_code_attempt();
            $number_of_attempts = $record_size % $max_attempts;

            $timeout = $this->verification_get_timeout($number_of_attempts);
            $latest_request = $xun_user_verification[0];

            $latest_request_at = $latest_request["request_at"];

            $timeout_time = date("Y-m-d H:i:s", strtotime('+' . $timeout . ' seconds', strtotime($latest_request_at)));

            if ($now < $timeout_time) {

                $error_message = 'The code you entered is incorrect. Please try again.';//$this->get_translation_message('E00137') /*You have requested the verification code previously. Please try again in %%timeout%% seconds.*/;
                //$error_message = str_replace("%%timeout%%", $timeout, $error_message);
                
                //PENDING0818 - ok
                if($req_type=="email") {
                    $db->where("email", $email);
                } else {
                    $db->where("mobile", $mobile);
                }
                $db->where('source', $company_name);
                $db->where('type', $nuxpay_user_type);
                $db->where("verification_code", $latest_verification_code);
                $verification_records = $db->get("xun_user_verification");
                $lq = $db->getLastQuery();
                $total_verification_records = count($verification_records);

                if($total_verification_records < 3) {

                    $now_ts = strtotime($now);
                    $timeout_ts = strtotime($timeout_time);

                    $timeout_left = $timeout_ts - $now_ts;

                    $error_message = $this->get_translation_message('E00137') /*You have requested the verification code previously. Please try again in %%timeout%% seconds.*/;
                    $error_message = str_replace("%%timeout%%", $timeout, $error_message);
                    
                    //PENDING0818 - ok
                    if($req_type=="email") {
                        $this->send_request_verification_code_message($email, $ip, $sending_count, null, "FAILED", $error_message, $user_info, null, null, "email", $nuxpay_user_type);
                    } else {
                        $this->send_request_verification_code_message($mobile, $ip, $sending_count, null, "FAILED", $error_message, $user_info, null, null, "mobile", $nuxpay_user_type);
                    }
                    
                    // store failed attempts
                    $this->store_failed_get_verification_code($latest_request, $user_info, $error_message, null, $company_name, $nuxpay_user_type, $ip);
                    // ($latest_request, $user_info, $error_message, null);

                    $show_help_message = ($record_size >= 5) ? true : false;

                    return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "timeout" => $timeout_left, "show_help_message" => $show_help_message, "help_message" => $this->get_translation_message('E00138') /*If you need help, please contact us.*/, "errorCode" => -101, 'lq'=>$lq);
                }

            }

            if($req_type=="email") {
                $verification_code_result = $this->generate_mobile_verification_code($email, null, null, "email", $company_name, $nuxpay_user_type, $ip);
            } else {
                $verification_code_result = $this->generate_mobile_verification_code($mobile, null, null, "mobile", $company_name, $nuxpay_user_type, $ip);
            }

            $number_of_attempts = ($record_size + 1) % $max_attempts;
            $timeout = $this->verification_get_timeout($number_of_attempts);
        }

        $verification_row_id = $verification_code_result["row_id"];

        if (!$verification_row_id) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00141') /*Internal server error. Please try again later.*/);
        }

        // send to sms gateway
        $sms_mobile = str_replace("+", "", $mobile);

        $verification_code = $verification_code_result["verification_code"];

        if($is_test_account === false){

            if($req_type=="email") {

                $verification_code_sms_message = $verification_code;

                if($request_type=="request_fund" || $request_type == "send_fund") {
                    $emailDetail = $xunEmail->getAutoRegistrationEmail($company_name, $verification_code);
                } else if($request_type == "reseller_reset_password"){
                    $emailDetail = $xunEmail->getResellerResetPasswordEmail($company_name, $verification_code);
                } else if($request_type == "reseller_request_username"){
                    $emailDetail = $xunEmail->getResellerRequestUsernameEmail($company_name, $verification_code);
                } else if ($request_type == "reseller_request_commission_withdrawal"){
                    $emailDetail = $xunEmail->getResellerRequestCommissionWithdrawalEmail($company_name, $verification_code);
                } else {
                    $emailDetail = $xunEmail->getRegistrationEmail($company_name, $verification_code);
                }
                
                $emailParams["subject"] = $emailDetail['emailSubject'];
                $emailParams["body"] = $emailDetail['html'];
                $emailParams["recipients"] = array($email);
                $emailParams["emailFromName"] = $emailDetail['emailFromName'];
                $emailParams["emailAddress"] = $emailDetail['emailAddress'];
                $emailParams["emailPassword"] = $emailDetail['emailPassword'];
                $msg = $general->sendEmail($emailParams);

            } else {
                $db->where('source', $company_name);
                $site = $db->getOne('site');
                $Prefix = $site['otp_prefix'];

                if ($Prefix != ""){
                    $company_name = $Prefix;
                }

                $verification_code_sms_message = $this->generate_mobile_verification_code_sms_message($mobile, $verification_code, $company_name, $mobile_region_code, $request_type);
                $user_type = $user_info["user_type"];
                $newParams["recipients"] = $sms_mobile;
                $newParams["message"] = $verification_code_sms_message;
                $newParams["ip"] = $ip;
                $newParams["country"] = $user_country;
                $newParams["device"] = $device;
                $newParams["type"] = $user_type;
                $newParams["sending_count"] = $sending_count;
                $newParams["companyName"] = $company_name;
                $sms_result = $xunSms->send_sms($newParams);
                $msg = $sms_result["msg"];
            }
            
        }else{
            //  send notification
            $notification_message .= "Login Time: " . date("Y-m-d H:i:s");

            $thenux_params["tag"] = "Test Account Login";
            $thenux_params["message"] = $notification_message;
            $thenux_params["mobile_list"] = array();
            
            //PENDING0818 - ok
            $thenux_result = $general->send_thenux_notification($thenux_params);
        }

        $company_name = $setting->systemSetting["companyName"];
        $company_name = $params["company_name"] ? trim($params["company_name"]) : $company_name;

        $translations_message = $this->get_translation_message('B00014') /*%%companyName%% verification code has been sent.*/;
        $return_message = str_replace("%%companyName%%", $company_name, $translations_message);

        $this->update_user_verification_info($verification_row_id, $user_info, $return_message, $verification_code_sms_message);
/////////////////
        //PENDING0818 - ok
        if($req_type=="email") {
            $thenux_result = $this->send_request_verification_code_message($email, $ip, $sending_count, $verification_code, "SUCCESS", $return_message, $user_info, $verification_code_sms_message, $company_name, $req_type, $nuxpay_user_type);
        }
        else{
            $thenux_result = $this->send_request_verification_code_message($mobile, $ip, $sending_count, $verification_code, "SUCCESS", $return_message, $user_info, $verification_code_sms_message, $company_name, $req_type, $nuxpay_user_type);
        }

        $show_help_message = ($record_size >= 5) ? true : false;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $return_message, "timeout" => $timeout, "show_help_message" => $show_help_message, "help_message" => $this->get_translation_message('E00138') /*If you need help, please contact us.*/);

    }

    public function register_verifycode_verify($params, $ip = null, $user_agent = null)
    {
        global $xunXmpp, $config, $xunBusinessPartner;
        $db = $this->db;
        $general = $this->general;

        $verify_code = trim($params["verify_code"]);
        $mobile = trim($params["mobile"]);
        $ip = $ip ? $ip : trim($params["ip"]);
        $device_os = $user_agent ? $user_agent : trim($params["device"]);
        $companyName = trim($params["companyName"]);
        $grouping = $companyName == 'NuxPay' ? 'thenux_pay' : null;
        $content = trim($params["content"]);
        $from_nuxpay_admin = trim($params['from_nuxpay_admin']) ? trim($params['from_nuxpay_admin']) : '';
        $req_type = trim($params['req_type']);
        $nuxpay_user_type = trim($params['nuxpay_user_type']) ? trim($params['nuxpay_user_type']) : 'business'; //business/reseller/distrbutor
        $request_type = trim($params['request_type']);

        if($from_nuxpay_admin != 1){
            if ($verify_code == '') {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00142') /*Verify code cannot be empty*/);
            };

        }
      
        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        };

        $test_account = $config["test_account"];
        $test_account_code = $config["test_account_code"];

        $user_info = $this->get_user_device_info($params, $mobile, $mobileNumberInfo);
        $user_type = $user_info["user_type"];

        if($from_nuxpay_admin != 1){
            $verify_code_return = $this->verify_code($mobile, $verify_code, $ip, $device_os, $user_type, $companyName, $req_type, $nuxpay_user_type);
            if ($verify_code_return["code"] === 0) {
                return $verify_code_return;
            }
    
        }
      
        $request_data = $verify_code_return["request_arr"];
        //$device = $request_data["phone_model"];
        //$device_os = $request_data["device_os"];
        $formatted_mobile = $verify_code_return["formatted_mobile"] ? $verify_code_return['formatted_mobile'] : $mobile;

        if($req_type == ""){
            // register user to xun_user for now user
            $add_xun_user_result = $this->add_xun_user($formatted_mobile);
            $is_new_user = $add_xun_user_result["is_new_user"];
            $user_type = $add_xun_user_result["user_type"];
            $user_id = $add_xun_user_result["user_id"];

            $match = "No";

            if($is_new_user == 1){
                // check the ip if it exists in the tracking table
                $date = date("Y-m-d H:i:s");
                $date_duration = '1 day';
                $start_date = date("Y-m-d H:i:s", strtotime("-$date_duration", strtotime($date)));

                $db->where("ip", $ip);
                $db->where("created_at", $start_date, ">");
                $download_link_data = $db->getOne("xun_download_link_tracking", "device, os, ip");

                $match = $download_link_data ? "Yes" : $match;
                if($download_link_data){
                    $verification_row_id = $verify_code_return["row_id"];
                    $match = "Yes";

                    $update_match = array(
                        "match" => 1
                    );

                    $db->where("id", $verification_row_id);
                    $db->update("xun_user_verification", $update_match);
                }

                //  update business partner side of new user registration
                //$xunBusinessPartner->update_registered_user($formatted_mobile, $user_id, $date);
            }

            $status = $db->getValue("xun_user_verification", "status");
                    

            // send notification if is new signup
            $user_country_info_arr = $this->get_user_country_info([$mobile]);

            $user_country_info = $user_country_info_arr[$mobile];
            $user_country = $user_country_info["name"];
            //$message = "Existing Username: " . $existingUsername . "\n";
            //$message .= "Current Username: " . $currentUsername . "\n";
            if($nuxpay_user_type == 'business'){
                if($grouping == 'thenux_pay'){
                    $nickname = $params["nickname"];
                    $type = 'nuxpay';
                    // $db->where('username', $mobile);
                    // $db->where('register_site', $type);
                    // $xun_user = $db->getOne('xun_user');
                    // $nickname = $xun_user['nickname'];
        
                    $message .= "Username: " .$nickname."\n";
                    $message .= "Phone number: " . $mobile . "\n";
                    $message .= "IP: " . $ip . "\n";
                    $message .= "Country: " . $user_country . "\n";
                    $message .= "Device: " . $device_os . "\n";
                    $message .= "Type of User: " . $user_type . "\n";
                    $message .= "Keyword: " . $content ."\n";
                    //$message .= "Match: " . $match . "\n";
                    $message .= "Status: " . strtoupper($status) . "\n";
                    $message .= "Time: " . date("Y-m-d H:i:s");
                }
                else{
                    $message .= "Phone number: " . $mobile . "\n";
                    $message .= "IP: " . $ip . "\n";
                    $message .= "Country: " . $user_country . "\n";
                    $message .= "Device: " . $device_os . "\n";
                    $message .= "Type of User: " . $user_type . "\n";
                    //$message .= "Match: " . $match . "\n";
                    $message .= "Status: " . strtoupper($status) . "\n";
                    $message .= "Time: " . date("Y-m-d H:i:s");
        
                }
                
                $thenux_params["tag"] = "New Sign Up";
                $thenux_params["message"] = $message;
                $thenux_params["mobile_list"] = array();
                $thenux_result = $general->send_thenux_notification($thenux_params, $grouping);
        
                if($grouping== null){
                    $update_language = array(
                        "language" => 1,
                        "updated_at" => date("Y-m-d H:i:s"),
                    );
                    $db->where('username', $mobile);
                    $db->where('type', 'user');
                    $updated = $db->update('xun_user', $update_language);
                }
            
            }
        }
        
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00074') /*Verification code verified*/, "password" => "", "isRegistered" => 1, "user" => array("username" => $formatted_mobile), "new_user" => $is_new_user);
    }

    public function get_user_device_info($params, $mobile, $mobileNumberInfo)
    {
        $db = $this->db;

        $device = trim($params["device"]);
        $os_version = trim($params["os_version"]);
        $phone_model = trim($params["phone_model"]);
        $change_number = trim($params["change_number"]);
        $source = trim($params["company_name"]);

        if (!$mobileNumberInfo["countryName"]) {
            $db->where("country_code", $mobileNumberInfo["countryCode"]);
            $country_info = $db->getOne("country");
            $country = $country_info ? $country_info["name"] : "";
        } else {
            $country = $mobileNumberInfo["countryName"];
        }

        if (!$change_number) {
            $db->where("(username='".$mobile."' OR email='".$mobile."') ");
            $db->where("register_site", $source);
            $db->where("type", "business");
            $xun_user = $db->getOne("xun_user");

            $user_type = ($xun_user && $xun_user["disabled"] === 0) ? "Return" : "New";
        } else {
            $user_type = "Change Number";
        }

        $user_info = [];
        $user_info["device"] = $device;
        $user_info["os_version"] = $os_version;
        $user_info["phone_model"] = $phone_model;
        $user_info["country"] = $country;
        $user_info["user_type"] = $user_type;
        return $user_info;
    }

    public function generate_mobile_verification_code($mobile, $verification_code = null, $expires_at = null, $req_type, $companyName, $user_type, $ip)
    {
        global $config;
        $db = $this->db;
        $general = $this->general;
        $post = $this->post;

        $test_account = $config["test_account"];
        $test_account_code = $config["test_account_code"];

        $is_test_account = in_array($mobile, $test_account);
        $verification_code = $is_test_account ? $test_account_code : $verification_code;
        
        // generate verification code => verification code is unique
        if (!$verification_code) {
            $code_exists = false;
            do {
                $verification_code = $general->generateRandomNumber(5);

                if($req_type=="email") {
                    $db->where("email", $mobile);
                } else {
                    $db->where("mobile", $mobile);
                }
                
                $db->where("verification_code", $verification_code);

                $code_verification = $db->getOne("xun_user_verification");
                $code_exists = $code_verification ? true : false;

            } while ($code_exists);
        }
        $created_at = date("Y-m-d H:i:s");

        // get expiration time -> 24 hours
        // verification code expires in 24 hours
        //$verification_code_expiry_duration = "3 hours";
        $verification_code_expiry_duration = "15 minutes";
        if (!$expires_at) {
            $expires_at = date("Y-m-d H:i:s", strtotime("+$verification_code_expiry_duration", strtotime($created_at)));
        }

        //invalidate old verification code - kpong
        if($req_type=="email") {
            $db->where('email', $mobile);
        } else {
            $db->where('mobile', $mobile);
        }
        $db->where('source', $companyName);
        $db->where('type', $user_type);
        $db->where('status', 'success');
        $db->where('is_verified', 0);
        $db->where('is_valid', 1);
        $db->update('xun_user_verification', array('is_valid'=>0));


        // insert into xun_business_mobile_verification
        if($req_type=="email") {
            $fields = array("email", "verification_code", "is_valid", "expires_at", "request_at", "is_verified", "status", "created_at", "source", "type", "ip");
        } else {
            $fields = array("mobile", "verification_code", "is_valid", "expires_at", "request_at", "is_verified", "status", "created_at", "source", "type", "ip");
        }
        
        $values = array($mobile, $verification_code, 1, $expires_at, $created_at, 0, "success", $created_at, $companyName, $user_type, $ip);
        $arrayData = array_combine($fields, $values);
        $row_id = $db->insert("xun_user_verification", $arrayData);

        return array("row_id" => $row_id, "verification_code" => $verification_code);
    }

    private function generate_mobile_verification_code_sms_message($mobile, $message, $prefix = null, $mobile_region_code = null, $request_type = null)
    {
        global $setting;

        if($prefix == '' || is_null($prefix)){
            $prefix = $setting->systemSetting["smsVerificationPrefix"];
        }

        if(strtolower($prefix) == "ppay") {
            $prefix = "PPAY";
        }

        if($request_type == 'request_fund'){
            $prefix = $prefix.": Your login password is ";
        }else{
            if($mobile_region_code == "cn" && ($prefix == "NuxPay" || $prefix == "NuxStory" || $prefix == "PPay")){
                $prefix = "您的". $prefix."验证码";
                // 您的NuxPay验证码
            }else{
                $prefix .= ": Code ";
            }
        }

        $new_message = $prefix . $message;

        return $new_message;

        // if (strpos($mobile, '+60') === 0) {
        //     $new_message = $prefix . $message;
        //     return $new_message;
        // } else {
        //     return $message;
        // }

    }

    private function maximum_request_verification_code_attempt()
    {
        return 3;
        //return 7;
    }

    private function verification_get_timeout($length)
    {
        // 1 -> 30s, 2 -> 1min, 3 -> 5min, 4 -> 10 min, 5 -> 30 min, 6 -> 1 hr, 7 -> 5hr
        // switch ($length) {
        //     case 1:
        //         // $timeout = 1 * 60;
        //         $timeout = 1 * 30;

        //         break;
        //     case 2:
        //         // $timeout = 1 * 60;
        //         $timeout = 1 * 60;
        //         break;
        //     case 3:
        //         // $timeout = 3 * 60;
        //         $timeout = 5 * 60;

        //         break;

        //     case 4:
        //         // $timeout = 4 * 60;
        //         $timeout = 10 * 60;

        //         break;
        //     case 5:
        //         // $timeout = 30 * 60;
        //         $timeout = 30 * 60;

        //         break;

        //     case 6:
        //         // $timeout = 30 * 60;
        //         $timeout = 60 * 60;

        //         break;

        //     case 0:
        //         // $timeout = 30 * 60;
        //         $timeout = 5 * 60 * 60;

        //         break;

        //     default:
        //         $timeout = 30 * 60;
        //         break;
        // }

        $timeout = 3 * 60;//3 minutes kpong

        return $timeout;

    }

    private function verification_verify_timeout($length)
    {
        switch ($length) {
            case 1:
                $timeout = 2;

                break;
            case 2:
                $timeout = 10;

                break;
            case 3:
                $timeout = 30;

                break;
            case 4:
                $timeout = 60;

                break;
            case 0:
                $timeout = 120;

                break;

            default:
                $timeout = 120;
                break;
        }

        return $timeout;

    }

    private function send_request_verification_code_message($mobile, $ip, $sending_count, $verification_code, $status, $sms_message, $user_info, $sms_content = null, $companyName = null, $req_type = null, $user_role = null)
    {
        global $xunXmpp;
        $db = $this->db;
        $general = $this->general;

        $device = $user_info["device"] ? $user_info["device"] : "";
        $os_version = $user_info["os_version"] ? $user_info["os_version"] : "";
        $phone_model = $user_info["phone_model"] ? $user_info["phone_model"] : "";
        $country = $user_info["country"] ? $user_info["country"] : "";
        $user_type = $user_info["user_type"];
        $grouping = $companyName == 'NuxPay' ? "thenux_pay" : '';
        
        if($req_type=="email") {
            $message = "Email address: " . $mobile . "\n";
        } else {
            $message = "Phone number: " . $mobile . "\n";
        }
        
        
        $created_at = date("Y-m-d H:i:s");
       
        $message .= "IP: " . $ip . "\n";
        $message .= "Country: " . $country . "\n";
        $message .= "Device: " . $device . "\n";
        $message .= "Type of User: " . $user_type . "\n";
        $message .= "User Role:" .$user_role."\n";

        if ($status == "FAILED") {
            $message .= "\nSending Count: " . $sending_count . "\n";
            $message .= "Status: " . $status . "\n";
            $message .= "Message: " . $sms_message . "\n";
        } else {
            $message .= "\nSending Count: " . $sending_count . "\n";
            $message .= "Verification Code: " . $verification_code . "\n";
            $message .= "Status: " . $status . "\n";
            $message .= "Message: " . $sms_message . "\n";
            $message .= "SMS Message Content: " . $sms_content . "\n";
        }

        $message .= "Time: " . $created_at;

        $thenux_params["tag"] = "Request Verification Code";
        $thenux_params["message"] = $message;
        $thenux_params["mobile_list"] = array();

        $thenux_result = $general->send_thenux_notification($thenux_params, $grouping);

        return $thenux_result;
    }

    private function send_verify_verification_code_message($mobile, $status, $input_code, $newParams, $grouping = null, $req_type = null, $user_role = null)
    {

        $db = $this->db;
        $general = $this->general;

        $created_at = date("Y-m-d H:i:s");

        $mobile = $newParams["mobile"];
        $email = $newParams['email'];
        $ip = $newParams["ip"];
        $user_country = $newParams["country"];
        $device_os = $newParams["device"];
        $user_type = $newParams["type"];
        $request_count = $newParams["requested_count"];
        $first_req_time = $newParams["first_req"];
        $time_take = $newParams["time_take"];

        if($grouping == 'thenux_pay'){
            if ($req_type == 'email'){
                $db->where("email", $email);
            } else {
                $db->where("username", $mobile);
            }
            $db->where("register_site", 'nuxpay');
            $xun_user = $db->getOne('xun_user');

            $nickname = $xun_user["nickname"];
            $message .= "Username: " .$nickname."\n";
        }

        if($req_type=="email") {
            $message .= "Email address: " . $email . "\n";
        } else {
            $message .= "Phone number: " . $mobile . "\n";
        }
        
        $message .= "IP: " . $ip . "\n";
        $message .= "Country: " . $user_country . "\n";
        $message .= "Device: " . $device_os . "\n";
        $message .= "Type Of User: " . $user_type . "\n";
        $message .= "User Role:" .$user_role."\n";
        $message .= "Status: " . $status . "\n";
        $message .= "Code: " . $input_code . "\n\n";

        $message .= "Requested Count: " . $request_count . "\n";
        $message .= "First request time: " . $first_req_time . "\n";
        $message .= "Time Taken: " . $time_take . " sec\n";
        $message .= "Time: " . $created_at;

        $thenux_params["tag"] = "Validate Verification Code";
        $thenux_params["message"] = $message;
        $thenux_params["mobile_list"] = array();
        
        //PENDING0818 - ok
        $thenux_result = $general->send_thenux_notification($thenux_params, $grouping);

        return $thenux_result;
    }

    private function update_user_verification_info($row_id, $user_info, $message, $sms_content = null)
    {
        $db = $this->db;

        $now = date("Y-m-d H:i:s");
        $updateData = [];
        $updateData["user_type"] = $user_info["user_type"];
        $updateData["device_os"] = $user_info["device"];
        $updateData["os_version"] = $user_info["os_version"];
        $updateData["phone_model"] = $user_info["phone_model"];
        $updateData["country"] = $user_info["country"];
        $updateData["message"] = $message;
        $updateData["sms_message_content"] = $sms_content;

        $db->where("id", $row_id);
        $db->update("xun_user_verification", $updateData);
    }

    private function store_failed_get_verification_code($latest_request, $user_info, $message, $sms_content = null, $source, $nuxpay_user_type, $ip)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        // insert into xun_user_verification
        $insertData = [];
        $insertData["mobile"] = $latest_request["mobile"];
        $insertData["email"] = $latest_request["email"];
        $insertData["verification_code"] = $latest_request["verification_code"];
        $insertData["expires_at"] = $latest_request["expires_at"];
        $insertData["user_type"] = $user_info["user_type"];
        $insertData["device_os"] = $user_info["device"];
        $insertData["os_version"] = $user_info["os_version"];
        $insertData["phone_model"] = $user_info["phone_model"];
        $insertData["country"] = $user_info["country"];
        $insertData["is_verified"] = 0;
        $insertData["is_valid"] = 0;
        $insertData["request_at"] = $now;
        $insertData["message"] = $message;
        $insertData["created_at"] = $now;
        $insertData["status"] = "failed";
        $insertData['source'] = $company_name;
        $insertData['type'] = $nuxpay_user_type; //business/reseller/distributor
        $insertData['ip'] = $ip;
        $row_id = $db->insert("xun_user_verification", $insertData);
    }

    private function add_xun_user($username)
    {
        $db = $this->db;

        global $config;
        $db->where("type", 'user');
        $db->where("username", $username);
        $xun_user = $db->getOne("xun_user");
        $now = date("Y-m-d H:i:s");

        $new_user = false;
        $user_type = "Existing";

        $erlang_server = $config["erlang_server"];
        if (!$xun_user) {
            $arrayData = array(
                "username" => $username,
                "server_host" => $erlang_server,
                "web_password" => "",
                "type" => "user",
                "created_at" => $now,
                "updated_at" => $now
            );
            $user_id = $db->insert("xun_user", $arrayData);
            $new_user = true;
            $user_type = "New";

        } else if ($xun_user["disabled"] == 1) {
            $update_xun_user["disabled"] = 0;
            $update_xun_user["updated_at"] = $now;
            $db->where("username", $username);
            $db->update("xun_user", $update_xun_user);
            $new_user = true;
            $disable_type = $xun_user["disable_type"];
            $user_type = "Return - " . $disable_type;
            $user_id = $xun_user["id"];
        }

        // follow official business
        $business_id = '1';
        $db->where("business_id", $business_id);
        $db->where("username", $username);
        $result = $db->getOne("xun_business_follow");

        if (!$result) {
            $fields = array("business_id", "username", "server_host", "old_id", "created_at", "updated_at");
            $values = array($business_id, $username, $config["erlang_server"], "", date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));

            $insertData = array_combine($fields, $values);

            $row_id = $db->insert("xun_business_follow", $insertData);
        }

        return array("is_new_user" => $new_user, "user_type" => $user_type, "user_id" => $user_id);
    }

    public function verify_code($mobile, $verify_code, $ip = "", $device_os = "", $user_type = "", $company_name = null, $req_type = null, $nuxpay_user_type = "business")
    {
        global $config;
        $whoisserver = $this->whoisserver;

        $test_account = $config["test_account"];
        $test_account_code = $config["test_account_code"];

        $general = $this->general;
        $db = $this->db;

        $grouping = $company_name == 'NuxPay' ? "thenux_pay" : null;

        if($req_type=="email") {
            $email = $mobile;
            $mobile = "";
        }

        if($req_type=="email") {

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00543') /*Please enter a valid email address.*/);
            }

            $is_test_account = false;

        } else {
            $mobileNumberInfo = $general->mobileNumberInfo($mobile, null);
            if ($mobileNumberInfo["isValid"] == 0) {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00046') /*Please enter a valid mobile number.*/, "errorCode" => -103);
            }

            $mobile = str_replace("-", "", $mobileNumberInfo["phone"]);
            $is_test_account = in_array($mobile, $test_account);
        }
        
        
        $now = date("Y-m-d H:i:s");

        $user_country_info_arr = $this->get_user_country_info([$mobile]);

        $user_country_info = $user_country_info_arr[$mobile];
        $user_country = $user_country_info["name"];

        if ($user_country == ''){
            $ipDetails = $whoisserver->LookupIP($ip);

            if($ipDetails['result']['country'] != ''){
                $user_country = $ipDetails['result']['country'];
            } else if ($ipDetails['result']['Country'] != ''){
                $user_country = $ipDetails['result']['Country'];
            }
        }

        $db->where("is_verified", 1);
        $db->where("is_valid", 1);
        $db->where('type', $nuxpay_user_type);
        $db->where('source', $company_name);//kpong
        if($req_type=="email") {
            $db->where("email", $email);
        } else {
            $db->where("mobile", $mobile);
        }
        $max_id = $db->getValue("xun_user_verification", "MAX(id)");

        if($max_id != NULL){
            $db->where("id", $max_id, ">");
        }
        $db->where("request_at", "NULL", "!=");
        $db->where('type', $nuxpay_user_type);
        $db->where('source', $company_name);//kpong
        if($req_type=="email") {
            $db->where("email", $email);
        } else {
            $db->where("mobile", $mobile);
        }
        $request_info = $db->get("xun_user_verification", null, "request_at");
        $request_count = count($request_info); 
        $first_req = min($request_info);
        $first_req_time  = $first_req["request_at"];


        $newParams["req_type"] = $req_type;
        $newParams["mobile"] = $mobile;
        $newParams["email"] = $email;
        $newParams["ip"] = $ip;
        $newParams["country"] = $user_country;
        $newParams["device"] = $device_os;
        $newParams["type"] = $user_type;
        $newParams["code"] = $verify_code;
        $newParams["requested_count"] = $request_count;
        $newParams["first_req"] = $first_req_time;

        if($req_type=="email") {
            $db->where("email", $email);
        } else {
            $db->where("mobile", $mobile);
        }
        $db->where("request_at", '0', '>');
        $db->where("is_valid", 1);
        $db->where("expires_at", $now, ">=");
        $db->where('source', $company_name);
        $db->where('type', $nuxpay_user_type);
        $db->orderBy("request_at", "DESC");

        $xun_user_verification = $db->map("verification_code")->ObjectBuilder()->get("xun_user_verification");

        $request_new_code = false;
        
        if (!$xun_user_verification) {
            $request_new_code = true;
            
            //invalidate old verification code - kpong
            if($req_type=="email") {
                $db->where('email', $email);
            } else {
                $db->where('mobile', $mobile);
            }
            $db->where('source', $company_name);
            $db->where('type', $nuxpay_user_type);
            $db->where('status', 'success');
            $db->where('is_verified', 0);
            $db->where('is_valid', 1);
            $db->update('xun_user_verification', array('is_valid'=>0));


            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00140') /*Please request for new a verification code.*/, "errorCode" => -102);
        }
        
        $latest_request = (array)current($xun_user_verification);

        // check if latest code is already verified
        // if($req_type=="email") {
        //     $db->where("email", $email);
        // } else {
        //     $db->where("mobile", $mobile);
        // }
        // $db->where("verification_code", $verify_code);
        // $db->where("is_valid", 1);
        // $db->where("is_verified", 1);
        // $db->where("verify_at", 0, '>');
        // $db->where('source', $company_name);
        // $db->where('type', $nuxpay_user_type);
        // $code_verification = $db->getOne("xun_user_verification");

        // if($is_test_account == false){
        //     $request_new_code = $code_verification ? true : false;

        //     if ($request_new_code) {

        //         //invalidate old verification code - kpong
        //         if($req_type=="email") {
        //             $db->where('email', $email);
        //         } else {
        //             $db->where('mobile', $mobile);
        //         }
        //         $db->where('source', $company_name);
        //         $db->where('type', $nuxpay_user_type);
        //         $db->where('status', 'success');
        //         $db->where('is_verified', 0);
        //         $db->where('is_valid', 1);
        //         $db->update('xun_user_verification', array('is_valid'=>0));


        //         return array('code' => 0, 'message' => "FAILED", 'message_d' =>'2'.$this->get_translation_message('E00140') /*Please request for new a verification code.*/, "errorCode" => -102);
        //     }
        // }
        // check timeout => reset for eact unique code
        // get if code has been verified before
        if($req_type=="email") {
            $db->where("email", $email);
        } else {
            $db->where("mobile", $mobile);
        }
        $db->where('type', $nuxpay_user_type);
        $db->where('source', $company_name);//kpong
        $db->orderBy('id', 'DESC');
        $verification_code = $db->getValue('xun_user_verification', 'verification_code');
        
        if($req_type=="email") {
            $db->where("email", $email);
        } else {
            $db->where("mobile", $mobile);
        }
        $db->where('source', $company_name);
        $db->where('type', $nuxpay_user_type);
        $db->where("verification_code", $verification_code);
        // $db->where("is_valid", 1);
        // $db->where("verify_at", '0', '>');
        // $db->orderBy("verify_at", "DESC");
        $verification_records = $db->get("xun_user_verification");
 
        $total_verification_records = count($verification_records);
        // Generate new otp if reattempt more than 3 times

        if($total_verification_records >= 3 && $verification_code != $verify_code){

            //invalidate old verification code - kpong
            if($req_type=="email") {
                $db->where('email', $email);
            } else {
                $db->where('mobile', $mobile);
            }
            $db->where('source', $company_name);
            $db->where('type', $nuxpay_user_type);
            $db->where('status', 'success');
            $db->where('is_verified', 0);
            $db->where('is_valid', 1);
            $db->update('xun_user_verification', array('is_valid'=>0));


            return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00140') /*Please request for new a verification code.*/, "errorCode" => -102); 
        }

        $max_verification_timeout_duration = $this->verification_verify_timeout(0);
        $max_verification_attempt_datetime = date("Y-m-d H:i:s", strtotime('-' . $max_verification_timeout_duration . ' seconds', strtotime($now)));

        if($req_type=="email") {
            $db->where("email", $email);
        } else {
            $db->where("mobile", $mobile);
        }
        $db->where("is_valid", 0);
        $db->where("verify_at", '0', '>');
        $db->where("created_at", $max_verification_attempt_datetime, '>=');
        $db->where('source', $company_name);
        $db->where('type', $nuxpay_user_type);
        $verification_records = $db->get("xun_user_verification");
        $record_size = sizeof($verification_records);


        //$max_attempts = 5;
        $max_attempts = 3;

        if (!$verification_records) {
            // 1st attempt
            $number_of_attempts = 0;
        } else {
            $number_of_attempts = $record_size % $max_attempts;
            $latest_verification_record = $verification_records[0];
            $latest_verify_at = $latest_verification_record["verify_at"];
            $timeout = $this->verification_verify_timeout($number_of_attempts);

            $timeout_time = date("Y-m-d H:i:s", strtotime('+' . $timeout . ' seconds', strtotime($latest_verify_at)));

            // if ($now < $timeout_time) {
            //     $now_ts = strtotime($now);
            //     $timeout_ts = strtotime($timeout_time);

            //     $timeout_left = $timeout_ts - $now_ts;

            //     //$translations_message = $this->get_translation_message('E00258') /*Please try again in %%timeout_left%% seconds.*/;
            //     $error_message = 'The code you entered is incorrect. Please try again.';//str_replace("%%timeout_left%%", $timeout_left, $translations_message);

            //     // insert for failed attempts
            //     $insertData = $latest_request;
            //     unset($insertData["id"]);
            //     $insertData["request_at"] = null;
            //     $insertData["verify_at"] = $now;
            //     $insertData["is_valid"] = 0;
            //     $insertData["is_verified"] = 0;
            //     $insertData["message"] = $error_message;
            //     $insertData["sms_message_content"] = "";
            //     $insertData["created_at"] = $now;
            //     $insertData["status"] = "failed";
            //     $insertData['source'] = $company_name;
            //     $insertData['type'] = $nuxpay_user_type; //reseller/distributor/business
            //     $db->insert("xun_user_verification", $insertData);
            //     $time_take = strtotime($now) - strtotime($first_req_time);
            //     $newParams["time_take"] = $time_take;


            //     //PENDING0818 - ok
            //     if($req_type=="email") {
            //         $this->send_verify_verification_code_message($email, "FAILED", $verify_code, $newParams, $grouping, "email", $nuxpay_user_type);
            //     } else {
            //         $this->send_verify_verification_code_message($mobile, "FAILED", $verify_code, $newParams, $grouping, "mobile", $nuxpay_user_type);
            //     }
                

            //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "timeout" => $timeout_left, "errorCode" => -101);

            // }
        }

        $verification_code_data = $xun_user_verification[$verify_code];

        if (!$verification_code_data) {

            // invalid code
            $number_of_attempts = ($number_of_attempts + 1) % $max_attempts;
            $timeout = $this->verification_verify_timeout($number_of_attempts);
            
            $error_message = 'The code you entered is incorrect. Please try again.';//$this->get_translation_message('E00139') /*The code you entered is incorrect. Please try again .*/;

            // $error_message = str_replace("%%timeout%%", $timeout, $error_message);
            $insertData = $latest_request;
            unset($insertData["id"]);
            $insertData["request_at"] = null;
            $insertData["verify_at"] = $now;
            $insertData["is_valid"] = 0;
            $insertData["is_verified"] = 0;
            $insertData["message"] = $error_message;
            $insertData["sms_message_content"] = "";
            $insertData["created_at"] = $now;
            $insertData["status"] = "failed";
            $insertData['source'] = $company_name;
            $insertData['type'] = $nuxpay_user_type; //business/reseller/distributor
            $insertData['ip'] = $ip;

            $row_id = $db->insert("xun_user_verification", $insertData);
            // if(!$row_id){
            //     print_r($db);
            //     print_r($insertData);
            // }
            
            $time_take = strtotime($now) - strtotime($first_req_time);
            $newParams["time_take"] = $time_take;



            //PENDING0818 - ok
            if($req_type=="email") {
                $this->send_verify_verification_code_message($email, "FAILED", $verify_code, $newParams, $grouping, "email", $nuxpay_user_type);
            } else {
                $this->send_verify_verification_code_message($mobile, "FAILED", $verify_code, $newParams, $grouping, "mobile", $nuxpay_user_type);
            }
            
            
            if($total_verification_records >= 3){
                return array('code' => 0, 'message' => "FAILED", 'message_d' =>  $this->get_translation_message('E00140') /*Please request for new a verification code.*/, "errorCode" => -102); 
            } else {
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $error_message, "errorCode" => -100, "timeout" => $timeout);
            }
            
        }

        $insertData = (array)$verification_code_data;
        unset($insertData["id"]);
        $insertData["request_at"] = null;
        $insertData["verify_at"] = $now;
        $insertData["is_verified"] = 1;
        $insertData["is_valid"] = 1;
        $insertData["sms_message_content"] = "";
        $insertData["message"] = "Verification code verified.";
        $insertData["created_at"] = $now;
        $insertData["status"] = "success";
        $insertData['source'] = $company_name;
        $insertData['type'] = $nuxpay_user_type; //business/reseller/distributor
        $insertData['ip'] = $ip;

        $row_id = $db->insert("xun_user_verification", $insertData);

        $time_take = strtotime($now) - strtotime($first_req_time);
        $newParams["time_take"] = $time_take;

        // return response
        //PENDING0818 - ok
        if($req_type=="email") {
            $this->send_verify_verification_code_message($email, "SUCCESS", $verify_code, $newParams, $grouping, "email", $nuxpay_user_type);
        } else {
            $this->send_verify_verification_code_message($mobile, "SUCCESS", $verify_code, $newParams, $grouping, "mobile", $nuxpay_user_type);
        }
        
        return array("code" => 1, "formatted_mobile" => $mobile, "email"=>$email, "request_arr" => $latest_request, "row_id" => $row_id);
    }

    public function verify_change_number($params)
    {
        $db = $this->db;
        $general = $this->general;

        $verify_code = trim($params["verify_code"]);
        $mobile = trim($params["mobile"]);

        if ($verify_code == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00142') /*Verify code cannot be empty*/);
        };

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00019') /*Mobile cannot be empty.*/);
        };

        $verify_code_return = $this->verify_code($mobile, $verify_code);
        if ($verify_code_return["code"] === 0) {
            return $verify_code_return;
        }

        $formatted_mobile = $verify_code_return["formatted_mobile"];

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00074') /*Verification code verified*/, "result" => array("username" => $formatted_mobile));
    }

    public function change_number($params)
    {
        $db = $this->db;
        $general = $this->general;

        $mobile = trim($params["mobile"]);
        $new_mobile = trim($params["new_mobile"]);

        // check verification in erlang

        if ($mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00005') /*Mobile number cannot be empty.*/);
        }
        if ($new_mobile == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00142') /*New mobile number cannot be empty*/);
        }

        $translations_message = $this->get_translation_message('B00075') /*Your phone number has successfully been changed from %%mobile%% to %%new_mobile%%*/;
        $return_message = str_replace("%%mobile%%", $mobile, $translations_message);
        $message_d = str_replace("%%new_mobile%%", $new_mobile, $return_message);

        if ($mobile === $new_mobile) {
            return array("code" => 1, "message" => "SUCCESS", "message_d" => $message_d);
        }

        $now = date("Y-m-d H:i:s");

        // in erlang
        // blocked user, roster, vcard, employee vcard, group chat

        $this->copy_user_device_information($mobile, $new_mobile);

        // update xun_user
        $user_id_arr = $this->copy_xun_user($mobile, $new_mobile);
        $old_user_id = $user_id_arr["old_user_id"];
        $new_user_id = $user_id_arr["new_user_id"];
        // update xun_user_contact
        $this->copy_xun_user_contact($mobile, $new_mobile);

        // update xun_user_chat_preference
        $this->copy_xun_user_chat_preference($mobile, $new_mobile);

        // update xun_user_privacy_settings
        $this->copy_xun_user_privacy_settings($mobile, $new_mobile);

        // update xun_business_follow, xun_business_follow_message, xun_business_block
        $this->copy_xun_business_follow($mobile, $new_mobile);
        $this->copy_xun_business_block($mobile, $new_mobile);
        $this->copy_xun_business_follow_message($mobile, $new_mobile);

        //  update xun_muc_user
        $group_chat_return = $this->copy_user_group_chat($mobile, $new_mobile);

        // xun_public_key
        $this->copy_user_public_key($mobile, $new_mobile);

        // update xun_employee and xun_business_tag_employee
        $employee_return = $this->copy_business_employee_info($mobile, $new_mobile);

        //  xun_crypto_user_address
        $this->copy_xun_crypto_user_address($old_user_id, $new_user_id);
        $this->copy_xun_crypto_user_external_address($old_user_id, $new_user_id);
        $this->copy_xun_crypto_user_address_verification($old_user_id, $new_user_id);

        // marketplace
        $this->copy_xun_marketplace_advertisement($old_user_id, $new_user_id);
        $this->copy_xun_marketplace_escrow_transaction($old_user_id, $new_user_id);
        $this->copy_xun_marketplace_user_payment_method($old_user_id, $new_user_id);
        $this->copy_xun_marketplace_user_report($old_user_id, $new_user_id);
        $this->copy_xun_marketplace_user($old_user_id, $new_user_id);

        $this->copy_xun_freecoin_payout_transaction($old_user_id, $new_user_id);
        $this->copy_xun_gift_code($old_user_id, $new_user_id);
        $this->copy_xun_kyc($old_user_id, $new_user_id);
        $this->copy_xun_wallet_transaction($old_user_id, $new_user_id);
        $this->copy_xun_user_setting($old_user_id, $new_user_id);
        
        /**
         * REMAINING TABLES
         * -    xun_message_archive 
         * -    xun_referral_transaction
         * -    xun_tree_referral 
         * -    xun_user_setting 
         */

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $message_d, "result" => array("group_chat" => $group_chat_return, "employee_details" => $employee_return));
    }

    private function copy_xun_user($mobile, $new_mobile)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        $db->where("username", $mobile);
        $xun_user = $db->getOne("xun_user");
        $old_user_id = $xun_user["id"];

        $db->where("username", $new_mobile);
        $xun_user_new = $db->getOne("xun_user");

        if ($xun_user_new) {
            // update
            $updateData = [];
            $updateData = $xun_user;
            $updateData["updated_at"] = $now;
            $updateData["disable_type"] = "";
            $updateData["disabled"] = 0;
            unset($updateData["id"]);
            unset($updateData["username"]);
            unset($updateData["created_at"]);

            $db->where("username", $new_mobile);
            $db->update("xun_user", $updateData);
            $row_id = $xun_user_new["id"];
        } else {
            unset($xun_user["id"]);
            $xun_user["username"] = $new_mobile;
            $xun_user["created_at"] = $now;
            $xun_user["updated_at"] = $now;

            $row_id = $db->insert("xun_user", $xun_user);
        }

        $updateData = [];
        $updateData["disabled"] = 1;
        $updateData["disable_type"] = "Change Number";
        $updateData["updated_at"] = $now;
        $db->where("username", $mobile);
        $db->update("xun_user", $updateData);

        return array("old_user_id" => $old_user_id, "new_user_id" => $row_id);
    }

    public function copy_user_device_information($mobile, $new_mobile)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        $db->where("mobile_number", $mobile);
        $xun_user_device = $db->getOne("xun_user_device");

        $db->where("mobile_number", $new_mobile);
        $xun_user_device_new = $db->getOne("xun_user_device");

        if ($xun_user_device_new) {
            // update
            $updateUserDevice = [];
            $updateUserDevice = $xun_user_device;
            $updateUserDevice["updated_at"] = $now;
            unset($updateUserDevice["id"]);
            unset($updateUserDevice["user_id"]);
            unset($updateUserDevice["mobile_number"]);
            unset($updateUserDevice["created_at"]);

            $db->where("mobile_number", $new_mobile);
            $db->update("xun_user_device", $updateUserDevice);
        } else {
            // insert
            unset($xun_user_device["id"]);

            //random nunmber for user id
            $flag = true;

            while ($flag) {

                $randNum = rand(1, 100000000);
                $value = $randNum;

                $db->where('user_id', $value);
                $result = $db->get('xun_user_device');

                if (!$result) {

                    $flag = false;
                    $device_id = $value;
                }
            }

            $xun_user_device["user_id"] = $device_id;
            $xun_user_device["mobile_number"] = $new_mobile;
            $xun_user_device["created_at"] = $now;
            $xun_user_device["updated_at"] = $now;

            $row_id = $db->insert("xun_user_device", $xun_user_device);
        }

        return 1;
    }

    private function copy_xun_user_contact($mobile, $new_mobile)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        // remove all new_mobile's contacts, replace with mobile's contacts
        $db->where("username", $mobile);
        $xun_user_contact = $db->get("xun_user_contact");

        $db->where("username", $new_mobile);
        $db->delete("xun_user_contact");

        foreach ($xun_user_contact as $user_contact) {
            if ($user_contact["contact_mobile"] === $new_mobile) {
                continue;
            }

            unset($user_contact["id"]);
            $user_contact["username"] = $new_mobile;
            $user_contact["created_at"] = $now;
            $user_contact["updated_at"] = $now;

            $db->insert("xun_user_contact", $user_contact);
        }

        $db->where("username", $mobile);
        $db->delete("xun_user_contact");

        return 1;
    }

    private function copy_xun_user_chat_preference($mobile, $new_mobile)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        $db->where("username", $mobile);
        $xun_user_chat_preference = $db->get("xun_user_chat_preference");

        $db->where("username", $new_mobile);
        $db->delete("xun_user_chat_preference");

        foreach ($xun_user_chat_preference as $user_chat_preference) {
            unset($user_chat_preference["id"]);
            $user_chat_preference["username"] = $new_mobile;
            $user_chat_preference["created_at"] = $now;
            $user_chat_preference["updated_at"] = $now;

            $db->insert("xun_user_chat_preference", $user_chat_preference);
        }

        $db->where("username", $mobile);
        $db->delete("xun_user_chat_preference");

        return 1;
    }

    private function copy_xun_user_privacy_settings($mobile, $new_mobile)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        $db->where("mobile_number", $mobile);
        $xun_user_privacy_settings = $db->get("xun_user_privacy_settings");

        $db->where("mobile_number", $new_mobile);
        $db->delete("xun_user_privacy_settings");

        foreach ($xun_user_privacy_settings as $user_privacy_setting) {
            unset($user_privacy_setting["id"]);
            $user_privacy_setting["mobile_number"] = $new_mobile;
            $user_privacy_setting["created_at"] = $now;
            $user_privacy_setting["updated_at"] = $now;

            $db->insert("xun_user_privacy_settings", $user_privacy_setting);
        }

        $db->where("mobile_number", $mobile);
        $db->delete("xun_user_privacy_settings");

        return 1;
    }

    private function copy_xun_business_follow($mobile, $new_mobile)
    {
        // xun_business_follow, xun_business_follow_message, , xun_business_block
        // maintain new_mobile's record
        // add $mobile's record

        // if new_mobile blocked business A but mobile is following then new_mobile following
        // if mobile blocked business A but new_mobile is following then new_mobile is following

        // if mobile is following && new_mobile blocked => delete from blocked
        $db = $this->db;

        $now = date("Y-m-d H:i:s");

        $db->where("username", $mobile);
        $xun_business_follow = $db->get("xun_business_follow");

        foreach ($xun_business_follow as $business_follow) {
            // check if new_mobile is following
            $business_id = $business_follow["business_id"];

            $db->where("username", $new_mobile);
            $db->where("business_id", $business_id);
            $new_user_business_follow = $db->getOne("xun_business_follow");

            if ($new_user_business_follow) {
                // new_mobile => id :1, mobile => id : 2, keep id:2
                $db->where("username", $new_mobile);
                $db->where("business_id", $business_id);
                $db->delete("xun_business_follow");
            }

            $updateData = [];
            $updateData["username"] = $new_mobile;
            $updateData["updated_at"] = $now;
            $db->where("id", $business_follow["id"]);
            $db->update("xun_business_follow", $updateData);

            $db->where("mobile_number", $new_mobile);
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $xun_business_block = $db->getOne("xun_business_block");

            if ($xun_business_block) {
                // unblock
                $updateData = [];
                $updateData["status"] = 0;
                $updateData["updated_at"] = $now;
                $db->where("mobile_number", $new_mobile);
                $db->where("business_id", $business_id);
                $db->where("status", 1);
                $db->update("xun_business_block", $updateData);
            }

            $db->where("username", $new_mobile);
            $db->where("business_id", $business_id);

            $db->delete("xun_business_follow_message");
        }

        $db->where("username", $mobile);
        $db->delete("xun_business_follow");

        return 1;
    }

    private function copy_xun_business_block($mobile, $new_mobile)
    {
        // copy mobile's to new_mobile
        // remove from mobile
        // check if blocked business is followed before copying

        $db = $this->db;

        $now = date("Y-m-d H:i:s");

        $db->where("mobile_number", $mobile);
        $db->where("status", 1);
        $xun_business_block = $db->get("xun_business_block");

        foreach ($xun_business_block as $business_block) {
            $business_id = $business_block["business_id"];
            $db->where("username", $new_mobile);
            $db->where("business_id", $business_id);

            $new_user_business_follow = $db->getOne("xun_business_follow");

            if ($new_user_business_follow) {
                continue;
            }

            $db->where("mobile_number", $new_mobile);
            $db->where("business_id", $business_id);

            $new_user_business_block = $db->getOne("xun_business_block");

            if (!$new_user_business_block) {
                $fields = array("business_id", "mobile_number", "user_id", "status", "created_at", "updated_at");
                $values = array($business_id, $new_mobile, $new_mobile, 1, $now, $now);

                $insertData = array_combine($fields, $values);
                $db->insert("xun_business_block", $insertData);
            } else if ($new_user_business_block["status"] == 1) {
                continue;
            } else if ($new_user_business_block["status"] == 0) {
                // update
                $updateData = [];
                $updateData["status"] = 1;
                $updateData["updated_at"] = $now;

                $db->where("id", $new_user_business_block["id"]);
                $db->update("xun_business_block", $updateData);
            }

            $db->where("username", $new_mobile);
            $db->where("business_id", $business_id);

            $db->delete("xun_business_follow_message");
        }

        $db->where("mobile_number", $mobile);
        $db->delete("xun_business_block");

        return 1;
    }

    private function copy_xun_business_follow_message($mobile, $new_mobile)
    {
        $db = $this->db;

        $now = date("Y-m-d H:i:s");

        $db->where("username", $mobile);
        $xun_business_follow_message = $db->get("xun_business_follow_message");

        foreach ($xun_business_follow_message as $business_follow_message) {
            $business_id = $business_follow_message["business_id"];

            // if mobile has it => not following, not blocked
            // if new_mobile has it => ignore
            // if new_mobile is following || blocked => ignore
            // else copy

            $new_user_business_follow;
            $db->where("username", $new_mobile);
            $db->where("business_id", $business_id);
            $new_user_business_follow = $db->getOne("xun_business_follow");

            if (!$new_user_business_follow) {
                $db->where("mobile_number", $new_mobile);
                $db->where("business_id", $business_id);
                $db->where("status", 1);
                $new_user_business_block;
                $new_user_business_block = $db->getOne("xun_business_block");

                if (!$new_user_business_block) {
                    $new_user_business_message_follow;

                    $db->where("username", $new_mobile);
                    $db->where("business_id", $business_id);

                    $new_user_business_message_follow = $db->getOne("xun_business_follow_message");

                    if (!$new_user_business_message_follow) {
                        // insert
                        $fields = array("business_id", "username", "created_at", "updated_at");
                        $values = array($business_id, $new_mobile, $now, $now);
                        $arrayData = array_combine($fields, $values);
                        $db->insert("xun_business_follow_message", $arrayData);
                    }
                }
            }
        }

        $db->where("username", $mobile);
        $db->delete("xun_business_follow_message");

        return 1;
    }

    private function copy_user_group_chat($mobile, $new_mobile)
    {
        $db = $this->db;
        $general = $this->general;

        $now = date("Y-m-d H:i:s");

        $db->where("username", $mobile);
        $xun_muc_user = $db->get("xun_muc_user", null, "group_id, group_host");

        $db->where("username", $new_mobile);
        $new_user_xun_muc_user = $db->get("xun_muc_user", null, "group_id, group_host");

        // get mobile's unique group
        // get new_mobile's unique group
        // get common group

        function flatten($arr)
        {
            $new_arr = array();
            foreach ($arr as $sub_arr) {
                $new_arr[$sub_arr["group_id"]] = $sub_arr["group_host"];
            }
            return $new_arr;
        }

        $flat_arr1 = flatten($xun_muc_user);
        $flat_arr2 = flatten($new_user_xun_muc_user);

        $old_user_group = array();
        $new_user_group = $flat_arr2;
        $common_group = array();

        $final_old_user_group = array();
        $final_new_user_group = array();
        $final_common_group = array();

        foreach ($flat_arr1 as $group_id => $group_host) {
            $group_obj = array();
            if (isset($flat_arr2[$group_id]) && $flat_arr2[$group_id] == $group_host) {
                $common_group[$group_id] = $group_host;
                unset($new_user_group[$group_id]);

                $group_obj["group_id"] = (string) $group_id;
                $group_obj["group_host"] = $group_host;
                $final_common_group[] = $group_obj;
            } else {
                $old_user_group[$group_id] = $group_host;

                $fields = array("username", "group_id", "group_host", "created_at");
                $values = array($new_mobile, $group_id, $group_host, $now);
                $insertData = array_combine($fields, $values);

                $db->insert("xun_muc_user", $insertData);

                // get encrypted_private_key from old user
                $db->where("username", $mobile);
                $db->where("status", 1);
                $db->where("group_id", $group_id);
                $db->where("group_host", $group_host);
                $xun_encrypted_key = $db->getOne("xun_encrypted_key");

                $group_encrypted_key = "";
                if ($xun_encrypted_key) {
                    // //id    username    group_id    group_host    encrypted_key    status    created_at    updated_at
                    $group_encrypted_key = $xun_encrypted_key["encrypted_key"];
                    $fields = array("username", "group_id", "group_host", "encrypted_key", "status", "created_at", "updated_at");
                    $values = array($new_mobile, $group_id, $group_host, $group_encrypted_key, "1", $now, $now);
                    $insertData = array_combine($fields, $values);

                    $db->insert("xun_encrypted_key", $insertData);
                }

                $group_obj["group_id"] = (string) $group_id;
                $group_obj["group_host"] = $group_host;
                $group_obj["encrypted_private_key"] = $group_encrypted_key;
                $final_old_user_group[] = $group_obj;
            }
        }

        foreach ($new_user_group as $group_id => $group_host) {
            // build final array

            // new_user_groups: [
            //     {
            //     group_id:
            //     group_host:
            //     encrypted_private_key:
            //     public_key:
            //     }
            // ]
            $group_obj = array();

            $db->where("username", $new_mobile);
            $db->where("status", 1);
            $db->where("group_id", $group_id);
            $db->where("group_host", $group_host);
            $xun_encrypted_key = $db->getOne("xun_encrypted_key");

            $group_encrypted_key = "";
            if ($xun_encrypted_key) {
                $group_encrypted_key = $xun_encrypted_key["encrypted_key"];
            }

            $db->where("key_user_id", $group_id);
            $db->where("key_host", $group_host);
            $db->where("status", 1);
            $xun_public_key = $db->getOne("xun_public_key");

            $group_public_key = "";
            if ($xun_public_key) {
                $group_public_key = $xun_public_key["key"];
            }

            $group_obj["group_id"] = (string) $group_id;
            $group_obj["group_host"] = $group_host;
            $group_obj["public_key"] = $group_public_key;
            $group_obj["encrypted_private_key"] = $group_encrypted_key;

            $db->where("old_id", $group_id);
            $db->where("host", $group_host);
            $xun_group_chat = $db->getOne("xun_group_chat");

            $group_obj["created_at"] = $general->formatDateTimeToIsoFormat($xun_group_chat["created_at"]);
            $group_obj["group_creator"] = $xun_group_chat["creator_id"];
            $group_obj["group_type"] = $xun_group_chat["type"];

            $final_new_user_group[] = $group_obj;
        }

        $db->where("username", $mobile);
        $db->delete("xun_muc_user");

        $db->where("username", $mobile);
        $db->delete("xun_encrypted_key");

        return array("common_groups" => $final_common_group, "old_user_groups" => $final_old_user_group, "new_user_groups" => $final_new_user_group);

    }

    private function copy_user_public_key($mobile, $new_mobile)
    {
        $db = $this->db;

        $now = date("Y-m-d H:i:s");

        $db->where("key_user_id", $mobile);
        $db->where("status", 1);

        $xun_public_key = $db->getOne("xun_public_key");

        $db->where("key_user_id", $new_mobile);
        $db->where("status", 1);

        $new_user_xun_public_key = $db->getOne("xun_public_key");

        if ($new_user_xun_public_key) {
            $updateData["key"] = $xun_public_key["key"];
            $updateData["updated_at"] = $now;

            $db->where("id", $new_user_xun_public_key["id"]);
            $db->update("xun_public_key", $updateData);
        } else {
            // insert
            $fields = array("key_user_id", "key_host", "key", "status", "created_at", "updated_at");
            $values = array($new_mobile, $xun_public_key["key_host"], $xun_public_key["key"], "1", $now, $now);
            $insertData = array_combine($fields, $values);

            $db->insert("xun_public_key", $insertData);
        }

        $db->where("key_user_id", $mobile);
        $db->delete("xun_public_key");

        return 1;
    }

    private function copy_business_employee_info($mobile, $new_mobile)
    {
        /*
        send message to:
        1)  $new_mobile
        2)  all co-workers of $mobile
        3)  all attending users of $mobile
         */

        $db = $this->db;
        global $config;

        $now = date("Y-m-d H:i:s");
        $erlang_server = $config["erlang_server"];
        $mobile_jid = $mobile . "@" . $erlang_server;
        $new_mobile_jid = $new_mobile . "@" . $erlang_server;

        $db->where("mobile", $mobile);
        $db->where("status", 1);
        $xun_employee = $db->get("xun_employee");

        $xmpp_employee_return_list = [];
        foreach ($xun_employee as $current_employee_rec) {
            $xmpp_recipient_list = [];
            $business_id = $current_employee_rec["business_id"];

            $db->where("mobile", $new_mobile);
            $db->where("business_id", $business_id);
            $db->where("status", 1);
            $new_employee_rec = $db->getOne("xun_employee");

            $current_employment_status = $current_employee_rec["employment_status"];
            $current_employee_id = $current_employee_rec["old_id"];

            if ($new_employee_rec) {
                $new_employment_status = $new_employee_rec["employment_status"];
                $new_employee_id = $new_employee_rec["old_id"];
            }

            if ($current_employment_status == "confirmed") {
                $new_employee_id = $this->copy_employee_info($current_employee_rec, $new_mobile, $new_employee_rec["id"]);
                $db->where("employee_id", $current_employee_id);
                $db->where("status", 1);
                $current_tag_employee = $db->get("xun_business_tag_employee");

                foreach ($current_tag_employee as $tag_employee) {
                    if ($new_employment_status == "confirmed") {
                        $db->where("business_id", $business_id);
                        $db->where("tag", $tag_employee["tag"]);
                        $db->where("status", 1);
                        $db->where("username", $new_mobile);

                        $new_tag_employee = $db->getOne("xun_business_tag_employee");

                        if ($new_tag_employee) {
                            continue;
                        }

                    }

                    // insert business_tag_employee
                    $insert_tag_employee = $tag_employee;
                    unset($insert_tag_employee["id"]);
                    $insert_tag_employee["employee_id"] = $new_employee_id;
                    $insert_tag_employee["username"] = $new_mobile;
                    $insert_tag_employee["created_at"] = $now;
                    $insert_tag_employee["updated_at"] = $now;

                    $db->insert("xun_business_tag_employee", $insert_tag_employee);
                }

                // populate xmpp_user_list
                $db->where("business_id", $business_id);
                $db->where("status", 1);
                $db->where("employment_status", "confirmed");
                $business_employees = $db->get("xun_employee");

                foreach ($business_employees as $business_employee) {
                    $xmpp_recipient_list[] = $business_employee["mobile"];
                }

                $db->where("business_id", $business_id);
                $db->where("employee_username", $mobile);
                $db->where("status", "accepted");
                $livechat_rooms = $db->get("xun_livechat_room");

                foreach ($livechat_rooms as $livechat_room) {
                    $xmpp_recipient_list[] = $livechat_room["username"];
                }

                $xmpp_employee_rec["employee_id_from"] = $current_employee_id;
                $xmpp_employee_rec["employee_id_to"] = $new_employee_id;
                $xmpp_employee_rec["employee_jid_from"] = $mobile_jid;
                $xmpp_employee_rec["employee_jid_to"] = $new_mobile_jid;

                unset($xmpp_recipient_list[$mobile]);

                $xmpp_employee_rec["recipient_list"] = array_values(array_unique($xmpp_recipient_list));

                $xmpp_employee_return_list[] = $xmpp_employee_rec;
            } else if ($current_employment_status == "pending") {
                if ($new_employment_status == "confirmed" || $new_employment_status == "pending") {
                    continue;
                }
                // update status to pending
                $new_employee_id = $this->copy_employee_info($current_employee_rec, $new_mobile, $new_employee_rec["id"]);
            }
        }

        $updateLiveChat;
        $updateLiveChat["updated_at"] = $now;
        $updateLiveChat["employee_username"] = $new_mobile;

        $db->where("employee_username", $mobile);
        $db->where("status", "accepted");
        $db->update("xun_livechat_room", $updateLiveChat);

        $updateTagEmployee;
        $updateTagEmployee["status"] = 0;
        $updateTagEmployee["updated_at"] = $now;
        $db->where("status", 1);
        $db->where("username", $mobile);

        $db->update("xun_business_tag_employee", $updateTagEmployee);

        $updateEmployee;
        $updateEmployee["status"] = 0;
        $updateEmployee["updated_at"] = $now;
        $db->where("status", 1);
        $db->where("mobile", $mobile);
        $db->update("xun_employee", $updateEmployee);

        return $xmpp_employee_return_list;
    }

    private function get_employee_old_id($business_id, $mobile)
    {
        $new_mobile = str_replace("+", "", $mobile);

        $employee_id = $business_id . "_" . $new_mobile;
        return $employee_id;
    }

    private function copy_employee_info($current_employee_rec, $new_mobile, $id = null)
    {
        $db = $this->db;

        $now = date("Y-m-d H:i:s");

        if ($id) {
            $updateData;
            $updateData["status"] = 0;
            $updateData["updated_at"] = $now;

            $db->where("id", $id);
            $db->update("xun_employee", $updateData);
        }

        $business_id = $current_employee_rec["business_id"];
        $new_employee_id = $this->get_employee_old_id($business_id, $new_mobile);

        $new_employee = $current_employee_rec;
        unset($new_employee["id"]);
        $new_employee["mobile"] = $new_mobile;
        $new_employee["created_at"] = $now;
        $new_employee["updated_at"] = $now;
        $new_employee["old_id"] = $new_employee_id;

        $row_id = $db->insert("xun_employee", $new_employee);
        return $new_employee_id;
    }

    private function copy_xun_crypto_user_address($old_user_id, $new_user_id)
    {
        $db = $this->db;

        $now = date("Y-m-d H:i:s");

        // inactivated new_mobile's active address
        $updateData = [];
        $updateData["active"] = 0;
        $updateData["updated_at"] = $now;
        $db->where("user_id", $new_user_id);
        $db->where("active", 1);
        // $db->where("address_type", "personal");
        $db->update("xun_crypto_user_address", $updateData);

        $updateData = [];
        $updateData["updated_at"] = $now;
        $updateData["user_id"] = $new_user_id;
        $db->where("user_id", $old_user_id);
        // $db->where("address_type", "personal");
        $db->update("xun_crypto_user_address", $updateData);

        return 1;
    }

    public function copy_xun_crypto_user_external_address($old_user_id, $new_user_id){
        $db = $this->db;

        $now = date("Y-m-d H:i:s");

        $updateData = [];
        $updateData["user_id"] = $new_user_id;
        $updateData["updated_at"] = $now;

        $db->where("user_id", $old_user_id);
        $ret_val = $db->update("xun_crypto_user_external_address", $updateData);

        return $ret_val;
    }

    public function copy_xun_crypto_user_address_verification($old_user_id, $new_user_id){
        $db = $this->db;

        $now = date("Y-m-d H:i:s");

        $updateData = [];
        $updateData["user_id"] = $new_user_id;

        $db->where("user_id", $old_user_id);
        $ret_val = $db->update("xun_crypto_user_address_verification", $updateData);

        return $ret_val;
    }

    public function copy_xun_marketplace_advertisement($old_user_id, $new_user_id){
        global $xunMarketplace;
        $db = $this->db;
        
        $now = date("Y-m-d H:i:s");
        
        $db->where("user_id", $old_user_id);
        $old_user_advertisement_arr = $db->get("xun_marketplace_advertisement", null, "id, user_id, created_at, date_format(created_at, '%Y-%m-%d') AS created_day");

        $old_user_advertisement_create_day = array_values(array_unique(array_column($old_user_advertisement_arr, "created_day")));

        $arr_len = count($old_user_advertisement_create_day);

        $updateUserData = [];
        $updateUserData["user_id"] = $new_user_id;
        $updateUserData["updated_at"] = $now;

        //  update marketplace order tables
        for ($i = 0; $i < $arr_len; $i++){
            $advertisement_date = $old_user_advertisement_create_day[$i];
            $advertisement_order_table = $xunMarketplace->get_advertisement_order_transaction_table_name($advertisement_date);

            $db->where("user_id", $old_user_id);
            $db->update($advertisement_order_table, $updateUserData);
        }

        //  update xun_marketplace_advertisement_order_cache
        $db->where("user_id", $old_user_id);
        $db->update("xun_marketplace_advertisement_order_cache", $updateUserData);

        //  update xun_marketplace_advertisement table
        $db->where("user_id", $old_user_id);
        $db->update("xun_marketplace_advertisement", $updateUserData);

        //  update xun_marketplace_escrow_transaction
        $db->where("user_id", $old_user_id);
        $db->update("xun_marketplace_escrow_transaction", $updateUserData);
    }

    public function copy_xun_marketplace_escrow_transaction($old_user_id, $new_user_id)
    {
        $db = $this->db;

        $updateUserData = [];
        $updateUserData["user_id"] = $new_user_id;
        
        $db->where("user_id", $old_user_id);
        $db->update("xun_marketplace_escrow_transaction", $updateUserData);
    }

    public function copy_xun_marketplace_user_payment_method($old_user_id, $new_user_id)
    {
        $db = $this->db;
        $now = date("Y-m-d H:i:s");
        
        //  update xun_marketplace_user_payment_method
        $db->where("user_id", [$old_user_id, $new_user_id], "in");
        $db->where("status", 1);
        $user_payment_method_arr = $db->get("xun_marketplace_user_payment_method", null, "id, user_id, payment_method_id");

        $old_user_id_int = (int)$old_user_id;
        $new_user_id_int = (int)$new_user_id;
        if(!empty($user_payment_method_arr)){
            $old_user_payment_method = [];
            $new_user_payment_method = [];

            $arr_len = count($user_payment_method_arr);

            for ($i = 0; $i < $arr_len; $i++)
            {
                $data = $user_payment_method_arr[$i];

                if ($data["user_id"] === $old_user_id_int){
                    $old_user_payment_method[$data["payment_method_id"]] = $data;
                }else{
                    $new_user_payment_method[$data["payment_method_id"]] = $data;
                }
            }

            $intersected_payment_method = array_intersect_key($new_user_payment_method, $old_user_payment_method);

            if(!empty($intersected_payment_method)){
                $intersected_payment_method_id = array_column($intersected_payment_method, "id");
                $updatePaymentMethodData = [];
                $updatePaymentMethodData["status"] = 0;
                $updatePaymentMethodData["updated_at"] = $now;

                $db->where("id", $intersected_payment_method_id, "in");
                $db->update("xun_marketplace_user_payment_method", $updatePaymentMethodData);
            }

            $updateUserData = [];
            $updateUserData["user_id"] = $new_user_id;
            $updateUserData["updated_at"] = $now;

            $db->where("user_id", $old_user_id);
            $db->update("xun_marketplace_user_payment_method", $updateUserData);
        }
    }

    public function copy_xun_marketplace_user_report($old_user_id, $new_user_id)
    {
        $db = $this->db;

        $updateData = [];
        $updateData["user_id"] = $new_user_id;
        $db->where("user_id", $old_user_id);
        $db->update("xun_marketplace_user_report", $updateData);
    }
    
    public function copy_xun_marketplace_user($old_user_id, $new_user_id)
    {
        global $setting, $xunMarketplace;
        $db = $this->db;
        $now = date("Y-m-d H:i:s");

        // xun_marketplace_user_rating
        // xun_marketplace_user

        $updateUserData = [];
        $updateUserData["user_id"] = $new_user_id;
        $db->where("user_id", $old_user_id);
        $db->update("xun_marketplace_user_rating", $updateUserData);

        $user_average_rating = $xunMarketplace->get_user_rating($new_user_id);

        //  update xun_marketplace_user
        //  update user_id for old_id, remove new_id

        $user_trade_count = $xunMarketplace->get_user_trade_count($new_user_id);

        $db->where("user_id", [$old_user_id, $new_user_id], "in");
        $marketplace_user_arr = $db->map("user_id")->ArrayBuilder()->get("xun_marketplace_user");

        if (!empty($user_average_rating) || !empty($user_trade_count)){
            if(isset($marketplace_user_arr[$new_user_id])){
                $record_id = $marketplace_user_arr[$new_user_id]["id"];
                $updateData = [];
                $updateData["total_trade"] = $user_trade_count;
                $updateData["avg_rating"] = $user_average_rating;

                $db->where("id", $record_id);
                $db->update("xun_marketplace_user", $updateData);
            }else{
                $insert_data = array(
                    "user_id" => $new_user_id,
                    "total_trade" => $user_trade_count,
                    "avg_rating" => $user_average_rating,
                    "created_at" => $now,
                    "updated_at" => $now
                );

                $db->insert("xun_marketplace_user", $insert_data);
            }
        }

        $db->where("user_id", $old_user_id);
        $db->delete("xun_marketplace_user");
    }

    public function copy_xun_freecoin_payout_transaction($old_user_id, $new_user_id)
    {
        $db = $this->db;

        // $db->where("user_id", $new_user_id);
        // $db->delete("xun_freecoin_payout_transaction");

        $updateUserData = [];
        $updateUserData["user_id"] = $new_user_id;
        $db->where("user_id", $old_user_id);
        $db->update("xun_freecoin_payout_transaction", $updateUserData);
    }

    public function copy_xun_gift_code($old_user_id, $new_user_id)
    {
        $db = $this->db;

        $updateUserData = [];
        $updateUserData["redeemed_by"] = $new_user_id;
        
        $db->where("redeemed_by", $old_user_id);
        $db->update("xun_gift_code", $updateUserData);
    }

    public function copy_xun_kyc($old_user_id, $new_user_id)
    {
        $db = $this->db;

        $updateUserData = [];
        $updateUserData["user_id"] = $new_user_id;

        $db->where("user_id", $old_user_id);
        $db->update("xun_kyc", $updateUserData);
    }

    public function copy_xun_wallet_transaction($old_user_id, $new_user_id)
    {
        $db = $this->db;

        $updateUserData = [];
        $updateUserData["user_id"] = $new_user_id;

        $db->where("user_id", $old_user_id);
        $db->update("xun_wallet_transaction", $updateUserData);
    }

    public function copy_xun_user_setting($old_user_id, $new_user_id)
    {
        $db = $this->db;

        $db->where("user_id", [$old_user_id, $new_user_id], "in");
        $user_data_arr = $db->get("xun_user_setting");

        $arr_len = count($user_data_arr);
        $old_user_arr = [];
        $new_user_arr = [];
        $old_user_id_int = (int)$old_user_id;
        $new_user_id_int = (int)$new_user_id;

        for ($i = 0; $i < $arr_len; $i++)
        {
            $data = $user_data_arr[$i];
            if((int)$data["user_id"] == $old_user_id_int){
                $old_user_arr[$data["name"]] = $data;
            }else{
                $new_user_arr[$data["name"]] = $data;
            }
        }

        foreach ($old_user_arr as $name => $value)
        {
            if (isset($new_user_arr[$name]))
            {
                $record_id = $new_user_arr[$name]["id"];
                $db->where("id", $record_id);
                $db->delete("xun_user_setting");
            }
        }

        if(count($old_user_arr) > 0){
            $updateData = [];
            $updateData["user_id"] = $new_user_id;

            $db->where("user_id", $old_user_id);
            $db->update("xun_user_setting", $updateData);
        }
    }

    public function update_nickname($params)
    {
        global $xunXmpp;
        $db = $this->db;
        $general = $this->general;
        
        $username = trim($params["username"]);
        $server_host = trim($params["server"]);
        $nickname = trim($params["nickname"]);

        $updateData = [];
        $updateData["nickname"] = $nickname;
        $updateData["updated_at"] = date("Y-m-d H:i:s");

        $db->where("username", $username);
        $db->where("server_host", $server_host);
        $xun_user = $db->getOne("xun_user");
        $existing_username = $xun_user["nickname"];
        if($existing_username == $nickname){
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00144') /*Success*/);
        }
        $user_id = $xun_user["id"];

        $db->where("mobile", $username);
        $db->orderBy("id", "DESC");
        $user_type = $db->getValue("xun_user_verification", "user_type");

        $user_country_info_arr = $this->get_user_country_info([$username]);
        $user_country_info = $user_country_info_arr[$username];
        $user_country = $user_country_info["name"];

        if (!$xun_user) {
        return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00251') /*Invalid user*/);
        }
        $db->where("username", $username);
        $db->where("server_host", $server_host);
        $db->update("xun_user", $updateData);

        $device_ip_arr = $this->get_device_os_ip($user_id, $username);
        $ip = $device_ip_arr["ip"];
        $device_os = $device_ip_arr["device_os"];

        $content .= "Existing Username: " . $existing_username . "\n";
        $content .= "Current Username: " . $nickname . "\n";
        $content .= "Phone number: " . $username . "\n";
        $content .= "IP: " . $ip . "\n";
        $content .= "Country: " . $user_country . "\n";
        $content .= "Device: " . $device_os . "\n";
        $content .= "Type Of User: " . $user_type . "\n";
        $content .= "Status: Success\n";
        $content .= "Time: " . date("Y-m-d H:i:s");

        $tag = "Name Changed";
        $erlang_params = array();
        $erlang_params["tag"] = $tag;
        $erlang_params["message"] = $content;
        $erlang_params["mobile_list"] = array();
        $xmpp_result = $general->send_thenux_notification($erlang_params);        

        return array("code" => 1, "message" => "SUCCESS", "message_d" =>  $this->get_translation_message('B00144') /*Success*/);
    }

    public function update_vcard($params)
    {
        global $xunXmpp;
        $db = $this->db;
        
        $username = trim($params["username"]);
        $server_host = trim($params["server"]);
        $nickname = trim($params["nickname"]);
        $photo = $params["photo"];
        $binval = trim($photo["binval"]);
        $type = trim($photo["type"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }
        if ($server_host == '') {
        return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00253') /*Server cannot be empty*/);
        }
        
        $xun_user_service = new XunUserService($db);

        $xun_user = $xun_user_service->getUserByUsername($username);

        // $db->where("username", $username);
        // $db->where("server_host", $server_host);
        // $xun_user = $db->getOne("xun_user");
        $existing_username = $xun_user["nickname"];

        $name_changed = true;
        if($existing_username == $nickname){
            $name_changed = false;
            // return array("code" => 1, "message" => "SUCCESS", "message_d" => "Success");
        }

        //  check md5 in ejabberd if image changed
        $user_id = $xun_user["id"];
        $user_type = $xun_user["type"];

        $db->where("mobile", $username);
        $db->orderBy("id", "DESC");
        $user_type = $db->getValue("xun_user_verification", "user_type");

        $user_country_info_arr = $this->get_user_country_info([$username]);
        $user_country_info = $user_country_info_arr[$username];
        $user_country = $user_country_info["name"];

        if (!$xun_user) {
            return array("code" => 0, "message" => "FAILED", "message_d" => $this->get_translation_message('E00251') /*Invalid user*/);
        }

        //  upload photo binval to aws s3 to get the url
        $profile_picture_result = $xun_user_service->uploadProfilePictureBinval($user_id, $binval, $type, $user_type);

        $picture_url = '';
        if($profile_picture_result){
            $picture_url = $profile_picture_result["object_url"];
        }

        $date = date("Y-m-d H:i:s");

        $updateData = [];
        $updateData["nickname"] = $nickname;
        $updateData["updated_at"] = $date;

        $db->where("username", $username);
        $db->where("server_host", $server_host);
        $db->update("xun_user", $updateData);

        //  update xun_user_details
        $insert_user_details_data = Array (
            "user_id" => $user_id,
            "picture_url" => $picture_url,
            "created_at" => $date,
            "updated_at" => $date
        );
        $update_columns = Array ("picture_url", "updated_at");
        $last_insert_id = "id";
        $db->onDuplicate($update_columns, $last_insert_id);
        $user_details_id = $db->insert ('xun_user_details', $insert_user_details_data);
        if(!$user_details_id){
            // print_r($db->getLastError());
        }

        if($name_changed){
            $device_ip_arr = $this->get_device_os_ip($user_id, $username);
            $ip = $device_ip_arr["ip"];
            $device_os = $device_ip_arr["device_os"];
    
            $content .= "Existing Username: " . $existing_username . "\n";
            $content .= "Current Username: " . $nickname . "\n";
            $content .= "Phone number: " . $username . "\n";
            $content .= "IP: " . $ip . "\n";
            $content .= "Country: " . $user_country . "\n";
            $content .= "Device: " . $device_os . "\n";
            $content .= "Type Of User: " . $user_type . "\n";
            $content .= "Status: Success\n";
            $content .= "Time: " . date("Y-m-d H:i:s");
    
            $tag = "Name Changed";
            $erlang_params = array();
            $erlang_params["tag"] = $tag;
            $erlang_params["message"] = $content;
            $erlang_params["mobile_list"] = array();
            $xmpp_result = $xunXmpp->send_xmpp_notification($erlang_params);        
        }

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00144') /*Success*/);
    }

    public function get_user_qr_code_string($params)
    {
        global $setting, $config;

        $db = $this->db;

        $username = trim($params["username"]);

        if ($username == '') {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00130') /*username cannot be empty.*/);
        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user", "id, username");

        if (!$xun_user) {
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $translations['E00025'][$language]/*User does not exist.*/);
        }

        $key = $setting->systemSetting["userQRCodeKey"];
        $cipher_method = $setting->systemSetting["userQRCodeCipherMethod"];

        $user_id = $xun_user["id"];
        $encrypted_string = openssl_encrypt($user_id, $cipher_method, $key);

        $server = $config["server"];
        $data = array("ref" => $encrypted_string);

        $url = "https://" . $server . "/referral?ref=" . $encrypted_string;

        return array("code" => 1, "message" => "SUCCESS", "message_d" => $this->get_translation_message('B00146')/*User URL*/, "data" => array("url" => $url));
    }

    public function get_translation_message($message_code)
    {
        // Language Translations.
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;
    }

    public function get_user_country_info($username_arr){
        $db = $this->db;
        $general = $this->general;

        if(!is_array($username_arr)){
            $username_arr = [$username_arr];
        }
        
        $username_arr_len = count($username_arr);
        $region_code_arr = [];
        $user_country_arr = [];
        for($i = 0; $i < $username_arr_len; $i++){
            $mobileNumberInfo = $general->mobileNumberInfo($username_arr[$i], null);
            $region_code = $mobileNumberInfo["regionCode"];

            $region_code_arr[] = $region_code;
            $user_country_arr[$username_arr[$i]] = $region_code;
        }
        
        if (!empty($region_code_arr)){
            $region_code_arr = array_unique($region_code_arr);
            $db->where("iso_code2", $region_code_arr, "in");
            $country_arr = $db->map("iso_code2")->ObjectBuilder()->get("country");
        }

        $final_arr = array();
        foreach($user_country_arr as $key => $value){
            $final_arr[$key] = (array)$country_arr[$value];
        }

        return $final_arr;
    }

    public function send_push_notification($username, $payload, $isVoip = false){
        global $setting;
        $db = $this->db;
        $post = $this->post;
        
        $db->where("mobile_number", $username);
        $user_device = $db->getOne("xun_user_device", "mobile_number, os, access_token, voip_access_token");

        if(!$user_device){
            return;
        }

        $device_os = $user_device["os"];

        if($device_os == 1){
            $access_token = $user_device["access_token"];
            $push = new pushNotification($setting, $post, "android");
            $res = $push->sendMessage($access_token, $payload);
                
        }else if($device_os == 2){
            $access_token = $isVoip ? $user_device["voip_access_token"] : $user_device["access_token"];

            $push = new pushNotification($setting, $post, "ios", $isVoip);

            $payload_arr = array("aps" => array("content-available" => 1), "data" => $payload);
            $payload_str = json_encode($payload_arr);

            $res = $push->sendMessage($access_token, $payload_str);
        }
        return $res;
    }

    public function get_user_device_by_username($username){
        $db = $this->db;
        
        $db->where("mobile_number", $username);
        $user_device = $db->getOne("xun_user_device", "mobile_number, os, access_token, voip_access_token");
        
        return $user_device;
    }

    public function get_user_data_by_user_id($user_id, $columns){
        $db = $this->db;

        $db->where("id", $user_id);
        $xun_user = $db->getOne("xun_user", $columns);
        return $xun_user;
    }

    public function get_user_data_by_username($username, $columns){
        $db = $this->db;

        $db->where("username", $username);
        $xun_user = $db->getOne("xun_user", $columns);
        return $xun_user;
    }


    public function get_device_os_ip($user_id, $username)
    {
        $db = $this->db;

        $db->where("user_id", $user_id);
        $db->where("name", "lastLoginIP");
        $user_ip = $db->getValue("xun_user_setting", "value");

        $user_device_os = $db->where("mobile_number",$username)->getValue("xun_user_device", "os");
        $user_device_os = $user_device_os == 1 ? $user_device_os = "Android" : $user_device_os = "iOS";
        return array("ip" => $user_ip, "device_os" => $user_device_os);
    }

    public function update_user_first_time_business_skip($params) {
        $db= $this->db;

        $business_id = trim($params['business_id']);
        
        if($business_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
        }

        $updateArray = array(
            "nickname" => '',
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('id', $business_id);
        $updated = $db->update('xun_user', $updateArray);

        if(!$updated){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        $updateBusiness = array(
            "name" => '',
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('user_id', $business_id);
        $updated = $db->update('xun_business', $updateBusiness);
       
        if(!$updated){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }
        
        $updateUserSetting = array(
            "value" => 1,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('user_id', $business_id);
        $db->where('name', 'hasChangedPassword');
        $updated = $db->update('xun_user_setting', $updateUserSetting);
        
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00351') /* Update First Time User Details Successfully */);
    }

    public function update_user_first_time_business($params) {
        $db= $this->db;

        $business_id = trim($params['business_id']);
        $business_name = trim($params['business_name']);
        $business_info = trim($params['business_info']);
        $business_website = trim($params['business_website']);

        if($business_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        // if($business_name == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00015') /*Business name cannot be empty.*/);
        // }

        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
        }

        $updateArray = array(
            "nickname" => $business_name,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('id', $business_id);
        $updated = $db->update('xun_user', $updateArray);

        if(!$updated){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        $updateBusiness = array(
            "name" => $business_name,
            "website" => $business_website,
            "info" => $business_info,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('user_id', $business_id);
        $updated = $db->update('xun_business', $updateBusiness);
       
        if(!$updated){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }
        
        $updateUserSetting = array(
            "value" => 1,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('user_id', $business_id);
        $db->where('name', 'hasChangedPassword');
        $updated = $db->update('xun_user_setting', $updateUserSetting);
        
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00351') /* Update First Time User Details Successfully */);
    }

    public function update_user_first_time_info($params){
        $db= $this->db;

        $business_id = trim($params['business_id']);
        $business_name = trim($params['business_name']);
        $business_info = trim($params['business_info']);
        $business_website = trim($params['business_website']);

        $password = trim($params['password']);
        $confirm_password = trim($params['confirm_password']);

        if($business_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        // if($business_name == ''){
        //     return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00015') /*Business name cannot be empty.*/);
        // }

        if($password == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00014') /*Password cannot be empty.*/, 'developer_msg' => 'Password cannot be empty.');
        }

        if($confirm_password == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00068') /*Confirm password cannot be empty*/, 'developer_msg' => 'Confirm password cannot be empty');
        }
        
        if($password != $confirm_password){
            return array('code' => 0, 'message' => "FAILED", "message_d" => $this->get_translation_message('E00243')/*Password not match.*/);
        }

        $db->where('id', $business_id);
        $xun_user = $db->getOne('xun_user');

        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00415') /*User not found.*/);
        }

        $hash_password = password_hash($password, PASSWORD_BCRYPT);

        $updateArray = array(
            "nickname" => $business_name,
            "web_password" => $hash_password,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('id', $business_id);
        $updated = $db->update('xun_user', $updateArray);

        if(!$updated){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        $updateBusiness = array(
            "password" => $hash_password,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('user_id', $business_id);
        $updatedBusiness = $db->update('xun_business_account', $updateBusiness);

        if(!$updatedBusiness){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }

        $updateBusiness = array(
            "name" => $business_name,
            "website" => $business_website,
            "info" => $business_info,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('user_id', $business_id);
        $updated = $db->update('xun_business', $updateBusiness);
       
        if(!$updated){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
        }
        
        $updateUserSetting = array(
            "value" => 1,
            "updated_at" => date("Y-m-d H:i:s")
        );

        $db->where('user_id', $business_id);
        $db->where('name', 'hasChangedPassword');
        $updated = $db->update('xun_user_setting', $updateUserSetting);
        
        return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00351') /* Update First Time User Details Successfully */);

    }

    public function request_fund_check_user_exist($params){
        
        $db = $this->db;
        $general = $this->general;

        $payee_mobile = $params['payee_mobile'];
        $payee_email = $params['payee_email'];

        if($payee_mobile) {
            $db->where("username", $payee_mobile);
            $db->where('type', 'business');
            $mobile_exist = $db->getOne("xun_user", "username, id, nickname");
            $user_mobile = $mobile_exist["username"];        
            $user_id = $mobile_exist["id"];
            $user_nickname = $mobile_exist['nickname'];


            $countryCode = "";
            $mobileNumber = "";
            $validMobile = 0;
        
            $mobileNumberInfo = $general->mobileNumberInfo($payee_mobile, null);
            if($mobileNumberInfo['isValid']==1) {
                $countryCode = "+".$mobileNumberInfo['countryCode'];
                $mobileNumberWithoutFormat = $mobileNumberInfo['mobileNumberWithoutFormat'];
                $mobileNumber = substr($mobileNumberWithoutFormat, strlen($mobileNumberInfo['countryCode']));
                $validMobile = 1;
            } 

            if($user_id == ''){
                return array("code" => 1, "message" => "User does not exists", "message_d" => "User does not exists", 'mobileData'=>array("validMobile"=>$validMobile, "mobileNumber"=>$mobileNumber, "countryCode"=>$countryCode));
            }
            else{
                return array("code" => 0, "message" => "User exists", "message_d" => "User exists", "data"=>array("nickname"=>$user_nickname, "user_id"=>$user_id),'mobileData'=>array("validMobile"=>$validMobile, "mobileNumber"=>$mobileNumber, "countryCode"=>$countryCode));
            }
        }

        if($payee_email){
            $db->where("email", $payee_email);
            $db->where('type', 'business');
            $email_exist = $db->getOne("xun_user", "email, id, nickname");
            $user_email = $email_exist["email"];
            $user_id = $email_exist["id"];
            $user_nickname = $email_exist['nickname'];

            if($user_id=="") {
                return array("code" => 1, "message" => "User does not exists", "message_d" => "User does not exists", 'email'=>$payee_email);
            } else {
                return array("code" => 0, "message" => "User exists", "message_d" => "User exists", "data"=>array("nickname"=>$user_nickname, "user_id"=>$user_id),'email'=> $payee_email);
            }

        }
        
        return array("code" => 1, "message" => "User does not exists", "message_d" => "User does not exists");
    }

    public function upgrade_user_account_type($params){

        $db = $this->db;

        $business_id    = $params['business_id'];
        
        if($business_id == ''){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00002') /*Business ID cannot be empty*/);
        }

        $db->where("user_id", $business_id);
        $userDetails = $db->getOne("xun_business_account", "account_type");
        $userAccType = $userDetails["account_type"];

        if($userAccType == "premium"){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00591') /*Account type is already Premium.*/);
        }else{

            $updateUserAccountType = array(
                "account_type" => "premium",
                "upgraded_date" => date("Y-m-d H:i:s")
            );
    
            $db->where("user_id", $business_id);
            $db->where("account_type", "basic");
            $updatedAccType = $db->update("xun_business_account", $updateUserAccountType);
    
            if($updatedAccType){
                return array('code' => 1, 'message' => "SUCCESS", 'message_d' => $this->get_translation_message('B00352') /* Account Upgraded to Premium Successfully */);
            }else{
                return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00200') /*Something went wrong. Please try again.*/, "developer_msg" => $db->getLastError());
            }

        }  
    }
}
