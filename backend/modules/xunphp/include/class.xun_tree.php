<?php

/**
 * Tree Stucture Class - Used for retrieving and setting hierachical structure in the system
 */

class XunTree
{

    /**
     * Database instance
     */
    protected $db;
    protected $setting;

    public function __construct($db, $setting, $general)
    {
        $this->db = $db;
        $this->setting = $setting;
        $this->general = $general;
    }

    public function getSponsorDownlineByUserID($userID, $userData = null)
    {
        $db = $this->db;

        $db->where("user_id", $userID);
        $userTraceKey = $db->getValue("xun_tree_referral", "trace_key");

        // Find the downline with the trace key
        $db->orderby("level", "asc");
        $db->orderby("id", "asc");
        $db->where("trace_key", $userTraceKey . "/%", "LIKE");
        $result = $db->get("xun_tree_referral", null, "id, user_id, level, created_at");
        foreach ($result as $row) {
            $downlines[] = $row["user_id"];
        }

        if ($userData) {
            $userDataArr = $this->getUserData($downlines, true);
            $downlinesLen = count($result);
            $downlinesUserData = [];
            for ($i = 0; $i < $downlinesLen; $i++) {
                $downlineUserID = $downlines[$i];
                $userDataObj = (array) $userDataArr[$downlineUserID];
                $newObj = array_merge($result[$i], $userDataObj);
                unset($newObj["user_id"]);
                $result[$i] = $newObj;
            }
        }

        return $result;
    }

    public function getUserData($userIDArr, $returnObj = false)
    {
        $db = $this->db;

        if (sizeof($userIDArr) > 0) {
            $db->where("id", $userIDArr, "in");
            if ($returnObj === true) {
                $db->map("id")->ObjectBuilder();
            }
            $downline_user_data = $db->get("xun_user", null, "id, username, nickname");

            return $downline_user_data;
        } else {
            return array();
        }
    }

    public function getSponsorDirectDownlineByUserID($userID, $userData = null)
    {
        $db = $this->db;

        $db->where("upline_id", $userID);
        $result = $db->get("xun_tree_referral", null, "user_id");

        foreach ($result as $row) {
            $downlines[] = $row["user_id"];
        }

        if ($userData) {
            $downlines = $this->getUserData($downlines);
        }

        return $downlines;

    }

    public function getSponsorUplineByUserID($userID, $limit)
    {
        $db = $this->db;

        $db->where("user_id", $userID);
        $userTraceKey = $db->getValue("xun_tree_referral", "trace_key");

        // Split the trace key to get the whole list of uplines
        $userArray = explode("/", $userTraceKey);
        if ($limit == 1) {
            $uplines[] = $userArray[1];
        } else {
            for ($i = 0; $i < count($userArray) - 1; $i++) {
                $uplines[] = $userArray[$i];
            }
        }

        // Sort the array by descending because we want to loop from bottom to top
        krsort($uplines);

        return $uplines;
    }

    public function getSponsorUplineIDByUserID($userID)
    {
        $db = $this->db;

        $db->where("user_id", $userID);
        $uplineID = $db->getValue("xun_tree_referral", "upline_id");

        return $uplineID;
    }

    public function getSponsorMasterUplineIDByUserID($userID)
    {
        $db = $this->db;

        $uplineIDArr = $this->getSponsorUplineByUserID($userID, 1);

        $highestUpline = $uplineIDArr[0];
        if (is_null($highestUpline)) {
            return;
        }

        if ($highestUpline === $userID) {
            return;
        }

        $db->where("user_id", $highestUpline);
        $isMasterUpline = $db->getValue("xun_tree_referral", "master_upline");

        if ($isMasterUpline) {
            return $highestUpline;
        }

        return;
    }

    public function getSponsorUplineAndMasterUplineByUserID($userID)
    {
        $db = $this->db;

        $db->where("user_id", $userID);
        $upline = $db->getOne("xun_tree_referral", "upline_id, master_upline");

        return $upline;
    }

