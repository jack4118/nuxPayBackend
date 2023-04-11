<?php
	
	class General
    {
        
        private $currentLanguage = 'english';
        
        function __construct($db, $setting) {
            $this->db = $db;
            $this->setting = $setting;

        }
        
        public function validatePassword($password)
        {
            global $setting;

            /*
             Explaining $\S*(?=\S{8,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])(?=\S*[\W])\S*$
             $ = beginning of string
             \S* = any set of characters
             (?=\S{8,}) = of at least length 8
             (?=\S*[a-z]) = containing at least one lowercase letter
             (?=\S*[A-Z]) = and at least one uppercase letter
             (?=\S*[\d]) = and at least one number
             (?=\S*[\W]) = and at least a special character (non-word characters)
             $ = end of the string
             */

            if (!preg_match('$\S*(?=\S{' . $setting->sysSetting["minPasswordLength"] . ',})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])\S*$', $password))
                return false;
            return true;
        }

        // take out number from string
        public function onlyNumber($str)
        {
            $number = preg_replace("/[^0-9]/", '', $str);

            return $number;
        }

        public function validateEmail($email)
        {
            if (preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/", $email))
                return true;
            else
                return false;
        }

        public function validatePostCode($postcode)
        {
            if ((preg_match('/^\d{0,}$/', $postcode)))
                return true;
            else return false;
        }

        public function generateAlpaNumeric($length, $type = "")
        {
            if($type == "referral_code"){
                $str = str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ2345689');
            }
            else{
                $str = str_shuffle('abcefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890');
            }

            return substr($str, 0, $length);
        }

        public function generateRandomNumber($length)
        {

            $numberAllow = '1234567890';
            $generate = '';
            $generateTmp = '';
            $strTmp = '';
            $str = '';
            $i = 0;

            while ($i < $length) {
                $generate = str_shuffle($numberAllow);
                $generateTmp = substr($generate, 0, 1);
                if ($strTmp != $generateTmp) {
                    unset($strTmp);
                    $strTmp = $generateTmp;
                    $str .= $generateTmp;
                    $i++;
                    unset($generateTmp);
                }
            }
            return $str;
        }

        public function phoneNumberWeKeep($phoneNumber)
        {

            if (is_array($phoneNumber)) {

                $phoneNumber = explode(";", $phoneNumber);
                for ($i = 0; $i < count($phoneNumber); $i++) {
                    $phoneNumber[$i] = trim($this->onlyNumber($phoneNumber[$i]));
                    ######## for Malaysia only #########
                    // add 0
                    if (strlen($phoneNumber[$i]) == "9" && substr($phoneNumber[$i], 0, 1) == "1")
                        $phoneNumber[$i] = '0' . $phoneNumber[$i];
                    if (strlen($phoneNumber[$i]) == "10" && substr($phoneNumber[$i], 0, 2) == "11")
                        $phoneNumber[$i] = '0' . $phoneNumber[$i];
                    // add 6
                    (substr($phoneNumber[$i], 0, 2) == "01") ? $phoneNumber[$i] = "6" . $phoneNumber[$i] : '';
                    #####################################
                }
                $phoneNumber = implode(";", $phoneNumber);

            }

            return $phoneNumber;
        }

        ######################### VALID PHONE NUMBER ###############
        public function mobileNumberInfo($phone, $clientRegionCode)
        {

            $phone = $this->numberOnly($phone);
            $regionCode = "";
            $countryCode = "";
            $countryName = "";

            if (substr($phone, 0, 1) != 0) $phone = "+" . $phone;

            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            try {
                $mobileNumberDetails = $phoneUtil->parse($phone, $clientRegionCode);
                $countryCode = $mobileNumberDetails->getCountryCode();
                $regionCode = $phoneUtil->getRegionCodeForNumber($mobileNumberDetails);
                $phone = $phoneUtil->format($mobileNumberDetails, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);

                $xml = simplexml_load_file(__DIR__ . "/lang_world_country.xml");
                foreach ($xml->language->item as $items) {
                    if ($items['phone_code'] == $countryCode && $items['country_code'] == $regionCode) {
                        $countryName = (string)$items;
                        break;
                    }
                }

                //var_dump($swissNumberProto);
            } catch (\libphonenumber\NumberParseException $e) {
                // var_dump($e);
                return array(
                    "isValid" => 0,
                    "mobileNumberFormatted" => $phone,
                    "phone" => $this->numberOnly($phone),
                    "mobileNumberWithoutFormat" => $this->onlyNumber($phone),
                    "countryCode" => $countryCode,
                    "regionCode" => $regionCode,
                    "countryName" => $countryName,
                );
            }

            $isValid = 0;
            if ($phoneUtil->isValidNumber($mobileNumberDetails)) {
                // 0:FIXED_LINE
                // 1:MOBILE
                // 2:FIXED_LINE_OR_MOBILE
                // 10:UNKNOWN
                // 27:EMERGENCY
                if ($phoneUtil->getNumberType($mobileNumberDetails)) $isValid = 1;
            }

            if($regionCode == "MY" || ($phone != "" && $isValid == 0)){
                // number must be 12 digit include country code and start from 63
                if(strlen($this->onlyNumber($phone)) == 12 && substr($this->onlyNumber($phone), 0, 4) == 6011){ 
                    $isValid = 1;
                }
            }
            
            return array(
//                          "asd" => $phoneUtil->isValidNumber($mobileNumberDetails),
                "isValid" => $isValid,
                "mobileNumberFormatted" => $phone,
                "phone" => $this->numberOnly($phone),
                "mobileNumberWithoutFormat" => $this->onlyNumber($phone),
                "countryCode" => $countryCode,
                "regionCode" => $regionCode,
                "countryName" => $countryName,
            );
        }

        public function generateApiKey($clientID)
        {
            return md5($clientID.time());
        }

        /**
         *
         * Get the Limit value.
         * @param $pageNumber Integer.
         *
        **/

        public function getXunLimit($pageNumber = NULL)
        {
            global $setting;

            $pagingLimit = $setting->getAdminPageLimit();
            $startLimit  = ($pageNumber-1) * $pagingLimit;
            $limit       = array($startLimit, $pagingLimit);

            return $limit;
        }

        /**
         *
         * Get the Limit value.
         * @param $pageNumber Integer.
         *
        **/
        public function getLimit($pageNumber = NULL)
        {
            global $setting;

            $pagingLimit = $setting->systemSetting["superAdminPageLimit"];
            $startLimit  = ($pageNumber-1) * $pagingLimit;
            $limit       = array($startLimit, $pagingLimit);

            return $limit;
        }
        
        public function setCurrentLanguage($currentLanguage)
        {
            $this->currentLanguage = $currentLanguage;
        }
        
        public function getCurrentLanguage()
        {
            return $this->currentLanguage;
        }
        
        public function setTranslations($translations)
        {
            $this->translations = $translations;
        }
        
        public function getTranslations()
        {
            return $this->translations;
        }

        //For getting the Language.
        public function getLanguage() {
            if (isset($_SESSION['language'])) {
                $language = $_SESSION['language'];
            } else {
                if(isset($_COOKIE["language"])) {
                    $_SESSION['language'] = $_COOKIE['language'];
                    $language = $_COOKIE['language'];
                } else {
                    $_SESSION['language'] = "english";
                    $language = "english";
                    setcookie("language", "english");
                }
            }
            return $language;
        }
        
        // Getting the timezone offset difference
        public function formatDate($offsetSecs, $timestamp) {
            $serverTime = date('Z');
            $timeDiff = $serverTime + $offsetSecs;
            
            return date($this->setting->getDateFormat(), $timestamp - $timeDiff);
        }
        
        public function formatDateTime($offsetSecs, $timestamp) {
            $serverTime = date('Z');
            $timeDiff = $serverTime + $offsetSecs;
            
            return date($this->setting->getDateTimeFormat(), $timestamp - $timeDiff);
        }
        
        
        // Convert from front to back, need to add
        // Convert from back to display at front, need to minus
        // Getting the timezone offset difference
        /*
         * $offsetSecs will be the UTC timezone from the front in seconds
         * 
         * $dateTimeString will be in this format
         * $from = 0 -->  Y-m-d H:i:s
         * $from = 1 -->  d/m/y H:i:s A
         * 
         * $from will be the conversion from where
         * 0 - To display from backend to frontend
         * 1 - To convert back from frontend to backend
         *
         * $format will be the date format for this output
         */
        public function formatDateTimeString($offsetSecs, $dateTimeString, $format="Y-m-d H:i:s") {
            $dateTs = strtotime($dateTimeString);
            if($dateTs < 0)
                return;
            $serverTime = date('Z');
            
            // Check for timezone setting
            if ($this->setting->getTimezoneSetting()) {
                $timeDiff = $serverTime + $offsetSecs;
                $newTs = $dateTs - $timeDiff;
                return date($format, $newTs);
            }
            else {
                return date($format, $dateTs);
            }
        }

        public function formatDateTimeToString($dateTimeString, $format="") {
            $setting = $this->setting;

            if($format == "")
                $format = strlen($setting->systemSetting['systemDateTimeFormat']) > 0 ? $setting->systemSetting['systemDateTimeFormat'] : "d/m/Y h:i:s A";
            
            return date($format, strtotime($dateTimeString));
        }

        public function formatIsoDateTimeToLocalTime($dateTimeString, $format="Y-m-d H:i:s"){
            $dateTime = date_format(date_create($dateTimeString), $format);
            $localTime = date($format, strtotime($dateTime . "+8 hours"));

            return $localTime;
        }

        public function formatDateTimeToIsoFormat($dateTimeString){
            if($dateTimeString == ""){
                return "";
            }
            $dateTime = new DateTime($dateTimeString);
            $dateTime->setTimezone(new DateTimeZone('UTC'));
            return $dateTime->format('Y-m-d\TH:i:s\Z');
        }

        public function numberOnly($string){
            $number = filter_var($string, FILTER_SANITIZE_NUMBER_INT);
            return $number;
        }

        public function sendEmail($params){
            include_once("class.smtp.php");
            include_once("class.phpmailer.php");

            $setting = $this->setting;

            $emailAddress = $setting->systemSetting["systemEmailAddress"];
            $emailPassword = $setting->systemSetting["systemEmailPassword"];
            $emailFromName = $setting->systemSetting["systemEmailName"];

            $subject = $params["subject"];
            $body = $params["body"];
            $recipients = $params["recipients"];
            $emailAddress = $params["emailAddress"] ? $params["emailAddress"] : $emailAddress;
            $emailPassword = $params["emailPassword"] ? $params["emailPassword"] : $emailPassword;
            $emailFromName = $params["emailFromName"] ? $params["emailFromName"] : $emailFromName;

            $mail = new PHPMailer();

            $mail->IsSMTP(); // set mailer to use SMTP
//            $mail->SMTPDebug = 3;  
            $mail->Host = "smtp.gmail.com";  // specify main and backup server
            $mail->SMTPAuth = true;     // turn on SMTP authentication
            $mail->Username = $emailAddress;  // SMTP username
            $mail->Password = $emailPassword; // SMTP password

            $mail->From = $emailAddress;
            $mail->FromName = $emailFromName;
            
            foreach($recipients as $email)
            {
                $mail->AddAddress($email);
            }

            $mail->WordWrap = 50;                                 // set word wrap to 50 characters
            // $mail->AddAttachment("/var/tmp/file.tar.gz");         // add attachments
            // $mail->AddAttachment("/tmp/image.jpg", "new.jpg");    // optional name
            $mail->IsHTML(true);                                  // set email format to HTML
    
            $mail->Subject = $subject;
            
            //$lang["M01424"][$language]."<br><br>".$lang["M01425"][$language].": ".$password."<br><br>".$lang["M01426"][$language]."<br><a href='".$sys['memberSite']."/login.php?resetPassword=1'>".$sys['memberSite']."</a>";

            $mail->Body = $body;

            $result = $mail->Send();
            return $result;
        }
        
        public function convert_date_to_atom($datetime){
            
            $datetime = new DateTime($datetime);
            $datetime_in_atom = $datetime->format(DateTime::ATOM);
            return $datetime_in_atom;
            
        }

        public function checkDecimalPlaces($decimal, $unitConversion){
            $int = bcmul((string)$decimal, (string)$unitConversion, log10($unitConversion));

            $floor = floor($int);

            if($int == $floor){
                return true;
            }else{
                return false;
            }
        }

        public function setIsDemoAccount($isDemoAccount = 0){
            $this->isDemoAccount = ($isDemoAccount==1)?1:0;
        }

        public function getResponseArr($type, $messageCode = "", $messageD = "", $data = null){
            $language = $this->getCurrentLanguage();
            $translations = $this->getTranslations();

            if($type == 1){
                $code = 1;
                $message = "SUCCESS";
            }else{
                $code = 0;
                $message = "FAILED";
            }

            if($messageCode){
                $messageD = $translations[$messageCode][$language];
            }

            $returnData = array("code" => $code, "message" => $message, 
            "message_d" => $messageD);
            
            if(!empty($data) || !is_null($data)){
                $returnData["data"] = $data;
            }

            return $returnData;
        }

        public function send_thenux_notification($params, $grouping = null, $message) {
            
            global $post, $config, $xun_numbers, $message, $xun_recipient_telegram;
            
            $url_string = $config["broadcast_url_string"];
            
            switch ($grouping) {
                
                case "thenux_referral_and_master_dealer":
                    $params["api_key"] = $config["thenux_referral_and_master_dealer_API"];
                    $params["business_id"] = $config["thenux_referral_and_master_dealer_bID"];
                    $params["mobile_list"] = $xun_numbers;
                    $thenuxReturn = $post->curl_post($url_string, $params, 0);

                    $recipient =  $xun_recipient_telegram["thenux_referral_and_master_dealer"];
                    $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");

                    break;

                case "thenux_wallet_transaction":
                    $params["api_key"] = $config["thenux_wallet_transaction_API"];
                    $params["business_id"] = $config["thenux_wallet_transaction_bID"];
                    $params["mobile_list"] = $xun_numbers;
                    $thenuxReturn = $post->curl_post($url_string, $params, 0);
              
                    $recipient =  $xun_recipient_telegram["thenux_wallet_transaction"];
                    $type = "telegram";

                    $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['messages'],$type);
                    return $output;
                    break;

                case "thenux_Daily_Monitor":
                    $params["api_key"] = $config["thenux_wallet_transaction_API"];
                    $params["business_id"] = $config["thenux_wallet_transaction_bID"];
                    $params["mobile_list"] = $xun_numbers;
                    $thenuxReturn = $post->curl_post($url_string, $params, 0);
                
                    $recipient =  $xun_recipient_telegram["thenux_Daily_Monitor"];
                    $type = "telegram";

                    $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['messages'],$type);
                    break;

                case "thenux_JH_Monitor":
                        $params["api_key"] = $config["thenux_wallet_transaction_API"];
                        $params["business_id"] = $config["thenux_wallet_transaction_bID"];
                        $params["mobile_list"] = $xun_numbers;
                        $thenuxReturn = $post->curl_post($url_string, $params, 0);
                    
                        $recipient =  $xun_recipient_telegram["thenux_JH_Monitor"];
                        $type = "telegram";
    
                        $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['messages'],$type);
                        break;

                case "thenux_JH_Monitor_error":
                    $params["api_key"] = $config["thenux_wallet_transaction_API"];
                    $params["business_id"] = $config["thenux_wallet_transaction_bID"];
                    $params["mobile_list"] = $xun_numbers;
                    $thenuxReturn = $post->curl_post($url_string, $params, 0);
                
                    $recipient =  $xun_recipient_telegram["thenux_JH_Monitor_error"];
                    $type = "telegram";

                    $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['messages'],$type);
                    break;

                case "thenux_pay":
                    $params["api_key"] = $config["thenux_pay_API"];
                    $params["business_id"] = $config["thenux_pay_bID"];
                    $params["mobile_list"] = $params["mobile_list"] ? $params["mobile_list"] : $xun_numbers;
                    $thenuxReturn = $post->curl_post($url_string, $params, 0);

                    $recipient =  $xun_recipient_telegram["thenux_pay"];
                    $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");

                    break;

                case "thenux_pay_marketing":
                    $params["api_key"] = $config["thenux_pay_marketing_API"];
                    $params["business_id"] = $config["thenux_pay_marketing_bID"];
                    $params["mobile_list"] = $params["mobile_list"] ? $params["mobile_list"] : $xun_numbers;
                    $thenuxReturn = $post->curl_post($url_string, $params, 0);

                    $recipient =  $xun_recipient_telegram["thenux_pay_marketing"];
                    $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");

                    break;

                case "thenux_marketing":
                    $params["api_key"] = $config["thenux_marketing_API"];
                    $params["business_id"] = $config["thenux_marketing_bID"];
                    $params["mobile_list"] = $xun_numbers;
                    $thenuxReturn = $post->curl_post($url_string, $params, 0);

                    // $recipient =  $xun_recipient_telegram["thenux_marketing"];
                    // $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");

                    break;

                case "thenux_NuxPay_Landing_Page_Track":
                    $params["api_key"] = $config["thenux_marketing_API"];
                    $params["business_id"] = $config["thenux_marketing_bID"];
                    $params["mobile_list"] = $xun_numbers;
                    $thenuxReturn = $post->curl_post($url_string, $params, 0);

                    $recipient =  $xun_recipient_telegram["thenux_NuxPay_Landing_Page_Track"];
                    $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");

                    break;

                case "thenux_issues":
                    $params["api_key"] = $config["thenux_issues_API"];
                    $params["business_id"] = $config["thenux_issues_bID"];
                    $params["mobile_list"] = $xun_numbers;
                    $erlangReturn = $post->curl_post($url_string, $params, 0);

                    $recipient =  $xun_recipient_telegram["thenux_issues"];
                    $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");

                    break;

                default:
                    $params["api_key"] = $config["thenux_API"];
                    $params["business_id"] = $config["thenux_bID"];
                    $params["mobile_list"] = $params["mobile_list"] ? $params["mobile_list"] : $xun_numbers;
                    $thenuxReturn = $post->curl_post($url_string, $params, 0);

                    $recipient =  $xun_recipient_telegram["thenux_Other_issue"];
                    $output = $message->createCustomizeMessageOut($recipient, $params['tag'], $params['message'], "telegram");

                    break;
            }
      
            return $output;
        }

        function getBrowserNew($u_agent){
            global $deviceDetector;
            $db = $this->db;

            $browser = "";
            $deviceOS = "";
            $deviceModel = "";
            $device = "";
            $platform = "";

            $userResult = $deviceDetector->getInfoFromUserAgent($u_agent);

            if($userResult['client']['name'] != ''){
                $browser = $userResult['client']['name'];
            }else{
                if (preg_match('/SkypeUriPreview/', $u_agent)) {
                    $browser = 'Skype'; 
                }else{
                    $browser = 'Others'; 
                }

                preg_match('#\((.*?)\)#', $u_agent, $match);
                $getDevice = $match[1];

                $userResult = $deviceDetector->getInfoFromUserAgent($getDevice);
            }

            if(count($userResult['os']) > 0){
                $deviceOS = $userResult['os']['name']." ".$userResult['os']['version'];
            }else{
                $deviceOS = 'Others';
            }
            
            if($userResult['device']['type'] == '' && $userResult['device']['brand'] == '' && $userResult['device']['model'] == ''){
                $device = 'Others';
                $deviceModel = 'Others';
            }else{
                if($userResult['device']['brand'] != ''){
                    $short = $userResult['device']['brand'];
                    
                    $device  = ucwords($userResult['device']['type']);

                    if($userResult['device']['brand'] != ''){
                        unset($brandTemp);
                        $db->where('short_name', $short);
                        $brandTemp = $db->getValue('device_model','name');
                        
                        $brand = $brandTemp != '' ? $brandTemp : $short;

                        if($userResult['device']['model'] != null){
                            $deviceModel = $brand." ".$userResult['device']['model'];
                        }else if($userResult['device']['type'] == 'desktop' && $userResult['device']['brand'] == 'AP'){
                            #### Apple Mac ####
                            $deviceModel = $brand." Mac";
                        }else{
                            $deviceModel = 'Others';
                        }
                    }else{
                        $deviceModel = 'Others';
                    }
                }else{
                    $device  = ucwords($userResult['device']['type']);
                    $deviceModel = 'Others';
                }
            }

            //First get the platform?
            if (preg_match('/android/i', $u_agent)) {
                $platform = 'Android';
            }
            else if (preg_match('/linux/i', $u_agent)) {
                $platform = 'Linux';
            }
            else if (preg_match('/iphone|cpu iphone os/i', $u_agent)) {
                $platform = 'Iphone';
            }
            else if (preg_match('/macintosh|mac os x/i', $u_agent)) {
                $platform = 'Mac';
            }
            else if (preg_match('/windows|win32/i', $u_agent)) {
                $platform = 'Windows';
            }
            else if (preg_match('/ipad/i', $u_agent)) {
                $platform = 'Ipad';
            }else{
                $platform = 'Others';
            }

            unset($agentData);
            $agentData = array(
                "OS"        => $deviceOS,
                "browser"   => $browser,
                "device"    => $device,
                "deviceModel" => $deviceModel,
                "platform" => $platform,
            );

            return $agentData;
        }

        public function keep_queue_callback($type, $params) {

            $db = $this->db;

            $queue_data['type'] = $type;
            $queue_data['json_string'] = json_encode($params);
            $queue_data['processed'] = 0;
            $queue_data['created_at'] = date("Y-m-d H:i:s");
            $db->insert("crypto_callback_queue", $queue_data);

            return array("status"=>"ok", "code" => 1, "message" => "SUCCESS", "message_d" => "Success");

        }

        public function generateTransactionToken($prefix){
            
            $token = $prefix."-".uniqid()."-".time();

            return $token;
            
        }

        public function floorp($val, $precision) {
            $mult = pow(10, $precision);      
            return floor($val * $mult) / $mult;
        }

        public function ceilp ($val, $precision ) { 
            $pow = pow ( 10, $precision ); 
            return ( ceil ( $pow * $val ) + ceil ( $pow * $val - ceil ( $pow * $val ) ) ) / $pow; 
        } 

    }
?>
