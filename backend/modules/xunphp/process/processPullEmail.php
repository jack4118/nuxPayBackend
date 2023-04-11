<?php

$processName = $argv[1]; // process name
$limit       = (strlen($argv[2]) > 0) ? $argv[2] : 10; // Limit records
$sleepTime   = (strlen($argv[3]) > 0) ? $argv[3] : 5; // Sleep time

$currentPath = __DIR__;
include_once $currentPath . "/../include/config.php";
include_once $currentPath . "/../include/class.database.php";
include_once $currentPath . "/../include/class.provider.php";
include_once $currentPath . "/../include/class.ticketing.php";
include_once $currentPath . "/../include/class.general.php";
include_once $currentPath . "/../include/class.setting.php";
include_once $currentPath . "/../include/class.message.php";
include_once $currentPath . '/../include/class.log.php';

#### Unsed Classes ####
include_once $currentPath . "/../include/class.webservice.php";
include_once $currentPath . "/../include/class.phpmailer.php";
include_once $currentPath . "/../include/class.cash.php";
#######################

#### Old Classes ####
//include_once($currentPath."/../include/class.client.php");
//include_once($currentPath."/../include/class.ticket.php");
//include_once($currentPath."/../include/class.sms.php");
//include_once($currentPath."/../include/class.customContent.php");
//include_once($currentPath."/../include/class.route.php");
//include_once($currentPath."/../include/class.eventsManager.php");
#####################

$db          = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$setting     = new Setting($db);
$general     = new General($db, $setting);
$provider    = new Provider($db);
$message     = new Message($db, $general, $provider);
$ticketClass = new Ticket($db, $general, $setting, $message);

$logBaseName = basename(__FILE__, '.php');
$logPath     = $currentPath . '/../log/';
$log = new Log($logPath, $logBaseName);

// CHECK PROCESS ENABLE
// $processEnable = $setting->systemSetting["processPullEmailEnable"];
$db->where('name', 'processPullEmailEnable');
$processEnable = $db->getValue('system_settings', 'value');

if ($processEnable == 1) {
    $systemSupportCompany = $setting->systemSetting["systemSupportCompany"];
    $db->where('company', $systemSupportCompany);
    //$db->where('name', "SMS123 Support Mail");
    $db->where('type', "notification");
    $provider = $db->getOne('provider', 'username, password, url1');

    if (empty($provider)) {
        $log->write(date("Y-m-d H:i:s") . " Provider no found.\n");
        exit();
    }

    $log->write(date("Y-m-d H:i:s") . " Pull Email Start.\n");

    $mailServer = 'imap.gmail.com:993/imap/ssl';
    $mailUser   = $provider['username'];//邮箱用户名
    $mailPass   = $provider['password']; //邮箱密码
    
    if   (($mailbox_inbox = imap_open("{" . $mailServer . "}INBOX", $mailUser, $mailPass)) == true) {
        $log->write(date("Y-m-d H:i:s") . " Start INBOX \n");
        scan_mailbox($mailbox_inbox);
        imap_close($mailbox_inbox);

    } else {
        $log->write(date("Y-m-d H:i:s") . " connect fail\n");
    }

    $log->write(date("Y-m-d H:i:s") . " Pull Email Completed.\n");

} else if ($processEnable == 2) {
    $log->write(date("Y-m-d H:i:s") . " process has been disable. KILL PROCESS NOW\n");
    exit();

} else {
    $log->write(date("Y-m-d H:i:s") . " process has been disable. sleep 5 sec\n");
    sleep(5);
}

