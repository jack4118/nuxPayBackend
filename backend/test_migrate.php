<?php

	include("include/config.php");
	include("include/class.database.php");

	$db              = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);

	$json_data       = json_decode(file_get_contents('php://input'), true);

	$table_name = $json_data["table_name"];
	$row_string = $json_data["row_string"];

	switch($table_name){

		case "xun_user":
            
            //List = [Username, ServerHost, DisplayName, IsEnabled, Language, BadgeCount, ContactList, BlockList, WebPassword,  Role, CreatedDate, ModifiedDate],
			$username = $row_string[0];
            $server_host = $row_string[1];
            $disabled = $row_string[3];
            $web_password = $row_string[8];
            $role = $row_string[9];
            if($row_string[10] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[10]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			
			if($row_string[11] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[11]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}

    		$fields = array("username", "server_host", "disabled", "web_password", "role", "created_at", "updated_at");
    		$values = array($username, $server_host, $disabled, $web_password, $role, $created_at, $updated_at);

            $insertData = array_combine($fields, $values);

			$db->insert("xun_user", $insertData);

			break;

		case "passwd":

			$fields = array("username", "server_host", "password");
            $values = array($row_string[0], $row_string[1], $row_string[2]);

            $insertData = array_combine($fields, $values);

            $db->insert("xun_passwd", $insertData);

			break;

		case "xun_business":
			
			//[UUID, Email, Name, Phone, Website, Addr1, Addr2, City, State, Postal, Country, Info, ProfilePic, ProfilePicUrl, Verified, Status, CompanySize, EmailAddr, ContactUsUrl, AutoRenewal, IsEncrypted, IsUpdated, CreatedDate, ModifiedDate]
            
            if($row_string[1] == 'email.com' && $row_string[2] == 'asdasdasdads') break;
            
			if($row_string[22] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[22]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			
			if($row_string[23] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[23]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}

			if($row_string[20] == 'undefined') $row_string[20] = 0;
			if($row_string[21] == 'undefined') $row_string[21] = 0;

			$fields = array("id", "email", "name", "phone_number", "website", "address1", "address2", "city", "state", "postal", "country", "info", "profile_picture", "profile_picture_url", "verified", "company_size", "display_email", "contact_us_url", "subscription_auto_renewal", "is_encrypted", "is_updated", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $row_string[6], $row_string[7], $row_string[8], $row_string[9], $row_string[10], $row_string[11], $row_string[12], $row_string[13], $row_string[14], $row_string[16], $row_string[17], $row_string[18], $row_string[19], $row_string[20], $row_string[21], $created_at, $updated_at);

			$insertData = array_combine($fields, $values);

            $db->insert("xun_business", $insertData);

			break;

		case "xun_business_api_key":

			//List = [Api_UUID, Business_UUID, Name, ExpiredDate, IsEnabled, Status, CreatedDate, ModifiedDate],
			if($row_string[3] == 'undefined') 
				$expired_at = '';
			else{
				$expired_at = new DateTime($row_string[3]);
                $expired_at = $expired_at->format('Y-m-d H:i:s');
                $expired_at = date("Y-m-d H:i:s", strtotime($expired_at."+8 hours"));
			}

			if($row_string[6] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[6]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}

			if($row_string[7] == 'undefined'){
				$updated_at = '';
			}else{	
				$updated_at = new DateTime($row_string[7]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}

            $fields = array("apikey", "business_id", "apikey_name", "apikey_expire_datetime", "is_enabled", "status", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $expired_at, $row_string[4], $row_string[5] == "1"? "active":"inactive", $created_at, $updated_at);

            $insertData = array_combine($fields, $values);

            $db->insert("xun_business_api_key", $insertData);

			break;

        case "xun_business_follow":

			//List = [UUID, Business_UUID, Username, ServerHost],
			//i	old_id	 business_id	username	server_host	created_at	updated_at
            $fields = array("old_id", "business_id", "username", "server_host", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));

            $insertData = array_combine($fields, $values);

            $db->insert("xun_business_follow", $insertData);
                        
			break;

        case "xun_business_follow_message":

            //List = [Username, Business_UUID, CreatedDate],
            //id	business_id	username	created_at	updated_at
            if($row_string[3] == 'undefined'){
                $created_at = '';
            }else{
                $created_at = new DateTime($row_string[3]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
            }

            $fields = array("business_id", "username", "created_at", "updated_at");
            $values = array($row_string[1], $row_string[0], $created_at, $created_at);

            $insertData = array_combine($fields, $values);

            $db->insert("xun_business_follow_message", $insertData);

            break;
            
        case "xun_business_account":
            //id	email	password	email_verified	main_mobile	main_mobile_verified	status	description	referral_code	time_zone	last_login	created_at	updated_at
            //List = [BusinessEmail, BusinessPassword, BusinessEmailVerified, BusinessMainMobile, BusinessMainMobileVerified,
            //            BusinessStatus, BusinessDescription, ReferralCode, Timezone, BusinessCreatedDate, BusinessModifiedDate],
		    if($row_string[9] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[9]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[10] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[10]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}
			$fields = array("email", "email_verified", "main_mobile", "main_mobile_verified", "status", "description", "referral_code", "time_zone", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $row_string[6], $row_string[7], $row_string[8], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);
			print_r($fields);
			print_r($values);
			print_r($insertData);
			$db->insert("xun_business_account", $insertData);
		
			break;
            
        case "xun_user_blocked_business":
            //id	business_id	user_id	status	created_at	updated_at	mobile_number
            //List = [UUID, User_Username, Business_UUID, Status, CreatedDate, ModifiedDate],
		    if($row_string[4] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[4]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[5] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[5]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}
			$fields = array("business_id", "user_id", "status", "created_at", "updated_at", "mobile_number");
			$values = array($row_string[2], $row_string[1], $row_string[3], $created_at, $updated_at, $row_string[1]);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_business_block", $insertData);
		
			break;
            
        case "xun_business_contact_group":
            //id	business_id	name	status	created_date	modified_date
            //List = [UUID, Name, Business_UUID, Status, CreatedDate, ModifiedDate],
		    if($row_string[4] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[4]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[5] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[5]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}
			$fields = array("old_id", "business_id", "name", "status", "created_date", "modified_date");
			$values = array($row_string[0], $row_string[2], $row_string[1], $row_string[3], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_business_contact_group", $insertData);
		
			break;
            
        case "xun_business_contact_group_member":
            
            //id	business_id	contact_group_id	contact_mobile	contact_name	status	created_date	modified_date
            //List = [UUID, ContactGroup, Business_UUID, ContactMobile, ContactName, Status, CreatedDate, ModifiedDate],
		    if($row_string[6] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[6]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[7] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[7]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}
			$fields = array("business_id", "contact_group_id", "contact_mobile", "contact_name", "status", "created_date", "modified_date");
			$values = array($row_string[2], $row_string[1], $row_string[3], $row_string[4], $row_string[5], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_business_contact_group_member", $insertData);
            
            break;
            
        case "live_chat_setting":
            
            //id	business_id	contact_group_id	contact_mobile	contact_name	status	created_date	modified_date
            //List = [Business_UUID, ContactUsUrl, WebsiteUrl, Livechatnoagentmsg, Livechatafterworkinghoursmsg, Livechatfirstmsg,
            //        Livechatprompt, Livechatinfo, CreatedDate, ModifiedDate],
		    if($row_string[8] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[8]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
            
			if($row_string[9] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[9]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}
            
			$fields = array("business_id", "contact_us_url", "website_url", "live_chat_no_agent_msg", "live_chat_after_working_hrs_msg", "live_chat_first_msg", "live_chat_prompt", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $row_string[6], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$insertId = $db->insert("xun_business_livechat_setting", $insertData);
            
                
            //	id	business_id	livechat_setting_id	live_chat_info	created_at	type	updated_at

            $fields = array("business_id", "livechat_setting_id", "live_chat_info", "created_at", "type", "updated_at");
            $values = array($row_string[0], $insertId, $row_string[7][0], $created_at, "name", $updated_at);
            $insertData = array_combine($fields, $values);

            $db->insert("xun_business_livechat_setting_livechat_info", $insertData);

            $fields = array("business_id", "livechat_setting_id", "live_chat_info", "created_at", "type", "updated_at");
            $values = array($row_string[0], $insertId, $row_string[7][1], $created_at, "email", $updated_at);
            $insertData = array_combine($fields, $values);

            $db->insert("xun_business_livechat_setting_livechat_info", $insertData);
                
            
            break;
            
        case "xun_livechat":
            
            //	id	business_id	tag	description	working_hour_from	working_hour_to	status	priority	created_at	updated_at
            //List = [Business_UUID, Tag, Description, WorkingHourFrom, WorkingHourTo, Status, CreatedDate, ModifiedDate, Priority],
            $working_hour_from = $row_string[3];
            if($row_string[3] == 'H:M:S'){
				$working_hour_from = '';
			}
            
            $working_hour_to = $row_string[4];
			if($row_string[4] == 'H:M:S'){
				$working_hour_to = '';
			}
            
		    if($row_string[6] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[6]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[7] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[7]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}
			$fields = array("business_id", "tag", "description", "working_hour_from", "working_hour_to", "status", "created_at", "updated_at", "priority");
			$values = array($row_string[0], $row_string[1], $row_string[2], $working_hour_from, $working_hour_to, $row_string[5], $created_at, $updated_at, $row_string[8]);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_business_tag", $insertData);
            
            break;
            
        case "xun_livechat_user":
            
            //id	employee_id	username	business_id	tag	status	created_at	updated_at
            //List = [Employee_UUID, Username, ServerHost, Business_UUID, Tag, Status, CreatedDate, ModifiedDate],
            
		    if($row_string[6] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[6]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[7] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[7]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}
            
			$fields = array("employee_id", "username", "business_id", "tag", "status", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[3], $row_string[4], $row_string[5], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_business_tag_employee", $insertData);
            
            break;
            
        case "xun_employee":
            
            //id	business_id	mobile	name	status	employment_status	created_at	updated_at	old_id	role
            //List = [Employee_UUID, Business_UUID, Username, Employee_mobile, Employee_name, Employee_role, Employee_status, Employment_status, CreatedDate, ModifiedDate],
		    if($row_string[8] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[8]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[9] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[9]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}
            
			$fields = array("business_id", "mobile", "name", "status", "employment_status", "created_at", "updated_at", "old_id", "role");
			$values = array($row_string[1], $row_string[3], $row_string[4], $row_string[6], $row_string[7], $created_at, $updated_at, $row_string[0], $row_string[5]);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_employee", $insertData);
            
            break;
            
        case "xun_publish_message_log":
            
            //id	apikey_id	business_id	sent_datetime	request_mobile_length	sent_mobile_length	tag	valid_mobile_list	invalid_mobile_list
            //List = [UUID, Api_UUID, Business_UUID, SentDate, RequestMobileLength, SentMobileLength, Tag, ValidMobileList, InvalidMobileList],
		    if($row_string[3] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[3]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
            
            foreach($row_string[7] as $valid_mobile){
                if($valid_numbers) $valid_numbers .= "##$valid_mobile";
                else $valid_numbers = $valid_mobile;
            }
            
            foreach($row_string[8] as $invalid_mobile){
                if($invalid_numbers) $invalid_numbers .= "##$invalid_mobile";
                else $invalid_numbers = $invalid_mobile;
            }
            
			$fields = array("apikey_id", "business_id", "sent_datetime", "request_mobile_length", "sent_mobile_length", "tag", "valid_mobile_list", "invalid_mobile_list");
			$values = array($row_string[1], $row_string[2], $created_at, $row_string[4], $row_string[5], $row_string[6], $valid_numbers, $invalid_numbers);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_publish_message_log", $insertData);
            
            break;
            
        case "xun_business_package_subscription":
            
            //List = [UUID, Business_UUID, PackageCode, Billing_UUID, MessageLimit, Status, StartDate, EndDate, CreatedDate, ModifiedDate],
            //id	business_id	package_code	billing_id	message_limit	status	startdate	enddate	created_at	updated_at
		    if($row_string[6] == 'undefined'){
				$start_at = '';
			}else{
				$start_at = new DateTime($row_string[6]);
                $start_at = $start_at->format('Y-m-d H:i:s');
                $start_at = date("Y-m-d H:i:s", strtotime($start_at."+8 hours"));
			}
			if($row_string[7] == 'undefined'){
				$end_at = '';
			}else{
				$end_at = new DateTime($row_string[7]);
                $end_at = $end_at->format('Y-m-d H:i:s');
                $end_at = date("Y-m-d H:i:s", strtotime($end_at."+8 hours"));
			}
            
            if($row_string[8] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[8]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[9] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[9]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}
            
            $fields = array("business_id", "package_code", "billing_id", "message_limit", "status", "startdate", "enddate", "created_at", "updated_at");
            $arrays = array($row_string[1], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $start_at, $end_at, $created_at, $updated_at);
            
            $insertData = array_combine($fields, $arrays);
            
            $db->insert("xun_business_package_subscription", $insertData);
            
            break;
            
        case "xun_business_forward_message":
            
            //id	tag	business_id	forward_url	status	created_at	updated_at
            //List = [Business_UUID, Tag, ForwardUrl, Status, CreatedDate, ModifiedDate],
		    if($row_string[4] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[4]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[5] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[5]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}            
            
			$fields = array("tag", "business_id", "forward_url", "status", "created_at", "updated_at");
			$values = array($row_string[1], $row_string[0], $row_string[2], $row_string[3], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_business_forward_message", $insertData);
            
            break;
            
        case "xun_package":
            
            //id	old_id	code	description	type	duration	price	currency	rank	message_limit	send_all	is_encrypted	status	created_at	updated_at
            //List = [Code, Description, Type, Duration, Price, Currency, StripeId, Rank, MessageLimit, SendAll, Encrypted, Status, CreatedDate, ModifiedDate],
		    if($row_string[12] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[12]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[13] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[13]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}            
            
			$fields = array("code", "description", "type", "duration", "price", "currency", "rank", "message_limit", "send_all", "is_encrypted", "status", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $row_string[7], $row_string[8], $row_string[9], $row_string[10], $row_string[11], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_business_package", $insertData);
            
            break;
            
        case "xun_business_billing_info":
            
            //id	old_id	business_id	first_name	last_name	address	postal	city	state	country	created_at	updated_at
            //List = [UUID, Business_UUID, FirstName, LastName, Address, Postal, City, State, Country, CreatedDate, ModifiedDate],
		    if($row_string[9] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[9]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[10] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[10]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}            
            
			$fields = array("old_id", "business_id", "first_name", "last_name", "address", "postal", "city", "state", "country", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $row_string[6], $row_string[7], $row_string[8], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_business_billing_info", $insertData);
            
            break;
            
        case "xun_user_chat_preference":
            
            //id	uuid	username	chat_room_id	tag	ringtone	mute_validity	show_notification	created_at	updated_at
            //List = [UUID, Username, ChatroomId, Tag, Ringtone, MuteValidity, ShowNotification, CreatedDate, ModifiedDate],
		    if($row_string[7] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[7]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[8] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[8]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}            
            
			$fields = array("uuid", "username", "chat_room_id", "tag", "ringtone", "mute_validity", "show_notification", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $row_string[6], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_user_chat_preference", $insertData);
            
            break;
            
        case "xun_business_customer":
            
            //id	business_email	stripe_customer_id	card_charge_status	stripe_error	created_at	updated_at
            //List = [BusinessEmail, StripeCustId, CardStatus, StripeError, CreatedDate, ModifiedDate],
		    if($row_string[4] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[4]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}
			if($row_string[5] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[5]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}            
            
			$fields = array("business_email", "stripe_customer_id", "card_charge_status", "stripe_error", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_stripe_customer", $insertData);
            
            break;
            
        case "xun_payment_transaction":
            
            //id	old_id	business_id	package_code	invoice_no	payment_amount	payment_currency	stripe_token	stripe_charge_id	payment_status	reference_id	type	stripe_created	error	created_at
            //List = [UUID, Business_UUID, PackageCode, InvoiceNo, Amount, Currency, StripeToken, StripeChargeId, Status, Tablename, TableId, StripeCreated, Error, CreatedDate],
 	
            if($row_string[13] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[13]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}           
            
			$fields = array("id", "business_id", "package_code", "invoice_no", "payment_amount", "payment_currency", "stripe_token", "stripe_charge_id", "payment_status", "reference_id", "type", "stripe_created", "error", "created_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $row_string[6], $row_string[7], $row_string[8], $row_string[10], $row_string[9], $row_string[11], $row_string[12], $created_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_payment_transaction", $insertData);
            
            break;
            
        case "xun_business_package_top_up":
            
            //	id	old_id	business_id	business_package_subscription_id	package_code	billing_id	package_message_limit	quantity	total_messages	created_at
            //List = [UUID, Business_UUID, PackageSubId, PackageCode, Billing_UUID, MessageLimit, Quantity, TotalMessage, CreatedDate],
 	
            if($row_string[8] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[8]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}           
            
			$fields = array("old_id", "business_id", "business_package_subscription_id", "package_code", "billing_id", "package_message_limit", "quantity", "total_messages", "created_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $row_string[6], $row_string[7], $created_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_business_package_top_up", $insertData);
            
        case "xun_livechat_room":
            
            //id	old_id	host	username	username_host	business_id	business_tag	employee_username	employee_host	status	created_at	updated_at
            //List = [UUID, LivechatHost, Username, ServerHost, Billing_UUID, BusinessTag, EmployeeUsername, EmployeeHost, Status, CreatedDate, ModifiedDate],
            
            if($row_string[9] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[9]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}   
			if($row_string[10] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[10]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}            
            
			$fields = array("old_id", "host", "username", "username_host", "business_id", "business_tag", "employee_username", "employee_host", "status", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $row_string[6], $row_string[7], $row_string[8], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_livechat_room", $insertData);
            
            break;
            
        case "xun_crypto_address":
            
            //id	user_id	address	created_at	updated_at
            //List = [UUID, user_id, crypto_address, CreatedDate, ModifiedDate],
            
            if($row_string[3] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[3]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}   
			if($row_string[4] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[4]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}            
            
			$fields = array("user_id", "address", "created_at", "updated_at");
			$values = array($row_string[1], $row_string[2], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_crypto_user_address", $insertData);
            
            break;
            
        case "xun_user_id":
            
            //id	username	created_at	updated_at
            //List = [UUID, username, CreatedDate, ModifiedDate],
            
            if($row_string[2] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[2]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}   
			if($row_string[3] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[3]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}            
            
			$fields = array("id", "user_username", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_user_id", $insertData);
            
            break;
            
        case "xun_live_group_chat_room":
            
            //id	old_id	host	user_mobile	user_host	business_id	status	creator_mobile	creator_host	created_at	updated_at
            //List = [ChatroomId, Chatroomhost, UserId, Userhost, Business_UUID, Status, CreatorId, CreatorHost, CreatedDate, ModifiedDate],
            
            if($row_string[8] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[8]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}   
			if($row_string[9] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[9]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}            
            
			$fields = array("old_id", "host", "user_mobile", "user_host", "business_id", "status", "creator_mobile", "creator_host", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $row_string[6], $row_string[7], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_live_group_chat_room", $insertData);
            
            break;
            
        case "xun_public_key":
            
            //id	old_id	key_user_id	key_host	key	status	created_at	updated_at
            //List = [UUID, Jid, Host, Publickey, Status, CreatedDate, ModifiedDate],
            
            if($row_string[5] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[5]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}   
			if($row_string[6] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[6]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}            
            
			$fields = array("old_id", "key_user_id", "key_host", "key", "status", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $row_string[4], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_public_key", $insertData);
            
            break;
            
        case "xun_group_chat":
            
            //id	old_id	host	creator_id	type	created_at	updated_at
            //List = [UUID, Host, CreatorId, Type, CreatedDate, ModifiedDate],
            
            if($row_string[4] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[4]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}   
			if($row_string[5] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[5]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			}            
            
			$fields = array("old_id", "host", "creator_id", "type", "created_at", "updated_at");
			$values = array($row_string[0], $row_string[1], $row_string[2], $row_string[3], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_group_chat", $insertData);
            
            break;
            
        case "xun_muc_user":
            
            //old_id host creator_id type created_at updated_at
            //[Username, GroupId, Grouphost],          
            
			$fields = array("username", "group_id", "group_host");
			$values = array($row_string[0], $row_string[1], $row_string[2]);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_muc_user", $insertData);
            
            
            break;
            
        case "xun_encrypted_key":
            
            //username group_id group_host encrypted_key updated_at status created_at
            //[UUID, Username, GroupId, Grouphost, Encryptedkey, Status, CreatedDate, ModifiedDate], 
            
            if($row_string[6] == 'undefined'){
				$created_at = '';
			}else{
				$created_at = new DateTime($row_string[6]);
                $created_at = $created_at->format('Y-m-d H:i:s');
                $created_at = date("Y-m-d H:i:s", strtotime($created_at."+8 hours"));
			}   
			if($row_string[7] == 'undefined'){
				$updated_at = '';
			}else{
				$updated_at = new DateTime($row_string[7]);
                $updated_at = $updated_at->format('Y-m-d H:i:s');
                $updated_at = date("Y-m-d H:i:s", strtotime($updated_at."+8 hours"));
			} 
            
			$fields = array("username", "group_id", "group_host", "encrypted_key", "status", "created_at", "updated_at");
			$values = array($row_string[1], $row_string[2], $row_string[3], $row_string[4], $row_string[5], $created_at, $updated_at);
			$insertData = array_combine($fields, $values);

			$db->insert("xun_encrypted_key", $insertData);
            
            
            break;
            
        case "vcard":
			// [Username, Server, Nickname]
			$username = $row_string[0];
			$server_host = $row_string[1];
			$nickname = $row_string[2];
			if($nickname != ''){
				$db->where("username", $username);
				$db->where("server_host", $server_host);

				$update_data = [];
				$update_data["nickname"] = $nickname;
				$db->update("xun_user", $update_data);
			}

            break;
            
	}

?>
