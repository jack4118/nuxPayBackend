<?php
    
    /**
     * Tree Stucture Class - Used for retrieving and setting hierachical structure in the system
     */
    
	class Tree {
        
        /**
         * Database instance
         */
        protected $db;
        protected $setting;
        
        function __construct($db, $setting, $general) {
            $this->db = $db;
            $this->setting = $setting;
            $this->general = $general;
        }
        
        public function getSponsorDownline($clientID, $directOnly=false,  $includeSelf=false)
        {
            $db = $this->db;
            
            if ($includeSelf)
            {
                // If it needs to include self id into the array
                $downlines[] = $clientID;
            }
            
            if ($directOnly)
            {
                // If it is for getting direct downline only
                $db->orderby("id", "ASC");
                $db->where("upline_id", $clientID);
                $result = $db->get("tree_sponsor", NULL, "client_id");
                foreach ($result as $row)
                {
                    $downlines[] = $row["client_id"];
                }
            }
            else
            {
                $db->where("client_id", $clientID);
                $clientTraceKey = $db->getValue("tree_sponsor", "trace_key");
                
                // Find the downline with the trace key
                $db->orderby("level", "ASC");
                $db->orderby("id", "ASC");
                $db->where("trace_key", $clientTraceKey."/%", "LIKE");
                $result = $db->get("tree_sponsor", null, "client_id");
                foreach ($result as $row)
                {
                    $downlines[] = $row["client_id"];
                }
            }
            
            return $downlines;
        }
        
        public function getSponsorUpline($clientID, $directOnly=false, $includeSelf=false)
        {
            $db = $this->db;
            
            $db->where("client_id", $clientID);
            $sponsorRes = $db->getOne("tree_sponsor", "upline_id, trace_key");
            
            if ($directOnly)
            {
                // If it is for getting direct upline only
                $uplines[] = $sponsorRes['upline_id'];
            }
            else
            {
                // Split the trace key to get the whole list of uplines
                $clientArray = explode("/", $sponsorRes['trace_key']);
                for ($i=0; $i<count($clientArray)-1; $i++)
                {
                    $uplines[] = $clientArray[$i];
                }
            }
            
            if ($includeSelf)
            {
                // If it needs to include self id into the array
                $uplines[] = $clientID;
            }
            
            // Sort the array by descending because we want to loop from bottom to top
            //krsort($uplines);
            $uplines = array_reverse($uplines);
            
            return $uplines;
        }
        
        
        

        function getSponsorByUsername($username) {
            $db = $this->db;

            $db->where("username", $username);
            $client = $db->getOne("client", "id,username,name,email,phone,country_id,sponsor_id");
            if(!$client) return false;

            $db->where("client_id", $client["id"]);
            $sponsor = $db->getOne("tree_sponsor","trace_key");
            if(!$sponsor) return false;

            $client["trace_key"] = $sponsor["trace_key"];


            return $client;
        }

        function getPlacementByUsername($username) {
            $db = $this->db;

            $db->where("username", $username);
            $client = $db->getOne("client", "id,username,name,email,phone,country_id,sponsor_id");
            if(!$client) return false;

            $db->where("client_id", $client["id"]);
            $placement = $db->getOne("tree_placement","client_position,trace_key");
            $client["trace_key"] = $placement["trace_key"];

            $db->where("upline_id", $client["id"]);
            $result = $db->get("tree_placement", null, "client_id, client_position");

            foreach ($result as $key => $val) {
                $client["position"][$val["client_position"]] = $val;           
            }

            if(!$client) return false;

            return $client;

        }
         
        public function insertSponsorTree($clientID, $uplineID)
        {
            $db = $this->db;

            $db->where("client_id", $uplineID);
            $result = $db->getOne("tree_sponsor", "level, trace_key");
            if(!$result) return false;

            $traceKey = $result["trace_key"]."/".$clientID;
            $level = $result["level"] + 1;
            
            $fields = array("client_id", "upline_id", "level", "trace_key");
            $values = array($clientID, $uplineID, $level, $traceKey);
            $data = array_combine($fields, $values);

            $id = $db->insert("tree_sponsor", $data);

            if(!$id) return false;

            return true;
        }

        public function insertPlacementTree($clientID, $uplineID, $position)
        {
            $db = $this->db;
            $setting = $this->setting;

            $maxPlacementPositions = $setting->systemSetting["maxPlacementPositions"];
            
            $db->where("client_id", $uplineID);
            $result = $db->getOne('tree_placement', "client_unit as upline_unit, client_position as upline_position, level, trace_key");
            if(!$result) return false;

            if($maxPlacementPositions == 2){

                if($position == 1) $positionKey = "<";
                else $positionKey = ">";
                    
            }else if($maxPlacementPositions == 3){
                    
                if($position == 1) $positionKey = "<";
                else if($position == 2) $positionKey = "|";
                else $positionKey = ">";
            }

            $traceKey = $result["trace_key"].$positionKey.$clientID."-1";
            $level = $result["level"] + 1;

            $fields    = array("client_id", "client_unit", "client_position", "upline_id", "upline_unit", "upline_position", "level", "trace_key");
            $values    = array($clientID, "1", $position, $uplineID, $result["upline_unit"], $result["upline_position"], $level, $traceKey);
            $data = array_combine($fields, $values);

            $id = $db->insert ('tree_placement', $data);

            if(!$id) return false;

            return true;
        }

//        function specialAdminSearchSpecificMember($memberID) {
//            global $db;
//
//            $sponsorIDQuery = "SELECT ID FROM mlmClient WHERE memberID='".mysql_escape_string($memberID)."'";
//            $sponsorIDRes = $db->dbSql($sponsorIDQuery);
//            $sponsorIDRow = mysql_fetch_assoc($sponsorIDRes);
//
//            $sponsorID = $sponsorIDRow["ID"];
//
//            if(trim($sponsorID)) {
//                $downlineID[$sponsorID] = mysql_escape_string($sponsorID);
//
//                $sponsorPositionQuery = "SELECT traceKey FROM mlmSponsorTreeNew WHERE clientID='".mysql_escape_string($sponsorID)."'";
//                $sponsorPositionRes = $db->dbSql($sponsorPositionQuery);
//                $sponsorPositionRow = mysql_fetch_assoc($sponsorPositionRes);
//
//                $sponsorTraceKey = $sponsorPositionRow["traceKey"];
//
//                // Get the downline data
//                $sponsorQuery = "SELECT clientID AS ID FROM mlmSponsorTreeNew WHERE traceKey LIKE '".mysql_escape_string($sponsorTraceKey."/%")."'";
//                $res = $db->dbSql($sponsorQuery, false, false);
//                while($row = mysql_fetch_assoc($res)) {
//                    $downlineID[$row["ID"]] = mysql_escape_string($row["ID"]);
//                }
//            }
//            else {
//                $downlineID['0'] = '0';
//            }
//
//            return $downlineID;
//        }

//        function getSponsorTreeUplines($clientID, $limit = null, $includeSelf = 1) {
//            $db = $this->db;
//
//            $db->where("client_id", $clientID);
//            $result = $db->getOne('tree_sponsor',"trace_key");
//
//            $uplineIDArray = explode("/", $result["trace_key"]);
//            krsort($uplineIDArray);
//            if(!$includeSelf) unset($uplineIDArray[count($uplineIDArray)-1]);
//            if($limit) $uplineIDArray = array_slice($uplineIDArray,0,$limit);
//
//            return $uplineIDArray;
//        }

        function getPlacementTreeUplines($clientID, $director = true) {
            $db = $this->db;
            $setting = $this->setting;

            $maxPlacementPositions = $setting->systemSetting["maxPlacementPositions"];
            $db->where("client_id", $clientID);
            $result = $db->getOne('tree_placement',"trace_key");

            $uplinesID = preg_split('/(?<=[0-9])(?=[<|>]+)/i', $result["trace_key"]);

            foreach ($uplinesID as $key => $upline) {

                if(!is_numeric($upline[0])){
                    $clientID = explode("-", substr($upline, 1))[0];

                    if($maxPlacementPositions == 2) $linesData[$clientID]["position"] = ($upline[0] == "<")? 1 : 2;
                    else if($maxPlacementPositions == 3) $linesData[$clientID]["position"] = ($upline[0] == "<") ? 1 : (($upline[0] == "|") ? 2 : 3);

                }else{
                    if(!$director) continue;

                    $clientID = explode("-", $upline)[0];
                    $linesData[$clientID]["position"] = 0;
                }

                $uplineIDArray[] = $clientID;
                $linesData[$clientID]["clientID"] = $clientID;

            }

            return array($linesData, $uplineIDArray);
        }        

//        function getSponsorTreeDownlines($clientID, $includeSelf = true) {
//            $db = $this->db;
//
//            $db->where("client_id", $clientID);
//            $result = $db->getOne("tree_sponsor", "trace_key");
//
//            $db->where("trace_key", $result["trace_key"]."%", "LIKE");
//            $result = $db->get("tree_sponsor", null, "client_id");
//
//            foreach ($result as $key => $val) $downlineIDArray[$val["client_id"]] = $val["client_id"];
//
//            if(!$includeSelf) unset($downlineIDArray[$clientID]);
//
//            return $downlineIDArray;
//        }
        
        function getPlacementTreeDownlines($clientID, $includeSelf = true) {
            $db = $this->db;   

            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_placement", "trace_key");

            $db->where("trace_key", $result["trace_key"]."%", "LIKE");
            $result = $db->get("tree_placement", null, "client_id");

            foreach ($result as $key => $val) $downlineIDArray[$val["client_id"]] = $val["client_id"];

            if(!$includeSelf) unset($downlineIDArray[$clientID]);

            return $downlineIDArray;
        }

//        function rebuildSponsorTree($movingTree){
//            foreach($movingTree as $moved) {
//                // Loop to insert the tree under the new sponsor
//                $bool = $this->insertSponsorTree($moved["client_id"], $moved["upline_id"]);
//
//                if(!$bool) $failedArray[] = $moved;
//            }
//
//            if(count($failedArray) > 0) $this->rebuildSponsorTree($failedArray);
//            else return true;
//        }
//
//        function rebuildPlacementTree($movingTree){
//                print_r($movingTree);
//            foreach($movingTree as $moved) {
//                // Loop to insert the tree under the new sponsor
//                $bool = $this->insertPlacementTree($moved["client_id"], $moved["upline_id"], $moved["position"]);
//
//                if(!$bool) $failedArray[] = $moved;
//            }
//            echo "failed array :  \n\n";
//            print_r($failedArray);
//            echo "next nested loop :\n\n";
//            if(count($failedArray) > 0) $this->rebuildPlacementTree($failedArray);
//            else return true;
//
//        }

//        function changeSponsor($params) {
//            $db = $this->db;
//            //current client
//            $clientID = trim($params["clientID"]);
//            //client that target to change
//            $sponsorUsername = trim($params["sponsorUsername"]);
//
//            // Check on required fields
//            if(strlen($clientID) == 0) return array('status' => "error", 'code' => 2, 'statusMsg' => $lang["E00069"][$language], 'data' => "");
//            if(strlen($sponsorUsername) == 0) return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00072"][$language], 'data' => array('field' => "sponsorUsername"));
//
//            // Get sponsor by username
//            $targetSponsor = $this->getSponsorByUsername($sponsorUsername);
//
//            if(!$targetSponsor) {
//                return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00074"][$language], 'data' => array('field' => "sponsorUsername"));
//            }
//            else {
//                $targetSponsorTraceKey = explode("/", $targetSponsor["trace_key"]);
//                foreach ($targetSponsorTraceKey as $val) $targetSponsorUplinesID[$val] = $val;
//
//            }
//
//            //get current client's sponsor ID
//            $db->where("id", $clientID);
//            $client = $db->getOne("client", "sponsor_id");
//            $currentSponsorID = $client["sponsor_id"];
//
//            // If is the same sponsor, skip it
//            if($targetSponsor["id"] == $currentSponsorID) return array('status' => "error", 'code' => 1, 'statusMsg' => "you are changing the same sponsor.", 'data' => array('field' => "sponsorUsername"));
//
//            // If is ownself, skip it
//            if($newSponsorData["id"] == $clientID) return array('status' => "error", 'code' => 1, 'statusMsg' => "not allow change sponsor to ownself.", 'data' => array('field' => "sponsorUsername"));
//
//            $db->where("client_id", $clientID);
//            $result = $db->getOne("tree_sponsor", "trace_key");
//            $clientTraceKey = $result["trace_key"];
//
//            if(!$clientTraceKey) {
//                // Skip if encounter error
//                return array('status' => "error", 'code' => 1, 'statusMsg' => "client not found.", 'data' => array('field' => "sponsorUsername"));
//            }
//
//            // Compare level, cannot change to a lower level sponsor in the same tree
//            if($targetSponsorUplinesID[$clientID]) {
//                return array('status' => "error", 'code' => 1, 'statusMsg' => "not allow change sponsor to a lower position in the same genealogy.", 'data' => array('field' => "sponsorUsername"));
//            }
//
//            $db->where("trace_key", $clientTraceKey."%", "LIKE");
//            $db->orderby("level", "asc");
//            $db->orderby("id", "asc");
//            $movingTree = $db->get("tree_sponsor", null, "client_id,upline_id");
//
//            //get client's data who going move to new tree
//            foreach ($movingTree as $key => $val) {
//                if($val["client_id"] == $clientID) $movingTree[$key]["upline_id"] = $targetSponsor['id'];
//                $movingClientArray[] = $val["client_id"];
//            }
//
//            $db->where("id", $clientID);
//            $db->update("client", array("sponsor_id" => $targetSponsor['id']));
//
//            $db->where("client_id", $movingClientArray, 'IN');
//            $db->delete("tree_sponsor");
//
//            $this->rebuildSponsorTree($movingTree);
//
//            $data['newSponsorID'] = $targetSponsor["id"];
//            $data['newSponsorUsername'] = $targetSponsor['username'];
//            $data['newSponsorName'] = $targetSponsor['name'];
//
//            return array('status' => "ok", 'code' => 0, 'statusMsg' => "change sponsor successfully.", 'data' => $data);
//        }
//
//        function changePlacement($params) {
//            $db = $this->db;
//            $setting = $this->setting;
//
//            $maxPlacementPositions = (int)$setting->systemSetting["maxPlacementPositions"];
//
//            $clientID = trim($params["clientID"]);
//            $targetUsername = trim($params["targetUsername"]);
//            $targetPosition = trim($params["targetPosition"]);
//
//            // Check on required fields
//            if(strlen($clientID) == 0) return array('status' => "error", 'code' => 2, 'statusMsg' => $lang["E00069"][$language], 'data' => "");
//            if(strlen($targetUsername) == 0) return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00075"][$language], 'data' => array('field' => "placementUsername"));
//
//            // Check whether placement position is out of range
//            if($targetPosition > $maxPlacementPositions) {
//                return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00027"][$language], 'data' => array('field' => "placementPosition"));
//            }
//
//            $db->where("id", $clientID);
//            $client = $db->getOne("client","sponsor_id,placement_id, placement_position");
//            $currentSponsorID = $client["sponsor_id"];
//            $currentPlacementID = $client["placement_id"];
//            $currentPlacementPosition = $client["placement_position"];
//
//            $db->where("client_id", $currentPlacementID);
//            $result = $db->getOne("tree_placement", "trace_key");
//            $currentPlacementTraceKey = $result["trace_key"];
//
//            // Get placement by username
//            $targetPlacementData = $this->getPlacementByUsername($targetUsername);
//
//            if(!$targetPlacementData) return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00076"][$language], 'data' => array('field' => "placementUsername"));
//
//            $targetPlacementTraceKey = preg_split('/(?<=[0-9])(?=[<|>]+)/i', $targetPlacementData["trace_key"]);
//
//            foreach ($targetPlacementTraceKey as $key => $val) {
//                if(!is_numeric($val[0])) $uplineID = explode("-", substr($val, 1))[0];
//                else $uplineID = explode("-", $val)[0];
//
//                $targetPlacementUplinesID[$uplineID] = $uplineID;
//            }
//
//            // If is the same placement, skip it
//            if($targetPlacementData["id"] == $currentPlacementID) return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00077"][$language], 'data' => array('field' => "placementUsername"));
//
//            // If is ownself, skip it
//            if($targetPlacementData["id"] == $clientID) return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00076"][$language], 'data' => array('field' => "placementUsername"));
//
//            // Check whether placement positions are fully occupied
//            if(count($targetPlacementData['position']) >= $maxPlacementPositions) {
//                return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00029"][$language], 'data' => array('field' => "placementUsername"));
//            }
//            // Check whether placement position is occupied
//            if($targetPlacementData['position'][$targetPosition]) {
//                return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00027"][$language], 'data' => array('field' => "placementPosition"));
//            }
//
//            $db->where("client_id", $clientID);
//            $result = $db->getOne("tree_placement", "trace_key");
//            $clientTraceKey = $result["trace_key"];
//
//            if(!$clientTraceKey) {
//                // Skip if encounter error
//                return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00076"][$language], 'data' => array('field' => "placementUsername"));
//            }
//
//            // Compare level, cannot change to a lower level placement in the same tree
//            if($targetPlacementUplinesID[$clientID]) {
//                return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00076"][$language], 'data' => array('field' => "placementUsername"));
//            }
//
//            $db->where("trace_key", $clientTraceKey."%", "LIKE");
//            $db->orderby("level", "asc");
//            $db->orderby("id", "asc");
//            $movingTree = $db->get("tree_placement", null, "client_id,upline_id,client_position as position");
//
//            //get client's data who going move to new tree
//            foreach ($movingTree as $key => $val) {
//                if($val["client_id"] == $clientID){
//                    $movingTree[$key]["upline_id"] = $targetPlacementData['id'];
//                    $movingTree[$key]["position"] = $targetPosition;
//                }
//                $movingClientArray[] = $val["client_id"];
//            }
//
//            $db->where("id", $clientID);
//            $insertClientData = array("placement_id" => $targetPlacementData['id'], "placement_position" => $targetPosition);
//            $db->update("client", $insertClientData);
//
//            $db->where("client_id", $movingClientArray, 'IN');
//            $db->delete("tree_placement");
//
//            $this->rebuildPlacementTree($movingTree);
//
//            $data['newPlacementUsername'] = $targetPlacementData["username"];
//            $data['newPlacementName'] = $targetPlacementData["name"];
//            $data['newPlacementPosition'] = $targetPosition;
//
//            return array('status' => "ok", 'code' => 0, 'statusMsg' => $lang["A00946"][$language], 'data' => $data);
//        }

        function getSponsorTree($params) {
            $db = $this->db;
            $general = $this->general;

            $clientID = trim($params["clientID"]);
            $targetID = trim($params["targetID"]);
            $viewType = trim($params["viewType"]);
            $viewType = trim($params["viewType"]);
            $targetUsername = trim($params["targetUsername"]);
            
            $offsetSecs = trim($params['offsetSecs']);

            //get sponsor tree by username if exist
            if($targetUsername){
                $db->where("username", $targetUsername);
                $result = $db->getOne("client", "id");
                if(!$result) return array('status' => "error", 'code' => 1, 'statusMsg' => "username not found", 'data' => array('field' => "targetUsername"));
                   
                $targetID = $result["id"];
            }

            $db->where("id", $targetID);
            $db->where("type", "Client");
            $targetClient = $db->getOne("client", "id,name,username");
            if(!$targetClient) return array('status' => "ok", 'code' => 0, 'statusMsg' => "Client Not Found", 'data' => array('field' => "targetID"));

            $db->where("client_id", $targetID);
            $result = $db->getOne("tree_sponsor", "level,trace_key");
            $targetClient["trace_key"] = $result["trace_key"];
            $targetClient["level"] = $result["level"];

            $filterTraceKey = strstr($targetClient["trace_key"], $clientID);

            $targetUplinesIDAry = explode("/", $filterTraceKey);
            $db->where("id", $targetUplinesIDAry, "in");
            $targetUplinesClientData = $db->map ('id')->ObjectBuilder()->get("client", null, "id,username,name,created_at");

            foreach ($targetUplinesIDAry as $key => $uplineID) {
                $username = $targetUplinesClientData[$uplineID]->username;
                $name = $targetUplinesClientData[$uplineID]->name;
                $createdAt = $targetUplinesClientData[$uplineID]->created_at;

                $tree['attr']['id'] = $uplineID;
                $tree['attr']['name'] = $name;
                $tree['attr']['username'] = $username;

                if($uplineID == $targetID){

                    $data['target']['attr']['id'] = $uplineID;
                    $data['target']['attr']['username'] = $username;
                    $data['target']['attr']['name'] = $name;
                    $data['target']['attr']['createdAt'] = $general->formatDateTimeToString($createdAt, "d/m/Y");

                    $targetLevel = $targetClient["level"];
                }
            }

            $limit = null;

            $db->where('level', $targetClient["level"], '>');
            $db->where('level', $targetClient["level"]+1, '<=');
            $db->where("trace_key", $targetClient["trace_key"]."%", "LIKE");
            if($viewType == "Horizontal") {
                $pageNumber = trim($params["pageNumber"]);
                if(!$pageNumber) $pageNumber = 1;
                $pagingLimit = 5;
                $startLimit = ($pageNumber-1) * $pagingLimit;
                $limit = array($startLimit, $pagingLimit);

                $copyDb = $db->copy();
                $totalRecord = $copyDb->getValue("tree_sponsor", "count(id)");
                $totalPage = ceil($totalRecord/$pagingLimit);
                $data['totalPage'] = $totalPage;

            }

            $result = $db->get("tree_sponsor", $limit, "client_id,level,trace_key");
            
            foreach ($result as $key => $val) {
                $val["depth"] = $val["level"] - $targetLevel;
                $downlineData[] = $val;
                $downlineIDAry[] = $val["client_id"];
            }
            
            $data['downline'] = [];
            if(count($downlineIDAry) == 0) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "no client found", 'data' => $data);
            }

            $db->where("id", $downlineIDAry, "in");
            $targetDownlinesClientData = $db->map ('id')->ObjectBuilder()->get("client", null, "id, username, name, created_at, disabled, suspended, freezed");
            
            foreach($downlineData as $row) {
                $downlineID = $row["client_id"];

                $downline['attr']['id'] = $downlineID;
                $downline['attr']['username'] = $targetDownlinesClientData[$downlineID]->username;
                $downline['attr']['name'] = $targetDownlinesClientData[$downlineID]->name;
                $downline['attr']['createdAt'] = $general->formatDateTimeToString($targetDownlinesClientData[$downlineID]->created_at, "d/m/Y");
                $downline['attr']['downlineCount'] = count($this->getSponsorDownline($downlineID, false, false));
                $downline['attr']['disabled'] =($targetDownlinesClientData[$downlineID]->disabled == 0)?'No':'Yes';
                $downline['attr']['suspended'] = ($targetDownlinesClientData[$downlineID]->suspended == 0)?'No':'Yes';
                $downline['attr']['freezed'] = ($targetDownlinesClientData[$downlineID]->freezed == 0)?'No':'Yes';

                $data['downline'][] = $downline;
                unset($downline);

            }
            
            $data['targetID'] = (trim($params["clientID"]) == trim($params["targetID"]))?'':trim($params["targetID"]);
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