function scan_mailbox($mbox)
{
    global $db, $mail, $provider, $client, $route, $cash, $webservice, $ticketing, $eventsManager, $general, $sms, $setting, $sys, $customContent, $mailUser, $ticketClass, $log, $message;

    // 获取邮箱信息
    $mboxes = imap_mailboxmsginfo($mbox);
    $log->write(date("Y-m-d H:i:s") . " total inbox : " . $mboxes->Nmsgs . "\n");



    // 查看是否有新邮件
    if ($mboxes->Nmsgs != 0) {
        for ($mailno = $mboxes->Nmsgs; $mailno > 0; $mailno--) {

            // header
            $fullHeader = imap_fetchheader($mbox, $mailno);
            $header     = imap_fetch_overview($mbox, $mailno);
            $messageID  = $header[0]->message_id;

            imap_mail_move($mbox, $mailno, "processed");

            // get last message ID
            $db->where('message_id', $messageID);
            $result = $db->getValue('sms_email_incoming', 'message_id');
          
            if ($result) {
                $log->write(date("Y-m-d H:i:s") . " Pull Email Update Completed.\n");
                exit();
            }

            // 获取邮件内容
            $email = fetchEmail($mbox, $mailno);
           

            // skip empty email
            if (trim($email["from_address"]) == "@" && trim($email["to_address"]) == "@") {
                $log->write(date("Y-m-d H:i:s") . " Empty Email.\n");
                continue;
            }

            $fromEmail      = $email['from_address'];
            $fromName       = $email['from_name'];
            $replyToEmail   = $email['reply_to_address'];
            $toEmail        = $email['to_address'];
            $toName         = "";
            $subject        = $email['subject'];
            $plainMsg       = $email['plainmsg'];
            $htmlMsg        = $email['htmlmsg'];
            //$content = $htmlMsg ? str_ireplace(array("<br />","<br>","<br/>"), "\r\n", $htmlMsg) : $plainMsg;
            $content        = $plainMsg ? $plainMsg : $htmlMsg;
            //$content = preg_replace('/(\\n|\\r)/', '', $content);
            $internal       = 1;

            // $incomingID = $db->getNewID();

            // loop attachment
            $attachmentFile = "";
            $attachmentName = "";
            $attachmentType = "";

            foreach ($email["attachments"] as $fileName => $fileContent) {
                $attachmentName = $fileName;
                $attachmentFile = $fileContent;
            }

            $db->where('value', $replyToEmail ? $replyToEmail : $fromEmail);
            $db->where('type', "emailIncoming");
            $result = $db->getValue('sms_email_whitelist', 'id');
            

            if ($result) {

                $uploadID = "";
                if (!empty($attachmentFile)) {

                    $insert = array(
                        'data'       => base64_decode($attachmentFile),
                        'type'       => "ticketing",
                        'created_at' => $db->now(),
                        // 'reference_id' => $incomingID,
                        'file_type'  => $attachmentType,
                        'file_name'  => $attachmentName,
                        'deleted'    => 0,
                    );
                    $uploadID = $db->insert('uploads', $insert);
                }

                $insert = array(
                    'id'         => $incomingID,
                    'message_id' => $messageID,
                    'from_email' => $fromEmail,
                    'from_email' => $fromName,
                    'to_email'   => $toEmail,
                    'to_name'    => $toName,
                    'subject'    => $subject,
                    'header'     => $fullHeader,
                    'body'       => $content,
                    'upload_id'  => $uploadID,
                    'created_at' => $db->now(),
                );
                $incomingID = $db->insert('sms_email_incoming', $insert);

                $db->where('id', $uploadID);
                $updateData     = array('reference_id' => $incomingID);
                $updateUploadID = $db->update('uploads', $updateData);

                continue;
            }

            $clientID    = "";
            $clientName  = "";
            $clientPhone = "";

            $db->where('email', $fromEmail);
            $db->where('deleted', 0);
            $result = $db->getOne('client', 'id, name, phone');
            if ($result) {
                $internal = 0;

                $clientID    = $result['id'];
                $clientName  = $result['name'];
                $clientPhone = $result['phone'];
            }

            if ($toEmail != $mailUser) {
                $internal = 1;
            }

            $subjectExp  = explode("#", $subject);
            $subjectExp2 = explode("]", $subjectExp[1]);
          
            if (is_numeric($subjectExp2[0])) {
                $ticketID = $subjectExp2[0];
               
                //update ticket updated_at time.
                $db->where('id', $ticketID);
                $db->update("sms_ticket", array('updated_at' => $db->now()));

                //Send notify email to assignee when reply from client received.
                $db->where('id', $ticketID);
                $ticket = $db->getOne('sms_ticket');
                
               
                if ($ticket) {

                    //get all admin email if no assignee email.
                    if ($ticket['assignee_id'] == 0) {
                        $adminList = $db->get('admin', null, 'name, email');

                    } else {
                        $db->where('id', $ticket['assignee_id']);
                        $adminList = $db->get('admin', 1, 'name, email');
                    }
                 
               
                    if ($adminList) {
                        foreach ($adminList as $admin) {
                            if ($general->validateEmail($admin['email']) && $admin['email'] != $mailUser) {
                                $assigneeEmail[] = $admin['email'];
                                $assigneeName[]  = $admin['name'];
                            }
                        }
                    }

                    $clientID    = $clientID ? $clientID : $ticket['client_id'];
                    $clientName  = $ticket['client_name'];
                    $clientEmail = $ticket['client_email'];
                    $clientPhone = $ticket['client_mobile_number'];

                    $subject    = $ticket['subject'];
                    $status     = $ticket['status'];
                    $department = $ticket['department'];

                    $assigneeID   = $ticket['assignee_id'] ? $ticket['assignee_id'] : "";
                    $assigneeName = $ticket['assignee_name'] ? $ticket['assignee_name'] : "";

                    $db->where('username', $mailUser);
                    $providerID = $db->getValue('provider', 'id');
                  
                    $clientName = $clientName ? $clientName : $fromName;

                    // $ticketItemID = $db->getNewID();
                    $uploadID = "";
                    if (!empty($attachmentFile)) {

                        $insert = array(
                            'data'       => base64_decode($attachmentFile),
                            'type'       => "ticketing",
                            'created_at' => date("Y-m-d H:i:s"),
                            'file_type'  => $attachmentType,
                            'file_name'  => $attachmentName,
                            'deleted'    => 0,
                        );
                        $uploadID = $db->insert('uploads', $insert);
                       
                    }
                    #### create ticket item ####
                    $insert = array(
                        'id'                => $ticketItemID,
                        'ticket_id'         => $ticketID,
                        'content'           => $content,
                        'status'            => "response",
                        'assignee_id'       => $assigneeID,
                        'assignee_name'     => $assigneeName,
                        'creator_id'        => $clientID,
                        'creator_type'      => "Member",
                        'email_incoming_id' => $incomingID ? $incomingID : "",
                        'upload_id'         => $uploadID ? $uploadID : "",
                        'created_at'        => date('Y-m-d H:i:s'),
                    );
                    $ticketItemID = $db->insert('sms_ticket_item', $insert);
                   
               
                    $db->where('id', $uploadID);
                    $updateData     = array('reference_id' => $ticketItemID);
                    $updateUploadID = $db->update('uploads', $updateData);

                    $db->where('id', $ticketID);
                    $db->where('status', "closed");
                    $db->update('sms_ticket', array('status' => 'open'));

                  
                    #### EMAIL TO CLIENT ####
                    if ($ticket['internal'] == 0) {

                        $mailSubject = $ticketClass->ticketingEmailSubject($ticketID, $subject);
                        $body        = $ticketClass->ticketingStandardEmailHeader(1, $ticketID);
                        $body .= $ticketClass->generateEmailBody($ticketID);

                        $message->createCustomizeMessageOut($clientEmail, $mailSubject, $body, "smtp", $providerID);
                    }

                    #### EMAIL REPLY TO ADMIN ####
                    $mailSubject = $ticketClass->ticketingEmailSubject($ticketID, $subject, "clientResponse");
                    $body        = $ticketClass->ticketingClientResponseInformAdminEmailContent($ticketID, $subject, $status, $clientName, $clientEmail, $clientPhone, $department, $content);

                    foreach ($assigneeEmail as $toAssignee) {
                        $message->createCustomizeMessageOut($toAssignee, $mailSubject, $body, "smtp", $providerID);
                    }
                    #### EMAIL REPLY TO ADMIN END ####

                    #### XUN NOTIFICATION ####
                    $ticketType = $ticket['internal'] == 1 ? "Internal Ticket" : "Email Ticket";
                    $xunContent = mb_substr($content, 0, 300);

                    $find = array(
                        "%%ticketID%%",
                        "%%subject%%",
                        "%%clientName%%",
                        "%%clientEmail%%",
                        "%%clientPhone%%",
                        "%%ticketType%%",
                        "%%content%%",
                        "%%noticeOn%%",
                    );
                    $replace = array(
                        $ticketID,
                        $subject,
                        $clientName,
                        $clientEmail,
                        $clientPhone,
                        $ticketType,
                        $xunContent,
                        $db->now(),
                    );

                    $messageCode = "10023";
                    $message->createMessageOut($messageCode, "", "", $find, $replace);
                }
            } else {
               
                $pos = strpos(strtolower($header[0]->to), "ticket");

                if ($pos !== false) {
                    $autoEmail = 1;
                } else {
                    $autoEmail = 0;
                }
            
                $params = array(
                    'clientID'         => $clientID,
                    'clientName'       => $fromName,
                    'clientEmail'      => $replyToEmail,
                    'clientPhone'      => $clientPhone,
                    'status'           => "open",
                    'priority'         => "1",
                    'type'             => "",
                    'subject'          => $subject,
                    'department'       => "",
                    'reminderDate'     => "",
                    'assigneeID'       => "",
                    'assigneeName'     => "",
                    'creatorID'        => $clientID,
                    'creatorType'      => "Member",
                    'internal'         => $internal,
                    'content'          => $content,
                    'incomingID'       => $incomingID,
                    'attachmentBase64' => $attachmentFile,
                    'attachmentName'   => $attachmentName,
                    'attachmentType'   => $attachmentType,
                    'autoEmail'        => $autoEmail,
                );
                $output = $ticketClass->addTicket($params);
              
                $uploadID = $output['data']['uploadID'];
                $ticketID = $output['data']['ticketID'];
            }

            $insert = array(
                'id'             => $incomingID,
                'ticket_id'      => $ticketID,
                'ticket_item_id' => $ticketItemID ? $ticketItemID : 0,
                'message_id'     => $messageID,
                'from_email'     => $replyToEmail,
                'from_name'      => $fromName,
                'to_email'       => $toEmail,
                'to_name'        => $toName,
                'subject'        => $subject,
                'header'         => $fullHeader,
                'body'           => $content,
                'upload_id'      => $uploadID,
                'created_at'     => $db->now(),
            );
            $db->insert('sms_email_incoming', $insert);
        }
        // 删除所有打上删除标记的邮件
        //imap_expunge($mbox);
    }
}

