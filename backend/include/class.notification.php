<?php
    
    class Notification
    {
        
        function __construct($mail)
        {
            $this->mail = $mail;
        }
        
        function sendEmailsUsingSendmail($to, $subject, $content, $providerInfo)
        {
            
            $mail = $this->mail;
            
            $mail->isMail();
            $mail->Subject = $subject;
            $mail->addAddress($to);
            $mail->msgHTML($content);
            
            // Set sender information
            $mail->From = $providerInfo['username'];
            $mail->FromName = $providerInfo['company'];
            
            if ($this->emailReply) $mail->addReplyTo($this->emailReply, $this->emailReply);
            
            $mailsender = $mail->send();
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            
            return $mailsender;
            
        }
        
        function sendEmailsUsingSMTP($to, $subject, $content, $providerInfo)
        {
            $mail = $this->mail;
            
            $mail->isSMTP();
            $mail->Subject = $subject;
            $mail->addAddress($to);
//            $mail->msgHTML($content, true);
            $mail->isHTML(true);
            $mail->Body = $content;
            
            // Authentication section
            $mail->Username = $providerInfo['username'];
            $mail->Password = $providerInfo['password'];
            
            // Set sender information
            $mail->From = $providerInfo['username'];
            $mail->FromName = $providerInfo['company'];
            
            if ($this->emailReply) $mail->addReplyTo($this->emailReply, $this->emailReply);
            
            // $mail->Sender = "";
            $mailsender = $mail->send();
            
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            
            return $mailsender? true : $mail->ErrorInfo;
            
        }
        
        
        function sendSMS($recipient, $text, $providerInfo)
        {
            
            $URL = $providerInfo['url1'];
            $xml = "<root>";
            $xml .= "<command>sendPrivateSMS</command>";
            $xml .= "<sendType>longCode</sendType>";
            $xml .= "<email>".$providerInfo['username']."</email>";
            $xml .= "<password>".$providerInfo['password']."</password>";
            $xml .= "<params>";
            $xml .= "<items>";
            $xml .= "<recipient>$recipient</recipient>";
            $xml .= "<textMessage>";
            $xml .= htmlspecialchars($text);
            $xml .= "</textMessage>";
            $xml .= "</items>";
            $xml .= "</params>";
            $xml .= "</root>";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $URL);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            return $response;
        }
        
        function sendXun($xunNumber, $message, $subject, $providerInfo)
        {
            global $db, $msgpack;
            
            $url = $providerInfo['url1'];
            $fields = array("api_key" => $providerInfo['api_key'],
                            "business_id" => $providerInfo['username'],
                            "message" => $message,
                            "tag" => $subject,
                            "mobile_list" => $xunNumber
                            );
            
            $dataString = json_encode($fields);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                                   'Content-Type: application/json',
                                                   'Content-Length: ' . strlen($dataString))
                       );
        
            $response = curl_exec($ch);
            curl_close($ch);
            
            return json_decode($response, true);
        }

        function curl_telegram($to,$subject,$text,$providerInfo){

            $url = $providerInfo['url1'];
            $title = $subject;
            $chat_id = $to; 
            $content = $text;
            $titleSub = "Subject :";
                $data = [
                    'chat_id'   => $chat_id,
                    'text'      => "*".$titleSub.$title."*"."\n\n".$content."\n Created at : ".date("Y-m-d H:i:s")
                ];
            
            $URL = $url."?".http_build_query($data)."&parse_mode=Markdown";
            // ##### GET METHOD #####
            $curl=curl_init($URL);
                            
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_VERBOSE, 0);  // for debug
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,120);
            curl_setopt($curl, CURLOPT_TIMEOUT, 120); //timeout in seconds
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, 0);
    
            $response = curl_exec($curl);
    
            if(curl_errno($curl)){
                return array('code' => 1, 'status' => "error", 'statusMsg' => '', 'curl_error_no' => curl_errno($curl), 'curl_error' => curl_error($curl));      
            }     
    
            /* get http status code*/
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
            curl_close($curl);
    
            return $response;
        }
        
    }
    
    ?>
