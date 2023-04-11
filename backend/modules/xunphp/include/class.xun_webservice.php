<?php 

	class XunWebservice {
        
        function __construct($db)
        {
            $this->db = $db;
        }

		function insertXunWebserviceData($dataIn, $tblDate, $createTime, $command) {
            $db = $this->db;
            
			if(!trim($tblDate)) {
				$tblDate = date("Ymd");
			}

			$result = $db->rawQuery("CREATE TABLE IF NOT EXISTS xun_web_services_".$db->escape($tblDate)." LIKE xun_web_services");

			// Insert a new record into xun webservice table
            $command = $db->escape($command);
            $createTime = $db->escape($createTime);
            $dataIn = $db->escape($dataIn);
            
            $fields = array("command", "data_in", "created_at");
            $values = array($command, $dataIn, $createTime);
            $insertData = array_combine($fields, $values);

	    $insertId = $db->insert("xun_web_services_".$db->escape($tblDate)."", $insertData);
            
            return $insertId;
		}
        
        function updateXunWebserviceData($webserviceID, $dataOut, $status, $completeTime, $processedTime, $tblDate, $httpCode) {
			$db = $this->db;
            
			if(!trim($tblDate)) {
				$tblDate = date("Ymd");
			}
            
            if (!$db->tableExists ('xun_web_services_'.$tblDate))
                $db->rawQuery("CREATE TABLE IF NOT EXISTS xun_web_services_".$db->escape($tblDate)." LIKE xun_web_services");

            $status = $db->escape($status);
            $completeTime = $db->escape($completeTime);
            $processedTime = $db->escape($processedTime);
            $dataOut = $db->escape($dataOut);
            $httpCode = $db->escape($httpCode);

            $fields = array("data_out", "status", "completed_at", "duration", "http_code");
            $values = array($dataOut, $status, $completeTime, $processedTime, $httpCode);
            $updateData = array_combine($fields, $values);
            
            $db->where("id", $webserviceID);
            $db->update("xun_web_services_".$tblDate."", $updateData);

		}
        
	}
?>
