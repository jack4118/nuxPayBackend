<?php
	session_start();

	class post{
        
		public function curl($command, $params = array(), $site="SuperAdmin", $language="english", $source="Web", $sourceVersion="", $userAgent="")
        {
            include('config.php');
            include('class.msgpack.php');
			
            if (!$userAgent) $userAgent = $_SERVER['HTTP_USER_AGENT'];
            
            if ($source == 'Web') {
                // Parse the user agent to find out details about the device
                $parser = $this->parseUserAgent($userAgent);
                $sourceVersion = $parser['browserVersion'];
                $type = $parser['platform']." - ".$parser['browserName'];
            }
            
            // Build the post data here
            $dataArray = array("command" => $command,
                               "userID" => $_SESSION['userID'],
                               "username" => $_SESSION['username'],
                               "sessionID" => $_SESSION['sessionID'],
                               "sessionTimeOut" => $_SESSION['sessionTimeOut'],
                               "source" => $source, //change member/admin accordingly
                               "sourceVersion" => $sourceVersion,
                               "language" => $language, //change chinese/malay/english accordingly
                               "userAgent" => $userAgent,
                               "type" => $type,
                               "site" => $site,
                               "ip" => $_SERVER['REMOTE_ADDR'],
                               "params" => $params
                               );
            
            if (in_array($site, array('Member', 'Admin'))) {
                $webServiceUrl = $config['adminMemberWebserviceURL'];
            }
            else {
                $webServiceUrl = $config['webserviceURL'];
            }
            
            
            $request = $_SERVER['REQUEST_METHOD'];
    
            //set your curl data here
            //$params is having the from data.
            $msgpack = new msgpack();
            
            $msg = $msgpack->msgpack_pack($dataArray);

			$ch = curl_init();
		    curl_setopt($ch, CURLOPT_URL, $webServiceUrl);
		    curl_setopt($ch, CURLOPT_POST, true);
		    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-msgpack'));
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $msg);
//		    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); //timeout in seconds
//		    curl_setopt($ch, CURLOPT_TIMEOUT, 60); //timeout in seconds
		    $result = curl_exec($ch);
		    curl_close($ch);
            
            $msgReturn = $msgpack->msgpack_unpack($result);
            
            //Destroy session here
            if($msgReturn['code'] == 3) {
                session_destroy();
            }
            else
                $_SESSION["sessionTimeOut"] = time(); //Reset session
            
            if ($command != 'superAdminLogin')
                $msgReturn = json_encode($msgReturn);
            
		    return $msgReturn;
		}
        
        public function parseUserAgent($userAgent)
        {
            $browserName = 'Unknown';
            $platform = 'Unknown';
            $version= "";
            
            //First get the platform?
            if (preg_match('/android/i', $userAgent)) {
                $platform = 'Android';
            }
            elseif (preg_match('/linux/i', $userAgent)) {
                $platform = 'Linux';
            }
            elseif (preg_match('/iphone|cpu iphone os/i', $userAgent)) {
                $platform = 'iPhone';
            }
            elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
                $platform = 'Mac';
            }
            elseif (preg_match('/windows|win32/i', $userAgent)) {
                $platform = 'Windows';
            }
            
            
            // Next get the name of the useragent yes seperately and for good reason
            if(preg_match('/MSIE/i',$userAgent) && !preg_match('/Opera/i',$userAgent))
            {
                $browserName = 'Internet Explorer';
                $ub = "MSIE";
            }
            elseif(preg_match('/Firefox/i', $userAgent))
            {
                $browserName = 'Mozilla Firefox';
                $ub = "Firefox";
            }
            elseif(preg_match('/Chrome/i', $userAgent))
            {
                $browserName = 'Google Chrome';
                $ub = "Chrome";
            }
            elseif(preg_match('/Safari/i', $userAgent))
            {
                $browserName = 'Apple Safari';
                $ub = "Safari";
            }
            elseif(preg_match('/Opera/i', $userAgent))
            {
                $browserName = 'Opera';
                $ub = "Opera";
            }
            elseif(preg_match('/Netscape/i', $userAgent))
            {
                $browserName = 'Netscape';
                $ub = "Netscape";
            }
            
            // finally get the correct version number
            $known = array('Version', $ub, 'other');
            $pattern = '#(?<browser>' . join('|', $known) .
            ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
            if (!preg_match_all($pattern, $userAgent, $matches)) {
                // we have no matching number just continue
            }
            
            // see how many we have
            $i = count($matches['browser']);
            if ($i != 1) {
                //we will have two since we are not using 'other' argument yet
                //see if version is before or after the name
                if (strripos($userAgent, "Version") < strripos($userAgent, $ub)){
                    $version = $matches['version'][0];
                }
                else {
                    $version = $matches['version'][1];
                }
            }
            else {
                $version = $matches['version'][0];
            }
            
            // check if we have a number
            if ($version==null || $version=="") {$version="?";}
            
            $data['browserName'] = $browserName;
            $data['browserVersion'] = $version;
            $data['platform'] = $platform;
            $data['pattern'] = $pattern;
            
            return $data;
        }
	}

?>