/**
 * 获取一封邮件的信息
 * @param resource $imap_stream
 * @param int $msg_number
 */
function fetchEmail($mbox, $mailno)
{
    // 获取邮件内容
    $email = array();
    // 获取Header信息
    $head = imap_header($mbox, $mailno);
    
    // 获取邮件的发件人地址
    $email['from_address']      = $head->from[0]->mailbox . '@' . $head->from[0]->host;
    $email['from_name']         = $head->from[0]->personal;
    $email['to_address']        = $head->to[0]->mailbox . '@' . $head->to[0]->host;
    $email['reply_to_address']  = $head->reply_to[0]->mailbox. '@' . $head->reply_to[0]->host;

    // 初始化邮件主题变量

    $subject = null;
    if (!empty($head->subject)) {
        // 编码转换


        $mhead = imap_mime_header_decode($head->subject);
    
        foreach ($mhead as $value) {
            if ($value->charset != 'default') {
                $subject .= mb_convert_encoding($value->text, 'UTF-8', $value->charset);
            } else {
                $subject .= $value->text;
            }
        }
    }

    $email['subject'] = $subject;

    global $charset, $htmlmsg, $plainmsg, $attachments;
    $htmlmsg     = $plainmsg     = $charset     = '';
    $attachments = array();

    // BODY
    $s = imap_fetchstructure($mbox, $mailno);

    if (!$s->parts) { // simple
        getpart($mbox, $mailno, $s, 0); // pass 0 as part-number
    } else {
        // multipart: cycle through each part
        foreach ($s->parts as $partno0 => $p) {
            getpart($mbox, $mailno, $p, $partno0 + 1);
        }

    }

    $email['plainmsg']    = $plainmsg;
    $email['htmlmsg']     = $htmlmsg;
    $email['attachments'] = $attachments;
   
    return $email;
}

