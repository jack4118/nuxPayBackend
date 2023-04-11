<?php

	class Credit
    {
        function __construct($db, $general)
        {
            $this->db = $db;
            $this->general = $general;
        }
        
        function getCredits($params) {
            $db = $this->db;
            $general = $this->general;
            
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);
            
            $searchData = $params['searchData'];
            
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'name':
                            $db->where('name', $dataValue);
                            break;
                            
                        case 'translation_code':
                            $db->where('translation_code', $dataValue);
                            break;
                            
                        case 'priority':
                            $db->where('priority', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");
            $result = $db->get('credit', $limit);

            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("credit", "count(id)");  
                foreach($result as $value) {

                    $credit['id'] = $value['id'];
                    $credit['name'] = $value['name'];
                    $credit['description'] = $value['description'];
                    $credit['translationCode'] = $value['translation_code'];
                    $credit['priority'] = $value['priority'];
                    $credit['createdAt'] = $value['created_at'];
                    $credit['updatedAt'] = $value['updated_at'];

                    $creditList[] = $credit;
                }
                
                $data['creditList'] = $creditList;
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['pageNumber']   = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord'] = $limit[1];
            
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            } 
        }
        
        function addCredit($params){
            $db = $this->db;

            $creditName = trim($params['creditName']);
            $description = trim($params['description']);
            $translationCode = trim($params['translationCode']);
            $priority = trim($params['priority']);

            if(strlen($creditName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Credit Name", 'data'=>"");
            
            if(strlen($description) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter a Description", 'data'=>"");
            
            if(strlen($translationCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter a translation code", 'data'=>"");
            
            if(strlen($priority) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter a priority", 'data'=>"");
            
            $db->where('name', $creditName);
            $result = $db->get('credit');

            if (!empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Duplicate Credit Name", 'data'=>"");
            
            $fields = array('name', 'description', 'translation_code', 'priority', 'created_at', 'updated_at');
            $values = array($creditName, $description, $translationCode, $priority, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'));
            $arrayData = array_combine($fields, $values);
            
            try{
                $creditID = $db->insert('credit', $arrayData);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to add new credit', 'data'=>'');
            }
            
            $creditPreset = $db->tableExists('credit_setting_preset');
            if (!$creditPreset)
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Credit Setting Preset table not found', 'data' => ''); 
            
            $cols = Array('name', 'value', 'type', 'reference', 'description');
            $result = $db->get('credit_setting_preset', null, $cols);
            
            if (empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Credit Setting Preset table is empty.', 'data' => ''); 
             
            foreach($result as $array) {
                $tmpName[] = $array['name'];
                $tmpValue[] = $array['value'];
                $tmpType[] = $array['type'];
                $tmpRef[] = $array['reference'];
                $tmpDesc[] = $array['description'];
            }
            
            $creditSettingArr = array();
            $i = 0;
            foreach ($result as $array) {
                $creditSettingArr[$i]['name'] = $array['name'];
                $creditSettingArr[$i]['value'] = $array['value'];
                $creditSettingArr[$i]['type'] = $array['type'];
                $creditSettingArr[$i]['reference'] = $array['reference'];
                $creditSettingArr[$i]['description'] = $array['description'];
                $creditSettingArr[$i]['credit_id'] = $creditID;
                $i++;
            }
            
            try{
                $result = $db->insertMulti("credit_setting", $creditSettingArr);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to assign setting for this credit", 'data'=>"");
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> 'Successfully Added', 'data'=>'');
        }
        
        function getCreditDetails($params){
            $db = $this->db;
            
            $id = trim($params['id']);
            
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Credit", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->get("credit", 1);
            
            if (!empty($result)) {
                
                $credit['id'] = $result[0]["id"];
                $credit['creditName'] = $result[0]["name"];
                $credit['description'] = $result[0]["description"];
                $credit['translationCode'] = $result[0]["translation_code"];
                $credit['priority'] = $result[0]["priority"];
                
                $data['creditDetails'] = $credit;
            
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);  
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Credit", 'data'=>"");
            }
        }
        
        function editCredit($params){
            $db = $this->db;
            
            $id = trim($params['id']);
            $creditName = trim($params['creditName']);
            $description = trim($params['description']);
            $translationCode = trim($params['translationCode']);
            $priority = trim($params['priority']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Credit ID does not exist", 'data'=>"");

            if(strlen($creditName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Credit Name", 'data'=>"");
            
            if(strlen($description) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter description", 'data'=>"");
            
            if(strlen($translationCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Translation Code", 'data'=>"");
            
            if(strlen($priority) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter priority", 'data'=>"");
            
            $db->where('id', $id);
            $result = $db->get('credit', 1);

            if (!empty($result)) {
                $db->where('name', $creditName);
                $db->where('id !='.$id);
                $result = $db->get('credit');
                if (!empty($result))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Duplicate Credit Name", 'data'=>"");
                
                $fields = array('name', 'description', 'translation_code', 'priority', 'updated_at');
                $values = array($creditName, $description, $translationCode, $priority, date('Y-m-d H:i:s'));
                
                $arrayData = array_combine($fields, $values);
                $db->where('id', $id);
                $db->update('credit', $arrayData);

                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Successfully Updated");
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No Result", 'data'=>"");
            }
        }
        
        function deleteCredit($params){
            $db = $this->db;

            $id = trim($params['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => "Please Select Credit", 'data'=>"");
            
            $db->where('id', $id);
            $result = $db->get('credit', 1);
            if (empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Credit not found", 'data'=>"");
            
            $db->where('id', $id);
            $result = $db->delete('credit');
            if(!$result)
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete credit', 'data' => '');
             
            $db->where('credit_id', $id);
            $result = $db->delete('credit_setting');
            if(!$result)
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete credit setting', 'data' => '');
            
            return $this->getCredits();
        }
        
        function getCreditSettingDetails($params){
            $db = $this->db;
            
            $id = trim($params['id']);
            
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Credit", 'data'=> '');
            
            $db->where('credit_id', $id);
            
            $cols = Array ('id', 'name', 'value');
            $result = $db->get('credit_setting', null, $cols);
            
            if (!empty($result)) {
                foreach($result as $array) {
                    $creditSettingID[] = $array['id'];
                    $name[] = $array['name'];
                    $value[] = $array['value'];
                }
                
                $credit['creditSettingID'] = $creditSettingID;
                $credit['name'] = $name;
                $credit['value'] = $value;
                
                $data['creditSetting'] = $credit;
            
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);  
            }
            else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Credit Settings Found", 'data'=>'');
            }
        }
        
        function editCreditSetting($params){
            $db = $this->db;
            
            $creditID = $params['creditID'];
            $id = $params['id'];
            $values = $params['values'];
            
            $fields = array('value');
            foreach($id as $key=>$val) {
                $data = array($values[$key]);
                $arrayData = array_combine($fields, $data);
                
                $db->where('credit_id', $creditID);
                $db->where('id', $val);
                try {
                    $db->update('credit_setting', $arrayData);
                }
                catch (Exception $e) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Fail to update completely.", 'data'=>"");
                }
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Successfully updated.', 'data' => '');
        }
	}

?>
