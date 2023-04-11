<?php

    class Ticket {

        function __construct($db, $general, $setting, $message, $log="") {
            $this->db = $db;
            $this->general = $general;
            $this->setting = $setting;
            $this->message = $message;
            $this->log = $log;
        }

        public function getTicketDefaultData($params="") {
            $db = $this->db;
            $general = $this->general;

            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $noAdmin[] = array('id' => 0, 'name' => "-");
            $admin = $db->get('admin', null, 'id, name');

            $data['admin'] = array_merge($noAdmin, $admin); 

            $db->where('type', 'ticket%', 'LIKE');
            $db->orderBy('type, priority', 'asc');
            $ticketSelectOption = $db->get('enumerators', null, 'name, type, translation_code');

            foreach($ticketSelectOption as $value) {
                
                $text = $translations[$value['translation_code']][$language] ? $translations[$value['translation_code']][$language] : $value["name"];
                
                if($value['type'] == "ticketStatus") {
                    $status[] = array(
                        'value' => $value['name'],
                        'text' => $text
                    );

                } else if($value['type'] == "ticketPriority") {
                    $priority[] = array(
                        'value' => $value['name'],
                        'text' => $text
                    );

                } else if($value['type'] == "ticketType") {
                    $type[] = array(
                        'value' => $value['name'],
                        'text' => $text
                    );

                } else if($value['type'] == "ticketDepartment") {
                    $department[] = array(
                        'value' => $value['name'],
                        'text' => $text
                    );
                }
            }

            $data['status'] = $status;
            $data['priority'] = $priority;
            $data['type'] = $type;
            $data['department'] = $department;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
        
        public function addTicket($params) {

            $db = $this->db;
            $general = $this->general;
            $message = $this->message;

            //Language Translations.
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $clientID    = $params['clientID'] ? $params['clientID'] : "";
            $clientName  = $params['clientName'];
            $clientEmail = $params['clientEmail'];
            $clientPhone = $params['clientPhone'];

            $status       = $params['status'];
            $priority     = $params['priority'];
            $type         = $params['type'];
            $subject      = $params['subject'];
            $department   = $params['department'];
            $reminderDate = $params['reminderDate'];

            $assigneeID   = $params['assigneeID'] ? $params['assigneeID'] : "";
            $assigneeName = $params['assigneeName'] ? $params['assigneeName'] : "";

            $creatorID   = $db->userID ? $db->userID : "";
            $creatorID   = $params['creatorID'] ? $params['creatorID'] : $creatorID;
            $creatorType = $db->userType ? $db->userType : "Member";
            $creatorType = ($params['creatorType'] ? $params['creatorType'] : ($db->userType ? $db->userType : "Member"));

            $internal = $params['internal'];

            $content = $params['content'];

            $attachmentBase64 = $params['attachmentBase64'];
            $attachmentName   = $params['attachmentName'];
            $attachmentType   = $params['attachmentType'];

            $source = $params['source'] == 'nuxpay' ?  $params['source'] : '';
            $keyword = $params['keyword'];
            
            // $ticketID     = $params['ticketID'] ? $params['ticketID'] : $db->getNewID();
            // $ticketItemID = $params['ticketItemID'] ? $params['ticketItemID'] : $db->getNewID();
            $incomingID   = $params['incomingID'] ? $params['incomingID'] : "";

            $autoEmail = $params['autoEmail'] ? $params['autoEmail'] : 0;

            if(empty($clientName) || empty($clientEmail) || empty($content))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00288'][$language]/* Required fields cannot be left empty. */, 'data' => "");

            $insert = array (
                'client_id' => $clientID,
                'client_name' => $clientName,
                'client_email' => $clientEmail,
                'client_mobile_number' => $clientPhone,
                'status' => $status,
                'priority' => $priority,
                'type' => $type,
                'subject' => $subject,
                'department' => $department,
                'assignee_id' => $assigneeID,
                'assignee_name' => $assigneeName,
                'creator_id' => $creatorID,
                'creator_type' => $creatorType,
                'internal' => $internal,
                'company_name' => $source,
                'keyword' => $keyword,
                'updated_at' => date("Y-m-d H:i:s"),
                'created_at' => date("Y-m-d H:i:s")
            );
            $ticketID = $db->insert('sms_ticket', $insert);
            $uploadID = "";
            if(!empty($attachmentBase64)) {
                $insert = array (
                    'data' => base64_decode($attachmentBase64),
                    'type' => "ticketing",
                    'created_at' => date("Y-m-d H:i:s"),
                    'file_type' => $attachmentType,
                    'file_name' => $attachmentName,
                    'deleted' => 0
                );
                $uploadID = $db->insert('uploads', $insert);

            }

            if(!empty($content)) {
                $insert = array (
                    'ticket_id' => $ticketID,
                    'content' => $content,
                    'status' => "new",
                    'assignee_id' => $assigneeID,
                    'assignee_name' => $assigneeName,
                    'creator_id' => $creatorID,
                    'creator_type' => $creatorType,
                    'email_incoming_id' => $incomingID,
                    'upload_id' => $uploadID,
                    'created_at' => date("Y-m-d H:i:s")
                );
                $ticketItemID = $db->insert('sms_ticket_item', $insert);
                $db->where('id',$uploadID);
                $updateData = array('reference_id' => $ticketItemID);
                $updateUploadID = $db->update('uploads',$updateData);
            }
            
            // Member site enquiry
            if($autoEmail == 1 && $creatorType == "Member" && $internal == 0) {

                $autoMailDescription = $this->ticketingAutoCreateTicketEmailContent($clientName, $source);
                
                // $ticketItemID = $db->getNewID();
//                $insert = array (
//                    'ticket_id' => $ticketID,
//                    'content' => $autoMailDescription,
//                    'status' => "autoReply",
//                    'assignee_id' => $assigneeID,
//                    'assignee_name' => $assigneeName,
//                    'creator_id' => 9,
//                    'creator_type' => "System",
//                    'email_incoming_id' => "",
//                    'upload_id' => "",
//                    'created_at' => date("Y-m-d H:i:s")
//                );
//                $db->insert('sms_ticket_item', $insert);

                // Email to this ticket creator
                $mailSubject = $this->ticketingEmailSubject($ticketID, $subject, '', $source);
                $body = $this->ticketingStandardEmailHeader(1, $ticketID, $source);
                $body .= $autoMailDescription;

                if($source == 'nuxpay'){
                    $output = $message->createCustomizeMessageOut($clientEmail, $mailSubject, $body, "nuxpay_email");
                }
                else{
                    $output = $message->createCustomizeMessageOut($clientEmail, $mailSubject, $body, "Acknowledgement Mail");
                }
               
            }

            // Admin site create new ticket
            if($autoEmail == 0 && $internal == 0) {
                
                $mailSubject = $this->ticketingEmailSubject($ticketID, $subject, '', $source);
                $body = $this->ticketingStandardEmailHeader(2, $ticketID, $source);
                $body .= $this->generateEmailBody($ticketID);
                $body .= $this->generateEmailSignature($assigneeName);

                $output = $message->createCustomizeMessageOut($clientEmail, $mailSubject, $body, "smtp", null, null, 1, 0, $uploadID);
            }

            // Update if message out(email)
            if($internal == 0) {
                $update = array (
                    'sent_history_id' => $output['sentHistoryID'],
                    'sent_history_table' => $output['sentHistoryTable']
                );

                $db->where('id', $ticketItemID);
                $db->update('sms_ticket_item', $update);
            }
            
            // All admin if no assignee
            if(empty($assigneeID)) {
                $adminEmail = $db->getValue('admin', 'email', null);

            } else {
                $db->where('id', $assigneeID);
                $adminEmail = $db->getValue('admin', 'email', null);
            }

            if(empty($adminEmail))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00283'][$language] /* Failed to create ticket. */, 'data' => "");
            
            $mailSubject = $this->ticketingEmailSubject($ticketID, $subject, '', $source);
            
            if(empty($assigneeID)) {
                $body = $this->ticketingEmailInformAdmin($ticketID, $subject, $status, $clientName, $clientEmail, $clientPhone, $department, $content, $source);

            } else {
                $body = $this->ticketingEmailInformAssignedAdmin($ticketID, $subject, $status, $clientName, $clientEmail, $clientPhone, $department, $content, $source);
            }

            // Email to admin
            foreach($adminEmail as $value) {
                if($source == 'nuxpay'){
                    $message->createCustomizeMessageOut($value, $mailSubject, $body, "nuxpay_email", null, null, 1, 0, $uploadID);
                }
                else{
                    $message->createCustomizeMessageOut($value, $mailSubject, $body, "smtp", null, null, 1, 0, $uploadID);   
                }
                
            }

            // Set account manager
            if(!empty($clientID) && !empty($assigneeID)) {

                $db->where('id', $assigneeID);
                $accountManagerName = $db->getValue('admin', 'name');

                if(empty($accountManagerName))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00287'][$language] /* Assignee no found. */, 'data' => "");

                $db->where('name', 'accountManager');
                $db->where('client_id', $clientID);
                $result = $db->getValue('client_setting', 'id');

                if(empty($result)) {
                    $insert = array (
                        'name' => "accountManager",
                        'value' => $assigneeID,
                        'reference' => $accountManagerName,
                        'client_id' => $clientID
                    );
                    $db->insert('client_setting', $insert);

                } else {
                    $update = array (
                        'value' => $assigneeID,
                        'reference' => $accountManagerName
                    );

                    $db->where('name', "accountManager");
                    $db->where('client_id', $clientID);
                    $db->update('client_setting', $update);
                }
            }
            
            if($creatorID == 9) {
                $creatorEmail = "System";

            } else {
                if(strtolower($creatorType) == "admin") {
                    $db->where('id', $creatorID);
                    $creatorEmail = $db->getValue('admin', 'email');

                } else {
                    $creatorEmail = $clientEmail;
                }
            }

            $ticketType = $internal == 1 ? "Internal Ticket" : "Email Ticket";
           
            $find = array (
                "%%subject%%",
                "%%clientName%%",
                "%%clientEmail%%",
                "%%clientPhone%%",
                "%%creator%%",
                "%%ticketType%%",
                "%%noticeOn%%"
            );
            $replace = array (
                $subject,
                $clientName,
                $clientEmail,
                $clientPhone,
                $creatorEmail,
                $ticketType,
                date("Y-m-d H:i:s")
            );

            $messageCode = "10022";
            $message->createMessageOut($messageCode, "", "", $find, $replace);
            
            $data['uploadID'] = $uploadID;
            $data['ticketID'] = $ticketID;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
        
        public function getTicket($params) {

            $db = $this->db;
            $general = $this->general;

            //Language Translations.
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);

            $status = $params['status'];
            $searchData = $params['searchData'];

            if(count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'clientName':
                            $db->where('client_name', '%'.$dataValue.'%', 'LIKE');
                                
                            break;
                            
                        case 'clientEmail':
                            $db->where('client_email', '%'.$dataValue.'%', 'LIKE');
                                
                            break;
                            
                        case 'clientPhone':
                            $db->where('client_mobile_number', '%'.$dataValue.'%', 'LIKE');
                                
                            break;
                            
                        case 'status':
                            $db->where('status', $dataValue);
                                
                            break;

                        case 'assigneeID':
                            $db->where('assignee_id', $dataValue);
                                
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($status == "closed") {
                $db->where('status', 'closed');

            } if($status == "myClosed") {
                $db->where('status', 'closed');
                $db->where('assignee_id', $db->userID);

            } else if($status == "myOpenAndPending") {
                $arr = array('open', 'pending', 'waitingForCustomer');
                $db->where('status', $arr, 'IN');
                $db->where('assignee_id', $db->userID);

            } else if($status == "allUnsolved") {
                $arr = array('deleted', 'closed');
                $db->where('status', $arr, 'NOT IN');

            } else if($status != "") {
                $db->where('status', $status);

            } else {
                $db->where('status', 'deleted', '!=');
            }

            $copyDb = $db->copy();
            $db->orderBy('updated_at', 'Desc');
            $getLastItemStatus = "(SELECT item.status FROM sms_ticket_item item WHERE ticket_id=sms_ticket.id AND item.status!='note' ORDER BY item.created_at DESC limit 1) AS last_status";

            $result = $db->get('sms_ticket', $limit, 'id, client_name, client_email, status, '.$getLastItemStatus.', priority, subject, assignee_id, created_at, updated_at');

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00002'][$language] /* No record found. */, 'data' => "");

            foreach($result as $value) {

                $ticket['id'] = $value['id'];

                $ticket['client_name'] = $value['client_name'];
                $ticket['client_email'] = $value['client_email'];

                $ticket['status'] = $value['status'];
                $ticket['last_status'] = $value['last_status'];
                $ticket['priority'] = $value['priority'];
                $ticket['subject'] = $value['subject'];
                $ticket['assignee_id'] = $value['assignee_id'];

                $ticket['created_at'] = $general->formatDateTimeToString($value['updated_at']);

                $ticketList[] = $ticket;
            }

            $totalRecords = $copyDb->getValue('sms_ticket', 'count(id)');
            $data['ticketList'] = $ticketList;
            $ticketSelectOption = $this->getTicketDefaultData();
            $data['ticketSelectOption'] = $ticketSelectOption['data'];
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
        
        public function getTicketDetails($params) {
            $db = $this->db;
            $general = $this->general;

            //Language Translations.
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
        
            $ticketID = $params['ticketID'];

            $db->where('id', $ticketID);
            $result = $db->get('sms_ticket', 1);
           

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00002'][$language] /* No record found. */, 'data' => "");
            
            foreach($result as $value) {

                $clientName = $value['client_name'];
                
                //get last admin reply
                $db->where('ticket_id', $value['id']);
                $db->where('status', "replied");
                $db->orderBy('created_at', 'Desc');
                $adminLastUpdate = $db->getValue('sms_ticket_item', 'created_at');
                $adminLastUpdate = $adminLastUpdate ? $adminLastUpdate : "-";
                
                //get last client reply
                $db->where('ticket_id', $value['id']);
                $db->where('status', "response");
                $db->orderBy('created_at', 'Desc');
                $clientLastUpdate = $db->getValue('sms_ticket_item', 'created_at');
                $clientLastUpdate = $clientLastUpdate ? $clientLastUpdate : "-";

                $db->where('ticket_id', $value['id']);
                // $db->where('status', 'note', '!=');
                $db->orderBy('created_at', 'Desc');
                $db->orderBy('id', 'Desc');
                $items = $db->get('sms_ticket_item', null, 'content, status AS item_status, creator_id, creator_type, created_at, upload_id');
                if(empty($items))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00002'][$language] /* No record found. */, 'data' => "");

                foreach($items as $item) {
                    
                    if(strtolower($item['creator_type']) == "member"){
                        $name = $clientName;
                    }else if(strtolower($item['creator_type']) == "admin"){
                        $db->where('id', $item['creator_id']);
                        $name = $db->getValue('admin', 'name');
                    }else if(strtolower($item['creator_type']) == "system"){
                        $name = "SMS123 Support";
                    }

                    if($item['upload_id'] == 0 ){
                        $content = $this->explodeTicketingEmailContent(nl2br($item['content']), $item['status']);
                        $isAttachment = 0;
                    }else{

                        $content = $this->explodeTicketingEmailContent(nl2br($item['content']), $item['status']);

                        $db->where('id', $item['upload_id']);
                        $uploadRes = $db->getOne('uploads', 'file_name');

                        $fileName     = $uploadRes['file_name'];
                        $isAttachment = 1;

                        $tempStr = explode('.', $fileName);
                        $extension = $tempStr[1];
                    }

                    if(empty($content) || $content == ""){
                        $content = "Attachment as below:";
                    }

                    $now = new DateTime("now");
                    $inboxDatetime = new DateTime($item['created_at']);
                    $interval = $now->diff($inboxDatetime);

                    if($interval->y >= 1)
                        $timeDiff = $interval->y." year ago";
                    elseif($interval->m >= 1)
                        $timeDiff = $interval->m." month ago";
                    elseif($interval->d >= 1)
                        $timeDiff = $interval->d." day ago";
                    elseif($interval->h >= 1)
                        $timeDiff = $interval->h." hour ago";
                    elseif($interval->i >= 1 )
                        $timeDiff = $interval->i." minute ago";
                    else
                        $timeDiff = "Just now";
                        
                    $ticketItem[] = array (
                        'content' => $content,
                        'fileName' => $fileName,
                        'extension' => $extension,
                        'item_status' => $item['item_status'],
                        'name' => $name,
                        'time' => $timeDiff,
                        'attachmentUploadID' => $item['upload_id'],
                        'isAttachment' => $isAttachment
                    );
                }
                
                $ticket = array (
                    'id' => $value['id'],

                    'clientID' => $value['client_id'] ? $value['client_id'] : "-",
                    'clientName' => $value['client_name'],
                    'clientEmail' => $value['client_email'],
                    'clientPhone' => $value['client_mobile_number'],

                    'status' => $value['status'],
                    'priority' => $value['priority'],
                    'type' => $value['type'],
                    'subject' => $value['subject'],
                    'department' => $value['department'],

                    'assigneeID' => $value['assignee_id'],
                    // 'assigneeName' => $value['assignee_name'] ? $value['assignee_name'] : "",
                    // 'creatorID' => $value['creator_id'],

                    'internal' => $value['internal'] == 1 ? "Internal Ticket" : "Email Ticket",
                    'keyword' => $value['keyword'],
                    'createdAt' => $general->formatDateTimeToString($value['created_at']),

                    'adminLastUpdate' => $adminLastUpdate,
                    'clientLastUpdate' => $clientLastUpdate
                );
            }
            
            $data['ticket'] = $ticket;
            $data['ticketItem'] = $ticketItem;
            $ticketSelectOption = $this->getTicketDefaultData();
            $data['ticketSelectOption'] = $ticketSelectOption['data'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
        
        public function unassignTickets($params) {
            $db = $this->db;
            $general = $this->general;

            //Language Translations.
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);

            $searchData = $params['searchData'];

            if(count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'clientName':
                            $db->where('client_name', '%'.$dataValue.'%', 'LIKE');
                                
                            break;
                            
                        case 'clientEmail':
                            $db->where('client_email', '%'.$dataValue.'%', 'LIKE');
                                
                            break;
                            
                        case 'clientPhone':
                            $db->where('client_mobile_number', '%'.$dataValue.'%', 'LIKE');
                                
                            break;
                            
                        case 'status':
                            $db->where('status', $dataValue);
                                
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('status', 'deleted', '!=');
            $db->where('assignee_id', '0');
            $copyDb = $db->copy();
            $db->orderBy('updated_at', 'Desc');
            $getLastItemStatus = "(SELECT item.status FROM sms_ticket_item item WHERE ticket_id=sms_ticket.id AND item.status!='note' ORDER BY item.created_at DESC limit 1) AS last_status";

            $result = $db->get('sms_ticket', $limit, 'id, client_name, client_email, status, '.$getLastItemStatus.', priority, subject, assignee_id, created_at, updated_at');
            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00002'][$language] /* No record found. */, 'data' => "");

            foreach($result as $value) {

                $ticket['id'] = $value['id'];

                $ticket['client_name'] = $value['client_name'];
                $ticket['client_email'] = $value['client_email'];

                $ticket['status'] = $value['status'];
                $ticket['last_status'] = $value['last_status'];
                $ticket['priority'] = $value['priority'];
                $ticket['subject'] = $value['subject'];
                $ticket['assignee_id'] = $value['assignee_id'];

                $ticket['created_at'] = $general->formatDateTimeToString($value['updated_at']);

                $ticketList[] = $ticket;
            }

            $totalRecords = $copyDb->getValue('sms_ticket', 'count(id)');
            $data['ticketList'] = $ticketList;
            $ticketSelectOption = $this->getTicketDefaultData();
            $data['ticketSelectOption'] = $ticketSelectOption['data'];
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
        
        public function updateTicket($params) {
            $db = $this->db;
            $general = $this->general;
            $message = $this->message;

            //Language Translations.
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $ticketIDs = $params['ticketIDs'];

            $clientID    = $params['clientID'];
            $clientName  = $params['clientName'];
            $clientEmail = $params['clientEmail'];
            $clientPhone = $params['clientPhone'];

            $status       = $params['status'];
            $priority     = $params['priority'];
            $type         = $params['type'];
            $subject      = $params['subject'];
            $department   = $params['department'];
            $reminderDate = $params['reminderDate'];

            $assigneeID = $params['assigneeID'];

            $internal = $params['internal'];

            $content = $params['content'] ? $params['content'] : "";

            $attachmentBase64 = $params['attachmentBase64'];
            $attachmentType   = $params['attachmentType'];
            $attachmentName   = $params['attachmentName'];

            $actionType = $params['actionType'] ? $params['actionType'] : ""; // autoClose
            $updateType = $params['updateType'] ? $params['updateType'] : "ticket"; // ticket, ticketItem
            $ticketAction = $params['ticketAction'] ? $params['ticketAction'] : "replied";

            $userID = $db->userID ? $db->userID : 9;
            $userType = $db->userType ? $db->userType : "System";

            $userName = "Member";
            if(strtolower($userType) == "admin") {
                $db->where('id', $userID);
                $admin = $db->getOne('admin', 'name, email');
                if(empty($admin)){
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E00284'][$language] /* Failed to update ticket. */, 'data' => '');
                }

                $userName = $admin['name'];
                $userEmail = $admin['email'];
            }

            // API starting here **********************************************************************

            if(empty($ticketIDs))
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E00285'][$language] /* No ticket selected. */, 'data' => '');

            $db->where('id', $ticketIDs, 'IN');
            $ticket = $db->get('sms_ticket');
            if(empty($ticket))
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E00286'][$language] /* Ticket no found. */, 'data' => '');
            
            // Update ticket
            if($updateType == "ticket") {

                foreach($ticket as $value) {

                    $ticketID = $value['id'];
//                    $clientName = $clientName ? $clientName : $value['client_name'];
//                    $clientEmail = $clientEmail ? $clientEmail : $value['client_email'];
//                    $clientPhone = $clientPhone ? $clientPhone : $value['client_mobile_number'];
//                    $status = $status ? $status : $value['status'];
//                    $subject = $subject ? $subject : $value['subject'];
//                    $department = $department ? $department : $value['department'];
//                    $internal = $internal ? $internal : $value['internal'];
                    $currentStatus = $value['status'];
                    $autoNoteMsg = "";

                    if(!empty($assigneeID) && $assigneeID != $value['assignee_id'] && $actionType != "autoClose") {

                        $col[] = "assignee_id";
                        $val[] = $assigneeID;

                        $db->where('id', $assigneeID);
                        $assignee = $db->getOne('admin', 'name, email');

                        $col[] = "assignee_name";
                        $val[] = $assignee['name'];
                        
                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";

                        $autoNoteMsg .= "- Update Assignee to ".$assignee['name'];

                        // Email to the assigned admin
                        $mailSubject = $this->ticketingEmailSubject($ticketID, $subject);
                        $body = $this->ticketingEmailInformAssignedAdmin($ticketID, $subject, $status, $clientName, $clientEmail, $clientPhone, $department, $content);

                        $message->createCustomizeMessageOut($assignee['email'], $mailSubject, $body, "smtp");

                    } else {
                        $assigneeID = $value['assignee_id'];
                    }

                    if(!empty($status) && $status != $value['status']) {
                        $col[] = "status";
                        $val[] = $status;

                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";

                        $autoNoteMsg .= "- Update Status to ".$status;
                    }

                    if(!empty($priority) && $priority != $value['priority']) {
                        $col[] = "priority";
                        $val[] = $priority;

                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";

                        $autoNoteMsg .= "- Update Priority to ".$priority;
                    }

                    if(!empty($type) && $type != $value['type']) {
                        $col[] = "type";
                        $val[] = $type;

                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";
                        
                        $autoNoteMsg .= "- Update Type to ".$type;
                    }

                    if(!empty($subject) && $subject != $value['subject']) {
                        $col[] = "subject";
                        $val[] = $subject;

                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";

                        $autoNoteMsg .= "- Update Subject to ".$subject;
                    }

                    if(!empty($department) && $department != $value['department']) {
                        $col[] = "department";
                        $val[] = $department;

                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";

                        $autoNoteMsg .= "- Update Department to ".$department;
                    }

                    if(!empty($reminderDate) && $reminderDate != $value['reminderDate']) {
                        $col[] = "reminder_date";
                        $val[] = $reminderDate;

                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";
                        
                        $autoNoteMsg .= "- Update Reminder Date to ".$reminderDate;
                    }

                    if(!empty($clientName) && $clientName != $value['client_name']) {
                        $col[] = "client_name";
                        $val[] = $client_name;

                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";
                        
                        $autoNoteMsg .= "- Update Client Name to ".$clientName;
                    }

                    if(!empty($clientEmail) && $clientEmail != $value['client_email']) {
                        $col[] = "client_email";
                        $val[] = $clientEmail;

                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";

                        $autoNoteMsg .= "- Update Client Email to ".$clientEmail;
                    }

                    if(!empty($clientPhone) && $clientPhone != $value['client_mobile_number']) {
                        $col[] = "client_mobile_number";
                        $val[] = $clientPhone;

                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";
                        
                        $autoNoteMsg .= "- Update Mobile Number to ".$clientPhone;
                    }

                    if($internal != "" && $internal != $value['internal']) {
                        $col[] = "internal";
                        $val[] = $internal;

                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";
                                        
                        if($internal == "1") {
                            $autoNoteMsg .= "- Update to internal ticket.";

                        } else {
                            $autoNoteMsg .= "- Update to email ticket.";
                        }
                    }

                    $col[] = "updated_at";
                    $val[] = date("Y-m-d H:i:s");

                    $update = array_combine($col, $val);

                    // update ticket
                    $db->where('id', $ticketID);
                    $db->update('sms_ticket', $update);
          
                    // auto note
                    if(!empty($autoNoteMsg)) {

                        $insert = array (
                            'ticket_id' => $ticketID,
                            'content' => $autoNoteMsg,
                            'status' => "note",
                            'creator_id' => $userID,
                            'creator_type' => $userType,
                            'created_at' => date("Y-m-d H:i:s")
                        );
                        $db->insert('sms_ticket_item', $insert);    
                    }
                
                            
                    // close ticket
                    if($status == "closed" && $currentStatus != "closed" && $internal == 0) {

                        $db->where('ticket_id', $ticketID);
                        $db->orderBy('created_at', 'asc');
                        $creatorTypeTemp = $db->getValue('sms_ticket_item', 'creator_type');
                        
                        if(strtolower($creatorTypeTemp) == "admin") {
                            // if have recode mean this ticket created by admin. No need send email to user ticket closed.
                        } else {

                            $closeMailDescription = $this->ticketingCloseTicketEmailContent($clientName, $userName);

                            // $ticketItemID = $db->getNewID();
                            $insert = array (
                                'ticket_id' => $ticketID,
                                'content' => $closeMailDescription,
                                'status' => "closed",
                                'creator_id' => $userID,
                                'creator_type' => $userType,
                                'created_at' => date("Y-m-d H:i:s")
                            );
                            $ticketItemID = $db->insert('sms_ticket_item', $insert);

                            $mailSubject = $this->ticketingEmailSubject($ticketID, $subject, "closeTicket");
                            $body = $this->ticketingStandardEmailHeader(1, $ticketID);
                            $body .= $this->generateEmailBody($ticketID);
                            $body .= $this->generateEmailSignature($userName);
                            
                            $output = $message->createCustomizeMessageOut($clientEmail, $mailSubject, $body, "smtp");

                            $update = array (
                                'sent_history_id' => $output['sentHistoryID'],
                                'sent_history_table' => $output['sentHistoryTable']
                            );

                            $db->where('id', $ticketItemID);
                            $db->update('sms_ticket_item', $update);
                        }
                    }
                            
                    if($status == "closed" && $currentStatus != "closed") {

                        ##### Auto assigned to admin who closed ticket  ######
                        unset($updateData);
                        $updateData = array(
                                        "assignee_id" => $userID,
                                        "assignee_name" => $userName,
                                            );
                        $db->where('id',$ticketID);
                        $db->update("sms_ticket",$updateData);

                        $actionType == "autoClose" ? "Auto Close" : $userName;
                        $internal == 1 ? "Internal Ticket" : "Email Ticket";

                        $find = array (
                            "%%ticketID%%",
                            "%%subject%%",
                            "%%clientName%%",
                            "%%clientEmail%%",
                            "%%clientPhone%%",
                            "%%closeBy%%",
                            "%%ticketType%%"
                        );
                        $replace = array (
                            $ticketID,
                            $subject,
                            $clientName,
                            $clientEmail,
                            $clientPhone,
                            $actionType,
                            $internal
                        );

                        $messageCode = "10025";
                        $message->createMessageOut($messageCode, "", "", $find, $replace);
                    }
                            
                    // set account manager
                    if(!empty($clientID) && !empty($assigneeID)) {

                        $db->where('id', $assigneeID);
                        $accountManagerName = $db->getValue("admin", "name");

                        if(empty($accountManagerName))
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00287'][$language] /* Assignee no found. */, 'data' => "");

                        $db->where('name', 'accountManager');
                        $db->where('client_id', $clientID);
                        $result = $db->getValue('client_setting', 'id');

                        if(empty($result)) {
                            $insert = array (
                                'name' => "accountManager",
                                'value' => $assigneeID,
                                'reference' => $accountManagerName,
                                'client_id' => $clientID
                            );

                            $db->insert('client_setting', $insert);
                        } else {

                            $update = array (
                                'value' => $assigneeID,
                                'reference' => $accountManagerName
                            );

                            $db->where('name', "accountManager");
                            $db->where('client_id', $clientID);
                            $db->update("client_setting", $update);
                        }
                    }
                }
            }
            // update ticket item
            else if($updateType == "ticketItem") {

                foreach($ticket as $value) {

                    $ticketID = $value['id'];
                    $clientName = $clientName ? $clientName : $value['client_name'];
                    $clientEmail = $clientEmail ? $clientEmail : $value['client_email'];
                    $clientPhone = $clientPhone ? $clientPhone : $value['client_mobile_number'];
                    $status = $status ? $status : $value['status'];
                    $subject = $subject ? $subject : $value['subject'];
                    $department = $department ? $department : $value['department'];
                    $internal = $internal ? $internal : $value['internal'];
                    $currentStatus = $value['status'];
                    $autoNoteMsg = "";
                        
                    // auto assign
                    if(!empty($userID) && $value['assignee_id'] != $userID && $userID != 9) {

                        $col[] = "assignee_id";
                        $val[] = $userID;

                        $col[] = "assignee_name";
                        $val[] = $userName;
                        
                        if($autoNoteMsg != "")
                            $autoNoteMsg .= "<br>";

                        $autoNoteMsg .= "- Update Assignee to ".$userName;

                        // email to the assigned admin
                        $mailSubject = $this->ticketingEmailSubject($ticketID, $subject);
                        $body = $this->ticketingEmailInformAssignedAdmin($ticketID, $subject, $status, $clientName, $clientEmail, $clientPhone, $department, $content);

                        $message->createCustomizeMessageOut($clientEmail, $mailSubject, $body, "smtp");
                    }
                        
                    // update ticket status
                    $col = array('assignee_id', 'assignee_name', 'updated_at');
                    $val = array($userID, $userName, date("Y-m-d H:i:s"));

                    $update = array_combine($col, $val);

                    $db->where('id', $ticketID);
                    $db->update('sms_ticket', $update);
                        
                    // auto note
                    if(!empty($autoNoteMsg)) {

                        $insert = array (
                            'ticket_id' => $ticketID,
                            'content' => $autoNoteMsg,
                            'status' => "note",
                            'creator_id' => $userID,
                            'creator_type' => $userType,
                            'created_at' => date("Y-m-d H:i:s")
                        );
                        $db->insert('sms_ticket_item', $insert);
                    }


                    if(!empty($attachmentBase64)) {

                        $uploadID = "";
                        
                        $insert = array (
                            'data' => base64_decode($attachmentBase64),
                            'type' => "ticketing",
                            'created_at' => date("Y-m-d H:i:s"),
                            'file_type' => $attachmentType,
                            'file_name' => $attachmentName,
                            'deleted' => 0
                        );
                        $uploadID = $db->insert('uploads', $insert);

                        $insert = array (
                            'ticket_id' => $ticketID,
                            'content' => $content,
                            'status' => $ticketAction,
                            'creator_id' => $userID,
                            'creator_type' => $userType,
                            'upload_id' => $uploadID,
                            'created_at' => date("Y-m-d H:i:s")
                        );
                        $ticketItemTempID = $db->insert('sms_ticket_item', $insert);

                        $db->where('id',$uploadID);
                        $updateData = array('reference_id' => $ticketItemTempID);
                        $updateUploadID = $db->update('uploads',$updateData);

                        if($ticketAction != "note" && $internal == 0) {
                            // email to client
                            $mailSubject = $this->ticketingEmailSubject($ticketID, $subject);
                            $body = $this->ticketingStandardEmailHeader(1, $ticketID);
                            $body .= $this->generateEmailBody($ticketID);
                            $body .= $this->generateEmailSignature($userName);
                            
                            $output = $message->createCustomizeMessageOut($clientEmail, $mailSubject, $body, "smtp", null, null, 1, 0, $uploadID);
                        }

                    }else{
                        
                        $insert = array (
                            'ticket_id' => $ticketID,
                            'content' => $content,
                            'status' => $ticketAction,
                            'creator_id' => $userID,
                            'creator_type' => $userType,
                            'upload_id' => 0,
                            'created_at' => date("Y-m-d H:i:s")
                        );
                        $ticketItemTempID = $db->insert('sms_ticket_item', $insert);

                        if($ticketAction != "note" && $internal == 0) {
                            // email to client
                            $mailSubject = $this->ticketingEmailSubject($ticketID, $subject);
                            $body = $this->ticketingStandardEmailHeader(1, $ticketID);
                            $body .= $this->generateEmailBody($ticketID);
                            $body .= $this->generateEmailSignature($userName);
                            
                            $output = $message->createCustomizeMessageOut($clientEmail, $mailSubject, $body, "smtp");
                        }
                    }
                        
                    // close ticket
                    if($status == "closed" && $currentStatus != "closed" && $internal == 0) {

                        $db->where('ticket_id', $ticketID);
                        $db->orderBy('created_at', 'asc');
                        $creatorTypeTemp = $db->getValue('sms_ticket_item', 'creator_type');
                        
                        if($creatorTypeTemp == "Admin") {
                            // if have recode mean this ticket created by admin. No need send email to user ticket closed.
                        } else {

                            $closeMailDescription = $this->ticketingCloseTicketEmailContent($clientName, $userName);

                            // $ticketItemTempID = $db->getNewID();
                            $insert = array (
                                // 'id' => $ticketItemTempID,
                                'ticket_id' => $ticketID,
                                'content' => $closeMailDescription,
                                'status' => "closed",
                                'creator_id' => $userID,
                                'creator_type' => $userType,
                                'created_at' => date("Y-m-d H:i:s")
                            );
                            $ticketItemTempID = $db->insert('sms_ticket_item', $insert);

                            #### EMAIL TO CLIENT ####
                            $mailSubject = $this->ticketingEmailSubject($ticketID, $subject);
                            $body = $this->ticketingStandardEmailHeader(1, $ticketID);
                            $body .= $this->generateEmailBody($ticketID);
                            $body .= $this->generateEmailSignature($userName);
                            
                            $output = $message->createCustomizeMessageOut($clientEmail, $mailSubject, $body, "smtp");
                        }
                    }

                    $update = array (
                        'sent_history_id' => $output['sentHistoryID'],
                        'sent_history_table' => $output['sentHistoryTable']
                    );

                    $db->where('id', $ticketItemTempID);
                    $db->update('sms_ticket_item', $update);
                        
                    if($status == "closed" && $currentStatus != "closed") {

                        $actionType == "autoClose" ? "Auto Close" : $userName;
                        $internal == 1 ? "Internal Ticket" : "Email Ticket";

                        $find = array (
                            "%%ticketID%%",
                            "%%subject%%",
                            "%%clientName%%",
                            "%%clientEmail%%",
                            "%%clientPhone%%",
                            "%%closeBy%%",
                            "%%ticketType%%"
                        );
                        $replace = array (
                            $ticketID,
                            $subject,
                            $clientName,
                            $clientEmail,
                            $clientPhone,
                            $actionType,
                            $internal
                        );

                        $messageCode = "10025";
                        $message->createMessageOut($messageCode, "", "", $find, $replace);
                    }
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00001'][$language], 'data' => '');// Completed Sucessfully.
        }
        
        public function deleteTicket($params) {
            $db = $this->db;
            $general = $this->general;

            //Language Translations.
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $ticketIDs = $params['ticketIDs'];

            if(empty($ticketIDs))
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations['E00285'][$language] /* No ticket selected. */, 'data' => '');
            
            $db->where('id', $ticketIDs, 'IN');
            $db->update('sms_ticket', array('status' => "deleted", 'updated_at' => date("Y-m-d H:i:s")));
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function enquiry($params, $msgpackData) {
           
            $db = $this->db;
            $general = $this->general;
            $message = $this->message;
          
            // Language Translations.
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();
            $clientName = $params['clientName'];
            $clientEmail = $params['clientEmail'];
            $clientPhone = $params['clientPhone'];
            $subject = $params['subject'];
            $content = $params['content'];
            $hostName     = $msgpackData['hostName'];
            $platform      = $msgpackData['type'];
            $ip            = $msgpackData['ip'];
            $sourceVersion = $msgpackData['sourceVersion'];
            $sourceVersion = $db->escape($sourceVersion);
            $userAgent     = $msgpackData['userAgent'];
      
            if(empty($clientName) || empty($clientEmail) || empty($clientPhone) || empty($subject) || empty($content))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E00288'][$language]/* Required fields cannot be left empty. */, 'data' => "");
                
          
            $db->where('email', $clientEmail);
            $db->where('deleted', 0);
            $getAccountManagerID = "(SELECT reference FROM client_setting WHERE client_id=client.id AND client_setting.name='accountManager') AS account_manager_name";
            $result = $db->getOne('client', 'id,'.$getAccountManagerID);

            $clientID = 0;
            $accountManager = "-";
            if(!empty($result)) {
                $clientID = $result['id'];
                $accountManager = $result['account_manager_name'];
            }
            
            // New Ticket
            $ticket = array (
                'clientID' => $clientID,
                'clientName' => $clientName,
                'clientEmail' => $clientEmail,
                'clientPhone' => $clientPhone,
                'status' => "open",
                'priority' => 1,
                'type' => "",
                'subject' => $subject,
                'department' => "",
                'reminderDate' => "",
                'assigneeID' => "",
                'assigneeName' => "",
                'creatorID' => $clientID,
                'internal' => 0,
                'content' => nl2br($content),
                'autoEmail' => 1,
            );
            $this->addTicket($ticket);

            $find = array (
                "%%name%%",
                "%%email%%",
                "%%mobileNumber%%",
                "%%ip%%",
                "%%domain%%",
                "%%subject%%",
                "%%content%%", 
                "%%date%%", 
            );
            $replace = array (
                $clientName,
                $clientEmail,
                $clientPhone,
                $ip,
                $hostName,
                $subject,
                $content,
                date("Y-m-d H:i:s"),
            );

            $messageCode = "10035";
            $message->createMessageOut($messageCode, "", "", $find, $replace);
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }
        
        function generateEmailBody($ticketID) {
            $db = $this->db;
            $general = $this->general;

            $db->where('ticket_id', $ticketID);
            $db->where('status', 'note', '!=');
            $db->orderBy('created_at', 'Desc');
            $db->orderBy('id', 'Desc');
            $result = $db->get('sms_ticket_item', null, 'ticket_id, content, status, creator_id, creator_type, created_at');
            
            foreach($result as $value) {

                if($value['creator_id'] == 0){
                    $db->where('id', $value['ticket_id']);
                    $result1 = $db->getOne('sms_ticket', 'client_name');
                    $name    = $result1['client_name'];
                }else{
                    $db->where('id', $value['creator_id']);

                    if(strtolower($value['creator_type']) == "admin"){
                        $name = $db->getValue('admin', 'name');
                    }else{
                        $name = $db->getValue('client', 'name');
                    }
                }
                
                $description = $this->explodeTicketingEmailContent(nl2br($value['content']), $value['status']);
                
                $createdAt = $general->formatDateTimeToString($value['created_at'],"d M, H:i");

                $body .= "<hr style='border-top: dotted 1px;color:grey;'><br/><table><tr><td style='font-weight: bold;color: rgb(27,29,30);font-size: 15px;line-height: 18px;'><a rel='nofollow' href='#' style='text-decoration:none; color:#333'>".$name."</a></tr><tr><td><div style='color:#bbbbbb;font-size: 13px;'>".$createdAt."</div></td></tr><tr><td><br/><div style='color: #2b2e2f;font-size: 14px;line-height: 22px;margin: 15px 0;'>".$description."</div></td></tr></table><br/>";
            }
            
            return $body;
        }
        
        function generateEmailSignature($assigneeName, $source = null){
            
            $setting = $this->setting;
            
            if($source == 'nuxpay'){
                $systemDisplayName = $setting->systemSetting['payCompanyName'];
            }else{
                $systemDisplayName = $setting->systemSetting['systemDisplayName'];
            } 
            
            $html .= "<br>";
            $html .= "Best Regards<br><br>";
            $html .= "($assigneeName)<br>";
            $html .= "Customer Support<br>";
            $html .= "$systemDisplayName Inc.<br>";
            
            return $html;
            
        }

        function explodeTicketingEmailContent($content, $type) {

            $filterDescription = explode("##- Please type your reply above this line -##", $content);
            
            if(count($filterDescription) == 1)
                return $content;
            
            if($filterDescription[0]=="")
                $description = $filterDescription[1];
            else
                $description = $filterDescription[0];
            
            if($type == "response") {

                $split = explode("wrote:", $description);

                if(count($split)) {
                    $description = $this->cutOutEmailContent($split[0], 0);

                } else {
                    $split3 = explode("\n", $description);
                    $descriptionArr = array_slice($split3, 0, -3);
                    $description = implode("\n",$descriptionArr);
                }
            }
            
            return $description;
        }

        function cutOutEmailContent($str, $hasContentCutted) {
            $split = explode("\n", $str);
            $arryCount = count($split);
            
            if($split[$arryCount-1] != "") {
                unset($split[$arryCount-1]);

                return implode("\n", $split);

            } else {
                $hasContentCutted++;
                unset($split[$arryCount-1]);

                return $this->cutOutEmailContent(implode("\n", $split), $hasContentCutted);
            }
        }
        
        public function expireTicketCheck() {
            $db = $this->db;
            $setting = $this->setting;
            $log = $this->log;

            $db->where("CURDATE() > DATE_ADD(updated_at ,INTERVAL ".$setting->systemSetting['autoCloseTicket']." HOUR)");
            $db->where('status', 'open');
            $copyDb = $db->copy();
            $ticketIDs = $db->getValue('sms_ticket', 'id', null);

            $count = $copyDb->getValue('sms_ticket', 'COUNT(id)');

            $log->write(date("Y-m-d H:i:s")." Start checking expire ticket. Total ticket: ".$count."\n");

            $params = array (
                "ticketIDs" => $ticketIDs,
                "updateType" => "ticket",
                "status" => "closed",
                "actionType" => "autoClose",
            );

            $this->updateTicket($params);
            
            $log->write(date("Y-m-d H:i:s")." End checking expire ticket.\n");

            return;
        }

        #### TICKETING : Auto email ####
        function ticketingAutoCreateTicketEmailContent($clientName, $source = null){
            $setting = $this->setting;
            
            if($source == 'nuxpay'){
                $systemDisplayName = $setting->systemSetting['payCompanyName'];
            }else{
                $systemDisplayName = $setting->systemSetting['systemDisplayName'];
            }
           
            $html  = "Dear $clientName,<br><br>";
            $html .= "We would like to acknowledge that we have received your request and a ticket has been created.";
            $html .= "A support representative will be reviewing your request and will send you a personal response.(usually within 24 hours).<br><br>";
            $html .= "Thank you for your patience.<br><br>";
            $html .= "Sincerely,<br>";
            $html .= $systemDisplayName." Support Team";
                
            return $html;
        }

        #### TICKETING : email subject ####
        function ticketingEmailSubject($ticketID, $subject, $type="", $source = null) {
            $setting = $this->setting;
            
            if($source == 'nuxpay'){
                $systemDisplayName = $setting->systemSetting['payCompanyName'];
            }
            else{
                $systemDisplayName = $setting->systemSetting['systemDisplayName'];
            }

            if($type == "clientResponse") {
                $html = "[".$systemDisplayName." #".$ticketID."] New Reply Received - ".$subject;

            } else if($type == "closeTicket") {
                $html = "[".$systemDisplayName." #".$ticketID."] Ticket Closed - ".$subject;

            } else {
                $html = "[".$systemDisplayName." #".$ticketID."] - ".$subject;
            }

            return $html;
        }

        #### TICKETING : email header ####
        function ticketingStandardEmailHeader($version, $ticketID, $source = null) {
            $setting = $this->setting;

            if($source = 'nuxpay'){
                $imageLogoPath = $setting->system['payEmailLogoImagePath'];

            }
            else{
                $domainName = $setting->systemSetting['officialURL'];
                $ticketingEmailHeaderImagePath = $setting->systemSetting["ticketingEmailHeaderImagePath"];
                $imageLogoPath ="https://".$domainName . $ticketingEmailHeaderImagePath;
            }
            
            if($version == 2) {
                $html = "<div style='color:#b5b5b5;font-size:12px;'>##- Please type your reply above this line -##</div>
                <img src='$imageLogoPath' style='height:60px;'><br/>";

            } else {
                $html = "<div style='color:#b5b5b5;font-size:12px; height:60px;'>##- Please type your reply above this line -##</div>
                <img src='$imageLogoPath' style='height:60px;'><br/>
                <div style='font-size:12px;'>Your request $ticketID has been updated. To add additional comments, reply to this email.</div>";
            }
            
            return $html;
        }

        #### TICKETING : new ticket created inform admin look into it ####
        function ticketingEmailInformAdmin($ticketID, $subject, $status, $clientName, $clientEmail, $clientPhone, $department, $content, $source= null) {
            $setting = $this->setting;
            
            $status = $this->ticketingDisplayStatus($status);
            if($source == 'nuxpay'){
                $systemDisplayName = $setting->systemSetting['payCompanyName'];
            }else{
                $systemDisplayName = $setting->systemSetting['systemDisplayName'];
            }
            
            
            $html  = "Dear Admin, <br><br>";
            $html .= "A new ticket has been created. You may view and respond to the ticket.<br><br>";
            $html .= "Ticket ID  : ".$ticketID."<br>";
            $html .= "Subject    : ".$subject."<br>";
            $html .= "Name       : ".$clientName."<br>";
            $html .= "Email      : ".$clientEmail."<br>";
            $html .= "Contact No : ".$clientPhone."<br>";
            $html .= "Department : ".$department."<br><br>";

            $html .= "Content    : <br>".nl2br($content)."<br><br>";

            $html .= "Regards,<br>";
            $html .= $systemDisplayName." Support Team";
            
            return $html;
        }

        #### TICKETING : new ticket created inform "assigned" admin look into it ####
        function ticketingEmailInformAssignedAdmin($ticketID, $subject, $status, $clientName, $clientEmail, $clientPhone, $department, $content, $source = null) {
            $setting = $this->setting;
            
            $status = $this->ticketingDisplayStatus($status);
            if($source == 'nuxpay'){
                $systemDisplayName = $setting->systemSetting['payCompanyName'];
            }
            else{
                $systemDisplayName = $setting->systemSetting['systemDisplayName'];
            }
            
            $html  = "Dear Admin,<br><br>";
            $html .= "You have been assigned to a ticket.<br>";
            $html .= "You may view and respond to the ticket.<br><br>";
            $html .= "TicketID   : ".$ticketID."<br>";
            $html .= "Subject    : ".$subject."<br>";
            $html .= "Status     : ".$status."<br>";
            $html .= "Name       : ".$clientName."<br>";
            $html .= "Email      : ".$clientEmail."<br>";
            $html .= "Contact No : ".$clientPhone."<br>";
            $html .= "Department : ".$department."<br><br>";
            $html .= "Content    : <br>".nl2br($content)."<br><br>";
            $html .= "Regards,<br>";
            $html .= $systemDisplayName." Support Team";
            
            return $html;
        }

        function ticketingDisplayStatus($status) {

            switch($status) {
                case "open":
                    $displayStatus = "Open";
                    break;
                case "pending":
                    $displayStatus = "Pending";
                    break;
                case "colsed":
                    $displayStatus = "Closed";
                    break;
                case "waitingForCustomer":
                    $displayStatus = "Waiting For Customer";
                    break;
                default:
                    $displayStatus = $status;
                    break;
            }

            return $displayStatus;
        }

        #### TICKETING : close ticket ####
        function ticketingCloseTicketEmailContent($clientName, $fromName){
            $setting = $this->setting;
            
            $systemDisplayName = $setting->systemSetting["systemDisplayName"];
            
            $html  = "Dear ".$clientName.",<br><br>";
            $html .= "Our system has indicated that your ticket has been closed.<br><br>";
            $html .= "We hope that the ticket was resolved to your satisfaction. If you feel that the ticket should not be closed or if the ticket has not been resolved, please reply to this email.<br><br>";
            $html .= "Regards,<br>";
            $html .= $systemDisplayName." Support Team";
            
            return $html;
        }

        #### TICKETING : inform Admin client response ticket ####
        function ticketingClientResponseInformAdminEmailContent($ticketID, $subject, $status, $clientName, $clientEmail, $clientPhone, $department, $content) {
            $setting = $this->setting;
            
            $status = $this->ticketingDisplayStatus($status);
            
            $systemDisplayName = $setting->systemSetting['systemDisplayName'];
            
            $html  = "Dear Admin,<br><br>";
            $html .= "The customer has responded to the ticket.<br>";
            $html .= "You may view and respond to the ticket.<br><br>";
            $html .= "TicketID   : ".$ticketID."<br>";
            $html .= "Subject    : ".$subject."<br>";
            $html .= "Status     : ".$status."<br>";
            $html .= "Name       : ".$clientName."<br>";
            $html .= "Email      : ".$clientEmail."<br>";
            $html .= "Contact No : ".$clientPhone."<br>";
            $html .= "Department : ".$department."<br>";
            $html .= "Content    : <br>".nl2br($content)."<br><br>";
            $html .= "Regards,<br>";
            $html .= $systemDisplayName." Support Team";
            
            return $html;
        }

        function getWhitelistIncomingEmail($params) {
              $db = $this->db;
              $general = $this->general;

               //Language Translations.
              $language     = $this->general->getCurrentLanguage();
              $translations = $this->general->getTranslations();
              
              if ($params['pagination'] == "No") {
                  // This is for getting all the countries without pagination
                  $limit = null;
              }
              else {
              
                  $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
                  //Get the limit.
                  $limit        = $general->getLimit($pageNumber);
              }

              $searchData = $params['searchData'];
              
              // Means the search params is there
              if (count($searchData) > 0) {
                  foreach ($searchData as $k => $v) {
                      $dataName  = trim($v['dataName']);
                      $dataValue = trim($v['dataValue']);
                          

                      switch($dataName) {    
                          case 'email':
                              $db->where('value', "%".$dataValue."%", 'LIKE');
                              break;
                      }
                      unset($dataName);
                      unset($dataValue);
                  }
              }

              $db->orderBy('id','DESC');
              $copyDb = $db->copy();
              $result = $db->get("sms_email_whitelist", $limit);

              if (!empty($result)) {
                  foreach($result as $value) {
                    $whitelistIncomingEmailData['id']  = $value['id'];
                    $whitelistIncomingEmailData['email'] = $value['value'];
                    $whitelistIncomingEmailList[] = $whitelistIncomingEmailData;
                  }
                  
                  $totalRecords         = $copyDb->getValue("sms_email_whitelist", "count(id)");
                  $data['whitelistIncomingEmailData']    = $whitelistIncomingEmailList;
                  $data['totalPage']    = ceil($totalRecords/$limit[1]);
                  $data['pageNumber']   = $pageNumber;
                  $data['totalRecord']  = $totalRecords;
                  $data['numRecord']    = $limit[1];
                  
                  return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00001"][$language], 'data' => $data); //Completed successfully.
              } else {
                  return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00002"][$language], 'data'=>""); //No record found.
              }
          }

          function deleteWhitelistIncomingEmail($params){
            $db = $this->db;
            $general = $this->general;

             //Language Translations.
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $id = $params["id"];

            if(!empty($id)){
              foreach ($id as $value) {
                $db->where("id", $value);
                $result = $db->get("sms_email_whitelist");
                if(!empty($result)){

                   $db->where('id', $value);
                   $result3 = $db->delete('sms_email_whitelist');
                }
              }
              return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00001"][$language], 'data' => $data); //Completed successfully.
            }
            else{
              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00002"][$language], 'data'=>""); //No record found.
            }

          }

           function editWhitelistIncomingEmail($params){
            $db = $this->db;
            $general = $this->general;

             //Language Translations.
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $value = $params["value"];
            $id = $params["id"];

            if($value == ""){
              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00149"][$language], 'data'=>""); //Please enter content.
            }

            $db->where("id", $id, "!=");
            $db->where("value", $value);
            $result = $db->get("sms_email_whitelist");
            if(empty($result)){

               $fields = array("value");
               $val    = array($value);
               $arrayData = array_combine($fields, $val);

               $db->where('id',$id);
               $result = $db->update('sms_email_whitelist',$arrayData);

               return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00001"][$language], 'data' => $data); //Completed successfully.
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00148"][$language], 'data'=>""); //Duplicate content found.
            }

          }

          function addWhitelistIncomingEmail($params){
            $db = $this->db;
            $general = $this->general;

             //Language Translations.
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $value = $params["value"];

            if($value == ""){
              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00149"][$language], 'data'=>""); //Please enter content.
            }

            $db->where("value", $value);
            $result = $db->get('sms_email_whitelist',null,"id");
            if(empty($result)){

              $fields = array("value", "created_at");
              $val    = array ($value, date("Y-m-d H:i:s"));

               $arrayData = array_combine($fields, $val);

               $addCustomRoute = $db->insert("sms_email_whitelist",$arrayData);


               return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00001"][$language], 'data' => $data, "debug"=> $debug); //Completed successfully.
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00148"][$language], 'data'=>""); //Duplicate content found.
            }

          }

        function getTicketItemAttachment($params){
            $db = $this->db;
            $general = $this->general;

             //Language Translations.
            $language     = $this->general->getCurrentLanguage();
            $translations = $this->general->getTranslations();

            $uploadID = $params["uploadID"];

            $db->where('id', $uploadID);
            $uploadRes = $db->getOne('uploads', 'data, file_name');

            $fileAttachment = $uploadRes['data'];
            $fileName       = $uploadRes['file_name'];

            $fileAttachment = base64_encode($fileAttachment);

            $data['fileName'] = $fileName;
            $data['fileAttachment'] = $fileAttachment;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00001"][$language], 'data' => $data); //Completed successfully.

        }

    }
?>