function getpart($mbox, $mid, $p, $partno)
{
    // $partno = '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple
    global $htmlmsg, $plainmsg, $charset, $attachments;

    // DECODE DATA
    $data = ($partno) ? imap_fetchbody($mbox, $mid, $partno) : imap_body($mbox, $mid); // simple

    // PARAMETERS
    // get all parameters, like charset, filenames of attachments, etc.
    $params = array();
    if ($p->parameters) {
        foreach ($p->parameters as $x) {
            $params[strtolower($x->attribute)] = $x->value;
        }
    }

    if (isset($p->dparameters)) {
        foreach ($p->dparameters as $x) {
            $params[strtolower($x->attribute)] = $x->value;
        }
    }

    // ATTACHMENT
    // Any part with a filename is an attachment,
    // so an attached text file (type 0) is not mistaken as the message.
    if (isset($params['filename']) || isset($params['name'])) {
        // filename may be given as 'Filename' or 'Name' or both
        $filename = ($params['filename']) ? $params['filename'] : $params['name'];
        // filename may be encoded, so see imap_mime_header_decode()

        /* 3 = BASE64 encoding */
        if ($p->encoding == 3) {
            $data = base64_decode($data);
        } else if ($p->encoding == 4) {
            /* 4 = QUOTED-PRINTABLE encoding */
            $data = quoted_printable_decode($data);
        }

        $attachments[$filename] = base64_encode($data); // this is a problem if two files have same name
    }

    // TEXT
    if ($p->type == 0 && !empty($data)) {
        $charset  = $params['charset'];
        $encoding = $p->encoding;

        // 根据encoding参数，进行转码
        switch ($encoding) {
            case 0:
                $data = mb_convert_encoding($data, "UTF-8", $charset);
                break;
            case 1:
                $encode_data = imap_8bit($data);
                $encode_data = imap_qprint($encode_data);
                $data        = mb_convert_encoding($encode_data, "UTF-8", $charset);
                break;
            case 3:
                $encode_data = imap_base64($data);
                $data        = mb_convert_encoding($encode_data, "UTF-8", $charset);
                break;
            case 4:
                $encode_data = imap_qprint($data);
                $data        = mb_convert_encoding($encode_data, 'UTF-8', $charset);
                break;
            case 2:
            case 5:
            default:
                // 转码失败
                break;
        }

        if (strtolower($p->subtype) == 'plain') {
            $plainmsg .= trim($data);

        } else {
            $htmlmsg .= $data;
        }
    }

    // EMBEDDED MESSAGE
    // Many bounce notifications embed the original message as type 2,
    // but AOL uses type 1 (multipart), which is not handled here.
    // There are no PHP functions to parse embedded messages,
    // so this just appends the raw source to the main message.
    if ($p->type == 2 && $data) {
        $plainmsg .= $data;
    }

    // SUBPART RECURSION
    if (isset($p->parts)) {
        foreach ($p->parts as $partno0 => $p2) {
            getpart($mbox, $mid, $p2, $partno . '.' . ($partno0 + 1));
        }
        // 1.2, 1.2.1, etc.
    }
}