    public function getSponsorByUsername($username, $xunUser = null)
    {
        $db = $this->db;

        if (!$xunUser) {
            $db->where("username", $username);
            $xunUser = $db->getOne("xun_user", "id, disabled, username, nickname");
            if (!$xunUser) {
                return false;
            }

            if ($xunUser["disabled"]) {
                return false;
            }

        }

        $db->where("user_id", $xunUser["id"]);
        $sponsor = $db->getOne("xun_tree_referral", "trace_key, upline_id, master_upline");
        if (!$sponsor) {
            return false;
        }

        $xunUser["trace_key"] = $sponsor["trace_key"];
        $xunUser["upline_id"] = $sponsor["upline_id"];
        $xunUser["master_upline"] = $sponsor["master_upline"];

        return $xunUser;
    }

    public function insertSponsorTree($userID, $uplineID)
    {
        $db = $this->db;

        $db->where("user_id", $uplineID);
        $result = $db->getOne("xun_tree_referral", "level, trace_key");
        if (!$result) {
            return false;
        }

        $traceKey = $result["trace_key"] . "/" . $userID;
        $level = $result["level"] + 1;

        $fields = array("user_id", "upline_id", "level", "trace_key", "created_at", "updated_at");
        $values = array($userID, $uplineID, $level, $traceKey, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));
        $data = array_combine($fields, $values);

        $id = $db->insert("xun_tree_referral", $data);

        if (!$id) {
            return false;
        }

