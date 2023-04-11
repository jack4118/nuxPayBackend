<?php

    $currentPath = __DIR__;
    include_once $currentPath . "/../include/config.php";
    include_once $currentPath . "/../include/class.database.php";
    include_once $currentPath . "/../include/class.post.php";
    include_once $currentPath . "/../include/class.xun_xmpp.php";

    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

    $post          = new post();
    $xunXmpp       = new XunXmpp($db, $post);

    $date = $argv[1];

    if($date){
        $startdate = date("Y-m-d 00:00:00", strtotime($date));
        $enddate = date("Y-m-d 23:59:59", strtotime($date));
    }else{
        $startdate = date("Y-m-d 00:00:00", strtotime("-1 day"));
        $enddate = date("Y-m-d 23:59:59", strtotime("-1 day"));
    }

    $db->where("updated_at", $startdate, ">=");
    $db->where("updated_at", $enddate, "<=");
    $updated_device_result = $db->get("xun_user_device");

    $total_online = count($updated_device_result);

    foreach($updated_device_result as $data){
        
        if($data["os"] == 1){
            $total_android += 1;
        }else{
            $total_ios += 1;
        }
        
    }

    $db->where("verify_at", $startdate, ">=");
    $db->where("verify_at", $enddate, "<=");
    $db->where("is_verified", "1");
    $db->where("status", "success");
    $db->orderBy("verify_at", "ASC");
    $updated_device_result = $db->get("xun_user_verification");

    foreach($updated_device_result as $data){

	if(strtolower($data["user_type"]) == "new"){
	    $total_new += 1;
	}else{
	    $total_return += 1;

	    if(!in_array($unique_user_array, $data["mobile"])){
                $unique_user_array[] = $data["mobile"]; 
                $total_unique_return += 1;
       	    }
	}

    }

    $db->where("request_at", $startdate, ">=");
    $db->where("request_at", $enddate, "<=");
    $db->orWhere("verify_at", $startdate, ">=");
    $db->where("verify_at", $enddate, "<=");
    $db->orderBy("verification_code", "ASC");
    $verify_result = $db->get("xun_user_verification");

    $total_verify = count($verify_result);

    $total_verified = 0;
    $total_not_verified = 0;
    $total_request_sent = 0;
    $total_verification_failed = 0;

    foreach($verify_result as $data){
        
        if(!$current_code){ 
            $current_code = $data["verification_code"];
        }
        
        if($current_code != $data["verification_code"]){
            $current_code = $data["verification_code"];
            
            if($is_verified){
                $total_verified++;
            }else{
                $total_not_verified++;
            }
            
        }
        
        $is_verified = $data["is_verified"];
        
        $request_at = $data["request_at"];
        $verify_at  = $data["verify_at"];
        $status     = $data["status"];
        
        if($request_at && $status == 'success'){
            $total_request_sent++;
        }
        
        if($verify_at && $status == 'failed'){
            $total_verification_failed++;
        }
        
        if(!next($verify_result)){
            if($verify_at){
                if($is_verified){
                    $total_verified++;
                }else{
                    $total_not_verified++;
                }
            }
        }
        
    }

    $message .= "Total online user: $total_online\n";
    $message .= "Number of IOS User: $total_ios\n";
    $message .= "Number of Android User: $total_android\n\n";
    $message .= "Daily Installation:\n";
    $message .= "Type of New User(daily)\n";
    $message .= "New: $total_new\n";
    $message .= "Return: $total_return\n";
    $message .= "Unique Return: $total_unique_return\n";
    $message .= "Number of SMS verification code sent: $total_request_sent\n";
    $message .= "Success verified: $total_verified\n";
    $message .= "Wrong verification attempt: $total_verification_failed\n";
    $message .= "Not verified : $total_not_verified\n";
    $message .= "\n\nTime: ".date("Y-m-d H:i:s");

    $erlang_params["tag"]         = "Daily Summary";
    $erlang_params["message"]     = $message;
    $erlang_params["mobile_list"] = array();
    $xmpp_result                  = $xunXmpp->send_xmpp_notification($erlang_params);
 

    //echo $message."\n";
?>