//       function getSponsorTreeByUsername($params, $clientID) {
//            $db = $this->db;
//
//           $clientID = trim($params["clientID"]);
//           $targetUsername = trim($params->targetUsername);
//           $viewType = trim($params->viewType);
//
//           $db->where("username", $targetUsername);
//           $result = $db->getOne("client", "id");
//           if(!$result) return array('status' => "error", 'code' => 1, 'statusMsg' => "username not found", 'data' => array('field' => "targetUsername"));
//
//           $targetID = $result["id"];
//
//           $db->where("client_id", $targetID);
//           $result = $db->get("tree_sponsor", "client_id, trace_key");
//           $targetTraceKey = $result["trace_key"];
//           $targetTraceKeyAry = explode("/", $targetTraceKey);
//           foreach ($targetTraceKeyAry as $val) $targetDownlineIDArray[$val] = $val;
//
//           // Target user not Downline
//           if(!$targetDownlineIDArray[$clientID]) {
//               return array('status' => "error", 'code' => 1, 'statusMsg' => $lang['E00114'][$language], 'data' => array("field"=>"targetUsername"));
//           }
//
//           $sponsorTree = $this->getSponsorTree($params);
//
//           return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $sponsorTree['data']);
//       }

//        function getPlacementTree($params) {
//            $db = $this->db;
//            $setting = $this->setting;
//
//            $clientID = trim($params["clientID"]);
//            $targetID = trim($params["targetID"]);
//            $viewType = trim($params["viewType"]);
//
//            $maxPlacementPositions = $setting->systemSetting["maxPlacementPositions"];
//
//            if(strlen($clientID) == 0) return array('status' => "error", 'code' => 1, 'statusMsg' => "invalid client", 'data' => "clientID");
//            if(!$viewType) return array('status' => "error", 'code' => 1, 'statusMsg' => "select view type", 'data' => array('field' => "targetID"));
//
//
//            //for($i=1; $i<=$maxPlacementPositions; $i++) {
//            //    $clientSettingName[] = "'Placement Total $i'";
//            //    $clientSettingName[] = "'Placement CF Total $i'";
//            //}
//
//            $db->where("id", $targetID);
//            $db->where("type", "Member");
//            $result = $db->getOne("client", "id");
//            if(!$result) return array('status' => "ok", 'code' => 0, 'statusMsg' => "Client Not Found", 'data' => array('field' => "targetID"));
//
//
//            $db->where("client_id", $targetID);
//            $targetClient = $db->getOne("tree_placement", "level,trace_key");
//
//            $filterTraceKey = strstr($targetClient["trace_key"], $clientID);
//
//            $targetTraceKey = preg_split('/(?<=[0-9])(?=[<|>]+)/i', $filterTraceKey);
//
//
//            foreach ($targetTraceKey as $key => $val) {
//                if(!is_numeric($val[0])){
//                    $targetUplinesID[] = explode("-", substr($val, 1))[0];
//
//                }else{
//                    $targetUplinesID[] = explode("-", $val)[0];
//                }
//            }
//
//            $db->where("client_id" , $targetUplinesID, "IN");
//            $targetUplinesAry = $db->get("tree_placement", null, "client_id,client_position,level,trace_key");
//
//            $db->where("id" , $targetUplinesID, "IN");
//            $targetUplinesClient = $db->map ('id')->ObjectBuilder()->get("client", null, "id,username,name, created_at");
//
//            foreach ($targetUplinesAry as $key => $upline) {
//                $uplineID = $upline['client_id'];
//                $username = $targetUplinesClient[$uplineID]->username;
//                $name = $targetUplinesClient[$uplineID]->name;
//                $createdAt = $targetUplinesClient[$uplineID]->created_at;
//
//                $tree['attr']['ID'] = $uplineID;
//                $tree['attr']['name'] = $name;
//                $tree['attr']['username'] = $username;
//                // Build the level from clientID to targetID
//                $data['treeLink'][] = $tree;
//
//                if($uplineID == $targetID) {
//
//                    $data['target']['attr']['id'] = $uplineID;
//                    $data['target']['attr']['username'] = $username;
//                    $data['target']['attr']['name'] = $name;
//                    $data['target']['attr']['createdAt'] = strtotime($createdAt);
//
//                    $targetLevel = $upline["level"];
//                }
//            }
//
//            $depthRule = "1";
//            if($viewType == "Horizontal") $depthRule = "3";
//
//            $db->where("level", $targetClient["level"], ">");
//            $db->where("level", $targetClient["level"]+$depthRule, "<=");
//            $db->where("trace_key", $targetClient["trace_key"]."%", "LIKE");
//            $targetDownlinesAry = $db->get("tree_placement", null," client_id,client_unit,client_position,level,trace_key");
//
//            foreach ($targetDownlinesAry as $key => $val) $targetDownlinesIDAry[] = $val["client_id"];
//            $db->where("id", $targetDownlinesIDAry, "in");
//            $targetDownlinesClient = $db->map('id')->ObjectBuilder()->get("client",null,"id,username,name,created_at");
//
//
//            if(count($targetDownlinesAry) == 0) return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
//
//            foreach ($targetDownlinesAry as $key => $targetDownline) {
//                $depth = $targetDownline["level"] - $targetLevel;
//                $downlineID = $targetDownline['client_id'];
//                $username = $targetDownlinesClient[$downlineID]->username;
//                $name = $targetDownlinesClient[$downlineID]->name;
//                $createdAt = $targetDownlinesClient[$downlineID]->created_at;
//
//                $downline['attr']['id'] = $downlineID;
//                $downline['attr']['username'] = $username;
//                $downline['attr']['name'] = $name;
//                $downline['attr']['position'] = $targetDownline["client_position"];
//                $downline['attr']['depth'] = $depth;
//                $downline['attr']['createdAt'] = strtotime($createdAt);
//
//                $data['downline'][] = $downline;
//                unset($downline);
//
//                //get placement total in client setting
//            }
//
//            // $data['generatePlacementBonusType'] = $setting->internalSetting['generatePlacementBonusType'];
//            // $data['placementLRDecimalType'] = $setting->internalSetting['placementLRDecimalType'];
//
//            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
//        }