        return true;
    }

    public function getSponsorTreeUplines($userID, $limit = null, $includeSelf = 1)
    {
        $db = $this->db;

        $db->where("user_id", $userID);
        $result = $db->getOne('xun_tree_referral', "trace_key");

        $uplineIDArray = explode("/", $result["trace_key"]);
        krsort($uplineIDArray);
        if (!$includeSelf) {
            unset($uplineIDArray[count($uplineIDArray) - 1]);
        }

        if ($limit) {
            $uplineIDArray = array_slice($uplineIDArray, 0, $limit);
        }

        return $uplineIDArray;
    }

    public function getSponsorTreeDownlines($userID, $includeSelf = true)
    {
        $db = $this->db;

        $db->where("user_id", $userID);
        $result = $db->getOne("xun_tree_referral", "trace_key");

        $db->where("trace_key", $result["trace_key"] . "%", "LIKE");
        $result = $db->get("xun_tree_referral", null, "user_id");

        foreach ($result as $key => $val) {
            $downlineIDArray[$val["user_id"]] = $val["user_id"];
        }

        if (!$includeSelf) {
            unset($downlineIDArray[$userID]);
        }

        return $downlineIDArray;
    }

    public function rebuildSponsorTree($movingTree)
    {
        foreach ($movingTree as $moved) {
            // Loop to insert the tree under the new sponsor
            $bool = $this->insertSponsorTree($moved["user_id"], $moved["upline_id"]);

            if (!$bool) {
                $failedArray[] = $moved;
            }

        }

        if (count($failedArray) > 0) {
            $this->rebuildSponsorTree($failedArray);
        } else {
            return true;
        }

    }

    public function changeSponsor($params)
    {
        $db = $this->db;
        //current client
        $userID = trim($params["clientID"]);
        //client that target to change
        $sponsorUsername = trim($params["sponsorUsername"]);

        // Check on required fields
        if (strlen($userID) == 0) {
            return array('status' => "error", 'code' => 2, 'statusMsg' => $lang["E00069"][$language], 'data' => "");
        }

        if (strlen($sponsorUsername) == 0) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00072"][$language], 'data' => array('field' => "sponsorUsername"));
        }

        // Get sponsor by username
        $targetSponsor = $this->getSponsorByUsername($sponsorUsername);

        if (!$targetSponsor) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $lang["E00074"][$language], 'data' => array('field' => "sponsorUsername"));
        } else {
            $targetSponsorTraceKey = explode("/", $targetSponsor["trace_key"]);
            foreach ($targetSponsorTraceKey as $val) {
                $targetSponsorUplinesID[$val] = $val;
            }

        }

        //get current client's sponsor ID
        $db->where("id", $userID);
        $client = $db->getOne("client", "sponsor_id");
        $currentSponsorID = $client["sponsor_id"];

        // If is the same sponsor, skip it
        if ($targetSponsor["id"] == $currentSponsorID) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "you are changing the same sponsor.", 'data' => array('field' => "sponsorUsername"));
        }

        // If is ownself, skip it
        if ($newSponsorData["id"] == $userID) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "not allow change sponsor to ownself.", 'data' => array('field' => "sponsorUsername"));
        }

        $db->where("user_id", $userID);
        $result = $db->getOne("xun_tree_referral", "trace_key");
        $clientTraceKey = $result["trace_key"];

        if (!$clientTraceKey) {
            // Skip if encounter error
            return array('status' => "error", 'code' => 1, 'statusMsg' => "client not found.", 'data' => array('field' => "sponsorUsername"));
        }

        // Compare level, cannot change to a lower level sponsor in the same tree
        if ($targetSponsorUplinesID[$userID]) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "not allow change sponsor to a lower position in the same genealogy.", 'data' => array('field' => "sponsorUsername"));
        }

        $db->where("trace_key", $clientTraceKey . "%", "LIKE");
        $db->orderby("level", "asc");
        $db->orderby("id", "asc");
        $movingTree = $db->get("xun_tree_referral", null, "user_id,upline_id");

        //get client's data who going move to new tree
        foreach ($movingTree as $key => $val) {
            if ($val["user_id"] == $userID) {
                $movingTree[$key]["upline_id"] = $targetSponsor['id'];
            }

            $movingClientArray[] = $val["user_id"];
        }

        $db->where("id", $userID);
        $db->update("client", array("sponsor_id" => $targetSponsor['id']));

        $db->where("user_id", $movingClientArray, 'IN');
        $db->delete("xun_tree_referral");

        $this->rebuildSponsorTree($movingTree);

        $data['newSponsorID'] = $targetSponsor["id"];
        $data['newSponsorUsername'] = $targetSponsor['username'];
        $data['newSponsorName'] = $targetSponsor['name'];

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "change sponsor successfully.", 'data' => $data);
    }

    public function getSponsorTree($params)
    {
        $db = $this->db;
        $general = $this->general;

        $userID = trim($params["userID"]);
        $targetID = trim($params["targetID"]);
        $viewType = trim($params["viewType"]);
        $viewType = trim($params["viewType"]);
        $targetUsername = trim($params["targetUsername"]);

        $offsetSecs = trim($params['offsetSecs']);

        //get sponsor tree by username if exist
        if ($targetUsername) {
            $db->where("username", $targetUsername);
            $result = $db->getOne("client", "id");
            if (!$result) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "username not found", 'data' => array('field' => "targetUsername"));
            }

            $targetID = $result["id"];
        }

        $db->where("id", $targetID);
        $db->where("type", "Client");
        $targetClient = $db->getOne("client", "id,name,username");
        if (!$targetClient) {
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "Client Not Found", 'data' => array('field' => "targetID"));
        }

        $db->where("user_id", $targetID);
        $result = $db->getOne("xun_tree_referral", "level,trace_key");
        $targetClient["trace_key"] = $result["trace_key"];
        $targetClient["level"] = $result["level"];

        $filterTraceKey = strstr($targetClient["trace_key"], $userID);

        $targetUplinesIDAry = explode("/", $filterTraceKey);
        $db->where("id", $targetUplinesIDAry, "in");
        $targetUplinesClientData = $db->map('id')->ObjectBuilder()->get("client", null, "id,username,name,created_at");

        foreach ($targetUplinesIDAry as $key => $uplineID) {
            $username = $targetUplinesClientData[$uplineID]->username;
            $name = $targetUplinesClientData[$uplineID]->name;
            $createdAt = $targetUplinesClientData[$uplineID]->created_at;

            $tree['attr']['id'] = $uplineID;
            $tree['attr']['name'] = $name;
            $tree['attr']['username'] = $username;

            if ($uplineID == $targetID) {

                $data['target']['attr']['id'] = $uplineID;
                $data['target']['attr']['username'] = $username;
                $data['target']['attr']['name'] = $name;
                $data['target']['attr']['createdAt'] = $general->formatDateTimeToString($createdAt, "d/m/Y");

                $targetLevel = $targetClient["level"];
            }
        }

        $limit = null;

        $db->where('level', $targetClient["level"], '>');
        $db->where('level', $targetClient["level"] + 1, '<=');
        $db->where("trace_key", $targetClient["trace_key"] . "%", "LIKE");
        if ($viewType == "Horizontal") {
            $pageNumber = trim($params["pageNumber"]);
            if (!$pageNumber) {
                $pageNumber = 1;
            }

            $pagingLimit = 5;
            $startLimit = ($pageNumber - 1) * $pagingLimit;
            $limit = array($startLimit, $pagingLimit);

            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue("xun_tree_referral", "count(id)");
            $totalPage = ceil($totalRecord / $pagingLimit);
            $data['totalPage'] = $totalPage;

        }

        $result = $db->get("xun_tree_referral", $limit, "user_id,level,trace_key");

        foreach ($result as $key => $val) {
            $val["depth"] = $val["level"] - $targetLevel;
            $downlineData[] = $val;
            $downlineIDAry[] = $val["user_id"];
        }

        $data['downline'] = [];
        if (count($downlineIDAry) == 0) {
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "no client found", 'data' => $data);
        }

        $db->where("id", $downlineIDAry, "in");
        $targetDownlinesClientData = $db->map('id')->ObjectBuilder()->get("client", null, "id, username, name, created_at, disabled, suspended, freezed");

        foreach ($downlineData as $row) {
            $downlineID = $row["user_id"];

            $downline['attr']['id'] = $downlineID;
            $downline['attr']['username'] = $targetDownlinesClientData[$downlineID]->username;
            $downline['attr']['name'] = $targetDownlinesClientData[$downlineID]->name;
            $downline['attr']['createdAt'] = $general->formatDateTimeToString($targetDownlinesClientData[$downlineID]->created_at, "d/m/Y");
            $downline['attr']['downlineCount'] = count($this->getSponsorTreeDownlines($downlineID, false));
            $downline['attr']['disabled'] = ($targetDownlinesClientData[$downlineID]->disabled == 0) ? 'No' : 'Yes';
            $downline['attr']['suspended'] = ($targetDownlinesClientData[$downlineID]->suspended == 0) ? 'No' : 'Yes';
            $downline['attr']['freezed'] = ($targetDownlinesClientData[$downlineID]->freezed == 0) ? 'No' : 'Yes';

            $data['downline'][] = $downline;
            unset($downline);

        }

        $data['targetID'] = (trim($params["clientID"]) == trim($params["targetID"])) ? '' : trim($params["targetID"]);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
    }

    public function getSponsorTreeByUsername($params, $userID)
    {
        $db = $this->db;

        $userID = trim($params["userID"]);
        $targetUsername = trim($params->targetUsername);
        $viewType = trim($params->viewType);

        $db->where("username", $targetUsername);
        $result = $db->getOne("client", "id");
        if (!$result) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => "username not found", 'data' => array('field' => "targetUsername"));
        }

        $targetID = $result["id"];

        $db->where("user_id", $targetID);
        $result = $db->get("xun_tree_referral", "user_id, trace_key");
        $targetTraceKey = $result["trace_key"];
        $targetTraceKeyAry = explode("/", $targetTraceKey);
        foreach ($targetTraceKeyAry as $val) {
            $targetDownlineIDArray[$val] = $val;
        }

        // Target user not Downline
        if (!$targetDownlineIDArray[$userID]) {
            return array('status' => "error", 'code' => 1, 'statusMsg' => $lang['E00114'][$language], 'data' => array("field" => "targetUsername"));
        }

        $sponsorTree = $this->getSponsorTree($params);

        return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $sponsorTree['data']);
    }

    /**
     * Function for getting the Downline.
     * @param  $userID Integer.
     * @author Rakesh.
     **/
    public function getMyDownlinesByClientID($userID)
    {
        $db = $this->db;
        $db->where("sponsor_id", $userID);
        $db->orderby("id", "asc");
        $result = $db->get("client", null, "id");
        foreach ($result as $row) {
            $downlines[] = $row["id"];
        }
        return $downlines;
    }

    public function getNumberOfDownline($userID){
        $db = $this->db;

        $db->where("upline_id", $userID);
        $count = $db->getValue ("xun_tree_referral", "count(id)");

        return $count;
    }
}
