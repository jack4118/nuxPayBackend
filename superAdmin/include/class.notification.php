<?php
	
    ## Class to handles events and send notification if neccessary.
    class notification{
		
        // Default variables
        var $emailType = 0;
        var $emailReply = '';
        
        function sendNotification($subject, $content = array()) {
            global $db, $sys;

            include('config.php');
            
            // Get the notification users
            $xunUsers = $config['xunUsers'];
            $emailUsers = $config['emailUsers'];
            $smsUsers = $config['smsUsers'];
            $xunBusinessID = $config['xunBusinessID'];
            $xunBusinessName = $config['xunBusinessName'];
            
            if (count($xunUsers) > 0) {
                // Send xun notification
                $return = $this->sendXun($subject, $content['xun'], $xunBusinessID, $xunUsers);
                
            }
            
            if (count($emailUsers) > 0) {
                // Send email notification
                $return = $this->sendEmail($subject, $content['email'], $emailUsers);
                
            }
            
            if (count($smsUsers) > 0) {
                // Send sms notification
                foreach ($smsUsers as $phone) {
                    $return = $this->sendSMS($phone, $content['sms']);
                }
                
            }
            
            
        }
        
        function sendXun($department, $content, $xunBusinessID, $xunUsers) {
            global $db, $sys, $config;
            
            if (!$department) return false;
            if (!content) return false;
            if (count($xunUsers) == 0) return false;
            
            $data = array(
                          "user_ID" => $sys["xunUserID"],
                          "API_key" => $sys["xunAPIKey"],
                          "command" => "business_account_gateway",
                          "msg" => $content,
                          "business_account_ID" => $xunBusinessID,
                          "department" => $department,
                          "recipient_username" => implode("#", $xunUsers),
                          );
            
            $fields = http_build_query($data);
            
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $sys["xunURL"]);
            curl_setopt($curl, CURLOPT_VERBOSE, 0);  // for debug
            curl_setopt($curl, CURLOPT_POST, count($data));
            curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10); //timeout in seconds
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($curl);
            
            if (curl_errno($curl)) {
                //echo date("Y-m-d H:i:s")." Curl error: ".curl_error($curl)."\n";
                return false;
            }
            
            curl_close($curl);
            
            $xmlResponse = simplexml_load_string($response);
            if($xmlResponse->status == "ok") {
                return true;
            }
            else {
                return false;
            }
            
        }
        
        function sendEmail($subject, $content, $recipients) {
            global $db, $sys, $config;

            include('config.php');
            include_once('class.phpmailer.php');

            $mail = new PHPMailer();
            
            $mail->IsMail(true);
            $mail->Subject = $subject;
            
            $mail->From = $config['companyEmail'];
            $mail->FromName = $config['companyName'];
            
            // Add reply to email
            if ($this->emailReply) $mail->AddReplyTo($this->emailReply, $this->emailReply);
            
            switch ($this->emailType){
                    
                default: // Default send email to users in the notification table
                    
                    foreach ($recipients as $recipient) {
                        // Add into the address list
                        if ($recipient["email"]) $mail->AddAddress($recipient["email"],$recipient["name"]);
                        
                    }
                    
                    break;
                    
            }
            
            $mail->MsgHTML($content);
            $mail->IsHTML(true); // send as HTML
            $mail->Send();
            $mail->ClearAllRecipients();
            $mail->ClearReplyTos();
            
        }
        
        function sendSMS($phone, $text){
            global $sys, $config;
            
            $aryTarget = array("<br>","<br/>","<BR>","<BR/>");
            $aryReplace = array(" ## "," ## "," ## "," ## ");
            $text = str_replace($aryTarget,$aryReplace,$text);
            
            $xmlMsg = '<root>';
            $xmlMsg .= '<email>'.$sys["smsEmail"].'</email>';
            $xmlMsg .= '<password>'.$sys["smsPassword"].'</password>';
            
            $xmlMsg .= '<customer>'.$phone.'</customer>';
            $xmlMsg .= '<text>'.$text.'</text>';
            $xmlMsg .= '<sendDate></sendDate>';
            $xmlMsg .= '<sendTime></sendTime>';
            $xmlMsg .= '</root>';
            
            // Do this in config file
            $notifyURL = $sys["xmlGatewayURL"];
            $curl=curl_init($notifyURL);
            
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            
            // debug
            curl_setopt($curl, CURLOPT_VERBOSE, 0);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $xmlMsg);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
        }
        
		
	}
	    
?>
