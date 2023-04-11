<?php 

	class Webservice {
        
        function __construct($db, $general, $message)
        {
            $this->db = $db;
            $this->general = $general;
            $this->message = $message;
        }

		function insertWebserviceData($msgpackData, $tblDate, $createTime, $command) {
            $db = $this->db;
            
			if(!trim($tblDate)) {
				$tblDate = date("Ymd");
			}

			$result = $db->rawQuery("CREATE TABLE IF NOT EXISTS web_services_".$db->escape($tblDate)." LIKE web_services");

			// Insert a new record into webservice table
            $ip = $msgpackData['ip'];
            
			$command = $msgpackData['command'] ? $msgpackData['command'] : $command;
            if(($command == 'superAdminLogin') || ($command == 'adminLogin') || ($command == 'memberLogin'))
                $client_username = $msgpackData['params']['username']; 
            else
                $client_username = $msgpackData['username'];
            
            if($msgpackData['userID'] == '')
                $userID = 0;
            else
                $userID = $msgpackData['userID'];

            $base64_content_api_list = array("businessimportcustomersales");
            if(in_array($command, $base64_content_api_list) && isset($msgpackData["attachment_data"])) $msgpackData["attachment_data"] = "Base64_data";

            $dataString = '';
            $this->traverseArray($msgpackData, $dataString, $level = 0);
            $dataInJson = json_encode($msgpackData);

            $data_in = $dataString;
            
            $source = $msgpackData['source'];
			$source_version = $msgpackData['sourceVersion'];
            $userAgent = $msgpackData['userAgent'];
			$type =  $msgpackData['type'];
            $site =  $msgpackData['site'];
            
            $ip = $db->escape($ip);
            $userID = $db->escape($userID);
            $client_username = $db->escape($client_username);
            $command = $db->escape($command);
            $source = $db->escape($source);
            $source_version = $db->escape($source_version);
            $type = $db->escape($type);
            $createTime = $db->escape($createTime);
            
            $db->rawQuery("INSERT INTO web_services_".$tblDate." (`client_id`, `client_username`, `command`, `data_in`, `source`, `source_version`, `type`, `site`, `ip`, `user_agent`, `created_at`) VALUES ('".$userID."', '".$client_username."', '".$command."', '".$data_in."', '".$source."', '".$source_version."', '".$type."', '".$site."', '".$ip."', '".$userAgent."', '".$createTime."');");
            
			// Get inserted id
			$res = $db->rawQuery("SELECT last_insert_id()");
            $webserviceid = $res[0]['last_insert_id()'];


            $this->developerWebServiceDataIn($webserviceid, $tblDate, $command, $dataInJson, $ip, $createTime);

            return $webserviceid;
		}
        
        function updateWebserviceData($webserviceID, $dataOut, $status, $completeTime, $processedTime, $tblDate, $queryNumber) {
			$db = $this->db;
            
			if(!trim($tblDate)) {
				$tblDate = date("Ymd");
			}
            
            if (!$db->tableExists ('web_services_'.$tblDate))
                $db->rawQuery("CREATE TABLE IF NOT EXISTS web_services_".$db->escape($tblDate)." LIKE web_services");

            $dataString = '';
            $this->traverseArray($dataOut, $dataString, $level = 0);
            $dataOutJson = json_encode($dataOut);

            $dataOut = $dataString;
            $status = $db->escape($status);
            $completeTime = $db->escape($completeTime);
            $processedTime = $db->escape($processedTime);
            $queryNumber = $db->escape($queryNumber);
            
            $db->rawQuery("UPDATE web_services_".$tblDate." SET `data_out`='".$dataOut."', `status`='".$status."', `completed_at`='".$completeTime."', `duration`='".$processedTime."', `no_of_queries`='".$queryNumber."' WHERE `id`=".$webserviceID);

            $this->developerWebServiceDataOut($webserviceID, $tblDate, $dataOutJson, $completeTime);

		}
        
        function getWebservices($params, $paramsSite){
            $db = $this->db;
            $general = $this->general;
            
            $offsetSecs = trim($params['offsetSecs']);
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            
            // Set default to get from today
            $webserviceDate = date("Ymd");
            
            $searchData = $params['searchData'];

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    
                    switch($dataName) {
                        case 'webserviceDate':
                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please specify a date", 'data'=>"");
                            
                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid date", 'data'=>"");
                            
                            $webserviceDate = date('Ymd', $dataValue);
                    
                            break;
                            
                        case 'webserviceTime':
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
                            
                        case 'clientUsername':
                            $db->where('client_username', $dataValue);
                            
                            break;
                            
                        case 'command':
                            $db->where('command', $dataValue);
                            
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($paramsSite == 'Admin')
                $db->where('site', 'SuperAdmin', '!=');

            //Get the limit.
            $limit = $general->getLimit($pageNumber);
            
            $db->orderBy("id", "DESC");
            $copyDb = $db->copy();
            
            try{
                $result = $db->get('web_services_'.$webserviceDate, $limit);
            }
            catch (Exception $e) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
            
            if (!empty($result)) {
                foreach($result as $value) {

                    $webservice['id']             = $value['id'];
                    $webservice['createdAt']      = $general->formatDateTimeToString($value['created_at'], "d/m/Y h:i:s A");
                    $webservice['completedAt']    = $general->formatDateTimeToString($value['completed_at'], "d/m/Y h:i:s A");
                    $webservice['clientUsername'] = $value['client_username'];
                    $webservice['command']        = $value['command'];
                    $wsData['dataIn']             = nl2br(str_replace(' ', '&nbsp;', $value['data_in']));
                    $wsData['dataOut']            = nl2br(str_replace(' ', '&nbsp;', $value['data_out']));
                    $wsData['userAgent']          = $value['user_agent'];
                    $webservice['dataIn']         = '';
                    $webservice['dataOut']        = '';
                    $webservice['source']         = $value['source'];
                    $webservice['sourceVersion']  = $value['source_version'];
                    $webservice['userAgent']      = '';
                    $webservice['type']           = $value['type'];
                    $webservice['site']           = $value['site'];
                    $webservice['status']         = $value['status'];
                    $webservice['duration']       = $value['duration'];
                    $webservice['ip']             = $value['ip'];
                    $webservice['noOfQueries']    = $value['no_of_queries'];

                    $webserviceList[] = $webservice;
                    $wsDataList[]     = $wsData;
                }
                
                // This is to get the commands for the search select option
                $result = $db->get('web_services_'.$webserviceDate, $limit, 'command');
                if(empty($result))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to get commands for search option', 'data' => '');
                
                foreach($result as $value) {
                    // $searchBarData[] = $value['command'];
                    $searchBarData['command'] = $value['command'];

                    $searchBarDataList[] = $searchBarData;
                }
                $totalRecord = $copyDb->getValue ('web_services_'.$webserviceDate, "count(id)");

                // remove duplicate command. Then sort it alphabetically
                $searchBarDataList = array_map("unserialize", array_unique(array_map("serialize", $searchBarDataList)));
                sort($searchBarDataList);
                
                $data['webserviceList'] = $webserviceList;
                $data['commandList']    = $searchBarDataList;
                $data['hiddenData']     = $wsDataList;
                $data['totalPage']      = ceil($totalRecord/$limit[1]);
                $data['pageNumber']     = $pageNumber;
                $data['totalRecord']    = $totalRecord;
                $data['numRecord']      = $limit[1];
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>'');
            }
        }

        /**
         *
         * Function for displaying the Daily API Summary.
         * @param NULL.
         * return Array.
        **/
		public function generateWebServiceSummary() {
            $db = $this->db;
            $message = $this->message;
            
            $timeStart = time();

            $subject   = "Webservice Symmary.";
            $day       = strtotime("yesterday");
            $tablename = "web_services_".date("Ymd", $day);

            $sqlTblExist = "SHOW TABLES LIKE '".$tablename."';";
            $sql         = "SELECT command, min(duration) as mintime, max(duration) as maxtime, avg(duration) as avgtime, count(*) as count FROM ".$tablename." group by command;";

            $sys['projectName'] = "T2II ";
            $rowCount           = 0;
            $num                = 0;
            $indent             = "&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp";
            $summaryText        = $sys['projectName']." Summary ".date("Y-m-d", $day)."<br>";

            $resTblExist        = $db->rawQuery ($sqlTblExist);

            if (count($resTblExist)>0){//check if table exist
                $results = $db->rawQuery ($sql);

                if (is_array($results) && !empty($results)) {//check if record exist
                    foreach ($results as $result) {
                        $colCount=0;
                        $summaryText .= ($num+1).". ".$result['command']." (".$result['count'].")<br>";
                        $colCount++;
                        $summaryText .= $indent."==> Min : ".$result['mintime']."<br>";
                        $colCount++;
                        $summaryText .= $indent."==> Max : ".$result['maxtime']."<br>";
                        $colCount++;
                        $summaryText .= $indent."==> Avg : ".$result['avgtime']."<br><br>";
                        $num++;
                        $rowCount++;
                    }
                }
                $message->createMessageOut('90003', $summaryText, $subject);//Send notification if Invalid Command.
                echo $summaryText."";
            } else {
                echo "Previous day backup table not found. Please check!<br>";
            }
            echo "timetaken : ".(time() - $timeStart);
        }
        
        function traverseArray($array, &$dataString, $level) {
            $db = $this->db;
            foreach($array as $key => $val) {
                $block = str_repeat("  ", $level);
                if(is_array($val)) {
                    $key = $db->escape($key);
                    $dataString = $dataString.$block.$key." :\n";
                    $this->traverseArray($val, $dataString, $level + 1);
                } else {
                    if (strcmp($key, 'password') == 0) {
                        //Store the password as speacial characters.
                        $outputPassword = str_repeat ('*', strlen ($val));
                        $dataString = $dataString.$block.$key." : ".$outputPassword."\n";
                    } else {
                        $key = $db->escape($key);
                        $val = $db->escape($val);
                        $dataString = $dataString.$block.$key." : ".$val."\n";
                    }
                }
            }
        }

        function developerWebServiceDataIn($webserviceID, $tblDate, $command, $data_in, $ip, $createTime) {

            $db = $this->db;

            $developer_log_command = array("payment_gatewaymerchanttransactionrequest", "payment_gatewaybuysellpaymentrequest", "cryptogeneratenewaddress", "cryptowalletbalanceget", "cryptoexternaltransferbybatch");

            if(in_array(strtolower($command), $developer_log_command) ) {

                // if(strtolower($command)=="payment_gatewaymerchanttransactionrequest") {
                $arrDataIn = json_decode($data_in, true);
                $userId = $arrDataIn['account_id'];
                if(!$userId) {
                    $userId = $arrDataIn['business_id'];
                }
                // } else if(strtolower($command)=="payment_gatewaybuysellpaymentrequest") {
                //     $arrDataIn = json_decode($data_in, true);
                //     $userId = $arrDataIn['account_id'];
                // } else {
                //     $arrDataIn = json_decode($data_in, true);
                //     $userId = $arrDataIn['business_id'];
                // }

                if($userId) {
                    $webservicelog['user_id'] = $userId;
                }

                $webservicelog['direction'] = "in";
                $webservicelog['webservice_id'] = $webserviceID;
                $webservicelog['webservice_tbl'] = $tblDate;
                $webservicelog['command'] = $command;
                $webservicelog['data_in'] = $data_in;
                $webservicelog['ip'] = $ip;
                $webservicelog['created_at'] = $createTime;
                $webservicelog['updated_at'] = date("Y-m-d H:i:s");
                $db->insert("developer_activity_log", $webservicelog);

            }

        }

        function developerWebServiceDataOut($webserviceID, $tblDate, $dataOut, $completeTime) {

            $db = $this->db;

            $webservicelog["data_out"] = $dataOut;
            $webservicelog["completed_at"] = $completeTime;
            $webservicelog['updated_at'] = date("Y-m-d H:i:s");

            $db->where("direction", "in");
            $db->where("webservice_tbl", $tblDate);
            $db->where("webservice_id", $webserviceID);
            $db->update("developer_activity_log", $webservicelog);
        }

        function developerOutgoingWebService($user_id, $command, $webservice_url, $data_in, $data_out) {

            $db = $this->db;
            
            $webservicelog['direction'] = "out";
            $webservicelog['user_id'] = $user_id;
            $webservicelog['command'] = $command;
            $webservicelog['webservice_url'] = $webservice_url;
            $webservicelog['data_in'] = $data_in;
            $webservicelog['data_out'] = $data_out;
            $webservicelog['created_at'] = date("Y-m-d H:i:s");
            $webservicelog['updated_at'] = date("Y-m-d H:i:s");
            $webservicelog['completed_at'] = date("Y-m-d H:i:s");
            $db->insert("developer_activity_log", $webservicelog);


        }
	}
?>