//        function getPlacementTreeByUsername($params, $clientID) {
//            global $db, $lang, $language, $source;
//
//            if($source == 'Member') {
//                $params->clientID = $clientID;
//            }
//            else {
//                $clientID = trim($params->clientID);
//            }
//
//            $targetUsername = trim($params->targetUsername);
//            $viewType = trim($params->viewType);
//
//            $res = $db->dbSelect("mlmClient", "ID", "username='".mysql_escape_string($targetUsername)."'", "", "", "", true);
//            if($db->dbFetchRow($res)) {
//                $clientRow = $db->dbRow["mlmClient"];
//                $params->targetID = $clientRow['ID'];
//                $targetID = $clientRow['ID'];
//            }
//            else {
//                return array('status' => "error", 'code' => 1, 'statusMsg' => $lang['E00114'][$language], 'data' => array('field' => "targetUsername"));
//            }
//
//            $res = $db->dbSelect("mlmPlacementTreeNew", "clientID, traceKey", "clientID='".mysql_escape_string($targetID)."'");
//            while($db->dbFetchRow($res)) {
//                $row = $db->dbRow["mlmPlacementTreeNew"];
//                
//                $targetTraceKey = $row["traceKey"];
//
//                $targetTraceKeyAry = preg_split('/(?<=[0-9])(?=[<|>]+)/i', $targetTraceKey);
//
//                for($x = 0; $x < count($targetTraceKeyAry); $x++) {
//                    if(!is_numeric($targetTraceKeyAry[$x][0])) {
//                        $targetDownlineIDArray[substr($targetTraceKeyAry[$x], 1)] = substr($targetTraceKeyAry[$x], 1);
//                    }
//                    else {
//                        $targetDownlineIDArray[$targetTraceKeyAry[$x]] = $targetTraceKeyAry[$x];
//                    }
//                }
//            }
//
//            // Target user not Downline
//            if(!$targetDownlineIDArray[$clientID]) {
//                return array('status' => "error", 'code' => 1, 'statusMsg' => $lang['E00114'][$language], 'data' => array("field"=>"targetUsername"));
//            }
//
//            $placementTree = $this->getPlacementTree($params);
//
//            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $placementTree['data']);
//        }

    }
	
?>
