<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the API Related Database code.
 * Date  29/06/2017.
 **/

class XunKYC
{

    public function __construct($db, $setting, $general)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
    }

	public function submit_kyc_document($params) {


        global $config, $xunXmpp;

        $db = $this->db;
       

        $country = trim($params["country"]);
        $given_name = trim($params["given_name"]);
        $surname = trim($params["surname"]);
        $document_type = trim($params["document_type"]);
        $document_id = trim($params["document_id"]);
        $s3_link = trim($params["s3_link"]);
		$username = trim($params["username"]);

		if ($country == '') {
            //$error_message = $this->get_translation_message('E00026');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "country cannot be empty", 'developer_msg' => "country cannot be empty");
        
		} else if ($given_name == '') {
            //$error_message = $this->get_translation_message('E00021');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "given_name cannot be empty", 'developer_msg' => "given_name cannot be empty");
        
		} else if ($surname == '') {
            //$error_message = $this->get_translation_message('E00022');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "surname cannot be empty", 'developer_msg' => "surname cannot be empty");
        
		} else if ($document_type == '') {
            //$error_message = $this->get_translation_message('E00023');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "document_type cannot be empty", 'developer_msg' => "document_type cannot be empty");
        
		} else if ($document_id == '') {
            //$error_message = $this->get_translation_message('E000024');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "document_id cannot be empty", 'developer_msg' => "document_id cannot be empty");
        
		} else if ($s3_link == '') {
            //$error_message = $this->get_translation_message('E00025');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "s3_link cannot be empty", 'developer_msg' => "s3_link cannot be empty");
        
		} else if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username cannot be empty", 'developer_msg' => "username cannot be empty");

        } else {
			//echo "dd";
	        $db->where("username", $username);
    	    $db->where("disabled", 0);
        	$xun_user = $db->getOne("xun_user");

			if ( count($xun_user) == 0) {
				//$error_message = $this->get_translation_message('E00457');  //User doesn't exist
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "User doesn't exist");

			} else {

				$user_id = $xun_user["id"];
                $nickname = $xun_user["nickname"];
                $date = date("Y-m-d H:i:s");

				$db->where("user_id", $user_id);
                $db->where("status", "rejected", "!=");
                $kyc_record = $db->getOne("xun_kyc");
                
                $device_os = $db->where("mobile_number", $username)->getValue("xun_user_device", "os");
                if ($device_os == 1){ $device_os = "Android";}
                else if ($device_os == 2) { $device_os = "iOS";}

                $db->where("user_id", $user_id);
                $ip = $db->where("name", "lastLoginIP")->getValue("xun_user_setting", "value");

				if ($kyc_record) {
                    $kyc_country = $kyc_record["country"];
                    $last_name = $kyc_record["surname"];
                    $first_name = $kyc_record["given_name"];
                    $kyc_doc_id = $kyc_record["document_id"];
                    $kyc_status = $kyc_record["status"];

                    $msg = "Username: $nickname\n";
                    $msg .= "Phone number: $username\n";
                    $msg .= "IP: " . $ip . "\n";
                    $msg .= "Country: " . $kyc_country . "\n";
                    $msg .= "Device: " . $device_os . "\n";
                    //$msg .= "Status: Failed\n";
                    //$msg .= "Current KYC status: $kyc_status\n";
                    $msg .= "\nFirst Name: " . $first_name . "\n";
                    $msg .= "Last Name: " . $last_name . "\n";
                    $msg .= "ID Number: " . $kyc_doc_id . "\n";
                    $msg .= "Time: $date\n";
                    $erlang_params["tag"]         = "New KYC Submission";
                    $erlang_params["message"]     = $msg;
                    $erlang_params["mobile_list"] = array();
                    $xmpp_result                  = $xunXmpp->send_xmpp_notification($erlang_params);
                    
		            return array('code' => 0, 'message' => "FAILED", 'message_d' => "invalid operation", 'developer_msg' => "current kyc status - " . $kyc_status);

				} else {
		
					$fields = array("user_id", "country", "given_name", "surname", "document_type", "document_id", "s3_link");
    	            $values = array($user_id, $country, $given_name, $surname, $document_type, $document_id, $s3_link);
	                $arrayData = array_combine($fields, $values);
        	        $db->insert("xun_kyc", $arrayData);

                    $msg = "Username: $nickname\n";
                    $msg .= "Phone number: $username\n";
                    $msg .= "IP: " . $ip . "\n";
                    $msg .= "Country: " . $country . "\n";
                    $msg .= "Device: " . $device_os . "\n";
                    //$msg .= "Status: Success\n";
                    $msg .= "\nFirst Name: " . $given_name . "\n";
                    $msg .= "Last Name: " . $surname . "\n";
                    $msg .= "ID Number: " . $document_id . "\n";
                    $msg .= "Time: $date\n";
                    $erlang_params["tag"]         = "New KYC Submission";
                    $erlang_params["message"]     = $msg;
                    $erlang_params["mobile_list"] = array();
                    $xmpp_result                  = $xunXmpp->send_xmpp_notification($erlang_params);

					return array("message_d" => "success", "message" => "SUCCESS", "code" => 1);

				}

			}


		}

	}

	public function get_kyc_status($param) {


        global $config;

        $db = $this->db;

        $username = trim($param["username"]);

		if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username cannot be empty", 'developer_msg' => "username cannot be empty");

        } else {

			$db->where("username", $username);
            $db->where("disabled", 0);
            $xun_user = $db->getOne("xun_user");

            if ( count($xun_user) == 0) {
                //$error_message = $this->get_translation_message('E00457');  //User doesn't exist
                return array('code' => 0, 'message' => "FAILED", 'message_d' => "User doesn't exist");

            } else {

                $user_id = $xun_user["id"];

				$db->where("user_id", $user_id);
				$db->orderby("id", "desc");	
                //$db->where("status", "rejected", "!=");

                $kyc_record = $db->getOne("xun_kyc");

                if ($kyc_record) {
                    $kyc_status = $kyc_record["status"];
                    return array('code' => 1, 'message' => "Success", 'message_d' => "Success", 'kyc_status'=> $kyc_status);

                } else {

                    return array("message_d" => "KYC Not Found", "message" => "Failed", "code" => 0);

                }
				

			}
			
		}


	}

    public function request_kyc_document_upload_link($params){

        global $xunAws;

        $setting = $this->setting;
        $db = $this->db;

        $username = trim($params["username"]);
        $file_name = trim($params["file_name"]);
        $content_type = trim($params["content_type"]);
        $content_size = trim($params["content_size"]);

        if ($username == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "username cannot be empty", 'developer_msg' => "username cannot be empty");

        }
        if ($file_name == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "file_name cannot be empty", 'developer_msg' => "file_name cannot be empty");

        }
        if ($content_type == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "content_type cannot be empty", 'developer_msg' => "content_type cannot be empty");

        }
        if ($content_size == '') {
            //$error_message = $this->get_translation_message('E00027');
            return array('code' => 0, 'message' => "FAILED", 'message_d' => "content_size cannot be empty", 'developer_msg' => "content_size cannot be empty");

        }

        $db->where("username", $username);
        $db->where("disabled", 0);

        $xun_user = $db->getOne("xun_user", "id, username, nickname");
        if(!$xun_user){
            return array('code' => 0, 'message' => "FAILED", 'message_d' => $this->get_translation_message('E00025') /*User does not exist.*/);
        }

        $user_id = $xun_user["id"];
        $bucket = $setting->systemSetting["awsS3KYCDocumentBucket"];
        $s3_folder = 'kyc';
        $timestamp = time();
        $presigned_url_key = $s3_folder . '/' . $user_id . '/' . $timestamp . '/' . $file_name;
        $expiration = '+20 minutes';

        $newParams = array(
            "s3_bucket" => $bucket,
            "s3_file_key" => $presigned_url_key,
            "content_type" => $content_type,
            "content_size" => $content_size,
            "expiration" => $expiration
        );

        $result = $xunAws->generate_put_presign_url($newParams);

        if(isset($result["error"])){
            return array("code" => 1, "message" => "FAILED", "message_d" => "Error generating AWS S3 presigned URL.", "errorMsg" => $result["error"]);
        }

        $return_message = "AWS presigned url.";
        return array("code" => 1, "message" => "SUCCESS", "message_d" => $return_message, "data" => $result);
    }

	public function get_translation_message($message_code)
    {
        // Language Translations.
        $language = $this->general->getCurrentLanguage();
        $translations = $this->general->getTranslations();

        $message = $translations[$message_code][$language];
        return $message;

    }

}
