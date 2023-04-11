<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Message Assigned Related Database code.
     * Date  04/07/2017.
    **/

    //include('notifi.php');
    //use notifi;

    class Message {
        
        function __construct($db, $general, $provider)
        {
            $this->db = $db;
            $this->general = $general;
            $this->provider = $provider;
        }
        
        public function getAssignedMessages($processName, $limit)
        {
            $db = $this->db;
            
            $db->where('processor', $processName);
            $db->where('sent', 0);
            $db->where('error_count', 10, "<");
            $db->orderBy('priority', "DESC");
            $results = $db->get('message_out', $limit);
            
            return $results;
        }
        
        public function assignMessages($processName, $limit)
        {
            $db = $this->db;
            
            $db->where('scheduled_at', date('Y-m-d H:i:s'), '<=');
            $db->where('processor', "");
            $db->where('sent', 0);
            $db->where('error_count', 10, "<");
            $db->orderBy('priority', "DESC");
            
            $msgoutRes = $db->get('message_out', $limit, "id, sent_history_id, sent_history_table");
            
            if (count($msgoutRes) > 0)
            {
                
                foreach($msgoutRes as $msgoutRow)
                {
                    $msgoutIDArray[] = $msgoutRow['id'];

                    // Update sent_history table
                    if($msgoutRow['sent_history_id'] && $msgoutRow['sent_history_table']) {
                        $db->where('id', $msgoutRow['sent_history_id']);
                        $db->update($msgoutRow['sent_history_table'], array('processor' => $processName));
                    }
                }
                
                $data = array('processor' => $processName);
                $db->where('id', $msgoutIDArray, "IN");
                $db->update('message_out', $data);
            
            }
            
            return count($msgoutRes);
        }
        
        public function updateMessages($sent, $id ,$data, $errorData, $error, $sentID, $sentHistoryTable)
        {
            $db = $this->db;
            
            if($sent == 1)
            {   
                // update the sent history table
                
                $sentData['sent_at'] = $data['sent_at'];
                $sentData['respond'] = $data['respond'];
                
                $db->where('id', $sentID);
                $sentUpdateRes = $db->update($sentHistoryTable, $sentData);
                
                // delete from message out table
                if($sentUpdateRes) {
                    $this->deleteMessageOut($id);
                }
            }
            else
            {
                $error++;
                if ($errorData) $db->insert('message_error', $errorData);
                //update error status - message_out
                $data = array('error_count' => $error);
                $db->where('id', $id);
                $db->update('message_out', $data);
            }
        }
        
        /** ######### Message Assigned Starts ########## **/

        /**
         * Function for getting the Message Assigned List.
         * @param NULLL.
         * @author Rakesh.
        **/
        public function messageAssignedList($messageParams){
            $db = $this->db;
            $general = $this->general;
            
            $pageNumber = $messageParams['pageNumber'] ? $messageParams['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            $result = $db->rawQuery("SELECT id, code, recipient, type FROM message_assigned order by id desc LIMIT ". $db->escape($limit[0]).", ". $db->escape($limit[1])." ");

            if (!empty($result)) {
                $totalRecord = $db->getValue ("message_assigned", "count(id)");
                foreach($result as $value) {

                    $message['id']        = $value['id'];
                    $message['code']      = $value['code'];
                    $message['recipient'] = $value['recipient'];
                    $message['type']      = $value['type'];

                    $messageList[]        = $message;
                }
                
                $data['messageAssignedList'] = $messageList;
                $data['totalPage']           = ceil($totalRecord/$limit[1]);
                $data['pageNumber']          = $pageNumber;
                $data['totalRecord']         = $totalRecord;
                $data['numRecord']           = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for saving the New Message Assigned.
         * @param $messageAssignedParams.
         * @author Rakesh.
        **/
        public function newMessageAssigned($messageAssignedParams) {
            $db = $this->db;

            $code        = $messageAssignedParams['messageCode'];
            $recipient   = $messageAssignedParams['messageRecipient'];
            $type        = $messageAssignedParams['messageType'];

            if(empty($code) || !isset($code)) {
                $errObj->data->result = "messageCode";
                $errMsg = json_encode($errObj);
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please select message code", 'data'=>$errMsg);
            }

            if(empty($recipient) || !isset($recipient)) {
                $recipientObj->data->result = "messageRecipient";
                $recipientMsg = json_encode($recipientObj);
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please select message recipient", 'data'=> $recipientMsg);
            }

            if(empty($type) || !isset($type)) {
                $typeObj->data->result = "messageType";
                $typeMsg = json_encode($typeObj);
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please select message type", 'data'=>$typeMsg);
            }

            $checkDuplicate = $db->rawQuery("SELECT * FROM message_assigned WHERE code ='". $code. "' AND recipient ='". $db->escape($recipient). "' AND type = '". $db->escape($type)."' ");

            if(!empty($checkDuplicate)) {
                $duplicateObj->data->result = "duplicateData";
                $duplicateJson = json_encode($duplicateObj);
                return array('status' => "ok", 'code' => 1, 'statusMsg' => 'Duplidate Data', 'data' => $duplicateJson);
            }else if(empty($checkDuplicate)){
                $dbFields    = array('code', 'recipient', 'type', 'created_at');
                $dbValues    = array($code, $recipient, $type, date("Y-m-d H:i:s"));
                $messageAssignedData   = array_combine($dbFields, $dbValues);

                try{
                    $messageAssignedParam = $db->insert("message_assigned", $messageAssignedData);
                } catch (Exception $e) {
                    $result =  $e->getMessage();
                }
                if (is_numeric($messageAssignedParam)) {
                    $result = true;
                }else {
                    $result = false;
                }

                $message['result'] = $messageAssignedParam;
                $message['saved']  = "saved";
                
                $data['messageSave'] = $message;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
        }

        /**
         * Function for Delete the Message Assigned record.
         * @param $messageAssignedId.
         * @author Rakesh.
        **/
        public function deleteMessageAssigned($messageAssignedId){
            $db = $this->db;
            
            $id = trim($messageAssignedId['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Message Assigned.", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->get('message_assigned', 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('message_assigned');
                if($result) {
                    return $this->messageAssignedList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete.', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Message Assigned.", 'data'=>"");
            }
        }

        /**
         * Function for the Message Assigned.
         * @param $params.
         * @author Rakesh.
        **/
        public function editMessageAssigned($params) {
            $db = $this->db;
            
            $code        = $params['code'];
            $recipient   = $db->escape($params['recipient']);
            $type        = $db->escape($params['type']);

            if(empty($code) || !isset($code)) {
                $errObj->data->result = "messageCode";
                $errMsg = json_encode($errObj);
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please select message code", 'data'=>$errMsg);
            }

            if(empty($recipient) || !isset($recipient)) {
                $recipientObj->data->result = "messageRecipient";
                $recipientMsg = json_encode($recipientObj);
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please select message recipient", 'data'=> $recipientMsg);
            }

            if(empty($type) || !isset($type)) {
                $typeObj->data->result = "messageType";
                $typeMsg = json_encode($typeObj);
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please select message type", 'data'=>$typeMsg);
            }

            $checkDuplicate = $db->rawQuery("SELECT * FROM message_assigned WHERE code ='". $code. "' AND id !='". $db->escape($params['id']). "' AND recipient ='". $recipient. "' AND type = '". $type."' ");

            if(!empty($checkDuplicate)) {
                $message['result'] = $checkDuplicate;
                $data['messageEdit'] = $message;
                
                return array('status' => "ok", 'code' => 1, 'statusMsg' => 'Duplidate Data', 'data' => $data);
            }else if(empty($checkDuplicate)){ 
                $dbFields = array('code', 'recipient', 'type', 'updated_at');
                $dbValues = array($code, $recipient, $type, date("Y-m-d H:i:s"));
                $messageAssignedData = array_combine($dbFields, $dbValues);

                $db->where ('id', $params['id']);
                $result = $db->update ('message_assigned', $messageAssignedData);
            }

            if($result) {
                $message['result']   = $result;
                $message['message']  = "updated";
                $data['messageEdit'] = $message;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
            } else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            }
        }

        /**
         * Function for the load the message Assigned data in Edit.
         * @param $messageAssignedId.
         * @author Rakesh.
        **/
        public function getEditMessageAssignedData($messageAssignedId) {
            $db = $this->db;
            
            $db->where ("id", $messageAssignedId['id']);
            $result = $db->getOne("message_assigned");

            if (!empty($result)) {
//                $message['id']        = $result['id'];
                $message['code']      = $result['code'];
                $message['recipient'] = $result['recipient'];
                $message['type']      = $result['type'];
//                $message['created']   = $result['created_at'];
                
                $data['messageAssignedData'] = $message;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }else{
                return array('status' => "error", 'code' => '1', 'statusMsg' => "No Result", 'data'=>$messageAssignedId['id']);
            }
        }
        /** ######### Message Assigned End ########## **/

        /** ######### Message Codes Starts (class.messagecodes.php) ########## **/

        /**
         * Function for the getting the Message Code.
         * @param NULL.
         * @author Rakesh.
         * @return Array.
        **/
        public function getMessageCode() {
            $db = $this->db;
            
            $messageCode = $db->rawQuery('SELECT code, title FROM message_code order by code asc');

            if(!empty($messageCode)) {
                foreach($messageCode as $value) {
                    $msgDesc[] = $value['code']." - ".$value['title'];
                    $msgCode[] = $value['code'];

                    // $message['messageDesc'] = $value['code']." - ".$value['title'];
                    // $message['messageCode'] = $value['code'];

                    // $messageList[] = $message;
                }

                $message['messageDesc'] = $msgDesc;
                $message['messageCode'] = $msgCode;

                $data['messageData'] = $message;
                // $data['messageData'] = $messageList;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for the seacrching the Message Code.
         * @param NULL.
         * @author Rakesh.
         * @return Array.
        **/
        public function getMessageSearchData($params) {
            $db = $this->db;
            $general = $this->general;
            
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            $searchData = $params['searchData'];

            //Search matching params.
            $searchData = $params['searchData'];

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    
                    switch($dataName) {
                            
                        case 'code':
                            $db->where('code', $dataValue);
                            
                            break;
                            
                        case 'recipient':
                            $db->where('recipient', $dataValue);
                            
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");
            $result      = $db->get('message_assigned', $limit);
            $timeStamp   = [];
            $description = [];
            $patchBy     = [];
            $patchOn     = [];
            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("message_assigned", "count(id)");
                foreach($result as $value) {
                    $id[]        = $value['id'];
                    $code[]      = $value['code'];
                    $recipient[] = $value['recipient'];
                    $type[]      = $value['type'];
                }
                $message['ID']        = $id;
                $message['Code']      = $code;
                $message['Recipient'] = $recipient;
                $message['Type']      = $type;
                
                $data['messageAssignedList'] = $message;
                $data['totalPage']           = ceil($totalRecord/$limit[1]);
                $data['pageNumber']          = $pageNumber;
                $data['totalRecord']         = $totalRecord;
                $data['numRecord']           = $limit[1];
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for the getting the Message Code.
         * @param messageCode.
         * @author Aman.
         * @return Array.
         * Added 2 parameters $content and $subject on 21/08/2017 - Rakesh.
        **/
        public function createMessageOut($messageCode, $content = NULL, $subject = NULL, $find = array(), $replace = array(), $scheduledAt = '', $priority = 1)
        {
            
            $db = $this->db;
            $provider = $this->provider;
            
            if(!$messageCode) return false;
            if(!$scheduledAt)
                $scheduledAt = date('Y-m-d H:i:s');

            $db->where('code', $messageCode);
            $msgAssignedResult = $db->get("message_assigned", null, "recipient, type");

            $db->where('code', $messageCode);
            $msgCodeResult = $db->getOne("message_code", "title AS subject, content");
            
            // Get provider details for mapping purpose
            $providerArray = $provider->getProvider();
            
            if ($msgCodeResult)
            {
                $date = date('Ymd');
                $sentHistoryTable = 'sent_history_'.$date;
                
                $check = $db->tableExists($sentHistoryTable);
                if(!$check) {
                    $db->rawQuery('CREATE TABLE IF NOT EXISTS '.$sentHistoryTable.' LIKE sent_history');
                }

                foreach ($msgAssignedResult as $key => $val)
                {
                    $insertData['recipient'] = $val['recipient'];
                    $insertData['type'] = $val['type'];
                    
                    if (isset($subject) && !empty($subject))
                    {
                        $insertData["subject"] = $subject;
                    }
                    else
                    {
                        $insertData["subject"] = $msgCodeResult["subject"];
                    }
                    
                    if(!empty($content) && isset($content))
                    {
                        $insertData["content"] = $content;
                    }
                    else
                    {
                        if (count($find) > 0 && count($replace) > 0)
                        {
                            // Use find and replace to replace contents
                            $insertData["content"] = str_replace($find, $replace, $msgCodeResult["content"]);
                            
                        }
                        else
                        {
                            $insertData["content"] = $msgCodeResult["content"];
                        }
                    }
                    // Map to get the provider_id
                    $insertData["provider_id"] = $providerArray[$val["type"]]["id"];
                    $insertData["created_at"] = date("Y-m-d H:i:s");
                    $insertData['scheduled_at'] = $scheduledAt;
                    
                    $sentID = $db->insert($sentHistoryTable, $insertData);
                    if(!$sentID)
                        return false;
                    
                    // Set the priority to 1
                    $insertData["priority"] = $priority;
                    
                    // Set the data for message_out table
                    $insertData["sent_history_id"] = $sentID;
                    $insertData["sent_history_table"] = $sentHistoryTable;

                    $msgID = $db->insert('message_out', $insertData);
                    if(!$msgID)
                        return false;

                    unset($insertData);
                }
            }
            else
            {
                return false;
            }

            return true;
        }

        public function createCustomizeMessageOut($recipient, $subject, $content, $type, $providerID='', $scheduledAt = '', $priority = 1, $referenceID = 0) {
            $db         = $this->db;
            $provider   = $this->provider;

            if(!$recipient) return false;
            if(!$subject) return false;
            if(!$content) return false;
            if(!$type) return false;
            if(!$scheduledAt)
                $scheduledAt = date('Y-m-d H:i:s');
            
            $date = date('Ymd');
            $sentHistoryTable = 'sent_history_'.$date;

            $check = $db->tableExists($sentHistoryTable);
            if(!$check) {
                $db->rawQuery("CREATE TABLE IF NOT EXISTS ".$sentHistoryTable." LIKE sent_history");
            }

            $providerID = trim($providerID);
            if(strlen($providerID) == 0) {
                $providerID = $provider->getProviderIDByName($type);
                if(!$providerID)
                    return false;
            }
            
            $msgOut['recipient'] = $recipient;
            $msgOut['subject'] = $subject;
            $msgOut['content'] = htmlentities($content);
            $msgOut['type'] = $type;
            $msgOut['provider_id'] = $providerID;
            $msgOut['scheduled_at'] = date("Y-m-d H:i:s");
            $msgOut["created_at"] = date("Y-m-d H:i:s");

            $sentID = $db->insert($sentHistoryTable, $msgOut);
            if(!$sentID)
                return false;
            
            $msgOut['priority'] = $priority;
            
            // Set the data for message_out table
            $msgOut["sent_history_id"] = $sentID;
            $msgOut["sent_history_table"] = $sentHistoryTable;
            $msgOut["reference_id"] = $referenceID;
            
            $msgOutID = $db->insert('message_out', $msgOut);
            
            $output = array(
                                "msgOutID" => $msgOutID,
                                "sentHistoryID" => $sentID,
                                "sentHistoryTable" => $sentHistoryTable
                            );

            return $output;
        }

        public function deleteMessageOut($id){
            
            $db = $this->db;
            
            if(!$id) return false;
            if(strlen($id) == 0) return false;
                
            $db->where('id', $id);
            $result = $db->delete('message_out');
            if($result)
                return true;

            return false;
        }

        public function messageErrorSender($result) {
            global $db, $mail, $notifications, $provider;

            $to         = $result['to'];
            $text       = $result['text'];
            $subject    = $result['subject'];

            $providerArray = $provider->getProvider();

            if($result['type']=='email') {  
                //send the email and check for the errors
                $response = $notifications->sendEmails($to,$subject,$text,$providerArray['email']);
                
               return $response;
                // if ($response) 
                // {
                // return $reponse;//echo "email sent!";
                // } 
            } elseif($result['type']=='sms') {
                //send the sms and  check for errors
                $response = $notifications->sendSMS($to, $text,$providerArray['sms']); 

                return $response;
                // if (strpos($response, '1609') !== false) 
                // {//strpos($response, '1609') === false
                //     return $response;//echo "sms sent";
                // } 
            } elseif($result['type']=='xun') {
                $xunNumber = array();
                array_push($xunNumber, $to) ; 

                $response  = $notifications->sendXun($xunNumber, $text, $department = null,$providerArray['xun']);

                return $response;    
                // if (strpos($response, 'ok') !== false) 
                // {
                //     return $response;//echo "xun sent message!";
                // }
            }
        }

        /**
         * Function for saving the New Message Assigned.
         * @param $messageAssignedParams.
         * @author Aman.
        **/
        public function sendMessage($messageSendingParams) {
            $db = $this->db;

            $code        = $messageSendingParams['messageCode'];
            $recipient   = $messageSendingParams['messageRecipient'];
            $type        = $messageSendingParams['messageType'];

            $sender = array('to' => $recipient, 'type' => $type, 'subject' => $code ,'text' => $code );

            $response = $this->messageErrorSender($sender);

            $message['response']      = $response;
            $message['Recipient'] = $recipient;
            $message['Type']      = $type;
            $message['formData']      = $messageSendingParams;
            
            $data['messageSend'] = $message;
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);

            // $checkDuplicate = $db->rawQuery("SELECT * FROM message_assigned WHERE code ='". $code. "' AND recipient ='". $recipient. "' AND type = '". $type."' ");
        }

        /**
         * Function for getting the message Code List.
         * @param NULLL.
         * @author Aman.
        **/
        public function getMessageCodes($permissionParams) {
            $db = $this->db;
            $general = $this->general;
            
            $pageNumber = $permissionParams['pageNumber'] ? $permissionParams['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            $searchData = $permissionParams['searchData'];;

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    
                    switch($dataName) {
                            
                        case 'code':
                            $db->where('code', $dataValue);
                            
                            break;
                            
                        case 'title':
                            $db->where('title', $dataValue);
                            
                            break;

                        case 'module':
                            $db->where('module', $dataValue);
                            
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");
            $result = $db->get("message_code", $limit, 'id, code, title, content, description, module');

            if (!empty($result)) {
                foreach($result as $array) {
                    foreach ($array as $key => $value) {
                        $message[$key] = $value;
                    }
                    $messageList[] = $message;
                }

                $totalRecord = $copyDb->getValue("message_code", "count(id)");
                $data['messageCodeList'] = $messageList;
                $data['totalPage']       = ceil($totalRecord/$limit[1]);
                $data['pageNumber']      = $pageNumber;
                $data['totalRecord']     = $totalRecord;
                $data['numRecord']       = $limit[1];
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for saving Message Codes data.
         * @param $params.
         * @author Aman.
        **/
        public function saveMessageCodeData($params) {
            $db = $this->db;
            
            $code        = $params['code'];
            $description = $params['description'];
            $content     = $params['content'];
            $title       = $params['title'];
            $module      = $params['module'];

            //return $params;
            if(strlen($code) == 0) 
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Message Code", 'data'=>"codeError");

            if(strlen($description) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter Description", 'data'=>"descriptionError");

            if(strlen($title) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter title", 'data'=>"");

            if(strlen($content) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter content", 'data'=>"");

            if(strlen($module) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter module", 'data'=>"");

            $fields    = array("code", "content", "description", "title", "module");
            $values    = array($code, $content, $description, $title, $module);
            $arrayData = array_combine($fields, $values);

            $result = $db->insert("message_code", $arrayData);

            if($result)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Success', 'data' => "");
            else
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed', 'data' => "");
        }

        /**
         * Function for Delete the Message Code record.
         * @param $messageCodeId.
         * @author Aman.
        **/
        public function deleteMessageCode($messageCodeId) {
            $db = $this->db;
            
            $id = trim($messageCodeId['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Message Code.", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->get('message_code', 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('message_code');
                if($result) {
                    return $this->getMessageCodes();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete.', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Message Assigned.", 'data'=>"");
            }
        }

        /**
         * Function for the Edit MessageCode.
         * @param $params.
         * @author Aman.
        **/
        public function editMessageCode($params) {
            $db = $this->db;

            $code        = $params['code'];
            $description = $params['description'];
            $title       = $params['title'];
            $content     = $params['content'];
            $module      = $params['module'];
           
            if(strlen($code) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter messagecode", 'data'=>"");
            
            if(strlen($description) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter message code Description", 'data'=>"");

            if(strlen($title) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter title", 'data'=>"");

            if(strlen($content) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter content", 'data'=>"");

            if(strlen($module) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Enter module", 'data'=>"");

            $fields    = array("code", "content", "description", "title", "module");
            $values    = array($code, $content, $description, $title, $module);
            
            $arrayData = array_combine($fields, $values);

            $db->where ('id', $params['id']);
            $result = $db->update('message_code', $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "Message Code successfully updated.", 'data' => "");
            } else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            }
        }

        /**
         * Function for the load the Api data in Edit.
         * @param $params.
         * @author Aman.
        **/
        public function getEditMessageCodeData($params) {
            $db = $this->db;
            
            $db->where ("id", $params['id']);
            $result = $db->get("message_code", 1, 'code, title, content, description, module');

            if (!empty($result)) {
                foreach($result as $array) {
                    foreach($array as $key => $value) {
                        $messageCode[$key] = $value;
                    }
                    $messageCodeList[] = $messageCode;
                }
                
                $data['messageCodeData'] = $messageCodeList;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }
       /** ######### Message Codes End. (class.messagecodes.php) ########## **/

        /** ######### Message Codes Starts. (class.messageerror.php) ########## **/

        /**
         * Function for getting the message Code List.
         * @param NULLL.
         * @author Aman.
        **/
        public function getMessageErrorList($errorParam) {
            $db = $this->db;
            $general = $this->general;
            
            $pageNumber = $errorParam['pageNumber'] ? $errorParam['pageNumber'] : 1;
            //Get the limit.
            $limit = $general->getLimit($pageNumber);
            
            $searchData = $errorParam['searchData'];

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    
                    switch($dataName) {

                        case 'errorCode':
                            $db->where('error_code', $dataValue);
                            
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            
            $getContent = '(SELECT o.content FROM message_out o WHERE e.message_id = o.id) as content';
            
            $db->orderBy("id", "DESC");
            $copyDb = $db->copy();
            $result = $db->get('message_error e', $limit, 'e.id, '.$getContent.', e.processor, e.error_code, e.error_description');

            if (!empty($result)) {
                foreach($result as $value) {
                    $message['id']                = $value['id'];
                    $message['content']           = $value['content'];
                    $message['processor']         = $value['processor'];
                    $message['errorCode']         = $value['error_code'];
                    $message['errorDescription']  = $value['error_description'];

                    $messageList[] = $message;
                }
                
                $totalRecord = $copyDb->getValue("message_error", "count(id)");
                $data['messageErrorList'] = $messageList;
                $data['totalPage']  = ceil($totalRecord/$limit[1]);
                $data['pageNumber'] = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord'] = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for getting the message Error Codes.
         * @param $messageOutParams
         * @author Aman.
        **/
        public function getErrorCode () {
            $db = $this->db;
            
            $errorCodes = $db->rawQuery('SELECT id, error_code FROM message_error order by id asc');
            if (!empty($errorCodes)) {
                foreach($errorCodes as $value) {
                    $message['errorList'] = $value['error_code'];

                    $messageList[] = $message;
                }

                // remove duplicate command. Then sort it alphabetically
                $uniqueMessageList = array_intersect_key($messageList, array_unique(array_map('serialize', $messageList)));
                sort($uniqueMessageList);
                
                $data['errorCode'] = $uniqueMessageList;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }
        /** ######### Message Error End. (class.messageerror.php) ########## **/

        /** ######### Message Out Starts. (class.messageout.php) ########## **/

        /**
         * Function for getting the message Code List.
         * @param $messageOutParams
         * @author Aman.
        **/
        public function getMessageSentList($params) {
            $db = $this->db;
            $general = $this->general;
            
            $offsetSecs = trim($params['offsetSecs']);
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            
            // Set default to get from today
            $messageDate = date("Ymd");
            
            $searchData = $params['searchData'];

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    
                    switch($dataName) {
                        case 'tblDate':
                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify a date", 'data'=>"");
                            
                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                            
                            $messageDate = date('Ymd', $dataValue);
                    
                            break;
                            
                        case 'tblTime':
                            // Set db column here
                            $columnName = 'created_at';
                            
                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify a date", 'data'=>"");
                                
                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                            
                            $dataValue = date('Y-m-d', $dataValue);
                            
                            $dateFrom = trim($v['timeFrom']);
                            $dateTo = trim($v['timeTo']);
                            if(strlen($dateFrom) > 0) {
                                $dateFrom = strtotime($dataValue.' '.$dateFrom);
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                                
                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                $dateTo = strtotime($dataValue.' '.$dateTo);
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                                
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Time from cannot be later than time to", 'data'=>$data);
                                $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                            }
                            
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                            
                        case 'recipient':
                            $db->where('recipient', $dataValue);
                            
                            break;
                            
                        case 'type':
                            $db->where('type', $dataValue);
                            
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->orderBy("sent_at", "desc");
            $copyDb = $db->copy();
            $result = $db->get('sent_history_'.$messageDate, $limit, "id, recipient, type, content, subject, sent_at as sentAt, scheduled_at as scheduledAt, processor, error_count as errorCount");
            
            if(empty($result)) $statusMsg = "No Results Found";

            foreach($result as $array) {
                $hiddenData['Content'] = $array['content'];

                foreach ($array as $keys => $values) {
                    if($keys != "sentAt" && $keys != 'scheduledAt') {
                        $msgSent[$keys] = $values;
                    }
                    else if($keys == 'sentAt') {
                        $msgSent[$keys] = $general->formatDateTimeToString($array['sentAt'], "d/m/Y h:i:s A");
                        unset($array['sentAt']);
                    } else if ($keys == "scheduledAt") {
                        $msgSent[$keys] = $general->formatDateTimeToString($array['scheduledAt'], "d/m/Y h:i:s A");
                        unset($array['scheduledAt']);
                    }
                }
                $hiddenDataList[] = $hiddenData;
                $msgSentList[] = $msgSent;
            }

            $totalRecord = $copyDb->getValue('sent_history_'.$messageDate, "count(id)");

            $data['msgSentList'] = $msgSentList;
            $data['hiddenData'] = $hiddenDataList;
            $data['totalPage'] = ceil($totalRecord/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord'] = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $statusMsg, 'data' => $data);
           
        }

        public function getMessageQueueList($params) {
            $db = $this->db;
            $general = $this->general;

            $offsetSecs = trim($params['offsetSecs']);
            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $statusMsg = "";

            //Get the limit.
            $limit = $general->getLimit($pageNumber);

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'recipient':
                            $db->where('recipient', $dataValue);
                            break;

                        case 'type':
                            $db->where('type', $dataValue);
                            break;

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Date from cannot be later than date to.', 'data'=>$data);

                                if($dateTo == $dateFrom)
                                    $dateTo += 86399;
                                $db->where('created_at', date('Y-m-d H:i:s', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            
            $copyDb = $db->copy();
            $db->orderBy("created_at", "DESC");
            $db->orderBy("id", "DESC");
            
            $result = $db->get('message_out', $limit, "id as ID, recipient, type, content, subject, created_at as createdAt, scheduled_at as scheduledAt, processor, priority, error_count as errorCount");

            if($db->count > 0) {
                foreach($result as $array) {
                    $hiddenData['Content'] = $array['content'];

                    foreach ($array as $key => $value) {
                        if ($key == 'createdAt') {
                            $createdAt = $general->formatDateTimeToString($array['createdAt'], "d/m/Y h:i:s");
                            $msgQueue[$key] = $createdAt?$createdAt:'-';
                        } 
                        if ($key == 'scheduledAt') {
                            $scheduledAt = $general->formatDateTimeToString($array['scheduledAt'], "d/m/Y h:i:s");
                            $msgQueue[$key] = $scheduledAt?$scheduledAt:'-';
                        }

                        if($key != "createdAt" && $key != 'scheduledAt') {
                            $msgQueue[$key] = $value;
                        }
                    }

                    $hiddenDataList[] = $hiddenData;
                    $msgQueueList[] = $msgQueue;
                }
                $totalRecord = $copyDb->getValue("message_out", "count(id)");

                $data['msgQueueList'] = $msgQueueList;
                $data['hiddenData'] = $hiddenDataList;
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['pageNumber'] = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord'] = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data); 
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "No result found.", 'data' => "");
        }
        /** ######### Message Out End. (class.messageout.php) ########## **/

        /** ######### Message IN. ############### **/
        public function getMessageInList($params) {
            $db = $this->db;
            $general = $this->general;
            
            $offsetSecs = trim($params['offsetSecs']);
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            //Get the limit.
            $limit = $general->getLimit($pageNumber);
            
            // Set default to get from today
            $messageDate = date("Ymd");
            
            $searchData = $params['searchData'];

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    
                    switch($dataName) {
                        case 'tblDate':
                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify a date", 'data'=>"");
                            
                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                            
                            $messageDate = date('Ymd', $dataValue);
                    
                            break;
                            
                        case 'tblTime':
                            // Set db column here
                            $columnName = 'created_at';
                            
                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify a date", 'data'=>"");
                                
                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                            
                            $dataValue = date('Y-m-d', $dataValue);
                            
                            $dateFrom = trim($v['timeFrom']);
                            $dateTo = trim($v['timeTo']);
                            if(strlen($dateFrom) > 0) {
                                $dateFrom = strtotime($dataValue.' '.$dateFrom);
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                                
                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                $dateTo = strtotime($dataValue.' '.$dateTo);
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                                
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Time from cannot be later than time to", 'data'=>$data);
                                $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                            }
                            
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                            
                        case 'sender':
                            $db->where('sender', $dataValue);
                            
                            break;
                            
                        case 'providerName':
                            $providerID = $db->subQuery();
                            $providerID->where("name", $dataValue);
                            $providerID->getValue("provider", "id", null);

                            $db->where("provider_id", $providerID, "IN");
                            
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            $getProviderName = "(SELECT name FROM provider WHERE provider.id=provider_id) AS providerName";
            $db->orderBy("created_at, id", "DESC");
            $results = $db->get("message_in", $limit, "id, sender, content, ".$getProviderName.", created_at as createdAt, processed");

            if(empty($results))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data' => "");

            foreach($results as $result) {
                $hiddenData["Content"][]    = $result["content"];

                $result["processed"] = ($result["processed"] == 0) ? "No" : "Yes";
                $messageData[] = $result;
                // unset($result["processed"]);
            }

            $totalRecord = $copyDb->getValue ("message_in", "count(id)");
            
            $data['messageData'] = $messageData;
            $data['hiddenData']  = $hiddenData;
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord'] = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
    }

?>
